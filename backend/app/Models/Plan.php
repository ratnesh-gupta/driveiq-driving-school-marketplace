<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'price_monthly',
        'features',
        'ranking_boost',
        'is_sponsored',
        'homepage_featured',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price_monthly' => 'integer',
            'ranking_boost' => 'integer',
            'is_sponsored' => 'boolean',
            'homepage_featured' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Ranking boost as 0.0–1.0 for scoring. */
    public function rankingBoostScore(): float
    {
        return min(max($this->ranking_boost, 0), 100) / 100.0;
    }
}
