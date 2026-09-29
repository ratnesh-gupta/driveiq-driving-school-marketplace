<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Learner;
use App\Models\Locality;
use App\Models\Review;
use App\Models\School;
use App\Models\User;
use App\Notifications\InquiryConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrustAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private function schoolWithLocality(): School
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-trust']);

        return School::create([
            'name' => 'Trust School',
            'slug' => 'trust-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000004000',
            'verified' => true,
            'phone_verified' => true,
            'business_verified' => true,
        ]);
    }

    /** A learner account enrolled at the school (DIQ-407 path A). */
    private function enrolledLearner(School $school, string $email): User
    {
        $user = User::factory()->create(['role' => 'learner', 'email' => $email, 'school_id' => $school->id]);
        Learner::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'name' => 'Learner',
            'status' => 'active',
        ]);

        return $user;
    }

    private function reviewPayload(School $school, array $overrides = []): array
    {
        return array_merge([
            'schoolId' => $school->id,
            'authorName' => 'Learner',
            'rating' => 5,
            'content' => 'Great',
        ], $overrides);
    }

    public function test_account_that_is_not_enrolled_cannot_review(): void
    {
        $school = $this->schoolWithLocality();
        $user = User::factory()->create(['role' => 'learner', 'email' => 'learner@example.com']);

        // Even with a matching enquiry: that path is the emailed one-time link now.
        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id, 'name' => 'L', 'phone' => '9888888888',
            'email' => 'learner@example.com', 'vehicle_type' => 'car', 'status' => 'pending',
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/reviews', $this->reviewPayload($school))->assertForbidden();

        // School staff cannot review either (PBAC: create = learner).
        Sanctum::actingAs(User::factory()->create(['role' => 'school', 'school_id' => $school->id]));
        $this->postJson('/api/reviews', $this->reviewPayload($school))->assertForbidden();
    }

    public function test_enrolled_learner_can_review_once(): void
    {
        $school = $this->schoolWithLocality();
        Sanctum::actingAs($this->enrolledLearner($school, 'learner2@example.com'));

        $this->postJson('/api/reviews', $this->reviewPayload($school, ['content' => 'Great trainer']))
            ->assertCreated()
            ->assertJsonPath('approved', false)
            ->assertJsonPath('eligibilitySource', 'learner');

        $this->postJson('/api/reviews', $this->reviewPayload($school, ['content' => 'Second']))
            ->assertForbidden();
    }

    public function test_learner_of_another_school_cannot_review(): void
    {
        $school = $this->schoolWithLocality();
        $other = School::create(['name' => 'Other', 'slug' => 'other-trust']);
        Sanctum::actingAs($this->enrolledLearner($other, 'elsewhere@example.com'));

        $this->postJson('/api/reviews', $this->reviewPayload($school))->assertForbidden();
    }

    public function test_inquirer_reviews_once_via_emailed_link(): void
    {
        Notification::fake();
        $school = $this->schoolWithLocality();

        $this->postJson('/api/inquiries', [
            'schoolId' => $school->id,
            'name' => 'Enquirer',
            'phone' => '9777777777',
            'email' => 'enquirer@example.com',
            'vehicleType' => 'car',
        ])->assertCreated();

        $url = null;
        Notification::assertSentTo(new AnonymousNotifiable, InquiryConfirmation::class, function (InquiryConfirmation $n) use (&$url) {
            $url = $n->reviewUrl;

            return true;
        });
        $this->assertStringStartsWith(config('app.frontend_url').'/review?token=', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $token = $query['token'];

        $this->getJson('/api/reviews/via-inquiry/'.$token)
            ->assertOk()
            ->assertJsonPath('schoolName', 'Trust School')
            ->assertJsonPath('authorName', 'Enquirer');

        $this->postJson('/api/reviews/via-inquiry', [
            'token' => $token, 'authorName' => 'Enquirer', 'rating' => 4, 'content' => 'Helpful on the phone',
        ])->assertCreated()
            ->assertJsonPath('approved', false)
            ->assertJsonPath('eligibilitySource', 'inquiry');

        // Single use.
        $this->postJson('/api/reviews/via-inquiry', [
            'token' => $token, 'authorName' => 'Again', 'rating' => 1, 'content' => 'Second try',
        ])->assertNotFound();
        $this->getJson('/api/reviews/via-inquiry/'.$token)->assertNotFound();
    }

    public function test_inquiry_without_email_gets_no_link_and_bad_tokens_fail(): void
    {
        Notification::fake();
        $school = $this->schoolWithLocality();

        $this->postJson('/api/inquiries', [
            'schoolId' => $school->id, 'name' => 'No Email', 'phone' => '9777777700', 'vehicleType' => 'car',
        ])->assertCreated();

        Notification::assertNothingSent();
        $this->postJson('/api/reviews/via-inquiry', [
            'token' => 'made-up', 'authorName' => 'X', 'rating' => 5, 'content' => 'Fake',
        ])->assertNotFound();
    }

    public function test_report_review_and_list_for_admin(): void
    {
        $school = $this->schoolWithLocality();
        $reviewer = $this->enrolledLearner($school, 'rev@example.com');
        $reporter = User::factory()->create(['role' => 'learner']);
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($reviewer);
        $created = $this->postJson('/api/reviews', $this->reviewPayload($school, [
            'authorName' => 'Rev', 'rating' => 2, 'content' => 'Spammy content',
        ]))->assertCreated();

        $reviewId = $created->json('id');

        // Approve so public can see, then report
        Review::withoutGlobalScope('school')->where('id', $reviewId)->update(['approved' => true]);

        Sanctum::actingAs($reporter);
        $this->postJson('/api/reviews/'.$reviewId.'/report', [
            'reason' => 'spam',
            'details' => 'Looks fake',
        ])->assertCreated();

        Sanctum::actingAs($admin);
        $this->getJson('/api/review-reports?status=pending')
            ->assertOk()
            ->assertJsonFragment(['reason' => 'spam']);
    }

    public function test_verified_filter_on_schools(): void
    {
        $locality = Locality::create(['name' => 'V', 'slug' => 'v-filter']);

        School::create([
            'name' => 'Verified One',
            'slug' => 'verified-one',
            'locality_id' => $locality->id,
            'address' => 'A',
            'phone' => '9000005001',
            'verified' => true,
        ]);

        School::create([
            'name' => 'Unverified',
            'slug' => 'unverified-one',
            'locality_id' => $locality->id,
            'address' => 'B',
            'phone' => '9000005002',
            'verified' => false,
        ]);

        $this->getJson('/api/schools?verified=true')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Verified One']);
    }

    public function test_public_reviews_hide_unapproved(): void
    {
        $school = $this->schoolWithLocality();

        Review::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'author_name' => 'Pending',
            'rating' => 5,
            'content' => 'Pending review',
            'approved' => false,
        ]);

        Review::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'author_name' => 'Live',
            'rating' => 4,
            'content' => 'Approved',
            'approved' => true,
        ]);

        $this->getJson('/api/reviews?schoolId='.$school->id)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['authorName' => 'Live']);
    }
}
