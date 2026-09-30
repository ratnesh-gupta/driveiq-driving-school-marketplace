<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Instructor;
use App\Models\LeadStatusHistory;
use App\Models\Learner;
use App\Models\LearnerAssignmentHistory;
use App\Models\LearnerDocument;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\NotificationService;
use App\Support\DocumentStorage;
use App\Support\SchoolScopedIds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LearnerController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $query = Learner::withoutGlobalScope('school')
            ->with(['instructor:id,name', 'vehicle:id,registration_number', 'package:id,name,price'])
            ->where('school_id', $schoolId)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json(
            $query->limit(200)->get()->map(fn (Learner $l) => $this->serialize($l, full: true))
        );
    }

    public function store(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        // createLearner() expects snake_case (same mapping as update()).
        $data = $this->mapLearnerPayload($this->validateLearner($request, $schoolId));
        $learner = $this->createLearner($schoolId, $data, $request->user());

        return response()->json($this->serialize($learner->load(['instructor', 'vehicle', 'package']), full: true), 201);
    }

    public function convertInquiry(Request $request, int $inquiryId): JsonResponse
    {
        $inquiry = Inquiry::withoutGlobalScope('school')->find($inquiryId);

        if (! $inquiry) {
            return response()->json(['message' => 'Inquiry not found'], 404);
        }

        if ($deny = $this->access()->school($request, (int) $inquiry->school_id)) {
            return $deny;
        }

        $existing = Learner::withoutGlobalScope('school')
            ->where('converted_from_inquiry_id', $inquiry->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Inquiry already converted',
                'learner' => $this->serialize($existing, full: true),
            ], 422);
        }

        $schoolId = (int) $inquiry->school_id;
        $extra = $request->validate([
            'packageId' => ['nullable', 'integer', SchoolScopedIds::package($schoolId)],
            'assignedInstructorId' => ['nullable', 'integer', SchoolScopedIds::instructor($schoolId)],
            'assignedVehicleId' => ['nullable', 'integer', SchoolScopedIds::vehicle($schoolId)],
            'startDate' => ['nullable', 'date'],
            'createLogin' => ['nullable', 'boolean'],
        ]);

        $learner = $this->createLearner((int) $inquiry->school_id, [
            'name' => $inquiry->name,
            'mobile' => $inquiry->phone,
            'email' => $inquiry->email,
            'vehicle_type' => $inquiry->vehicle_type,
            'package_id' => $extra['packageId'] ?? null,
            'assigned_instructor_id' => $extra['assignedInstructorId'] ?? null,
            'assigned_vehicle_id' => $extra['assignedVehicleId'] ?? null,
            'start_date' => $extra['startDate'] ?? now()->toDateString(),
            'converted_from_inquiry_id' => $inquiry->id,
            'status' => 'active',
            'create_login' => (bool) ($extra['createLogin'] ?? false),
            // Only the account that submitted this enquiry itself may be linked.
            'link_user_id' => $inquiry->user_id,
        ], $request->user());

        $previous = $inquiry->status;
        $inquiry->update(['status' => 'converted', 'next_follow_up_at' => null]);
        $inquiry->markResponded();

        LeadStatusHistory::create([
            'inquiry_id' => $inquiry->id,
            'from_status' => $previous,
            'to_status' => 'converted',
            'changed_by_id' => $request->user()->id,
        ]);

        AuditLog::log('convert', 'Inquiry', $inquiry->id, ['status' => $previous], [
            'status' => 'converted',
            'learner_id' => $learner->id,
        ]);

        return response()->json($this->serialize($learner->load(['instructor', 'vehicle', 'package']), full: true), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')
            ->with(['instructor:id,name', 'vehicle:id,registration_number,type', 'package:id,name,price', 'documents'])
            ->find($id);

        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }

        if ($deny = $this->access()->school($request, (int) $learner->school_id)) {
            return $deny;
        }

        return response()->json($this->serialize($learner, full: true, withDocs: true));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);

        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }

        if ($deny = $this->access()->school($request, (int) $learner->school_id)) {
            return $deny;
        }

        $data = $this->validateLearner($request, (int) $learner->school_id, partial: true);
        $payload = $this->mapLearnerPayload($data);

        $old = $learner->only(array_keys($payload));
        $learner->fill($payload);
        $reassigned = $learner->isDirty(['assigned_instructor_id', 'assigned_vehicle_id']);
        $learner->save();

        // Editing the trainer or vehicle here is an assignment too; keep its history.
        if ($reassigned) {
            LearnerAssignmentHistory::withoutGlobalScope('school')->create([
                'learner_id' => $learner->id,
                'school_id' => $learner->school_id,
                'instructor_id' => $learner->assigned_instructor_id,
                'vehicle_id' => $learner->assigned_vehicle_id,
                'assigned_by' => $request->user()->id,
                'action' => 'reassign',
            ]);
            Instructor::refreshLearnerCount($learner->assigned_instructor_id);
        }

        AuditLog::log('update', 'Learner', $learner->id, $old, $payload);

        return response()->json($this->serialize($learner->fresh(['instructor', 'vehicle', 'package']), full: true));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);

        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }

        if ($deny = $this->access()->school($request, (int) $learner->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'instructorId' => ['nullable', 'integer'],
            'vehicleId' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $instructorId = $data['instructorId'] ?? null;
        $vehicleId = $data['vehicleId'] ?? null;

        if ($instructorId) {
            $ok = Instructor::withoutGlobalScope('school')
                ->where('id', $instructorId)
                ->where('school_id', $learner->school_id)
                ->where('status', 'active')
                ->exists();
            if (! $ok) {
                return response()->json(['message' => 'Instructor not available'], 422);
            }
        }

        if ($vehicleId) {
            $ok = Vehicle::withoutGlobalScope('school')
                ->where('id', $vehicleId)
                ->where('school_id', $learner->school_id)
                ->where('status', 'active')
                ->exists();
            if (! $ok) {
                return response()->json(['message' => 'Vehicle not available'], 422);
            }
        }

        $action = (! $learner->assigned_instructor_id && $instructorId) ? 'assign' : 'reassign';

        $learner->update([
            'assigned_instructor_id' => $instructorId,
            'assigned_vehicle_id' => $vehicleId,
        ]);

        LearnerAssignmentHistory::withoutGlobalScope('school')->create([
            'learner_id' => $learner->id,
            'school_id' => $learner->school_id,
            'instructor_id' => $instructorId,
            'vehicle_id' => $vehicleId,
            'assigned_by' => $request->user()->id,
            'action' => $action,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($instructorId) {
            $instructor = Instructor::withoutGlobalScope('school')->with('user')->find($instructorId);
            if ($instructor?->user) {
                $this->notifications->notify(
                    $instructor->user,
                    'learner_assigned',
                    'New learner assigned',
                    "{$learner->name} has been assigned to you.",
                    ['learnerId' => $learner->id],
                    (int) $learner->school_id
                );
            }

            Instructor::refreshLearnerCount($instructorId);
        }

        AuditLog::log('assign', 'Learner', $learner->id, [], [
            'instructor_id' => $instructorId,
            'vehicle_id' => $vehicleId,
        ]);

        return response()->json($this->serialize($learner->fresh(['instructor', 'vehicle', 'package']), full: true));
    }

    /** Trainer and vehicle assignment history, newest first (DIQ-906). */
    public function assignments(Request $request, int $id): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->access()->school($request, (int) $learner->school_id)) {
            return $deny;
        }

        $rows = LearnerAssignmentHistory::withoutGlobalScope('school')
            ->where('learner_id', $learner->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $instructors = Instructor::withoutGlobalScope('school')->whereIn('id', $rows->pluck('instructor_id')->filter())->pluck('name', 'id');
        $vehicles = Vehicle::withoutGlobalScope('school')->whereIn('id', $rows->pluck('vehicle_id')->filter())->pluck('registration_number', 'id');
        $users = User::whereIn('id', $rows->pluck('assigned_by')->filter())->pluck('name', 'id');

        return response()->json($rows->map(fn (LearnerAssignmentHistory $h) => [
            'id' => $h->id,
            'action' => $h->action,
            'instructorId' => $h->instructor_id,
            'instructorName' => $instructors[$h->instructor_id] ?? null,
            'vehicleId' => $h->vehicle_id,
            'vehicleRegistration' => $vehicles[$h->vehicle_id] ?? null,
            'assignedBy' => $users[$h->assigned_by] ?? null,
            'notes' => $h->notes,
            'createdAt' => $h->created_at?->toISOString(),
        ])->values());
    }

    public function listDocuments(Request $request, int $id): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->access()->learner($request, $learner, allowSelf: true)) {
            return $deny;
        }

        $docs = LearnerDocument::withoutGlobalScope('school')
            ->where('learner_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (LearnerDocument $d) => $this->serializeDoc($d));

        return response()->json($docs);
    }

    public function addDocument(Request $request, int $id, DocumentStorage $storage): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->access()->learner($request, $learner, allowSelf: true)) {
            return $deny;
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:aadhaar,pan,photo,learner_license,medical,other'],
            'file' => ['nullable', ...DocumentStorage::FILE_RULE],
            'expiryDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('file');
        $schoolId = (int) $learner->school_id;

        $doc = LearnerDocument::withoutGlobalScope('school')->create([
            'learner_id' => $learner->id,
            'school_id' => $schoolId,
            'type' => $data['type'],
            'file_path' => $file ? $storage->store($file, $schoolId, 'learners', $learner->id) : null,
            'file_name' => $file ? DocumentStorage::displayName($file) : null,
            'expiry_date' => $data['expiryDate'] ?? null,
            'status' => $file ? 'uploaded' : 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($this->serializeDoc($doc), 201);
    }

    /**
     * School staff review a document (verify / reject) and may attach or
     * replace its file. Send as multipart POST with _method=PATCH to upload.
     */
    public function updateDocument(Request $request, int $id, int $docId, DocumentStorage $storage): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->access()->school($request, (int) $learner->school_id)) {
            return $deny;
        }

        $doc = LearnerDocument::withoutGlobalScope('school')
            ->where('learner_id', $id)
            ->where('id', $docId)
            ->first();

        if (! $doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $data = $request->validate([
            // pending/uploaded follow from the file; staff only verify or reject.
            'status' => ['required_without:file', 'string', 'in:verified,rejected'],
            'notes' => ['nullable', 'string', 'max:500'],
            'file' => ['nullable', ...DocumentStorage::FILE_RULE],
            'expiryDate' => ['nullable', 'date'],
        ]);

        $previous = $doc->status;

        if ($file = $request->file('file')) {
            $old = $doc->file_path;
            $doc->file_path = $storage->store($file, (int) $doc->school_id, 'learners', $learner->id);
            $doc->file_name = DocumentStorage::displayName($file);
            $doc->status = 'uploaded';
            // A new file has not been checked by anyone yet.
            $doc->verified_by = null;
            $doc->verified_at = null;
            $storage->delete($old, (int) $doc->school_id);
        }

        if (($data['status'] ?? null) === 'verified' && $doc->file_path === null) {
            throw ValidationException::withMessages(['status' => 'Upload the file before verifying it.']);
        }

        $doc->fill([
            'status' => $data['status'] ?? $doc->status,
            'notes' => $data['notes'] ?? $doc->notes,
            'expiry_date' => $data['expiryDate'] ?? $doc->expiry_date,
        ]);

        if (isset($data['status'])) {
            $doc->verified_by = $request->user()->id;
            $doc->verified_at = now();
        }

        $doc->save();

        if ($doc->status !== $previous) {
            AuditLog::log('document_'.$doc->status, 'LearnerDocument', $doc->id, ['status' => $previous], ['status' => $doc->status], (int) $doc->school_id);
        }

        return response()->json($this->serializeDoc($doc));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $learner = Learner::withoutGlobalScope('school')
            ->with(['instructor:id,name,mobile', 'vehicle:id,registration_number,type', 'package:id,name,price', 'documents'])
            ->where('user_id', $user->id)
            ->first();

        if (! $learner) {
            return response()->json(['message' => 'Learner profile not found'], 404);
        }

        $sessions = Schedule::withoutGlobalScope('school')
            ->where('learner_id', $learner->id)
            ->where('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(20)
            ->get(['id', 'session_date', 'start_time', 'end_time', 'status', 'pickup_location', 'instructor_id']);

        return response()->json([
            'learner' => $this->serialize($learner, full: true, withDocs: true),
            'upcomingSessions' => $sessions->map(fn (Schedule $s) => [
                'id' => $s->id,
                'sessionDate' => $s->session_date?->toDateString(),
                'startTime' => substr((string) $s->start_time, 0, 5),
                'endTime' => substr((string) $s->end_time, 0, 5),
                'status' => $s->status,
                'pickupLocation' => $s->pickup_location,
            ]),
        ]);
    }

    private function createLearner(int $schoolId, array $data, User $actor): Learner
    {
        $user = $this->learnerAccountFor($schoolId, $data);
        $userId = $user?->id;

        $learner = Learner::withoutGlobalScope('school')->create([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'converted_from_inquiry_id' => $data['converted_from_inquiry_id'] ?? null,
            'name' => $data['name'],
            'mobile' => $data['mobile'] ?? null,
            'email' => isset($data['email']) ? strtolower($data['email']) : null,
            'gender' => $data['gender'] ?? null,
            'dob' => $data['dob'] ?? null,
            'address' => $data['address'] ?? null,
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'expected_completion_date' => $data['expected_completion_date'] ?? null,
            'assigned_instructor_id' => $data['assigned_instructor_id'] ?? null,
            'assigned_vehicle_id' => $data['assigned_vehicle_id'] ?? null,
            'learner_license_number' => $data['learner_license_number'] ?? null,
            'license_issue_date' => $data['license_issue_date'] ?? null,
            'license_expiry_date' => $data['license_expiry_date'] ?? null,
            'permanent_license_status' => $data['permanent_license_status'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        if ($learner->assigned_instructor_id || $learner->assigned_vehicle_id) {
            LearnerAssignmentHistory::withoutGlobalScope('school')->create([
                'learner_id' => $learner->id,
                'school_id' => $schoolId,
                'instructor_id' => $learner->assigned_instructor_id,
                'vehicle_id' => $learner->assigned_vehicle_id,
                'assigned_by' => $actor->id,
                'action' => 'assign',
            ]);
            Instructor::refreshLearnerCount($learner->assigned_instructor_id);
        }

        AuditLog::log('create', 'Learner', $learner->id, [], ['name' => $learner->name]);

        if ($user) {
            $schoolName = School::whereKey($schoolId)->value('name');
            $this->notifications->notify(
                $user,
                'learner_enrolled',
                'You are enrolled',
                "{$schoolName} has enrolled you. Your trainer, sessions and progress now appear in your portal.",
                ['learnerId' => $learner->id],
                $schoolId
            );
        }

        return $learner;
    }

    /**
     * The login account to attach to a new learner record (DIQ-406), or null.
     *
     * - Consent: an existing learner account is linked only when that learner
     *   submitted the converted enquiry while signed in (link_user_id), and
     *   has no school yet. Knowing someone's email is never enough.
     * - With create_login and no existing account, a new learner account is
     *   created (they set a password via "forgot password").
     * - Existing accounts are otherwise never modified; asking to create a
     *   login for an email that already has an account is rejected.
     */
    private function learnerAccountFor(int $schoolId, array $data): ?User
    {
        if (! empty($data['link_user_id'])) {
            $user = User::find($data['link_user_id']);
            if ($user?->isLearner() && ($user->school_id === null || (int) $user->school_id === $schoolId)) {
                if ($user->school_id === null) {
                    $user->forceFill(['school_id' => $schoolId])->save();
                }

                return $user;
            }
        }

        if (empty($data['email']) || empty($data['create_login'])) {
            return null;
        }

        $email = strtolower($data['email']);

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email already belongs to another DriveIQ account.',
            ]);
        }

        return User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => Hash::make(Str::random(24)),
            'role' => 'learner',
            'school_id' => $schoolId,
        ]);
    }

    private function validateLearner(Request $request, int $schoolId, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergencyContact' => ['nullable', 'string', 'max:100'],
            'vehicleType' => ['nullable', 'string', 'max:50'],
            'packageId' => ['nullable', 'integer', SchoolScopedIds::package($schoolId)],
            'startDate' => ['nullable', 'date'],
            'expectedCompletionDate' => ['nullable', 'date'],
            'assignedInstructorId' => ['nullable', 'integer', SchoolScopedIds::instructor($schoolId)],
            'assignedVehicleId' => ['nullable', 'integer', SchoolScopedIds::vehicle($schoolId)],
            'learnerLicenseNumber' => ['nullable', 'string', 'max:50'],
            'licenseIssueDate' => ['nullable', 'date'],
            'licenseExpiryDate' => ['nullable', 'date'],
            'permanentLicenseStatus' => ['nullable', 'string', 'in:none,applied,passed,issued'],
            'status' => ['sometimes', 'string', 'in:active,inactive,completed,suspended'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'createLogin' => ['nullable', 'boolean'],
        ]);
    }

    private function mapLearnerPayload(array $data): array
    {
        $map = [
            'name' => 'name',
            'mobile' => 'mobile',
            'email' => 'email',
            'gender' => 'gender',
            'dob' => 'dob',
            'address' => 'address',
            'emergencyContact' => 'emergency_contact',
            'vehicleType' => 'vehicle_type',
            'packageId' => 'package_id',
            'startDate' => 'start_date',
            'expectedCompletionDate' => 'expected_completion_date',
            'assignedInstructorId' => 'assigned_instructor_id',
            'assignedVehicleId' => 'assigned_vehicle_id',
            'learnerLicenseNumber' => 'learner_license_number',
            'licenseIssueDate' => 'license_issue_date',
            'licenseExpiryDate' => 'license_expiry_date',
            'permanentLicenseStatus' => 'permanent_license_status',
            'status' => 'status',
            'notes' => 'notes',
        ];

        $payload = [];
        foreach ($map as $c => $s) {
            if (array_key_exists($c, $data)) {
                $payload[$s] = $c === 'email' && $data[$c] ? strtolower($data[$c]) : $data[$c];
            }
        }

        if (array_key_exists('createLogin', $data)) {
            $payload['create_login'] = $data['createLogin'];
        }

        return $payload;
    }

    private function serialize(Learner $l, bool $full = false, bool $withDocs = false): array
    {
        $base = [
            'id' => $l->id,
            'schoolId' => $l->school_id,
            'name' => $l->name,
            'mobile' => $l->mobile,
            'email' => $l->email,
            'vehicleType' => $l->vehicle_type,
            'status' => $l->status,
            'assignedInstructorId' => $l->assigned_instructor_id,
            'instructorName' => $l->instructor?->name,
            'assignedVehicleId' => $l->assigned_vehicle_id,
            'vehicleRegistration' => $l->vehicle?->registration_number,
            'packageId' => $l->package_id,
            'packageName' => $l->package?->name,
            'startDate' => $l->start_date?->toDateString(),
        ];

        if (! $full) {
            return $base;
        }

        $fullData = array_merge($base, [
            'userId' => $l->user_id,
            'convertedFromInquiryId' => $l->converted_from_inquiry_id,
            'gender' => $l->gender,
            'dob' => $l->dob?->toDateString(),
            'address' => $l->address,
            'emergencyContact' => $l->emergency_contact,
            'expectedCompletionDate' => $l->expected_completion_date?->toDateString(),
            'learnerLicenseNumber' => $l->learner_license_number,
            'licenseIssueDate' => $l->license_issue_date?->toDateString(),
            'licenseExpiryDate' => $l->license_expiry_date?->toDateString(),
            'permanentLicenseStatus' => $l->permanent_license_status,
            'notes' => $l->notes,
        ]);

        if ($withDocs) {
            $fullData['documents'] = ($l->documents ?? collect())->map(fn ($d) => $this->serializeDoc($d))->values();
        }

        return $fullData;
    }

    private function serializeDoc(LearnerDocument $d): array
    {
        return [
            'id' => $d->id,
            'type' => $d->type,
            'learnerId' => $d->learner_id,
            'hasFile' => $d->file_path !== null,
            'fileName' => $d->file_name,
            'status' => $d->status,
            'expiryDate' => $d->expiry_date?->toDateString(),
            'verifiedAt' => $d->verified_at?->toISOString(),
            'notes' => $d->notes,
            'createdAt' => $d->created_at?->toISOString(),
        ];
    }
}
