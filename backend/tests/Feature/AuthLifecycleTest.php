<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** DIQ-401 token expiry, DIQ-402 password reset. */
class AuthLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_expire_after_configured_lifetime(): void
    {
        config(['sanctum.expiration' => 60]);
        $user = User::factory()->create(['role' => 'learner']);
        $token = $user->createToken('api-token');

        $this->withToken($token->plainTextToken)->getJson('/api/auth/me')->assertOk();

        $token->accessToken->forceFill(['created_at' => now()->subMinutes(61)])->save();
        $this->app['auth']->forgetGuards();

        $this->withToken($token->plainTextToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_forgot_password_does_not_reveal_whether_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/auth/forgot-password', ['email' => 'known@example.com'])->assertOk();
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_starts_with($url, config('app.frontend_url').'/auth/reset-password?token=')
                && str_contains($url, 'email=known%40example.com');
        });
    }

    public function test_reset_password_changes_password_and_revokes_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $user->createToken('old-session');

        $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassw0rd',
            'password_confirmation' => 'NewPassw0rd',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassw0rd', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());

        // The token is single-use.
        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'Another1Pass',
            'password_confirmation' => 'Another1Pass',
        ])->assertStatus(422);
    }

    public function test_reset_password_rejects_bad_token_and_weak_password(): void
    {
        User::factory()->create(['email' => 'weak@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'weak@example.com',
            'password' => 'GoodPassw0rd',
            'password_confirmation' => 'GoodPassw0rd',
        ])->assertStatus(422)->assertJsonPath('message', 'This reset link is invalid or has expired.');

        $this->postJson('/api/auth/reset-password', [
            'token' => 'x',
            'email' => 'weak@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
