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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LearnerController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authSchool($request, $schoolId)) {
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
        if ($deny = $this->authSchool($request, $schoolId)) {
            return $deny;
        }

        $data = $this->validateLearner($request);
        $learner = $this->createLearner($schoolId, $data, $request->user());

        return response()->json($this->serialize($learner->load(['instructor', 'vehicle', 'package']), full: true), 201);
    }

    public function convertInquiry(Request $request, int $inquiryId): JsonResponse
    {
        $inquiry = Inquiry::withoutGlobalScope('school')->find($inquiryId);

        if (! $inquiry) {
            return response()->json(['message' => 'Inquiry not found'], 404);
        }

        if ($deny = $this->authSchool($request, (int) $inquiry->school_id)) {
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

        $extra = $request->validate([
            'packageId' => ['nullable', 'integer'],
            'assignedInstructorId' => ['nullable', 'integer'],
            'assignedVehicleId' => ['nullable', 'integer'],
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
        ], $request->user());

        $previous = $inquiry->status;
        $inquiry->update(['status' => 'converted']);

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

        if ($deny = $this->authSchool($request, (int) $learner->school_id)) {
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

        if ($deny = $this->authSchool($request, (int) $learner->school_id)) {
            return $deny;
        }

        $data = $this->validateLearner($request, partial: true);
        $payload = $this->mapLearnerPayload($data);

        $old = $learner->only(array_keys($payload));
        $learner->fill($payload)->save();

        AuditLog::log('update', 'Learner', $learner->id, $old, $payload);

        return response()->json($this->serialize($learner->fresh(['instructor', 'vehicle', 'package']), full: true));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);

        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }

        if ($deny = $this->authSchool($request, (int) $learner->school_id)) {
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

            Instructor::withoutGlobalScope('school')
                ->where('id', $instructorId)
                ->increment('total_learners_trained');
        }

        AuditLog::log('assign', 'Learner', $learner->id, [], [
            'instructor_id' => $instructorId,
            'vehicle_id' => $vehicleId,
        ]);

        return response()->json($this->serialize($learner->fresh(['instructor', 'vehicle', 'package']), full: true));
    }

    public function listDocuments(Request $request, int $id): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->authSchool($request, (int) $learner->school_id)) {
            return $deny;
        }

        $docs = LearnerDocument::withoutGlobalScope('school')
            ->where('learner_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (LearnerDocument $d) => $this->serializeDoc($d));

        return response()->json($docs);
    }

    public function addDocument(Request $request, int $id): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->authSchoolOrLearner($request, $learner)) {
            return $deny;
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:aadhaar,pan,photo,learner_license,medical,other'],
            'filePath' => ['nullable', 'string', 'max:2048'],
            'fileName' => ['nullable', 'string', 'max:255'],
            'expiryDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $doc = LearnerDocument::withoutGlobalScope('school')->create([
            'learner_id' => $learner->id,
            'school_id' => $learner->school_id,
            'type' => $data['type'],
            'file_path' => $data['filePath'] ?? null,
            'file_name' => $data['fileName'] ?? null,
            'expiry_date' => $data['expiryDate'] ?? null,
            'status' => ! empty($data['filePath']) ? 'uploaded' : 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($this->serializeDoc($doc), 201);
    }

    public function updateDocument(Request $request, int $id, int $docId): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($id);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->authSchool($request, (int) $learner->school_id)) {
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
            'status' => ['required', 'string', 'in:pending,uploaded,verified,rejected'],
            'notes' => ['nullable', 'string', 'max:500'],
            'filePath' => ['nullable', 'string', 'max:2048'],
            'fileName' => ['nullable', 'string', 'max:255'],
            'expiryDate' => ['nullable', 'date'],
        ]);

        $doc->fill([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $doc->notes,
            'file_path' => $data['filePath'] ?? $doc->file_path,
            'file_name' => $data['fileName'] ?? $doc->file_name,
            'expiry_date' => $data['expiryDate'] ?? $doc->expiry_date,
        ]);

        if (in_array($data['status'], ['verified', 'rejected'], true)) {
            $doc->verified_by = $request->user()->id;
            $doc->verified_at = now();
        }

        $doc->save();

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
        $userId = null;
        if (! empty($data['create_login']) && ! empty($data['email'])) {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();
            if (! $user) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'learner',
                    'school_id' => $schoolId,
                ]);
            } else {
                $user->update([
                    'role' => $user->isAdmin() ? $user->role : 'learner',
                    'school_id' => $schoolId,
                ]);
            }
            $userId = $user->id;
        }

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
        }

        AuditLog::log('create', 'Learner', $learner->id, [], ['name' => $learner->name]);

        return $learner;
    }

    private function validateLearner(Request $request, bool $partial = false): array
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
            'packageId' => ['nullable', 'integer'],
            'startDate' => ['nullable', 'date'],
            'expectedCompletionDate' => ['nullable', 'date'],
            'assignedInstructorId' => ['nullable', 'integer'],
            'assignedVehicleId' => ['nullable', 'integer'],
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
            'filePath' => $d->file_path,
            'fileName' => $d->file_name,
            'status' => $d->status,
            'expiryDate' => $d->expiry_date?->toDateString(),
            'verifiedAt' => $d->verified_at?->toISOString(),
            'notes' => $d->notes,
        ];
    }

    private function authSchool(Request $request, int $schoolId): ?JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }
        $user = $request->user();
        if ($user->isAdmin() || ($user->isSchool() && (int) $user->school_id === $schoolId)) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }

    private function authSchoolOrLearner(Request $request, Learner $learner): ?JsonResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return null;
        }
        if ($user->isSchool() && (int) $user->school_id === (int) $learner->school_id) {
            return null;
        }
        if ($user->role === 'learner' && (int) $user->id === (int) $learner->user_id) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
