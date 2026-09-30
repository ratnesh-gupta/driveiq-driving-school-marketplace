<?php

namespace App\Messaging\Drivers;

use App\Messaging\MessageSender;
use App\Messaging\SendResult;

/** Sends nothing (tests, or messaging switched off platform-wide). */
class NullSender implements MessageSender
{
    public function send(string $channel, string $to, array $template, array $vars, string $text): SendResult
    {
        return SendResult::sent();
    }
}
