<?php

namespace App\Support;

/** Phone number normalisation for messaging (DIQ-1001). */
class Phone
{
    /**
     * E.164 ("+919876543210"), or null when the input is not a plausible
     * mobile number. Bare 10-digit numbers get the default country code;
     * a leading 0 (trunk prefix) is dropped.
     */
    public static function toE164(?string $raw, ?string $countryCode = null): ?string
    {
        if ($raw === null) {
            return null;
        }
        $cc = $countryCode ?? (string) config('messaging.default_country_code', '91');
        $hasPlus = str_starts_with(trim($raw), '+');
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return null;
        }
        if (! $hasPlus) {
            $digits = ltrim($digits, '0');
            if (strlen($digits) === 10) {
                $digits = $cc.$digits;
            } elseif (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            }
        }

        // Indian mobiles: 91 + 10 digits starting 6-9. Others: plain E.164 length.
        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            return preg_match('/^91[6-9]\d{9}$/', $digits) ? '+'.$digits : null;
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
    }

    /** "+91******3210": enough to recognise, not enough to reuse. */
    public static function mask(string $e164): string
    {
        return substr($e164, 0, 3).str_repeat('*', max(0, strlen($e164) - 7)).substr($e164, -4);
    }
}
