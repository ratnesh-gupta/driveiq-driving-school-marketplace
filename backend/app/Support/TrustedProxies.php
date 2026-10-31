<?php

namespace App\Support;

/**
 * Parses TRUSTED_PROXIES (BSEC-02). Empty = trust nobody (safe default);
 * "*" trusts every hop and is only safe when the origin is reachable solely
 * through the proxy; otherwise a comma list of IPs/CIDRs.
 */
class TrustedProxies
{
    /** @return array<int, string>|string|null */
    public static function parse(mixed $value): array|string|null
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $value = trim($value);

        if ($value === '*' || $value === '**') {
            return $value;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
