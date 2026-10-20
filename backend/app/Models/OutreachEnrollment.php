<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One prospect's place in a campaign's sequence (DIQ-1105). */
class OutreachEnrollment extends Model
{
    protected $attributes = ['status' => 'active', 'next_step' => 0];

    protected $fillable = ['campaign_id', 'prospect_id', 'next_step', 'next_send_at', 'status', 'stop_reason'];

    protected function casts(): array
    {
        return ['next_send_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function stop(string $reason): void
    {
        $this->forceFill(['status' => 'stopped', 'stop_reason' => $reason, 'next_send_at' => null])->save();
    }
}
