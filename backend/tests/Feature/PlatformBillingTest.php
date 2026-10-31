<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** DIQ-803: manual plan billing. */
class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.payment.upi_id' => 'driveiq@okicici', 'billing.gst_rate' => 18]);
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Billing School', 'slug' => 'billing-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        SchoolAdmin::create(['school_id' => $this->school->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'status' => 'active']);
    }

    private function request(array $body = [])
    {
        return $this->postJson("/api/schools/{$this->school->id}/billing/invoices", array_merge(['planCode' => 'premium', 'months' => 3], $body));
    }

    private function asAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_owner_gets_an_invoice_with_gst_and_payment_instructions(): void
    {
        Sanctum::actingAs($this->owner);

        $res = $this->request(['gstin' => '27abcde1234f1z5'])->assertCreated();

        $res->assertJsonPath('subtotal', 14997)
            ->assertJsonPath('gstAmount', 2699)
            ->assertJsonPath('total', 17696)
            ->assertJsonPath('status', 'issued')
            ->assertJsonPath('billedToGstin', '27ABCDE1234F1Z5')
            ->assertJsonPath('paymentInstructions.upi_id', 'driveiq@okicici');
        $this->assertMatchesRegularExpression('/^DIQ-\d{4}-\d{6}$/', $res->json('invoiceNumber'));

        // One open invoice at a time; the owner can cancel it and ask again.
        $this->request(['months' => 1])->assertUnprocessable();
        $this->postJson("/api/schools/{$this->school->id}/billing/invoices/{$res->json('id')}/cancel")
            ->assertOk()->assertJsonPath('status', 'void');
        $this->request(['planCode' => 'featured', 'months' => 1])->assertCreated()->assertJsonPath('total', 2359);
    }

    public function test_validation_and_owner_only(): void
    {
        $manager = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id]);
        SchoolAdmin::create(['school_id' => $this->school->id, 'user_id' => $manager->id, 'role' => 'manager', 'status' => 'active']);

        Sanctum::actingAs($manager);
        $this->request()->assertForbidden();
        $this->getJson("/api/schools/{$this->school->id}/billing/invoices")->assertOk();

        Sanctum::actingAs($this->owner);
        $this->request(['planCode' => 'basic'])->assertUnprocessable();
        $this->request(['months' => 2])->assertUnprocessable();
        $this->request(['gstin' => 'NOT-A-GSTIN'])->assertUnprocessable();

        Sanctum::actingAs($this->otherSchoolUser());
        $this->getJson("/api/schools/{$this->school->id}/billing/invoices")->assertForbidden();
    }

    public function test_only_an_admin_can_record_payment_which_activates_the_plan(): void
    {
        Sanctum::actingAs($this->owner);
        $id = $this->request()->json('id');

        $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'UTR1'])->assertForbidden();

        $this->asAdmin();
        $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'UTR123456'])
            ->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('paymentReference', 'UTR123456');
        $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'again'])->assertUnprocessable();

        $sub = Subscription::withoutGlobalScope('school')->where('school_id', $this->school->id)->where('status', 'active')->first();
        $this->assertSame('premium', $sub->plan->code);
        $this->assertEqualsWithDelta(now()->addMonths(3)->timestamp, $sub->expires_at->timestamp, 5);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->owner->id, 'type' => 'billing.paid']);

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/schools/{$this->school->id}/entitlements")->assertJsonPath('plan', 'premium');
        $this->getJson("/api/schools/{$this->school->id}")->assertJsonPath('isSponsored', true);
    }

    public function test_renewing_the_same_plan_extends_from_the_current_end(): void
    {
        // Pinned: this used to depend on today's date (month-end overflow).
        Carbon::setTestNow('2026-03-10 10:00:00');

        $this->renewTwice();

        $subs = Subscription::withoutGlobalScope('school')->where('school_id', $this->school->id)->where('status', 'active')->get();
        $this->assertCount(1, $subs);
        $this->assertSame('2026-05-10', $subs->first()->expires_at->toDateString());
    }

    /** @return array<string, array{string, string}> */
    public static function monthEndProvider(): array
    {
        return [
            'jan 31' => ['2026-01-31 10:00:00', '2026-03-28'],  // Feb 28 then +1 month
            'aug 31' => ['2026-08-31 10:00:00', '2026-10-30'],  // Sep 30 then Oct 30
            'oct 31' => ['2026-10-31 10:00:00', '2026-12-30'],  // Nov 30 then Dec 30
            'feb 29 leap' => ['2028-02-29 10:00:00', '2028-04-29'],
            'feb 28' => ['2026-02-28 10:00:00', '2026-04-28'],
        ];
    }

    #[DataProvider('monthEndProvider')]
    public function test_renewal_never_overflows_into_the_next_month(string $now, string $expected): void
    {
        Carbon::setTestNow($now);

        $this->renewTwice();

        $sub = Subscription::withoutGlobalScope('school')->where('school_id', $this->school->id)->where('status', 'active')->firstOrFail();
        $this->assertSame($expected, $sub->expires_at->toDateString());
    }

    public function test_single_month_purchase_on_the_31st_ends_on_the_last_day_of_the_shorter_month(): void
    {
        Carbon::setTestNow('2026-01-31 10:00:00');

        Sanctum::actingAs($this->owner);
        $id = $this->request(['months' => 1])->assertCreated()->json('id');
        $this->asAdmin();
        $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'UTR'])->assertOk();

        $sub = Subscription::withoutGlobalScope('school')->where('school_id', $this->school->id)->where('status', 'active')->firstOrFail();
        $this->assertSame('2026-02-28', $sub->expires_at->toDateString());
    }

    private function renewTwice(): void
    {
        foreach ([1, 2] as $round) {
            Sanctum::actingAs($this->owner);
            $id = $this->request(['months' => 1])->assertCreated()->json('id');
            $this->asAdmin();
            $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => "UTR{$round}"])->assertOk();
        }
    }

    public function test_admin_lists_and_voids_invoices(): void
    {
        Sanctum::actingAs($this->owner);
        $id = $this->request()->json('id');

        $this->asAdmin();
        $this->getJson('/api/admin/billing/invoices?status=issued')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson("/api/admin/billing/invoices/{$id}/void", ['reason' => 'Duplicate'])
            ->assertOk()->assertJsonPath('status', 'void')->assertJsonPath('voidReason', 'Duplicate');
        $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'late'])->assertUnprocessable();
        $this->assertSame(0, AppNotification::where('type', 'billing.paid')->count());
    }

    public function test_school_staff_cannot_settle_via_their_own_payments_endpoints(): void
    {
        Sanctum::actingAs($this->owner);
        $id = $this->request()->json('id');

        // The schools' own payments ledger is a different table: this id is not theirs to mark.
        $this->postJson("/api/payments/{$id}/mark-paid")->assertNotFound();
        $this->assertDatabaseHas('platform_invoices', ['id' => $id, 'status' => 'issued']);
    }
}
