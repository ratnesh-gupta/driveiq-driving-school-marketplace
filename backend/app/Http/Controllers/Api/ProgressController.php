<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DrivingTest;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Schedule;
use App\Models\School;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
    ) {}

    public function skillsCatalog(): JsonResponse
    {
        return response()->json([
            'skills' => TrainingProgressSkillLabels(),
        ]);
    }

    public function show(Request $request, int $learnerId): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($learnerId);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->authLearnerAccess($request, $learner)) {
            return $deny;
        }

        $snapshot = $this->progress->snapshot($learner->id, (int) $learner->school_id);

        return response()->json([
            'learnerId' => $learner->id,
            'learnerName' => $learner->name,
            ...$snapshot,
        ]);
    }

    public function update(Request $request, int $learnerId): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($learnerId);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->authSchoolOrInstructor($request, $learner)) {
            return $deny;
        }

        $data = $request->validate([
            'skillName' => ['required', 'string', 'in:vehicle_controls,parking,reverse,traffic_navigation,night_driving,highway_driving'],
            'percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'sessionId' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $instructorId = null;
        $user = $request->user();
        if ($user->isInstructor()) {
            $instructorId = Instructor::withoutGlobalScope('school')
                ->where('user_id', $user->id)
                ->value('id');
        }

        $row = $this->progress->updateSkill(
            $learner->id,
            (int) $learner->school_id,
            $data['skillName'],
            (int) $data['percentage'],
            $instructorId,
            $data['sessionId'] ?? null,
            $data['notes'] ?? null
        );

        // Auto-complete learner when overall reaches 100
        $snapshot = $this->progress->snapshot($learner->id, (int) $learner->school_id);
        if ($snapshot['overallCompletion'] >= 100 && $learner->status === 'active') {
            $learner->update(['status' => 'completed']);
        }

        AuditLog::log('update_progress', 'TrainingProgress', $row->id, [], [
            'skill' => $data['skillName'],
            'percentage' => $data['percentage'],
        ]);

        return response()->json([
            'learnerId' => $learner->id,
            ...$snapshot,
        ]);
    }

    public function sessionHistory(Request $request, int $learnerId): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($learnerId);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->authLearnerAccess($request, $learner)) {
            return $deny;
        }

        $sessions = Schedule::withoutGlobalScope('school')
            ->with(['instructor:id,name', 'attendance'])
            ->where('learner_id', $learnerId)
            ->orderByDesc('session_date')
            ->orderByDesc('start_time')
            ->limit(100)
            ->get()
            ->map(fn (Schedule $s) => [
                'id' => $s->id,
                'sessionDate' => $s->session_date?->toDateString(),
                'startTime' => substr((string) $s->start_time, 0, 5),
                'endTime' => substr((string) $s->end_time, 0, 5),
                'status' => $s->status,
                'instructorName' => $s->instructor?->name,
                'pickupLocation' => $s->pickup_location,
                'sessionSummary' => $s->session_summary,
                'notes' => $s->notes,
                'attendance' => $s->attendance?->status,
            ]);

        return response()->json($sessions);
    }

    public function listTests(Request $request, int $learnerId): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($learnerId);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->authLearnerAccess($request, $learner)) {
            return $deny;
        }

        $tests = DrivingTest::withoutGlobalScope('school')
            ->where('learner_id', $learnerId)
            ->orderByDesc('test_date')
            ->get()
            ->map(fn (DrivingTest $t) => $this->serializeTest($t));

        return response()->json($tests);
    }

    public function createTest(Request $request, int $learnerId): JsonResponse
    {
        $learner = Learner::withoutGlobalScope('school')->find($learnerId);
        if (! $learner) {
            return response()->json(['message' => 'Learner not found'], 404);
        }
        if ($deny = $this->authSchool($request, (int) $learner->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'testDate' => ['required', 'date'],
            'rtoName' => ['nullable', 'string', 'max:255'],
            'rtoLocation' => ['nullable', 'string', 'max:255'],
            'attemptNumber' => ['nullable', 'integer', 'min:1', 'max:20'],
            'status' => ['nullable', 'string', 'in:scheduled,completed,passed,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attempt = $data['attemptNumber'] ?? (
            (int) DrivingTest::withoutGlobalScope('school')
                ->where('learner_id', $learnerId)
                ->max('attempt_number') + 1
        );

        $test = DrivingTest::withoutGlobalScope('school')->create([
            'learner_id' => $learnerId,
            'school_id' => $learner->school_id,
            'test_date' => $data['testDate'],
            'rto_name' => $data['rtoName'] ?? null,
            'rto_location' => $data['rtoLocation'] ?? null,
            'attempt_number' => max(1, $attempt),
            'status' => $data['status'] ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if (in_array($test->status, ['passed'], true)) {
            $learner->update(['permanent_license_status' => 'passed']);
        }

        return response()->json($this->serializeTest($test), 201);
    }

    public function updateTest(Request $request, int $testId): JsonResponse
    {
        $test = DrivingTest::withoutGlobalScope('school')->find($testId);
        if (! $test) {
            return response()->json(['message' => 'Driving test not found'], 404);
        }
        if ($deny = $this->authSchool($request, (int) $test->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'testDate' => ['sometimes', 'date'],
            'rtoName' => ['nullable', 'string', 'max:255'],
            'rtoLocation' => ['nullable', 'string', 'max:255'],
            'attemptNumber' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'status' => ['sometimes', 'string', 'in:scheduled,completed,passed,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $map = [
            'testDate' => 'test_date',
            'rtoName' => 'rto_name',
            'rtoLocation' => 'rto_location',
            'attemptNumber' => 'attempt_number',
            'status' => 'status',
            'notes' => 'notes',
        ];
        $payload = [];
        foreach ($map as $c => $s) {
            if (array_key_exists($c, $data)) {
                $payload[$s] = $data[$c];
            }
        }

        $test->fill($payload)->save();

        if (($payload['status'] ?? null) === 'passed') {
            Learner::withoutGlobalScope('school')
                ->where('id', $test->learner_id)
                ->update(['permanent_license_status' => 'passed']);
        }

        return response()->json($this->serializeTest($test));
    }

    private function serializeTest(DrivingTest $t): array
    {
        return [
            'id' => $t->id,
            'learnerId' => $t->learner_id,
            'testDate' => $t->test_date?->toDateString(),
            'rtoName' => $t->rto_name,
            'rtoLocation' => $t->rto_location,
            'attemptNumber' => (int) $t->attempt_number,
            'status' => $t->status,
            'notes' => $t->notes,
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

    private function authLearnerAccess(Request $request, Learner $learner): ?JsonResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return null;
        }
        if ($user->isSchool() && (int) $user->school_id === (int) $learner->school_id) {
            return null;
        }
        if ($user->isLearner() && (int) $user->id === (int) $learner->user_id) {
            return null;
        }
        if ($user->isInstructor() && (int) $user->school_id === (int) $learner->school_id) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }

    private function authSchoolOrInstructor(Request $request, Learner $learner): ?JsonResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return null;
        }
        if ($user->isSchool() && (int) $user->school_id === (int) $learner->school_id) {
            return null;
        }
        if ($user->isInstructor() && (int) $user->school_id === (int) $learner->school_id) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}

/** Helper labels for skill catalog. */
function TrainingProgressSkillLabels(): array
{
    return [
        ['code' => 'vehicle_controls', 'label' => 'Vehicle controls'],
        ['code' => 'parking', 'label' => 'Parking'],
        ['code' => 'reverse', 'label' => 'Reverse'],
        ['code' => 'traffic_navigation', 'label' => 'Traffic navigation'],
        ['code' => 'night_driving', 'label' => 'Night driving'],
        ['code' => 'highway_driving', 'label' => 'Highway driving'],
    ];
}
