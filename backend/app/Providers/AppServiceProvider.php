<?php

namespace App\Providers;

use App\Messaging\MessageSender;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // WhatsApp / SMS driver (DIQ-1001), chosen by MESSAGING_DRIVER.
        $this->app->bind(MessageSender::class, function () {
            $driver = config('messaging.driver', 'log');
            $class = config("messaging.drivers.{$driver}")
                ?? throw new \InvalidArgumentException("Unknown MESSAGING_DRIVER [{$driver}]");

            return $this->app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));

        // Password reset emails link to the SPA, not a Laravel web route.
        ResetPassword::createUrlUsing(fn ($user, string $token) => config('app.frontend_url')
            .'/auth/reset-password?token='.urlencode($token).'&email='.urlencode($user->getEmailForPasswordReset()));

        // Deactivated accounts (DIQ-602): any token they still hold is refused.
        Sanctum::authenticateAccessTokensUsing(
            fn ($accessToken, bool $isValid) => $isValid && $accessToken->tokenable?->deactivated_at === null
        );

        $this->configureRateLimiting();
    }

    /**
     * Named limiters for public (unauthenticated) mutations and lookups
     * (DIQ-404/405). Routes refer to them as throttle:<name>.
     */
    private function configureRateLimiting(): void
    {
        // Login / register / password reset: per IP, and per IP+email so one
        // address cannot be hammered while staying under the IP limit.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('auth-ip:'.$request->ip()),
            Limit::perMinute(5)->by('auth:'.$request->ip().'|'.strtolower((string) $request->input('email'))),
        ]);

        // Lead form spam (PROJECT-PLAN 0.3: 5 per minute per IP).
        RateLimiter::for('inquiries', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Other public writes: data-subject requests, review links, invite acceptance.
        RateLimiter::for('public-forms', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Consent records: a few per page view at most (register, portal gate, cookie banner).
        RateLimiter::for('consents', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Public token lookups (invite / review link previews).
        RateLimiter::for('public-lookups', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
