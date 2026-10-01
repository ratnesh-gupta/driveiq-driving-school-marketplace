<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Prospect;
use App\Notifications\OnboardingInvite;
use App\Services\ListingClaimService;
use App\Services\OutreachSuppression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * DIQ-1106: Google Ads lead form webhook. Google posts each lead with the
 * shared google_key; we file it as a prospect (source "ads") and reply
 * with an onboarding email, since they asked to hear from us.
 */
class GoogleAdsLeadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $key = (string) config('services.google_ads.webhook_key');
        if ($key === '') {
            return response()->json(['message' => 'Not configured'], 404);
        }
        if (! is_string($request->input('google_key')) || ! hash_equals($key, $request->input('google_key'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $leadId = (string) $request->input('lead_id', '');
        if ($leadId === '' || ! is_array($request->input('user_column_data'))) {
            return response()->json(['message' => 'lead_id and user_column_data are required'], 422);
        }
        // Google retries until it gets a 200: answer duplicates with 200 too.
        if (DB::table('ad_leads')->where('lead_id', $leadId)->exists()) {
            return response()->json([]);
        }

        $ids = [
            'lead_id' => $leadId,
            'form_id' => $this->str($request->input('form_id')),
            'campaign_id' => $this->str($request->input('campaign_id')),
            'adgroup_id' => $this->str($request->input('adgroup_id')),
            'is_test' => (bool) $request->input('is_test', false),
        ];

        if ($ids['is_test']) {
            DB::table('ad_leads')->insertOrIgnore([...$ids, 'outcome' => 'test', 'created_at' => now()]);

            return response()->json([]);
        }

        $fields = $this->columns($request->input('user_column_data'));
        [$prospect, $outcome] = $this->fileProspect($fields, $ids);

        DB::table('ad_leads')->insertOrIgnore([...$ids, 'prospect_id' => $prospect?->id, 'outcome' => $outcome, 'created_at' => now()]);

        return response()->json([]);
    }

    /** @return array{0: ?Prospect, 1: string} */
    private function fileProspect(array $f, array $ids): array
    {
        $contact = $f['FULL_NAME'] ?? trim(($f['FIRST_NAME'] ?? '').' '.($f['LAST_NAME'] ?? '')) ?: null;
        $email = $f['EMAIL'] ?? $f['WORK_EMAIL'] ?? null;
        $phone = $f['PHONE_NUMBER'] ?? $f['WORK_PHONE'] ?? null;
        $answers = mb_strtolower(implode(' ', $f['_custom'] ?? []));
        $type = str_contains($answers, 'trainer') || str_contains($answers, 'instructor') ? 'trainer' : 'school';
        $name = $f['COMPANY_NAME'] ?? $contact ?? 'Google Ads lead';

        if ($email && Validator::make(['e' => $email], ['e' => 'email'])->fails()) {
            $email = null;
        }

        $meta = ['google_ads' => array_filter([...$ids, 'city' => $f['CITY'] ?? null, 'answers' => $f['_custom'] ?? null])];
        $prospect = Prospect::findDuplicate($phone, $email, null);
        $outcome = 'created';

        if ($prospect) {
            $prospect->forceFill([
                'notes' => trim(($prospect->notes ? $prospect->notes."\n" : '').'Google Ads enquiry on '.now()->toDateString().'.'),
                'meta' => array_merge($prospect->meta ?? [], $meta),
                'email' => $prospect->email ?: $email,
                'phone' => $prospect->phone ?: $phone,
            ])->save();
            $outcome = 'updated';
        } else {
            $prospect = Prospect::create([
                'type' => $type, 'name' => mb_substr($name, 0, 255), 'contact_person' => $contact,
                'email' => $email, 'phone' => $phone, 'source' => 'ads', 'meta' => $meta,
                'notes' => isset($f['CITY']) ? "City: {$f['CITY']}" : null,
            ]);
        }
        AuditLog::log('ads_lead', 'Prospect', $prospect->id, [], ['outcome' => $outcome, 'campaign_id' => $ids['campaign_id']]);

        // They asked to hear from us; we still honour an earlier opt-out.
        $to = $prospect->email;
        if (! $to || ! $prospect->isContactable()) {
            return [$prospect, $outcome];
        }
        if (app(OutreachSuppression::class)->isSuppressed($to, null)) {
            return [$prospect, 'suppressed'];
        }

        $school = $prospect->school;
        $link = $school && $school->listing_status === 'unclaimed'
            ? app(ListingClaimService::class)->issue($school, $prospect)['url']
            : config('app.frontend_url').'/auth/register?'.http_build_query([
                'type' => $prospect->type, 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'ads-'.($ids['campaign_id'] ?? 'lead'),
            ]);

        Notification::route('mail', $to)->notify(new OnboardingInvite($prospect->type, $link, $prospect->contact_person));
        $prospect->forceFill(['stage' => $prospect->stage === 'new' ? 'contacted' : $prospect->stage, 'last_contacted_at' => now()])->save();

        return [$prospect, $outcome];
    }

    /**
     * user_column_data => [COLUMN_ID => value]; answers to custom questions
     * (no standard column id) are collected under _custom.
     */
    private function columns(array $data): array
    {
        $out = ['_custom' => []];
        foreach ($data as $col) {
            if (! is_array($col)) {
                continue;
            }
            $value = trim((string) ($col['string_value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $id = strtoupper((string) ($col['column_id'] ?? ''));
            if (in_array($id, ['FULL_NAME', 'FIRST_NAME', 'LAST_NAME', 'EMAIL', 'WORK_EMAIL', 'PHONE_NUMBER', 'WORK_PHONE', 'CITY', 'COMPANY_NAME', 'POSTAL_CODE'], true)) {
                $out[$id] = mb_substr($value, 0, 255);
            } else {
                $out['_custom'][] = mb_substr(($col['column_name'] ?? $id).': '.$value, 0, 300);
            }
        }

        return $out;
    }

    private function str(mixed $v): ?string
    {
        return $v === null || $v === '' ? null : (string) $v;
    }
}
