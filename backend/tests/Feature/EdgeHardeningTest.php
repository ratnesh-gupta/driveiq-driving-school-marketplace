<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TrustedProxies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** BSEC-02 trusted proxies, BSEC-10 throttles, BSEC-11 login limits, BSEC-28 headers. */
class EdgeHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_proxy_setting_defaults_to_nobody_and_parses_lists(): void
    {
        $this->assertSame([], TrustedProxies::parse(null));
        $this->assertSame([], TrustedProxies::parse('  '));
        $this->assertSame('*', TrustedProxies::parse('*'));
        $this->assertSame(['10.0.0.0/8', '172.18.0.1'], TrustedProxies::parse('10.0.0.0/8, 172.18.0.1'));
    }

    public function test_untrusted_forwarded_for_header_is_ignored(): void
    {
        $seen = null;
        \Route::get('/api/_ip', function (Request $r) use (&$seen) {
            $seen = $r->ip();

            return 'ok';
        });

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeader('X-Forwarded-For', '1.2.3.4')
            ->get('/api/_ip')->assertOk();

        $this->assertSame('203.0.113.9', $seen);
    }

    public function test_public_reads_are_throttled_per_ip(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/plans')->assertOk();
        }
        $this->getJson('/api/plans')->assertStatus(429);
    }

    public function test_schools_offset_has_a_ceiling(): void
    {
        $this->getJson('/api/schools?offset=1000000')->assertUnprocessable();
    }

    public function test_default_api_limiter_is_per_user(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $other = User::factory()->create(['role' => 'learner']);

        Sanctum::actingAs($user);
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/auth/me')->assertOk();
        }
        $this->getJson('/api/auth/me')->assertStatus(429);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($other);
        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_login_is_limited_per_account_across_ips(): void
    {
        User::factory()->create(['email' => 'target@example.com', 'password' => Hash::make('right-password')]);

        $statuses = [];
        // 20 attempts per hour per account, from a different IP each time (bypasses the IP and IP+email keys).
        for ($i = 0; $i < 22; $i++) {
            $statuses[] = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($i + 1)])
                ->postJson('/api/auth/login', ['email' => 'Target@Example.com', 'password' => 'wrong-pass-'.$i])
                ->getStatusCode();
        }

        $this->assertSame(422, $statuses[0]);
        $this->assertSame(429, $statuses[21]);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
            ->postJson('/api/auth/login', ['email' => 'target@example.com', 'password' => 'right-password'])
            ->assertStatus(429);
    }

    public function test_array_email_on_login_is_a_validation_error_not_a_500(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['a@b.c'], 'password' => 'x'])->assertUnprocessable();
    }

    public function test_responses_carry_security_headers_and_authenticated_ones_are_not_cacheable(): void
    {
        $res = $this->getJson('/api/healthz')->assertOk();
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('Referrer-Policy', 'no-referrer');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
        $this->assertNotNull($res->headers->get('Permissions-Policy'));
        $this->assertNull($res->headers->get('Strict-Transport-Security'));

        $secure = $this->get('https://localhost/api/healthz');
        $this->assertNotNull($secure->headers->get('Strict-Transport-Security'));

        $user = User::factory()->create(['role' => 'learner']);
        $token = $user->createToken('t')->plainTextToken;
        $me = $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->assertStringContainsString('no-store', $me->headers->get('Cache-Control'));
    }
}
