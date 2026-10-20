<?php

namespace Tests\Feature;

use App\Models\FeaturedPlacement;
use App\Models\Locality;
use App\Models\MarketplaceSetting;
use App\Models\School;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DIQ-805: labelled paid placement, capped sponsored slots and campaign windows. */
class ListingTiersTest extends TestCase
{
    use RefreshDatabase;

    private Locality $baner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baner = Locality::create(['name' => 'Baner', 'slug' => 'baner-tiers']);
    }

    private function school(string $name, float $rating, bool $verified = false, ?string $plan = null, ?Locality $locality = null): School
    {
        $school = School::create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'locality_id' => ($locality ?? $this->baner)->id,
            'rating' => $rating,
            'review_count' => 10,
            'verified' => $verified,
            'latitude' => 18.5590,
            'longitude' => 73.7868,
        ]);
        if ($plan) {
            app(SubscriptionService::class)->assign($school->id, $plan, 1);
        }

        return $school;
    }

    private function results(string $query = ''): array
    {
        return $this->getJson('/api/schools?limit=20'.$query)->assertOk()->json();
    }

    public function test_only_the_slot_cap_is_pinned_and_paid_placement_is_labelled(): void
    {
        $this->school('Premium A', 4.8, true, 'premium');
        $this->school('Premium B', 4.5, true, 'premium');
        $this->school('Premium C', 1.0, false, 'premium');
        $this->school('Top Basic', 5.0, true);
        $this->school('Featured One', 3.0, false, 'featured');

        $rows = $this->results();
        $names = array_column($rows, 'name');

        $this->assertSame(['Premium A', 'Premium B'], array_slice($names, 0, 2));
        $this->assertTrue($rows[0]['isPinned'] && $rows[1]['isPinned']);
        // Beyond the cap a premium school competes on score (its boost included).
        $this->assertLessThan(array_search('Premium C', $names), array_search('Top Basic', $names));

        $byName = collect($rows)->keyBy('name');
        $this->assertTrue($byName['Premium C']['isSponsored']);
        $this->assertFalse($byName['Premium C']['isPinned']);
        $this->assertTrue($byName['Featured One']['isFeatured']);
        $this->assertFalse($byName['Featured One']['isSponsored']);
        $this->assertSame('featured', $byName['Featured One']['listingTier']);
        $this->assertFalse($byName['Top Basic']['isSponsored']);
    }

    public function test_slot_count_comes_from_marketplace_settings(): void
    {
        $this->school('Premium A', 4.8, true, 'premium');
        $this->school('Premium B', 4.5, true, 'premium');
        MarketplaceSetting::set('sponsored_slots_per_page', 1);

        $rows = $this->results();
        $this->assertSame([true, false], array_column($rows, 'isPinned'));
    }

    public function test_campaign_window_pins_a_basic_school_only_while_live(): void
    {
        $this->school('Premium A', 4.8, true, 'premium');
        $campaign = $this->school('Campaign Basic', 2.0);
        $placement = FeaturedPlacement::create([
            'school_id' => $campaign->id, 'placement' => 'search_top',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        ]);

        $row = collect($this->results())->firstWhere('name', 'Campaign Basic');
        $this->assertTrue($row['isPinned']);
        $this->assertTrue($row['isSponsored']);

        $placement->update(['ends_at' => now()->subMinute()]);
        $row = collect($this->results())->firstWhere('name', 'Campaign Basic');
        $this->assertFalse($row['isPinned']);
        $this->assertFalse($row['isSponsored']);
    }

    public function test_locality_campaign_applies_only_in_that_locality(): void
    {
        $wakad = Locality::create(['name' => 'Wakad', 'slug' => 'wakad-tiers']);
        $local = $this->school('Wakad Local', 2.0, false, null, $wakad);
        FeaturedPlacement::create([
            'school_id' => $local->id, 'placement' => 'locality', 'locality_id' => $wakad->id,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        ]);

        $this->assertTrue(collect($this->results('&locality=wakad-tiers'))->firstWhere('name', 'Wakad Local')['isPinned']);
        $this->assertFalse(collect($this->results())->firstWhere('name', 'Wakad Local')['isPinned']);
    }

    public function test_distance_sort_is_not_pinned_and_trials_are_never_sponsored(): void
    {
        $this->school('Premium A', 4.8, true, 'premium');
        $this->school('Trial School', 5.0, true); // on its feature trial

        $rows = $this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=5&sortBy=distance')->assertOk()->json();
        $this->assertArrayNotHasKey('isPinned', $rows[0]);

        $trial = collect($this->results())->firstWhere('name', 'Trial School');
        $this->assertFalse($trial['isSponsored']);
        $this->assertSame('basic', $trial['listingTier']);
    }

    public function test_homepage_campaign_leads_the_featured_list(): void
    {
        $this->school('Verified Star', 5.0, true);
        $campaign = $this->school('Homepage Campaign', 3.0);
        FeaturedPlacement::create([
            'school_id' => $campaign->id, 'placement' => 'homepage',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        ]);

        $names = array_column($this->getJson('/api/schools/featured')->assertOk()->json(), 'name');
        $this->assertSame(['Homepage Campaign', 'Verified Star'], $names);
    }
}
