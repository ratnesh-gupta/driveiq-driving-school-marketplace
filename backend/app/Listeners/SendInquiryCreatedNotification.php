<?php

namespace App\Listeners;

use App\Events\InquiryCreated;
use App\Models\SchoolSetting;
use App\Notifications\NewLeadNotification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the school's staff about a new lead, in-app and by email, as their
 * notification settings allow (DIQ-704). Queued so the public enquiry form
 * never waits on mail delivery.
 */
class SendInquiryCreatedNotification implements ShouldQueue
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(InquiryCreated $event): void
    {
        $inquiry = $event->inquiry;

        if (! $inquiry->school_id) {
            return;
        }

        $prefs = SchoolSetting::forSchool($inquiry->school_id)['notifications'];
        if (! ($prefs['new_inquiry'] ?? true)) {
            return;
        }

        if ($prefs['in_app'] ?? true) {
            $this->notifications->notifySchool(
                $inquiry->school_id,
                'inquiry.created',
                'New enquiry received',
                sprintf('%s submitted an enquiry (%s)', $inquiry->name, $inquiry->phone),
                [
                    'inquiryId' => $inquiry->id,
                    'name' => $inquiry->name,
                    'phone' => $inquiry->phone,
                    'vehicleType' => $inquiry->vehicle_type,
                    'status' => $inquiry->status,
                ]
            );
        }

        if ($prefs['email'] ?? true) {
            Notification::send($this->notifications->schoolStaff($inquiry->school_id), new NewLeadNotification($inquiry));
        }
    }
}
