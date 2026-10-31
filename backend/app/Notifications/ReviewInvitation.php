<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One-time review link, sent after the school responded to the enquiry (DIQ-407, BSEC-08). */
class ReviewInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Inquiry $inquiry,
        public readonly string $reviewUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->inquiry->school?->name ?? 'the driving school';

        return (new MailMessage)
            ->subject("How was your experience with {$school}?")
            ->greeting("Hi {$this->inquiry->name},")
            ->line("{$school} has been in touch about your enquiry. Once you have spoken with them or trained there, you can leave an honest review.")
            ->line('This link works once and expires in 90 days.')
            ->action('Review '.$school, $this->reviewUrl);
    }
}
