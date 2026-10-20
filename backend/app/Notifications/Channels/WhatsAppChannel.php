<?php

namespace App\Notifications\Channels;

use App\Messaging\Message;
use App\Messaging\Messenger;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel "whatsapp" (DIQ-1001). A notification opts in
 * by listing 'whatsapp' in via() and implementing toWhatsApp(): ?Message.
 * The number comes from routeNotificationForWhatsapp() on the notifiable,
 * which returns null unless that person opted in.
 */
class WhatsAppChannel
{
    public function __construct(private readonly Messenger $messenger) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('whatsapp', $notification);
        if (! $phone || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);
        if ($message instanceof Message) {
            $this->messenger->send($phone, $message);
        }
    }
}
