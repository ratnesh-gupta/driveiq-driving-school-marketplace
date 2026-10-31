<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DrivePackage;
use App\Models\Learner;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** BSEC-17 audit trail hardening, BSEC-20 / BSEC-21 money-flow guards. */
class MoneyFlowGuardsTest extends TestCase
{
    use RefreshDatabase;

    /** Run a statement that must be rejected by PostgreSQL, keeping the test transaction usable. */
    private function assertDbRejects(callable $statement, string $messagePart): void
    {
        try {
            DB::transaction($statement);
            $this->fail('Expected the database to reject the statement.');
        } catch (QueryException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    /** @return array{User, School} */
    private function schoolWithOwner(string $slug): array
    {
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create(['name' => ucfirst($slug), 'slug' => $slug, 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);
        SchoolAdmin::create(['school_id' => $school->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);

        return [$owner->refresh(), $school];
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function invoiceFor(User $owner, School $school, int $months = 1): int
    {
        Sanctum::actingAs($owner);

        return $this->postJson("/api/schools/{$school->id}/billing/invoices", ['planCode' => 'featured', 'months' => $months])
            ->assertCreated()->json('id');
    }

    // --- BSEC-17 audit_logs ---------------------------------------------------

    public function test_audit_attribution_cannot_be_blanked_while_the_user_or_school_exists(): void
    {
        [$owner, $school] = $this->schoolWithOwner('aud-a');
        $log = AuditLog::create(['user_id' => $owner->id, 'school_id' => $school->id, 'model_type' => 'X', 'action' => 'a']);

        $this->assertDbRejects(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['user_id' => null]), 'append-only');
        $this->assertDbRejects(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['school_id' => null]), 'append-only');
        $this->assertDbRejects(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'b']), 'append-only');
        $this->assertDbRejects(fn () => DB::table('audit_logs')->where('id', $log->id)->delete(), 'append-only');
        $this->assertSame($owner->id, DB::table('audit_logs')->find($log->id)->user_id);
    }

    public function test_deleting_a_user_still_clears_attribution_through_the_foreign_key(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $log = AuditLog::create(['user_id' => $user->id, 'model_type' => 'X', 'action' => 'a']);

        DB::table('users')->where('id', $user->id)->delete();

        $row = DB::table('audit_logs')->find($log->id);
        $this->assertNotNull($row);
        $this->assertNull($row->user_id);
    }

    public function test_audit_log_cannot_be_truncated_even_with_replication_role_bypass(): void
    {
        $this->assertDbRejects(fn () => DB::statement('TRUNCATE audit_logs'), 'TRUNCATE is not allowed');

        // Skipping ordinary triggers needs superuser; ENABLE ALWAYS must still hold.
        try {
            DB::transaction(function () {
                DB::statement('SET LOCAL session_replication_role = replica');
                DB::table('audit_logs')->insert(['model_type' => 'X', 'action' => 'a', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('audit_logs')->delete();
            });
            $this->fail('delete went through under replica role');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'permission denied')) {
                $this->markTestIncomplete('Test DB role is not a superuser; replica-role bypass not exercised.');
            }
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_money_and_document_actions_are_audited_with_the_school(): void
    {
        [, $schoolA] = $this->schoolWithOwner('aud-b');
        $admin = $this->admin();

        $this->postJson('/api/admin/subscriptions', ['schoolId' => $schoolA->id, 'planCode' => 'featured', 'months' => 1])->assertCreated();
        $this->postJson("/api/admin/subscriptions/{$schoolA->id}/cancel");
        $assign = AuditLog::where('action', 'assign_subscription')->sole();
        $this->assertSame($schoolA->id, $assign->school_id);
        $this->assertSame($admin->id, $assign->user_id);
    }

    // --- BSEC-20 platform billing ---------------------------------------------

    public function test_paid_and_void_platform_invoices_are_final_and_content_is_immutable(): void
    {
        [$owner, $school] = $this->schoolWithOwner('inv-a');
        $id = $this->invoiceFor($owner, $school);

        $edit = fn (array $values) => fn () => DB::table('platform_invoices')->where('id', $id)->update($values);
        $this->assertDbRejects($edit(['total' => 1]), 'cannot be edited');
        $this->assertDbRejects($edit(['subtotal' => 1, 'total' => 1]), 'cannot be edited');
        $this->assertDbRejects($edit(['billed_to_name' => 'Someone else']), 'cannot be edited');
        $this->assertDbRejects($edit(['school_id' => School::create(['name' => 'Z', 'slug' => 'zz'])->id]), 'cannot be edited');
        $this->assertDbRejects($edit(['status' => 'paid']), 'needs a payment reference');
        $this->assertDbRejects(fn () => DB::table('platform_invoices')->where('id', $id)->delete(), 'cannot be deleted');

        $this->admin();
        $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'UTR-AAA-1'])->assertOk();

        $this->assertDbRejects($edit(['status' => 'issued']), 'invalid status change');
        $this->assertDbRejects($edit(['status' => 'void', 'voided_at' => now()]), 'invalid status change');
        $this->assertDbRejects($edit(['payment_reference' => 'edited']), 'final');
        $this->assertDbRejects($edit(['paid_at' => now()->subYear()]), 'final');
    }

    public function test_second_open_invoice_for_a_school_is_rejected_by_the_database(): void
    {
        [$owner, $school] = $this->schoolWithOwner('inv-b');
        $this->invoiceFor($owner, $school);
        $row = (array) DB::table('platform_invoices')->first();
        unset($row['id']);
        $row['invoice_number'] = 'DIQ-X-1';

        $this->assertDbRejects(fn () => DB::table('platform_invoices')->insert($row), 'platform_invoices_one_open_per_school');
        $row['total'] = $row['total'] + 1; // inconsistent totals
        $row['status'] = 'void';
        $this->assertDbRejects(fn () => DB::table('platform_invoices')->insert($row), 'platform_invoices_totals_check');
    }

    public function test_record_payment_is_idempotent_and_never_extends_twice(): void
    {
        [$owner, $school] = $this->schoolWithOwner('inv-c');
        $id = $this->invoiceFor($owner, $school, 3);
        $this->admin();

        $first = $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'UTR-IDEMP-1'])->assertOk();
        $expires = Subscription::where('school_id', $school->id)->where('status', 'active')->value('expires_at');
        $audits = AuditLog::where('action', 'record_payment')->count();

