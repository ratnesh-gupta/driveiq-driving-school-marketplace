<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Learner extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'user_id',
        'converted_from_inquiry_id',
        'name',
        'mobile',
        'email',
        'gender',
        'dob',
        'address',
        'emergency_contact',
        'vehicle_type',
        'package_id',
        'start_date',
        'expected_completion_date',
        'assigned_instructor_id',
        'assigned_vehicle_id',
        'learner_license_number',
        'license_issue_date',
        'license_expiry_date',
        'permanent_license_status',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'start_date' => 'date',
            'expected_completion_date' => 'date',
            'license_issue_date' => 'date',
            'license_expiry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class, 'converted_from_inquiry_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(DrivePackage::class, 'package_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'assigned_instructor_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'assigned_vehicle_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LearnerDocument::class);
    }

    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(LearnerAssignmentHistory::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
