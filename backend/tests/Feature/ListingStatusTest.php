<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\Locality;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1101: only published listings are public; independent trainers are a listing type. */
class ListingStatusTest extends TestCase
{
    use RefreshDatabase;

    private Locality $locality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locality = Locality::create(['name' => 'Baner', 'slug' => 'baner']);
    }

    private function listing(string $slug, string $status, string $type = 'school'): School
    {
        $school = School::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'locality_id' => $this->locality->id,
            'phone' => '9000000000', 'latitude' => 18.559, 'longitude' => 73.7868, 'verified' => true,
        ]);
        $school->forceFill(['listing_status' => $status, 'listing_type' => $type])->save();

        return $school;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Meera Joshi', 'email' => 'meera@example.com', 'password' => 'Secret123', 'role' => 'school',
        ], $overrides);
    }

    public function test_hidden_listings_never_appear_publicly(): void
    {
        $live = $this->listing('live', 'published');
        $hidden = collect(['unclaimed', 'draft', 'suspended'])->map(fn ($s) => $this->listing($s, $s));

        $ids = collect($this->getJson('/api/schools')->assertOk()->json())->pluck('id');
        $this->assertEquals([$live->id], $ids->all());
        $this->assertEquals([$live->id], collect($this->getJson('/api/schools/featured')->json())->pluck('id')->all());
        $this->getJson('/api/schools?nearLat=18.559&nearLng=73.7868&radiusKm=5')->assertJsonCount(1);
        $this->assertSame(1, $this->getJson('/api/stats/overview')->json('totalSchools'));

        foreach ($hidden as $school) {
            $this->getJson("/api/schools/{$school->id}")->assertNotFound();
            $this->getJson("/api/schools/slug/{$school->slug}")->assertNotFound();
            $this->getJson("/api/schools/slug/{$school->slug}/trainers")->assertNotFound();
            $this->getJson("/api/schools/compare?ids={$live->id},{$school->id}")->assertJsonPath('missingIds', [$school->id]);
            $this->postJson('/api/inquiries', [
                'schoolId' => $school->id, 'name' => 'Lead', 'phone' => '9876543210', 'vehicleType' => 'car',
                'formStartedAt' => now()->subMinute()->timestamp,
            ])->assertUnprocessable()->assertJsonValidationErrors('schoolId');
        }
    }

    public function test_owner_and_admin_still_see_a_hidden_listing(): void
    {
        $owner = User::factory()->create(['role' => 'school']);
        $draft = $this->listing('mine', 'draft');
        $draft->forceFill(['user_id' => $owner->id, 'listing_status' => 'suspended'])->save();
        $owner->update(['school_id' => $draft->id]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/schools/{$draft->id}")->assertOk()->assertJsonPath('listingStatus', 'suspended');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/schools?includeHidden=1&listingStatus=suspended')->assertJsonCount(1)->assertJsonPath('0.id', $draft->id);

        // includeHidden means nothing for anyone else.
        Sanctum::actingAs($owner);
        $this->getJson('/api/schools?includeHidden=1')->assertJsonCount(0);
    }

    public function test_registration_creates_a_draft_that_goes_live_once_complete(): void
    {
        $res = $this->postJson('/api/auth/register', $this->payload())->assertCreated();
        $school = School::find($res->json('schoolId'));
        $this->assertSame('draft', $school->listing_status);
        $this->assertSame('school', $school->listing_type);

        $owner = User::find($res->json('user.id'));
        Sanctum::actingAs($owner);
        $this->getJson("/api/schools/{$school->id}/dashboard")
            ->assertJsonPath('listingStatus', 'draft')
            ->assertJsonPath('publishBlockers', ['verify_email', 'phone', 'locality', 'location']);

        $owner->markEmailAsVerified();
        $this->patchJson("/api/schools/{$school->id}", [
            'phone' => '9822012345', 'localityId' => $this->locality->id, 'latitude' => 18.56, 'longitude' => 73.79,
        ])->assertOk()->assertJsonPath('listingStatus', 'published');

        $this->assertCount(1, $this->getJson('/api/schools')->json());
    }

    public function test_an_owner_cannot_publish_or_retype_their_own_listing(): void
    {
        $res = $this->postJson('/api/auth/register', $this->payload())->assertCreated();
        Sanctum::actingAs(User::find($res->json('user.id')));

        $this->patchJson('/api/schools/'.$res->json('schoolId'), ['listingStatus' => 'published', 'listingType' => 'trainer'])->assertOk();
        $school = School::find($res->json('schoolId'));
        $this->assertSame('draft', $school->listing_status);
        $this->assertSame('school', $school->listing_type);
    }

    public function test_independent_trainer_registration(): void
    {
        $res = $this->postJson('/api/auth/register', $this->payload(['listingType' => 'trainer', 'womenInstructor' => true]))
            ->assertCreated();

        $school = School::find($res->json('schoolId'));
        $this->assertSame('trainer', $school->listing_type);
        $this->assertTrue($school->women_instructor);

        $trainer = Instructor::withoutGlobalScope('school')->where('school_id', $school->id)->sole();
        $this->assertSame($res->json('user.id'), $trainer->user_id);
        $this->assertTrue($trainer->women_instructor);
        $this->assertTrue($trainer->public_visible);
        $this->assertSame(1, Subscription::withoutGlobalScope('school')->where('school_id', $school->id)->where('status', 'trial')->count());

        // Trainers may not buy operations tiers.
        Sanctum::actingAs(User::find($res->json('user.id')));
        $this->getJson("/api/schools/{$school->id}/entitlements")->assertJsonPath('availablePlans', ['basic', 'featured']);
        $this->postJson("/api/schools/{$school->id}/billing/invoices", ['planCode' => 'premium', 'months' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('planCode');
    }

    public function test_search_filters_by_listing_type(): void
    {
        $school = $this->listing('school-a', 'published');
        $trainer = $this->listing('trainer-a', 'published', 'trainer');

        $this->getJson('/api/schools?listingType=trainer')->assertJsonCount(1)->assertJsonPath('0.id', $trainer->id)
            ->assertJsonPath('0.listingType', 'trainer');
        $this->getJson('/api/schools?listingType=school')->assertJsonCount(1)->assertJsonPath('0.id', $school->id);
        $this->getJson('/api/schools')->assertJsonCount(2);
    }

    public function test_admin_suspends_and_restores_a_listing(): void
    {
        $school = $this->listing('rogue', 'published');
        $owner = User::factory()->create(['role' => 'school', 'school_id' => $school->id]);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/admin/schools/{$school->id}/listing-status", ['status' => 'suspended'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson("/api/admin/schools/{$school->id}/listing-status", ['status' => 'suspended', 'reason' => 'Fake reviews'])
            ->assertOk()->assertJsonPath('listingStatus', 'suspended');
        $this->getJson('/api/schools/slug/rogue')->assertNotFound();
        $this->assertSame('Fake reviews', AuditLog::where('action', 'listing_status')->sole()->new_values['reason']);

        $this->patchJson("/api/admin/schools/{$school->id}/listing-status", ['status' => 'published'])->assertOk();
        $this->getJson('/api/schools/slug/rogue')->assertOk();
    }

    public function test_unclaimed_listing_starts_no_trial(): void
    {
        $school = School::create(['name' => 'Prospect', 'slug' => 'prospect', 'listing_status' => 'unclaimed']);
        // listing_status is not fillable: set it as the prospects flow does.
        $this->assertSame('published', $school->listing_status);

        $unclaimed = new School(['name' => 'Prospect 2', 'slug' => 'prospect-2']);
        $unclaimed->listing_status = 'unclaimed';
        $unclaimed->save();
        $this->assertSame(0, Subscription::withoutGlobalScope('school')->where('school_id', $unclaimed->id)->count());
    }

    /** DIQ-1108: first-visit tags are kept, and our own channels are recognised. */
    public function test_registration_records_where_the_listing_came_from(): void
    {
        $res = $this->postJson('/api/auth/register', $this->payload([
            'attribution' => ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'ads-222'],
        ]))->assertCreated();
        $school = School::find($res->json('schoolId'));
        $this->assertSame(['ads', 'google', 'cpc', 'ads-222'], [$school->source, $school->utm_source, $school->utm_medium, $school->utm_campaign]);

        $res = $this->postJson('/api/auth/register', $this->payload(['email' => 'two@example.com', 'attribution' => ['utm_source' => 'outreach']]))->assertCreated();
        $this->assertSame('outreach', School::find($res->json('schoolId'))->source);

        $res = $this->postJson('/api/auth/register', $this->payload(['email' => 'three@example.com']))->assertCreated();
        $this->assertSame('organic', School::find($res->json('schoolId'))->source);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'four@example.com', 'attribution' => ['gclid' => 'x']]))
            ->assertUnprocessable()->assertJsonValidationErrors('attribution');
    }
}