        // Same reference again (double click / retry): same invoice back, nothing re-applied.
        $replay = $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => ' utr-idemp-1 '])->assertOk();
        $this->assertSame($first->json('paidAt'), $replay->json('paidAt'));
        $this->assertEquals($expires, Subscription::where('school_id', $school->id)->where('status', 'active')->value('expires_at'));
        $this->assertSame($audits, AuditLog::where('action', 'record_payment')->count());

        // A different reference on a settled invoice is still refused.
        $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'OTHER'])->assertUnprocessable();
    }

    public function test_a_payment_reference_cannot_settle_two_invoices(): void
    {
        [$ownerA, $schoolA] = $this->schoolWithOwner('inv-d');
        [$ownerB, $schoolB] = $this->schoolWithOwner('inv-e');
        $a = $this->invoiceFor($ownerA, $schoolA);
        $b = $this->invoiceFor($ownerB, $schoolB);
        $this->admin();

        $this->postJson("/api/admin/billing/invoices/{$a}/record-payment", ['reference' => 'UTR-SAME'])->assertOk();
        $this->postJson("/api/admin/billing/invoices/{$b}/record-payment", ['reference' => 'utr-same'])
            ->assertUnprocessable()->assertJsonPath('errors.reference.0', 'This payment reference was already used on another invoice.');
        $this->assertSame('issued', DB::table('platform_invoices')->where('id', $b)->value('status'));
    }

    public function test_only_platform_admins_record_or_void_and_owners_cancel_only_their_own(): void
    {
        [$ownerA, $schoolA] = $this->schoolWithOwner('inv-f');
        [$ownerB] = $this->schoolWithOwner('inv-g');
        $id = $this->invoiceFor($ownerA, $schoolA);

        foreach ([$ownerA, $ownerB] as $user) {
            Sanctum::actingAs($user);
            $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'X'])->assertForbidden();
            $this->postJson("/api/admin/billing/invoices/{$id}/void")->assertForbidden();
        }
        // Another school's owner cannot cancel it.
        Sanctum::actingAs($ownerB);
        $this->postJson("/api/schools/{$schoolA->id}/billing/invoices/{$id}/cancel")->assertForbidden();

        $this->admin();
        $this->postJson("/api/admin/billing/invoices/{$id}/void", ['reason' => 'x'])->assertOk();
        $this->postJson("/api/admin/billing/invoices/{$id}/record-payment", ['reference' => 'LATE'])->assertUnprocessable();
        $this->postJson("/api/admin/billing/invoices/{$id}/void")->assertUnprocessable();
    }

    // --- BSEC-21 learner payments ------------------------------------------------

    /** @return array{User, School, Learner, DrivePackage} */
    private function payWorld(string $slug, float $price = 4999.5): array
    {
        [$owner, $school] = $this->schoolWithOwner($slug);
        $learner = Learner::withoutGlobalScope('school')->create(['school_id' => $school->id, 'name' => 'L', 'status' => 'active']);
        $package = DrivePackage::withoutGlobalScope('school')->create([
            'school_id' => $school->id, 'name' => 'P', 'price' => $price, 'sessions' => 10, 'vehicle_type' => 'car',
            'transmission' => 'manual', 'active' => true,
        ]);

        return [$owner, $school, $learner, $package];
    }

    private function buy(User $owner, School $school, Learner $learner, DrivePackage $package)
    {
        Sanctum::actingAs($owner);

        return $this->postJson("/api/schools/{$school->id}/payments/package", [
            'learnerId' => $learner->id, 'packageId' => $package->id, 'method' => 'cash',
        ]);
    }

    public function test_amounts_are_stored_in_paise_and_validated(): void
    {
        [$owner, $school, $learner, $package] = $this->payWorld('pay-a');

        $res = $this->buy($owner, $school, $learner, $package)->assertCreated();
        $res->assertJsonPath('payment.amountPaise', 499950);
        $payment = Payment::withoutGlobalScope('school')->find($res->json('payment.id'));
        $this->assertSame(499950, $payment->amount_paise);
        $this->assertSame(499950, (int) DB::table('invoices')->value('amount_paise'));
        $this->assertSame($school->id, AuditLog::where('action', 'purchase')->sole()->school_id);

        $package->update(['price' => 0]);
        $this->buy($owner, $school, $learner, $package)->assertUnprocessable();
        $package->update(['price' => 99999999]);
        $this->buy($owner, $school, $learner, $package)->assertUnprocessable();

        $this->assertDbRejects(fn () => DB::table('payments')->where('id', $payment->id)->update(['amount_paise' => 1]), 'cannot be edited');
        $row = (array) DB::table('payments')->where('id', $payment->id)->first();
        unset($row['id']);
        $this->assertDbRejects(fn () => DB::table('payments')->insert([...$row, 'status' => 'bogus', 'receipt' => 'x']), 'payments_status_check');
    }

    public function test_payment_transitions_are_guarded_and_final(): void
    {
        [$owner, $school, $learner, $package] = $this->payWorld('pay-b');
        $id = $this->buy($owner, $school, $learner, $package)->json('payment.id');

        $this->postJson("/api/payments/{$id}/mark-paid", ['providerPaymentId' => 'UTR1'])->assertOk();
        $this->postJson("/api/payments/{$id}/mark-paid")->assertUnprocessable();
        $this->postJson("/api/payments/{$id}/mark-failed")->assertUnprocessable();

        $this->assertDbRejects(fn () => DB::table('payments')->where('id', $id)->update(['status' => 'pending']), 'invalid status change');
        $this->assertDbRejects(fn () => DB::table('payments')->where('id', $id)->update(['paid_at' => now()->subYear()]), 'final');
        $this->assertDbRejects(fn () => DB::table('payments')->where('id', $id)->update(['amount' => 1]), 'cannot be edited');
        $this->assertDbRejects(fn () => DB::table('invoices')->where('id', DB::table('payments')->where('id', $id)->value('invoice_id'))->update(['status' => 'issued']), 'invalid status change');
        $this->assertDbRejects(fn () => DB::table('invoices')->update(['amount' => 1]), 'cannot be edited');

        $this->assertSame('mark_paid', AuditLog::where('action', 'mark_paid')->sole()->action);
        $this->assertSame($school->id, AuditLog::where('action', 'mark_paid')->sole()->school_id);
    }

    public function test_a_provider_payment_id_settles_only_one_payment(): void
    {
        [$owner, $school, $learner, $package] = $this->payWorld('pay-c');
        $one = $this->buy($owner, $school, $learner, $package)->json('payment.id');
        $two = $this->buy($owner, $school, $learner, $package)->json('payment.id');

        $this->postJson("/api/payments/{$one}/mark-paid", ['providerPaymentId' => 'UTR-DUP'])->assertOk();
        $this->postJson("/api/payments/{$two}/mark-paid", ['providerPaymentId' => 'UTR-DUP'])->assertUnprocessable();
        $this->assertSame('pending', DB::table('payments')->where('id', $two)->value('status'));
        $this->postJson("/api/payments/{$two}/mark-paid", ['providerPaymentId' => 'UTR-OK'])->assertOk();
    }

    public function test_failing_a_payment_voids_its_invoice(): void
    {
        [$owner, $school, $learner, $package] = $this->payWorld('pay-d');
        $res = $this->buy($owner, $school, $learner, $package);

        $this->postJson('/api/payments/'.$res->json('payment.id').'/mark-failed', ['notes' => 'bounced'])->assertOk();
        $this->assertSame('void', DB::table('invoices')->where('id', $res->json('invoice.id'))->value('status'));
    }

    public function test_other_schools_and_roles_cannot_settle_payments(): void
    {
        [$owner, $school, $learner, $package] = $this->payWorld('pay-e');
        [$intruder] = $this->payWorld('pay-f');
        $id = $this->buy($owner, $school, $learner, $package)->json('payment.id');

        Sanctum::actingAs($intruder);
        $this->postJson("/api/payments/{$id}/mark-paid")->assertForbidden();
        $this->postJson("/api/payments/{$id}/mark-failed")->assertForbidden();
        $this->getJson("/api/schools/{$school->id}/payments")->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'learner', 'school_id' => $school->id]));
        $this->postJson("/api/payments/{$id}/mark-paid")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/payments/{$id}/mark-paid")->assertUnauthorized();
        $this->assertSame('pending', DB::table('payments')->where('id', $id)->value('status'));
    }
}
