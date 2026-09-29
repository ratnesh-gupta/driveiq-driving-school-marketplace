<?php

namespace Tests\Feature;

use App\Listeners\SendInquiryConfirmation;
use App\Listeners\SendInquiryCreatedNotification;
use App\Models\AppNotification;
use App\Models\Inquiry;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** DIQ-704: new leads are emailed to school staff, as their settings allow. */
class NewLeadMailTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Mail School', 'slug' => 'mail-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
    }

    private function enquire(): void
    {
        $this->postJson('/api/inquiries', [
            'schoolId' => $this->school->id,
            'name' => 'Kavya',
            'phone' => '98765 43210',
            'vehicleType' => 'car',
            'area' => 'Baner',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ])->assertCreated();
    }

    public function test_staff_are_emailed_and_learners_are_not(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id]);
        $learner = User::factory()->create(['role' => 'learner', 'school_id' => $this->school->id]);

        $this->enquire();

        Notification::assertSentTo([$this->owner, $manager], NewLeadNotification::class);
        Notification::assertNotSentTo($learner, NewLeadNotification::class);

        $mail = (new NewLeadNotification(Inquiry::withoutGlobalScope('school')->first()))->toMail($this->owner);
        $this->assertSame('New enquiry from Kavya', $mail->subject);
        $this->assertStringContainsString('https://wa.me/919876543210', implode("\n", $mail->introLines));
        $this->assertStringEndsWith('/dashboard/leads', $mail->actionUrl);
    }

    public function test_email_toggle_off_keeps_in_app_only(): void
    {
        Notification::fake();
        SchoolSetting::create(['school_id' => $this->school->id, 'settings' => ['notifications' => ['email' => false]]]);

        $this->enquire();

        Notification::assertNotSentTo($this->owner, NewLeadNotification::class);
        $this->assertSame(1, AppNotification::where('user_id', $this->owner->id)->count());
    }

    public function test_new_inquiry_toggle_off_sends_nothing(): void
    {
        Notification::fake();
        SchoolSetting::create(['school_id' => $this->school->id, 'settings' => ['notifications' => ['new_inquiry' => false]]]);

        $this->enquire();

        Notification::assertNothingSentTo($this->owner);
        $this->assertSame(0, AppNotification::count());
    }

    public function test_inquiry_listeners_are_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, app(SendInquiryCreatedNotification::class));
        $this->assertInstanceOf(ShouldQueue::class, app(SendInquiryConfirmation::class));
        $this->assertInstanceOf(ShouldQueue::class, new NewLeadNotification(new Inquiry));
    }
}
