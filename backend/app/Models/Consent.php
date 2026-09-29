<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Consent extends Model
{
    public const PURPOSES = ['terms', 'privacy', 'processing', 'role_portal', 'cookies_optional'];

    /** Purposes that may be recorded without an account (cookie banner). */
    public const ANONYMOUS_PURPOSES = ['cookies_optional'];

    protected $fillable = [
        'user_id', 'device_id', 'purpose', 'role', 'version', 'granted_at', 'withdrawn_at', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('withdrawn_at');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'purpose' => $this->purpose,
            'role' => $this->role,
            'version' => $this->version,
            'grantedAt' => $this->granted_at?->toISOString(),
            'withdrawnAt' => $this->withdrawn_at?->toISOString(),
        ];
    }
}
