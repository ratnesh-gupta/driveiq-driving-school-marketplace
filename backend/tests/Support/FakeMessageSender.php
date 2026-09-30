<?php

namespace Tests\Support;

use App\Messaging\MessageSender;
use App\Messaging\SendResult;

/** Records what would have been sent; can be told to fail a channel. */
class FakeMessageSender implements MessageSender
{
    /** @var list<array{channel: string, to: string, template: array, vars: array, text: string}> */
    public array $sent = [];

    /** @var list<string> channels that fail */
    public array $failing = [];

    public function send(string $channel, string $to, array $template, array $vars, string $text): SendResult
    {
        if (in_array($channel, $this->failing, true)) {
            return SendResult::failed("{$channel} down");
        }
        $this->sent[] = compact('channel', 'to', 'template', 'vars', 'text');

        return SendResult::sent('fake-'.count($this->sent));
    }

    public function texts(): array
    {
        return array_column($this->sent, 'text');
    }
}
