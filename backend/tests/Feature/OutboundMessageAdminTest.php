<?php

namespace Tests\Feature;

use App\Messaging\Message;
use App\Messaging\MessageSender;
use App\Messaging\Messenger;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeMessageSender;
use Tests\TestCase;

/** DIQ-1006: admins see the WhatsApp / SMS log; it is purged after 90 days. */
class OutboundMessageAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_masked_log_and_others_cannot(): void
    {
        $fake = new FakeMessageSender;
        $this->app->instance(MessageSender::class, $fake);
        $messenger = app(Messenger::class);
        $messenger->send('9876543210', new Message('lead_reminder', ['name' => 'A'], 'Inquiry', 1));
        $fake->failing = ['whatsapp'];
        $messenger->send('9876543211', new Message('lead_reminder', ['name' => 'B'], 'Inquiry', 2));

        Sanctum::actingAs(User::factory()->create(['role' => 'school']));
        $this->getJson('/api/admin/outbound-messages')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/outbound-messages?status=failed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.to', '+91******3211')
            ->assertJsonPath('data.0.error', 'whatsapp down')
            ->assertJsonPath('meta.last24h.sent', 1);
    }

    public function test_retention_purges_the_log_after_90_days(): void
    {
        OutboundMessage::create(['channel' => 'whatsapp', 'template' => 't', 'to_masked' => 'x', 'to_hash' => 'a', 'status' => 'sent', 'related_id' => 1])
            ->forceFill(['created_at' => now()->subDays(91)])->save();
        OutboundMessage::create(['channel' => 'whatsapp', 'template' => 't', 'to_masked' => 'x', 'to_hash' => 'b', 'status' => 'sent', 'related_id' => 2]);

        $this->artisan('driveiq:retention --execute')->assertSuccessful();

        $this->assertSame(1, OutboundMessage::count());
    }
}
