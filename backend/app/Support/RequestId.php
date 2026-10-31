<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * BSEC-25: one reference per request, shown to the client on errors and
 * written to the log context so support can find the exception.
 * A proxy-supplied X-Request-Id is honoured only if it looks like an id.
 */
class RequestId
{
    public static function current(): string
    {
        $request = request();
        $existing = $request->attributes->get('request_id');
        if (is_string($existing)) {
            return $existing;
        }

        $incoming = $request->headers->get('X-Request-Id');
        $id = is_string($incoming) && preg_match('/^[A-Za-z0-9\-]{8,64}$/', $incoming)
            ? $incoming
            : (string) Str::uuid();
        $request->attributes->set('request_id', $id);

        return $id;
    }
}
