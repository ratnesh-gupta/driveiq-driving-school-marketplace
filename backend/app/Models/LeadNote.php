<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    use BelongsToSchool;

    protected $fillable = ['inquiry_id', 'school_id', 'user_id', 'body', 'follow_up_at'];

    protected function casts(): array
    {
        return ['follow_up_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
