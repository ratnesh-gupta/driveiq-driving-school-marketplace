<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'invoice_id', 'learner_id', 'payer_user_id',
        'purpose', 'package_id', 'plan_id', 'amount', 'currency',
        'method', 'status', 'provider', 'provider_order_id',
        'provider_payment_id', 'receipt', 'meta', 'paid_at',
        'recorded_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(DrivePackage::class, 'package_id');
    }
}
