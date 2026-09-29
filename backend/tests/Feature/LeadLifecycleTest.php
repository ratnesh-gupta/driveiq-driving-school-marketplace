<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-702: one lead lifecycle across API, history and dashboard metrics. */
class LeadLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Lifecycle School', 'slug' => 'lifecycle-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        Sanctum::actingAs($this->owner->refresh());
    }

    private function lead(string $status = 'pending'): Inquiry
    {
        return Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Lead', 'phone' => '9000000000',
            'vehicle_type' => 'car', 'status' => $status,
        ]);
    }

    public function test_every_lifecycle_status_is_accepted_and_recorded(): void
    {
        $lead = $this->lead();

        foreach (['contacted', 'follow_up', 'interested', 'converted'] as $status) {
            $this->patchJson("/api/inquiries/{$lead->id}", ['status' => $status])
                ->assertOk()->assertJsonPath('status', $status);
        }

        $this->assertSame(
            ['contacted', 'follow_up', 'interested', 'converted'],
            $lead->statusHistory()->reorder('id')->pluck('to_status')->all()
        );
    }

    public function test_retired_statuses_are_rejected(): void
    {
        $lead = $this->lead();

        foreach (['enrolled', 'closed', 'bogus'] as $status) {
            $this->patchJson("/api/inquiries/{$lead->id}", ['status' => $status])
                ->assertUnprocessable()->assertJsonValidationErrors('status');
        }
    }

    public function test_lost_reason_is_kept_only_while_lost(): void
    {
        $lead = $this->lead('contacted');

        $this->patchJson("/api/inquiries/{$lead->id}", ['status' => 'lost', 'lostReason' => 'Chose a closer school'])
            ->assertOk()->assertJsonPath('lostReason', 'Chose a closer school');

        $this->patchJson("/api/inquiries/{$lead->id}", ['status' => 'interested'])
            ->assertOk()->assertJsonPath('lostReason', null);
    }

    public function test_status_filter_and_dashboard_rates_use_the_new_statuses(): void
    {
        $this->lead('pending');
        $this->lead('follow_up');
        $this->lead('interested');
        $this->lead('converted');
        $this->lead('lost');

        $this->getJson('/api/inquiries?status=follow_up')->assertOk()->assertJsonCount(1);

        $metrics = $this->getJson("/api/schools/{$this->school->id}/dashboard")->assertOk()->json('metrics');
        $this->assertEquals(0.8, $metrics['responseRate']);
        $this->assertEquals(0.2, $metrics['conversionRate']);
    }
}
