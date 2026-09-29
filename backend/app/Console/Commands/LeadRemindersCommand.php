<?php

namespace App\Console\Commands;

use App\Models\Inquiry;
use App\Models\SchoolSetting;
use App\Notifications\NewLeadNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * DIQ-705: remind school staff once about a lead still unanswered after the
 * school's notifications.reminder_after_minutes (0 = off). Only leads from the
 * last LOOKBACK_DAYS are considered, so enabling reminders never mails a backlog.
 */
class LeadRemindersCommand extends Command
{
    public const LOOKBACK_DAYS = 7;

    protected $signature = 'driveiq:lead-reminders';

    protected $description = 'Remind schools about leads that are still waiting for a first reply';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;
        $settings = [];

        Inquiry::withoutGlobalScope('school')
            ->where('status', 'pending')
            ->whereNull('first_responded_at')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('school_id')
            ->where('created_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->orderBy('id')
            ->chunkById(200, function ($leads) use (&$sent, &$settings, $notifications) {
                foreach ($leads as $lead) {
                    $prefs = $settings[$lead->school_id] ??= SchoolSetting::forSchool($lead->school_id)['notifications'];
                    $after = (int) ($prefs['reminder_after_minutes'] ?? 60);

                    if ($after <= 0 || ! ($prefs['new_inquiry'] ?? true) || $lead->created_at->gt(now()->subMinutes($after))) {
                        continue;
                    }

                    if ($prefs['in_app'] ?? true) {
                        $notifications->notifySchool(
                            $lead->school_id,
                            'inquiry.reminder',
                            'Lead waiting for a reply',
                            sprintf('%s enquired %s and has not been contacted yet.', $lead->name, $lead->created_at->diffForHumans()),
                            ['inquiryId' => $lead->id, 'name' => $lead->name]
                        );
                    }
                    if ($prefs['email'] ?? true) {
                        Notification::send($notifications->schoolStaff($lead->school_id), new NewLeadNotification($lead, reminder: true));
                    }

                    $lead->forceFill(['reminder_sent_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Lead reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
