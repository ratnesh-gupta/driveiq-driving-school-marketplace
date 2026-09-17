<?php

namespace Tests\Feature;

use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionMonetizationTest extends TestCase
{
    use RefreshDatabase;

    private function school(): School
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-p4']);

        return School::create([
            'name' => 'Paid School',
            'slug' => 'paid-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000007000',
            'verified' => true,
            'rating' => 4.0,
            'review_count' => 5,
            'latitude' => 18.5590,
            'longitude' => 73.7868,
        ]);
    }

    public function test_plans_catalog_is_public(): void
    {
        $this->getJson('/api/plans')
            ->assertOk()
            ->assertJsonFragment(['code' => 'basic'])
            ->assertJsonFragment(['code' => 'premium']);
    }

    public function test_admin_can_assign_subscription(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $school = $this->school();

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/subscriptions', [
            'schoolId' => $school->id,
            'planCode' => 'premium',
            'months' => 1,
        ])->assertCreated()
            ->assertJsonPath('planCode', 'premium')
            ->assertJsonPath('isSponsored', true);

        $this->getJson('/api/admin/subscriptions/overview')
            ->assertOk()
            ->assertJsonPath('activeSubscriptions', 1);
    }

    public function test_school_can_view_own_subscription_only(): void
    {
        $school = $this->school();
        $owner = User::factory()->create(['role' => 'school', 'school_id' => $school->id]);
        $other = User::factory()->create(['role' => 'school', 'school_id' => 999]);

        app(SubscriptionService::class)->assign($school->id, 'featured', 1);

        Sanctum::actingAs($owner);
        $this->getJson('/api/schools/'.$school->id.'/subscription')
            ->assertOk()
            ->assertJsonPath('planCode', 'featured');

        Sanctum::actingAs($other);
        $this->getJson('/api/schools/'.$school->id.'/subscription')
            ->assertForbidden();
    }

    public function test_premium_boosts_geo_rank_over_basic(): void
    {
        $locality = Locality::create(['name' => 'G', 'slug' => 'g-p4']);

        $basic = School::create([
            'name' => 'Basic Near',
            'slug' => 'basic-near',
            'locality_id' => $locality->id,
            'address' => 'A',
            'phone' => '9000007001',
            'latitude' => 18.5590,
            'longitude' => 73.7868,
            'rating' => 4.5,
            'review_count' => 20,
            'verified' => true,
        ]);

        $premium = School::create([
            'name' => 'Premium Near',
            'slug' => 'premium-near',
            'locality_id' => $locality->id,
            'address' => 'B',
            'phone' => '9000007002',
            'latitude' => 18.5600,
            'longitude' => 73.7870,
            'rating' => 4.0,
            'review_count' => 5,
            'verified' => true,
        ]);

        app(SubscriptionService::class)->assign($premium->id, 'premium', 1);

        $response = $this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=5&sortBy=rank')
            ->assertOk();

        $names = collect($response->json())->pluck('name')->all();
        $this->assertSame('Premium Near', $names[0]);
        $this->assertTrue(collect($response->json())->firstWhere('name', 'Premium Near')['isSponsored']);
    }
}
