<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * DIQ-1106: reply to someone who asked us (through an ad form) about
 * listing their driving school or trainer profile. Not cold outreach.
 */
class OnboardingInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $link,
        public readonly ?string $contactName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $what = $this->type === 'trainer' ? 'trainer profile' : 'driving school listing';

        return (new MailMessage)
            ->subject("Your free {$what} on DriveQ")
            ->greeting('Hi '.($this->contactName ?: 'there').',')
            ->line('Thanks for your interest in DriveQ. Learners in Pune use it to find and compare driving schools and trainers near them.')
            ->line("Setting up your free {$what} takes a few minutes. Learners can then send you enquiries by WhatsApp or phone.")
            ->action('Set up my free '.$what, $this->link)
            ->line('Questions? Just reply to this email.');
    }
}
