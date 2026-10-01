<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One outreach email (DIQ-1105). The address is kept masked plus a hash. */
class OutreachMessage extends Model
{
    protected $fillable = ['campaign_id', 'prospect_id', 'step', 'to_masked', 'to_hash', 'status', 'error', 'unsubscribe_hash', 'clicked_at'];

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
