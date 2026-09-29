<?php

namespace Tests\Feature;

use App\Models\DrivePackage;
use App\Models\Instructor;
use App\Models\Locality;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-302: PBAC owner-only actions vs what managers may do. */
class OwnerManagerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{owner: User, manager: User, school: School} */
    private function schoolWithOwnerAndManager(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-om']);
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'name' => 'Owner Manager School',
            'slug' => 'owner-manager-school',
            'locality_id' => $locality->id,
            'user_id' => $owner->id,
        ]);
        $owner->update(['school_id' => $school->id]);
        SchoolAdmin::create(['school_id' => $school->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);

        $manager = User::factory()->create(['role' => 'school', 'school_id' => $school->id]);
        SchoolAdmin::create(['school_id' => $school->id, 'user_id' => $manager->id, 'role' => 'manager', 'status' => 'active']);

        return ['owner' => $owner->refresh(), 'manager' => $manager, 'school' => $school];
    }

    private function package(School $school): DrivePackage
    {
        return DrivePackage::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Basic',
            'price' => 5000,
            'sessions' => 10,
            'vehicle_type' => 'car',
            'transmission' => 'manual',
        ]);
    }

    private function instructor(School $school): Instructor
    {
        return Instructor::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Trainer',
            'status' => 'active',
        ]);
    }

    public function test_auth_me_reports_school_role(): void
    {
        ['owner' => $owner, 'manager' => $manager] = $this->schoolWithOwnerAndManager();

        Sanctum::actingAs($owner);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('schoolRole', 'owner');

        Sanctum::actingAs($manager);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('schoolRole', 'manager');
    }

    public function test_manager_cannot_manage_team(): void
    {
        ['manager' => $manager, 'owner' => $owner, 'school' => $school] = $this->schoolWithOwnerAndManager();
        $ownerRow = SchoolAdmin::withoutGlobalScope('school')->where('user_id', $owner->id)->first();

        Sanctum::actingAs($manager);
        $this->getJson("/api/schools/{$school->id}/team")->assertOk();
        $this->postJson("/api/schools/{$school->id}/team", ['email' => 'new@example.com'])->assertForbidden();
        $this->deleteJson("/api/schools/{$school->id}/team/{$ownerRow->id}")->assertForbidden();
    }

    public function test_only_owner_can_delete_packages(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'school' => $school] = $this->schoolWithOwnerAndManager();
        $package = $this->package($school);

        Sanctum::actingAs($manager);
        $this->patchJson("/api/packages/{$package->id}", ['price' => 5500])->assertOk();
        $this->deleteJson("/api/packages/{$package->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/packages/{$package->id}")->assertNoContent();
    }

    public function test_only_owner_can_deactivate_instructors(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'school' => $school] = $this->schoolWithOwnerAndManager();
        $instructor = $this->instructor($school);

        Sanctum::actingAs($manager);
        $this->getJson("/api/schools/{$school->id}/instructors")->assertOk();
        $this->deleteJson("/api/instructors/{$instructor->id}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/instructors/{$instructor->id}")->assertNoContent();
        $this->assertSame('terminated', $instructor->fresh()->status);
    }

    public function test_manager_keeps_day_to_day_access(): void
    {
        ['manager' => $manager, 'school' => $school] = $this->schoolWithOwnerAndManager();

        Sanctum::actingAs($manager);
        $this->getJson("/api/schools/{$school->id}/dashboard")->assertOk();
        $this->getJson("/api/schools/{$school->id}/learners")->assertOk();
        $this->getJson("/api/schools/{$school->id}/schedules")->assertOk();
        $this->getJson("/api/schools/{$school->id}/subscription")->assertOk();
        $this->patchJson("/api/schools/{$school->id}", ['description' => 'Updated by manager'])->assertOk();
    }
}
