<?php

namespace App\Console\Commands;

use App\Services\OutreachService;
use Illuminate\Console\Command;

/** DIQ-1105: sends the outreach emails that are due, within the daily cap and sending hours. */
class OutreachCommand extends Command
{
    protected $signature = 'driveiq:outreach';

    protected $description = 'Send due school/trainer outreach emails (daily cap, business hours, suppression list)';

    public function handle(OutreachService $outreach): int
    {
        if (! $outreach->withinSendingHours()) {
            $this->info('Outside sending hours; nothing sent.');

            return self::SUCCESS;
        }

        $r = $outreach->sendDue();
        $this->info("Outreach: {$r['sent']} sent, {$r['stopped']} sequences stopped, {$r['failed']} failed.");

        return self::SUCCESS;
    }
}
