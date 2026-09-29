<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSetting extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** A school's settings merged over the defaults. */
    public static function forSchool(int $schoolId): array
    {
        $saved = static::withoutGlobalScope('school')->where('school_id', $schoolId)->value('settings');

        return array_replace_recursive(static::defaults(), is_array($saved) ? $saved : (json_decode((string) $saved, true) ?: []));
    }

    public static function defaults(): array
    {
        return [
            'notifications' => [
                'email' => true,
                'sms' => false,
                'in_app' => true,
                'new_inquiry' => true,
                'new_review' => true,
                // Remind staff about a lead still unanswered after this many minutes; 0 = off (DIQ-705).
                'reminder_after_minutes' => 60,
            ],
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
            'lead_auto_assign' => false,
        ];
    }
}
