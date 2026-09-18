<?php

namespace App\Services;

use App\Models\DrivePackage;
use App\Models\Invoice;
use App\Models\Learner;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function purchasePackage(
        int $schoolId,
        int $learnerId,
        int $packageId,
        User $actor,
        string $method = 'manual',
        bool $markPaid = false
    ): array {
        $learner = Learner::withoutGlobalScope('school')
            ->where('id', $learnerId)
            ->where('school_id', $schoolId)
            ->first();

        if (! $learner) {
            throw ValidationException::withMessages(['learnerId' => 'Learner not found.']);
        }

        $package = DrivePackage::withoutGlobalScope('school')
            ->where('id', $packageId)
            ->where('school_id', $schoolId)
            ->where('active', true)
            ->first();

        if (! $package) {
            throw ValidationException::withMessages(['packageId' => 'Package not available.']);
        }

        $amount = (int) round((float) $package->price);

        $invoice = Invoice::withoutGlobalScope('school')->create([
            'school_id' => $schoolId,
            'learner_id' => $learnerId,
            'invoice_number' => $this->nextInvoiceNumber($schoolId),
            'purpose' => 'package',
            'package_id' => $packageId,
            'amount' => $amount,
            'currency' => 'INR',
            'status' => 'issued',
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
            'line_items' => [[
                'description' => $package->name,
                'quantity' => 1,
                'unit_amount' => $amount,
                'sessions' => $package->sessions,
            ]],
        ]);

        $payment = Payment::withoutGlobalScope('school')->create([
            'school_id' => $schoolId,
            'invoice_id' => $invoice->id,
            'learner_id' => $learnerId,
            'payer_user_id' => $actor->id,
            'purpose' => 'package',
            'package_id' => $packageId,
            'amount' => $amount,
            'currency' => 'INR',
            'method' => $method,
            'status' => 'pending',
            'provider' => $method === 'razorpay' ? 'razorpay' : 'manual',
            'receipt' => 'rcpt_'.Str::lower(Str::random(12)),
            'recorded_by' => $actor->id,
        ]);

        $gateway = null;
        if ($method === 'razorpay') {
            $gateway = $this->buildRazorpayOrder($payment);
            $payment->update([
                'provider_order_id' => $gateway['order_id'],
                'meta' => $gateway,
            ]);
        }

        if ($markPaid || $method === 'cash' || $method === 'manual') {
            if ($markPaid || $method === 'cash') {
                $this->markPaid($payment, $actor);
            }
        }

        return [
            'invoice' => $invoice->fresh(),
            'payment' => $payment->fresh(),
            'gateway' => $gateway,
        ];
    }

    public function markPaid(Payment $payment, User $actor, ?string $providerPaymentId = null): Payment
    {
        if ($payment->status === 'paid') {
            return $payment;
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider_payment_id' => $providerPaymentId ?? $payment->provider_payment_id,
            'recorded_by' => $actor->id,
        ]);

        if ($payment->invoice_id) {
            Invoice::withoutGlobalScope('school')
                ->where('id', $payment->invoice_id)
                ->update(['status' => 'paid']);
        }

        // Attach package to learner when package purchase completes
        if ($payment->purpose === 'package' && $payment->learner_id && $payment->package_id) {
            Learner::withoutGlobalScope('school')
                ->where('id', $payment->learner_id)
                ->update([
                    'package_id' => $payment->package_id,
                    'start_date' => now()->toDateString(),
                ]);
        }

        return $payment->fresh();
    }

    public function markFailed(Payment $payment, ?string $notes = null): Payment
    {
        $payment->update([
            'status' => 'failed',
            'notes' => $notes ?? $payment->notes,
        ]);

        return $payment->fresh();
    }

    private function nextInvoiceNumber(int $schoolId): string
    {
        $seq = Invoice::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->count() + 1;

        return sprintf('INV-%d-%06d', $schoolId, $seq);
    }

    /**
     * Build Razorpay-compatible order payload.
     * When RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET are set, a real order can be created later.
     * MVP returns a deterministic stub for frontend integration.
     */
    private function buildRazorpayOrder(Payment $payment): array
    {
        $keyId = config('services.razorpay.key_id') ?: env('RAZORPAY_KEY_ID');
        $orderId = 'order_'.Str::lower(Str::random(14));

        return [
            'provider' => 'razorpay',
            'key_id' => $keyId ?: null,
            'order_id' => $orderId,
            'amount' => $payment->amount * 100, // paise
            'currency' => $payment->currency,
            'receipt' => $payment->receipt,
            'payment_id' => $payment->id,
            'mode' => $keyId ? 'live_ready' : 'stub',
        ];
    }
}
