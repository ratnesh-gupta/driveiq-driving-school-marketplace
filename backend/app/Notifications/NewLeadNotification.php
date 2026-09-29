<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a school's staff about a new lead (DIQ-704). Also used for the
 * "still unanswered" reminder (DIQ-705). via() is the single place to add
 * SMS / WhatsApp delivery later.
 */
class NewLeadNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Inquiry $inquiry,
        public readonly bool $reminder = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->inquiry;
        $details = array_filter([
            'Vehicle' => $lead->vehicle_type,
            'Area' => $lead->area,
            'Preferred timing' => $lead->preferred_timing,
            'Came in via' => $lead->channel,
        ]);

        $mail = (new MailMessage)
            ->subject($this->reminder
                ? "Reminder: {$lead->name} is still waiting for a reply"
                : "New enquiry from {$lead->name}")
            ->greeting($this->reminder ? 'This lead has not been answered yet' : 'You have a new enquiry');

        $mail->line("**{$lead->name}** · {$lead->phone}");
        foreach ($details as $label => $value) {
            $mail->line("{$label}: {$value}");
        }
        if ($lead->message) {
            $mail->line('"'.str($lead->message)->limit(300).'"');
        }

        if ($intl = self::international($lead->phone)) {
            $mail->line("Call: [+{$intl}](tel:+{$intl})");
        }
        if ($wa = self::whatsappUrl($lead->phone)) {
            $mail->line("WhatsApp: [{$wa}]({$wa})");
        }

        return $mail
            ->action('Open in your dashboard', config('app.frontend_url').'/dashboard/leads')
            ->line('Schools that reply within an hour convert far more enquiries.');
    }

    private static function digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /** Digits with country code; Indian 10-digit numbers get 91. Null if unusable. */
    private static function international(string $phone): ?string
    {
        $digits = self::digits($phone);
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return strlen($digits) >= 11 ? $digits : null;
    }

    public static function whatsappUrl(string $phone): ?string
    {
        $intl = self::international($phone);

        return $intl ? 'https://wa.me/'.$intl : null;
    }
}
