<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-603: the contact form is stored and handled by admins. */
class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Meera',
            'email' => 'Meera@Example.com',
            'subject' => 'Listing my school',
            'message' => 'How do I list my driving school in Wakad?',
            'formStartedAt' => now()->subSeconds(20)->getTimestampMs(),
        ], $overrides);
    }

    public function test_message_is_stored_and_listed_for_admins(): void
    {
        $this->postJson('/api/contact', $this->payload())->assertCreated();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'meera@example.com', 'subject' => 'Listing my school', 'status' => 'new', 'user_id' => null,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $id = $this->getJson('/api/admin/contact-messages')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.subject', 'Listing my school')
            ->json('data.0.id');

        $this->patchJson("/api/admin/contact-messages/{$id}", ['status' => 'closed'])
            ->assertOk()->assertJsonPath('status', 'closed');
        $this->getJson('/api/admin/contact-messages?status=new')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_signed_in_sender_is_linked(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/contact', $this->payload())->assertCreated();

        $this->assertSame($user->id, ContactMessage::first()->user_id);
    }

    public function test_bots_are_rejected(): void
    {
        $this->postJson('/api/contact', $this->payload(['website' => 'http://spam.test']))
            ->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->postJson('/api/contact', $this->payload(['formStartedAt' => now()->getTimestampMs()]))
            ->assertUnprocessable()->assertJsonValidationErrors('formStartedAt');
        $this->postJson('/api/contact', $this->payload(['formStartedAt' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('formStartedAt');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_contact_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/contact', $this->payload())->assertCreated();
        }

        $this->postJson('/api/contact', $this->payload())->assertTooManyRequests();
    }

    public function test_inbox_is_admin_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'school']));

        $this->getJson('/api/admin/contact-messages')->assertForbidden();
    }
}
