<?php

namespace Tests\Feature;

use App\Models\Locality;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoSearchTest extends TestCase
{
    use RefreshDatabase;

    private function seedGeoSchools(): Locality
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-geo']);

        // ~0.5 km from origin (18.5590, 73.7868) — high rating, verified
        School::create([
            'name' => 'Close Verified Star',
            'slug' => 'close-verified-star',
            'locality_id' => $locality->id,
            'address' => 'Near',
            'phone' => '9000002001',
            'latitude' => 18.5620,
            'longitude' => 73.7868,
            'rating' => 4.9,
            'review_count' => 40,
            'verified' => true,
        ]);

        // Same area but lower rating / not verified
        School::create([
            'name' => 'Close Unverified',
            'slug' => 'close-unverified',
            'locality_id' => $locality->id,
            'address' => 'Near 2',
            'phone' => '9000002002',
            'latitude' => 18.5600,
            'longitude' => 73.7870,
            'rating' => 3.0,
            'review_count' => 2,
            'verified' => false,
        ]);

        // Outside 5km (Mumbai-ish)
        School::create([
            'name' => 'Far School',
            'slug' => 'far-school-geo',
            'locality_id' => $locality->id,
            'address' => 'Far',
            'phone' => '9000002003',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'rating' => 5.0,
            'review_count' => 100,
            'verified' => true,
        ]);

        return $locality;
    }

    public function test_radius_excludes_far_schools(): void
    {
        $this->seedGeoSchools();

        $this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=5')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_sort_by_distance_orders_closest_first(): void
    {
        $this->seedGeoSchools();

        $response = $this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=5&sortBy=distance')
            ->assertOk();

        $names = collect($response->json())->pluck('name')->all();
        $this->assertSame('Close Unverified', $names[0]);
    }

    public function test_default_rank_prefers_verified_high_rated(): void
    {
        $this->seedGeoSchools();

        $response = $this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=5&sortBy=rank')
            ->assertOk();

        $first = $response->json()[0];
        $this->assertSame('Close Verified Star', $first['name']);
        $this->assertArrayHasKey('distanceKm', $first);
        $this->assertArrayHasKey('rankingScore', $first);
        $this->assertGreaterThan(0, $first['rankingScore']);
    }

    public function test_radius_options_2_5_10_work(): void
    {
        $locality = Locality::create(['name' => 'Geo2', 'slug' => 'geo2']);

        School::create([
            'name' => 'Within2',
            'slug' => 'within-2',
            'locality_id' => $locality->id,
            'address' => 'A',
            'phone' => '9000003001',
            'latitude' => 18.5590,
            'longitude' => 73.7868,
        ]);

        $this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=2')
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=10')
            ->assertOk()
            ->assertJsonCount(1);
    }
}
