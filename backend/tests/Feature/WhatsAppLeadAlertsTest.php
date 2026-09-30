<?php

namespace Tests\Feature;

use App\Messaging\MessageSender;
use App\Models\OutboundMessage;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeMessageSender;
use Tests\TestCase;

/** DIQ-1003: staff lead alerts and reminders on WhatsApp. */
class WhatsAppLeadAlertsTest extends TestCase
{
    use RefreshDatabase;

    private FakeMessageSender $fake;

    private School $school;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeMessageSender;
        $this->app->instance(MessageSender::class, $this->fake);

        $this->owner = User::factory()->create(['role' => 'school', 'phone' => '+919876500001', 'whatsapp_opt_in_at' => now()]);
        $this->school = School::create(['name' => 'Alert School', 'slug' => 'alert-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        SchoolAdmin::create(['school_id' => $this->school->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'status' => 'active']);
        // Has a number but never opted in.
        $this->manager = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id, 'phone' => '+919876500002']);
    }

    private function whatsapp(bool $on): void
    {
        SchoolSetting::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $this->school->id],
            ['settings' => ['notifications' => ['whatsapp' => $on]]]
        );
    }

    private function enquire(): void
    {
        $this->postJson('/api/inquiries', [
            'schoolId' => $this->school->id, 'name' => 'Rohan', 'phone' => '9811111111', 'vehicleType' => 'car', 'area' => 'Baner',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ])->assertCreated();
    }

    public function test_opted_in_staff_get_the_new_lead_on_whatsapp_when_the_school_turned_it_on(): void
    {
        $this->enquire();
        $this->assertSame([], $this->fake->sent, 'school switch is off by default');

        $this->whatsapp(true);
        $this->enquire();

        $this->assertCount(1, $this->fake->sent);
        $this->assertSame('+919876500001', $this->fake->sent[0]['to']);
        $this->assertStringContainsString('New enquiry for Alert School: Rohan, car, Baner', $this->fake->texts()[0]);
    }

    public function test_unanswered_lead_reminder_goes_on_whatsapp_once(): void
    {
        $this->whatsapp(true);
        $this->enquire();
        $this->fake->sent = [];

        $this->travel(2)->hours();
        $this->artisan('driveiq:lead-reminders')->assertSuccessful();
        $this->artisan('driveiq:lead-reminders')->assertSuccessful();

        $this->assertCount(1, $this->fake->sent);
        $this->assertStringContainsString('Reminder: Rohan enquired', $this->fake->texts()[0]);
        $this->assertSame(2, OutboundMessage::where('status', 'sent')->count());
    }

    public function test_email_off_whatsapp_on_still_alerts_on_whatsapp(): void
    {
        SchoolSetting::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $this->school->id],
            ['settings' => ['notifications' => ['whatsapp' => true, 'email' => false]]]
        );
        Mail::fake();

        $this->enquire();

        $this->assertCount(1, $this->fake->sent);
        Mail::assertNothingOutgoing();
    }
}
