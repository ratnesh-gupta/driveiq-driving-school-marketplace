<?php

namespace Tests\Feature;

use App\Mail\OutreachEmail;
use App\Models\Locality;
use App\Models\Prospect;
use App\Models\School;
use App\Models\User;
use App\Notifications\VerifyOwnerEmail;
use App\Services\OutreachService;
use App\Services\ProspectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1203/1204: branded, localized outreach and transactional emails. */
class OutreachPresetsTest extends TestCase
{
    use RefreshDatabase;

    private Locality $baner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baner = Locality::create(['name' => 'Baner', 'slug' => 'baner']);
    }

    private function prospect(string $type = 'school'): Prospect
    {
        return Prospect::create(['type' => $type, 'name' => 'Sai Motor School', 'contact_person' => 'Sunil',
            'email' => 'sai@example.com', 'locality_id' => $this->baner->id]);
    }

    public function test_every_preset_renders_fully_in_every_language(): void
    {
        School::create(['name' => 'Live One', 'slug' => 'live-one', 'locality_id' => $this->baner->id]);
        School::create(['name' => 'Live Two', 'slug' => 'live-two', 'locality_id' => $this->baner->id]);
        $service = app(OutreachService::class);

        foreach (config('outreach_presets') as $audience => $languages) {
            $prospect = $this->prospect($audience);
            foreach ($languages as $lang => $steps) {
                $this->assertCount(3, $steps, "$audience/$lang");
                foreach ($steps as $step) {
                    [$subject, $body] = $service->render($step, $prospect, 'https://x.test/claim/abc', $lang);
                    $this->assertStringNotContainsString('{{', $subject.$body, "$audience/$lang");
                    $this->assertStringContainsString('https://x.test/claim/abc', $body);
                }
                [, $first] = $service->render($steps[0], $prospect, 'https://x.test/claim/abc', $lang);
                $this->assertStringContainsString('2', $first, "real nearby count in $audience/$lang");
            }
        }
    }

    public function test_no_nearby_sentence_when_nobody_is_live(): void
    {
        [, $body] = app(OutreachService::class)->render(config('outreach_presets.school.en.0'), $this->prospect(), 'https://x.test/l');
        $this->assertStringNotContainsString('already on DriveQ', $body);
        $this->assertStringNotContainsString(" \n", $body, 'no dangling space where the sentence was');
    }

    public function test_campaign_language_drives_the_email(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-07 05:30:00');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $presets = $this->getJson('/api/admin/outreach')->assertOk()->json('presets');
        $this->assertCount(3, $presets['trainer']['mr']);

        $id = $this->postJson('/api/admin/outreach/campaigns', [
            'name' => 'Marathi', 'audience' => 'school', 'language' => 'mr', 'steps' => $presets['school']['mr'],
        ])->assertCreated()->assertJsonPath('language', 'mr')->json('id');

        $prospect = $this->prospect();
        app(ProspectService::class)->createListing($prospect);
        $this->postJson("/api/admin/outreach/campaigns/{$id}/enroll", [])->assertJsonPath('count', 1);
        $this->patchJson("/api/admin/outreach/campaigns/{$id}", ['status' => 'active'])->assertOk();
        $this->artisan('driveiq:outreach');

        Mail::assertSent(OutreachEmail::class, function (OutreachEmail $m) {
            $html = $m->htmlBody();
            $this->assertSame('mr', $m->lang);
            $this->assertStringContainsString('नमस्कार Sunil', $m->body);
            $this->assertStringContainsString('माझी मोफत लिस्टिंग सुरू करा', $html);
            $this->assertStringContainsString('Sai Motor School', $html);
            $this->assertStringContainsString('/claim/', $m->card['link']);
            $this->assertStringContainsString('अनसबस्क्राइब', $m->plainText());
            $this->assertSame('List-Unsubscribe=One-Click', $m->headers()->text['List-Unsubscribe-Post']);

            return true;
        });

        $this->postJson('/api/admin/outreach/campaigns', ['name' => 'X', 'audience' => 'school', 'language' => 'fr', 'steps' => $presets['school']['en']])
            ->assertUnprocessable()->assertJsonValidationErrors('language');
    }

    public function test_transactional_mail_carries_the_brand(): void
    {
        $user = User::factory()->create(['role' => 'school']);
        $html = (string) (new VerifyOwnerEmail)->toMail($user)->render();

        $this->assertStringContainsString('Drive<span', $html);
        $this->assertStringContainsString('#2563eb', $html);
        $this->assertStringNotContainsString('laravel.com/img', $html);
    }
}
