<?php

namespace Tests\Feature;

use App\Events\InquiryCreated;
use App\Listeners\SendInquiryConfirmation;
use App\Messaging\MessageSender;
use App\Models\Inquiry;
use App\Models\OutboundMessage;
use App\Models\Review;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Notifications\InquiryConfirmation;
use App\Notifications\ReviewInvitation;
use App\Services\ReviewEligibilityService;
use App\Support\TextSanitizer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeMessageSender;
use Tests\TestCase;

/** BSEC-03 / 07 / 08: public inquiry abuse and review link/report hardening. */
class InquiryAbuseTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private School $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = $this->makeSchool('abuse-a');
        $this->other = $this->makeSchool('abuse-b');
    }

    private function makeSchool(string $slug): School
    {
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create(['name' => 'School '.$slug, 'slug' => $slug, 'user_id' => $owner->id, 'phone' => '9000000001']);
        $owner->update(['school_id' => $school->id]);

        return $school;
    }

    private function enquire(array $extra = [], ?School $school = null)
    {
        return $this->postJson('/api/inquiries', $extra + [
            'schoolId' => ($school ?? $this->school)->id, 'name' => 'Asha', 'phone' => '98765 43210', 'vehicleType' => 'car',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ]);
    }

    public function test_whatsapp_opt_in_on_the_form_sends_nothing_to_that_number(): void
    {
        $fake = new FakeMessageSender;
        $this->app->instance(MessageSender::class, $fake);
        SchoolSetting::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $this->school->id],
            ['settings' => ['notifications' => ['whatsapp' => true]]]
        );

        $this->enquire(['whatsappOptIn' => true, 'email' => 'victim@example.com'])->assertCreated();

        // Intent is stored for the school, but no WhatsApp/SMS goes to the typed number.
        $this->assertNotNull(Inquiry::withoutGlobalScope('school')->latest('id')->first()->whatsapp_opt_in_at);
        $this->assertSame(0, OutboundMessage::query()->where('template', 'enquiry_confirmation')->count());
        $this->assertSame([], array_filter($fake->sent, fn ($m) => $m['to'] === '+919876543210'));
    }

    public function test_receipt_email_is_transactional_once_per_address_per_day(): void
    {
        Notification::fake();

        $this->enquire(['email' => 'Victim@Example.com', 'phone' => '9811111111'])->assertCreated();
        // Same address, different number and school: still no second mail inside a day.
        $this->enquire(['email' => 'victim@example.com', 'phone' => '9822222222'], $this->other)->assertCreated();

        Notification::assertSentTimes(InquiryConfirmation::class, 1);
        Notification::assertNotSentTo(new AnonymousNotifiable, ReviewInvitation::class);

        Carbon::setTestNow(now()->addDay()->addMinute());
        $inquiry = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Later', 'phone' => '9833333333', 'email' => 'victim@example.com', 'vehicle_type' => 'car', 'status' => 'pending',
        ]);
        app(SendInquiryConfirmation::class)->handle(new InquiryCreated($inquiry));
        Notification::assertSentTimes(InquiryConfirmation::class, 2);
    }

    public function test_receipt_emails_are_capped_per_school_per_hour(): void
    {
        Notification::fake();

        for ($i = 0; $i < SendInquiryConfirmation::MAX_PER_SCHOOL_HOUR + 5; $i++) {
            RateLimiter::clear('inquiries'); // per-IP throttle is not what is under test
            $inquiry = Inquiry::withoutGlobalScope('school')->create([
                'school_id' => $this->school->id, 'name' => 'N'.$i, 'phone' => '98000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'email' => "capped{$i}@example.com", 'vehicle_type' => 'car', 'status' => 'pending',
            ]);
            app(SendInquiryConfirmation::class)->handle(new InquiryCreated($inquiry));
        }
        Notification::assertSentTimes(InquiryConfirmation::class, SendInquiryConfirmation::MAX_PER_SCHOOL_HOUR);

        // Another school has its own budget.
        $inquiry = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $this->other->id, 'name' => 'Z', 'phone' => '9899999999', 'email' => 'other@example.com', 'vehicle_type' => 'car', 'status' => 'pending',
        ]);
        app(SendInquiryConfirmation::class)->handle(new InquiryCreated($inquiry));
        Notification::assertSentTimes(InquiryConfirmation::class, SendInquiryConfirmation::MAX_PER_SCHOOL_HOUR + 1);
    }

    public function test_same_number_is_throttled_across_ips_and_duplicates_do_not_alert_again(): void
    {
        Notification::fake();
        $this->enquire(['phone' => '9844444444', 'email' => 'dup@example.com'])->assertCreated();
        $this->enquire(['phone' => '+91 98444 44444', 'email' => 'dup2@example.com'])->assertCreated();

        // Both leads are stored (staff are alerted for each) but only the first got a receipt.
        $this->assertSame(2, Inquiry::withoutGlobalScope('school')->where('school_id', $this->school->id)->count());
        Notification::assertSentTimes(InquiryConfirmation::class, 1);

        // Per-number daily cap, regardless of how many different IPs are used.
        $codes = [];
        for ($i = 0; $i < 6; $i++) {
            $codes[] = $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->enquire(['phone' => '9844444444', 'email' => "x{$i}@example.com"], $this->other)->status();
        }
        $this->assertContains(429, $codes);
    }

    public function test_visitor_text_is_stripped_of_links_before_it_reaches_templates(): void
    {
        $this->enquire(['name' => "Win iPhone http://evil.example/claim www.evil.io\x07 now", 'area' => 'Baner bit.ly/abc'])->assertCreated();
        $lead = Inquiry::withoutGlobalScope('school')->latest('id')->first();
        $this->assertSame('Win iPhone now', $lead->name);
        $this->assertSame('Baner', $lead->area);

        $this->assertSame('a b', TextSanitizer::plain("a\n\n  b"));
        $this->enquire(['phone' => 'call me http://x.y'])->assertUnprocessable();
        $this->enquire(['name' => 'https://only.link/x'])->assertUnprocessable();
        $this->enquire(['message' => str_repeat('x', 5000)])->assertCreated();
        $this->assertSame(2000, mb_strlen(Inquiry::withoutGlobalScope('school')->latest('id')->first()->message));
    }

    public function test_unpublished_school_takes_no_inquiries(): void
    {
        $this->other->forceFill(['listing_status' => 'suspended'])->save();
        $this->enquire([], $this->other)->assertUnprocessable();
    }

    public function test_review_link_waits_for_the_school_and_is_issued_once_per_email_and_school(): void
    {
        Notification::fake();
        $this->enquire(['email' => 'real@example.com'])->assertCreated();
        $first = Inquiry::withoutGlobalScope('school')->latest('id')->firstOrFail();
        $this->assertNull($first->review_token_hash);
        Notification::assertNotSentTo(new AnonymousNotifiable, ReviewInvitation::class);

        $first->markResponded();
        Notification::assertSentTimes(ReviewInvitation::class, 1);
        $this->assertNotNull($first->fresh()->review_invited_at);

        // Second enquiry by the same address to the same school never gets another link.
        $second = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Again', 'phone' => '9855555555', 'email' => 'REAL@example.com', 'vehicle_type' => 'car', 'status' => 'pending',
        ]);
        $second->markResponded();
        Notification::assertSentTimes(ReviewInvitation::class, 1);
        $this->assertNull($second->fresh()->review_token_hash);

        // A different school is a different relationship.
        $third = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $this->other->id, 'name' => 'Elsewhere', 'phone' => '9866666666', 'email' => 'real@example.com', 'vehicle_type' => 'car', 'status' => 'pending',
        ]);
        $third->markResponded();
        Notification::assertSentTimes(ReviewInvitation::class, 2);
    }

    public function test_review_link_endpoints_reject_malformed_tokens_and_never_cache(): void
    {
        $this->getJson('/api/reviews/via-inquiry/short')->assertNotFound()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->getJson('/api/reviews/via-inquiry/'.str_repeat('a', 48))
            ->assertNotFound()
            ->assertJsonPath('message', 'This review link is invalid, expired or already used.');
        $this->postJson('/api/reviews/via-inquiry', ['token' => str_repeat('a', 200), 'authorName' => 'X', 'rating' => 5, 'content' => 'x'])
            ->assertUnprocessable();
        $this->postJson('/api/reviews/via-inquiry', ['token' => '../../etc', 'authorName' => 'X', 'rating' => 5, 'content' => 'x'])
            ->assertNotFound();

        // Valid token: response is not cacheable and author text is sanitised.
        $inquiry = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Asha', 'phone' => '9877777777', 'email' => 'tok@example.com', 'vehicle_type' => 'car', 'status' => 'pending',
        ]);
        $token = app(ReviewEligibilityService::class)->issueInquiryToken($inquiry);
        $this->getJson('/api/reviews/via-inquiry/'.$token)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/reviews/via-inquiry', ['token' => $token, 'authorName' => 'Bob http://spam.example', 'rating' => 5, 'content' => 'Fine'])
            ->assertCreated()->assertJsonPath('approved', false);
        $this->assertSame('Bob', Review::withoutGlobalScope('school')->latest('id')->first()->author_name);
    }

    private function approvedReview(?School $school = null): Review
    {
        return Review::withoutGlobalScope('school')->create([
            'school_id' => ($school ?? $this->school)->id, 'author_name' => 'R', 'rating' => 5, 'content' => 'Nice', 'approved' => true,
        ]);
    }

    public function test_new_or_same_school_reporters_never_auto_hide_a_review(): void
    {
        $review = $this->approvedReview();

        // Three brand-new throwaway accounts: recorded, not enough to hide.
        foreach (range(1, 3) as $i) {
            Sanctum::actingAs(User::factory()->create(['role' => 'learner']));
            $this->postJson("/api/reviews/{$review->id}/report", ['reason' => 'fake'])->assertCreated();
        }
        $review->refresh();
        $this->assertTrue((bool) $review->approved);
        $this->assertSame(3, (int) $review->report_count);

        // The school's own staff: reported, but never counted as credible.
        $staff = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id]);
        $staff->forceFill(['created_at' => now()->subYear()])->save();
        Sanctum::actingAs($staff);
        $this->postJson("/api/reviews/{$review->id}/report", ['reason' => 'fake'])->assertCreated();
        $this->assertTrue((bool) $review->fresh()->approved);
    }

    public function test_three_established_independent_reporters_hide_the_review_and_it_is_audited(): void
    {
        $review = $this->approvedReview();
        foreach (range(1, 3) as $i) {
            $u = User::factory()->create(['role' => 'learner']);
            $u->forceFill(['created_at' => now()->subDays(30)])->save();
            Sanctum::actingAs($u);
            $this->postJson("/api/reviews/{$review->id}/report", ['reason' => 'abuse'])->assertCreated();
        }
        $this->assertFalse((bool) $review->fresh()->approved);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report', 'model_type' => 'Review', 'model_id' => $review->id, 'school_id' => $this->school->id]);
    }

    public function test_unapproved_reviews_cannot_be_probed_by_reporters_from_other_schools(): void
    {
        $pending = Review::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'author_name' => 'R', 'rating' => 1, 'content' => 'Pending', 'approved' => false,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'learner']));
        $this->postJson("/api/reviews/{$pending->id}/report", ['reason' => 'x'])->assertNotFound();
        $this->postJson('/api/reviews/999999/report', ['reason' => 'x'])->assertNotFound();

        $otherOwner = User::factory()->create(['role' => 'school', 'school_id' => $this->other->id]);
        Sanctum::actingAs($otherOwner);
        $this->postJson("/api/reviews/{$pending->id}/report", ['reason' => 'x'])->assertNotFound();

        // The reviewed school itself can still flag it (admin queue).
        Sanctum::actingAs(User::find($this->school->user_id));
        $this->postJson("/api/reviews/{$pending->id}/report", ['reason' => 'x'])->assertCreated();
        $this->postJson("/api/reviews/{$pending->id}/report", ['reason' => 'x'])->assertUnprocessable();
    }

    public function test_reporters_have_a_daily_cap(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($user);
        $codes = [];
        for ($i = 0; $i < 22; $i++) {
            $review = $this->approvedReview();
            $codes[] = $this->postJson("/api/reviews/{$review->id}/report", ['reason' => 'x'])->status();
            RateLimiter::clear('throttle');
        }
        $this->assertContains(429, $codes);
    }

    public function test_report_requires_authentication(): void
    {
        $review = $this->approvedReview();
        $this->postJson("/api/reviews/{$review->id}/report", ['reason' => 'x'])->assertUnauthorized();
    }
}
