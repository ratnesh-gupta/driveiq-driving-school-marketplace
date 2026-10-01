<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One-time code sent to a listing's email or phone during a claim (DIQ-1104). */
class ListingClaimCode extends Model
{
    public const UPDATED_AT = null;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['listing_claim_id', 'channel', 'sent_to_masked', 'code_hash', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
