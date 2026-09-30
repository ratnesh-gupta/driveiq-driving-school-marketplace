<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-908: what a trainer sees and does in their own portal. */
class InstructorPortalTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $raviUser;

    private Instructor $ravi;

    private Instructor $meera;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Portal School', 'slug' => 'portal-school', 'user_id' => $owner->id]);
        $owner->update(['school_id' => $this->school->id]);
        $sid = $this->school->id;
        $this->raviUser = User::factory()->create(['role' => 'instructor', 'school_id' => $sid]);
        $this->ravi = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'user_id' => $this->raviUser->id, 'name' => 'Ravi', 'status' => 'active']);
        $this->meera = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Meera', 'status' => 'active']);
    }

    public function test_roster_lists_only_my_learners_with_progress_and_next_session(): void
    {
        $sid = $this->school->id;
        $mine = Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Asha', 'status' => 'active', 'assigned_instructor_id' => $this->ravi->id]);
        $viaSession = Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Bina', 'status' => 'active', 'assigned_instructor_id' => $this->meera->id]);
        Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Chetan', 'status' => 'active', 'assigned_instructor_id' => $this->meera->id]);
        Schedule::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'instructor_id' => $this->ravi->id, 'learner_id' => $viaSession->id,
            'session_date' => now()->addDays(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled',
        ]);

        Sanctum::actingAs($this->raviUser);
        $this->putJson("/api/learners/{$mine->id}/progress", ['skillName' => 'parking', 'percentage' => 60])->assertOk();

        $rows = collect($this->getJson('/api/instructor/learners')->assertOk()->json())->keyBy('name');
        $this->assertEqualsCanonicalizing(['Asha', 'Bina'], $rows->keys()->all());
        $this->assertTrue($rows['Asha']['assignedToMe']);
        $this->assertSame(10, $rows['Asha']['overallCompletion']);
        $this->assertFalse($rows['Bina']['assignedToMe']);
        $this->assertSame(now()->addDays(2)->toDateString(), $rows['Bina']['nextSessionDate']);
    }

    public function test_trainer_requests_their_own_leave_and_staff_are_told(): void
    {
        Sanctum::actingAs($this->raviUser);
        $from = now()->addDays(3)->toDateString();

        $this->postJson('/api/instructor/leave-requests', ['startDate' => now()->subDay()->toDateString(), 'endDate' => $from])
            ->assertUnprocessable()->assertJsonValidationErrors('startDate');
        // An instructorId in the body is ignored: it is always the caller's own leave.
        $this->postJson('/api/instructor/leave-requests', ['startDate' => $from, 'endDate' => $from, 'reason' => 'Family', 'instructorId' => $this->meera->id])
            ->assertCreated()->assertJsonPath('status', 'pending')->assertJsonPath('instructorId', $this->ravi->id);

        $this->getJson('/api/instructor/leave-requests')->assertOk()->assertJsonCount(1);
        $this->assertSame(0, LeaveRequest::withoutGlobalScope('school')->where('instructor_id', $this->meera->id)->count());
        $this->assertTrue(AppNotification::where('type', 'leave_requested')->where('user_id', $this->school->user_id)->exists());

        // The school-side endpoint stays staff-only.
        $this->postJson("/api/schools/{$this->school->id}/leave-requests", ['instructorId' => $this->meera->id, 'startDate' => $from, 'endDate' => $from])
            ->assertForbidden();
    }

    public function test_all_four_attendance_outcomes_with_a_summary(): void
    {
        Sanctum::actingAs($this->raviUser);
        $expected = ['present' => 'completed', 'absent' => 'completed', 'rescheduled' => 'rescheduled', 'cancelled' => 'cancelled'];

        foreach ($expected as $attendance => $sessionStatus) {
            $s = Schedule::withoutGlobalScope('school')->create([
                'school_id' => $this->school->id, 'instructor_id' => $this->ravi->id, 'learner_name' => 'Walk-in',
                'session_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled',
            ]);
            $this->postJson("/api/schedules/{$s->id}/attendance", ['status' => $attendance, 'sessionSummary' => 'Worked on clutch control'])
                ->assertOk()->assertJsonPath('sessionStatus', $sessionStatus);
            $this->assertSame('Worked on clutch control', $s->fresh()->session_summary);
        }
    }
}
