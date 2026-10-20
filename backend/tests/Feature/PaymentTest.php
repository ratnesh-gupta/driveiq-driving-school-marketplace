<?php

namespace Tests\Feature;

use App\Models\DrivePackage;
use App\Models\Invoice;
use App\Models\Learner;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixtures(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-pay']);
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'name' => 'Pay School',
            'slug' => 'pay-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000014000',
            'user_id' => $owner->id,
        ]);
        $owner->update(['school_id' => $school->id]);

        $package = DrivePackage::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Car Basic',
            'price' => 5000,
            'sessions' => 15,
            'vehicle_type' => 'car',
            'transmission' => 'manual',
            'active' => true,
        ]);

        $learner = Learner::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Asha',
            'status' => 'active',
        ]);

        return compact('owner', 'school', 'package', 'learner');
    }

    public function test_package_purchase_and_mark_paid(): void
    {
        ['owner' => $owner, 'school' => $school, 'package' => $package, 'learner' => $learner] = $this->seedFixtures();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/schools/'.$school->id.'/payments/package', [
            'learnerId' => $learner->id,
            'packageId' => $package->id,
            'method' => 'cash',
            'markPaid' => true,
        ])->assertCreated()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.amount', 5000);

        $this->assertDatabaseHas('learners', [
            'id' => $learner->id,
            'package_id' => $package->id,
        ]);

        $this->getJson('/api/schools/'.$school->id.'/payments')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_pending_then_mark_paid(): void
    {
        ['owner' => $owner, 'school' => $school, 'package' => $package, 'learner' => $learner] = $this->seedFixtures();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/schools/'.$school->id.'/payments/package', [
            'learnerId' => $learner->id,
            'packageId' => $package->id,
            'method' => 'upi',
        ])->assertCreated()
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonMissingPath('gateway');

        $id = $created->json('payment.id');

        $this->postJson('/api/payments/'.$id.'/mark-paid', [
            'providerPaymentId' => 'UTR123',
        ])->assertOk()
            ->assertJsonPath('status', 'paid');
        $this->assertDatabaseHas('audit_logs', ['action' => 'mark_paid', 'model_id' => $id]);
    }

    public function test_cash_is_not_paid_unless_asked_and_razorpay_stub_is_gone(): void
    {
        ['owner' => $owner, 'school' => $school, 'package' => $package, 'learner' => $learner] = $this->seedFixtures();
        Sanctum::actingAs($owner);
        $url = '/api/schools/'.$school->id.'/payments/package';

        $this->postJson($url, ['learnerId' => $learner->id, 'packageId' => $package->id, 'method' => 'cash'])
            ->assertCreated()->assertJsonPath('payment.status', 'pending');
        $this->postJson($url, ['learnerId' => $learner->id, 'packageId' => $package->id, 'method' => 'razorpay'])
            ->assertUnprocessable()->assertJsonValidationErrors('method');
    }

    public function test_paid_and_failed_are_final(): void
    {
        ['owner' => $owner, 'school' => $school, 'package' => $package, 'learner' => $learner] = $this->seedFixtures();
        Sanctum::actingAs($owner);
        $url = '/api/schools/'.$school->id.'/payments/package';
        $body = ['learnerId' => $learner->id, 'packageId' => $package->id];

        $paid = $this->postJson($url, $body + ['markPaid' => true])->json('payment.id');
        $this->postJson("/api/payments/{$paid}/mark-failed")->assertUnprocessable();
        $this->postJson("/api/payments/{$paid}/mark-paid")->assertUnprocessable();

        $failed = $this->postJson($url, $body)->json('payment.id');
        $this->postJson("/api/payments/{$failed}/mark-failed")->assertOk()->assertJsonPath('status', 'failed');
        $this->postJson("/api/payments/{$failed}/mark-paid")->assertUnprocessable();
        $this->assertDatabaseHas('payments', ['id' => $failed, 'status' => 'failed']);
    }

    public function test_invoice_numbers_are_unique_per_row(): void
    {
        ['owner' => $owner, 'school' => $school, 'package' => $package, 'learner' => $learner] = $this->seedFixtures();
        Sanctum::actingAs($owner);
        $url = '/api/schools/'.$school->id.'/payments/package';
        $body = ['learnerId' => $learner->id, 'packageId' => $package->id];

        $first = $this->postJson($url, $body)->json('invoice');
        // Deleting a row used to make count()+1 hand out a number that already existed.
        Invoice::withoutGlobalScope('school')->where('id', '!=', $first['id'])->delete();
        $second = $this->postJson($url, $body)->assertCreated()->json('invoice');

        $this->assertSame(sprintf('INV-%d-%06d', $school->id, $second['id']), $second['invoiceNumber']);
        $this->assertNotSame($first['invoiceNumber'], $second['invoiceNumber']);
    }

    public function test_other_school_forbidden(): void
    {
        ['school' => $school] = $this->seedFixtures();
        $other = $this->otherSchoolUser();
        Sanctum::actingAs($other);

        $this->getJson('/api/schools/'.$school->id.'/payments')->assertForbidden();
    }
}
