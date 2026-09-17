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

    public static function defaults(): array
    {
        return [
            'notifications' => [
                'email' => true,
                'sms' => false,
                'in_app' => true,
                'new_inquiry' => true,
                'new_review' => true,
            ],
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
            'lead_auto_assign' => false,
        ];
    }
}
