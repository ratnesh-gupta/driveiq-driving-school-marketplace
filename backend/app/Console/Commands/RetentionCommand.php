<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ErasureService;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** DIQ-606: delete personal data past its retention period (dry run unless --execute). */
class RetentionCommand extends Command
{
    protected $signature = 'driveiq:retention {--execute : Delete the rows instead of only reporting them}';

    protected $description = 'Apply the DPDP retention schedule (config/retention.php); reports only unless --execute';

    public function handle(): int
    {
        $execute = $this->option('execute') || (bool) config('retention.execute');
        $holds = config('retention.legal_hold_school_ids', []);
        $months = config('retention.months');

        $this->info($execute ? 'Retention: deleting expired rows.' : 'Retention: dry run (nothing deleted; pass --execute to apply).');
        if ($holds) {
            $this->line('Legal hold on school ids: '.implode(', ', $holds));
        }

        $jobs = [
            // Leads with no activity for the period (status history cascades;
            // learner/review links to the inquiry are set to null).
            'inquiries' => fn () => $this->excludingHolds(
                DB::table('inquiries')->where('updated_at', '<', now()->subMonthsNoOverflow($months['inquiries'])),
                $holds
            ),
            'messages' => fn () => $this->excludingHolds(
                DB::table('messages')->where('created_at', '<', now()->subMonthsNoOverflow($months['messages'])),
                $holds
            ),
            'contact_messages' => fn () => DB::table('contact_messages')
                ->where('status', 'closed')
                ->where('updated_at', '<', now()->subMonthsNoOverflow($months['contact_messages'])),
            // WhatsApp / SMS attempt log (DIQ-1006): numbers are masked, kept briefly.
            'outbound_messages' => fn () => DB::table('outbound_messages')
                ->where('created_at', '<', now()->subDays((int) config('messaging.log_retention_days', 90))),
            // Outreach contacts who never came on board (DIQ-1103). The
            // do-not-contact list keeps only hashes, so it outlives them.
            'prospects' => fn () => DB::table('prospects')
                ->where('stage', '!=', 'claimed')
                ->where('updated_at', '<', now()->subMonthsNoOverflow($months['prospects'])),
            'outreach_messages' => fn () => DB::table('outreach_messages')
                ->where('created_at', '<', now()->subMonthsNoOverflow($months['outreach_messages'])),
            'data_subject_requests' => fn () => DB::table('data_subject_requests')
                ->whereIn('status', ['completed', 'rejected'])
                ->where('handled_at', '<', now()->subMonthsNoOverflow($months['data_subject_requests'])),
        ];

        // WP-8 jobs: only when their window is > 0.
        if (($months['notifications'] ?? 0) > 0) {
            $jobs['notifications'] = fn () => $this->excludingHolds(
                DB::table('notifications')->where('created_at', '<', now()->subMonthsNoOverflow($months['notifications'])),
                $holds
            );
        }
        if (($months['payments'] ?? 0) > 0) {
            $cut = now()->subMonthsNoOverflow($months['payments']);
            $jobs['payments'] = fn () => $this->excludingHolds(DB::table('payments')->where('created_at', '<', $cut), $holds);
            // Learner invoices go once old and with no younger payment attached.
            $jobs['invoices (learner)'] = fn () => $this->excludingHolds(
                DB::table('invoices')->where('created_at', '<', $cut)
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('payments')->whereColumn('payments.invoice_id', 'invoices.id')->where('payments.created_at', '>=', $cut)),
                $holds
            );
        }
        if (($months['training_records'] ?? 0) > 0) {
            $stale = fn () => $this->excludingHolds(
                DB::table('learners')->where('status', '!=', 'active')
                    ->where('updated_at', '<', now()->subMonthsNoOverflow($months['training_records']))
                    ->select('id'),
                $holds
            );
            foreach (['training_progress', 'driving_tests', 'learner_assignment_history', 'schedules'] as $table) {
                $jobs["{$table} (training)"] = fn () => DB::table($table)->whereIn('learner_id', $stale());
            }
        }

        $rows = [];
        foreach ($jobs as $table => $query) {
            $count = $query()->count();
            if ($execute && $count > 0) {
                DB::transaction(fn () => $query()->delete());
            }
            $rows[] = [$table, $count, $execute ? 'deleted' : 'would delete'];
        }

        if ($execute) {
            // Threads left with no messages.
            $emptyThreads = $this->excludingHolds(
                DB::table('message_threads')->whereNotExists(
                    fn ($q) => $q->select(DB::raw(1))->from('messages')->whereColumn('messages.thread_id', 'message_threads.id')
                ),
                $holds
            )->where('updated_at', '<', now()->subMonthsNoOverflow($months['messages']))->delete();
            $rows[] = ['message_threads (empty)', $emptyThreads, 'deleted'];
        }

        // Minimisation and account jobs update instead of delete.
        $rows = array_merge($rows, $this->minimise($months, $holds, $execute));

        $this->table(['Table', 'Rows', 'Action'], $rows);
        $this->line('audit_logs and platform_invoices are never deleted by this job.');

        return self::SUCCESS;
    }

    /** @return array<int, array{0: string, 1: int, 2: string}> */
    private function minimise(array $months, array $holds, bool $execute): array
    {
        $rows = [];
        $verb = fn (string $base) => $execute ? rtrim($base, 'e').'ed' : 'would '.$base;

        if (($months['consent_details'] ?? 0) > 0) {
            $q = fn () => DB::table('consents')
                ->where('created_at', '<', now()->subMonthsNoOverflow($months['consent_details']))
                ->where(fn ($w) => $w->whereNotNull('ip_address')->orWhereNotNull('user_agent'));
            $count = $q()->count();
            if ($execute && $count > 0) {
                $q()->update(['ip_address' => null, 'user_agent' => null]);
            }
            $rows[] = ['consents (ip/user agent)', $count, $verb('clear')];
        }

        // Tokens of deactivated (or erased) users never expire usefully; drop them.
        $tokens = fn () => DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', DB::table('users')->whereNotNull('deactivated_at')->select('id'));
        $count = $tokens()->count();
        if ($execute && $count > 0) {
            $tokens()->delete();
        }
        $rows[] = ['personal_access_tokens (deactivated users)', $count, $verb('delete')];

        if (($months['accounts_inactive'] ?? 0) > 0) {
            $cut = now()->subMonthsNoOverflow($months['accounts_inactive']);
            $inactive = User::query()->withoutGlobalScopes()
                ->where('role', 'learner')
                ->whereNull('deactivated_at')
                ->where('updated_at', '<', $cut)
                ->where('email', 'not like', '%@'.ErasureService::PLACEHOLDER_DOMAIN)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('personal_access_tokens')
                    ->whereColumn('personal_access_tokens.tokenable_id', 'users.id')
                    ->where('personal_access_tokens.tokenable_type', User::class)
                    ->where(fn ($w) => $w->where('last_used_at', '>=', $cut)->orWhere('created_at', '>=', $cut)))
                ->get();

            $erasure = app(ErasureService::class);
            $done = 0;
            $blocked = 0;
            foreach ($inactive as $user) {
                $result = $erasure->erase($user, $execute);
                $result['status'] === 'blocked' ? $blocked++ : $done++;
            }
            $rows[] = ['users (inactive learners)', $done, $verb('anonymise')];
            if ($blocked > 0) {
                $rows[] = ['users (skipped: legal hold)', $blocked, 'kept'];
            }
        }

        return $rows;
    }

    private function excludingHolds(Builder $query, array $holds): Builder
    {
        return $holds ? $query->whereNotIn('school_id', $holds) : $query;
    }
}
