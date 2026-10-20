<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Support\Facades\DB;

/**
 * Do-not-contact list for outreach (DIQ-1103/1105). Checked before every
 * outreach send; holds only SHA-256 hashes of normalised emails and phones.
 */
class OutreachSuppression
{
    public const REASONS = ['unsubscribed', 'not_my_business', 'admin', 'bounced', 'complaint'];

    public function suppress(?string $email, ?string $phone, string $reason): void
    {
        $rows = [];
        foreach ($this->hashes($email, $phone) as $kind => $hash) {
            $rows[] = ['kind' => $kind, 'value_hash' => $hash, 'reason' => $reason, 'created_at' => now()];
        }
        if ($rows) {
            DB::table('outreach_suppressions')->insertOrIgnore($rows);
        }
    }

    public function isSuppressed(?string $email, ?string $phone = null): bool
    {
        $hashes = $this->hashes($email, $phone);
        if (! $hashes) {
            return false;
        }

        return DB::table('outreach_suppressions')
            ->where(function ($q) use ($hashes) {
                foreach ($hashes as $kind => $hash) {
                    $q->orWhere(fn ($w) => $w->where('kind', $kind)->where('value_hash', $hash));
                }
            })
            ->exists();
    }

    /** @return array<string, string> */
    private function hashes(?string $email, ?string $phone): array
    {
        $out = [];
        if ($e = Prospect::normalEmail($email)) {
            $out['email'] = hash('sha256', $e);
        }
        if ($p = Prospect::normalPhone($phone)) {
            $out['phone'] = hash('sha256', $p);
        }

        return $out;
    }
}
