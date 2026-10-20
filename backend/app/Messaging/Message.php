<?php

namespace App\Messaging;

/**
 * One templated message for one phone (DIQ-1001). `related` identifies what
 * it is about (e.g. the inquiry), so the same message is never sent twice.
 */
final class Message
{
    /** @param array<string, scalar|null> $vars */
    public function __construct(
        public readonly string $template,
        public readonly array $vars,
        public readonly ?string $relatedType = null,
        public readonly ?int $relatedId = null,
        public readonly ?int $schoolId = null,
        public readonly string $channel = 'whatsapp',
    ) {}
}
