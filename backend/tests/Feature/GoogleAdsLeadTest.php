<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Notifications\OnboardingInvite;
use App\Services\OutreachSuppression;
use App\Services\ProspectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** DIQ-1106: Google Ads lead form webhook. */
class GoogleAdsLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['services.google_ads.webhook_key' => 'secret-key']);
    }

    private function payload(array $overrides = [], array $columns = []): array
    {
        return array_merge([
            'lead_id' => 'lead-1',
            'api_version' => '1.0',
            'form_id' => 111,
            'campaign_id' => 222,
            'adgroup_id' => 333,
            'google_key' => 'secret-key',
            'is_test' => false,
            'user_column_data' => $columns ?: [
                ['column_name' => 'Full Name', 'string_value' => 'Prakash Jadhav', 'column_id' => 'FULL_NAME'],
                ['column_name' => 'User Email', 'string_value' => 'prakash@example.com', 'column_id' => 'EMAIL'],
                ['column_name' => 'User Phone', 'string_value' => '+919822033333', 'column_id' => 'PHONE_NUMBER'],
                ['column_name' => 'City', 'string_value' => 'Pune', 'column_id' => 'CITY'],
                ['column_name' => 'Company Name', 'string_value' => 'Jadhav Driving Classes', 'column_id' => 'COMPANY_NAME'],
                ['column_name' => 'Do you run a school or train on your own?', 'string_value' => 'Driving school', 'column_id' => 'school_or_trainer'],
            ],
        ], $overrides);
    }

    public function test_a_lead_becomes_a_prospect_and_gets_an_onboarding_email(): void
    {
        $this->postJson('/api/webhooks/google-ads/lead', $this->payload())->assertOk();

        $p = Prospect::sole();
        $this->assertSame(['ads', 'school', 'Jadhav Driving Classes', 'Prakash Jadhav', '+919822033333', 'contacted'],
            [$p->source, $p->type, $p->name, $p->contact_person, $p->phone_e164, $p->stage]);
        $this->assertSame('222', $p->meta['google_ads']['campaign_id']);

        Notification::assertSentTo(new AnonymousNotifiable, OnboardingInvite::class, function (OnboardingInvite $n, $ch, $notifiable) {
            $this->assertStringContainsString('/register?type=school&utm_source=google', $n->link);

            return $notifiable->routes['mail'] === 'prakash@example.com';
        });

        // Google retries: the same lead is processed once.
        $this->postJson('/api/webhooks/google-ads/lead', $this->payload())->assertOk();
        $this->assertSame(1, Prospect::count());
        Notification::assertSentTimes(OnboardingInvite::class, 1);
    }

    public function test_bad_key_or_unconfigured_is_refused(): void
    {
        $this->postJson('/api/webhooks/google-ads/lead', $this->payload(['google_key' => 'wrong']))->assertForbidden();
        $this->postJson('/api/webhooks/google-ads/lead', $this->payload(['google_key' => null]))->assertForbidden();

        config(['services.google_ads.webhook_key' => null]);
        $this->postJson('/api/webhooks/google-ads/lead', $this->payload())->assertNotFound();
        $this->assertSame(0, Prospect::count());
    }

    public function test_test_leads_are_recorded_but_not_filed(): void
    {
        $this->postJson('/api/webhooks/google-ads/lead', $this->payload(['is_test' => true]))->assertOk();

        $this->assertSame(0, Prospect::count());
        $this->assertSame('test', DB::table('ad_leads')->value('outcome'));
        Notification::assertNothingSent();
    }

    public function test_trainer_answer_and_existing_prospect_with_listing(): void
    {
        $existing = Prospect::create(['type' => 'trainer', 'name' => 'Lata Pawar', 'phone' => '9822044444', 'email' => 'lata@example.com']);
        app(ProspectService::class)->createListing($existing);

        $this->postJson('/api/webhooks/google-ads/lead', $this->payload(['lead_id' => 'lead-2'], [
            ['string_value' => 'Lata Pawar', 'column_id' => 'FULL_NAME'],
            ['string_value' => '09822044444', 'column_id' => 'PHONE_NUMBER'],
            ['column_name' => 'School or trainer?', 'string_value' => 'Independent trainer', 'column_id' => 'q1'],
        ]))->assertOk();

        $this->assertSame(1, Prospect::count());
        $this->assertStringContainsString('Google Ads enquiry', $existing->fresh()->notes);
        // They have a listing waiting, so the email carries its claim link.
        Notification::assertSentTo(new AnonymousNotifiable, OnboardingInvite::class, fn ($n) => str_contains($n->link, '/claim/'));
    }

    public function test_an_earlier_opt_out_is_respected(): void
    {
        app(OutreachSuppression::class)->suppress('prakash@example.com', null, 'unsubscribed');
        $this->postJson('/api/webhooks/google-ads/lead', $this->payload())->assertOk();

        Notification::assertNothingSent();
        $this->assertSame('suppressed', DB::table('ad_leads')->value('outcome'));
    }
}
