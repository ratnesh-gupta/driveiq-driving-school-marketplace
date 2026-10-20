<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PlanNoticeNotification;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** DIQ-807: expiry and ending-soon reminders, once each, owners only. */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Life School', 'slug' => 'life-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        SchoolAdmin::create(['school_id' => $this->school->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'status' => 'active']);
        $this->manager = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id]);
    }

    private function trial(): Subscription
    {
        return Subscription::withoutGlobalScope('school')->where('school_id', $this->school->id)
            ->where('notes', 'Feature trial')->firstOrFail();
    }

    private function notices(string $type): int
    {
        return AppNotification::where('type', $type)->count();
    }

    public function test_trial_gets_7_day_and_1_day_reminders_once_each_to_owners_only(): void
    {
        $this->travel(24)->days(); // trial ends in 6 days
        $this->artisan('driveiq:subscriptions')->assertSuccessful();
        $this->artisan('driveiq:subscriptions')->assertSuccessful();
        $this->assertSame(1, $this->notices('billing.ending'));
        Notification::assertSentTo($this->owner, PlanNoticeNotification::class);
        Notification::assertNotSentTo($this->manager, PlanNoticeNotification::class);

        $this->travelTo(now()->addDays(5)->addHours(12)); // under a day left
        $this->artisan('driveiq:subscriptions');
        $this->artisan('driveiq:subscriptions');
        $this->assertSame(2, $this->notices('billing.ending'));
        $this->assertStringContainsString('tomorrow', AppNotification::where('type', 'billing.ending')->latest('id')->first()->title);
    }

    public function test_ended_trial_is_expired_and_owner_told_once(): void
    {
        $this->travel(31)->days();
        $this->artisan('driveiq:subscriptions')->assertSuccessful();
        $this->artisan('driveiq:subscriptions')->assertSuccessful();

        $this->assertSame('expired', $this->trial()->status);
        $this->assertSame(1, $this->notices('billing.ended'));
        $this->assertStringContainsString('Premium trial has ended', AppNotification::where('type', 'billing.ended')->first()->title);
    }

    public function test_no_trial_notices_when_a_paid_plan_already_runs_longer(): void
    {
        app(SubscriptionService::class)->assign($this->school->id, 'premium', 3);

        $this->travel(24)->days();
        $this->artisan('driveiq:subscriptions');
        $this->travel(7)->days();
        $this->artisan('driveiq:subscriptions');

        $this->assertSame(0, $this->notices('billing.ending'));
        $this->assertSame(0, $this->notices('billing.ended'));
        // The trial row itself still gets tidied up.
        $this->assertSame(0, Subscription::withoutGlobalScope('school')->where('status', 'trial')->count());
    }

    public function test_paid_plan_end_is_announced_and_access_drops_to_basic(): void
    {
        $this->trial()->update(['expires_at' => now()->subDay(), 'status' => 'expired']);
        app(SubscriptionService::class)->assign($this->school->id, 'featured', 1);

        $this->travelTo(now()->addMonth()->addHour());
        $this->artisan('driveiq:subscriptions');

        $this->assertSame(1, $this->notices('billing.ended'));
        $this->assertStringContainsString('Featured plan has ended', AppNotification::where('type', 'billing.ended')->first()->title);
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/schools/{$this->school->id}/entitlements")
            ->assertJsonPath('plan', 'basic')
            ->assertJsonPath('features', []);
    }
}
