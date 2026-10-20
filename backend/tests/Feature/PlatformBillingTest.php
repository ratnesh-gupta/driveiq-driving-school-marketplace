<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
        foreach ([1, 2] as $round) {
            Sanctum::actingAs($this->owner);
            $id = $this->request(['months' => 1])->assertCreated()->json('id');
            $this->asAdmin();
            $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => "UTR{$round}"])->assertOk();
        }

        $subs = Subscription::withoutGlobalScope('school')->where('school_id', $this->school->id)->where('status', 'active')->get();
        $this->assertCount(1, $subs);
        $this->assertEqualsWithDelta(now()->addMonths(2)->timestamp, $subs->first()->expires_at->timestamp, 5);
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
