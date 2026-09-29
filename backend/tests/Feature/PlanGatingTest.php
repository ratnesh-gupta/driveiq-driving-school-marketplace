<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-802: plan entitlements, read-only gating and the feature trial. */
class PlanGatingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Gate School', 'slug' => 'gate-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        Sanctum::actingAs($this->owner->refresh());
    }

    private function endTrial(): void
    {
        Subscription::withoutGlobalScope('school')->where('school_id', $this->school->id)
            ->where('status', 'trial')->update(['expires_at' => now()->subMinute()]);
    }

    private function onPlan(string $code): void
    {
        $this->endTrial();
        app(SubscriptionService::class)->assign($this->school->id, $code, 1);
    }

    private function addLearner()
    {
        return $this->postJson("/api/schools/{$this->school->id}/learners", ['name' => 'Asha']);
    }

    private function addInstructor()
    {
        return $this->postJson("/api/schools/{$this->school->id}/instructors", ['name' => 'Coach']);
    }

    public function test_new_school_gets_a_feature_trial_with_everything_unlocked(): void
    {
        $this->addLearner()->assertCreated();
        $this->addInstructor()->assertCreated();
        $this->getJson("/api/schools/{$this->school->id}/analytics")->assertOk();

        $this->getJson("/api/schools/{$this->school->id}/entitlements")
            ->assertOk()
            ->assertJsonPath('plan', 'basic')
            ->assertJsonPath('trial.active', true)
            ->assertJsonPath('lockedFeatures', []);
    }

    public function test_trial_never_buys_visibility(): void
    {
        $this->getJson("/api/schools/{$this->school->id}")
            ->assertOk()
            ->assertJsonPath('planCode', 'basic')
            ->assertJsonPath('isSponsored', false);
    }

    public function test_after_the_trial_basic_is_read_only_and_data_stays_visible(): void
    {
        $this->addLearner()->assertCreated();
        $learnerId = Learner::withoutGlobalScope('school')->value('id');
        $this->endTrial();

        $this->getJson("/api/schools/{$this->school->id}/learners")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/learners/{$learnerId}")->assertOk();

        $this->addLearner()
            ->assertStatus(402)
            ->assertJsonPath('code', 'plan_required')
            ->assertJsonPath('feature', 'learners')
            ->assertJsonPath('requiredPlan', 'featured');
        $this->patchJson("/api/learners/{$learnerId}", ['name' => 'Changed'])->assertStatus(402);
        $this->addInstructor()->assertStatus(402)->assertJsonPath('requiredPlan', 'premium');

        // Premium views are gated on reads too.
        $this->getJson("/api/schools/{$this->school->id}/analytics")->assertStatus(402);

        // The lead engine stays free.
        $this->getJson('/api/inquiries')->assertOk();
    }

    public function test_featured_unlocks_learners_but_not_premium_modules(): void
    {
        $this->onPlan('featured');

        $this->addLearner()->assertCreated();
        $this->addInstructor()->assertStatus(402);
        $this->postJson("/api/schools/{$this->school->id}/vehicles", ['registrationNumber' => 'MH12AB1'])->assertStatus(402);

        $this->getJson("/api/schools/{$this->school->id}/entitlements")
            ->assertJsonPath('plan', 'featured')
            ->assertJsonPath('features', ['learners', 'documents']);
    }

    public function test_premium_unlocks_operations(): void
    {
        $this->onPlan('premium');

        $this->addInstructor()->assertCreated();
        $this->postJson("/api/schools/{$this->school->id}/vehicles", ['registrationNumber' => 'MH12AB2'])->assertCreated();
        $this->getJson("/api/schools/{$this->school->id}/analytics")->assertOk();
    }

    public function test_learners_of_a_basic_school_cannot_upload_documents(): void
    {
        $this->addLearner()->assertCreated();
        $learner = Learner::withoutGlobalScope('school')->first();
        $learnerUser = User::factory()->create(['role' => 'learner', 'school_id' => $this->school->id]);
        $learner->update(['user_id' => $learnerUser->id]);
        $this->endTrial();

        Sanctum::actingAs($learnerUser);
        $this->postJson("/api/learners/{$learner->id}/documents", ['type' => 'pan'])->assertStatus(402);
        $this->getJson("/api/learners/{$learner->id}/documents")->assertOk();
    }

    public function test_role_check_still_answers_first_and_admins_are_never_gated(): void
    {
        $this->endTrial();

        Sanctum::actingAs(User::factory()->create(['role' => 'learner', 'school_id' => $this->school->id]));
        $this->addLearner()->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->addLearner()->assertCreated();
    }

    public function test_enforcement_can_be_switched_off(): void
    {
        $this->endTrial();
        config(['plans.enforce' => false]);

        $this->addInstructor()->assertCreated();
    }

    public function test_trial_is_granted_once(): void
    {
        $this->assertNull(app(SubscriptionService::class)->startTrial($this->school->id));
        $this->assertSame(1, Subscription::withoutGlobalScope('school')->where('school_id', $this->school->id)->count());
    }

    public function test_entitlements_are_private_to_the_school(): void
    {
        Sanctum::actingAs($this->otherSchoolUser());

        $this->getJson("/api/schools/{$this->school->id}/entitlements")->assertForbidden();
    }
}
