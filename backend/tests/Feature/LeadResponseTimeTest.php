<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\School;
use App\Models\User;
use App\Services\LeadResponseStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-703: first-response time is recorded once and summarised. */
class LeadResponseTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Fast School', 'slug' => 'fast-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
    }

    private function lead(array $attrs = []): Inquiry
    {
        return Inquiry::withoutGlobalScope('school')->create(array_merge([
            'school_id' => $this->school->id, 'name' => 'Lead', 'phone' => '9000000000',
            'vehicle_type' => 'car', 'status' => 'pending',
        ], $attrs));
    }

    public function test_first_status_change_records_response_time_once(): void
    {
        $lead = $this->lead();
        Sanctum::actingAs($this->owner);

        $this->travel(90)->minutes();
        $this->patchJson("/api/inquiries/{$lead->id}", ['status' => 'contacted'])
            ->assertOk()
            ->assertJsonPath('responseSeconds', 5400);

        $this->travel(2)->hours();
        $this->patchJson("/api/inquiries/{$lead->id}", ['status' => 'interested'])->assertOk();

        $this->assertSame(5400, $lead->fresh()->response_seconds);
    }

    public function test_editing_a_new_lead_without_changing_status_is_not_a_response(): void
    {
        $lead = $this->lead();
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/inquiries/{$lead->id}", ['message' => 'called, no answer'])->assertOk();

        $this->assertNull($lead->fresh()->first_responded_at);
    }

    public function test_converting_counts_as_a_response(): void
    {
        $lead = $this->lead();
        Sanctum::actingAs($this->owner);

        $this->travel(10)->minutes();
        $this->postJson("/api/inquiries/{$lead->id}/convert")->assertCreated();

        $this->assertSame(600, $lead->fresh()->response_seconds);
    }

    public function test_dashboard_summarises_median_rates_and_waiting_leads(): void
    {
        // Answered in 10 min, 30 min and 2 days; one still waiting; one older than the window.
        foreach ([600, 1800, 172800] as $seconds) {
            $this->lead(['first_responded_at' => now(), 'response_seconds' => $seconds, 'status' => 'contacted']);
        }
        $this->lead();
        $old = $this->lead(['response_seconds' => 5, 'first_responded_at' => now(), 'status' => 'contacted']);
        $old->forceFill(['created_at' => now()->subDays(120)])->save();

        Sanctum::actingAs($this->owner);
        $stats = $this->getJson("/api/schools/{$this->school->id}/dashboard")->assertOk()->json('metrics.responseTime');

        $this->assertSame(4, $stats['leads']);
        $this->assertSame(3, $stats['responded']);
        $this->assertSame(1, $stats['awaitingReply']);
        $this->assertSame(1800, $stats['medianSeconds']);
        $this->assertEquals(0.5, $stats['within1hRate']);
        $this->assertEquals(0.5, $stats['within24hRate']);
    }

    public function test_admin_sees_platform_and_per_school_response_times(): void
    {
        $this->lead(['first_responded_at' => now(), 'response_seconds' => 1200, 'status' => 'contacted']);
        $other = School::create(['name' => 'Slow School', 'slug' => 'slow-school']);
        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $other->id, 'name' => 'L', 'phone' => '9', 'vehicle_type' => 'car', 'status' => 'pending',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $rt = $this->getJson('/api/admin/analytics')->assertOk()->json('responseTime');

        $this->assertSame(2, $rt['overall']['leads']);
        // Schools with no answered leads sort first (slowest).
        $this->assertSame('Slow School', $rt['slowestSchools'][0]['schoolName']);
        $this->assertSame(1, $rt['slowestSchools'][0]['awaitingReply']);
        $this->assertSame(1200, $rt['slowestSchools'][1]['medianSeconds']);
    }

    /** DIQ-708: public badge needs 5 answered recent leads; schools cannot set it. */
    public function test_public_reply_badge_is_computed_not_editable(): void
    {
        foreach ([300, 600, 900, 1200] as $seconds) {
            $this->lead(['first_responded_at' => now(), 'response_seconds' => $seconds, 'status' => 'contacted']);
        }
        app(LeadResponseStats::class)->refreshSchoolBadges();
        $this->assertNull($this->school->fresh()->typical_response_minutes);

        $this->lead(['first_responded_at' => now(), 'response_seconds' => 5000, 'status' => 'contacted']);
        app(LeadResponseStats::class)->refreshSchoolBadges();
        $this->assertSame(15, $this->school->fresh()->typical_response_minutes);

        $this->getJson('/api/schools/slug/fast-school')->assertOk()->assertJsonPath('typicalResponseMinutes', 15);

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/schools/{$this->school->id}", ['typicalResponseMinutes' => 1, 'typical_response_minutes' => 1])->assertOk();
        $this->assertSame(15, $this->school->fresh()->typical_response_minutes);
    }
}
