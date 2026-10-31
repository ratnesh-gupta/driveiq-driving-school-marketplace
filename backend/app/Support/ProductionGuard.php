<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * BSEC-16: refuse to boot in production with development defaults.
 *
 * Only runs when APP_ENV=production. Messages name the setting, never its
 * value (values may be secrets). Build-time artisan commands that run before
 * the runtime environment is injected (package:discover, config:cache, ...)
 * are exempt, see EXEMPT_COMMANDS.
 */
class ProductionGuard
{
    /** Commands that legitimately run at image build time without runtime env. */
    private const EXEMPT_COMMANDS = [
        'package:discover', 'key:generate', 'list', 'help', 'about',
        'config:cache', 'config:clear', 'route:cache', 'route:clear',
        'event:cache', 'event:clear', 'view:cache', 'view:clear',
        'optimize', 'optimize:clear', 'storage:link',
    ];

    /** @return array<int, string> human readable problems; empty when safe */
    public static function violations(Application $app): array
    {
        if (! $app->environment('production')) {
            return [];
        }

        $config = $app['config'];
        $problems = [];

        if ($config->get('app.debug')) {
            $problems[] = 'APP_DEBUG must be false in production.';
        }

        if (! is_string($config->get('app.key')) || trim($config->get('app.key')) === '') {
            $problems[] = 'APP_KEY is not set.';
        }

        $mailer = (string) $config->get('mail.default');
        if (in_array($mailer, ['log', 'array', ''], true)) {
            $problems[] = 'MAIL_MAILER must be a real mailer (not log/array) in production.';
        }

        $messaging = $config->get('messaging.driver');
        if ($config->get('messaging.require_real', true) && ($messaging === null || $messaging === '' || $messaging === 'log')) {
            $problems[] = 'MESSAGING_DRIVER must not be unset or "log" while MESSAGING_REQUIRE_REAL=true (use a real provider, or "null" to send nothing).';
        }

        foreach ((array) $config->get('cors.allowed_origins', []) as $origin) {
            if (! is_string($origin) || trim($origin) === '' || str_contains($origin, '*')) {
                $problems[] = 'CORS allowed origins must be explicit (no wildcard or empty entries).';
                break;
            }
        }
        if (((array) $config->get('cors.allowed_origins', [])) === [] && ((array) $config->get('cors.allowed_origins_patterns', [])) === []) {
            $problems[] = 'CORS allowed origins are empty; set FRONTEND_URL or CORS_ALLOWED_ORIGINS.';
        }
        foreach ((array) $config->get('cors.allowed_origins_patterns', []) as $pattern) {
            if (is_string($pattern) && in_array(trim($pattern), ['#.*#', '/.*/', '#^.*$#', '/^.*$/', '.*'], true)) {
                $problems[] = 'CORS origin patterns must not match every origin.';
                break;
            }
        }

        $connection = (string) $config->get('database.default');
        $driver = (string) $config->get("database.connections.{$connection}.driver");
        if ($driver !== 'pgsql') {
            $problems[] = 'DB_CONNECTION must be pgsql (PostgreSQL/PostGIS); sqlite and others are not supported in production.';
        }

        $key = (string) $config->get('services.google_ads.webhook_key', '');
        if ($key !== '' && strlen($key) < 32) {
            $problems[] = 'GOOGLE_ADS_WEBHOOK_KEY must be at least 32 characters when set.';
        }

        return $problems;
    }

    /** @throws RuntimeException */
    public static function assert(Application $app): void
    {
        $problems = self::violations($app);
        if ($problems !== []) {
            throw new RuntimeException("Refusing to start in production:\n - ".implode("\n - ", $problems));
        }
    }

    /** True when the current CLI invocation is a build-time command. */
    public static function isExemptCommand(): bool
    {
        if (PHP_SAPI !== 'cli') {
            return false;
        }
        $command = $_SERVER['argv'][1] ?? null;

        return $command === null || in_array($command, self::EXEMPT_COMMANDS, true);
    }
}
