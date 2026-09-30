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

/** DIQ-910 / DIQ-911: learner portal data and the public trainer list. */
class LearnerPortalAndTrainersTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Portal School', 'slug' => 'portal-school-910', 'user_id' => $owner->id]);
    }

    private function trainer(array $attrs): Instructor
    {
        return Instructor::withoutGlobalScope('school')->create($attrs + [
            'school_id' => $this->school->id, 'status' => 'active', 'mobile' => '9800000000', 'email' => 't@example.com',
        ]);
    }

    public function test_learner_sees_their_trainers_number_and_session_feedback(): void
    {
        $ravi = $this->trainer(['name' => 'Ravi', 'mobile' => '9811111111']);
        $user = User::factory()->create(['role' => 'learner', 'school_id' => $this->school->id]);
        $asha = Learner::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'name' => 'Asha', 'status' => 'active', 'assigned_instructor_id' => $ravi->id,
        ]);
        Schedule::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'instructor_id' => $ravi->id, 'learner_id' => $asha->id,
            'session_date' => now()->subDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
            'status' => 'completed', 'session_summary' => 'Hill starts',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/learner/me')->assertOk()->assertJsonPath('learner.instructorMobile', '9811111111');
        $this->getJson("/api/learners/{$asha->id}/sessions")->assertOk()->assertJsonPath('0.sessionSummary', 'Hill starts');
        $this->getJson("/api/learners/{$asha->id}/driving-tests")->assertOk();
        $this->getJson('/api/training-skills')->assertOk()->assertJsonPath('skills.0.label', 'Vehicle controls');
    }

    public function test_public_trainers_are_opted_in_active_and_without_contact_details(): void
    {
        $this->trainer(['name' => 'Public Meera', 'public_visible' => true, 'women_instructor' => true, 'languages' => ['Marathi']]);
        $this->trainer(['name' => 'Private Ravi', 'public_visible' => false]);
        $this->trainer(['name' => 'Left Sunil', 'public_visible' => true, 'status' => 'inactive']);

        $res = $this->getJson("/api/schools/slug/{$this->school->slug}/trainers")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Public Meera')
            ->assertJsonPath('0.womenInstructor', true);
        $this->assertArrayNotHasKey('mobile', $res->json('0'));
        $this->assertArrayNotHasKey('email', $res->json('0'));
    }
}
