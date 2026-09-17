<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Locality;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedSchoolOwner(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-p3']);
        $owner = User::factory()->create(['role' => 'school', 'email' => 'owner-p3@example.com']);
        $school = School::create([
            'name' => 'Ops School',
            'slug' => 'ops-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000006000',
            'user_id' => $owner->id,
            'profile_completeness' => 50,
        ]);
        $owner->update(['school_id' => $school->id]);

        SchoolAdmin::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        return compact('owner', 'school');
    }

    public function test_owner_can_invite_and_list_team(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seedSchoolOwner();

        Sanctum::actingAs($owner);

        $this->postJson('/api/schools/'.$school->id.'/team', [
            'email' => 'manager-p3@example.com',
            'name' => 'Manager',
            'role' => 'manager',
        ])->assertCreated()
            ->assertJsonPath('email', 'manager-p3@example.com')
            ->assertJsonPath('role', 'manager');

        $this->getJson('/api/schools/'.$school->id.'/team')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_other_school_cannot_access_team(): void
    {
        ['school' => $school] = $this->seedSchoolOwner();

        $other = User::factory()->create(['role' => 'school', 'school_id' => 99999]);

        Sanctum::actingAs($other);

        $this->getJson('/api/schools/'.$school->id.'/team')->assertForbidden();
    }

    public function test_settings_get_and_update(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seedSchoolOwner();

        Sanctum::actingAs($owner);

        $this->getJson('/api/schools/'.$school->id.'/settings')
            ->assertOk()
            ->assertJsonPath('settings.timezone', 'Asia/Kolkata');

        $this->putJson('/api/schools/'.$school->id.'/settings', [
            'settings' => [
                'notifications' => ['sms' => true],
                'timezone' => 'Asia/Kolkata',
            ],
        ])->assertOk()
            ->assertJsonPath('settings.notifications.sms', true)
            ->assertJsonPath('settings.notifications.email', true); // merged default
    }

    public function test_dashboard_metrics(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seedSchoolOwner();

        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Lead',
            'phone' => '9111111111',
            'vehicle_type' => 'car',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/schools/'.$school->id.'/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.totalInquiries', 1)
            ->assertJsonPath('metrics.pendingInquiries', 1)
            ->assertJsonPath('profileCompleteness', 50);
    }

    public function test_audit_logs_scoped(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seedSchoolOwner();

        Sanctum::actingAs($owner);

        $this->putJson('/api/schools/'.$school->id.'/settings', [
            'settings' => ['locale' => 'hi-IN'],
        ])->assertOk();

        $this->getJson('/api/schools/'.$school->id.'/audit-logs')
            ->assertOk()
            ->assertJsonFragment(['action' => 'update']);
    }
}
