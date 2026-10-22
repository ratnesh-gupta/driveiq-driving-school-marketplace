<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DIQ-1303: /api/healthz checks real dependencies, not just that PHP runs. */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_stack_reports_every_check_ok(): void
    {
        $this->getJson('/api/healthz')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.queue', 'ok')
            ->assertJsonPath('checks.storage', 'ok');
    }

    public function test_a_broken_dependency_returns_503_and_names_the_check(): void
    {
        config(['filesystems.documents_disk' => 'missing-disk']);

        $this->getJson('/api/healthz')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.storage', 'fail')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonMissingPath('error');
    }
}
