<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToSchool;

    protected $table = 'attendance';

    protected $fillable = [
        'schedule_id', 'school_id', 'learner_id', 'instructor_id',
        'status', 'marked_by', 'marked_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['marked_at' => 'datetime'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
