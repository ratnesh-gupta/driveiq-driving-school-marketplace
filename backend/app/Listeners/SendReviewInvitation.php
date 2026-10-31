<?php

namespace App\Listeners;

use App\Events\InquiryResponded;
use App\Models\Inquiry;
use App\Models\Review;
use App\Notifications\ReviewInvitation;
use App\Services\ReviewEligibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * BSEC-08: the one-time review link goes out only after the school itself
 * answered the lead, and at most once per (email, school), so typing an
 * address into the form is not enough to obtain a "verified" review slot.
 */
class SendReviewInvitation implements ShouldQueue
{
    public function __construct(private ReviewEligibilityService $eligibility) {}

    public function handle(InquiryResponded $event): void
    {
        $inquiry = Inquiry::withoutGlobalScope('school')->with('school')->find($event->inquiry->id);

        if (! $inquiry || ! $inquiry->email || $inquiry->review_invited_at || ! $inquiry->first_responded_at) {
            return;
        }

        $email = mb_strtolower(trim($inquiry->email));

        $alreadyInvited = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $inquiry->school_id)
            ->where('id', '!=', $inquiry->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where(fn ($q) => $q->whereNotNull('review_invited_at')->orWhereNotNull('review_token_hash'))
            ->exists()
            || Review::withoutGlobalScope('school')
                ->where('school_id', $inquiry->school_id)
                ->whereIn('inquiry_id', Inquiry::withoutGlobalScope('school')
                    ->where('school_id', $inquiry->school_id)->whereRaw('LOWER(email) = ?', [$email])->select('id'))
                ->exists();

        if ($alreadyInvited) {
            return;
        }

        $token = $this->eligibility->issueInquiryToken($inquiry);
        $inquiry->forceFill(['review_invited_at' => now()])->save();

        Notification::route('mail', $inquiry->email)->notify(
            new ReviewInvitation($inquiry, $this->eligibility->reviewUrl($token))
        );
    }
}
