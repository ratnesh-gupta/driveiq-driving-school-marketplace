<?php

namespace App\Services;

use App\Mail\OutreachEmail;
use App\Models\AuditLog;
use App\Models\OutreachCampaign;
use App\Models\OutreachEnrollment;
use App\Models\OutreachMessage;
use App\Models\Prospect;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * DIQ-1105: emails prospects in short sequences, within a daily cap and
 * business hours, never to anyone on the do-not-contact list. A sequence
 * stops when the prospect replies, claims, signs up or unsubscribes.
 */
class OutreachService
{
    public const PLACEHOLDERS = ['name', 'contact', 'locality', 'link', 'listing', 'nearby_line'];

    public function __construct(
        private readonly OutreachSuppression $suppression,
        private readonly ListingClaimService $claims,
        private readonly ProspectService $prospects,
    ) {}

    /** Prospects a campaign may write to: right type, has email, still open, not suppressed. */
    public function eligible(OutreachCampaign $campaign, array $filters): Builder
    {
        return Prospect::query()
            ->where('type', $campaign->audience)
            ->whereNotNull('email_normalized')
            ->whereIn('stage', $filters['stages'] ?? ['new', 'contacted'])
            ->when($filters['localityId'] ?? null, fn ($q, $v) => $q->where('locality_id', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->whereNotExists(fn ($q) => $q->from('outreach_enrollments')
                ->whereColumn('outreach_enrollments.prospect_id', 'prospects.id')
                ->where('outreach_enrollments.campaign_id', $campaign->id))
            ->whereNotExists(fn ($q) => $q->from('outreach_suppressions')
                ->where('kind', 'email')
                ->whereRaw('value_hash = encode(sha256(convert_to(prospects.email_normalized, \'UTF8\')), \'hex\')'));
    }

    /** @return int how many prospects were (or, on a dry run, would be) added */
    public function enroll(OutreachCampaign $campaign, array $filters, bool $dryRun = false): int
    {
        $query = $this->eligible($campaign, $filters);
        if ($dryRun) {
            return $query->count();
        }

        $ids = $query->pluck('id');
        $now = now();
        DB::table('outreach_enrollments')->insertOrIgnore($ids->map(fn ($id) => [
            'campaign_id' => $campaign->id, 'prospect_id' => $id, 'next_step' => 0, 'next_send_at' => $now,
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ])->all());

        AuditLog::log('enroll', 'OutreachCampaign', $campaign->id, [], ['count' => $ids->count(), 'filters' => $filters]);

        return $ids->count();
    }

    public function withinSendingHours(?Carbon $at = null): bool
    {
        $local = ($at ?? now())->copy()->setTimezone(config('outreach.timezone'));

        return in_array($local->isoWeekday(), config('outreach.days'), true)
            && $local->hour >= config('outreach.start_hour')
            && $local->hour < config('outreach.end_hour');
    }

    public function sentToday(): int
    {
        $start = now()->setTimezone(config('outreach.timezone'))->startOfDay()->utc();

        return OutreachMessage::whereIn('status', ['sent', 'sending'])->where('created_at', '>=', $start)->count();
    }

    /** @return array{sent: int, stopped: int, failed: int} */
    public function sendDue(): array
    {
        $result = ['sent' => 0, 'stopped' => 0, 'failed' => 0];
        $room = max(0, (int) config('outreach.daily_cap') - $this->sentToday());
        if ($room === 0 || ! $this->withinSendingHours()) {
            return $result;
        }

        $due = OutreachEnrollment::query()
            ->with(['campaign', 'prospect.school'])
            ->where('status', 'active')
            ->where('next_send_at', '<=', now())
            ->whereHas('campaign', fn ($q) => $q->where('status', 'active'))
            ->orderBy('next_send_at')->orderBy('id')
            ->limit($room)
            ->get();

        foreach ($due as $enrollment) {
            $outcome = $this->sendStep($enrollment);
            $result[$outcome]++;
        }

        return $result;
    }

    /** @return 'sent'|'stopped'|'failed' */
    private function sendStep(OutreachEnrollment $enrollment): string
    {
        $campaign = $enrollment->campaign;
        $prospect = $enrollment->prospect;
        $steps = $campaign->steps;

        if (! $prospect || ! $prospect->email) {
            $enrollment->stop('no_email');

            return 'stopped';
        }
        // Replied, claimed, lost or asked us to stop: the sequence ends.
        if (! in_array($prospect->stage, ['new', 'contacted'], true)) {
            $enrollment->stop($prospect->stage);

            return 'stopped';
        }
        if ($this->suppression->isSuppressed($prospect->email, $prospect->phone)) {
            $enrollment->stop('suppressed');

            return 'stopped';
        }
        // Signed up on their own since we found them.
        if (DB::table('users')->whereRaw('LOWER(email) = ?', [$prospect->email_normalized])->exists()) {
            $enrollment->stop('has_account');

            return 'stopped';
        }

        $step = (int) $enrollment->next_step;
        // Already sent by an earlier run (the unique key guards this too): move on.
        if (OutreachMessage::where(['campaign_id' => $campaign->id, 'step' => $step, 'prospect_id' => $prospect->id])->exists()) {
            $this->advance($enrollment, $steps);

            return 'stopped';
        }

        $unsubscribe = Str::random(48);
        $message = new OutreachMessage([
            'campaign_id' => $campaign->id,
            'prospect_id' => $prospect->id,
            'step' => $step,
            'to_masked' => ListingClaimService::maskEmail($prospect->email),
            'to_hash' => hash('sha256', $prospect->email_normalized),
            'status' => 'sending',
            'unsubscribe_hash' => hash('sha256', $unsubscribe),
        ]);
        $message->save();

        $link = $this->linkFor($prospect, $campaign, $message);
        [$subject, $body] = $this->render($steps[$step], $prospect, $link, $campaign->language);

        try {
            Mail::mailer(config('outreach.mailer'))->to($prospect->email)->send(new OutreachEmail(
                $subject,
                $body,
                $this->unsubscribeApiUrl($unsubscribe),
                config('app.frontend_url').'/unsubscribe/'.$unsubscribe,
                $this->card($prospect, $link, $campaign->language),
                $campaign->language,
            ));
            $message->forceFill(['status' => 'sent'])->save();
        } catch (Throwable $e) {
            $message->forceFill(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 480)])->save();
            $enrollment->stop('send_failed');

            return 'failed';
        }

        $prospect->forceFill(['stage' => 'contacted', 'last_contacted_at' => now()])->save();
        $this->advance($enrollment, $steps);

        return 'sent';
    }

