<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\DataSubjectRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-604: server-side consent records. */
class ConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_user_records_register_and_portal_consents(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($user);

        $this->postJson('/api/consents', ['consents' => [
            ['purpose' => 'terms', 'version' => '2026-09-29'],
            ['purpose' => 'privacy', 'version' => '2026-09-29'],
            ['purpose' => 'processing', 'version' => '2026-09-29'],
            // The role recorded is the account's own, whatever the client sends.
            ['purpose' => 'role_portal', 'version' => '1', 'role' => 'admin'],
        ]])->assertCreated()->assertJsonCount(4);

        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'purpose' => 'role_portal', 'role' => 'learner']);
        $this->assertDatabaseMissing('consents', ['role' => 'admin']);
        $this->assertNotNull(Consent::first()->ip_address);

        // Re-sending an active grant does not duplicate it.
        $this->postJson('/api/consents', ['consents' => [['purpose' => 'terms', 'version' => '2026-09-29']]])->assertCreated();
        $this->assertSame(4, Consent::count());

        $this->getJson('/api/consents')->assertOk()->assertJsonCount(4);
    }

    public function test_withdrawal_keeps_history_and_a_new_grant_is_a_new_row(): void
    {
        $user = User::factory()->create(['role' => 'school']);
        Sanctum::actingAs($user);
        $this->postJson('/api/consents', ['consents' => [['purpose' => 'processing', 'version' => '1']]])->assertCreated();

        $this->deleteJson('/api/consents/processing')->assertNoContent();
        $this->getJson('/api/consents')->assertOk()->assertJsonCount(0);
        $this->assertNotNull(Consent::first()->withdrawn_at);

        $this->postJson('/api/consents', ['consents' => [['purpose' => 'processing', 'version' => '1']]])->assertCreated();
        $this->assertSame(2, Consent::where('user_id', $user->id)->count());

        $this->deleteJson('/api/consents/not-a-purpose')->assertNotFound();
    }

    public function test_anonymous_visitors_can_only_record_the_cookie_choice(): void
    {
        $device = (string) Str::uuid();

        $this->postJson('/api/consents', ['deviceId' => $device, 'consents' => [['purpose' => 'cookies_optional', 'version' => '1']]])
            ->assertCreated();
        $this->assertDatabaseHas('consents', ['device_id' => $device, 'user_id' => null, 'purpose' => 'cookies_optional']);

        $this->postJson('/api/consents', ['deviceId' => $device, 'consents' => [['purpose' => 'terms', 'version' => '1']]])
            ->assertUnauthorized();
        $this->postJson('/api/consents', ['consents' => [['purpose' => 'cookies_optional', 'version' => '1']]])
            ->assertUnprocessable()->assertJsonValidationErrors('deviceId');

        $this->getJson('/api/consents')->assertUnauthorized();
        $this->deleteJson('/api/consents/cookies_optional')->assertUnauthorized();
    }

    public function test_users_only_see_and_withdraw_their_own_consents(): void
    {
        $a = User::factory()->create(['role' => 'learner']);
        $b = User::factory()->create(['role' => 'learner']);
        Consent::create(['user_id' => $a->id, 'purpose' => 'processing', 'version' => '1', 'granted_at' => now()]);

        Sanctum::actingAs($b);
        $this->getJson('/api/consents')->assertOk()->assertJsonCount(0);
        $this->deleteJson('/api/consents/processing')->assertNoContent();

        $this->assertNull(Consent::first()->withdrawn_at);
    }

    public function test_admin_sees_consent_history_on_data_requests(): void
    {
        $user = User::factory()->create(['role' => 'learner', 'email' => 'dp@example.com']);
        Consent::create(['user_id' => $user->id, 'purpose' => 'processing', 'version' => '1', 'granted_at' => now()]);
        DataSubjectRequest::create([
            'name' => 'DP', 'email' => 'dp@example.com', 'request_type' => 'access', 'status' => 'pending', 'user_id' => $user->id,
        ]);
        DataSubjectRequest::create([
            'name' => 'Visitor', 'email' => 'nobody@example.com', 'request_type' => 'other', 'status' => 'pending',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $rows = collect($this->getJson('/api/admin/data-requests')->assertOk()->json());

        $this->assertSame('processing', $rows->firstWhere('email', 'dp@example.com')['consents'][0]['purpose']);
        $this->assertSame([], $rows->firstWhere('email', 'nobody@example.com')['consents']);
    }
}
