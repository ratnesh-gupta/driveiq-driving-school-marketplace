<?php

namespace Tests\Feature;

use App\Models\DrivePackage;
use App\Models\Review;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DIQ-505: GET /api/schools/compare. */
class CompareApiTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $slug, array $attrs): School
    {
        return School::create(array_merge(['name' => ucfirst($slug), 'slug' => $slug], $attrs));
    }

    public function test_compare_returns_schools_in_order_with_summaries_and_badges(): void
    {
        $a = $this->school('alpha', ['rating' => 4.8, 'review_count' => 10, 'price_from' => 6000, 'women_instructor' => true, 'verified' => true]);
        $b = $this->school('bravo', ['rating' => 4.2, 'review_count' => 40, 'price_from' => 3000]);
        $c = $this->school('charlie', ['rating' => 3.9, 'review_count' => 5, 'price_from' => 3500, 'women_instructor' => true]);

        DrivePackage::withoutGlobalScope('school')->create(['school_id' => $a->id, 'name' => 'Basic', 'price' => 6000, 'sessions' => 10, 'vehicle_type' => 'car', 'transmission' => 'manual', 'active' => true]);
        DrivePackage::withoutGlobalScope('school')->create(['school_id' => $a->id, 'name' => 'Pro', 'price' => 9000, 'sessions' => 20, 'vehicle_type' => 'car', 'transmission' => 'manual', 'active' => true]);
        foreach ([[5, 'Excellent'], [3, 'Okay']] as [$rating, $content]) {
            Review::withoutGlobalScope('school')->create(['school_id' => $a->id, 'author_name' => 'R', 'rating' => $rating, 'content' => $content, 'approved' => true]);
        }
        Review::withoutGlobalScope('school')->create(['school_id' => $a->id, 'author_name' => 'X', 'rating' => 1, 'content' => 'Hidden', 'approved' => false]);

        DB::enableQueryLog();
        $response = $this->getJson("/api/schools/compare?ids={$c->id},{$a->id},{$b->id}")->assertOk();
        $queries = count(DB::getQueryLog());

        $this->assertSame([$c->id, $a->id, $b->id], array_column($response->json('schools'), 'id'));
        $this->assertLessThanOrEqual(6, $queries);

        $alpha = collect($response->json('schools'))->firstWhere('id', $a->id);
        $this->assertSame(['count' => 2, 'minPrice' => 6000, 'maxPrice' => 9000], $alpha['packageSummary']);
        $this->assertSame(2, $alpha['reviewSummary']['count']);
        $this->assertEquals(4.0, $alpha['reviewSummary']['average']);
        $this->assertSame('Excellent', $alpha['reviewSummary']['topReview']['content']);

        $response->assertJsonPath('badges.bestRated', $a->id)
            ->assertJsonPath('badges.mostAffordable', $b->id)
            ->assertJsonPath('badges.bestValue', $b->id)
            ->assertJsonPath('badges.mostReviewed', $b->id)
            ->assertJsonPath('badges.womenFriendly', [$a->id]) // charlie is below 4.0
            ->assertJsonPath('missingIds', []);
    }

    public function test_compare_validates_ids(): void
    {
        $a = $this->school('alpha', []);

        $this->getJson('/api/schools/compare')->assertUnprocessable();
        $this->getJson('/api/schools/compare?ids=abc')->assertUnprocessable();
        $this->getJson("/api/schools/compare?ids={$a->id}")->assertUnprocessable();
        $this->getJson('/api/schools/compare?ids=1,2,3,4,5')->assertUnprocessable();

        $this->getJson("/api/schools/compare?ids={$a->id},999999")
            ->assertOk()
            ->assertJsonCount(1, 'schools')
            ->assertJsonPath('missingIds', [999999])
            ->assertJsonPath('badges.bestRated', null);
    }
}
