<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function create(array $data): Schedule
    {
        $this->assertNoConflicts($data);

        return Schedule::withoutGlobalScope('school')->create($data);
    }

    public function update(Schedule $schedule, array $data): Schedule
    {
        $merged = array_merge($schedule->only([
            'school_id', 'instructor_id', 'vehicle_id', 'session_date', 'start_time', 'end_time',
        ]), $data);

        $this->assertNoConflicts($merged, $schedule->id);

        $schedule->fill($data);
        $schedule->save();

        return $schedule->fresh(['instructor', 'vehicle', 'attendance']);
    }

    public function assertNoConflicts(array $data, ?int $ignoreId = null): void
    {
        $schoolId = (int) $data['school_id'];
        $instructorId = (int) $data['instructor_id'];
        $vehicleId = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
        $date = Carbon::parse($data['session_date'])->toDateString();
        $start = $data['start_time'];
        $end = $data['end_time'];

        if ($start >= $end) {
            throw ValidationException::withMessages(['end_time' => 'End time must be after start time.']);
        }

        $instructor = Instructor::withoutGlobalScope('school')
            ->where('id', $instructorId)
            ->where('school_id', $schoolId)
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
    }
}
