<?php

namespace Tests\Feature;

use App\Models\Locality;
use App\Models\Prospect;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1109: acquisition funnel numbers for admins. */
class AcquisitionFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_and_supply_numbers(): void
    {
        Notification::fake();
        $baner = Locality::create(['name' => 'Baner', 'slug' => 'baner']);
        Locality::create(['name' => 'Wakad', 'slug' => 'wakad']);

        Prospect::create(['type' => 'school', 'name' => 'A']);
        Prospect::create(['type' => 'school', 'name' => 'B', 'source' => 'ads'])->forceFill(['stage' => 'contacted'])->save();
        DB::table('ad_leads')->insert([
            ['lead_id' => 'l1', 'is_test' => false, 'outcome' => 'created', 'created_at' => now()],
            ['lead_id' => 'l2', 'is_test' => true, 'outcome' => 'test', 'created_at' => now()],
        ]);

        // A registration from an ad that goes live two hours later.
        $res = $this->postJson('/api/auth/register', [
            'name' => 'Ad School', 'email' => 'ad@example.com', 'password' => 'Secret123', 'role' => 'school',
            'attribution' => ['utm_source' => 'google', 'utm_medium' => 'cpc'],
        ])->assertCreated();
        $this->travel(2)->hours();
        User::find($res->json('user.id'))->markEmailAsVerified();
        School::find($res->json('schoolId'))->update(['phone' => '9822000000', 'locality_id' => $baner->id, 'latitude' => 18.56, 'longitude' => 73.78]);

        // An older live school, and a trainer still in draft.
        School::create(['name' => 'Old', 'slug' => 'old', 'locality_id' => $baner->id])->forceFill(['created_at' => now()->subYear(), 'published_at' => now()->subYear()])->save();
        $this->postJson('/api/auth/register', ['name' => 'T', 'email' => 't@example.com', 'password' => 'Secret123', 'role' => 'school', 'listingType' => 'trainer']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $r = $this->getJson('/api/admin/acquisition?days=30')->assertOk();

        $r->assertJsonPath('prospects.total', 2)
            ->assertJsonPath('prospects.byStage.contacted', 1)
            ->assertJsonPath('prospects.bySource.ads', 1)
            ->assertJsonPath('ads.leads', 1)
            ->assertJsonPath('ads.signedUp', 1)
            ->assertJsonPath('listings.joined', 2)
            ->assertJsonPath('listings.published.schools', 1)
            ->assertJsonPath('listings.published.trainers', 0)
            ->assertJsonPath('listings.waitingToPublish', 1)
            ->assertJsonPath('listings.medianHoursToPublish', 2)
            ->assertJsonPath('supply.0.locality', 'Baner')
            ->assertJsonPath('supply.0.schools', 2)
            ->assertJsonPath('supply.1.schools', 0);

        Sanctum::actingAs(User::factory()->create(['role' => 'school']));
        $this->getJson('/api/admin/acquisition')->assertForbidden();
    }
}
