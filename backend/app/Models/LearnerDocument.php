<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerDocument extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'learner_id', 'school_id', 'type', 'file_path', 'file_name',
        'status', 'expiry_date', 'verified_by', 'verified_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
