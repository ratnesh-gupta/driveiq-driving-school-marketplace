<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function seed(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-p10']);
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'name' => 'Analytics School',
            'slug' => 'analytics-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000013000',
            'user_id' => $owner->id,
            'active' => true,
        ]);
        $owner->update(['school_id' => $school->id]);

        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Lead One',
            'phone' => '9000000001',
            'status' => 'pending',
        ]);
        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Lead Two',
            'phone' => '9000000002',
            'status' => 'converted',
        ]);

        Instructor::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Top Trainer',
            'status' => 'active',
        ]);

        Learner::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Active Learner',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        return compact('owner', 'school', 'admin');
    }

    public function test_school_analytics(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seed();
        Sanctum::actingAs($owner);

        $this->getJson('/api/schools/'.$school->id.'/analytics')
            ->assertOk()
            ->assertJsonPath('leads.total', 2)
            ->assertJsonPath('leads.converted', 1)
            ->assertJsonStructure([
                'leads' => ['thisMonth', 'conversionRate', 'trend'],
                'learners' => ['active', 'completionRate'],
                'sessions',
                'instructors',
            ]);
    }

    public function test_instructor_analytics(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seed();
        Sanctum::actingAs($owner);

        $this->getJson('/api/schools/'.$school->id.'/analytics/instructors')
            ->assertOk()
            ->assertJsonCount(1, 'instructors');
    }

    public function test_platform_analytics_admin_only(): void
    {
        ['owner' => $owner, 'admin' => $admin] = $this->seed();

        Sanctum::actingAs($owner);
        $this->getJson('/api/admin/analytics')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/analytics')
            ->assertOk()
            ->assertJsonStructure([
                'schools' => ['total', 'active', 'subscribed'],
                'funnel' => ['inquiries', 'converted', 'conversionRate'],
                'revenue' => ['mrr', 'arr', 'byPlan'],
                'topLocalities',
            ]);
    }

    public function test_other_school_forbidden(): void
    {
        ['school' => $school] = $this->seed();
        $other = User::factory()->create(['role' => 'school', 'school_id' => 999]);

        Sanctum::actingAs($other);
        $this->getJson('/api/schools/'.$school->id.'/analytics')->assertForbidden();
    }
}
