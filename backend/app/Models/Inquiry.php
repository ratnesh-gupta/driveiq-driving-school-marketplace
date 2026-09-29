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

    /**
     * Lead lifecycle (DIQ-702). "pending" is shown as "New".
     * pending → contacted → follow_up → interested → converted | lost
     */
    public const STATUSES = ['pending', 'contacted', 'follow_up', 'interested', 'converted', 'lost'];

    /** Leads still being worked. */
    public const OPEN_STATUSES = ['pending', 'contacted', 'follow_up', 'interested'];

    /** Statuses that mean the school has responded to the lead. */
    public const RESPONDED_STATUSES = ['contacted', 'follow_up', 'interested', 'converted', 'lost'];

    protected $fillable = [
        'school_id', 'user_id', 'name', 'phone', 'email', 'vehicle_type', 'area',
        'preferred_timing', 'channel', 'message', 'status', 'lost_reason',
        'review_token_hash', 'review_token_expires_at',
        'first_responded_at', 'response_seconds', 'next_follow_up_at',
    ];

    protected $hidden = ['review_token_hash'];

    protected function casts(): array
    {
        return [
            'review_token_expires_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'response_seconds' => 'integer',
            'reminder_sent_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    /**
     * Record the school's first response (status change, note or conversion).
     * Only the first call has an effect (DIQ-703).
     */
    public function markResponded(): bool
    {
        if ($this->first_responded_at !== null) {
            return false;
        }

        $now = now();
        $this->forceFill([
            'first_responded_at' => $now,
            'response_seconds' => max(0, (int) $this->created_at?->diffInSeconds($now)),
        ])->save();

        return true;
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
