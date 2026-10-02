<?php

namespace App\Console\Commands;

use Database\Seeders\DemoShowcaseSeeder;
use Illuminate\Console\Command;

/**
 * DIQ-1202: fills the database with the demo showcase used for videos,
 * screenshots and sales meetings. Refuses to run in production.
 */
class DemoCommand extends Command
{
    protected $signature = 'driveiq:demo {--fresh : Wipe the database and run all migrations first}';

    protected $description = 'Load the DriveQ demo showcase (never in production)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to load demo data in production.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', ['--force' => true]);
        }
        $this->call('db:seed', ['--class' => DemoShowcaseSeeder::class, '--force' => true]);

        $this->info('Demo ready. Logins (password123): admin@driveiq.in, info@skylinedrive.in (school owner), trainer.skyline@driveiq.in, learner.asha@driveiq.in');

        return self::SUCCESS;
    }
}
