<?php

namespace App\Models\Traits;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSchool
{
    /**
     * Automatically scope queries to the authenticated user's school.
     * Platform admins (no school_id) see all records.
     * Public/unauthenticated requests are not filtered.
     */
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $builder): void {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            // Platform admins retain full visibility.
            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return;
            }

            if ($user->school_id) {
                $builder->where($builder->getModel()->getTable().'.school_id', $user->school_id);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