    private function advance(OutreachEnrollment $enrollment, array $steps): void
    {
        $next = $enrollment->next_step + 1;
        if ($next >= count($steps)) {
            $enrollment->forceFill(['next_step' => $next, 'status' => 'completed', 'next_send_at' => null])->save();

            return;
        }
        $enrollment->forceFill([
            'next_step' => $next,
            'next_send_at' => now()->addDays(max(1, (int) ($steps[$next]['delayDays'] ?? 3))),
        ])->save();
    }

    /** The claim link when we built their listing, else the sign-up page. */
    private function linkFor(Prospect $prospect, OutreachCampaign $campaign, ?OutreachMessage $message): string
    {
        $school = $prospect->school;
        if ($school && $school->listing_status === 'unclaimed') {
            return $this->claims->issue($school, $prospect, $message?->id)['url'];
        }

        $page = $prospect->type === 'trainer' ? '/for-trainers' : '/for-schools';

        return config('app.frontend_url').$page.'?'.http_build_query([
            'utm_source' => 'outreach', 'utm_medium' => 'email', 'utm_campaign' => 'c'.$campaign->id,
        ]);
    }

    /** @return array{0: string, 1: string} subject and body with placeholders filled */
    public function render(array $step, Prospect $prospect, string $link, string $lang = 'en'): array
    {
        $copy = config('outreach_copy.'.$lang) ?? config('outreach_copy.en');
        $locality = $prospect->locality?->name ?? 'Pune';
        $vars = [
            'name' => $prospect->name,
            'contact' => $prospect->contact_person ?: $copy['contact_fallback'],
            'locality' => $locality,
            'link' => $link,
            'listing' => $copy['listing'][$prospect->type] ?? $copy['listing']['school'],
            'nearby_line' => $this->nearbyLine($prospect, $copy, $locality),
        ];
        $fill = fn (string $text) => trim(preg_replace("/[ \t]+\n/", "\n", preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $text)));

