<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Sent when a school creates a portal login for a trainer (DIQ-907). The
 * account starts with an unusable random password; this link lets the
 * trainer choose their own, through the normal password-reset page.
 */
class StaffLoginInvite extends Notification
{
    use Queueable;

    public function __construct(public readonly School $school) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function setPasswordUrl(object $notifiable): string
    {
        $token = Password::broker()->createToken($notifiable);

        return config('app.frontend_url').'/auth/reset-password?token='.urlencode($token)
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your {$this->school->name} trainer login on DriveIQ")
            ->line("{$this->school->name} has created a DriveIQ trainer login for you.")
            ->line('Use it to see your sessions, mark attendance and update learner progress.')
            ->action('Set your password', $this->setPasswordUrl($notifiable))
            ->line('This link expires in '.config('auth.passwords.users.expire', 60).' minutes. Ask your school to resend it if it runs out.');
    }
}
