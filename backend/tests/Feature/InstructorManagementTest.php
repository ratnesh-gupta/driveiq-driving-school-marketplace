<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedSchool(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-p5']);
        $owner = User::factory()->create(['role' => 'school', 'email' => 'owner-p5@example.com']);
        $school = School::create([
            'name' => 'Trainer School',
            'slug' => 'trainer-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000008000',
            'user_id' => $owner->id,
        ]);
        $owner->update(['school_id' => $school->id]);

        return compact('owner', 'school');
    }

    public function test_school_can_create_and_list_instructors(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seedSchool();

        Sanctum::actingAs($owner);

        $this->postJson('/api/schools/'.$school->id.'/instructors', [
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
            'gender' => 'female',
            'womenInstructor' => true,
            'yearsExperience' => 5,
            'skills' => ['car', 'automatic'],
            'languages' => ['English', 'Hindi', 'Marathi'],
            'publicVisible' => true,
            'createLogin' => true,
        ])->assertCreated()
            ->assertJsonPath('name', 'Priya Sharma')
            ->assertJsonPath('womenInstructor', true);

        $this->getJson('/api/schools/'.$school->id.'/instructors')
            ->assertOk()
            ->assertJsonCount(1);

        $this->assertDatabaseHas('users', [
            'email' => 'priya@example.com',
            'role' => 'instructor',
            'school_id' => $school->id,
        ]);
    }

    public function test_other_school_cannot_list_instructors(): void
    {
        ['school' => $school] = $this->seedSchool();
        $other = User::factory()->create(['role' => 'school', 'school_id' => 999]);

        Sanctum::actingAs($other);

        $this->getJson('/api/schools/'.$school->id.'/instructors')->assertForbidden();
    }

    public function test_public_trainers_only_show_visible_active(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seedSchool();

        Instructor::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Public Trainer',
            'status' => 'active',
            'public_visible' => true,
            'years_experience' => 3,
        ]);

        Instructor::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Hidden Trainer',
            'status' => 'active',
            'public_visible' => false,
        ]);

        $this->getJson('/api/schools/slug/trainer-school/trainers')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Public Trainer']);
    }

    public function test_document_flow(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seedSchool();

        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/schools/'.$school->id.'/instructors', [
            'name' => 'Doc Trainer',
        ])->assertCreated();

        $id = $created->json('id');

        $doc = $this->postJson('/api/instructors/'.$id.'/documents', [
            'type' => 'driving_license',
            'filePath' => '/uploads/dl.pdf',
            'fileName' => 'dl.pdf',
        ])->assertCreated()
            ->assertJsonPath('status', 'uploaded');

        $this->patchJson('/api/instructors/'.$id.'/documents/'.$doc->json('id'), [
            'status' => 'verified',
        ])->assertOk()
            ->assertJsonPath('status', 'verified');
    }

    public function test_deactivate_instructor(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seedSchool();

        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/schools/'.$school->id.'/instructors', [
            'name' => 'Temp',
            'publicVisible' => true,
        ])->assertCreated();

        $id = $created->json('id');

        $this->deleteJson('/api/instructors/'.$id)->assertNoContent();

        $this->assertDatabaseHas('instructors', [
            'id' => $id,
            'status' => 'terminated',
            'public_visible' => false,
        ]);
    }
}
