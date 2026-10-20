<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\LeaveRequest;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-912: overview to-dos and the owner-only audit trail. */
class DashboardAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school', 'name' => 'Olivia Owner']);
        $this->school = School::create(['name' => 'Todo School', 'slug' => 'todo-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        SchoolAdmin::create(['school_id' => $this->school->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'status' => 'active']);
        $this->manager = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id, 'name' => 'Manny Manager']);
        SchoolAdmin::create(['school_id' => $this->school->id, 'user_id' => $this->manager->id, 'role' => 'manager', 'status' => 'active']);
    }

    public function test_overview_lists_operational_todos_and_what_the_profile_lacks(): void
    {
        $sid = $this->school->id;
        $ravi = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Ravi', 'status' => 'active']);
        LeaveRequest::withoutGlobalScope('school')->create(['school_id' => $sid, 'instructor_id' => $ravi->id, 'start_date' => now()->addDay(), 'end_date' => now()->addDay(), 'status' => 'pending']);
        Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'name' => 'Asha', 'status' => 'active']);
        $car = Vehicle::withoutGlobalScope('school')->create(['school_id' => $sid, 'registration_number' => 'MH12TD0001', 'type' => 'car', 'status' => 'active']);
        VehicleDocument::withoutGlobalScope('school')->create(['school_id' => $sid, 'vehicle_id' => $car->id, 'type' => 'pollution', 'expiry_date' => now()->subDay(), 'status' => 'valid']);

        Sanctum::actingAs($this->manager);
        $res = $this->getJson("/api/schools/{$sid}/dashboard")->assertOk();

        $tasks = collect($res->json('pendingTasks'))->keyBy('type');
        $this->assertSame(1, $tasks['pending_leave']['count']);
        $this->assertSame(1, $tasks['unassigned_learners']['count']);
        $this->assertSame(1, $tasks['expired_vehicle_papers']['count']);
        $this->assertSame('/dashboard/vehicles', $tasks['expired_vehicle_papers']['href']);
        $this->assertContains('service areas', $res->json('missingProfileFields'));
        $this->assertSame(1, $res->json('metrics.learnersThisMonth'));
    }

    public function test_audit_trail_is_owner_only_named_filterable_and_hides_ips(): void
    {
        Sanctum::actingAs($this->manager);
        $this->patchJson("/api/schools/{$this->school->id}", ['description' => 'Changed by manager'])->assertOk();
        $this->getJson("/api/schools/{$this->school->id}/audit-logs")->assertForbidden();

        AuditLog::log('create', 'Vehicle', 1, [], [], $this->school->id);

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/schools/{$this->school->id}/audit-logs?modelType=School")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.userName', 'Manny Manager')
            ->assertJsonPath('data.0.ipAddress', null);

        Sanctum::actingAs($this->otherSchoolUser());
        $this->getJson("/api/schools/{$this->school->id}/audit-logs")->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson("/api/admin/audit-logs?schoolId={$this->school->id}&modelType=School")
            ->assertOk()
            ->assertJsonPath('data.0.schoolName', 'Todo School')
            ->assertJsonPath('data.0.userName', 'Manny Manager');
    }
}
