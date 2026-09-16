<?php

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AppNotification $notification)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.'.$this->notification->user_id),
        ];

        if ($this->notification->school_id) {
            $channels[] = new PrivateChannel('school.'.$this->notification->school_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'data' => $this->notification->data,
            'schoolId' => $this->notification->school_id,
            'readAt' => $this->notification->read_at?->toISOString(),
            'createdAt' => $this->notification->created_at?->toISOString(),
        ];
    }
}
