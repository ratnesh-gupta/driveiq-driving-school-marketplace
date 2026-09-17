<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Locality;
use App\Models\Review;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_review_without_inquiry_is_forbidden(): void
    {
        $school = $this->schoolWithLocality();
        $user = User::factory()->create(['role' => 'user', 'email' => 'learner@example.com']);

        Sanctum::actingAs($user);

        $this->postJson('/api/reviews', [
            'schoolId' => $school->id,
            'authorName' => 'Learner',
            'rating' => 5,
            'content' => 'Great',
        ])->assertForbidden();
    }

    public function test_review_allowed_after_inquiry_with_matching_email(): void
    {
        $school = $this->schoolWithLocality();
        $user = User::factory()->create(['role' => 'user', 'email' => 'learner2@example.com']);

        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Learner',
            'phone' => '9888888888',
            'email' => 'learner2@example.com',
            'vehicle_type' => 'car',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/reviews', [
            'schoolId' => $school->id,
            'authorName' => 'Learner',
            'rating' => 5,
            'content' => 'Great experience after enquiry',
        ])
            ->assertCreated()
            ->assertJsonPath('approved', false)
            ->assertJsonPath('eligibilitySource', 'inquiry');
    }

    public function test_duplicate_review_blocked(): void
    {
        $school = $this->schoolWithLocality();
        $user = User::factory()->create(['role' => 'user', 'email' => 'dup@example.com']);

        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Dup',
            'phone' => '9777777777',
            'email' => 'dup@example.com',
            'vehicle_type' => 'car',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/reviews', [
            'schoolId' => $school->id,
            'authorName' => 'Dup',
            'rating' => 4,
            'content' => 'First',
        ])->assertCreated();

        $this->postJson('/api/reviews', [
            'schoolId' => $school->id,
            'authorName' => 'Dup',
            'rating' => 3,
            'content' => 'Second',
        ])->assertForbidden();
    }

    public function test_report_review_and_list_for_admin(): void
    {
        $school = $this->schoolWithLocality();
        $reviewer = User::factory()->create(['role' => 'user', 'email' => 'rev@example.com']);
        $reporter = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Rev',
            'phone' => '9666666666',
            'email' => 'rev@example.com',
            'vehicle_type' => 'car',
        ]);

        Sanctum::actingAs($reviewer);
        $created = $this->postJson('/api/reviews', [
            'schoolId' => $school->id,
            'authorName' => 'Rev',
            'rating' => 2,
            'content' => 'Spammy content',
        ])->assertCreated();

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
