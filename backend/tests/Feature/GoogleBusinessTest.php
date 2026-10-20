<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\GoogleBusinessConnection;
use App\Models\Locality;
use App\Models\Prospect;
use App\Models\School;
use App\Models\User;
use App\Services\GoogleBusinessService;
use App\Services\ListingClaimService;
use App\Services\ProspectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1107: Google Business Profile connect, import and claim proof. */
class GoogleBusinessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['services.google.client_id' => 'cid', 'services.google.client_secret' => 'csecret']);

        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Old Name', 'slug' => 'old-name', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        DB::table('school_admins')->insert(['school_id' => $this->school->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function fakeGoogle(bool $verified = true, string $placeId = 'ChIJ-place'): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at-1', 'refresh_token' => 'rt-1', 'expires_in' => 3600]),
            'oauth2.googleapis.com/revoke' => Http::response([]),
            'mybusinessaccountmanagement.googleapis.com/*' => Http::response(['accounts' => [['name' => 'accounts/1']]]),
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response(['locations' => [[
                'name' => 'locations/9',
                'title' => 'Skyline Driving Academy',
                'phoneNumbers' => ['primaryPhone' => '020 2729 1234'],
                'storefrontAddress' => ['addressLines' => ['12 Baner Road'], 'locality' => 'Pune', 'postalCode' => '411045'],
                'latlng' => ['latitude' => 18.559, 'longitude' => 73.7868],
                'metadata' => ['placeId' => $placeId, 'hasVoiceOfMerchant' => $verified],
                'regularHours' => ['periods' => array_map(fn ($d) => [
                    'openDay' => $d, 'openTime' => ['hours' => 7], 'closeDay' => $d, 'closeTime' => ['hours' => 19],
                ], ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'])],
            ]]]),
            'mybusiness.googleapis.com/v4/*' => Http::response(['averageRating' => 4.66, 'totalReviewCount' => 212]),
        ]);
    }

    /** Runs the OAuth round trip and returns the redirect target. */
    private function oauth(string $url): string
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame(GoogleBusinessService::SCOPE, $q['scope']);
        $this->assertSame('offline', $q['access_type']);

        return $this->get('/api/google-business/callback?code=auth-code&state='.urlencode($q['state']))->assertRedirect()->headers->get('Location');
    }

    public function test_owner_connects_and_imports_a_verified_location(): void
    {
        $this->fakeGoogle();
        Sanctum::actingAs($this->owner);
        $this->getJson("/api/schools/{$this->school->id}/google-business")->assertJsonPath('enabled', true)->assertJsonPath('connected', false);

        $url = $this->postJson("/api/schools/{$this->school->id}/google-business/connect")->assertOk()->json('url');
        $this->assertSame(config('app.frontend_url').'/dashboard/profile?google=connected', $this->oauth($url));

        $c = GoogleBusinessConnection::sole();
        $this->assertSame('rt-1', $c->refresh_token);
        $this->assertNotSame('rt-1', DB::table('google_business_connections')->value('refresh_token'), 'stored encrypted');

        $this->getJson("/api/schools/{$this->school->id}/google-business/locations")->assertOk()
            ->assertJsonPath('0.name', 'accounts/1/locations/9')
            ->assertJsonPath('0.hours', 'Mon–Sat 07:00–19:00')
            ->assertJsonPath('0.address', '12 Baner Road, Pune, 411045');

        $this->postJson("/api/schools/{$this->school->id}/google-business/import", ['location' => 'accounts/1/locations/9'])->assertOk()
            ->assertJsonPath('school.name', 'Skyline Driving Academy')
            ->assertJsonPath('school.businessVerified', true)
            ->assertJsonPath('rating', 4.7)
            ->assertJsonPath('reviewCount', 212);

        $school = $this->school->fresh();
        $this->assertSame('ChIJ-place', $school->google_place_id);
        $this->assertSame(18.559, $school->latitude);
        $this->assertSame('Mon–Sat 07:00–19:00', $school->timings);
        $this->assertSame(1, AuditLog::where('action', 'google_import')->count());

        $this->deleteJson("/api/schools/{$this->school->id}/google-business")->assertNoContent();
        $this->assertSame(0, GoogleBusinessConnection::count());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/revoke'));
    }

    public function test_unverified_location_does_not_verify_the_business(): void
    {
        $this->fakeGoogle(verified: false);
        Sanctum::actingAs($this->owner);
        $this->oauth($this->postJson("/api/schools/{$this->school->id}/google-business/connect")->json('url'));

        $this->postJson("/api/schools/{$this->school->id}/google-business/import", ['location' => 'accounts/1/locations/9'])->assertOk();
        $this->assertFalse($this->school->fresh()->business_verified);
        $this->assertNull($this->school->fresh()->google_place_id);
    }

    public function test_managers_and_other_schools_cannot_connect(): void
    {
        $manager = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id]);
        DB::table('school_admins')->insert(['school_id' => $this->school->id, 'user_id' => $manager->id, 'role' => 'manager', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($manager);
        $this->postJson("/api/schools/{$this->school->id}/google-business/connect")->assertForbidden();

        $other = User::factory()->create(['role' => 'school']);
        Sanctum::actingAs($other);
        $this->getJson("/api/schools/{$this->school->id}/google-business")->assertForbidden();
    }

    public function test_hidden_when_not_configured_and_tampered_state_is_refused(): void
    {
        config(['services.google.client_id' => null]);
        Sanctum::actingAs($this->owner);
        $this->getJson("/api/schools/{$this->school->id}/google-business")->assertJsonPath('enabled', false);
        $this->postJson("/api/schools/{$this->school->id}/google-business/connect")->assertNotFound();

        $this->get('/api/google-business/callback?code=x&state=forged')
            ->assertRedirect(config('app.frontend_url').'/dashboard/profile?google=expired');
    }

    public function test_claiming_with_the_matching_verified_google_location(): void
    {
        $this->fakeGoogle();
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner']);
        $prospect = Prospect::create(['type' => 'school', 'name' => 'Skyline Driving Academy', 'google_place_id' => 'ChIJ-place',
            'phone' => '9822055555', 'locality_id' => $locality->id, 'latitude' => 18.559, 'longitude' => 73.7868]);
        $listing = app(ProspectService::class)->createListing($prospect);
        $url = app(ListingClaimService::class)->issue($listing, $prospect)['url'];
        $token = substr($url, strrpos($url, '/') + 1);

        $this->getJson("/api/claims/{$token}")->assertJsonPath('channels.google', 'Google Business Profile');
        $back = $this->oauth($this->getJson("/api/claims/{$token}/google")->assertOk()->json('url'));

        $this->assertStringStartsWith(config('app.frontend_url').'/claim/'.$token.'?google=verified&code=', $back);
        parse_str(parse_url($back, PHP_URL_QUERY), $q);

        $this->postJson("/api/claims/{$token}/complete", [
            'code' => $q['code'], 'name' => 'Amit Shah', 'email' => 'amit@example.com', 'password' => 'Secret123',
        ])->assertCreated();
        $this->assertNotNull($listing->fresh()->user_id);
        $this->assertSame(0, GoogleBusinessConnection::count(), 'claim-time tokens are not kept');
    }

    public function test_claim_with_google_fails_when_the_place_does_not_match(): void
    {
        $this->fakeGoogle(placeId: 'some-other-place');
        $prospect = Prospect::create(['type' => 'school', 'name' => 'X', 'google_place_id' => 'ChIJ-place']);
        $listing = app(ProspectService::class)->createListing($prospect);
        $url = app(ListingClaimService::class)->issue($listing, $prospect)['url'];
        $token = substr($url, strrpos($url, '/') + 1);

        $back = $this->oauth($this->getJson("/api/claims/{$token}/google")->json('url'));
        $this->assertSame(config('app.frontend_url').'/claim/'.$token.'?google=nomatch', $back);
    }
}
