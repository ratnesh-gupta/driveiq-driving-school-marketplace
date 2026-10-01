<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GoogleBusinessConnection;
use App\Models\School;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * DIQ-1107: Google Business Profile, read only. An owner connects with
 * OAuth (business.manage), picks one of their locations and imports its
 * details into the listing. A location Google has verified for them also
 * proves the listing is theirs.
 */
class GoogleBusinessService
{
    public const SCOPE = 'https://www.googleapis.com/auth/business.manage';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const ACCOUNTS_URL = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';

    private const INFO_URL = 'https://mybusinessbusinessinformation.googleapis.com/v1/';

    private const REVIEWS_URL = 'https://mybusiness.googleapis.com/v4/';

    private const READ_MASK = 'name,title,phoneNumbers,storefrontAddress,websiteUri,regularHours,latlng,metadata';

    private const DAYS = ['MONDAY' => 'Mon', 'TUESDAY' => 'Tue', 'WEDNESDAY' => 'Wed', 'THURSDAY' => 'Thu', 'FRIDAY' => 'Fri', 'SATURDAY' => 'Sat', 'SUNDAY' => 'Sun'];

    public function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return rtrim(config('app.url'), '/').'/api/google-business/callback';
    }

    /** @param  array<string, scalar>  $state  who is connecting and why; encrypted, valid 10 minutes */
    public function authUrl(array $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => Crypt::encryptString(json_encode([...$state, 'exp' => now()->addMinutes(10)->timestamp])),
        ]);
    }

    public function readState(string $state): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return ($data['exp'] ?? 0) >= now()->timestamp ? $data : null;
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_in: int} */
    public function exchangeCode(string $code): array
    {
        $res = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        if (! $res->ok() || ! $res->json('access_token')) {
            throw new RuntimeException('Google sign-in failed: '.$res->json('error', 'no token'));
        }

        return ['access_token' => $res->json('access_token'), 'refresh_token' => $res->json('refresh_token'), 'expires_in' => (int) $res->json('expires_in', 3600)];
    }

    public function accessToken(GoogleBusinessConnection $c): string
    {
        if ($c->token_expires_at && $c->token_expires_at->isAfter(now()->addMinute())) {
            return $c->access_token;
        }
        if (! $c->refresh_token) {
            throw new RuntimeException('The Google connection has expired. Connect again.');
        }

        $res = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $c->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        if (! $res->ok() || ! $res->json('access_token')) {
            throw new RuntimeException('The Google connection has expired. Connect again.');
        }
        $c->forceFill(['access_token' => $res->json('access_token'), 'token_expires_at' => now()->addSeconds((int) $res->json('expires_in', 3600))])->save();

        return $c->access_token;
    }

    /**
     * Every location the signed-in Google user manages, simplified.
     *
     * @return list<array{name: string, title: string, address: ?string, phone: ?string, placeId: ?string, verified: bool, latitude: ?float, longitude: ?float, website: ?string, hours: ?string}>
     */
    public function locations(string $token): array
    {
        $accounts = Http::withToken($token)->timeout(15)->get(self::ACCOUNTS_URL)->throw()->json('accounts', []);

        $out = [];
        foreach ($accounts as $account) {
            $pageToken = null;
            do {
                $res = Http::withToken($token)->timeout(15)->get(self::INFO_URL.$account['name'].'/locations', array_filter([
                    'readMask' => self::READ_MASK, 'pageSize' => 100, 'pageToken' => $pageToken,
                ]))->throw();
                foreach ($res->json('locations', []) as $loc) {
                    $out[] = $this->simplify($account['name'], $loc);
                }
                $pageToken = $res->json('nextPageToken');
            } while ($pageToken);
        }

        return $out;
    }

    /** @return array{rating: ?float, reviewCount: ?int} */
    public function reviewSummary(string $token, string $locationName): array
    {
        $res = Http::withToken($token)->timeout(15)->get(self::REVIEWS_URL.$locationName.'/reviews', ['pageSize' => 1]);
        if (! $res->ok()) {
            return ['rating' => null, 'reviewCount' => null];
        }

        return [
            'rating' => $res->json('averageRating') !== null ? round((float) $res->json('averageRating'), 1) : null,
            'reviewCount' => $res->json('totalReviewCount') !== null ? (int) $res->json('totalReviewCount') : null,
        ];
    }

    /** Copies a location's details into the listing; a verified location also marks the business verified. */
    public function import(School $school, GoogleBusinessConnection $c, array $location): School
    {
        return DB::transaction(function () use ($school, $c, $location) {
            $old = $school->only(['name', 'phone', 'address', 'latitude', 'longitude', 'timings', 'business_verified', 'google_place_id']);

            $updates = array_filter([
                'name' => $location['title'] ?: null,
                'phone' => $location['phone'],
                'address' => $location['address'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'timings' => $location['hours'],
            ], fn ($v) => $v !== null && $v !== '');
            $school->fill($updates);

            if ($location['verified'] && $location['placeId']) {
                $taken = School::where('google_place_id', $location['placeId'])->whereKeyNot($school->id)->exists();
                if ($taken) {
                    throw new RuntimeException('Another DriveIQ listing is already linked to this Google business. Contact us and we will sort it out.');
                }
                $school->forceFill(['google_place_id' => $location['placeId'], 'business_verified' => true]);
            }
            $school->save();

            $c->forceFill([
                'location_name' => $location['name'],
                'location_title' => $location['title'],
                'place_id' => $location['placeId'],
                'imported_at' => now(),
            ])->save();

            AuditLog::log('google_import', 'School', $school->id, $old, $school->only(array_keys($old)), $school->id);

            return $school;
        });
    }

    public function revoke(GoogleBusinessConnection $c): void
    {
        try {
            Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $c->refresh_token ?: $c->access_token]);
        } catch (\Throwable) {
            // Deleting our copy is what matters; Google also expires unused grants.
        }
        $c->delete();
    }

    private function simplify(string $account, array $loc): array
    {
        $addr = $loc['storefrontAddress'] ?? [];
        $address = trim(implode(', ', array_filter([...($addr['addressLines'] ?? []), $addr['locality'] ?? null, $addr['postalCode'] ?? null])));

        return [
            // The v4 reviews API wants accounts/{a}/locations/{l}.
            'name' => $account.'/'.$loc['name'],
            'title' => (string) ($loc['title'] ?? ''),
            'address' => $address !== '' ? $address : null,
            'phone' => $loc['phoneNumbers']['primaryPhone'] ?? null,
            'website' => $loc['websiteUri'] ?? null,
            'placeId' => $loc['metadata']['placeId'] ?? null,
            // Google lets only verified owners manage a location's details.
            'verified' => (bool) ($loc['metadata']['hasVoiceOfMerchant'] ?? false),
            'latitude' => isset($loc['latlng']['latitude']) ? (float) $loc['latlng']['latitude'] : null,
            'longitude' => isset($loc['latlng']['longitude']) ? (float) $loc['latlng']['longitude'] : null,
            'hours' => $this->hours($loc['regularHours']['periods'] ?? []),
        ];
    }

    /** "Mon–Sat 07:00–19:00" style summary of regular opening hours. */
    private function hours(array $periods): ?string
    {
        $byTime = [];
        foreach ($periods as $p) {
            $day = self::DAYS[$p['openDay'] ?? ''] ?? null;
            if (! $day) {
                continue;
            }
            $t = fn ($x) => sprintf('%02d:%02d', $x['hours'] ?? 0, $x['minutes'] ?? 0);
            $byTime[$t($p['openTime'] ?? []).'–'.$t($p['closeTime'] ?? [])][] = $day;
        }
        if (! $byTime) {
            return null;
        }

        $order = array_values(self::DAYS);
        $parts = [];
        foreach ($byTime as $time => $days) {
            $days = array_values(array_unique($days));
            usort($days, fn ($a, $b) => array_search($a, $order) <=> array_search($b, $order));
            $idx = array_map(fn ($d) => array_search($d, $order), $days);
            $consecutive = count($days) > 2 && end($idx) - $idx[0] === count($days) - 1;
            $parts[] = ($consecutive ? $days[0].'–'.end($days) : implode(', ', $days)).' '.$time;
        }

        return mb_substr(implode('; ', $parts), 0, 255);
    }
}
