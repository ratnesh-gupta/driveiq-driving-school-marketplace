<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Learner;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Who may review a school (DIQ-407, PBAC "Reviews: Create = Learner").
 *
 * Path A: a learner enrolled at the school, from their account.
 * Path B: someone who enquired, through the one-time link emailed with their
 *         inquiry confirmation (no account needed). See issueInquiryToken().
 */
class ReviewEligibilityService
{
    public const INQUIRY_LINK_TTL_DAYS = 90;

    /**
     * Path A.
     *
     * @return array{eligible: bool, source: ?string, learner_id: ?int, message: ?string}
     */
    public function check(User $user, int $schoolId): array
    {
        $learner = $user->isLearner()
            ? Learner::withoutGlobalScope('school')
                ->where('user_id', $user->id)
                ->where('school_id', $schoolId)
                ->first()
            : null;

        if (! $learner) {
            return $this->deny('Only learners enrolled with this school can review it from their account. If you enquired, use the review link from your enquiry confirmation email.');
        }

        if (Review::withoutGlobalScope('school')->where('learner_id', $learner->id)->exists()) {
            return $this->deny('You have already reviewed this school.');
        }

        return ['eligible' => true, 'source' => 'learner', 'learner_id' => $learner->id, 'message' => null];
    }

    /** Path B: create a fresh single-use review token for an inquiry; returns the plain token. */
    public function issueInquiryToken(Inquiry $inquiry): string
    {
        $token = Str::random(48);

        $inquiry->forceFill([
            'review_token_hash' => hash('sha256', $token),
            'review_token_expires_at' => now()->addDays(self::INQUIRY_LINK_TTL_DAYS),
        ])->save();

        return $token;
    }

    /** Path B: the inquiry a still-valid, unused review token belongs to. */
    public function inquiryForToken(string $token): ?Inquiry
    {
        $inquiry = Inquiry::withoutGlobalScope('school')
            ->with('school:id,name,slug')
            ->where('review_token_hash', hash('sha256', $token))
            ->where('review_token_expires_at', '>', now())
            ->first();

        if (! $inquiry || Review::withoutGlobalScope('school')->where('inquiry_id', $inquiry->id)->exists()) {
            return null;
        }

        return $inquiry;
    }

    public function reviewUrl(string $token): string
    {
        return config('app.frontend_url').'/review?token='.urlencode($token);
    }

    private function deny(string $message): array
    {
        return ['eligible' => false, 'source' => null, 'learner_id' => null, 'message' => $message];
    }
}
