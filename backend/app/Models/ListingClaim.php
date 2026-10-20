<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A claim link for an unclaimed listing (DIQ-1104). */
class ListingClaim extends Model
{
    protected $fillable = ['school_id', 'prospect_id', 'token_hash', 'expires_at', 'outreach_message_id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', self::hashToken($token))->first();
    }

    public function isUsable(): bool
    {
        return $this->claimed_at === null && $this->expires_at->isFuture()
            && $this->school?->listing_status === 'unclaimed';
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ListingClaimCode::class);
    }
}
