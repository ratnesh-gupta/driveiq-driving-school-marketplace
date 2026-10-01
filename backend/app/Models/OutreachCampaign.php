<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An outreach email sequence to prospects (DIQ-1105). */
class OutreachCampaign extends Model
{
    public const STATUSES = ['draft', 'active', 'paused'];

    protected $attributes = ['status' => 'draft', 'audience' => 'school'];

    protected $fillable = ['name', 'audience', 'status', 'steps', 'created_by'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'started_at' => 'datetime'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(OutreachEnrollment::class, 'campaign_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class, 'campaign_id');
    }
}
