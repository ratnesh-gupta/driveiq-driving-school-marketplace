<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\AppNotification;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** BSEC-04 trainer offboarding, BSEC-05 broadcast channels, BSEC-24 dead instructor flags. */
class TrainerOffboardingAndChannelsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $owner;

    private User $trainerUser;

    private Instructor $trainer;

    private Learner $assigned;

    private Learner $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Off School', 'slug' => 'off-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        $sid = $this->school->id;
        $this->trainerUser = User::factory()->create(['role' => 'instructor', 'school_id' => $sid, 'email' => 'tr@example.com', 'password' => Hash::make('trainer-pass')]);
        $this->trainer = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'user_id' => $this->trainerUser->id, 'name' => 'Tr', 'status' => 'active']);
        $other = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Other', 'status' => 'active']);
        $this->assigned = Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Mine', 'mobile' => '+919800000001', 'status' => 'active', 'assigned_instructor_id' => $this->trainer->id]);
        $past = Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Past', 'mobile' => '+919800000002', 'status' => 'active', 'assigned_instructor_id' => $other->id]);
        Schedule::withoutGlobalScope('school')->create([
            'school_id' => $sid, 'instructor_id' => $this->trainer->id, 'learner_id' => $past->id,
            'session_date' => now()->subDays(5)->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed',
        ]);
        $this->stranger = $past;
    }

    public function test_roster_hides_phone_of_learners_not_currently_taught(): void
    {
        Sanctum::actingAs($this->trainerUser);
        $rows = collect($this->getJson('/api/instructor/learners')->assertOk()->json())->keyBy('name');
        $this->assertSame('+919800000001', $rows['Mine']['mobile']);
        $this->assertNull($rows['Past']['mobile']);
    }

    public function test_terminating_a_trainer_revokes_tokens_and_blocks_access_and_login(): void
    {
        $token = $this->trainerUser->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/instructor/learners')->assertOk();

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/instructors/{$this->trainer->id}")->assertNoContent();
        $this->assertSame(0, $this->trainerUser->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'deactivate', 'model_type' => 'Instructor', 'school_id' => $this->school->id]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/instructor/learners')->assertUnauthorized();

        // Even a fresh token (e.g. issued before the fix) cannot read the roster or progress.
        Sanctum::actingAs($this->trainerUser);
        $this->getJson('/api/instructor/learners')->assertForbidden();
        $this->getJson("/api/learners/{$this->assigned->id}/progress")->assertForbidden();

        $this->postJson('/api/auth/login', ['email' => 'tr@example.com', 'password' => 'trainer-pass'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_marking_a_trainer_inactive_via_update_locks_them_out_and_reactivation_restores(): void
    {
        $this->trainerUser->createToken('t');
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/instructors/{$this->trainer->id}", ['status' => 'inactive'])->assertOk();
        $this->assertSame(0, $this->trainerUser->tokens()->count());

        Sanctum::actingAs($this->trainerUser);
        $this->getJson('/api/instructor/sessions')->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/instructors/{$this->trainer->id}", ['status' => 'active'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'tr@example.com', 'password' => 'trainer-pass'])->assertOk();
    }

    public function test_other_school_owner_cannot_terminate_the_trainer(): void
    {
        $this->trainerUser->createToken('t');
        Sanctum::actingAs($this->otherSchoolUser());
        $this->deleteJson("/api/instructors/{$this->trainer->id}")->assertForbidden();
        $this->assertSame(1, $this->trainerUser->tokens()->count());
        $this->assertSame('active', $this->trainer->fresh()->status);
    }

    public function test_instructor_cannot_use_staff_trainer_endpoints(): void
    {
        Sanctum::actingAs($this->trainerUser);
        $this->getJson("/api/schools/{$this->school->id}/instructors")->assertForbidden();
        $this->getJson("/api/instructors/{$this->trainer->id}")->assertForbidden();
        $this->getJson("/api/schools/{$this->school->id}/entitlements")->assertForbidden();
    }

    public function test_user_channel_is_laravel_standard_and_private_to_the_owner(): void
    {
        $event = new NotificationCreated(AppNotification::create([
            'user_id' => $this->owner->id, 'school_id' => $this->school->id, 'type' => 't', 'title' => 'x', 'body' => 'y',
        ]));
        $names = array_map(fn ($c) => $c->name, $event->broadcastOn());
        $this->assertContains('private-App.Models.User.'.$this->owner->id, $names);
        $this->assertContains('private-school.'.$this->school->id, $names);

        $this->assertTrue($this->authorizeChannel($this->owner, 'App.Models.User.'.$this->owner->id));
        $this->assertFalse($this->authorizeChannel($this->trainerUser, 'App.Models.User.'.$this->owner->id));
        $this->assertFalse($this->authorizeChannel($this->owner, 'user.'.$this->owner->id));
    }

    public function test_school_channel_admits_staff_and_admin_only(): void
    {
        $manager = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id]);
        SchoolAdmin::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'user_id' => $manager->id, 'role' => 'manager', 'status' => 'active']);
        $learnerUser = User::factory()->create(['role' => 'learner', 'school_id' => $this->school->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $channel = 'school.'.$this->school->id;

        $this->assertTrue($this->authorizeChannel($this->owner, $channel));
        $this->assertTrue($this->authorizeChannel($manager, $channel));
        $this->assertTrue($this->authorizeChannel($admin, $channel));
        $this->assertFalse($this->authorizeChannel($this->trainerUser, $channel));
        $this->assertFalse($this->authorizeChannel($learnerUser, $channel));
        $this->assertFalse($this->authorizeChannel($this->otherSchoolUser(), $channel));
    }

    public function test_broadcast_auth_requires_a_bearer_token(): void
    {
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-school.'.$this->school->id])->assertUnauthorized();
    }

    private function authorizeChannel(User $user, string $channel): bool
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
        $broadcaster = app(Broadcaster::class);

        // The log broadcaster has no auth; call the registered callback directly.
        $ref = new \ReflectionProperty(\Illuminate\Broadcasting\Broadcasters\Broadcaster::class, 'channels');
        foreach ($ref->getValue($broadcaster) as $pattern => $callback) {
            $regex = preg_replace('/\{(\w+)\}/', '(?<$1>[^.]+)', $pattern);
            if (preg_match('/^'.str_replace('.', '\.', $regex).'$/', $channel, $m)) {
                $args = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

                return (bool) $callback($user, ...array_map(fn ($v) => ctype_digit($v) ? (int) $v : $v, array_values($args)));
            }
        }

        return false;
    }
}
