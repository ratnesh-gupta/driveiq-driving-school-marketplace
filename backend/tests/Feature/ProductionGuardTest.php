<?php

namespace Tests\Feature;

use App\Messaging\Drivers\LogSender;
use App\Models\School;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\ProductionGuard;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoShowcaseSeeder;
use Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** BSEC-16 production boot guard, demo refusal; BSEC-25 error handler. */
class ProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function production(array $overrides = []): void
    {
        $this->app['env'] = 'production';
        config(array_merge([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'mail.default' => 'smtp',
            'messaging.driver' => 'null',
            'messaging.require_real' => true,
            'cors.allowed_origins' => ['https://app.driveq.in'],
            'cors.allowed_origins_patterns' => [],
            'database.default' => 'pgsql',
            'services.google_ads.webhook_key' => null,
        ], $overrides));
    }

    public function test_safe_production_config_has_no_violations(): void
    {
        $this->production();
        $this->assertSame([], ProductionGuard::violations($this->app));
        ProductionGuard::assert($this->app);
    }

    public function test_guard_is_inert_outside_production(): void
    {
        config(['app.debug' => true, 'mail.default' => 'log', 'app.key' => '']);
        $this->assertSame([], ProductionGuard::violations($this->app));
    }

    #[DataProvider('unsafeSettings')]
    public function test_each_unsafe_setting_blocks_boot(array $override, string $mentions): void
    {
        $this->production($override);
        $problems = ProductionGuard::violations($this->app);
        $this->assertNotEmpty($problems);
        $this->assertStringContainsString($mentions, implode(' ', $problems));

        $thrown = null;
        try {
            (new AppServiceProvider($this->app))->boot();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }
        config(['database.default' => 'pgsql']); // keep RefreshDatabase teardown on Postgres
        $this->assertNotNull($thrown);
    }

    public static function unsafeSettings(): array
    {
        return [
            'debug' => [['app.debug' => true], 'APP_DEBUG'],
            'no key' => [['app.key' => ''], 'APP_KEY'],
            'log mailer' => [['mail.default' => 'log'], 'MAIL_MAILER'],
            'array mailer' => [['mail.default' => 'array'], 'MAIL_MAILER'],
            'log messaging' => [['messaging.driver' => 'log'], 'MESSAGING_DRIVER'],
            'unset messaging' => [['messaging.driver' => null], 'MESSAGING_DRIVER'],
            'wildcard cors' => [['cors.allowed_origins' => ['*']], 'CORS'],
            'empty cors' => [['cors.allowed_origins' => []], 'CORS'],
            'match-all cors pattern' => [['cors.allowed_origins_patterns' => ['#.*#']], 'CORS'],
            'sqlite' => [['database.default' => 'sqlite'], 'DB_CONNECTION'],
            'short ads key' => [['services.google_ads.webhook_key' => 'short'], 'GOOGLE_ADS_WEBHOOK_KEY'],
        ];
    }

    public function test_log_messaging_is_allowed_only_when_real_sends_are_not_required(): void
    {
        $this->production(['messaging.driver' => 'log', 'messaging.require_real' => false]);
        $this->assertSame([], ProductionGuard::violations($this->app));
    }

    public function test_failure_message_names_settings_not_values(): void
    {
        $this->production(['app.debug' => true, 'services.google_ads.webhook_key' => 'secret-short']);
        try {
            ProductionGuard::assert($this->app);
            $this->fail('expected refusal');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('APP_DEBUG', $e->getMessage());
            $this->assertStringNotContainsString('secret-short', $e->getMessage());
        }
    }

    public function test_demo_seeders_and_command_refuse_production(): void
    {
        $this->app['env'] = 'production';
        foreach ([DatabaseSeeder::class, DemoShowcaseSeeder::class, UsersSeeder::class] as $seeder) {
            try {
                $this->app->make($seeder)->run();
                $this->fail("$seeder ran in production");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('production', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('users', 0);

        $this->artisan('driveiq:demo')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_log_driver_masks_one_time_codes(): void
    {
        Log::spy();
        (new LogSender)->send('sms', '+919876543210', [], ['code' => '482913', 'name' => 'X'], '482913 is your code for X');

        Log::shouldHaveReceived('info')->withArgs(fn ($m) => ! str_contains($m, '482913') && str_contains($m, '****'))->once();
    }

    public function test_unknown_errors_return_generic_message_with_request_id_and_no_internals(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/_boom', fn () => throw new \PDOException('SQLSTATE[22P02]: select * from secret_table where token = 1'));
        Log::spy();

        $res = $this->getJson('/api/_boom');

        $res->assertStatus(500)->assertJsonStructure(['message', 'requestId']);
        $body = $res->getContent();
        foreach (['PDOException', 'SQLSTATE', 'secret_table', 'trace', 'file', 'line', 'ProductionGuardTest'] as $leak) {
            $this->assertStringNotContainsString($leak, $body);
        }
        $this->assertSame($res->json('requestId'), $res->headers->get('X-Request-Id'));
        Log::shouldHaveReceived('error')->withArgs(fn ($m, $ctx) => ($ctx['request_id'] ?? null) === $res->json('requestId'))->atLeast()->once();
    }

    public function test_incoming_request_id_is_honoured_only_if_well_formed(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/_boom2', fn () => throw new RuntimeException('x'));

        $this->withHeader('X-Request-Id', 'abc-12345678')->getJson('/api/_boom2')->assertJsonPath('requestId', 'abc-12345678');
        $id = $this->withHeader('X-Request-Id', "bad id\n<script>")->getJson('/api/_boom2')->json('requestId');
        $this->assertMatchesRegularExpression('/^[0-9a-f\-]{36}$/', $id);
    }

    public function test_model_not_found_does_not_leak_model_class(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/_nf', fn () => School::query()->findOrFail(99999999));

        $res = $this->getJson('/api/_nf')->assertStatus(404);
        $this->assertStringNotContainsString('App\\Models', $res->getContent());
    }

    public function test_non_uuid_notification_id_is_a_404_not_a_500(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/not-a-uuid/read')->assertStatus(404);
    }
}
