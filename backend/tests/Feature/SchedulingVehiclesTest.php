<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchedulingVehiclesTest extends TestCase
{
    use RefreshDatabase;

    private function seed(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-p6']);
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'name' => 'Fleet School',
            'slug' => 'fleet-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000009000',
            'user_id' => $owner->id,
        ]);
        $owner->update(['school_id' => $school->id]);

        $instructor = Instructor::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Ravi',
            'status' => 'active',
        ]);

        return compact('owner', 'school', 'instructor');
    }

    public function test_vehicle_crud(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seed();
        Sanctum::actingAs($owner);

        $this->postJson('/api/schools/'.$school->id.'/vehicles', [
            'registrationNumber' => 'MH12AB1234',
            'type' => 'car',
            'transmission' => 'manual',
        ])->assertCreated()
            ->assertJsonPath('registrationNumber', 'MH12AB1234');

        $this->getJson('/api/schools/'.$school->id.'/vehicles')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_schedule_conflict_on_instructor(): void
    {
        ['owner' => $owner, 'school' => $school, 'instructor' => $instructor] = $this->seed();
        Sanctum::actingAs($owner);

        $this->postJson('/api/schools/'.$school->id.'/schedules', [
            'instructorId' => $instructor->id,
            'learnerName' => 'Asha',
            'sessionDate' => '2026-10-01',
            'startTime' => '10:00',
            'endTime' => '11:00',
        ])->assertCreated();

        $this->postJson('/api/schools/'.$school->id.'/schedules', [
            'instructorId' => $instructor->id,
            'learnerName' => 'Bina',
            'sessionDate' => '2026-10-01',
            'startTime' => '10:30',
            'endTime' => '11:30',
        ])->assertStatus(422);
    }

    public function test_attendance_marks_session_completed(): void
    {
        ['owner' => $owner, 'school' => $school, 'instructor' => $instructor] = $this->seed();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/schools/'.$school->id.'/schedules', [
            'instructorId' => $instructor->id,
            'learnerName' => 'Asha',
            'sessionDate' => '2026-10-02',
            'startTime' => '09:00',
            'endTime' => '10:00',
        ])->assertCreated();

        $id = $created->json('id');

        $this->postJson('/api/schedules/'.$id.'/attendance', [
            'status' => 'present',
            'sessionSummary' => 'Good progress on clutch control',
        ])->assertOk()
            ->assertJsonPath('status', 'present')
            ->assertJsonPath('sessionStatus', 'completed');
    }

    public function test_leave_blocks_scheduling(): void
    {
        ['owner' => $owner, 'school' => $school, 'instructor' => $instructor] = $this->seed();
        Sanctum::actingAs($owner);

        $leave = $this->postJson('/api/schools/'.$school->id.'/leave-requests', [
            'instructorId' => $instructor->id,
            'startDate' => '2026-10-05',
            'endDate' => '2026-10-07',
            'reason' => 'Personal',
        ])->assertCreated();

        $this->patchJson('/api/leave-requests/'.$leave->json('id'), [
            'status' => 'approved',
        ])->assertOk();

        $this->postJson('/api/schools/'.$school->id.'/schedules', [
            'instructorId' => $instructor->id,
            'sessionDate' => '2026-10-06',
            'startTime' => '10:00',
            'endTime' => '11:00',
        ])->assertStatus(422);
    }
}
