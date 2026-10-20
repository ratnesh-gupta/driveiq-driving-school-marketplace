<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-909: vehicle paper expiry and leave approvals that clash with bookings. */
class FleetAndLeaveTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-01 10:00:00');
        $owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Fleet School', 'slug' => 'fleet-school-909', 'user_id' => $owner->id]);
        $owner->update(['school_id' => $this->school->id]);
        Sanctum::actingAs($owner->refresh());
    }

    public function test_vehicle_document_status_follows_expiry(): void
    {
        $car = Vehicle::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'registration_number' => 'MH12AA0001', 'type' => 'car', 'status' => 'active']);
        $doc = fn (string $type, ?string $expiry, bool $file = true) => VehicleDocument::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'vehicle_id' => $car->id, 'type' => $type,
            'expiry_date' => $expiry, 'file_path' => $file ? "x/{$type}.pdf" : null, 'status' => 'pending',
        ]);
        $doc('pollution', '2026-09-20');
        $doc('insurance', '2026-10-15');
        $doc('registration', '2030-01-01');
        $doc('permit', null, false);

        $docs = collect($this->getJson("/api/vehicles/{$car->id}/documents")->assertOk()->json())->keyBy('type');
        $this->assertSame('expired', $docs['pollution']['status']);
        $this->assertSame(['valid', true], [$docs['insurance']['status'], $docs['insurance']['expiringSoon']]);
        $this->assertSame(['valid', false], [$docs['registration']['status'], $docs['registration']['expiringSoon']]);
        $this->assertSame('pending', $docs['permit']['status']);

        $this->getJson("/api/schools/{$this->school->id}/vehicles")
            ->assertOk()->assertJsonPath('0.expiredDocuments', 1)->assertJsonPath('0.expiringDocuments', 1);
    }

    public function test_approving_leave_lists_the_sessions_it_clashes_with(): void
    {
        $sid = $this->school->id;
        $ravi = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Ravi', 'status' => 'active']);
        $session = fn (string $date, string $status = 'scheduled') => Schedule::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'instructor_id' => $ravi->id, 'learner_name' => 'Asha',
            'session_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00', 'status' => $status,
        ]);
        $clash = $session('2026-10-05');
        $session('2026-10-06', 'cancelled');
        $session('2026-10-09');
        $leave = LeaveRequest::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'instructor_id' => $ravi->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-07', 'status' => 'pending',
        ]);

        $this->patchJson("/api/leave-requests/{$leave->id}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonCount(1, 'clashingSessions')
            ->assertJsonPath('clashingSessions.0.id', $clash->id);

        // The clashing session can still be cancelled (DIQ-903).
        $this->patchJson("/api/schedules/{$clash->id}", ['status' => 'cancelled'])->assertOk();
    }
}
