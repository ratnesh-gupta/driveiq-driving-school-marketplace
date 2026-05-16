<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Locality;
use App\Models\Review;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPhaseTwoModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_and_localities_endpoints_work(): void
    {
        $this->getJson('/api/healthz')
            ->assertOk()
            ->assertJsonPath('status', 'ok');

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
        $locality = Locality::create([
            'name' => 'Wakad',
            'slug' => 'wakad',
        ]);

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

        $this->getJson('/api/schools?locality=wakad&vehicleType=car')
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson('/api/schools/slug/skyline-driving-academy')
            ->assertOk();

        $package = $this->postJson('/api/packages', [
            'schoolId' => $schoolId,
            'name' => 'Beginner Car',
            'price' => 5500,
            'sessions' => 12,
            'vehicleType' => 'car',
            'transmission' => 'manual',
        ])->assertCreated();

        $packageId = $package->json('id') ?? \App\Models\DrivePackage::query()->value('id');
        $this->patchJson('/api/packages/'.$packageId, ['active' => false])->assertOk()->assertJsonPath('active', false);

        $review = $this->postJson('/api/reviews', [
            'schoolId' => $schoolId,
            'authorName' => 'A User',
            'rating' => 5,
            'content' => 'Great experience',
        ])->assertCreated();

        $reviewId = $review->json('id') ?? Review::query()->value('id');
        $this->getJson('/api/reviews?schoolId='.$schoolId)->assertOk()->assertJsonCount(1);
        $this->patchJson('/api/reviews/'.$reviewId, ['approved' => true])->assertOk()->assertJsonPath('approved', true);

        $inquiry = $this->postJson('/api/inquiries', [
            'schoolId' => $schoolId,
            'name' => 'Lead One',
            'phone' => '9888888888',
            'vehicleType' => 'car',
            'status' => 'pending',
        ])->assertCreated();

        $inquiryId = $inquiry->json('id') ?? Inquiry::query()->value('id');
        $this->getJson('/api/inquiries?schoolId='.$schoolId)->assertOk()->assertJsonCount(1);
        $this->patchJson('/api/inquiries/'.$inquiryId, ['status' => 'contacted'])->assertOk()->assertJsonPath('status', 'contacted');

        $this->deleteJson('/api/reviews/'.$reviewId)->assertNoContent();
        $this->deleteJson('/api/packages/'.$packageId)->assertNoContent();
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

        Inquiry::create([
            'school_id' => $school->id,
            'name' => 'Lead',
            'phone' => '9000000001',
            'vehicle_type' => 'car',
            'status' => 'pending',
        ]);

        Review::create([
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
}
