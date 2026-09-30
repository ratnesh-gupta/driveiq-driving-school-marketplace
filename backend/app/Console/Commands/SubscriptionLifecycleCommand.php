<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\PlanNoticeNotification;
use App\Services\BillingService;
use App\Services\NotificationService;
use App\Support\SchoolAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * DIQ-807: daily plan lifecycle.
 *  - Reminds owners 7 days and 1 day before a trial or paid plan ends (once each).
 *  - Marks plans/trials past their end date as expired and tells the owner
 *    what changed (once), unless the school already has a paid plan running.
 */
class SubscriptionLifecycleCommand extends Command
{
    protected $signature = 'driveiq:subscriptions';

    protected $description = 'Expire ended plans and trials, and send ending-soon reminders';

    public function handle(BillingService $billing, NotificationService $notifications, SchoolAccess $access): int
    {
        $now = now();
        $reminded = 0;
        $ended = 0;

        $upcoming = Subscription::withoutGlobalScope('school')->with('plan')
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(7)])
            ->get();

        foreach ($upcoming as $sub) {
            if ($this->coveredByPaidPlan($sub)) {
                continue;
            }

            $withinDay = $sub->expires_at->lte($now->copy()->addDay());
            $column = $withinDay ? 'reminded_1d_at' : 'reminded_7d_at';
            if ($sub->{$column}) {
                continue;
            }

            $what = $this->describe($sub);
            $when = $withinDay ? 'tomorrow' : 'on '.$sub->expires_at->toFormattedDateString();
            $this->notify($billing, $notifications, $access, $sub, 'billing.ending',
                "Your {$what} ends {$when}",
                $sub->status === 'trial'
                    ? 'After your trial ends, modules that are not on your plan become read-only. Your data stays. Choose a plan to keep full access.'
                    : "Renew to keep your {$what} benefits without a break."
            );
            // A 1-day reminder also covers the 7-day one if both would be due.
            $sub->forceFill(['reminded_1d_at' => $withinDay ? $now : null, 'reminded_7d_at' => $now])->save();
            $reminded++;
        }

        $expired = Subscription::withoutGlobalScope('school')->with('plan')
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expired as $sub) {
            $wasTrial = $sub->status === 'trial';
            $sub->forceFill(['status' => 'expired'])->save();

            if (! $sub->ended_notified_at && ! $this->coveredByPaidPlan($sub)) {
                $what = $this->describe($sub, $wasTrial);
                $this->notify($billing, $notifications, $access, $sub, 'billing.ended',
                    "Your {$what} has ended",
                    'You are now on the Basic plan. Learners, instructors and other operations modules are read-only until you upgrade; nothing has been deleted.'
                );
                $sub->forceFill(['ended_notified_at' => $now])->save();
            }
            $ended++;
        }

        $this->info("Reminders sent: {$reminded}. Plans/trials ended: {$ended}.");

        return self::SUCCESS;
    }

    /** Another paid plan runs past this one, so there is nothing to warn about. */
    private function coveredByPaidPlan(Subscription $sub): bool
    {
        return Subscription::withoutGlobalScope('school')
            ->where('school_id', $sub->school_id)
            ->where('id', '!=', $sub->id)
            ->where('status', 'active')
            ->whereHas('plan', fn ($p) => $p->where('price_monthly', '>', 0))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $sub->expires_at))
            ->exists();
    }

    private function describe(Subscription $sub, ?bool $trial = null): string
    {
        $plan = ucfirst($sub->plan?->code ?? 'paid');

        return ($trial ?? $sub->status === 'trial') ? "{$plan} trial" : "{$plan} plan";
    }

    private function notify(BillingService $billing, NotificationService $notifications, SchoolAccess $access, Subscription $sub, string $type, string $title, string $body): void
    {
        $billing->notifyOwners($sub->school_id, $type, $title, $body, ['subscriptionId' => $sub->id]);

        $owners = $notifications->schoolStaff($sub->school_id)->filter(fn ($u) => $access->isOwner($u, $sub->school_id));
        Notification::send($owners, new PlanNoticeNotification($title, $body));
    }
}
