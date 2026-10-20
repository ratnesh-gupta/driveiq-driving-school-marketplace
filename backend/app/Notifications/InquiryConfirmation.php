<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the person who enquired; carries their one-time review link (DIQ-407). */
class InquiryConfirmation extends Notification
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
            ->subject("Your enquiry to {$school} was sent")
            ->greeting("Hi {$this->inquiry->name},")
            ->line("We've passed your enquiry to {$school}. They'll contact you on {$this->inquiry->phone}.")
            ->line('Once you have spoken with them or trained there, you can leave an honest review. This link works once and expires in 90 days.')
            ->action('Review '.$school, $this->reviewUrl);
    }
}
