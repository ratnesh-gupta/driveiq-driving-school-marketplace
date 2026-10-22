<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Learner;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1305 (QA BUG-02): leads and learners page through results on the server. */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private School $other;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $locality = Locality::create(['name' => 'Wakad', 'slug' => 'wakad']);
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Paged School', 'slug' => 'paged-school', 'locality_id' => $locality->id, 'phone' => '9000020000', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        $this->other = School::create(['name' => 'Other School', 'slug' => 'other-school', 'locality_id' => $locality->id, 'phone' => '9000020001']);

        foreach (range(1, 30) as $i) {
            Inquiry::withoutGlobalScope('school')->create([
                'school_id' => $this->school->id, 'name' => "Lead {$i}", 'phone' => '98220'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'vehicle_type' => 'car', 'status' => $i <= 4 ? 'converted' : 'pending',
            ]);
            Learner::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => "Learner {$i}", 'mobile' => '97000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'status' => 'active']);
        }
        Inquiry::withoutGlobalScope('school')->create(['school_id' => $this->other->id, 'name' => 'Not yours', 'phone' => '9811111111', 'vehicle_type' => 'car', 'status' => 'pending']);
        Learner::withoutGlobalScope('school')->create(['school_id' => $this->other->id, 'name' => 'Not yours', 'status' => 'active']);

        Sanctum::actingAs($this->owner);
    }

    public function test_leads_come_one_page_at_a_time_with_a_total(): void
    {
        $page1 = $this->getJson('/api/inquiries?page=1&perPage=25')->assertOk()
            ->assertHeader('X-Total-Count', '30')->assertHeader('X-Page', '1')->assertHeader('X-Per-Page', '25');
        $this->assertCount(25, $page1->json());

        $page2 = $this->getJson('/api/inquiries?page=2&perPage=25')->assertOk();
        $this->assertCount(5, $page2->json());
        $this->assertEmpty(array_intersect(array_column($page1->json(), 'id'), array_column($page2->json(), 'id')));
        $this->assertNotContains('Not yours', array_column($page2->json(), 'name'));
    }

    public function test_lead_filters_apply_before_paging(): void
    {
        $this->getJson('/api/inquiries?page=1&perPage=10&status=converted')
            ->assertOk()->assertHeader('X-Total-Count', '4');

        $res = $this->getJson('/api/inquiries?page=1&perPage=10&search=Lead%2012')->assertOk()->assertHeader('X-Total-Count', '1');
        $this->assertSame('Lead 12', $res->json('0.name'));

        $this->getJson('/api/inquiries?page=1&search=9822000007')->assertHeader('X-Total-Count', '1');
    }

    public function test_page_size_is_capped(): void
    {
        $this->getJson('/api/inquiries?perPage=1000')->assertUnprocessable();
    }

    public function test_unpaged_callers_still_get_a_plain_list(): void
    {
        $res = $this->getJson('/api/inquiries')->assertOk()->assertHeaderMissing('X-Total-Count');
        $this->assertCount(30, $res->json());
    }

    public function test_lead_summary_counts_by_status_for_the_school_only(): void
    {
        $this->getJson('/api/inquiries/summary')->assertOk()
            ->assertJsonPath('total', 30)
            ->assertJsonPath('byStatus.converted', 4)
            ->assertJsonPath('byStatus.pending', 26)
            ->assertJsonPath('byStatus.lost', 0);
    }

    public function test_learners_page_and_search(): void
    {
        $res = $this->getJson("/api/schools/{$this->school->id}/learners?page=2&perPage=20")
            ->assertOk()->assertHeader('X-Total-Count', '30');
        $this->assertCount(10, $res->json());

        $this->getJson("/api/schools/{$this->school->id}/learners?page=1&search=Learner%207")
            ->assertOk()->assertHeader('X-Total-Count', '1');
        $this->getJson("/api/schools/{$this->school->id}/learners?page=1&search=9700000030")
            ->assertOk()->assertHeader('X-Total-Count', '1');
    }

    public function test_another_schools_learners_stay_out_of_reach(): void
    {
        $this->getJson("/api/schools/{$this->other->id}/learners?page=1")->assertForbidden();
    }
}
