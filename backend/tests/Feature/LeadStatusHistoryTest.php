<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\LeadStatusHistory;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_writes_lead_status_history(): void
    {
        $locality = Locality::create([
            'name' => 'Baner',
            'slug' => 'baner-history',
        ]);

        $owner = User::factory()->create(['role' => 'school']);

        $school = School::create([
            'user_id' => $owner->id,
            'name' => 'History School',
            'slug' => 'history-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000000000',
        ]);

        $owner->update(['school_id' => $school->id]);
        $owner->refresh();

        $inquiry = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Lead',
            'phone' => '9888888888',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/inquiries/'.$inquiry->id, [
            'status' => 'contacted',
        ])->assertOk()->assertJsonPath('status', 'contacted');

        $this->assertDatabaseHas('lead_status_history', [
            'inquiry_id' => $inquiry->id,
            'from_status' => 'pending',
            'to_status' => 'contacted',
            'changed_by_id' => $owner->id,
        ]);

        $this->assertSame(1, LeadStatusHistory::where('inquiry_id', $inquiry->id)->count());
    }

    public function test_non_status_update_does_not_write_history(): void
    {
        $locality = Locality::create([
            'name' => 'Wakad',
            'slug' => 'wakad-history',
        ]);

        $owner = User::factory()->create(['role' => 'school']);

        $school = School::create([
            'user_id' => $owner->id,
            'name' => 'No History School',
            'slug' => 'no-history-school',
            'locality_id' => $locality->id,
            'address' => 'Wakad',
            'phone' => '9000000001',
        ]);

        $owner->update(['school_id' => $school->id]);
        $owner->refresh();

        $inquiry = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Lead',
            'phone' => '9777777777',
            'status' => 'pending',
            'message' => 'Old',
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/inquiries/'.$inquiry->id, [
            'message' => 'Updated note only',
        ])->assertOk();

        $this->assertSame(0, LeadStatusHistory::where('inquiry_id', $inquiry->id)->count());
    }
}
