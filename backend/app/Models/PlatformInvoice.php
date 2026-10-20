<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformInvoice extends Model
{
    protected $fillable = [
        'invoice_number', 'school_id', 'plan_id', 'plan_code', 'months', 'billed_to_name', 'billed_to_gstin',
        'seller', 'subtotal', 'gst_rate', 'gst_amount', 'total', 'currency', 'status', 'issued_at', 'due_at',
        'payment_reference', 'paid_at', 'recorded_by', 'requested_by', 'voided_at', 'void_reason', 'provider_order_id',
    ];

    protected function casts(): array
    {
        return [
            'seller' => 'array',
            'gst_rate' => 'float',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function toApi(bool $withInstructions = false): array
    {
        $data = [
            'id' => $this->id,
            'invoiceNumber' => $this->invoice_number,
            'schoolId' => $this->school_id,
            'schoolName' => $this->billed_to_name,
            'billedToGstin' => $this->billed_to_gstin,
            'planCode' => $this->plan_code,
            'months' => $this->months,
            'subtotal' => $this->subtotal,
            'gstRate' => $this->gst_rate,
            'gstAmount' => $this->gst_amount,
            'total' => $this->total,
            'currency' => $this->currency,
            'status' => $this->status,
            'issuedAt' => $this->issued_at?->toISOString(),
            'dueAt' => $this->due_at?->toISOString(),
            'paymentReference' => $this->payment_reference,
            'paidAt' => $this->paid_at?->toISOString(),
            'voidedAt' => $this->voided_at?->toISOString(),
            'voidReason' => $this->void_reason,
            'seller' => $this->seller,
        ];

        if ($withInstructions && $this->status === 'issued') {
            $data['paymentInstructions'] = array_filter(config('billing.payment'));
        }

        return $data;
    }
}
