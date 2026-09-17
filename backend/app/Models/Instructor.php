<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'user_id',
        'name',
        'mobile',
        'email',
        'gender',
        'dob',
        'address',
        'employee_id',
        'joining_date',
        'status',
        'employment_type',
        'license_number',
        'license_category',
        'license_expiry',
        'years_experience',
        'skills',
        'languages',
        'women_instructor',
        'public_visible',
        'photo_url',
        'rating_average',
        'rating_count',
        'total_learners_trained',
        'bio',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'joining_date' => 'date',
            'license_expiry' => 'date',
            'skills' => 'array',
            'languages' => 'array',
            'women_instructor' => 'boolean',
            'public_visible' => 'boolean',
            'rating_average' => 'float',
            'rating_count' => 'integer',
            'total_learners_trained' => 'integer',
            'years_experience' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(InstructorDocument::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublicVisible($query)
    {
        return $query->where('public_visible', true)->where('status', 'active');
    }
}
