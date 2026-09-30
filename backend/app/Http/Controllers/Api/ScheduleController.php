<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Services\ScheduleService;
use App\Support\SchoolScopedIds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    /** A month view holds at most ~6 weeks; this cap only guards against abuse. */
    private const RANGE_LIMIT = 1000;

    private const MAX_RANGE_DAYS = 70;

    public function __construct(private readonly ScheduleService $schedules) {}

    /**
     * The calendar asks for the dates it shows (from/to, inclusive). Without
     * them, the last week and the next eight weeks are returned.
     *
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now()->subWeek();
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : $from->copy()->addWeeks(9);

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'Ask for at most '.self::MAX_RANGE_DAYS.' days at a time.']);
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        [$from, $to] = $this->range($request);

        $query = Schedule::withoutGlobalScope('school')
            ->with(['instructor:id,name', 'vehicle:id,registration_number,type', 'attendance'])
            ->where('school_id', $schoolId)
            ->whereBetween('session_date', [$from, $to]);

        if ($request->filled('instructorId')) {
            $query->where('instructor_id', $request->query('instructorId'));
        }

        $items = $query->orderBy('session_date')->orderBy('start_time')->limit(self::RANGE_LIMIT)->get()
            ->map(fn (Schedule $s) => $this->serialize($s));

        return response()->json($items);
    }

    public function instructorSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $instructor = Instructor::withoutGlobalScope('school')
            ->where('user_id', $user->id)
            ->first();

        if (! $instructor) {
            return response()->json(['message' => 'Instructor profile not found'], 404);
        }

        [$from, $to] = $this->range($request);

        $items = Schedule::withoutGlobalScope('school')
            ->with(['vehicle:id,registration_number,type', 'attendance'])
            ->where('instructor_id', $instructor->id)
            ->whereBetween('session_date', [$from, $to])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(self::RANGE_LIMIT)
            ->get()
            ->map(fn (Schedule $s) => $this->serialize($s));

        return response()->json($items);
    }

    public function store(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $data = $request->validate([
            'instructorId' => ['required', 'integer'],
            'vehicleId' => ['nullable', 'integer'],
            'learnerId' => ['nullable', 'integer', SchoolScopedIds::learner($schoolId)],
            'learnerName' => ['nullable', 'string', 'max:255'],
            'sessionDate' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'pickupLocation' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $schedule = $this->schedules->create([
            'school_id' => $schoolId,
            'instructor_id' => $data['instructorId'],
            'vehicle_id' => $data['vehicleId'] ?? null,
            'learner_id' => $data['learnerId'] ?? null,
            'learner_name' => $data['learnerName'] ?? null,
            'session_date' => $data['sessionDate'],
            'start_time' => $data['startTime'],
            'end_time' => $data['endTime'],
            'pickup_location' => $data['pickupLocation'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'scheduled',
            'created_by' => $request->user()->id,
        ]);

        $schedule->load(['instructor:id,name', 'vehicle:id,registration_number,type']);

        AuditLog::log('create', 'Schedule', $schedule->id, [], [
            'instructor_id' => $schedule->instructor_id,
            'session_date' => $schedule->session_date?->toDateString(),
        ]);

        return response()->json($this->serialize($schedule), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::withoutGlobalScope('school')->find($id);
        if (! $schedule) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }
        if ($deny = $this->access()->school($request, (int) $schedule->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'instructorId' => ['sometimes', 'integer'],
            'vehicleId' => ['nullable', 'integer'],
            'learnerId' => ['nullable', 'integer', SchoolScopedIds::learner((int) $schedule->school_id)],
            'learnerName' => ['nullable', 'string', 'max:255'],
            'sessionDate' => ['sometimes', 'date'],
            'startTime' => ['sometimes', 'date_format:H:i'],
            'endTime' => ['sometimes', 'date_format:H:i'],
            'pickupLocation' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:scheduled,completed,cancelled,rescheduled'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sessionSummary' => ['nullable', 'string', 'max:5000'],
        ]);

        $payload = [];
        $map = [
            'instructorId' => 'instructor_id',
            'vehicleId' => 'vehicle_id',
            'learnerId' => 'learner_id',
            'learnerName' => 'learner_name',
            'sessionDate' => 'session_date',
            'startTime' => 'start_time',
            'endTime' => 'end_time',
            'pickupLocation' => 'pickup_location',
            'status' => 'status',
            'notes' => 'notes',
            'sessionSummary' => 'session_summary',
        ];
        foreach ($map as $c => $s) {
            if (array_key_exists($c, $data)) {
                $payload[$s] = $data[$c];
            }
        }

        $old = $schedule->only(array_keys($payload));
        $schedule = $this->schedules->update($schedule, $payload);

        AuditLog::log('update', 'Schedule', $schedule->id, $this->plain($old), $this->plain($schedule->only(array_keys($payload))), (int) $schedule->school_id);

        return response()->json($this->serialize($schedule));
    }

    public function markAttendance(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::withoutGlobalScope('school')->find($id);
        if (! $schedule) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }
        if ($deny = $this->access()->schedule($request, $schedule)) {
            return $deny;
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:present,absent,rescheduled,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sessionSummary' => ['nullable', 'string', 'max:5000'],
        ]);

        $attendance = Attendance::withoutGlobalScope('school')->updateOrCreate(
            ['schedule_id' => $schedule->id],
            [
                'school_id' => $schedule->school_id,
                'learner_id' => $schedule->learner_id,
                'instructor_id' => $schedule->instructor_id,
                'status' => $data['status'],
                'marked_by' => $request->user()->id,
                'marked_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        $sessionStatus = match ($data['status']) {
            'present' => 'completed',
            'absent' => 'completed',
            'rescheduled' => 'rescheduled',
            'cancelled' => 'cancelled',
        };

        $previous = $schedule->status;
        $schedule->update([
            'status' => $sessionStatus,
            'session_summary' => $data['sessionSummary'] ?? $schedule->session_summary,
        ]);

        AuditLog::log('attendance', 'Schedule', $schedule->id, ['status' => $previous], [
            'attendance' => $attendance->status,
            'status' => $sessionStatus,
        ], (int) $schedule->school_id);

        return response()->json([
            'id' => $attendance->id,
            'scheduleId' => $schedule->id,
            'status' => $attendance->status,
            'sessionStatus' => $schedule->status,
            'markedAt' => $attendance->marked_at?->toISOString(),
        ]);
    }

    public function listLeave(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $items = LeaveRequest::withoutGlobalScope('school')
            ->with('instructor:id,name')
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (LeaveRequest $l) => [
                'id' => $l->id,
                'instructorId' => $l->instructor_id,
                'instructorName' => $l->instructor?->name,
                'startDate' => $l->start_date?->toDateString(),
                'endDate' => $l->end_date?->toDateString(),
                'reason' => $l->reason,
                'status' => $l->status,
            ]);

        return response()->json($items);
    }

    public function requestLeave(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $data = $request->validate([
            'instructorId' => ['required', 'integer'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $instructor = Instructor::withoutGlobalScope('school')
            ->where('id', $data['instructorId'])
            ->where('school_id', $schoolId)
            ->first();

        if (! $instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $leave = LeaveRequest::withoutGlobalScope('school')->create([
            'instructor_id' => $instructor->id,
            'school_id' => $schoolId,
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'id' => $leave->id,
            'status' => $leave->status,
            'startDate' => $leave->start_date?->toDateString(),
            'endDate' => $leave->end_date?->toDateString(),
        ], 201);
    }

    public function reviewLeave(Request $request, int $id): JsonResponse
    {
        $leave = LeaveRequest::withoutGlobalScope('school')->find($id);
        if (! $leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }
        if ($deny = $this->access()->school($request, (int) $leave->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected'],
        ]);

        $previous = $leave->status;
        $leave->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditLog::log('review', 'LeaveRequest', $leave->id, ['status' => $previous], ['status' => $leave->status], (int) $leave->school_id);

        return response()->json([
            'id' => $leave->id,
            'status' => $leave->status,
            'reviewedAt' => $leave->reviewed_at?->toISOString(),
        ]);
    }

    /** Dates and times as plain strings for the audit trail. */
    private function plain(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $values);
    }

    private function serialize(Schedule $s): array
    {
        return [
            'id' => $s->id,
            'schoolId' => $s->school_id,
            'learnerId' => $s->learner_id,
            'learnerName' => $s->learner_name,
            'instructorId' => $s->instructor_id,
            'instructorName' => $s->instructor?->name,
            'vehicleId' => $s->vehicle_id,
            'vehicleRegistration' => $s->vehicle?->registration_number,
            'sessionDate' => $s->session_date?->toDateString(),
            'startTime' => substr((string) $s->start_time, 0, 5),
            'endTime' => substr((string) $s->end_time, 0, 5),
            'pickupLocation' => $s->pickup_location,
            'status' => $s->status,
            'notes' => $s->notes,
            'sessionSummary' => $s->session_summary,
            'attendance' => $s->attendance ? [
                'status' => $s->attendance->status,
                'markedAt' => $s->attendance->marked_at?->toISOString(),
            ] : null,
        ];
    }
}
