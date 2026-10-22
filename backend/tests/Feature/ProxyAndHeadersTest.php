<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * DIQ-1302: forwarded client IPs are honoured only from configured proxies,
 * so per-IP rate limits neither collapse onto the proxy's address nor can be
 * dodged by a visitor who forges X-Forwarded-For. Security headers on API
 * responses.
 */
class ProxyAndHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = '10.0.0.5';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('auth-ip:203.0.113.1');
    }

    /** Ten failed logins per IP per minute are allowed; the eleventh is throttled. */
    private function attemptLogins(int $count, callable $clientIp, string $remote): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->withServerVariables(['REMOTE_ADDR' => $remote])
                ->withHeader('X-Forwarded-For', $clientIp($i))
                ->postJson('/api/auth/login', ['email' => "nobody{$i}@example.com", 'password' => 'wrong-password'])
                ->status();
        }

        return $codes;
    }

    public function test_behind_a_trusted_proxy_each_visitor_gets_their_own_limit(): void
    {
        config(['app.trusted_proxies' => self::PROXY]);

        // Twelve different visitors, all arriving through the same proxy.
        $codes = $this->attemptLogins(12, fn ($i) => '198.51.100.'.($i + 1), self::PROXY);

        $this->assertNotContains(429, $codes, 'Visitors behind the proxy must not share one rate limit.');
    }

    public function test_forwarded_header_from_an_untrusted_source_is_ignored(): void
    {
        config(['app.trusted_proxies' => self::PROXY]);

        // One client forging a new X-Forwarded-For on every request.
        $codes = $this->attemptLogins(11, fn ($i) => '198.51.100.'.($i + 1), '203.0.113.1');

        $this->assertSame(429, end($codes), 'A forged X-Forwarded-For must not reset the limit.');
    }

    public function test_no_proxy_is_trusted_by_default(): void
    {
        config(['app.trusted_proxies' => '']);

        $ip = $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeader('X-Forwarded-For', '198.51.100.7')
            ->get('/api/healthz')
            ->baseRequest->ip();

        $this->assertSame(self::PROXY, $ip);
    }

    public function test_api_responses_carry_security_headers(): void
    {
        $this->getJson('/api/healthz')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/api/healthz')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
