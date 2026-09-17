<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearnerProgressTest extends TestCase
{
    use RefreshDatabase;

    private function seed(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-p8']);
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'name' => 'Progress School',
            'slug' => 'progress-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000011000',
            'user_id' => $owner->id,
        ]);
        $owner->update(['school_id' => $school->id]);

        $learner = Learner::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Asha',
            'status' => 'active',
        ]);

        return compact('owner', 'school', 'learner');
    }

    public function test_progress_snapshot_and_update(): void
    {
        ['owner' => $owner, 'learner' => $learner] = $this->seed();
        Sanctum::actingAs($owner);

        $this->getJson('/api/learners/'.$learner->id.'/progress')
            ->assertOk()
            ->assertJsonPath('overallCompletion', 0)
            ->assertJsonCount(6, 'skills');

        $this->putJson('/api/learners/'.$learner->id.'/progress', [
            'skillName' => 'parking',
            'percentage' => 80,
            'notes' => 'Parallel parking improving',
        ])->assertOk()
            ->assertJsonPath('overallCompletion', 13); // round(80/6)
    }

    public function test_driving_test_lifecycle(): void
    {
        ['owner' => $owner, 'learner' => $learner] = $this->seed();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/learners/'.$learner->id.'/driving-tests', [
            'testDate' => '2026-11-01',
            'rtoName' => 'Pune RTO',
            'rtoLocation' => 'Shivajinagar',
        ])->assertCreated()
            ->assertJsonPath('status', 'scheduled')
            ->assertJsonPath('attemptNumber', 1);

        $this->patchJson('/api/driving-tests/'.$created->json('id'), [
            'status' => 'passed',
        ])->assertOk()
            ->assertJsonPath('status', 'passed');

        $this->assertDatabaseHas('learners', [
            'id' => $learner->id,
            'permanent_license_status' => 'passed',
        ]);
    }

    public function test_other_school_cannot_view_progress(): void
    {
        ['learner' => $learner] = $this->seed();
        $other = User::factory()->create(['role' => 'school', 'school_id' => 999]);

        Sanctum::actingAs($other);

        $this->getJson('/api/learners/'.$learner->id.'/progress')->assertForbidden();
    }

    public function test_skills_catalog_public(): void
    {
        $this->getJson('/api/training-skills')
            ->assertOk()
            ->assertJsonFragment(['code' => 'highway_driving']);
    }
}