        return [$fill((string) $step['subject']), $fill((string) $step['body'])];
    }

    /**
     * A true sentence about live listings in their locality, or nothing
     * when there are none (we never invent numbers).
     */
    private function nearbyLine(Prospect $prospect, array $copy, string $locality): string
    {
        if (! $prospect->locality_id) {
            return '';
        }
        $count = School::query()->public()->where('locality_id', $prospect->locality_id)->count();

        return match (true) {
            $count === 0 => '',
            $count === 1 => strtr($copy['nearby_one'], [':locality' => $locality]),
            default => strtr($copy['nearby_many'], [':locality' => $locality, ':count' => (string) $count]),
        };
    }

    /** @return array{name: string, place: string, type: string, link: string, claim: bool} */
    private function card(Prospect $prospect, string $link, string $lang): array
    {
        $copy = config('outreach_copy.'.$lang) ?? config('outreach_copy.en');

        return [
            'name' => $prospect->name,
            'place' => ($prospect->locality?->name ?? 'Pune').', Pune',
            'type' => $copy['listing'][$prospect->type] ?? $copy['listing']['school'],
            'link' => $link,
            'claim' => str_contains($link, '/claim/'),
        ];
    }

    /** Sends one step to an admin, with sample data and no tracking. */
    public function sendTest(OutreachCampaign $campaign, int $step, string $to): void
    {
        $sample = new Prospect([
            'type' => $campaign->audience,
            'name' => $campaign->audience === 'trainer' ? 'Meena Deshpande' : 'Sai Motor Driving School',
            'contact_person' => 'Sunil',
        ]);
        $link = config('app.frontend_url').'/claim/EXAMPLE';
        [$subject, $body] = $this->render($campaign->steps[$step], $sample, $link, $campaign->language);

        Mail::mailer(config('outreach.mailer'))->to($to)->send(new OutreachEmail(
            '[Test] '.$subject, $body, null, config('app.frontend_url').'/unsubscribe/EXAMPLE',
            $this->card($sample, $link, $campaign->language), $campaign->language,
        ));
    }

    /** One-click unsubscribe from an email: never write to them again. */
    public function unsubscribe(string $token): bool
    {
        $message = OutreachMessage::where('unsubscribe_hash', hash('sha256', $token))->with('prospect')->first();
        if (! $message) {
            return false;
        }

        $prospect = $message->prospect;
        if ($prospect && $prospect->stage !== 'claimed') {
            $this->prospects->markDoNotContact($prospect, 'unsubscribed');
        } elseif ($prospect) {
            $this->suppression->suppress($prospect->email, null, 'unsubscribed');
        }
        OutreachEnrollment::where('prospect_id', $message->prospect_id)->where('status', 'active')
            ->get()->each->stop('unsubscribed');

        return true;
    }

    /** Admin marks an address as bounced or a spam complaint. */
    public function markUndeliverable(OutreachMessage $message, string $reason): void
    {
        $message->forceFill(['status' => 'bounced', 'error' => $reason])->save();
        if ($prospect = $message->prospect) {
            $this->suppression->suppress($prospect->email, null, $reason);
            OutreachEnrollment::where('prospect_id', $prospect->id)->where('status', 'active')->get()->each->stop($reason);
        }
    }

    private function unsubscribeApiUrl(string $token): string
    {
        return rtrim(config('app.url'), '/').'/api/outreach/unsubscribe/'.$token;
    }

    /** @return array<string, int> funnel numbers per campaign */
    public function stats(OutreachCampaign $campaign): array
    {
        $messages = DB::table('outreach_messages')->where('campaign_id', $campaign->id);
        $enrollments = DB::table('outreach_enrollments')->where('campaign_id', $campaign->id);
        $prospectIds = (clone $enrollments)->select('prospect_id');

        return [
            'enrolled' => (clone $enrollments)->count(),
            'active' => (clone $enrollments)->where('status', 'active')->count(),
            'sent' => (clone $messages)->where('status', 'sent')->count(),
            'failed' => (clone $messages)->whereIn('status', ['failed', 'bounced'])->count(),
            'clicked' => (clone $messages)->whereNotNull('clicked_at')->distinct()->count('prospect_id'),
            'claimed' => DB::table('prospects')->whereIn('id', $prospectIds)->where('stage', 'claimed')->count(),
            'unsubscribed' => (clone $enrollments)->where('stop_reason', 'unsubscribed')->count(),
        ];
    }

    /** Records that the link in an outreach email was opened (DIQ-1104 claim page). */
    public static function markClicked(?int $outreachMessageId): void
    {
        if ($outreachMessageId) {
            OutreachMessage::whereKey($outreachMessageId)->whereNull('clicked_at')->update(['clicked_at' => now()]);
        }
    }
}
