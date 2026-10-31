<?php

namespace App\Services;

use App\Models\DrivePackage;
use App\Models\Invoice;
use App\Models\Learner;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /** Upper bound for one package price (rupees); anything above is a typo. */
    public const MAX_RUPEES = 10_000_000;

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

        // BSEC-21: money is whole paise internally; the rupee `amount` is kept for the API.
        $price = (float) $package->price;
        if ($price <= 0 || $price > self::MAX_RUPEES) {
            throw ValidationException::withMessages(['packageId' => 'This package has no valid price.']);
        }
        $amountPaise = (int) round($price * 100);
        $amount = (int) round($amountPaise / 100);

        return DB::transaction(function () use ($schoolId, $learnerId, $packageId, $package, $amount, $amountPaise, $actor, $method, $markPaid) {
            $invoice = Invoice::withoutGlobalScope('school')->create([
                'school_id' => $schoolId,
                'learner_id' => $learnerId,
                'invoice_number' => 'tmp-'.Str::uuid(), // replaced below once the id is known
                'purpose' => 'package',
                'package_id' => $packageId,
                'amount' => $amount,
                'amount_paise' => $amountPaise,
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
            // Numbered from the row id, so two purchases at once can never share a number.
            $invoice->update(['invoice_number' => sprintf('INV-%d-%06d', $schoolId, $invoice->id)]);

            $payment = Payment::withoutGlobalScope('school')->create([
                'school_id' => $schoolId,
                'invoice_id' => $invoice->id,
                'learner_id' => $learnerId,
                'payer_user_id' => $actor->id,
                'purpose' => 'package',
                'package_id' => $packageId,
                'amount' => $amount,
                'amount_paise' => $amountPaise,
                'currency' => 'INR',
                'method' => $method,
                'status' => 'pending',
                'provider' => 'manual',
                'receipt' => 'rcpt_'.Str::lower(Str::random(12)),
                'recorded_by' => $actor->id,
            ]);

            if ($markPaid) {
                $payment = $this->markPaid($payment, $actor);
            }

            return [
                'invoice' => $invoice->fresh(),
                'payment' => $payment->fresh(),
            ];
        });
    }

    /** Only a pending payment can be settled; paid and failed are final. */
    public function markPaid(Payment $payment, User $actor, ?string $providerPaymentId = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $providerPaymentId) {
            $payment = $this->lockPending($payment);

            // BSEC-21: a provider/UTR id can settle only one payment.
            if ($providerPaymentId !== null) {
                $providerPaymentId = trim($providerPaymentId);
                $taken = Payment::withoutGlobalScope('school')
                    ->where('provider', $payment->provider)
                    ->where('provider_payment_id', $providerPaymentId)
                    ->where('id', '!=', $payment->id)
                    ->exists();
                if ($taken) {
                    throw ValidationException::withMessages(['providerPaymentId' => 'This payment reference was already used.']);
                }
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
        });
    }

    public function markFailed(Payment $payment, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($payment, $notes) {
            $payment = $this->lockPending($payment);

            $payment->update([
                'status' => 'failed',
                'notes' => $notes ?? $payment->notes,
            ]);

            // A failed payment must not leave an open invoice behind (BSEC-21).
            if ($payment->invoice_id) {
                Invoice::withoutGlobalScope('school')
                    ->where('id', $payment->invoice_id)
                    ->where('status', 'issued')
                    ->update(['status' => 'void']);
            }

            return $payment->fresh();
        });
    }

    private function lockPending(Payment $payment): Payment
    {
        $locked = Payment::withoutGlobalScope('school')->lockForUpdate()->findOrFail($payment->id);

        if ($locked->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => "This payment is already {$locked->status}.",
            ]);
        }

        return $locked;
    }
}
