<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Transactional receipt for the person who enquired. No review link (that is
 * sent after the school responds, BSEC-08) and no phone number echoed back.
 */
class InquiryConfirmation extends Notification
{
    use Queueable;

    public function __construct(public readonly Inquiry $inquiry) {}

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
            ->line("We've passed your enquiry to {$school}. They will contact you on the number you gave.")
            ->line('If you did not make this enquiry, you can ignore this email; we will not contact you again.');
    }
}
