<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\School;
use App\Models\User;
use App\Notifications\StaffLoginInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-907: trainer logins are created with a set-password email, never a silent password. */
class InstructorLoginInviteTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Invite School', 'slug' => 'invite-school', 'user_id' => $owner->id]);
        $owner->update(['school_id' => $this->school->id]);
        Sanctum::actingAs($owner->refresh());
    }

    public function test_creating_with_a_login_emails_a_set_password_link(): void
    {
        $this->postJson("/api/schools/{$this->school->id}/instructors", [
            'name' => 'Coach', 'email' => 'Coach@Example.com', 'createLogin' => true,
        ])->assertCreated()->assertJsonPath('hasLogin', true);

        $user = User::where('email', 'coach@example.com')->firstOrFail();
        $this->assertSame('instructor', $user->role);
        Notification::assertSentTo($user, StaffLoginInvite::class, function (StaffLoginInvite $n) use ($user) {
            $url = $n->setPasswordUrl($user);

            return str_contains($url, '/auth/reset-password?token=') && str_contains($url, 'coach%40example.com');
        });
    }

    public function test_login_can_be_added_later_and_resent(): void
    {
        $coach = Instructor::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Ravi', 'status' => 'active',
        ]);

        $this->postJson("/api/instructors/{$coach->id}/login")->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson("/api/instructors/{$coach->id}/login", ['email' => 'ravi@example.com'])
            ->assertOk()->assertJsonPath('hasLogin', true)->assertJsonPath('email', 'ravi@example.com');
        $this->postJson("/api/instructors/{$coach->id}/login")->assertOk();

        $user = User::where('email', 'ravi@example.com')->firstOrFail();
        $this->assertSame($user->id, $coach->fresh()->user_id);
        Notification::assertSentToTimes($user, StaffLoginInvite::class, 2);
    }

    public function test_someone_elses_account_is_never_attached(): void
    {
        User::factory()->create(['role' => 'learner', 'email' => 'taken@example.com']);
        $coach = Instructor::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Ravi', 'status' => 'active',
        ]);

        $this->postJson("/api/instructors/{$coach->id}/login", ['email' => 'taken@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertNull($coach->fresh()->user_id);
        Notification::assertNothingSent();

        Sanctum::actingAs($this->otherSchoolUser());
        $this->postJson("/api/instructors/{$coach->id}/login", ['email' => 'x@example.com'])->assertForbidden();
    }
}
