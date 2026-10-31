<?php

namespace App\Support;

/**
 * Visitor-supplied text that ends up inside branded WhatsApp/SMS/email bodies
 * (BSEC-03): no control characters, no links, bounded length.
 */
class TextSanitizer
{
    public static function plain(?string $value, int $max = 100): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $value) ?? '';
        // Scheme URLs, www. hosts and bare domain-looking tokens (example.com/x, bit.ly/abc).
        $value = preg_replace('~(?:[a-z][a-z0-9+.\-]*://|www\.)\S+~iu', ' ', $value) ?? '';
        $value = preg_replace('~\b[\w\-]+(?:\.[\w\-]+)*\.(?:[a-z]{2,24})(?:/\S*)?~iu', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, $max);
    }

    /** Free text for storage: control characters out, links allowed (staff see it in the app only). */
    public static function clean(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace('/[^\P{Cc}\n\t]|\p{Cf}/u', '', $value) ?? '';

        return mb_substr(trim($value), 0, $max);
    }
}
