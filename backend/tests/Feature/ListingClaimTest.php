<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\ListingClaim;
use App\Models\Locality;
use App\Models\OutboundMessage;
use App\Models\Prospect;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\ClaimCodeNotification;
use App\Notifications\VerifyOwnerEmail;
use App\Services\ListingClaimService;
use App\Services\OutreachSuppression;
use App\Services\ProspectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1104: owners claim the listing we built for them. */
class ListingClaimTest extends TestCase
{
    use RefreshDatabase;

    private Prospect $prospect;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $locality = Locality::create(['name' => 'Aundh', 'slug' => 'aundh']);
        $this->prospect = Prospect::create([
            'type' => 'school', 'name' => 'Aundh Auto School', 'phone' => '9822011111', 'email' => 'owner@aundhauto.in',
            'locality_id' => $locality->id, 'latitude' => 18.558, 'longitude' => 73.807,
        ]);
        $this->school = app(ProspectService::class)->createListing($this->prospect);
    }

    private function link(): string
    {
        $url = app(ListingClaimService::class)->issue($this->school, $this->prospect)['url'];

        return substr($url, strrpos($url, '/') + 1);
    }

    /** Sends a code by email and returns it as the owner would read it. */
    private function emailCode(string $token): string
    {
        $this->postJson("/api/claims/{$token}/code", ['channel' => 'email'])->assertOk()->assertJsonPath('sentTo', 'ow***@aundhauto.in');

        $code = null;
        Notification::assertSentTo(new AnonymousNotifiable, ClaimCodeNotification::class, function ($n, $channels, $notifiable) use (&$code) {
            $code = $n->code;

            return $notifiable->routes['mail'] === 'owner@aundhauto.in';
        });

        return $code;
    }

    private function account(array $overrides = []): array
    {
        return array_merge(['name' => 'Rajesh More', 'email' => 'owner@aundhauto.in', 'password' => 'Secret123'], $overrides);
    }

    public function test_claim_with_an_email_code(): void
    {
        $token = $this->link();
        $this->getJson("/api/claims/{$token}")->assertOk()
            ->assertJsonPath('listing.name', 'Aundh Auto School')
            ->assertJsonPath('channels.email', 'ow***@aundhauto.in');

        $code = $this->emailCode($token);
        $res = $this->postJson("/api/claims/{$token}/complete", [...$this->account(), 'code' => $code])->assertCreated();

        $owner = User::find($res->json('user.id'));
        $school = $this->school->fresh();
        $this->assertSame($school->id, $owner->school_id);
        $this->assertSame($owner->id, $school->user_id);
        $this->assertTrue($owner->hasVerifiedEmail(), 'the code proved this address');
        $this->assertNotNull($school->claimed_at);
        // Phone, locality and location came from the prospect, so it is live.
        $this->assertSame('published', $res->json('listingStatus'));
        $this->assertSame('claimed', $this->prospect->fresh()->stage);
        $this->assertSame(1, Subscription::withoutGlobalScope('school')->where('school_id', $school->id)->where('status', 'trial')->count());
        $this->assertSame(1, AuditLog::where('action', 'claimed')->count());

        // The new owner is signed in and the link is spent.
        Sanctum::actingAs($owner);
        $this->getJson("/api/schools/{$school->id}/dashboard")->assertOk();
        $this->getJson("/api/claims/{$token}")->assertStatus(410);
    }

    public function test_a_different_account_email_must_still_be_confirmed(): void
    {
        $token = $this->link();
        $code = $this->emailCode($token);

        $res = $this->postJson("/api/claims/{$token}/complete", [...$this->account(['email' => 'rajesh@gmail.com']), 'code' => $code])->assertCreated();

        $owner = User::find($res->json('user.id'));
        $this->assertFalse($owner->hasVerifiedEmail());
        Notification::assertSentTo($owner, VerifyOwnerEmail::class);
        $this->assertSame('draft', $res->json('listingStatus'));
    }

    public function test_wrong_codes_are_limited_and_expired_codes_fail(): void
    {
        RateLimiter::clear('public-forms');
        $token = $this->link();
        $code = $this->emailCode($token);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson("/api/claims/{$token}/complete", [...$this->account(), 'code' => $wrong])
                ->assertUnprocessable()->assertJsonPath('errors.code.0', 'That code is not right.');
        }
        // Fifth try is the last; afterwards even the right code is refused.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])->postJson("/api/claims/{$token}/complete", [...$this->account(), 'code' => $wrong])->assertUnprocessable();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.2'])->postJson("/api/claims/{$token}/complete", [...$this->account(), 'code' => $code])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'This code has expired or was tried too often. Ask for a new one.');

        $fresh = $this->emailCode($token);
        $this->travel(ListingClaimService::CODE_MINUTES + 1)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.3'])->postJson("/api/claims/{$token}/complete", [...$this->account(), 'code' => $fresh])->assertUnprocessable();
        $this->assertNull($this->school->fresh()->user_id);
    }

    public function test_codes_per_hour_are_capped(): void
    {
        $token = $this->link();
        foreach (range(1, ListingClaimService::CODES_PER_HOUR) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$i}"])->postJson("/api/claims/{$token}/code", ['channel' => 'email'])->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->postJson("/api/claims/{$token}/code", ['channel' => 'email'])
            ->assertUnprocessable()->assertJsonValidationErrors('channel');
    }

    public function test_sms_code_goes_through_the_messaging_layer(): void
    {
        config(['messaging.driver' => 'log']);
        $token = $this->link();
        $this->getJson("/api/claims/{$token}")->assertJsonPath('channels.sms', '+91******1111');
        $this->postJson("/api/claims/{$token}/code", ['channel' => 'sms'])->assertOk();

        $log = OutboundMessage::sole();
        $this->assertSame(['sms', 'claim_code', 'sent'], [$log->channel, $log->template, $log->status]);

        // With the null driver nothing would arrive, so SMS is not offered.
        config(['messaging.driver' => null]);
        $this->getJson("/api/claims/{$token}")->assertJsonMissingPath('channels.sms');
        $this->postJson("/api/claims/{$token}/code", ['channel' => 'sms'])->assertUnprocessable();
    }

    public function test_decline_removes_the_listing_and_suppresses_outreach(): void
    {
        $token = $this->link();
        $this->postJson("/api/claims/{$token}/decline")->assertOk();

        $this->assertNull(School::find($this->school->id));
        $this->assertSame('do_not_contact', $this->prospect->fresh()->stage);
        $this->assertTrue(app(OutreachSuppression::class)->isSuppressed('owner@aundhauto.in'));
        $this->getJson("/api/claims/{$token}")->assertNotFound();
    }

    public function test_bad_or_expired_links(): void
    {
        $this->getJson('/api/claims/not-a-token')->assertNotFound();

        $token = $this->link();
        $this->travel(ListingClaimService::LINK_DAYS + 1)->days();
        $this->getJson("/api/claims/{$token}")->assertStatus(410);
        $this->postJson("/api/claims/{$token}/code", ['channel' => 'email'])->assertStatus(410);
    }

    public function test_claiming_a_trainer_listing_creates_the_trainer_profile(): void
    {
        $this->school->forceFill(['listing_type' => 'trainer'])->save();
        $token = $this->link();
        $code = $this->emailCode($token);
        $res = $this->postJson("/api/claims/{$token}/complete", [...$this->account(), 'code' => $code])->assertCreated();

        $this->assertSame($res->json('user.id'), Instructor::withoutGlobalScope('school')->where('school_id', $this->school->id)->sole()->user_id);
    }

    public function test_admin_issues_a_claim_link(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $url = $this->postJson("/api/admin/prospects/{$this->prospect->id}/claim-link")->assertCreated()->json('url');
        $this->assertStringStartsWith(config('app.frontend_url').'/claim/', $url);
        $this->assertSame(1, ListingClaim::count());
        $this->assertDatabaseMissing('listing_claims', ['token_hash' => substr($url, strrpos($url, '/') + 1)]);

        $other = Prospect::create(['type' => 'school', 'name' => 'No listing']);
        $this->postJson("/api/admin/prospects/{$other->id}/claim-link")->assertUnprocessable();
    }
}
