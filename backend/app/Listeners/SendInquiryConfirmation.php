<?php

namespace App\Listeners;

use App\Events\InquiryCreated;
use App\Models\SchoolSetting;
use App\Notifications\InquiryConfirmation;
use App\Notifications\InquiryWhatsAppConfirmation;
use App\Services\ReviewEligibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Confirms an enquiry to the person who made it: by email with their one-time
 * review link (DIQ-407), and on WhatsApp when they opted in on the form and
 * the school uses WhatsApp (DIQ-1004).
 */
class SendInquiryConfirmation implements ShouldQueue
{
    public function __construct(private ReviewEligibilityService $eligibility) {}

    public function handle(InquiryCreated $event): void
    {
        $inquiry = $event->inquiry;

        if ($inquiry->whatsapp_opt_in_at && $inquiry->school_id
            && (SchoolSetting::forSchool($inquiry->school_id)['notifications']['whatsapp'] ?? false)) {
            Notification::route('whatsapp', $inquiry->phone)
                ->notify(new InquiryWhatsAppConfirmation($inquiry->loadMissing('school')));
        }

        // The link is only ever delivered by email, which is what ties the
        // review to someone who actually submitted this enquiry.
        if (! $inquiry->email) {
            return;
        }

        $token = $this->eligibility->issueInquiryToken($inquiry);

        Notification::route('mail', $inquiry->email)->notify(
            new InquiryConfirmation($inquiry->loadMissing('school'), $this->eligibility->reviewUrl($token))
        );
    }
}
