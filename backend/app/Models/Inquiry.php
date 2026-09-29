<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'school_id', 'name', 'phone', 'email', 'vehicle_type', 'area',
        'preferred_timing', 'channel', 'message', 'status',
        'review_token_hash', 'review_token_expires_at',
    ];

    protected $hidden = ['review_token_hash'];

    protected function casts(): array
    {
        return ['review_token_expires_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->orderByDesc('created_at');
    }
}
