<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-403: optional manager invites with explicit acceptance. */
class TeamInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Invite School', 'slug' => 'invite-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        SchoolAdmin::create(['school_id' => $this->school->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'status' => 'active']);
    }

    /** Invite as the owner and return the emailed token. */
    private function invite(string $email): string
    {
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/schools/{$this->school->id}/team", ['email' => $email])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('role', 'manager');

        $token = null;
        Notification::assertSentTo(
            new AnonymousNotifiable,
            TeamInvitation::class,
            function (TeamInvitation $n, array $channels, AnonymousNotifiable $to) use ($email, &$token) {
                if (($to->routes['mail'] ?? null) !== $email) {
                    return false;
                }
                $token = $n->token;

                return str_starts_with($n->acceptUrl(), config('app.frontend_url').'/team/accept?token=');
            }
        );

        $this->app['auth']->forgetGuards();

        return $token;
    }

    public function test_new_person_accepts_by_setting_a_password(): void
    {
        $token = $this->invite('new.manager@example.com');

        // Nothing is created until acceptance.
        $this->assertDatabaseMissing('users', ['email' => 'new.manager@example.com']);

        $this->getJson("/api/team/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('schoolName', 'Invite School')
            ->assertJsonPath('hasAccount', false);

        $accepted = $this->postJson('/api/team/accept', [
            'token' => $token,
            'name' => 'New Manager',
            'password' => 'Manag3rPass',
            'password_confirmation' => 'Manag3rPass',
        ])->assertOk()
            ->assertJsonPath('schoolId', $this->school->id)
            ->assertJsonPath('schoolRole', 'manager');

        $this->assertNotNull($accepted->json('token'));
        $this->assertDatabaseHas('users', [
            'email' => 'new.manager@example.com',
            'role' => 'school',
            'school_id' => $this->school->id,
        ]);

        $this->withToken($accepted->json('token'))->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('schoolRole', 'manager');

        // Single use.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/team/accept', ['token' => $token, 'name' => 'x', 'password' => 'Manag3rPass', 'password_confirmation' => 'Manag3rPass'])
            ->assertNotFound();
    }

    public function test_only_managers_can_be_invited(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/schools/{$this->school->id}/team", ['email' => 'x@example.com', 'role' => 'owner'])
            ->assertUnprocessable();
    }

    public function test_existing_learners_and_other_schools_staff_are_refused(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $otherStaff = $this->otherSchoolUser();

        Sanctum::actingAs($this->owner);
        foreach ([$learner, $otherStaff] as $user) {
            $this->postJson("/api/schools/{$this->school->id}/team", ['email' => $user->email])
                ->assertStatus(422);
            $this->assertSame($user->role, $user->fresh()->role);
        }
    }

    public function test_expired_or_unknown_invitation_is_rejected(): void
    {
        $token = $this->invite('late@example.com');
        SchoolAdmin::withoutGlobalScope('school')->where('invite_email', 'late@example.com')
            ->update(['invite_expires_at' => now()->subMinute()]);

        $this->getJson("/api/team/invitations/{$token}")->assertNotFound();
        $this->getJson('/api/team/invitations/not-a-token')->assertNotFound();
    }

    public function test_removed_manager_is_signed_out_and_can_be_reinvited_but_must_sign_in_to_accept(): void
    {
        $token = $this->invite('again@example.com');
        $manager = User::find($this->postJson('/api/team/accept', [
            'token' => $token, 'name' => 'Again', 'password' => 'Manag3rPass', 'password_confirmation' => 'Manag3rPass',
        ])->assertOk()->json('user.id'));
        $member = SchoolAdmin::withoutGlobalScope('school')->where('user_id', $manager->id)->first();

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/schools/{$this->school->id}/team/{$member->id}")->assertNoContent();

        $manager->refresh();
        $this->assertNull($manager->school_id);
        $this->assertSame('learner', $manager->role);
        $this->assertSame(0, $manager->tokens()->count());

        // Re-invite: they already have an account, so they must accept while signed in.
        $token = $this->invite('again@example.com');
        $this->getJson("/api/team/invitations/{$token}")->assertJsonPath('hasAccount', true);
        $this->postJson('/api/team/accept', ['token' => $token])->assertForbidden();

        Sanctum::actingAs($manager);
        $this->postJson('/api/team/accept', ['token' => $token])->assertOk()->assertJsonPath('token', null);
        $this->assertSame($this->school->id, (int) $manager->fresh()->school_id);
    }

    public function test_owner_cannot_be_removed(): void
    {
        $ownerRow = SchoolAdmin::withoutGlobalScope('school')->where('user_id', $this->owner->id)->first();

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/schools/{$this->school->id}/team/{$ownerRow->id}")->assertStatus(422);
    }
}
