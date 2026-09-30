<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\Learner;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    /** Changing any of these can create a clash, so they re-run the conflict checks. */
    private const BOOKING_FIELDS = ['instructor_id', 'vehicle_id', 'learner_id', 'session_date', 'start_time', 'end_time'];

    public function create(array $data): Schedule
    {
        return DB::transaction(function () use ($data) {
            $this->assertNoConflicts($data);

            return Schedule::withoutGlobalScope('school')->create($data);
        });
    }

    public function update(Schedule $schedule, array $data): Schedule
    {
        DB::transaction(function () use ($schedule, $data) {
            $merged = array_merge($schedule->only(['school_id', ...self::BOOKING_FIELDS]), $data);

            // Cancelling, completing or editing notes never needs a free slot:
            // a session must stay cancellable even if the trainer went on leave.
            $closing = in_array($data['status'] ?? null, ['cancelled', 'completed'], true);
            if (! $closing && $this->changesBooking($schedule, $data)) {
                $this->assertNoConflicts($merged, $schedule->id);
            }

            $schedule->fill($data);
            $schedule->save();
        });

        return $schedule->fresh(['instructor', 'vehicle', 'attendance']);
    }

    private function changesBooking(Schedule $schedule, array $data): bool
    {
        if (($data['status'] ?? null) === 'scheduled' && $schedule->status !== 'scheduled') {
            return true; // reopening a closed session takes the slot again
        }

        foreach (self::BOOKING_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $current = $schedule->{$field};
            $current = match ($field) {
                'session_date' => $current?->toDateString(),
                'start_time', 'end_time' => substr((string) $current, 0, 5),
                default => $current,
            };
            $new = $field === 'session_date' && $data[$field] ? Carbon::parse($data[$field])->toDateString() : $data[$field];
            if ((string) $current !== (string) $new) {
                return true;
            }
        }

        return false;
    }

    /**
     * Must run inside a transaction: the instructor, vehicle and learner rows
     * are locked so two simultaneous bookings for the same slot queue up
     * instead of both passing the overlap check.
     */
    public function assertNoConflicts(array $data, ?int $ignoreId = null): void
    {
        $schoolId = (int) $data['school_id'];
        $instructorId = (int) $data['instructor_id'];
        $vehicleId = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
        $learnerId = isset($data['learner_id']) ? (int) $data['learner_id'] : null;
        $date = Carbon::parse($data['session_date'])->toDateString();
        $start = substr((string) $data['start_time'], 0, 5);
        $end = substr((string) $data['end_time'], 0, 5);

        if ($start >= $end) {
            throw ValidationException::withMessages(['end_time' => 'End time must be after start time.']);
        }

        $instructor = Instructor::withoutGlobalScope('school')
            ->where('id', $instructorId)
            ->where('school_id', $schoolId)
            ->lockForUpdate()
            ->first();

        if (! $instructor || $instructor->status !== 'active') {
            throw ValidationException::withMessages(['instructor_id' => 'Instructor not available.']);
        }

        // Leave conflict
        $onLeave = LeaveRequest::withoutGlobalScope('school')
            ->where('instructor_id', $instructorId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();

        if ($onLeave) {
            throw ValidationException::withMessages(['instructor_id' => 'Instructor is on approved leave that day.']);
        }

        $overlap = function ($query) use ($date, $start, $end, $ignoreId) {
            $query->where('session_date', $date)
                ->whereIn('status', ['scheduled', 'rescheduled'])
                ->where('start_time', '<', $end)
                ->where('end_time', '>', $start);

            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
        };

        $instructorBusy = Schedule::withoutGlobalScope('school')
            ->where('instructor_id', $instructorId)
            ->where($overlap)
            ->exists();

        if ($instructorBusy) {
            throw ValidationException::withMessages([
                'instructor_id' => 'Instructor already has an overlapping session.',
            ]);
        }

        if ($vehicleId) {
            $vehicle = Vehicle::withoutGlobalScope('school')
                ->where('id', $vehicleId)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->first();

            if (! $vehicle || $vehicle->status !== 'active') {
                throw ValidationException::withMessages(['vehicle_id' => 'Vehicle not available.']);
            }

            $vehicleBusy = Schedule::withoutGlobalScope('school')
                ->where('vehicle_id', $vehicleId)
                ->where($overlap)
                ->exists();

            if ($vehicleBusy) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Vehicle already assigned to an overlapping session.',
                ]);
            }
        }

        if ($learnerId) {
            Learner::withoutGlobalScope('school')->whereKey($learnerId)->lockForUpdate()->first();

            $learnerBusy = Schedule::withoutGlobalScope('school')
                ->where('learner_id', $learnerId)
                ->where($overlap)
                ->exists();

            if ($learnerBusy) {
                throw ValidationException::withMessages([
                    'learner_id' => 'Learner already has an overlapping session.',
                ]);
            }
        }
    }
}
