<?php

namespace App\Listeners;

use App\Events\InquiryCreated;
use App\Services\NotificationService;

class SendInquiryCreatedNotification
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(InquiryCreated $event): void
    {
        $inquiry = $event->inquiry;

        if (! $inquiry->school_id) {
            return;
        }

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
}
