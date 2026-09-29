<?php

use App\Models\User;
use App\Services\LeadResponseStats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('driveiq:create-admin {email} {--name=Platform Admin}', function (string $email) {
    // Platform admins cannot self-register; this is the supported way to create one.
    if (User::where('email', $email)->exists()) {
        $this->error("A user with email {$email} already exists.");

        return 1;
    }

    $password = $this->secret('Password (min 8, mixed case, numbers)');
    $validator = Validator::make(['password' => $password], [
        'password' => ['required', Password::min(8)->mixedCase()->numbers()],
    ]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first('password'));

        return 1;
    }

    User::create([
        'name' => $this->option('name'),
        'email' => $email,
        'password' => $password,
        'role' => 'admin',
    ]);

    $this->info("Platform admin {$email} created.");

    return 0;
})->purpose('Create a platform admin account');

// Remove API tokens that expired more than a day ago (config sanctum.expiration).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// DPDP retention (DIQ-606): reports daily; deletes only when RETENTION_EXECUTE=true.
Schedule::command('driveiq:retention')->dailyAt('02:30')->withoutOverlapping();

// Remind schools about leads still waiting for a first reply (DIQ-705).
Schedule::command('driveiq:lead-reminders')->everyFifteenMinutes()->withoutOverlapping();

// Public "usually replies within" badges (DIQ-708).
Schedule::call(fn () => app(LeadResponseStats::class)->refreshSchoolBadges())
    ->name('driveiq:response-badges')->hourly()->withoutOverlapping();
