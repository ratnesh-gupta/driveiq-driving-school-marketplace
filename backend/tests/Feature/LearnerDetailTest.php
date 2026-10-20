<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\Learner;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-906: data behind the school's learner detail sheet. */
class LearnerDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_history_lists_trainer_vehicle_and_who_assigned(): void
    {
        $owner = User::factory()->create(['role' => 'school', 'name' => 'Owner Name']);
        $school = School::create(['name' => 'Detail School', 'slug' => 'detail-school', 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);
        $ravi = Instructor::withoutGlobalScope('school')->create(['school_id' => $school->id, 'name' => 'Ravi', 'status' => 'active']);
        $car = Vehicle::withoutGlobalScope('school')->create(['school_id' => $school->id, 'registration_number' => 'MH12ZZ0001', 'type' => 'car', 'status' => 'active']);
        $asha = Learner::withoutGlobalScope('school')->create(['school_id' => $school->id, 'name' => 'Asha', 'status' => 'active']);
        Sanctum::actingAs($owner->refresh());

        $this->postJson("/api/learners/{$asha->id}/assign", ['instructorId' => $ravi->id])->assertOk();
        $this->postJson("/api/learners/{$asha->id}/assign", ['instructorId' => $ravi->id, 'vehicleId' => $car->id])->assertOk();

        $this->getJson("/api/learners/{$asha->id}/assignments")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.action', 'reassign')
            ->assertJsonPath('0.instructorName', 'Ravi')
            ->assertJsonPath('0.vehicleRegistration', 'MH12ZZ0001')
            ->assertJsonPath('0.assignedBy', 'Owner Name')
            ->assertJsonPath('1.action', 'assign');

        Sanctum::actingAs($this->otherSchoolUser());
        $this->getJson("/api/learners/{$asha->id}/assignments")->assertForbidden();
    }
}
