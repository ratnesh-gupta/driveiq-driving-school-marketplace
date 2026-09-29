<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\PlatformInvoice;
use App\Models\School;
use App\Models\User;
use App\Support\SchoolAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual plan billing (DIQ-803): a school owner requests an invoice, pays by
 * UPI / bank transfer, and a platform admin records the payment, which
 * activates or extends the plan.
 */
class BillingService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private NotificationService $notifications,
    ) {}

    public function requestInvoice(School $school, string $planCode, int $months, ?string $gstin, User $requester): PlatformInvoice
    {
        $open = PlatformInvoice::where('school_id', $school->id)->where('status', 'issued')->first();
        if ($open) {
            throw ValidationException::withMessages([
                'planCode' => ["You already have an unpaid invoice ({$open->invoice_number}). Pay or cancel it first."],
            ]);
        }

        $plan = Plan::where('code', $planCode)->where('active', true)->firstOrFail();
        $subtotal = (int) $plan->price_monthly * $months;
        $rate = (float) config('billing.gst_rate', 18);
        $gst = (int) round($subtotal * $rate / 100);

        return DB::transaction(function () use ($school, $plan, $months, $gstin, $requester, $subtotal, $rate, $gst) {
            $invoice = PlatformInvoice::create([
                'school_id' => $school->id,
                'plan_id' => $plan->id,
                'plan_code' => $plan->code,
                'months' => $months,
                'billed_to_name' => $school->name,
                'billed_to_gstin' => $gstin ? strtoupper($gstin) : null,
                'seller' => config('billing.seller'),
                'subtotal' => $subtotal,
                'gst_rate' => $rate,
                'gst_amount' => $gst,
                'total' => $subtotal + $gst,
                'status' => 'issued',
                'issued_at' => now(),
                'due_at' => now()->addDays((int) config('billing.due_days', 7)),
                'requested_by' => $requester->id,
            ]);
            // Numbered from the id: unique and gap-free per issue order.
            $invoice->update(['invoice_number' => sprintf('DIQ-%d-%06d', $invoice->issued_at->year, $invoice->id)]);

            AuditLog::log('request_invoice', 'PlatformInvoice', $invoice->id, [], [
                'plan' => $plan->code, 'months' => $months, 'total' => $invoice->total,
            ], $school->id);

            return $invoice;
        });
    }

    public function recordPayment(PlatformInvoice $invoice, string $reference, ?Carbon $paidAt, User $admin): PlatformInvoice
    {
        $this->assertOpen($invoice);

        DB::transaction(function () use ($invoice, $reference, $paidAt, $admin) {
            $invoice->update([
                'status' => 'paid',
                'payment_reference' => $reference,
                'paid_at' => $paidAt ?? now(),
                'recorded_by' => $admin->id,
            ]);

            if ($invoice->school_id) {
                $this->subscriptions->extendOrAssign($invoice->school_id, $invoice->plan_code, $invoice->months, $admin->id);
            }

            AuditLog::log('record_payment', 'PlatformInvoice', $invoice->id, ['status' => 'issued'], [
                'status' => 'paid', 'reference' => $reference, 'total' => $invoice->total,
            ], $invoice->school_id);
        });

        if ($invoice->school_id) {
            $this->notifyOwners($invoice->school_id, 'billing.paid', 'Payment received — plan active', sprintf(
                'We received %s %s for invoice %s. Your %s plan is active.',
                $invoice->currency, number_format($invoice->total), $invoice->invoice_number, ucfirst($invoice->plan_code)
            ), ['invoiceId' => $invoice->id]);
        }

        return $invoice->fresh();
    }

    public function void(PlatformInvoice $invoice, ?string $reason, User $actor): PlatformInvoice
    {
        $this->assertOpen($invoice);

        $invoice->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => $reason]);
        AuditLog::log('void_invoice', 'PlatformInvoice', $invoice->id, ['status' => 'issued'], [
            'status' => 'void', 'reason' => $reason,
        ], $invoice->school_id);

        return $invoice->fresh();
    }

    /** In-app notice to the school's owner(s) only. */
    public function notifyOwners(int $schoolId, string $type, string $title, string $body, array $data = []): void
    {
        $access = app(SchoolAccess::class);
        $this->notifications->schoolStaff($schoolId)
            ->filter(fn (User $u) => $access->isOwner($u, $schoolId))
            ->each(fn (User $u) => $this->notifications->notify($u, $type, $title, $body, $data, $schoolId));
    }

    private function assertOpen(PlatformInvoice $invoice): void
    {
        if ($invoice->status !== 'issued') {
            throw ValidationException::withMessages(['status' => ["Invoice is already {$invoice->status}."]]);
        }
    }
}
