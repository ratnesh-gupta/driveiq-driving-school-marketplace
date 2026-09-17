<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessagingService $messaging,
    ) {}

    public function threads(Request $request): JsonResponse
    {
        $user = $request->user();

        $threads = MessageThread::withoutGlobalScope('school')
            ->with(['participantA:id,name,role', 'participantB:id,name,role'])
            ->where(function ($q) use ($user) {
                $q->where('participant_a_id', $user->id)
                    ->orWhere('participant_b_id', $user->id);
            })
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get()
            ->map(function (MessageThread $t) use ($user) {
                $otherId = $t->otherParticipantId((int) $user->id);
                $other = (int) $t->participant_a_id === $otherId ? $t->participantA : $t->participantB;

                $unread = Message::withoutGlobalScope('school')
                    ->where('thread_id', $t->id)
                    ->where('receiver_id', $user->id)
                    ->whereNull('read_at')
                    ->count();

                $last = Message::withoutGlobalScope('school')
                    ->where('thread_id', $t->id)
                    ->orderByDesc('id')
                    ->first();

                return [
                    'id' => $t->id,
                    'schoolId' => $t->school_id,
                    'subject' => $t->subject,
                    'otherUser' => $other ? [
                        'id' => $other->id,
                        'name' => $other->name,
                        'role' => $other->role,
                    ] : null,
                    'lastMessageAt' => $t->last_message_at?->toISOString(),
                    'lastMessagePreview' => $last ? mb_substr($last->body, 0, 100) : null,
                    'unreadCount' => $unread,
                ];
            });

        return response()->json($threads);
    }

    public function show(Request $request, int $threadId): JsonResponse
    {
        $user = $request->user();
        $thread = MessageThread::withoutGlobalScope('school')
            ->with(['participantA:id,name,role', 'participantB:id,name,role'])
            ->find($threadId);

        if (! $thread || ! $thread->involves((int) $user->id)) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        $this->messaging->markThreadRead($thread, (int) $user->id);

        $messages = Message::withoutGlobalScope('school')
            ->with('sender:id,name,role')
            ->where('thread_id', $threadId)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'senderId' => $m->sender_id,
                'senderName' => $m->sender?->name,
                'receiverId' => $m->receiver_id,
                'body' => $m->body,
                'readAt' => $m->read_at?->toISOString(),
                'createdAt' => $m->created_at?->toISOString(),
            ]);

        $otherId = $thread->otherParticipantId((int) $user->id);
        $other = (int) $thread->participant_a_id === $otherId
            ? $thread->participantA
            : $thread->participantB;

        return response()->json([
            'id' => $thread->id,
            'schoolId' => $thread->school_id,
            'subject' => $thread->subject,
            'otherUser' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'role' => $other->role,
            ] : null,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receiverId' => ['required', 'integer'],
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'schoolId' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:255'],
            'threadId' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        // If threadId provided, resolve receiver from thread
        if (! empty($data['threadId'])) {
            $thread = MessageThread::withoutGlobalScope('school')->find($data['threadId']);
            if (! $thread || ! $thread->involves((int) $user->id)) {
                return response()->json(['message' => 'Thread not found'], 404);
            }
            $data['receiverId'] = $thread->otherParticipantId((int) $user->id);
            $data['schoolId'] = $thread->school_id;
        }

        $message = $this->messaging->send(
            $user,
            (int) $data['receiverId'],
            $data['body'],
            isset($data['schoolId']) ? (int) $data['schoolId'] : null,
            $data['subject'] ?? null
        );

        return response()->json([
            'id' => $message->id,
            'threadId' => $message->thread_id,
            'senderId' => $message->sender_id,
            'receiverId' => $message->receiver_id,
            'body' => $message->body,
            'createdAt' => $message->created_at?->toISOString(),
        ], 201);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unreadCount' => $this->messaging->unreadCount((int) $request->user()->id),
        ]);
    }

    public function markRead(Request $request, int $threadId): JsonResponse
    {
        $user = $request->user();
        $thread = MessageThread::withoutGlobalScope('school')->find($threadId);

        if (! $thread || ! $thread->involves((int) $user->id)) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        $updated = $this->messaging->markThreadRead($thread, (int) $user->id);

        return response()->json(['markedRead' => $updated]);
    }
}
