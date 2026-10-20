<?php

namespace App\Notifications;

use App\Services\ListingClaimService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** DIQ-1104: one-time code proving the claimant reads the listing's email. Sent immediately. */
class ClaimCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code, public readonly string $listingName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->code} is your code to claim {$this->listingName} on DriveQ")
            ->line("Use this code to claim the DriveQ listing for {$this->listingName}:")
            ->line("**{$this->code}**")
            ->line('It expires in '.ListingClaimService::CODE_MINUTES.' minutes. If you did not ask for it, ignore this email: nothing changes.');
    }
}
