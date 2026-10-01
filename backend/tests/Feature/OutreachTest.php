<?php

namespace Tests\Feature;

use App\Mail\OutreachEmail;
use App\Models\ListingClaim;
use App\Models\OutreachCampaign;
use App\Models\OutreachEnrollment;
use App\Models\OutreachMessage;
use App\Models\Prospect;
use App\Models\User;
use App\Services\OutreachSuppression;
use App\Services\ProspectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1105: outreach email campaigns. */
class OutreachTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Wednesday 11:00 IST: inside sending hours.
        $this->travelTo('2026-10-07 05:30:00');
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'team@driveiq.in']);
        Sanctum::actingAs($this->admin);
    }

    private function steps(): array
    {
        return [
            ['subject' => '{{name}} on DriveIQ', 'body' => "Hi {{contact}},\n\nLearners in {{locality}} look for schools like {{name}}. Your free {{listing}}: {{link}}"],
            ['subject' => 'Following up', 'body' => 'Just checking: {{link}}', 'delayDays' => 4],
        ];
    }

    private function campaign(string $audience = 'school'): OutreachCampaign
    {
        $id = $this->postJson('/api/admin/outreach/campaigns', ['name' => 'Pune pilot', 'audience' => $audience, 'steps' => $this->steps()])
            ->assertCreated()->json('id');

        return OutreachCampaign::find($id);
    }

    private function prospect(array $attrs = []): Prospect
    {
        return Prospect::create(array_merge(['type' => 'school', 'name' => 'Sai Motor School', 'email' => 'sai@example.com', 'contact_person' => 'Sunil'], $attrs));
    }

    private function activate(OutreachCampaign $c): void
    {
        $this->patchJson("/api/admin/outreach/campaigns/{$c->id}", ['status' => 'active'])->assertOk();
    }

    public function test_sequence_sends_step_one_then_step_two_after_the_delay(): void
    {
        $prospect = $this->prospect();
        app(ProspectService::class)->createListing($prospect);
        $c = $this->campaign();
        $this->postJson("/api/admin/outreach/campaigns/{$c->id}/enroll", ['dryRun' => true])->assertJsonPath('count', 1);
        $this->postJson("/api/admin/outreach/campaigns/{$c->id}/enroll", [])->assertJsonPath('count', 1);
        $this->postJson("/api/admin/outreach/campaigns/{$c->id}/enroll", [])->assertJsonPath('count', 0);

        // Draft campaigns send nothing.
        $this->artisan('driveiq:outreach')->assertSuccessful();
        Mail::assertNothingSent();

        $this->activate($c);
        $this->artisan('driveiq:outreach')->assertSuccessful();

        $claimUrl = null;
        Mail::assertSent(OutreachEmail::class, function (OutreachEmail $m) use (&$claimUrl) {
            $this->assertSame('Sai Motor School on DriveIQ', $m->subjectLine);
            $this->assertStringContainsString('Hi Sunil,', $m->body);
            $this->assertStringContainsString('Learners in Pune', $m->body);
            preg_match('~/claim/(\S+)~', $m->body, $match);
            $claimUrl = $match[1];
            $this->assertStringStartsWith(config('app.url').'/api/outreach/unsubscribe/', $m->unsubscribeUrl);
            $this->assertSame('List-Unsubscribe=One-Click', $m->headers()->text['List-Unsubscribe-Post']);
            $this->assertStringContainsString('Unsubscribe', $m->plainText());

            return $m->hasTo('sai@example.com') && $m->mailer === config('outreach.mailer');
        });
        $this->assertSame('contacted', $prospect->fresh()->stage);

        // Opening the claim link from the email counts as a click.
        $this->getJson("/api/claims/{$claimUrl}")->assertOk();
        $this->assertNotNull(OutreachMessage::sole()->clicked_at);
        $this->assertSame(1, ListingClaim::whereNotNull('outreach_message_id')->count());

        // Step two waits 4 days, and Sunday (day 4) is not a sending day.
        $this->artisan('driveiq:outreach');
        Mail::assertSentCount(1);
        $this->travel(4)->days();
        $this->artisan('driveiq:outreach');
        Mail::assertSentCount(1);
        $this->travel(1)->days();
        $this->artisan('driveiq:outreach');
        Mail::assertSentCount(2);
        $this->assertSame('completed', OutreachEnrollment::sole()->status);

        $this->getJson('/api/admin/outreach/campaigns')
            ->assertJsonPath('0.stats.sent', 2)->assertJsonPath('0.stats.clicked', 1)->assertJsonPath('0.stats.enrolled', 1);
    }

    public function test_daily_cap_and_sending_hours(): void
    {
        config(['outreach.daily_cap' => 2]);
        foreach (range(1, 3) as $i) {
            $this->prospect(['name' => "School {$i}", 'email' => "s{$i}@example.com"]);
        }
        $c = $this->campaign();
        $this->postJson("/api/admin/outreach/campaigns/{$c->id}/enroll", []);
        $this->activate($c);

        // Sunday: no sending.
        $this->travelTo('2026-10-11 05:30:00');
        $this->artisan('driveiq:outreach')->expectsOutput('Outside sending hours; nothing sent.');
        // Monday 08:00 IST: too early.
        $this->travelTo('2026-10-12 02:30:00');
        $this->artisan('driveiq:outreach');
        Mail::assertNothingSent();

        $this->travelTo('2026-10-12 05:30:00');
        $this->artisan('driveiq:outreach');
        $this->artisan('driveiq:outreach');
        Mail::assertSentCount(2);

        // The cap resets the next day (in IST).
        $this->travelTo('2026-10-13 05:30:00');
        $this->artisan('driveiq:outreach');
        Mail::assertSentCount(3);
    }

    public function test_never_writes_to_suppressed_replied_or_registered_people(): void
    {
        $suppressed = $this->prospect(['name' => 'A', 'email' => 'a@example.com']);
        $replied = $this->prospect(['name' => 'B', 'email' => 'b@example.com']);
        $registered = $this->prospect(['name' => 'C', 'email' => 'C@example.com']);
        $this->prospect(['name' => 'No email', 'email' => null]);
        $this->prospect(['name' => 'Trainer', 'email' => 't@example.com', 'type' => 'trainer']);

        app(OutreachSuppression::class)->suppress('A@Example.com', null, 'unsubscribed');
        $c = $this->campaign();
        // Suppressed, emailless and trainer prospects are never enrolled.
        $this->postJson("/api/admin/outreach/campaigns/{$c->id}/enroll", [])->assertJsonPath('count', 2);
        $this->assertFalse(OutreachEnrollment::where('prospect_id', $suppressed->id)->exists());

        $replied->update(['stage' => 'replied']);
        User::factory()->create(['email' => 'c@example.com']);
        $this->activate($c);
        $this->artisan('driveiq:outreach');

        Mail::assertNothingSent();
        $this->assertEqualsCanonicalizing(['replied', 'has_account'], OutreachEnrollment::pluck('stop_reason')->all());
    }

    public function test_one_click_unsubscribe(): void
    {
        $prospect = $this->prospect();
        app(ProspectService::class)->createListing($prospect);
        $c = $this->campaign();
        $this->postJson("/api/admin/outreach/campaigns/{$c->id}/enroll", []);
        $this->activate($c);
        $this->artisan('driveiq:outreach');

        $url = null;
        Mail::assertSent(OutreachEmail::class, function ($m) use (&$url) {
            $url = $m->unsubscribeUrl;

            return true;
        });

        // The mail provider posts to the List-Unsubscribe URL without a session.
        $this->app['auth']->forgetGuards();
        $this->postJson(parse_url($url, PHP_URL_PATH))->assertOk();
        $this->postJson('/api/outreach/unsubscribe/not-a-token')->assertOk();

        $this->assertSame('do_not_contact', $prospect->fresh()->stage);
        $this->assertNull($prospect->fresh()->school_id, 'their unclaimed listing is removed');
        $this->assertTrue(app(OutreachSuppression::class)->isSuppressed('sai@example.com'));
        $this->assertSame('unsubscribed', OutreachEnrollment::sole()->stop_reason);

        $this->travel(5)->days();
        $this->artisan('driveiq:outreach');
        Mail::assertSentCount(1);
    }

    public function test_marking_a_bounce_suppresses_the_address(): void
    {
        $this->prospect();
        $c = $this->campaign();
        $this->postJson("/api/admin/outreach/campaigns/{$c->id}/enroll", []);
        $this->activate($c);
        $this->artisan('driveiq:outreach');

        $message = OutreachMessage::sole();
        $this->assertSame('sa*@example.com', $message->to_masked);
        $this->postJson("/api/admin/outreach/messages/{$message->id}/undeliverable", ['reason' => 'bounced'])->assertOk();
        $this->assertTrue(app(OutreachSuppression::class)->isSuppressed('sai@example.com'));
        $this->assertSame('bounced', OutreachEnrollment::sole()->stop_reason);
        $this->getJson('/api/admin/outreach/messages')->assertJsonPath('data.0.status', 'bounced');
    }

    public function test_test_send_and_validation(): void
    {
        $c = $this->campaign('trainer');
        $this->postJson("/api/admin/outreach/campaigns/{$c->id}/test", ['step' => 0])->assertOk();
        Mail::assertSent(OutreachEmail::class, fn ($m) => $m->hasTo('team@driveiq.in') && str_starts_with($m->subjectLine, '[Test] Meena Deshpande'));
        $this->assertSame(0, OutreachMessage::count());

        $this->postJson('/api/admin/outreach/campaigns', ['name' => 'Too long', 'audience' => 'school', 'steps' => array_fill(0, 4, $this->steps()[0])])
            ->assertUnprocessable()->assertJsonValidationErrors('steps');

        Sanctum::actingAs(User::factory()->create(['role' => 'school']));
        $this->getJson('/api/admin/outreach/campaigns')->assertForbidden();
    }
}
