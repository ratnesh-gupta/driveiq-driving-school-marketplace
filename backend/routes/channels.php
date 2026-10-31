<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (BSEC-05 / DIQ-1404)
|--------------------------------------------------------------------------
|
| Private channel names (clients subscribe as "private-<name>"):
|   App.Models.User.{id}  Laravel-standard per-user channel: only that user.
|   school.{id}           School staff only (owner/manager = role "school" with
|                         that school_id) and platform admins. Instructors and
|                         learners of the school are refused: the payloads carry
|                         lead names and phone numbers.
| Auth endpoint: POST /api/broadcasting/auth with the Sanctum bearer token.
|
*/

Broadcast::channel('App.Models.User.{userId}', function (User $user, int $userId): bool {
    return (int) $user->id === $userId;
});

Broadcast::channel('school.{schoolId}', function (User $user, int $schoolId): bool {
    if ($user->isAdmin()) {
        return true;
    }

    return $user->isSchool() && (int) $user->school_id === $schoolId;
});
