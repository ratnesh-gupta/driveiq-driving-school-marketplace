<?php

namespace Tests\Feature;

use App\Messaging\Drivers\LogSender;
use App\Messaging\Drivers\NullSender;
use App\Messaging\Message;
use App\Messaging\MessageSender;
use App\Messaging\Messenger;
use App\Models\OutboundMessage;
use App\Providers\AppServiceProvider;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeMessageSender;
use Tests\TestCase;

/** DIQ-1001: provider-neutral WhatsApp / SMS layer. */
class MessagingLayerTest extends TestCase
{
    use RefreshDatabase;

    private FakeMessageSender $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeMessageSender;
        $this->app->instance(MessageSender::class, $this->fake);
    }

    private function message(int $relatedId = 1): Message
    {
        return new Message('lead_reminder', ['name' => 'Asha', 'ago' => '2 hours ago', 'link' => 'https://x.test/l'], 'Inquiry', $relatedId, 7);
    }

    public function test_indian_numbers_normalise_to_e164_and_junk_is_rejected(): void
    {
        $this->assertSame('+919876543210', Phone::toE164('98765 43210'));
        $this->assertSame('+919876543210', Phone::toE164('+91 98765-43210'));
        $this->assertSame('+919876543210', Phone::toE164('09876543210'));
        $this->assertSame('+919876543210', Phone::toE164('919876543210'));
        $this->assertSame('+14155550123', Phone::toE164('+1 415 555 0123'));
        $this->assertNull(Phone::toE164('12345'));
        $this->assertNull(Phone::toE164('+91 1234567890')); // Indian mobiles start 6-9
        $this->assertNull(Phone::toE164(''));
        $this->assertSame('+91******3210', Phone::mask('+919876543210'));
    }

    public function test_sends_rendered_template_once_and_logs_masked(): void
    {
        $messenger = app(Messenger::class);

        $this->assertSame('sent', $messenger->send('9876543210', $this->message()));
        $this->assertSame('duplicate', $messenger->send('+91 98765 43210', $this->message()));
        $this->assertSame('sent', $messenger->send('9876543210', $this->message(2)));
        $this->assertSame('invalid_number', $messenger->send('000', $this->message(3)));

        $this->assertCount(2, $this->fake->sent);
        $this->assertSame('Reminder: Asha enquired 2 hours ago and has not been contacted yet. https://x.test/l', $this->fake->texts()[0]);
        $this->assertSame(['Asha', '2 hours ago', 'https://x.test/l'], $this->fake->sent[0]['vars']);

        $row = OutboundMessage::first();
        $this->assertSame(['sent', '+91******3210', 7], [$row->status, $row->to_masked, $row->school_id]);
        $this->assertDatabaseMissing('outbound_messages', ['to_masked' => '+919876543210']);
    }

    public function test_failures_are_logged_and_sms_fallback_only_with_a_dlt_template(): void
    {
        $this->fake->failing = ['whatsapp'];
        $messenger = app(Messenger::class);

        $this->assertSame('failed', $messenger->send('9876543210', $this->message()));
        $this->assertSame('whatsapp down', OutboundMessage::first()->error);

        config(['messaging.sms_fallback' => true, 'messaging.templates.lead_reminder.dlt_template_id' => '1107000000000000001']);
        $this->assertSame('sent', $messenger->send('9876543210', $this->message(2)));
        $this->assertSame('sms', OutboundMessage::where('related_id', 2)->value('channel'));
        $this->assertSame('sms', $this->fake->sent[0]['channel']);
    }

    public function test_driver_comes_from_config(): void
    {
        $this->app->forgetInstance(MessageSender::class);
        $this->app->offsetUnset(MessageSender::class);
        (new AppServiceProvider($this->app))->register();

        config(['messaging.driver' => 'null']);
        $this->assertInstanceOf(NullSender::class, app(MessageSender::class));
        config(['messaging.driver' => 'nope']);
        $this->expectException(\InvalidArgumentException::class);
        app(MessageSender::class);
    }

    public function test_log_driver_writes_the_text_with_a_masked_number(): void
    {
        Log::spy();

        (new Messenger(new LogSender))->send('9876543210', $this->message());

        Log::shouldHaveReceived('info')->withArgs(fn ($line) => str_contains($line, '+91******3210') && str_contains($line, 'Asha enquired'));
    }
}
