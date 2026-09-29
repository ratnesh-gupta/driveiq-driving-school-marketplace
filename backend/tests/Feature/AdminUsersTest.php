<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-602: admin user list + deactivation. */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Platform Admin']);
    }

    public function test_only_admins_can_list_or_change_users(): void
    {
        $school = User::factory()->create(['role' => 'school']);
        $learner = User::factory()->create(['role' => 'learner']);

        foreach ([$school, $learner] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/users')->assertForbidden();
            $this->patchJson("/api/admin/users/{$this->admin->id}", ['active' => false])->assertForbidden();
        }
    }

    public function test_list_supports_search_role_and_status_filters(): void
    {
        User::factory()->create(['role' => 'learner', 'name' => 'Asha Patil', 'email' => 'asha@example.com']);
        User::factory()->create(['role' => 'school', 'name' => 'Skyline Academy', 'email' => 'info@skyline.test']);
        User::factory()->create(['role' => 'learner', 'name' => 'Old Account', 'deactivated_at' => now()]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/users')->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson('/api/admin/users?search=SKYLINE')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Skyline Academy');
        $this->getJson('/api/admin/users?search=asha@')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/users?role=learner')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/admin/users?status=deactivated')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.active', false);
        // LIKE wildcards in the search term are literal.
        $this->getJson('/api/admin/users?search=%25')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/users?role=superuser')->assertUnprocessable();

        $this->assertArrayNotHasKey('password', $this->getJson('/api/admin/users')->json('data.0'));
    }

    public function test_deactivation_revokes_tokens_and_blocks_login_until_reactivated(): void
    {
        $user = User::factory()->create(['role' => 'learner', 'email' => 'ravi@example.com', 'password' => 'Passw0rdX']);
        $token = $this->postJson('/api/auth/login', ['email' => 'ravi@example.com', 'password' => 'Passw0rdX'])
            ->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->app['auth']->forgetGuards();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/users/{$user->id}", ['active' => false])
            ->assertOk()->assertJsonPath('active', false);
        $this->app['auth']->forgetGuards();

        $this->assertSame(0, $user->tokens()->count());
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'ravi@example.com', 'password' => 'Passw0rdX'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/users/{$user->id}", ['active' => true])->assertOk()->assertJsonPath('active', true);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', ['email' => 'ravi@example.com', 'password' => 'Passw0rdX'])->assertOk();

        $this->assertSame(
            ['deactivate', 'reactivate'],
            AuditLog::where('model_type', 'User')->where('model_id', $user->id)->orderBy('id')->pluck('action')->all()
        );
    }

    public function test_a_token_created_outside_login_is_refused_once_deactivated(): void
    {
        $user = User::factory()->create(['role' => 'school', 'deactivated_at' => now()]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_admin_cannot_deactivate_themself(): void
    {
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/admin/users/{$this->admin->id}", ['active' => false])->assertUnprocessable();
        $this->assertTrue($this->admin->fresh()->isActive());
    }
}
