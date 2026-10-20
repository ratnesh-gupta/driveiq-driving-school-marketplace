<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Learner;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-406: self-registered learners are linked to the school that enrols them. */
class LearnerLinkingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Link School', 'slug' => 'link-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
    }

    private function inquiryFrom(string $email): Inquiry
    {
        return Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'name' => 'Asha',
            'phone' => '9000000123',
            'email' => $email,
            'vehicle_type' => 'car',
            'status' => 'pending',
        ]);
    }

    public function test_learner_who_enquired_while_signed_in_is_linked_on_conversion(): void
    {
        $registered = $this->postJson('/api/auth/register', [
            'name' => 'Asha', 'email' => 'Asha@Example.com', 'password' => 'Passw0rdX', 'role' => 'learner',
        ])->assertCreated();
        $learnerUser = User::find($registered->json('user.id'));

        $inquiryId = $this->withToken($registered->json('token'))->postJson('/api/inquiries', [
            'schoolId' => $this->school->id,
            'name' => 'Asha',
            'phone' => '9000000123',
            'email' => 'asha@example.com',
            'vehicleType' => 'car',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ])->assertCreated()->json('id');
        $this->assertDatabaseHas('inquiries', ['id' => $inquiryId, 'user_id' => $learnerUser->id]);
        $this->app['auth']->forgetGuards();

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/inquiries/{$inquiryId}/convert")->assertCreated();

        $learnerUser->refresh();
        $this->assertSame($this->school->id, (int) $learnerUser->school_id);
        $this->assertSame('learner', $learnerUser->role);
        $this->assertDatabaseHas('learners', ['user_id' => $learnerUser->id, 'school_id' => $this->school->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $learnerUser->id, 'type' => 'learner_enrolled']);

        Sanctum::actingAs($learnerUser);
        $this->getJson('/api/learner/me')->assertOk()->assertJsonPath('learner.name', 'Asha');
    }

    public function test_knowing_a_learners_email_is_not_enough_to_link_them(): void
    {
        $learnerUser = User::factory()->create(['role' => 'learner', 'email' => 'unaware@example.com', 'school_id' => null]);

        Sanctum::actingAs($this->owner);

        // An anonymous enquiry naming their email (anyone can submit one)...
        $inquiry = $this->inquiryFrom('unaware@example.com');
        $this->postJson("/api/inquiries/{$inquiry->id}/convert")->assertCreated();

        // ...or a learner record typed in with their email.
        $this->postJson("/api/schools/{$this->school->id}/learners", [
            'name' => 'Unaware', 'email' => 'unaware@example.com',
        ])->assertCreated();

        $this->assertNull($learnerUser->fresh()->school_id);
        $this->assertSame(0, Learner::withoutGlobalScope('school')->where('user_id', $learnerUser->id)->count());
    }

    public function test_other_accounts_are_never_modified(): void
    {
        $otherOwner = $this->otherSchoolUser(['email' => 'boss@other.example']);
        $instructor = User::factory()->create(['role' => 'instructor', 'email' => 'coach@example.com', 'school_id' => $this->school->id]);

        Sanctum::actingAs($this->owner);

        // Plain conversion: record created without a login, accounts untouched.
        $inquiry = $this->inquiryFrom('boss@other.example');
        $this->postJson("/api/inquiries/{$inquiry->id}/convert")->assertCreated();
        $this->assertDatabaseHas('learners', ['converted_from_inquiry_id' => $inquiry->id, 'user_id' => null]);

        // Asking for a login for someone else's account is refused.
        $this->postJson("/api/schools/{$this->school->id}/learners", [
            'name' => 'Coach', 'email' => 'coach@example.com', 'createLogin' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame('school', $otherOwner->fresh()->role);
        $this->assertNotSame($this->school->id, (int) $otherOwner->fresh()->school_id);
        $this->assertSame('instructor', $instructor->fresh()->role);
    }

    public function test_creating_a_learner_keeps_all_submitted_fields(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/schools/{$this->school->id}/learners", [
            'name' => 'Ravi',
            'email' => 'ravi@example.com',
            'vehicleType' => 'two_wheeler',
            'startDate' => '2026-10-05',
            'emergencyContact' => '9000000999',
            'createLogin' => true,
        ])->assertCreated();

        // Before the fix, camelCase input reached createLearner() unmapped and
        // these fields (and createLogin) were silently dropped.
        $this->assertDatabaseHas('learners', [
            'name' => 'Ravi',
            'vehicle_type' => 'two_wheeler',
            'emergency_contact' => '9000000999',
        ]);
        $this->assertSame('2026-10-05', Learner::withoutGlobalScope('school')->where('name', 'Ravi')->first()->start_date->toDateString());
        $this->assertDatabaseHas('users', ['email' => 'ravi@example.com', 'role' => 'learner', 'school_id' => $this->school->id]);
    }

    public function test_learner_enrolled_elsewhere_is_not_moved(): void
    {
        $elsewhere = $this->otherSchoolUser(['role' => 'learner', 'email' => 'moved@example.com']);
        $inquiry = $this->inquiryFrom('moved@example.com');

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/inquiries/{$inquiry->id}/convert")->assertCreated();

        $this->assertNotSame($this->school->id, (int) $elsewhere->fresh()->school_id);
        $this->assertSame(0, Learner::withoutGlobalScope('school')->where('user_id', $elsewhere->id)->count());
    }
}
