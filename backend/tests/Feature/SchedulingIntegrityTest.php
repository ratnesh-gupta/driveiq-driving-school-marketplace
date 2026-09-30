<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\InstructorDocument;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-903: bookings, calendar ranges, audit trail and document status rules. */
class SchedulingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    private Instructor $ravi;

    private Instructor $meera;

    private Learner $asha;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Slot School', 'slug' => 'slot-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        $sid = $this->school->id;
        $this->ravi = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Ravi', 'status' => 'active']);
        $this->meera = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Meera', 'status' => 'active']);
        $this->asha = Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Asha', 'status' => 'active']);
        Sanctum::actingAs($this->owner->refresh());
    }

    private function book(array $overrides = [])
    {
        return $this->postJson("/api/schools/{$this->school->id}/schedules", $overrides + [
            'instructorId' => $this->ravi->id,
            'learnerId' => $this->asha->id,
            'sessionDate' => '2026-10-07',
            'startTime' => '09:00',
            'endTime' => '10:00',
        ]);
    }

    public function test_a_learner_cannot_be_double_booked(): void
    {
        $this->book()->assertCreated();

        $this->book(['instructorId' => $this->meera->id, 'startTime' => '09:30', 'endTime' => '10:30'])
            ->assertUnprocessable()->assertJsonValidationErrors('learner_id');
        $this->book(['instructorId' => $this->meera->id, 'startTime' => '10:00', 'endTime' => '11:00'])
            ->assertCreated();
    }

    public function test_a_session_can_be_cancelled_during_leave_and_notes_edited_without_rechecks(): void
    {
        $id = $this->book()->json('id');
        LeaveRequest::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'instructor_id' => $this->ravi->id,
            'start_date' => '2026-10-07', 'end_date' => '2026-10-07', 'status' => 'approved',
        ]);

        $this->patchJson("/api/schedules/{$id}", ['notes' => 'Bring learner licence'])->assertOk();
        $this->patchJson("/api/schedules/{$id}", ['status' => 'cancelled'])->assertOk()->assertJsonPath('status', 'cancelled');
        // Reopening takes the slot again, so the leave now blocks it.
        $this->patchJson("/api/schedules/{$id}", ['status' => 'scheduled'])->assertUnprocessable();
        // Moving it still re-checks.
        $this->patchJson("/api/schedules/{$id}", ['sessionDate' => '2026-10-07', 'startTime' => '11:00', 'endTime' => '12:00'])
            ->assertUnprocessable();

        $this->assertTrue(AuditLog::where('action', 'update')->where('model_type', 'Schedule')->where('model_id', $id)->exists());
    }

    public function test_calendar_loads_the_requested_range_not_the_oldest_rows(): void
    {
        $sid = $this->school->id;
        foreach (['2026-01-05', '2026-10-07', '2026-12-01'] as $i => $date) {
            Schedule::withoutGlobalScope('school')->create([
                'school_id' => $sid, 'instructor_id' => $this->ravi->id, 'session_date' => $date,
                'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled',
            ]);
        }

        $this->getJson("/api/schools/{$sid}/schedules?from=2026-11-30&to=2026-12-06")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.sessionDate', '2026-12-01');
        // Ranges over the cap, or reversed, are refused.
        $this->getJson("/api/schools/{$sid}/schedules?from=2026-01-01&to=2026-12-31")->assertUnprocessable();
        $this->getJson("/api/schools/{$sid}/schedules?from=2026-12-06&to=2026-11-30")->assertUnprocessable();

        $user = User::factory()->create(['role' => 'instructor', 'school_id' => $sid]);
        $this->ravi->update(['user_id' => $user->id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/instructor/sessions?from=2026-01-01&to=2026-01-31')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.sessionDate', '2026-01-05');
    }

    public function test_attendance_and_leave_reviews_are_audited(): void
    {
        $id = $this->book()->json('id');
        $this->postJson("/api/schedules/{$id}/attendance", ['status' => 'present'])->assertOk();

        $leave = LeaveRequest::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'instructor_id' => $this->meera->id,
            'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'status' => 'pending',
        ]);
        $this->patchJson("/api/leave-requests/{$leave->id}", ['status' => 'approved'])->assertOk();

        $this->assertTrue(AuditLog::where('action', 'attendance')->where('model_id', $id)->exists());
        $this->assertTrue(AuditLog::where('action', 'review')->where('model_type', 'LeaveRequest')->exists());
    }

    public function test_duplicate_vehicle_registration_is_a_validation_error(): void
    {
        $sid = $this->school->id;
        $this->postJson("/api/schools/{$sid}/vehicles", ['registrationNumber' => 'MH12AB1234'])->assertCreated();
        $this->postJson("/api/schools/{$sid}/vehicles", ['registrationNumber' => 'mh12ab1234'])
            ->assertUnprocessable()->assertJsonValidationErrors('registrationNumber');

        $other = Vehicle::withoutGlobalScope('school')->create(['school_id' => $sid, 'registration_number' => 'MH12CD5678', 'type' => 'car', 'status' => 'active']);
        $this->patchJson("/api/vehicles/{$other->id}", ['registrationNumber' => 'MH12AB1234'])->assertUnprocessable();
        $this->patchJson("/api/vehicles/{$other->id}", ['registrationNumber' => 'MH12CD5678', 'status' => 'maintenance'])->assertOk();
        $this->assertTrue(AuditLog::where('action', 'update')->where('model_type', 'Vehicle')->exists());
    }

    public function test_document_status_rules(): void
    {
        $sid = $this->school->id;
        $withFile = LearnerDocument::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'learner_id' => $this->asha->id, 'type' => 'pan', 'file_path' => 'x/pan.pdf', 'status' => 'uploaded',
        ]);
        $empty = LearnerDocument::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'learner_id' => $this->asha->id, 'type' => 'photo', 'status' => 'pending',
        ]);
        $url = fn ($d) => "/api/learners/{$this->asha->id}/documents/{$d->id}";

        $this->patchJson($url($withFile), ['status' => 'verified'])->assertOk()->assertJsonPath('status', 'verified');
        $this->patchJson($url($withFile), ['status' => 'pending'])->assertUnprocessable();
        $this->patchJson($url($empty), ['status' => 'verified'])->assertUnprocessable();
        $this->assertTrue(AuditLog::where('action', 'document_verified')->where('model_id', $withFile->id)->exists());

        $doc = InstructorDocument::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'instructor_id' => $this->ravi->id, 'type' => 'driving_license', 'status' => 'pending',
        ]);
        $this->patchJson("/api/instructors/{$this->ravi->id}/documents/{$doc->id}", ['status' => 'uploaded'])->assertUnprocessable();
        $this->patchJson("/api/instructors/{$this->ravi->id}/documents/{$doc->id}", ['status' => 'rejected'])->assertOk();
        $this->assertTrue(AuditLog::where('action', 'document_rejected')->where('model_type', 'InstructorDocument')->exists());
    }
}
