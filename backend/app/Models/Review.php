<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'inquiry_id',
        'eligibility_source',
        'author_name',
        'rating',
        'content',
        'approved',
        'report_count',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'approved' => 'boolean',
            'report_count' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }
}
