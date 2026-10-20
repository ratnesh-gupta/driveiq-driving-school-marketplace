<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use App\Notifications\VerifyOwnerEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1102: school and trainer owners confirm their email before their listing goes live. */
class OwnerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $role = 'school'): array
    {
        $res = $this->postJson('/api/auth/register', [
            'name' => 'Kiran Patil', 'email' => 'kiran@example.com', 'password' => 'Secret123', 'role' => $role,
        ])->assertCreated();

        return [User::find($res->json('user.id')), $res->json('schoolId')];
    }

    public function test_owner_gets_a_link_and_confirming_publishes_a_complete_listing(): void
    {
        Notification::fake();
        [$owner, $schoolId] = $this->register();

        $url = null;
        Notification::assertSentTo($owner, VerifyOwnerEmail::class, function (VerifyOwnerEmail $n) use ($owner, &$url) {
            $url = $n->verifyUrl($owner);

            return true;
        });

        $locality = Locality::create(['name' => 'Wakad', 'slug' => 'wakad']);
        School::find($schoolId)->update(['phone' => '9822012345', 'locality_id' => $locality->id, 'latitude' => 18.6, 'longitude' => 73.76]);
        $this->assertSame('draft', School::find($schoolId)->listing_status);

        $this->get($url)->assertRedirect(config('app.frontend_url').'/dashboard?verified=1');

        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
        $this->assertSame('published', School::find($schoolId)->listing_status);
        $this->assertSame(1, AuditLog::where('action', 'email_verified')->count());
    }

    public function test_tampered_or_expired_links_do_nothing(): void
    {
        Notification::fake();
        [$owner] = $this->register();
        $url = (new VerifyOwnerEmail)->verifyUrl($owner);

        $this->get(str_replace('/verify/'.$owner->id.'/', '/verify/'.$owner->id.'/x', $url))->assertForbidden();

        $this->travel(VerifyOwnerEmail::EXPIRES_DAYS + 1)->days();
        $this->get($url)->assertForbidden();

        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_for_an_old_email_address_is_refused(): void
    {
        Notification::fake();
        [$owner] = $this->register();
        $url = (new VerifyOwnerEmail)->verifyUrl($owner);
        $owner->update(['email' => 'new@example.com']);

        $this->get($url)->assertRedirect(config('app.frontend_url').'/dashboard?verified=invalid');
        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
    }

    public function test_resend_is_for_unconfirmed_owners_only(): void
    {
        Notification::fake();
        [$owner] = $this->register();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/email/verification-notification')->assertOk();
        Notification::assertSentToTimes($owner, VerifyOwnerEmail::class, 2);

        $owner->markEmailAsVerified();
        $this->postJson('/api/auth/email/verification-notification')->assertOk()->assertJsonPath('message', 'Your email is already confirmed.');
        Notification::assertSentToTimes($owner, VerifyOwnerEmail::class, 2);
    }

    public function test_learners_are_not_asked_to_confirm(): void
    {
        Notification::fake();
        [$learner] = $this->register('learner');

        Notification::assertNothingSent();
        Sanctum::actingAs($learner);
        $this->postJson('/api/auth/email/verification-notification')->assertUnprocessable();
    }
}
