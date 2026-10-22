<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Trusts X-Forwarded-* headers only from the proxies listed in
 * TRUSTED_PROXIES (DIQ-1302). Read from config at request time, so it keeps
 * working once `config:cache` has run. Empty means trust nobody: the
 * default, for when browsers reach Caddy directly and PHP sees their real IP.
 * Behind Cloudflare or an AWS load balancer, list their ranges so the
 * per-IP rate limits use the visitor's address, not the proxy's.
 */
class TrustConfiguredProxies extends TrustProxies
{
    protected function proxies()
    {
        $configured = trim((string) config('app.trusted_proxies', ''));

        if ($configured === '') {
            return null;
        }

        if ($configured === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
