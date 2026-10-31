<?php

namespace App\Providers;

use App\Messaging\MessageSender;
use App\Notifications\Channels\WhatsAppChannel;
use App\Support\Phone;
use App\Support\ProductionGuard;
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
            // env() turns MESSAGING_DRIVER=null into a real null: that means the null driver.
            $driver = config('messaging.driver') ?? 'null';
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
        // BSEC-16/25: never run production on development defaults.
        if (! ProductionGuard::isExemptCommand()) {
            ProductionGuard::assert($this->app);
        }

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
        // BSEC-11: plus a per-account key (any IP) so a botnet cannot grind one account.
        RateLimiter::for('auth', function (Request $request) {
            $input = $request->input('email');
            $email = is_string($input) ? strtolower(trim($input)) : '';

            return [
                Limit::perMinute(10)->by('auth-ip:'.$request->ip()),
                Limit::perMinute(5)->by('auth:'.$request->ip().'|'.sha1($email)),
                Limit::perHour(20)->by('auth-account:'.sha1($email)),
            ];
        });

        // BSEC-10: default API limiter (per user when signed in, else per IP).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user('sanctum')?->getAuthIdentifier() ? 'u:'.$request->user('sanctum')->getAuthIdentifier() : 'ip:'.$request->ip()));

        // BSEC-10: anonymous catalogue reads (listings, reviews, packages, stats).
        RateLimiter::for('public-reads', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Lead form spam (PROJECT-PLAN 0.3: 5 per minute per IP).
        // BSEC-03: also bucketed per recipient so one number/address cannot be
        // pumped from many IPs (keys are hashes, never raw PII).
        RateLimiter::for('inquiries', function (Request $request) {
            $phone = is_string($request->input('phone')) ? Phone::key($request->input('phone')) : '';
            $email = is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : '';
            $limits = [Limit::perMinute(5)->by($request->ip())];
            if ($phone !== '') {
                $limits[] = Limit::perDay(6)->by('inq-phone:'.sha1($phone));
            }
            if ($email !== '') {
                $limits[] = Limit::perDay(4)->by('inq-email:'.sha1($email));
            }

            return $limits;
        });

        // Other public writes: data-subject requests, review links, invite acceptance.
        RateLimiter::for('public-forms', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Consent records: a few per page view at most (register, portal gate, cookie banner).
        RateLimiter::for('consents', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Public token lookups (invite / review link previews).
        RateLimiter::for('public-lookups', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
