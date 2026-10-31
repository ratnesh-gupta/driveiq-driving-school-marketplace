<?php

namespace App\Listeners;

use App\Events\InquiryCreated;
use App\Models\Inquiry;
use App\Notifications\InquiryConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Receipt for the person who enquired (BSEC-03). The form proves control of
 * neither the phone number nor the email address, so:
 *  - nothing goes to WhatsApp/SMS from the form (the opt-in box is recorded as
 *    intent only; real consent needs a verified number);
 *  - the email is one plain transactional receipt, at most once per address per
 *    day and at most MAX_PER_SCHOOL_HOUR per school per hour, with no marketing
 *    and no review link (that follows the school's response, BSEC-08).
 */
class SendInquiryConfirmation implements ShouldQueue
{
    public const MAX_PER_SCHOOL_HOUR = 30;

    public function handle(InquiryCreated $event): void
    {
        $inquiry = $event->inquiry;

        if (! $inquiry->email) {
            return;
        }

        // A repeat from the same number to the same school within a day: the lead is
        // stored and staff are alerted, but the enquirer is not mailed again.
        $repeat = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $inquiry->school_id)
            ->where('phone_key', $inquiry->phone_key)
            ->whereNotNull('phone_key')
            ->where('id', '<', $inquiry->id)
            ->where('created_at', '>=', now()->subDay())
            ->exists();
        if ($repeat) {
            return;
        }

        $addressKey = 'inquiry-receipt:'.sha1(mb_strtolower(trim($inquiry->email)));
        if (! Cache::add($addressKey, 1, now()->addDay())) {
            return;
        }

        $sent = RateLimiter::attempt(
            'inquiry-receipt-school:'.$inquiry->school_id,
            self::MAX_PER_SCHOOL_HOUR,
            fn () => true,
            3600,
        );
        if (! $sent) {
            Cache::forget($addressKey);

            return;
        }

        Notification::route('mail', $inquiry->email)->notify(
            new InquiryConfirmation($inquiry->loadMissing('school'))
        );
    }
}
