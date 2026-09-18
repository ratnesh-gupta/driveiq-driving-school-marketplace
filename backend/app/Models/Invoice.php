<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'learner_id', 'invoice_number', 'purpose',
        'package_id', 'plan_id', 'amount', 'currency', 'status',
        'issued_at', 'due_at', 'line_items', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'line_items' => 'array',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(DrivePackage::class, 'package_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
