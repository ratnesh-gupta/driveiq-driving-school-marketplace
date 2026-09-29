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

/**
 * Regression coverage for the M1 security fixes (DIQ-101, 102, 103, 105).
 * Each test reproduces an exploit that worked before the fix.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchoolWithOwner(string $slug): array
    {
        $locality = Locality::first() ?? Locality::create(['name' => 'Baner', 'slug' => 'baner-sec']);
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create([
            'user_id' => $owner->id,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'locality_id' => $locality->id,
            'address' => 'Test Address',
            'phone' => '9000000000',
        ]);
        $owner->update(['school_id' => $school->id]);

        return [$owner->refresh(), $school];
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Account',
            'email' => 'new-'.uniqid().'@example.com',
            'password' => 'Password123',
        ], $overrides);
    }

    // ── DIQ-101: registration is School (owner) or Learner only ──

    public function test_cannot_self_register_as_admin(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload(['role' => 'admin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['role' => 'admin']);
    }

    public function test_cannot_self_register_as_instructor_or_generic_user_or_without_role(): void
    {
        foreach (['instructor', 'user'] as $role) {
            $this->postJson('/api/auth/register', $this->registerPayload(['role' => $role]))
                ->assertUnprocessable();
        }

        $this->postJson('/api/auth/register', $this->registerPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_school_registration_creates_owner_membership(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerPayload(['role' => 'school']))
            ->assertCreated();

        $schoolId = $response->json('schoolId');
        $this->assertNotNull($schoolId);
        $this->assertDatabaseHas('school_admins', [
            'school_id' => $schoolId,
            'user_id' => $response->json('user.id'),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_learner_can_self_register(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload(['role' => 'learner']))
            ->assertCreated()
            ->assertJsonPath('user.role', 'learner')
            ->assertJsonPath('schoolId', null);
    }

    // ── DIQ-105: school scope fails closed ──

    public function test_learner_without_school_sees_no_school_scoped_rows(): void
    {
        [, $school] = $this->makeSchoolWithOwner('scope-school');
        Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'name' => 'Lead',
            'phone' => '9111111111',
            'vehicle_type' => 'car',
            'status' => 'pending',
        ]);

        $learner = User::factory()->create(['role' => 'learner', 'school_id' => null]);
        Sanctum::actingAs($learner);

        // Before the fix the global scope added no filter for school_id = null.
        $this->assertSame(0, Inquiry::count());
        $this->assertSame(1, Inquiry::withoutGlobalScope('school')->count());

        $this->getJson('/api/messages/threads')->assertOk()->assertJsonCount(0);
    }

    // ── DIQ-102: school profile ownership ──

    public function test_school_cannot_update_another_schools_profile(): void
    {
        [$ownerA] = $this->makeSchoolWithOwner('school-a');
        [, $schoolB] = $this->makeSchoolWithOwner('school-b');

        Sanctum::actingAs($ownerA);
        $this->patchJson("/api/schools/{$schoolB->id}", ['name' => 'HACKED'])->assertForbidden();

        $this->assertSame('School-b', $schoolB->fresh()->name);
    }

    public function test_school_cannot_set_its_own_rating_review_count_or_slug(): void
    {
        [$owner, $school] = $this->makeSchoolWithOwner('self-inflate');

        Sanctum::actingAs($owner);
        $this->patchJson("/api/schools/{$school->id}", [
            'name' => 'Renamed',
            'rating' => 5,
            'reviewCount' => 999,
            'slug' => 'hijacked-slug',
        ])->assertOk();

        $school->refresh();
        $this->assertSame('Renamed', $school->name);
        $this->assertSame(0, (int) $school->review_count);
        $this->assertNotEquals(5.0, (float) $school->rating);
        $this->assertSame('self-inflate', $school->slug);
    }

    public function test_admin_can_update_any_school(): void
    {
        [, $school] = $this->makeSchoolWithOwner('admin-edit');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->patchJson("/api/schools/{$school->id}", ['name' => 'Admin Renamed', 'verified' => true])
            ->assertOk();

        $this->assertSame('Admin Renamed', $school->fresh()->name);
        $this->assertTrue((bool) $school->fresh()->verified);
    }

    // ── DIQ-103: review moderation is admin-only ──

    public function test_school_cannot_approve_edit_or_delete_its_own_reviews(): void
    {
        [$owner, $school] = $this->makeSchoolWithOwner('self-moderate');
        $review = Review::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'author_name' => 'Unhappy learner',
            'rating' => 1,
            'content' => 'Bad experience',
            'approved' => false,
        ]);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/reviews/{$review->id}", ['approved' => true, 'rating' => 5, 'content' => 'Great'])
            ->assertForbidden();
        $this->deleteJson("/api/reviews/{$review->id}")->assertForbidden();

        $review = Review::withoutGlobalScope('school')->find($review->id);
        $this->assertFalse((bool) $review->approved);
        $this->assertSame(1, (int) $review->rating);
        $this->assertSame('Bad experience', $review->content);

        // Reporting is the school's path instead.
        $this->postJson("/api/reviews/{$review->id}/report", ['reason' => 'fake'])->assertCreated();
    }

    public function test_admin_approval_recalculates_school_rating_from_approved_reviews(): void
    {
        [, $school] = $this->makeSchoolWithOwner('recalc');
        foreach ([[5, true], [3, true], [1, false]] as [$rating, $approved]) {
            Review::withoutGlobalScope('school')->create([
                'school_id' => $school->id,
                'author_name' => 'R'.$rating,
                'rating' => $rating,
                'content' => 'Review',
                'approved' => $approved,
            ]);
        }
        $pending = Review::withoutGlobalScope('school')->where('approved', false)->first();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson("/api/reviews/{$pending->id}", ['approved' => true])->assertOk();

        $school->refresh();
        $this->assertSame(3, (int) $school->review_count);
        $this->assertEquals(3.0, (float) $school->rating);

        $this->deleteJson("/api/reviews/{$pending->id}")->assertNoContent();
        $school->refresh();
        $this->assertSame(2, (int) $school->review_count);
        $this->assertEquals(4.0, (float) $school->rating);
    }

    // ── Found during DIQ-406: createLogin must not repurpose other accounts ──

    public function test_school_cannot_turn_another_schools_owner_into_its_instructor(): void
    {
        [$ownerA, $schoolA] = $this->makeSchoolWithOwner('takeover-a');
        [$ownerB, $schoolB] = $this->makeSchoolWithOwner('takeover-b');

        Sanctum::actingAs($ownerA);
        $this->postJson("/api/schools/{$schoolA->id}/instructors", [
            'name' => 'Not really an instructor',
            'email' => $ownerB->email,
            'createLogin' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $ownerB->refresh();
        $this->assertSame('school', $ownerB->role);
        $this->assertSame($schoolB->id, (int) $ownerB->school_id);
    }

    public function test_school_cannot_turn_another_schools_owner_into_its_learner(): void
    {
        [$ownerA, $schoolA] = $this->makeSchoolWithOwner('takeover-c');
        [$ownerB, $schoolB] = $this->makeSchoolWithOwner('takeover-d');

        Sanctum::actingAs($ownerA);
        $this->postJson("/api/schools/{$schoolA->id}/learners", [
            'name' => 'Not really a learner',
            'email' => $ownerB->email,
            'createLogin' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $ownerB->refresh();
        $this->assertSame('school', $ownerB->role);
        $this->assertSame($schoolB->id, (int) $ownerB->school_id);
    }

    // ── M2 leftovers fixed in M3 ──

    public function test_public_enquiry_cannot_set_its_own_status(): void
    {
        [, $school] = $this->makeSchoolWithOwner('status-school');

        $id = $this->postJson('/api/inquiries', [
            'schoolId' => $school->id,
            'name' => 'Lead',
            'phone' => '9000000001',
            'vehicleType' => 'car',
            'status' => 'converted',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ])->assertCreated()->json('id');

        $this->assertDatabaseHas('inquiries', ['id' => $id, 'status' => 'pending']);
    }

    public function test_pending_reviews_visible_only_to_admin_and_the_school_itself(): void
    {
        [$ownerA, $schoolA] = $this->makeSchoolWithOwner('pending-a');
        [$ownerB, $schoolB] = $this->makeSchoolWithOwner('pending-b');
        Review::withoutGlobalScope('school')->create([
            'school_id' => $schoolB->id, 'author_name' => 'P', 'rating' => 2, 'content' => 'Pending', 'approved' => false,
        ]);
        $url = "/api/reviews?schoolId={$schoolB->id}&includePending=1";

        $this->getJson($url)->assertOk()->assertJsonCount(0);

        Sanctum::actingAs($ownerA);
        $this->getJson($url)->assertOk()->assertJsonCount(0);
        $this->getJson('/api/reviews?includePending=1')->assertOk()->assertJsonCount(0);

        Sanctum::actingAs($ownerB);
        $this->getJson($url)->assertOk()->assertJsonCount(1);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson($url)->assertOk()->assertJsonCount(1);
    }
}
