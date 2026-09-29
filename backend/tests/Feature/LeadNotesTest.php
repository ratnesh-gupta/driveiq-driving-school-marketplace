<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-706: notes, follow-ups and the lead timeline. */
class LeadNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school', 'name' => 'Owner Name']);
        $this->school = School::create(['name' => 'Notes School', 'slug' => 'notes-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
    }

    private function lead(array $attrs = []): Inquiry
    {
        return Inquiry::withoutGlobalScope('school')->create(array_merge([
            'school_id' => $this->school->id, 'name' => 'Lead', 'phone' => '9000000000',
            'vehicle_type' => 'car', 'status' => 'pending', 'channel' => 'whatsapp',
        ], $attrs));
    }

    public function test_note_with_follow_up_moves_lead_and_counts_as_response(): void
    {
        $lead = $this->lead();
        Sanctum::actingAs($this->owner);

        $this->travel(20)->minutes();
        $this->postJson("/api/inquiries/{$lead->id}/notes", [
            'body' => 'Called, asked to ring back Saturday',
            'followUpAt' => now()->addDays(2)->toISOString(),
        ])->assertCreated()->assertJsonPath('by', 'Owner Name');

        $lead->refresh();
        $this->assertSame('follow_up', $lead->status);
        $this->assertSame(1200, $lead->response_seconds);
        $this->assertNotNull($lead->next_follow_up_at);

        $events = $this->getJson("/api/inquiries/{$lead->id}/timeline")->assertOk()->json('events');
        $this->assertSame(['created', 'status', 'note'], array_column($events, 'type'));
        $this->assertSame('follow_up', $events[1]['to']);
        $this->assertSame('whatsapp', $events[0]['channel']);
    }

    public function test_plain_note_keeps_status_and_past_follow_up_is_rejected(): void
    {
        $lead = $this->lead(['status' => 'interested']);
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/inquiries/{$lead->id}/notes", ['body' => 'Sent fee details'])->assertCreated();
        $this->postJson("/api/inquiries/{$lead->id}/notes", ['body' => 'x', 'followUpAt' => now()->subDay()->toISOString()])
            ->assertUnprocessable()->assertJsonValidationErrors('followUpAt');

        $this->assertSame('interested', $lead->fresh()->status);
    }

    public function test_follow_up_due_filter_and_closing_clears_it(): void
    {
        $due = $this->lead(['name' => 'Due', 'status' => 'follow_up', 'next_follow_up_at' => now()->subHour()]);
        $this->lead(['name' => 'Later', 'status' => 'follow_up', 'next_follow_up_at' => now()->addDay()]);
        $this->lead(['name' => 'Converted', 'status' => 'converted', 'next_follow_up_at' => now()->subHour()]);
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/inquiries?followUpDue=1')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Due');

        $this->patchJson("/api/inquiries/{$due->id}", ['status' => 'lost'])->assertOk()->assertJsonPath('nextFollowUpAt', null);
    }

    public function test_oldest_waiting_sort_puts_unanswered_leads_first(): void
    {
        $answered = $this->lead(['name' => 'Answered', 'first_responded_at' => now()]);
        $answered->forceFill(['created_at' => now()->subDays(3)])->save();
        $this->lead(['name' => 'New today']);
        $old = $this->lead(['name' => 'Waiting two days']);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        Sanctum::actingAs($this->owner);

        $names = collect($this->getJson('/api/inquiries?sort=oldest_waiting')->assertOk()->json())->pluck('name')->all();
        $this->assertSame(['Waiting two days', 'New today', 'Answered'], $names);
    }

    public function test_other_schools_and_learners_cannot_read_or_add_notes(): void
    {
        $lead = $this->lead();
        $otherOwner = User::factory()->create(['role' => 'school']);
        $otherSchool = School::create(['name' => 'Other', 'slug' => 'other-notes', 'user_id' => $otherOwner->id]);
        $otherOwner->update(['school_id' => $otherSchool->id]);
        $learner = User::factory()->create(['role' => 'learner', 'school_id' => $this->school->id]);

        Sanctum::actingAs($otherOwner->refresh());
        $this->getJson("/api/inquiries/{$lead->id}/timeline")->assertForbidden();
        $this->postJson("/api/inquiries/{$lead->id}/notes", ['body' => 'x'])->assertForbidden();

        Sanctum::actingAs($learner);
        $this->getJson("/api/inquiries/{$lead->id}/timeline")->assertForbidden();

        $this->assertNull($lead->fresh()->first_responded_at);
    }
}
