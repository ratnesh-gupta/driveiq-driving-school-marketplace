<?php

namespace Tests\Feature;

use App\Models\DrivePackage;
use App\Models\Inquiry;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-901: a school can never attach another school's rows by ID. */
class CrossSchoolIdsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{owner: User, school: School, instructor: Instructor, vehicle: Vehicle, package: DrivePackage, learner: Learner, schedule: Schedule} */
    private function school(string $slug): array
    {
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create(['name' => $slug, 'slug' => $slug, 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);
        $sid = $school->id;

        $instructor = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Coach', 'status' => 'active']);
        $vehicle = Vehicle::withoutGlobalScope('school')->create(['school_id' => $sid, 'registration_number' => "MH12{$sid}", 'type' => 'car', 'status' => 'active']);
        $package = DrivePackage::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'name' => 'Basic', 'price' => 3000, 'sessions' => 10, 'vehicle_type' => 'car', 'transmission' => 'manual',
        ]);
        $learner = Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Asha', 'status' => 'active']);
        $schedule = Schedule::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'instructor_id' => $instructor->id, 'learner_id' => $learner->id,
            'session_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled',
        ]);

        return compact('owner', 'school', 'instructor', 'vehicle', 'package', 'learner', 'schedule');
    }

    public function test_foreign_ids_are_rejected_everywhere(): void
    {
        $a = $this->school('school-a');
        $b = $this->school('school-b');
        Sanctum::actingAs($a['owner']->refresh());
        $sid = $a['school']->id;

        $session = ['instructorId' => $a['instructor']->id, 'sessionDate' => '2026-10-06', 'startTime' => '09:00', 'endTime' => '10:00'];
        $this->postJson("/api/schools/{$sid}/schedules", $session + ['learnerId' => $b['learner']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('learnerId');
        $this->patchJson("/api/schedules/{$a['schedule']->id}", ['learnerId' => $b['learner']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('learnerId');

        $foreign = [
            'packageId' => $b['package']->id,
            'assignedInstructorId' => $b['instructor']->id,
            'assignedVehicleId' => $b['vehicle']->id,
        ];
        $this->postJson("/api/schools/{$sid}/learners", ['name' => 'New'] + $foreign)
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($foreign));
        $this->patchJson("/api/learners/{$a['learner']->id}", $foreign)
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($foreign));

        $inquiry = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'name' => 'Lead', 'phone' => '9000000001', 'status' => 'pending', 'vehicle_type' => 'car',
        ]);
        $this->postJson("/api/inquiries/{$inquiry->id}/convert", $foreign)
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($foreign));

        // A session of another school, or of another learner in the same school, is not this learner's.
        $other = Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Bina', 'status' => 'active']);
        foreach ([$b['schedule']->id, $a['schedule']->id] as $sessionId) {
            $this->putJson("/api/learners/{$other->id}/progress", ['skillName' => 'parking', 'percentage' => 40, 'sessionId' => $sessionId])
                ->assertUnprocessable()->assertJsonValidationErrors('sessionId');
        }
    }

    public function test_own_ids_are_still_accepted(): void
    {
        $a = $this->school('school-a');
        Sanctum::actingAs($a['owner']->refresh());
        $sid = $a['school']->id;

        $this->postJson("/api/schools/{$sid}/learners", [
            'name' => 'New',
            'packageId' => $a['package']->id,
            'assignedInstructorId' => $a['instructor']->id,
            'assignedVehicleId' => $a['vehicle']->id,
        ])->assertCreated()->assertJsonPath('packageId', $a['package']->id);

        $this->putJson("/api/learners/{$a['learner']->id}/progress", [
            'skillName' => 'parking', 'percentage' => 40, 'sessionId' => $a['schedule']->id,
        ])->assertOk();
    }
}
