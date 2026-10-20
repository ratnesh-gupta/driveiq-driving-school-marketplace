<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Inquiry;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** DIQ-705: one reminder per unanswered lead, after the school's threshold. */
class LeadRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Remind School', 'slug' => 'remind-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
    }

    private function lead(array $attrs = []): Inquiry
    {
        return Inquiry::withoutGlobalScope('school')->create(array_merge([
            'school_id' => $this->school->id, 'name' => 'Waiting Lead', 'phone' => '9000000000',
            'vehicle_type' => 'car', 'status' => 'pending',
        ], $attrs));
    }

    private function reminders(): int
    {
        return AppNotification::where('type', 'inquiry.reminder')->count();
    }

    public function test_reminds_once_after_the_default_hour(): void
    {
        $lead = $this->lead();

        $this->travel(59)->minutes();
        $this->artisan('driveiq:lead-reminders')->assertSuccessful();
        $this->assertSame(0, $this->reminders());

        $this->travel(2)->minutes();
        $this->artisan('driveiq:lead-reminders')->assertSuccessful();
        $this->assertSame(1, $this->reminders());
        Notification::assertSentTo($this->owner, NewLeadNotification::class, fn ($n) => $n->reminder && $n->inquiry->is($lead));

        $this->travel(1)->hours();
        $this->artisan('driveiq:lead-reminders')->assertSuccessful();
        $this->assertSame(1, $this->reminders());
    }

    public function test_answered_leads_are_not_reminded(): void
    {
        $this->lead(['status' => 'contacted', 'first_responded_at' => now()]);
        $this->lead()->markResponded();

        $this->travel(3)->hours();
        $this->artisan('driveiq:lead-reminders')->assertSuccessful();

        $this->assertSame(0, $this->reminders());
        Notification::assertNothingSent();
    }

    public function test_school_threshold_and_off_switch_are_respected(): void
    {
        $setting = SchoolSetting::create(['school_id' => $this->school->id, 'settings' => ['notifications' => ['reminder_after_minutes' => 30]]]);
        $this->lead();

        $this->travel(31)->minutes();
        $this->artisan('driveiq:lead-reminders');
        $this->assertSame(1, $this->reminders());

        $setting->update(['settings' => ['notifications' => ['reminder_after_minutes' => 0]]]);
        $this->lead();
        $this->travel(5)->hours();
        $this->artisan('driveiq:lead-reminders');
        $this->assertSame(1, $this->reminders());
    }

    public function test_old_backlog_is_never_reminded(): void
    {
        $this->lead()->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->artisan('driveiq:lead-reminders');

        $this->assertSame(0, $this->reminders());
    }
}
