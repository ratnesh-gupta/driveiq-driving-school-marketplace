<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Instructor;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearnerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seed(): array
    {
        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner-p7']);
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'name' => 'Learner School',
            'slug' => 'learner-school',
            'locality_id' => $locality->id,
            'address' => 'Baner',
            'phone' => '9000010000',
            'user_id' => $owner->id,
        ]);
        $owner->update(['school_id' => $school->id]);

        $instructor = Instructor::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Trainer',
            'status' => 'active',
        ]);

        return compact('owner', 'school', 'instructor');
    }

    public function test_convert_inquiry_to_learner(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seed();

        $inquiry = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Asha Patil',
            'phone' => '9876543210',
            'email' => 'asha@example.com',
            'vehicle_type' => 'car',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/inquiries/'.$inquiry->id.'/convert', [
            'createLogin' => true,
        ])->assertCreated()
            ->assertJsonPath('name', 'Asha Patil')
            ->assertJsonPath('convertedFromInquiryId', $inquiry->id);

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'status' => 'converted',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'asha@example.com',
            'role' => 'learner',
        ]);
    }

    public function test_assign_instructor(): void
    {
        ['owner' => $owner, 'school' => $school, 'instructor' => $instructor] = $this->seed();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/schools/'.$school->id.'/learners', [
            'name' => 'Bina',
            'mobile' => '9000000001',
        ])->assertCreated();

        $id = $created->json('id');

        $this->postJson('/api/learners/'.$id.'/assign', [
            'instructorId' => $instructor->id,
        ])->assertOk()
            ->assertJsonPath('assignedInstructorId', $instructor->id);
    }

    public function test_other_school_cannot_list_learners(): void
    {
        ['school' => $school] = $this->seed();
        $other = User::factory()->create(['role' => 'school', 'school_id' => 999]);

        Sanctum::actingAs($other);

        $this->getJson('/api/schools/'.$school->id.'/learners')->assertForbidden();
    }

    public function test_document_verify_flow(): void
    {
        ['owner' => $owner, 'school' => $school] = $this->seed();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/schools/'.$school->id.'/learners', [
            'name' => 'Doc Learner',
        ])->assertCreated();

        $id = $created->json('id');

        $doc = $this->postJson('/api/learners/'.$id.'/documents', [
            'type' => 'aadhaar',
            'filePath' => '/uploads/aadhaar.pdf',
        ])->assertCreated();

        $this->patchJson('/api/learners/'.$id.'/documents/'.$doc->json('id'), [
            'status' => 'verified',
        ])->assertOk()
            ->assertJsonPath('status', 'verified');
    }
}
