<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create a notification for a single user and broadcast it.
     */
    public function notify(
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?int $schoolId = null
    ): AppNotification {
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'school_id' => $schoolId ?? $user->school_id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
        ]);

        event(new NotificationCreated($notification));

        return $notification;
    }

    /**
     * Notify all users belonging to a school (owners / staff linked via school_id).
     *
     * @return Collection<int, AppNotification>
     */
    public function notifySchool(
        int $schoolId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = []
    ): Collection {
        $users = User::query()
            ->where('school_id', $schoolId)
            ->get();

        return $users->map(function (User $user) use ($schoolId, $type, $title, $body, $data) {
            return $this->notify($user, $type, $title, $body, $data, $schoolId);
        });
    }
}
