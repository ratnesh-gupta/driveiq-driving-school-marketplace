<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Private channels are school-scoped. A user may only join their own user
| channel, or a school channel matching their school_id (admins allowed).
|
*/

Broadcast::channel('user.{userId}', function (User $user, int $userId): bool {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('school.{schoolId}', function (User $user, int $schoolId): bool {
    if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
        return true;
    }

    return (int) $user->school_id === (int) $schoolId;
});
