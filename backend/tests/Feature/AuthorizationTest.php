<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Locality;
use App\Models\Review;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchoolWithOwner(string $name, string $slug): array
    {
        $locality = Locality::first() ?? Locality::create([
            'name' => 'Test Locality',
            'slug' => 'test-locality-'.uniqid(),
        ]);

        $owner = User::factory()->create(['role' => 'school']);

        $school = School::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
            'locality_id' => $locality->id,
            'address' => 'Test Address',
            'phone' => '9000000000',
        ]);

        $owner->update(['school_id' => $school->id]);
        $owner->refresh();

        return [$owner, $school];
    }

    public function test_school_user_cannot_list_other_schools_inquiries(): void
    {
        [$user1, $school1] = $this->makeSchoolWithOwner('School One', 'school-one');
        [$user2, $school2] = $this->makeSchoolWithOwner('School Two', 'school-two');

        $inquiry1 = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school1->id,
            'name' => 'Lead One',
            'phone' => '9111111111',
            'status' => 'pending',
        ]);

        $inquiry2 = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school2->id,
            'name' => 'Lead Two',
            'phone' => '9222222222',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user1);

        $this->getJson('/api/inquiries')
            ->assertOk()
            ->assertJsonFragment(['id' => $inquiry1->id])
            ->assertJsonMissing(['id' => $inquiry2->id]);
    }

    public function test_school_user_cannot_update_other_schools_inquiry(): void
    {
        [$user1] = $this->makeSchoolWithOwner('School One', 'school-one-upd');
        [, $school2] = $this->makeSchoolWithOwner('School Two', 'school-two-upd');

        $inquiry2 = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school2->id,
            'name' => 'Other Lead',
            'phone' => '9333333333',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user1);

        $this->patchJson('/api/inquiries/'.$inquiry2->id, [
            'status' => 'contacted',
        ])->assertNotFound();
    }

    public function test_school_user_cannot_delete_other_schools_review(): void
    {
        [$user1] = $this->makeSchoolWithOwner('School One', 'school-one-rev');
        [, $school2] = $this->makeSchoolWithOwner('School Two', 'school-two-rev');

        $review2 = Review::withoutGlobalScope('school')->create([
            'school_id' => $school2->id,
            'author_name' => 'Someone',
            'rating' => 4,
            'content' => 'Ok',
            'approved' => true,
        ]);

        Sanctum::actingAs($user1);

        $this->deleteJson('/api/reviews/'.$review2->id)->assertNotFound();
    }

    public function test_school_user_package_create_is_forced_to_own_school(): void
    {
        [$user1, $school1] = $this->makeSchoolWithOwner('School One', 'school-one-pkg');
        [, $school2] = $this->makeSchoolWithOwner('School Two', 'school-two-pkg');

        Sanctum::actingAs($user1);

        $response = $this->postJson('/api/packages', [
            'schoolId' => $school2->id,
            'name' => 'Forced Package',
            'price' => 5000,
            'sessions' => 10,
            'vehicleType' => 'car',
            'transmission' => 'manual',
        ])->assertCreated();

        $this->assertSame($school1->id, $response->json('schoolId') ?? $response->json('school_id'));
    }

    public function test_register_school_sets_user_school_id(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Driving School',
            'email' => 'newschool@example.com',
            'password' => 'password123',
            'role' => 'school',
        ])->assertCreated();

        $this->assertNotNull($response->json('schoolId'));
        $this->assertDatabaseHas('users', [
            'email' => 'newschool@example.com',
            'role' => 'school',
            'school_id' => $response->json('schoolId'),
        ]);
    }
}
