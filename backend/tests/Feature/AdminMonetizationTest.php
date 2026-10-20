<?php

namespace Tests\Feature;

use App\Models\FeaturedPlacement;
use App\Models\Locality;
use App\Models\MarketplaceSetting;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-806: admin monetization console APIs. */
class AdminMonetizationTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $name): School
    {
        return School::create(['name' => $name, 'slug' => str($name)->slug()]);
    }

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_console_endpoints_are_admin_only(): void
    {
        $owner = User::factory()->create(['role' => 'school']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/admin/subscriptions')->assertForbidden();
        $this->getJson('/api/admin/placements')->assertForbidden();
        $this->putJson('/api/admin/marketplace-settings', ['sponsored_slots_per_page' => 9])->assertForbidden();
    }

    public function test_overview_reports_mrr_trials_slots_and_churn(): void
    {
        $subs = app(SubscriptionService::class);
        $a = $this->school('Alpha');
        $b = $this->school('Bravo');
        $c = $this->school('Charlie');
        $subs->assign($a->id, 'premium', 1);
        $subs->assign($b->id, 'featured', 1);

        // Charlie paid last month and let it lapse ten days ago.
        $subs->assign($c->id, 'featured', 1);
        Subscription::withoutGlobalScope('school')->where('school_id', $c->id)->where('status', 'active')
            ->update(['starts_at' => now()->subDays(40), 'expires_at' => now()->subDays(10)]);
        // Alpha has been paying since before the window.
        Subscription::withoutGlobalScope('school')->where('school_id', $a->id)->where('status', 'active')
            ->update(['starts_at' => now()->subDays(60)]);

        $this->admin();
        $o = $this->getJson('/api/admin/subscriptions/overview')->assertOk()->json();

        $this->assertSame(4999 + 1999, $o['mrr']);
        $this->assertSame((4999 + 1999) * 12, $o['arr']);
        $this->assertSame(2, $o['activePaid']);
        $this->assertSame(3, $o['trials']); // each new school started a trial
        $this->assertSame(1, $o['churn30d']['churned']);
        $this->assertSame(2, $o['churn30d']['paidAtStart']);
        $this->assertSame(['perPage' => 2, 'eligibleSchools' => 1, 'utilization' => 0.5], $o['sponsoredSlots']);
        $this->assertSame([], $o['trialsEndingSoon']); // trials end in 30 days, outside the 7-day window
    }

    public function test_subscriptions_list_filters_by_status_and_school(): void
    {
        $alpha = $this->school('Alpha Driving');
        $this->school('Bravo Driving');
        app(SubscriptionService::class)->assign($alpha->id, 'premium', 1);

        $this->admin();
        $this->getJson('/api/admin/subscriptions?status=trial')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/admin/subscriptions?status=active&search=alpha')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.planCode', 'premium');
    }

    public function test_admin_runs_campaigns_and_slot_settings(): void
    {
        $school = $this->school('Campaign School');
        $locality = Locality::create(['name' => 'Wakad', 'slug' => 'wakad-admin']);
        $this->admin();

        $this->postJson('/api/admin/placements', [
            'schoolId' => $school->id, 'placement' => 'locality',
            'startsAt' => now()->toISOString(), 'endsAt' => now()->addDays(7)->toISOString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('localityId');

        $id = $this->postJson('/api/admin/placements', [
            'schoolId' => $school->id, 'placement' => 'search_top',
            'startsAt' => now()->subMinute()->toISOString(), 'endsAt' => now()->addDays(7)->toISOString(),
        ])->assertCreated()->assertJsonPath('live', true)->json('id');

        $this->getJson('/api/admin/placements')->assertOk()->assertJsonCount(1);
        $this->postJson("/api/admin/placements/{$id}/end")->assertOk()->assertJsonPath('live', false);
        $this->assertFalse(FeaturedPlacement::find($id)->ends_at->isFuture());

        $this->putJson('/api/admin/marketplace-settings', ['sponsored_slots_per_page' => 3, 'homepage_slots' => 8])
            ->assertOk()->assertJsonPath('sponsored_slots_per_page', 3);
        $this->assertSame(3, MarketplaceSetting::get('sponsored_slots_per_page'));
        $this->putJson('/api/admin/marketplace-settings', ['sponsored_slots_per_page' => 50])->assertUnprocessable();
    }
}
