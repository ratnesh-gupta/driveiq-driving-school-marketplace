<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Password reset emails link to the SPA, not a Laravel web route.
        ResetPassword::createUrlUsing(fn ($user, string $token) => config('app.frontend_url')
            .'/auth/reset-password?token='.urlencode($token).'&email='.urlencode($user->getEmailForPasswordReset()));
    }
}
