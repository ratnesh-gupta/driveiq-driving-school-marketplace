<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emailed to someone a school owner invites as a manager (DIQ-403). */
class TeamInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public readonly School $school,
        public readonly string $token,
        public readonly string $inviterName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function acceptUrl(): string
    {
        return config('app.frontend_url').'/team/accept?token='.urlencode($this->token);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to manage {$this->school->name} on DriveIQ")
            ->line("{$this->inviterName} has invited you to join {$this->school->name} as a manager.")
            ->action('Accept invitation', $this->acceptUrl())
            ->line('This invitation expires in 7 days. If you were not expecting it, you can ignore this email.');
    }
}
