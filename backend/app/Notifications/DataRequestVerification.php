<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** BSEC-13: proves the requester controls the e-mail address of a data-subject request. */
class DataRequestVerification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $verifyUrl, public readonly int $validHours) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your DriveQ data request')
            ->line('We received a request about your personal data. Confirm that it was you; we do nothing until you do.')
            ->action('Confirm my request', $this->verifyUrl)
            ->line("The link works once and expires in {$this->validHours} hours. If you did not ask for this, ignore this email.");
    }
}
