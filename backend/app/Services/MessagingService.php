<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Find or create a 1:1 thread between two users within a school.
     */
    public function findOrCreateThread(int $schoolId, int $userA, int $userB, ?string $subject = null): MessageThread
    {
        if ($userA === $userB) {
            throw ValidationException::withMessages(['receiverId' => 'Cannot message yourself.']);
        }

        [$a, $b] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        return MessageThread::withoutGlobalScope('school')->firstOrCreate(
            [
                'school_id' => $schoolId,
                'participant_a_id' => $a,
                'participant_b_id' => $b,
            ],
            [
                'subject' => $subject,
                'last_message_at' => null,
            ]
        );
    }

    public function send(User $sender, int $receiverId, string $body, ?int $schoolId = null, ?string $subject = null): Message
    {
        $receiver = User::find($receiverId);
        if (! $receiver) {
            throw ValidationException::withMessages(['receiverId' => 'Receiver not found.']);
        }

        $resolvedSchoolId = $this->resolveSchoolId($sender, $receiver, $schoolId);
        $this->assertCanMessage($sender, $receiver, $resolvedSchoolId);

        return DB::transaction(function () use ($sender, $receiver, $body, $resolvedSchoolId, $subject) {
            $thread = $this->findOrCreateThread(
                $resolvedSchoolId,
                (int) $sender->id,
                (int) $receiver->id,
                $subject
            );

            $message = Message::withoutGlobalScope('school')->create([
                'school_id' => $resolvedSchoolId,
                'thread_id' => $thread->id,
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'body' => $body,
            ]);

            $thread->update(['last_message_at' => now()]);

            $this->notifications->notify(
                $receiver,
                'new_message',
                'New message from '.$sender->name,
                mb_substr($body, 0, 120),
                [
                    'threadId' => $thread->id,
                    'messageId' => $message->id,
                    'senderId' => $sender->id,
                ],
                $resolvedSchoolId
            );

            return $message;
        });
    }

    public function unreadCount(int $userId): int
    {
        return Message::withoutGlobalScope('school')
            ->where('receiver_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markThreadRead(MessageThread $thread, int $userId): int
    {
        return Message::withoutGlobalScope('school')
            ->where('thread_id', $thread->id)
            ->where('receiver_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function resolveSchoolId(User $sender, User $receiver, ?int $schoolId): int
    {
        if ($schoolId) {
            return $schoolId;
        }

        if ($sender->school_id && (int) $sender->school_id === (int) $receiver->school_id) {
            return (int) $sender->school_id;
        }

        if ($sender->isAdmin() && $receiver->school_id) {
            return (int) $receiver->school_id;
        }

        if ($sender->school_id) {
            return (int) $sender->school_id;
        }

        throw ValidationException::withMessages(['schoolId' => 'Unable to resolve school for conversation.']);
    }

    private function assertCanMessage(User $sender, User $receiver, int $schoolId): void
    {
        if ($sender->isAdmin()) {
            return;
        }

        // Both must belong to the same school (or one is school owner of that school)
        $senderOk = (int) $sender->school_id === $schoolId;
        $receiverOk = (int) $receiver->school_id === $schoolId;

        if (! $senderOk || ! $receiverOk) {
            throw ValidationException::withMessages([
                'receiverId' => 'Messaging is only allowed within the same school.',
            ]);
        }

        // Allowed role pairs: school↔instructor, school↔learner, instructor↔learner
        $roles = collect([$sender->role, $receiver->role])->sort()->values()->all();
        $allowed = [
            ['instructor', 'school'],
            ['learner', 'school'],
            ['instructor', 'learner'],
            ['school', 'school'], // staff-to-staff
            ['instructor', 'instructor'],
            ['learner', 'learner'],
        ];

        $ok = false;
        foreach ($allowed as $pair) {
            if ($roles === $pair) {
                $ok = true;
                break;
            }
        }

        if (! $ok) {
            throw ValidationException::withMessages([
                'receiverId' => 'This role combination cannot message each other.',
            ]);
        }
    }
}
