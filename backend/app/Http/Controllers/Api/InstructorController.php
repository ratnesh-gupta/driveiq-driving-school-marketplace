<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\InstructorDocument;
use App\Models\Learner;
use App\Models\Schedule;
use App\Models\School;
use App\Models\TrainingProgress;
use App\Models\User;
use App\Notifications\StaffLoginInvite;
use App\Services\NotificationService;
use App\Support\DocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstructorController extends Controller
{
    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId, allowInstructor: true)) {
            return $deny;
        }

        $status = $request->query('status');

        $query = Instructor::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->orderBy('name');

        if ($status) {
            $query->where('status', $status);
        }

        return response()->json(
            $query->get()->map(fn (Instructor $i) => $this->serialize($i, full: true))
        );
    }

    public function store(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId, allowInstructor: true)) {
            return $deny;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'employeeId' => ['nullable', 'string', 'max:50'],
            'joiningDate' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,inactive,terminated'],
            'employmentType' => ['nullable', 'string', 'in:full_time,part_time,contract'],
            'licenseNumber' => ['nullable', 'string', 'max:50'],
            'licenseCategory' => ['nullable', 'string', 'max:20'],
            'licenseExpiry' => ['nullable', 'date'],
            'yearsExperience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skills' => ['nullable', 'array'],
            'languages' => ['nullable', 'array'],
            'womenInstructor' => ['nullable', 'boolean'],
            'publicVisible' => ['nullable', 'boolean'],
            'photoUrl' => ['nullable', 'string', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'createLogin' => ['nullable', 'boolean'],
        ]);

        $login = null;
        if (! empty($data['createLogin']) && ! empty($data['email'])) {
            $login = $this->loginFor($schoolId, $data['name'], $data['email']);
            if ($login instanceof JsonResponse) {
                return $login;
            }
        }
        $userId = $login?->id;

        $instructor = Instructor::withoutGlobalScope('school')->create([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'name' => $data['name'],
            'mobile' => $data['mobile'] ?? null,
            'email' => isset($data['email']) ? strtolower($data['email']) : null,
            'gender' => $data['gender'] ?? null,
            'dob' => $data['dob'] ?? null,
            'address' => $data['address'] ?? null,
            'employee_id' => $data['employeeId'] ?? null,
            'joining_date' => $data['joiningDate'] ?? null,
            'status' => $data['status'] ?? 'active',
            'employment_type' => $data['employmentType'] ?? 'full_time',
            'license_number' => $data['licenseNumber'] ?? null,
            'license_category' => $data['licenseCategory'] ?? null,
            'license_expiry' => $data['licenseExpiry'] ?? null,
            'years_experience' => $data['yearsExperience'] ?? 0,
            'skills' => $data['skills'] ?? [],
            'languages' => $data['languages'] ?? [],
            'women_instructor' => (bool) ($data['womenInstructor'] ?? false),
            'public_visible' => (bool) ($data['publicVisible'] ?? false),
            'photo_url' => $data['photoUrl'] ?? null,
            'bio' => $data['bio'] ?? null,
        ]);

        // Keep school women_instructor flag in sync if any trainer qualifies
        if ($instructor->women_instructor) {
            School::where('id', $schoolId)->update(['women_instructor' => true]);
        }

        AuditLog::log('create', 'Instructor', $instructor->id, [], ['name' => $instructor->name]);

        if ($login?->wasRecentlyCreated) {
            $login->notify(new StaffLoginInvite(School::find($schoolId)));
        }

        return response()->json($this->serialize($instructor, full: true), 201);
    }

    /**
     * Create (or resend) the trainer's portal login. A new account gets an
     * email to set its password; nothing is ever shown to the school.
     */
    public function sendLogin(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);
        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        if ($deny = $this->access()->school($request, (int) $instructor->school_id)) {
            return $deny;
        }

        $data = $request->validate(['email' => ['nullable', 'email', 'max:255']]);
        $schoolId = (int) $instructor->school_id;

        if ($instructor->user_id) {
            $user = User::find($instructor->user_id);
        } else {
            $email = $data['email'] ?? $instructor->email;
            if (! $email) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['email' => ['Add the trainer\'s email first.']],
                ], 422);
            }
            $user = $this->loginFor($schoolId, $instructor->name, $email);
            if ($user instanceof JsonResponse) {
                return $user;
            }
            $instructor->update(['user_id' => $user->id, 'email' => $instructor->email ?: strtolower($email)]);
        }

        $user->notify(new StaffLoginInvite(School::find($schoolId)));
        AuditLog::log('send_login', 'Instructor', $instructor->id, [], ['user_id' => $user->id], $schoolId);

        return response()->json($this->serialize($instructor->fresh(), full: true));
    }

    /**
     * The login to attach to a trainer: a new instructor account, or an
     * existing instructor login of this school that has no profile yet.
     * Never repurposes anyone else's account (another school's owner, a
     * learner, an admin...).
     */
    private function loginFor(int $schoolId, string $name, string $email): User|JsonResponse
    {
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        if (! $user) {
            return User::create([
                'name' => $name,
                'email' => strtolower($email),
                'password' => Hash::make(Str::random(40)),
                'role' => 'instructor',
                'school_id' => $schoolId,
            ]);
        }

        $attachable = $user->isInstructor()
            && (int) $user->school_id === $schoolId
            && ! Instructor::withoutGlobalScope('school')->where('user_id', $user->id)->exists();

        if (! $attachable) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['email' => ['This email already belongs to another DriveIQ account.']],
            ], 422);
        }

        return $user;
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->access()->school($request, (int) $instructor->school_id, allowInstructor: true)) {
            return $deny;
        }

        return response()->json($this->serialize($instructor, full: true));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->access()->school($request, (int) $instructor->school_id, allowInstructor: true)) {
            return $deny;
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'employeeId' => ['nullable', 'string', 'max:50'],
            'joiningDate' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:active,inactive,terminated'],
            'employmentType' => ['sometimes', 'string', 'in:full_time,part_time,contract'],
            'licenseNumber' => ['nullable', 'string', 'max:50'],
            'licenseCategory' => ['nullable', 'string', 'max:20'],
            'licenseExpiry' => ['nullable', 'date'],
            'yearsExperience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skills' => ['nullable', 'array'],
            'languages' => ['nullable', 'array'],
            'womenInstructor' => ['nullable', 'boolean'],
            'publicVisible' => ['nullable', 'boolean'],
            'photoUrl' => ['nullable', 'string', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        $map = [
            'name' => 'name',
            'mobile' => 'mobile',
            'email' => 'email',
            'gender' => 'gender',
            'dob' => 'dob',
            'address' => 'address',
            'employeeId' => 'employee_id',
            'joiningDate' => 'joining_date',
            'status' => 'status',
            'employmentType' => 'employment_type',
            'licenseNumber' => 'license_number',
            'licenseCategory' => 'license_category',
            'licenseExpiry' => 'license_expiry',
            'yearsExperience' => 'years_experience',
            'skills' => 'skills',
            'languages' => 'languages',
            'womenInstructor' => 'women_instructor',
            'publicVisible' => 'public_visible',
            'photoUrl' => 'photo_url',
            'bio' => 'bio',
        ];

        $payload = [];
        foreach ($map as $camel => $snake) {
            if (array_key_exists($camel, $data)) {
                $payload[$snake] = $data[$camel];
            }
        }
        if (isset($payload['email']) && $payload['email']) {
            $payload['email'] = strtolower($payload['email']);
        }

        $old = $instructor->only(array_keys($payload));
        $instructor->fill($payload);
        $instructor->save();

        AuditLog::log('update', 'Instructor', $instructor->id, $old, $payload);

        return response()->json($this->serialize($instructor->fresh(), full: true));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->access()->school($request, (int) $instructor->school_id, ownerOnly: true)) {
            return $deny;
        }

        $instructor->update(['status' => 'terminated', 'public_visible' => false]);

        AuditLog::log('deactivate', 'Instructor', $instructor->id, ['status' => 'active'], ['status' => 'terminated']);

        return response()->json(null, 204);
    }

    public function publicTrainers(string $slug): JsonResponse
    {
        $school = School::where('slug', $slug)->first();

        if (! $school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $trainers = Instructor::withoutGlobalScope('school')
            ->publicVisible()
            ->where('school_id', $school->id)
            ->orderByDesc('rating_average')
            ->orderBy('name')
            ->get()
            ->map(fn (Instructor $i) => $this->serializePublic($i));

        return response()->json($trainers);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $instructor = Instructor::withoutGlobalScope('school')
            ->where('user_id', $user->id)
            ->first();

        if (! $instructor) {
            return response()->json(['message' => 'Instructor profile not found'], 404);
        }

        return response()->json([
            'instructor' => $this->serialize($instructor, full: true),
            'dashboard' => $this->dashboardFor($instructor),
        ]);
    }

    /**
     * The trainer's own roster (DIQ-908): learners assigned to them or with
     * a session with them, the same rule that grants progress access.
     */
    public function myLearners(Request $request): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->where('user_id', $request->user()->id)->first();
        if (! $instructor) {
            return response()->json(['message' => 'Instructor profile not found'], 404);
        }

        $sessionLearnerIds = Schedule::withoutGlobalScope('school')
            ->where('instructor_id', $instructor->id)
            ->whereNotNull('learner_id')
            ->distinct()
            ->pluck('learner_id');

        $learners = Learner::withoutGlobalScope('school')
            ->with('package:id,name')
            ->where('school_id', $instructor->school_id)
            ->where(fn ($q) => $q->where('assigned_instructor_id', $instructor->id)->orWhereIn('id', $sessionLearnerIds))
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('name')
            ->limit(200)
            ->get();

        $ids = $learners->pluck('id');
        $progress = TrainingProgress::withoutGlobalScope('school')
            ->whereIn('learner_id', $ids)
            ->selectRaw('learner_id, sum(percentage) as total')
            ->groupBy('learner_id')
            ->pluck('total', 'learner_id');
        $next = Schedule::withoutGlobalScope('school')
            ->where('instructor_id', $instructor->id)
            ->whereIn('learner_id', $ids)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->where('session_date', '>=', now()->toDateString())
            ->selectRaw('learner_id, min(session_date) as next_date')
            ->groupBy('learner_id')
            ->pluck('next_date', 'learner_id');
        $skills = count(TrainingProgress::SKILLS);

        return response()->json($learners->map(fn (Learner $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'mobile' => $l->mobile,
            'status' => $l->status,
            'vehicleType' => $l->vehicle_type,
            'packageName' => $l->package?->name,
            'assignedToMe' => (int) $l->assigned_instructor_id === (int) $instructor->id,
            'overallCompletion' => (int) round(((float) ($progress[$l->id] ?? 0)) / $skills),
            'nextSessionDate' => isset($next[$l->id]) ? substr((string) $next[$l->id], 0, 10) : null,
        ])->values());
    }

    /** Today's and the next seven days' sessions, current learners and attendance record. */
    private function dashboardFor(Instructor $instructor): array
    {
        $today = now()->toDateString();
        $open = fn () => Schedule::withoutGlobalScope('school')
            ->where('instructor_id', $instructor->id)
            ->whereIn('status', ['scheduled', 'rescheduled']);

        $marked = Attendance::withoutGlobalScope('school')
            ->where('instructor_id', $instructor->id)
            ->whereIn('status', ['present', 'absent'])
            ->selectRaw("count(*) as total, sum(case when status = 'present' then 1 else 0 end) as present")
            ->first();

        return [
            'status' => $instructor->status,
            'assignedLearners' => Learner::withoutGlobalScope('school')
                ->where('assigned_instructor_id', $instructor->id)
                ->where('status', 'active')
                ->count(),
            'learnersTrained' => (int) $instructor->total_learners_trained,
            'ratingAverage' => (float) $instructor->rating_average,
            'todaySessions' => $open()->where('session_date', $today)->count(),
            'upcomingSessions' => $open()
                ->where('session_date', '>', $today)
                ->where('session_date', '<=', now()->addDays(7)->toDateString())
                ->count(),
            // Share of marked sessions the learner attended; null until any are marked.
            'attendanceRate' => $marked->total > 0 ? round($marked->present / $marked->total, 4) : null,
        ];
    }

    public function listDocuments(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        // Staff of the school, or the instructor themself (not their colleagues).
        if ($deny = $this->access()->instructor($request, $instructor, allowSelf: true)) {
            return $deny;
        }

        $docs = InstructorDocument::withoutGlobalScope('school')
            ->where('instructor_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (InstructorDocument $d) => $this->serializeDoc($d));

        return response()->json($docs);
    }

    public function addDocument(Request $request, int $id, DocumentStorage $storage): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->access()->instructor($request, $instructor, allowSelf: true)) {
            return $deny;
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:driving_license,aadhaar,pan,photo,certificate'],
            'file' => ['nullable', ...DocumentStorage::FILE_RULE],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('file');
        $schoolId = (int) $instructor->school_id;

        $doc = InstructorDocument::withoutGlobalScope('school')->create([
            'instructor_id' => $instructor->id,
            'school_id' => $schoolId,
            'type' => $data['type'],
            'file_path' => $file ? $storage->store($file, $schoolId, 'instructors', $instructor->id) : null,
            'file_name' => $file ? DocumentStorage::displayName($file) : null,
            'status' => $file ? 'uploaded' : 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($this->serializeDoc($doc), 201);
    }

    /**
     * Staff verify / reject a document; staff or the instructor may attach or
     * replace its file (multipart POST with _method=PATCH). Instructors cannot
     * change the review status of their own documents.
     */
    public function updateDocument(Request $request, int $id, int $docId, DocumentStorage $storage): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->access()->instructor($request, $instructor, allowSelf: true)) {
            return $deny;
        }

        $doc = InstructorDocument::withoutGlobalScope('school')
            ->where('instructor_id', $id)
            ->where('id', $docId)
            ->first();

        if (! $doc) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $isStaff = ! $request->user()->isInstructor();

        $data = $request->validate([
            // pending/uploaded follow from the file; staff only verify or reject.
            'status' => [$isStaff ? 'required_without:file' : 'prohibited', 'string', 'in:verified,rejected'],
            'notes' => ['nullable', 'string', 'max:500'],
            'file' => [$isStaff ? 'nullable' : 'required', ...DocumentStorage::FILE_RULE],
        ]);

        $previous = $doc->status;

        if ($file = $request->file('file')) {
            $old = $doc->file_path;
            $doc->file_path = $storage->store($file, (int) $doc->school_id, 'instructors', $instructor->id);
            $doc->file_name = DocumentStorage::displayName($file);
            $doc->status = 'uploaded';
            $doc->verified_by = null;
            $doc->verified_at = null;
            $storage->delete($old, (int) $doc->school_id);
        }

        $doc->fill([
            'status' => $data['status'] ?? $doc->status,
            'notes' => $data['notes'] ?? $doc->notes,
        ]);

        if (($data['status'] ?? null) === 'verified' && $doc->file_path === null) {
            throw ValidationException::withMessages(['status' => 'Upload the file before verifying it.']);
        }

        if (isset($data['status'])) {
            $doc->verified_by = $request->user()->id;
            $doc->verified_at = now();
        }

        $doc->save();

        if ($doc->status !== $previous) {
            AuditLog::log('document_'.$doc->status, 'InstructorDocument', $doc->id, ['status' => $previous], ['status' => $doc->status], (int) $doc->school_id);

            // DIQ-913: the trainer hears when the school verifies or rejects their document.
            if (in_array($doc->status, ['verified', 'rejected'], true) && $instructor->user_id && ($user = User::find($instructor->user_id))) {
                $label = str_replace('_', ' ', $doc->type);
                app(NotificationService::class)->notify(
                    $user,
                    'document.'.$doc->status,
                    $doc->status === 'verified' ? "Your {$label} was verified" : "Your {$label} needs another upload",
                    $doc->status === 'verified' ? null : trim('Please upload a clearer or valid copy. '.($doc->notes ?? '')),
                    ['documentType' => $doc->type],
                    (int) $doc->school_id
                );
            }
        }

        return response()->json($this->serializeDoc($doc));
    }

    private function serialize(Instructor $i, bool $full = false): array
    {
        $base = [
            'id' => $i->id,
            'schoolId' => $i->school_id,
            'name' => $i->name,
            'gender' => $i->gender,
            'yearsExperience' => (int) $i->years_experience,
            'skills' => $i->skills ?? [],
            'languages' => $i->languages ?? [],
            'womenInstructor' => (bool) $i->women_instructor,
            'publicVisible' => (bool) $i->public_visible,
            'photoUrl' => $i->photo_url,
            'ratingAverage' => (float) $i->rating_average,
            'ratingCount' => (int) $i->rating_count,
            'status' => $i->status,
            'bio' => $i->bio,
        ];

        if (! $full) {
            return $base;
        }

        return array_merge($base, [
            'userId' => $i->user_id,
            'mobile' => $i->mobile,
            'email' => $i->email,
            'dob' => $i->dob?->toDateString(),
            'address' => $i->address,
            'employeeId' => $i->employee_id,
            'joiningDate' => $i->joining_date?->toDateString(),
            'employmentType' => $i->employment_type,
            'licenseNumber' => $i->license_number,
            'licenseCategory' => $i->license_category,
            'licenseExpiry' => $i->license_expiry?->toDateString(),
            'totalLearnersTrained' => (int) $i->total_learners_trained,
            'hasLogin' => $i->user_id !== null,
        ]);
    }

    private function serializePublic(Instructor $i): array
    {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'photoUrl' => $i->photo_url,
            'yearsExperience' => (int) $i->years_experience,
            'skills' => $i->skills ?? [],
            'languages' => $i->languages ?? [],
            'womenInstructor' => (bool) $i->women_instructor,
            'ratingAverage' => (float) $i->rating_average,
            'ratingCount' => (int) $i->rating_count,
            'bio' => $i->bio,
        ];
    }

    private function serializeDoc(InstructorDocument $d): array
    {
        return [
            'id' => $d->id,
            'instructorId' => $d->instructor_id,
            'type' => $d->type,
            'hasFile' => $d->file_path !== null,
            'fileName' => $d->file_name,
            'status' => $d->status,
            'verifiedBy' => $d->verified_by,
            'verifiedAt' => $d->verified_at?->toISOString(),
            'notes' => $d->notes,
            'createdAt' => $d->created_at?->toISOString(),
        ];
    }
}
