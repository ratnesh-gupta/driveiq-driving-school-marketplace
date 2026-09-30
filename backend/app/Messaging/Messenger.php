<?php

namespace App\Messaging;

use App\Models\OutboundMessage;
use App\Support\Phone;
use InvalidArgumentException;

/**
 * Sends templated WhatsApp / SMS messages through the configured driver
 * (DIQ-1001). Callers decide *whether* someone should get a message (school
 * switch, opt-in); this class makes sending safe: valid numbers only, each
 * (template, subject, recipient) at most once, every attempt logged.
 */
class Messenger
{
    public function __construct(private readonly MessageSender $sender) {}

    /** @return 'sent'|'failed'|'duplicate'|'invalid_number' */
    public function send(?string $phone, Message $message): string
    {
        $to = Phone::toE164($phone);
        if ($to === null) {
            return 'invalid_number';
        }

        $template = config("messaging.templates.{$message->template}")
            ?? throw new InvalidArgumentException("Unknown message template {$message->template}");

        // Claim the (template, subject, recipient) slot first (ON CONFLICT DO
        // NOTHING): a retried job or a second worker finds it taken and sends
        // nothing. A failed plain insert would abort a surrounding transaction.
        $key = [
            'template' => $message->template,
            'related_type' => $message->relatedType,
            'related_id' => $message->relatedId,
            'to_hash' => hash('sha256', $to),
        ];
        $claimed = OutboundMessage::query()->insertOrIgnore($key + [
            'school_id' => $message->schoolId,
            'channel' => $message->channel,
            'to_masked' => Phone::mask($to),
            'status' => 'sending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($claimed === 0) {
            return 'duplicate';
        }
        $log = OutboundMessage::query()->where($key)->firstOrFail();

        $text = self::render($template['text'], $message->vars);
        $vars = array_map(fn ($k) => (string) ($message->vars[$k] ?? ''), $template['vars']);
        $result = $this->sender->send($message->channel, $to, $template, $vars, $text);

        if (! $result->ok && $message->channel === 'whatsapp' && config('messaging.sms_fallback') && ! empty($template['dlt_template_id'])) {
            $fallback = $this->sender->send('sms', $to, $template, $vars, $text);
            if ($fallback->ok) {
                $log->update(['channel' => 'sms', 'status' => 'sent', 'provider_message_id' => $fallback->providerId, 'error' => 'whatsapp: '.$result->error]);

                return 'sent';
            }
        }

        $log->update([
            'status' => $result->ok ? 'sent' : 'failed',
            'provider_message_id' => $result->providerId,
            'error' => $result->error ? mb_substr($result->error, 0, 500) : null,
        ]);

        return $result->ok ? 'sent' : 'failed';
    }

    /** Fill {{var}} placeholders; missing vars become empty strings. */
    public static function render(string $text, array $vars): string
    {
        return trim(preg_replace_callback('/\{\{(\w+)\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $text));
    }
}
