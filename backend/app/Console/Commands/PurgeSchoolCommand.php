<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PlatformInvoice;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Erases an archived school for good (DIQ-1304). Refuses while its money
 * records are inside the tax retention period (8 years after archiving);
 * after that it deletes them together with everything else of the school.
 * Shows what would go without --force.
 */
class PurgeSchoolCommand extends Command
{
    protected $signature = 'driveiq:purge-school {id : School id} {--force : Actually delete}';

    protected $description = 'Permanently delete an archived school and its records';

    public const FINANCIAL_RETENTION_YEARS = 8;

    public function handle(): int
    {
        $school = School::find((int) $this->argument('id'));
        if (! $school) {
            $this->error('No school with that id.');

            return self::FAILURE;
        }
        if (! $school->archived_at) {
            $this->error("{$school->name} is not archived. Archive it from the admin console first.");

            return self::FAILURE;
        }

        $money = [
            'payments' => Payment::withoutGlobalScope('school')->where('school_id', $school->id)->count(),
            'invoices' => Invoice::withoutGlobalScope('school')->where('school_id', $school->id)->count(),
            'subscriptions' => Subscription::withoutGlobalScope('school')->where('school_id', $school->id)->count(),
            'platform invoices' => PlatformInvoice::where('school_id', $school->id)->count(),
        ];
        $keepUntil = $school->archived_at->copy()->addYears(self::FINANCIAL_RETENTION_YEARS);

        $this->line("{$school->name} (archived {$school->archived_at->toDateString()})");
        foreach ($money as $what => $count) {
            $this->line("  {$what}: {$count}");
        }

        if (array_sum($money) > 0 && now()->lt($keepUntil)) {
            $this->error('Money records must be kept until '.$keepUntil->toDateString().'. Nothing deleted.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('Dry run. Add --force to delete this school and all its records.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($school) {
            PlatformInvoice::where('school_id', $school->id)->delete();
            Payment::withoutGlobalScope('school')->where('school_id', $school->id)->delete();
            Invoice::withoutGlobalScope('school')->where('school_id', $school->id)->delete();
            Subscription::withoutGlobalScope('school')->where('school_id', $school->id)->delete();
            AuditLog::log('purged', 'School', $school->id, $school->only(['name', 'slug']), [], null);
            $school->delete();
        });

        $this->info('Deleted.');

        return self::SUCCESS;
    }
}
