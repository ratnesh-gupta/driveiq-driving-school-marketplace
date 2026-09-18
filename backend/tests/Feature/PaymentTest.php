<?php

namespace Tests\Feature;

use App\Models\DrivePackage;
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

    private function seed(): array
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
        ['owner' => $owner, 'school' => $school, 'package' => $package, 'learner' => $learner] = $this->seed();
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
        ['owner' => $owner, 'school' => $school, 'package' => $package, 'learner' => $learner] = $this->seed();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/schools/'.$school->id.'/payments/package', [
            'learnerId' => $learner->id,
            'packageId' => $package->id,
            'method' => 'razorpay',
        ])->assertCreated()
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('gateway.provider', 'razorpay');

        $id = $created->json('payment.id');

        $this->postJson('/api/payments/'.$id.'/mark-paid', [
            'providerPaymentId' => 'pay_test_123',
        ])->assertOk()
            ->assertJsonPath('status', 'paid');
    }

    public function test_other_school_forbidden(): void
    {
        ['school' => $school] = $this->seed();
        $other = User::factory()->create(['role' => 'school', 'school_id' => 999]);
        Sanctum::actingAs($other);

        $this->getJson('/api/schools/'.$school->id.'/payments')->assertForbidden();
    }
}
