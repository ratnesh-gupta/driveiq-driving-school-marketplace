<?php

namespace App\Listeners;

use App\Events\InquiryCreated;
use App\Notifications\InquiryConfirmation;
use App\Services\ReviewEligibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Emails the enquirer a confirmation with their one-time review link (DIQ-407). */
class SendInquiryConfirmation implements ShouldQueue
{
    public function __construct(private ReviewEligibilityService $eligibility) {}

    public function handle(InquiryCreated $event): void
    {
        $inquiry = $event->inquiry;

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
