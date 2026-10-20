<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeaturedPlacement extends Model
{
    public const PLACEMENTS = ['search_top', 'homepage', 'locality'];

    protected $fillable = ['school_id', 'placement', 'locality_id', 'starts_at', 'ends_at', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'schoolId' => $this->school_id,
            'schoolName' => $this->school?->name,
            'placement' => $this->placement,
            'localityId' => $this->locality_id,
            'localityName' => $this->locality?->name,
            'startsAt' => $this->starts_at?->toISOString(),
            'endsAt' => $this->ends_at?->toISOString(),
            'live' => $this->starts_at?->lte(now()) && $this->ends_at?->gt(now()),
            'notes' => $this->notes,
        ];
    }
}
