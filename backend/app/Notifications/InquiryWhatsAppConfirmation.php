<?php

namespace App\Notifications;

use App\Messaging\Message;
use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * WhatsApp confirmation to someone who enquired and opted in (DIQ-1004).
 * Carries the school's chat link, not the review link: that stays
 * email-only, which is what ties a review to whoever enquired (DIQ-407).
 */
class InquiryWhatsAppConfirmation extends Notification
{
    use Queueable;

    public function __construct(public readonly Inquiry $inquiry) {}

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): Message
    {
        $school = $this->inquiry->school;
        $contact = $school?->whatsapp ?: $school?->phone;

        return new Message('enquiry_confirmation', [
            'name' => $this->inquiry->name,
            'school' => $school?->name ?? 'The driving school',
            'chat_link' => ($contact ? NewLeadNotification::whatsappUrl($contact) : null)
                ?? config('app.frontend_url').'/school/'.$school?->slug,
        ], 'Inquiry', $this->inquiry->id, $this->inquiry->school_id);
    }
}
