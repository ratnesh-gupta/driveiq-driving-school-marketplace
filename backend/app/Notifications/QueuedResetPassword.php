<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Password reset mail, queued so forgot-password answers in constant time (BSEC-23). */
class QueuedResetPassword extends ResetPassword implements ShouldQueue {}
