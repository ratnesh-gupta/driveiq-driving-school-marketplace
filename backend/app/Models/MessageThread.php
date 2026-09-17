<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThread extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'participant_a_id',
        'participant_b_id',
        'subject',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function participantA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_a_id');
    }

    public function participantB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_b_id');
    }

    public function otherParticipantId(int $userId): int
    {
        return (int) $this->participant_a_id === $userId
            ? (int) $this->participant_b_id
            : (int) $this->participant_a_id;
    }

    public function involves(int $userId): bool
    {
        return (int) $this->participant_a_id === $userId
            || (int) $this->participant_b_id === $userId;
    }
}
