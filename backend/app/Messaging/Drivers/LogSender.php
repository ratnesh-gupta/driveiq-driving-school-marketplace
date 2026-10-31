<?php

namespace App\Messaging\Drivers;

use App\Messaging\MessageSender;
use App\Messaging\SendResult;
use App\Support\Phone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development driver: writes the message to the log instead of sending it. */
class LogSender implements MessageSender
{
    public function send(string $channel, string $to, array $template, array $vars, string $text): SendResult
    {
        // BSEC-16: one-time codes never reach the log in clear.
        foreach (['code', 'otp'] as $secret) {
            if (isset($vars[$secret]) && $vars[$secret] !== '') {
                $text = str_replace((string) $vars[$secret], '****', $text);
            }
        }

        Log::info("[messaging:{$channel}] to ".Phone::mask($to).': '.$text);

        return SendResult::sent('log-'.Str::lower(Str::random(10)));
    }
}
