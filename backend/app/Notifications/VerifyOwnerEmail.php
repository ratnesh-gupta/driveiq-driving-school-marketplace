<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * DIQ-1102: a school or trainer listing only goes live once its owner has
 * confirmed their email. The link is signed and expires after 7 days.
 */
class VerifyOwnerEmail extends Notification
{
    use Queueable;

    public const EXPIRES_DAYS = 7;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function verifyUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addDays(self::EXPIRES_DAYS), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email to go live on DriveIQ')
            ->greeting("Hi {$notifiable->name},")
            ->line('Confirm your email address so learners can find your listing on DriveIQ.')
            ->action('Confirm email', $this->verifyUrl($notifiable))
            ->line('The link works for '.self::EXPIRES_DAYS.' days. If you did not sign up, you can ignore this email.');
    }
}
