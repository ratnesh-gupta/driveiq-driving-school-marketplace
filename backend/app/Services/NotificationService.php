<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\AppNotification;
use App\Models\School;
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
     * Notify a school's staff: its owner and managers (role "school"), never
     * its learners or instructors, who share school_id but must not receive
     * lead details (DIQ-701). Deactivated accounts are skipped.
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
        return $this->schoolStaff($schoolId)->map(
            fn (User $user) => $this->notify($user, $type, $title, $body, $data, $schoolId)
        );
    }

    /**
     * Active staff of a school, including a legacy owner linked only through
     * schools.user_id, each once.
     *
     * @return Collection<int, User>
     */
    public function schoolStaff(int $schoolId): Collection
    {
        return User::query()
            ->where('role', 'school')
            ->whereNull('deactivated_at')
            ->where(fn ($q) => $q
                ->where('school_id', $schoolId)
                ->orWhereIn('id', School::where('id', $schoolId)->whereNotNull('user_id')->select('user_id')))
            ->orderBy('id')
            ->get();
    }
}
