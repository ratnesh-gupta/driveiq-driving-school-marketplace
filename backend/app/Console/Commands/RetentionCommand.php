<?php

namespace App\Console\Commands;

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
                DB::table('inquiries')->where('updated_at', '<', now()->subMonths($months['inquiries'])),
                $holds
            ),
            'messages' => fn () => $this->excludingHolds(
                DB::table('messages')->where('created_at', '<', now()->subMonths($months['messages'])),
                $holds
            ),
            'contact_messages' => fn () => DB::table('contact_messages')
                ->where('status', 'closed')
                ->where('updated_at', '<', now()->subMonths($months['contact_messages'])),
            'data_subject_requests' => fn () => DB::table('data_subject_requests')
                ->whereIn('status', ['completed', 'rejected'])
                ->where('handled_at', '<', now()->subMonths($months['data_subject_requests'])),
        ];

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
            )->where('updated_at', '<', now()->subMonths($months['messages']))->delete();
            $rows[] = ['message_threads (empty)', $emptyThreads, 'deleted'];
        }

        $this->table(['Table', 'Rows', 'Action'], $rows);
        $this->line('audit_logs, payments and training records are never deleted by this job.');

        return self::SUCCESS;
    }

    private function excludingHolds(Builder $query, array $holds): Builder
    {
        return $holds ? $query->whereNotIn('school_id', $holds) : $query;
    }
}
