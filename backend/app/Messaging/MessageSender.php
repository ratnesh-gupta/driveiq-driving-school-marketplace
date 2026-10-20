<?php

namespace App\Messaging;

/**
 * A WhatsApp / SMS provider (DIQ-1001). Implementations receive an E.164
 * number, the template definition from config/messaging.php (including the
 * provider and DLT template ids) and the rendered text; they must not throw
 * for delivery failures but return SendResult::failed().
 */
interface MessageSender
{
    /** @param array{text: string, vars: list<string>, provider_template_id: ?string, dlt_template_id: ?string} $template */
    public function send(string $channel, string $to, array $template, array $vars, string $text): SendResult;
}
