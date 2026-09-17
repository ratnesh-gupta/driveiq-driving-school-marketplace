<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\InstructorDocument;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstructorController extends Controller
{
    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authorizeSchool($request, $schoolId)) {
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
        if ($deny = $this->authorizeSchool($request, $schoolId)) {
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

        $userId = null;
        if (! empty($data['createLogin']) && ! empty($data['email'])) {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();
            if (! $user) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'instructor',
                    'school_id' => $schoolId,
                ]);
            } else {
                $user->update([
                    'role' => $user->isAdmin() ? $user->role : 'instructor',
                    'school_id' => $schoolId,
                ]);
            }
            $userId = $user->id;
        }

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

        return response()->json($this->serialize($instructor, full: true), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->authorizeSchool($request, (int) $instructor->school_id)) {
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

        if ($deny = $this->authorizeSchool($request, (int) $instructor->school_id)) {
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

        if ($deny = $this->authorizeSchool($request, (int) $instructor->school_id)) {
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
            'dashboard' => [
                'status' => $instructor->status,
                'assignedLearners' => (int) $instructor->total_learners_trained,
                'ratingAverage' => (float) $instructor->rating_average,
                'todaySessions' => 0, // Phase 6 scheduling
                'upcomingSessions' => 0,
            ],
        ]);
    }

    public function listDocuments(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->authorizeSchool($request, (int) $instructor->school_id)) {
            return $deny;
        }

        $docs = InstructorDocument::withoutGlobalScope('school')
            ->where('instructor_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (InstructorDocument $d) => $this->serializeDoc($d));

        return response()->json($docs);
    }

    public function addDocument(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->authorizeSchool($request, (int) $instructor->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:driving_license,aadhaar,pan,photo,certificate'],
            'filePath' => ['nullable', 'string', 'max:2048'],
            'fileName' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $doc = InstructorDocument::withoutGlobalScope('school')->create([
            'instructor_id' => $instructor->id,
            'school_id' => $instructor->school_id,
            'type' => $data['type'],
            'file_path' => $data['filePath'] ?? null,
            'file_name' => $data['fileName'] ?? null,
            'status' => ! empty($data['filePath']) ? 'uploaded' : 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($this->serializeDoc($doc), 201);
    }

    public function updateDocument(Request $request, int $id, int $docId): JsonResponse
    {
        $instructor = Instructor::withoutGlobalScope('school')->find($id);

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        if ($deny = $this->authorizeSchool($request, (int) $instructor->school_id)) {
            return $deny;
        }

        $doc = InstructorDocument::withoutGlobalScope('school')
            ->where('instructor_id', $id)
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
        ]);

        $doc->fill([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $doc->notes,
            'file_path' => $data['filePath'] ?? $doc->file_path,
            'file_name' => $data['fileName'] ?? $doc->file_name,
        ]);

        if (in_array($data['status'], ['verified', 'rejected'], true)) {
            $doc->verified_by = $request->user()->id;
            $doc->verified_at = now();
        }

        $doc->save();

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
            'filePath' => $d->file_path,
            'fileName' => $d->file_name,
            'status' => $d->status,
            'verifiedBy' => $d->verified_by,
            'verifiedAt' => $d->verified_at?->toISOString(),
            'notes' => $d->notes,
            'createdAt' => $d->created_at?->toISOString(),
        ];
    }

    private function authorizeSchool(Request $request, int $schoolId): ?JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $user = $request->user();

        if ($user->isAdmin()) {
            return null;
        }

        if ($user->role === 'instructor' && (int) $user->school_id === $schoolId) {
            return null;
        }

        if ($user->isSchool() && (int) $user->school_id === $schoolId) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
