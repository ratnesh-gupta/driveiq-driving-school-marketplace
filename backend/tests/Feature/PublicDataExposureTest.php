<?php

namespace Tests\Feature;

use App\Models\DrivePackage;
use App\Models\Inquiry;
use App\Models\Locality;
use App\Models\Review;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** BSEC-09 / 12 / 19 / 27: public listings, input limits, trimmed public payload. */
class PublicDataExposureTest extends TestCase
{
    use RefreshDatabase;

    private School $live;

    private School $hidden;

    private User $liveOwner;

    private User $hiddenOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-x']);
        [$this->live, $this->liveOwner] = $this->school('live', $locality);
        [$this->hidden, $this->hiddenOwner] = $this->school('hidden', $locality);
        $this->hidden->forceFill(['listing_status' => 'suspended'])->save();

        foreach ([$this->live, $this->hidden] as $s) {
            Review::withoutGlobalScope('school')->create(['school_id' => $s->id, 'author_name' => 'A', 'rating' => 5, 'content' => 'ok '.$s->slug, 'approved' => true]);
            Review::withoutGlobalScope('school')->create(['school_id' => $s->id, 'author_name' => 'P', 'rating' => 3, 'content' => 'pending '.$s->slug, 'approved' => false]);
            DrivePackage::withoutGlobalScope('school')->create(['school_id' => $s->id, 'name' => 'Active '.$s->slug, 'price' => 100, 'sessions' => 5, 'vehicle_type' => 'car', 'transmission' => 'manual', 'active' => true]);
            DrivePackage::withoutGlobalScope('school')->create(['school_id' => $s->id, 'name' => 'Inactive '.$s->slug, 'price' => 100, 'sessions' => 5, 'vehicle_type' => 'car', 'transmission' => 'manual', 'active' => false]);
            Inquiry::withoutGlobalScope('school')->create(['school_id' => $s->id, 'name' => 'L', 'phone' => '9000000000', 'vehicle_type' => 'car', 'status' => 'pending']);
        }
    }

    /** @return array{School, User} */
    private function school(string $slug, Locality $locality, string $type = 'school'): array
    {
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'user_id' => $owner->id, 'locality_id' => $locality->id,
            'address' => 'Addr', 'phone' => '9000000001', 'email' => "{$slug}@biz.example",
        ]);
        $school->forceFill(['listing_type' => $type])->save();
        $owner->update(['school_id' => $school->id]);

        return [$school->refresh(), $owner];
    }

    public function test_public_reviews_only_cover_approved_reviews_of_published_listings(): void
    {
        $all = $this->getJson('/api/reviews')->assertOk();
        $all->assertJsonCount(1)->assertHeader('X-Total-Count', '1');
        $this->assertStringNotContainsString('hidden', json_encode($all->json()));

        $this->getJson('/api/reviews?schoolId='.$this->hidden->id)->assertOk()->assertJsonCount(0);
        $this->getJson('/api/reviews?schoolId='.$this->hidden->id.'&includePending=1')->assertOk()->assertJsonCount(0);
    }

    public function test_owner_sees_their_own_hidden_listing_reviews_but_not_another_schools(): void
    {
        Sanctum::actingAs($this->hiddenOwner);
        $this->getJson('/api/reviews?schoolId='.$this->hidden->id.'&includePending=1')->assertOk()->assertJsonCount(2);
        // Cross-school: the hidden school's owner gets nothing extra from the live school.
        $this->getJson('/api/reviews?schoolId='.$this->live->id.'&includePending=1')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($this->liveOwner);
        $this->getJson('/api/reviews?schoolId='.$this->hidden->id.'&includePending=1')->assertOk()->assertJsonCount(0);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/reviews?includePending=1')->assertOk()->assertJsonCount(4);
    }

    public function test_review_list_is_paginated(): void
    {
        foreach (range(1, 4) as $i) {
            Review::withoutGlobalScope('school')->create(['school_id' => $this->live->id, 'author_name' => 'X'.$i, 'rating' => 4, 'content' => 'c', 'approved' => true]);
        }
        $this->getJson('/api/reviews?limit=2')->assertOk()->assertJsonCount(2)->assertHeader('X-Total-Count', '5');
        $this->getJson('/api/reviews?limit=5000')->assertUnprocessable();
    }

    public function test_public_packages_are_active_and_from_published_listings(): void
    {
        $res = $this->getJson('/api/packages')->assertOk()->assertJsonCount(1);
        $this->assertSame('Active live', $res->json('0.name'));

        $this->getJson('/api/packages?schoolId='.$this->hidden->id)->assertOk()->assertJsonCount(0);
        $this->getJson('/api/packages?schoolId='.$this->live->id)->assertOk()->assertJsonCount(1);
    }

    public function test_owner_sees_inactive_own_packages_only(): void
    {
        Sanctum::actingAs($this->liveOwner);
        $this->getJson('/api/packages?schoolId='.$this->live->id)->assertOk()->assertJsonCount(2);
        $this->getJson('/api/packages?schoolId='.$this->hidden->id)->assertOk()->assertJsonCount(0);
    }

    public function test_stats_overview_counts_published_listings_only(): void
    {
        $this->getJson('/api/stats/overview')->assertOk()
            ->assertJsonPath('totalSchools', 1)
            ->assertJsonPath('totalReviews', 1)
            ->assertJsonPath('totalInquiries', 1);
    }

    public function test_package_writes_are_checked_against_the_callers_school(): void
    {
        $payload = fn (int $schoolId) => ['schoolId' => $schoolId, 'name' => 'New', 'price' => 10, 'sessions' => 1, 'vehicleType' => 'car', 'transmission' => 'manual'];

        Sanctum::actingAs($this->liveOwner);
        // A client-supplied foreign schoolId is ignored: the package lands in the caller's school.
        $id = $this->postJson('/api/packages', $payload($this->hidden->id))->assertCreated()->json('id');
        $this->assertSame($this->live->id, (int) DrivePackage::withoutGlobalScope('school')->find($id)->school_id);

        // Another school's package: indistinguishable from a missing one.
        $foreign = DrivePackage::withoutGlobalScope('school')->where('school_id', $this->hidden->id)->first();
        $this->patchJson("/api/packages/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->deleteJson("/api/packages/{$foreign->id}")->assertNotFound();
        $this->assertNotSame('Hijack', $foreign->fresh()->name);

        // A school-role account with no school cannot create packages anywhere.
        $orphan = User::factory()->create(['role' => 'school', 'school_id' => null]);
        Sanctum::actingAs($orphan);
        $this->postJson('/api/packages', $payload($this->live->id))->assertStatus(404)->assertJsonPath('message', 'School not found');
        $this->assertSame(0, DrivePackage::withoutGlobalScope('school')->where('name', 'New')->where('school_id', $this->hidden->id)->count());

        // Admin may target any school.
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/packages', $payload($this->hidden->id))->assertCreated();
    }

    public function test_school_profile_inputs_are_length_limited_and_urls_must_be_https(): void
    {
        Sanctum::actingAs($this->liveOwner);
        $url = "/api/schools/{$this->live->id}";

        foreach (['javascript:alert(1)', 'data:text/html;base64,AAAA', 'http://insecure.example/x.png', '//cdn.example/x.png', 'not a url'] as $bad) {
            $this->patchJson($url, ['imageUrl' => $bad])->assertUnprocessable();
        }
        $this->patchJson($url, ['description' => str_repeat('x', 5001)])->assertUnprocessable();
        $this->patchJson($url, ['address' => str_repeat('x', 501)])->assertUnprocessable();
        $this->patchJson($url, ['cancellationPolicy' => str_repeat('x', 2001)])->assertUnprocessable();
        $this->patchJson($url, ['serviceAreas' => array_fill(0, 51, 'Baner')])->assertUnprocessable();
        $this->patchJson($url, ['languages' => [str_repeat('x', 51)]])->assertUnprocessable();

        $this->patchJson($url, ['imageUrl' => 'https://cdn.example/x.png', 'description' => 'Fine'])->assertOk()
            ->assertJsonPath('imageUrl', 'https://cdn.example/x.png');

        // Another school's profile stays off limits.
        $this->patchJson("/api/schools/{$this->hidden->id}", ['description' => 'x'])->assertForbidden();

        $this->postJson('/api/packages', ['schoolId' => $this->live->id, 'name' => 'P', 'price' => 1, 'sessions' => 1, 'vehicleType' => 'car', 'transmission' => 'manual', 'description' => str_repeat('x', 2001)])
            ->assertUnprocessable();
    }

    public function test_trainer_photo_must_be_https(): void
    {
        Sanctum::actingAs($this->liveOwner);
        $this->postJson("/api/schools/{$this->live->id}/instructors", ['name' => 'T', 'photoUrl' => 'javascript:alert(1)'])->assertUnprocessable();
    }

    public function test_public_school_payload_hides_internals_but_owner_and_admin_keep_them(): void
    {
        $public = $this->getJson('/api/schools/'.$this->live->id)->assertOk();
        foreach (['planCode', 'profileCompleteness', 'rankingScore'] as $field) {
            $public->assertJsonMissingPath($field);
        }
        // Needed by the public page.
        $public->assertJsonPath('listingTier', 'basic')->assertJsonPath('email', 'live@biz.example');

        Sanctum::actingAs($this->liveOwner);
        $this->getJson('/api/schools/'.$this->live->id)->assertOk()->assertJsonPath('planCode', 'basic')->assertJsonStructure(['profileCompleteness']);

        // Another school's owner sees the public shape only.
        Sanctum::actingAs($this->hiddenOwner);
        $this->getJson('/api/schools/'.$this->live->id)->assertOk()->assertJsonMissingPath('planCode');
    }

    public function test_independent_trainers_personal_email_is_not_published(): void
    {
        $locality = Locality::first();
        [$trainer, $owner] = $this->school('solo', $locality, 'trainer');

        $this->getJson('/api/schools/'.$trainer->id)->assertOk()->assertJsonMissingPath('email');

        Sanctum::actingAs($owner);
        $this->getJson('/api/schools/'.$trainer->id)->assertOk()->assertJsonPath('email', 'solo@biz.example');
    }

    public function test_search_results_do_not_carry_ranking_internals_for_anonymous_visitors(): void
    {
        $res = $this->getJson('/api/schools?limit=5')->assertOk();
        $this->assertStringNotContainsString('rankingScore', $res->getContent());
        $this->assertStringNotContainsString('planCode', $res->getContent());
    }
}
