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

class ApiPhaseTwoModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_and_localities_endpoints_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->getJson('/api/healthz')
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/localities', [
            'name' => 'Baner',
            'slug' => 'baner',
            'description' => 'West Pune',
        ])->assertCreated();

        $id = $created->json('id');

        $this->getJson('/api/localities')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/localities/'.$id)->assertOk()->assertJsonPath('slug', 'baner');
        $this->getJson('/api/localities/slug/baner')->assertOk()->assertJsonPath('name', 'Baner');
    }

    public function test_schools_packages_reviews_and_inquiries_contracts_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $schoolUser = User::factory()->create(['role' => 'school']);

        $locality = Locality::create([
            'name' => 'Wakad',
            'slug' => 'wakad',
        ]);

        Sanctum::actingAs($admin);
        $schoolResp = $this->postJson('/api/schools', [
            'name' => 'Skyline Driving Academy',
            'slug' => 'skyline-driving-academy',
            'localityId' => $locality->id,
            'address' => 'Wakad Road',
            'phone' => '9999999999',
            'vehicleTypes' => ['car'],
            'transmission' => ['manual'],
            'priceFrom' => 4000,
            'priceTo' => 7000,
            'hasPickup' => true,
        ])->assertCreated();

        $schoolId = $schoolResp->json('id') ?? School::query()->value('id');

        // Bind school ownership for isolation-aware dashboard actions.
        School::where('id', $schoolId)->update(['user_id' => $schoolUser->id]);
        $schoolUser->update(['school_id' => $schoolId]);
        $schoolUser->refresh();

        $this->getJson('/api/schools?locality=wakad&vehicleType=car')
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson('/api/schools/slug/skyline-driving-academy')
            ->assertOk();

        Sanctum::actingAs($schoolUser);
        $package = $this->postJson('/api/packages', [
            'schoolId' => $schoolId,
            'name' => 'Beginner Car',
            'price' => 5500,
            'sessions' => 12,
            'vehicleType' => 'car',
            'transmission' => 'manual',
        ])->assertCreated();

        $packageId = $package->json('id') ?? \App\Models\DrivePackage::withoutGlobalScope('school')->value('id');
        $this->patchJson('/api/packages/'.$packageId, ['active' => false])->assertOk()->assertJsonPath('active', false);

        Sanctum::actingAs($schoolUser);
        $review = $this->postJson('/api/reviews', [
            'schoolId' => $schoolId,
            'authorName' => 'A User',
            'rating' => 5,
            'content' => 'Great experience',
        ])->assertCreated();

        $reviewId = $review->json('id') ?? Review::withoutGlobalScope('school')->value('id');
        $this->getJson('/api/reviews?schoolId='.$schoolId)->assertOk()->assertJsonCount(1);
        $this->patchJson('/api/reviews/'.$reviewId, ['approved' => true])->assertOk()->assertJsonPath('approved', true);

        $inquiry = $this->postJson('/api/inquiries', [
            'schoolId' => $schoolId,
            'name' => 'Lead One',
            'phone' => '9888888888',
            'vehicleType' => 'car',
            'status' => 'pending',
        ])->assertCreated();

        $inquiryId = $inquiry->json('id') ?? Inquiry::withoutGlobalScope('school')->value('id');
        $this->getJson('/api/inquiries?schoolId='.$schoolId)->assertOk()->assertJsonCount(1);
        $this->patchJson('/api/inquiries/'.$inquiryId, ['status' => 'contacted'])->assertOk()->assertJsonPath('status', 'contacted');

        $this->deleteJson('/api/reviews/'.$reviewId)->assertNoContent();
        $this->deleteJson('/api/packages/'.$packageId)->assertNoContent();
    }

    public function test_review_submission_requires_authentication(): void
    {
        $locality = Locality::create([
            'name' => 'Wakad',
            'slug' => 'wakad',
        ]);

        $school = School::create([
            'name' => 'Unauth School',
            'slug' => 'unauth-school',
            'locality_id' => $locality->id,
            'address' => 'Wakad Road',
            'phone' => '9999999998',
        ]);

        $this->postJson('/api/reviews', [
            'schoolId' => $school->id,
            'authorName' => 'A User',
            'rating' => 5,
            'content' => 'Great experience',
        ])->assertUnauthorized();
    }

    public function test_stats_endpoints_work(): void
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner']);
        $school = School::create([
            'name' => 'RoadStar',
            'slug' => 'roadstar',
            'locality_id' => $locality->id,
            'address' => 'Baner Main',
            'phone' => '9000000000',
            'verified' => true,
            'rating' => 4.5,
            'review_count' => 1,
        ]);

        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Lead',
            'phone' => '9000000001',
            'vehicle_type' => 'car',
            'status' => 'pending',
        ]);

        Review::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'author_name' => 'User',
            'rating' => 5,
            'content' => 'Nice',
            'approved' => true,
        ]);

        $this->getJson('/api/stats/overview')
            ->assertOk()
            ->assertJsonPath('totalSchools', 1)
            ->assertJsonPath('totalLocalities', 1)
            ->assertJsonPath('verifiedSchools', 1);

        $this->getJson('/api/stats/school/'.$school->id)
            ->assertOk()
            ->assertJsonPath('schoolId', $school->id)
            ->assertJsonPath('totalInquiries', 1)
            ->assertJsonPath('pendingInquiries', 1)
            ->assertJsonPath('totalReviews', 1);
    }

    public function test_geo_radius_filter_returns_nearby_schools(): void
    {
        $locality = Locality::create(['name' => 'Geo', 'slug' => 'geo']);

        School::create([
            'name' => 'Near School',
            'slug' => 'near-school',
            'locality_id' => $locality->id,
            'address' => 'Near',
            'phone' => '9000001000',
            'latitude' => 18.5590,
            'longitude' => 73.7868,
            'service_radius_km' => 10,
        ]);

        School::create([
            'name' => 'Far School',
            'slug' => 'far-school',
            'locality_id' => $locality->id,
            'address' => 'Far',
            'phone' => '9000001001',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'service_radius_km' => 10,
        ]);

        $this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=10')
            ->assertOk()
            ->assertJsonCount(1);
    }
}
