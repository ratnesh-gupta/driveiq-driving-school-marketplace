<?php

namespace Tests\Feature;

use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function seed(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-p9']);
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'name' => 'Msg School',
            'slug' => 'msg-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000012000',
            'user_id' => $owner->id,
        ]);
        $owner->update(['school_id' => $school->id]);

        $instructor = User::factory()->create([
            'role' => 'instructor',
            'school_id' => $school->id,
            'name' => 'Trainer User',
        ]);

        $learner = User::factory()->create([
            'role' => 'learner',
            'school_id' => $school->id,
            'name' => 'Learner User',
        ]);

        $otherSchoolUser = User::factory()->create([
            'role' => 'school',
            'school_id' => 999,
        ]);

        return compact('owner', 'school', 'instructor', 'learner', 'otherSchoolUser');
    }

    public function test_school_can_message_instructor(): void
    {
        ['owner' => $owner, 'instructor' => $instructor] = $this->seed();
        Sanctum::actingAs($owner);

        $this->postJson('/api/messages', [
            'receiverId' => $instructor->id,
            'body' => 'Please confirm tomorrow sessions',
        ])->assertCreated()
            ->assertJsonPath('receiverId', $instructor->id);

        Sanctum::actingAs($instructor);
        $this->getJson('/api/messages/unread-count')
            ->assertOk()
            ->assertJsonPath('unreadCount', 1);

        $threads = $this->getJson('/api/messages/threads')->assertOk()->json();
        $this->assertCount(1, $threads);

        $this->getJson('/api/messages/threads/'.$threads[0]['id'])
            ->assertOk()
            ->assertJsonCount(1, 'messages');

        $this->getJson('/api/messages/unread-count')
            ->assertJsonPath('unreadCount', 0);
    }

    public function test_cross_school_messaging_blocked(): void
    {
        ['owner' => $owner, 'otherSchoolUser' => $other] = $this->seed();
        Sanctum::actingAs($owner);

        $this->postJson('/api/messages', [
            'receiverId' => $other->id,
            'body' => 'Hello other school',
        ])->assertStatus(422);
    }

    public function test_instructor_learner_thread(): void
    {
        ['instructor' => $instructor, 'learner' => $learner] = $this->seed();
        Sanctum::actingAs($instructor);

        $this->postJson('/api/messages', [
            'receiverId' => $learner->id,
            'body' => 'Session at 10am tomorrow',
        ])->assertCreated();

        Sanctum::actingAs($learner);
        $this->getJson('/api/messages/threads')
            ->assertOk()
            ->assertJsonCount(1);
    }
}
