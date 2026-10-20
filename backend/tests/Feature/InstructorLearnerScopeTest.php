<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-303: instructors see and update progress only for learners they teach. */
class InstructorLearnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Scope School', 'slug' => 'scope-school']);
    }

    /** @return array{User, Instructor} */
    private function instructor(string $name): array
    {
        $user = User::factory()->create(['role' => 'instructor', 'school_id' => $this->school->id]);
        $instructor = Instructor::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'user_id' => $user->id,
            'name' => $name,
            'status' => 'active',
        ]);

        return [$user, $instructor];
    }

    private function learner(?int $assignedInstructorId = null): Learner
    {
        return Learner::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'name' => 'Learner',
            'status' => 'active',
            'assigned_instructor_id' => $assignedInstructorId,
        ]);
    }

    private function progressUpdate(Learner $learner)
    {
        return $this->putJson("/api/learners/{$learner->id}/progress", [
            'skillName' => 'parking',
            'percentage' => 50,
        ]);
    }

    public function test_assigned_instructor_can_view_and_update_progress(): void
    {
        [$user, $instructor] = $this->instructor('Assigned');
        $learner = $this->learner($instructor->id);

        Sanctum::actingAs($user);
        $this->getJson("/api/learners/{$learner->id}/progress")->assertOk();
        $this->progressUpdate($learner)->assertOk();
        $this->getJson("/api/learners/{$learner->id}/sessions")->assertOk();
    }

    public function test_unassigned_instructor_in_same_school_is_forbidden(): void
    {
        [, $assigned] = $this->instructor('Assigned');
        [$otherUser] = $this->instructor('Other');
        $learner = $this->learner($assigned->id);

        Sanctum::actingAs($otherUser);
        $this->getJson("/api/learners/{$learner->id}/progress")->assertForbidden();
        $this->progressUpdate($learner)->assertForbidden();
        $this->getJson("/api/learners/{$learner->id}/driving-tests")->assertForbidden();
    }

    public function test_instructor_with_a_session_for_the_learner_is_allowed(): void
    {
        [, $assigned] = $this->instructor('Assigned');
        [$substituteUser, $substitute] = $this->instructor('Substitute');
        $learner = $this->learner($assigned->id);

        Schedule::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'learner_id' => $learner->id,
            'instructor_id' => $substitute->id,
            'session_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($substituteUser);
        $this->getJson("/api/learners/{$learner->id}/progress")->assertOk();
        $this->progressUpdate($learner)->assertOk();
    }
}
