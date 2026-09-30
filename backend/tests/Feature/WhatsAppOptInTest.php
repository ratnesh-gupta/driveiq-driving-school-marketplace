<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\Inquiry;
use App\Models\Learner;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1002: numbers and opt-in are the person's own, recorded as consent. */
class WhatsAppOptInTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'WA School', 'slug' => 'wa-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
    }

    private function enquire(array $extra = [])
    {
        return $this->postJson('/api/inquiries', $extra + [
            'schoolId' => $this->school->id, 'name' => 'Asha', 'phone' => '98765 43210', 'vehicleType' => 'car',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ]);
    }

    public function test_staff_opt_in_needs_a_valid_number_and_is_recorded_and_withdrawable(): void
    {
        Sanctum::actingAs($this->owner);

        $this->putJson('/api/me/whatsapp', ['optIn' => true])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->putJson('/api/me/whatsapp', ['phone' => '12345', 'optIn' => true])->assertUnprocessable();

        $this->putJson('/api/me/whatsapp', ['phone' => '98765 43210', 'optIn' => true])
            ->assertOk()->assertJsonPath('phone', '+919876543210')->assertJsonPath('optedIn', true);
        $this->assertSame('+919876543210', $this->owner->fresh()->routeNotificationForWhatsapp());
        $this->assertSame(1, Consent::active()->where('user_id', $this->owner->id)->where('purpose', 'whatsapp_updates')->count());

        $this->putJson('/api/me/whatsapp', ['optIn' => false])->assertOk()->assertJsonPath('optedIn', false);
        $this->assertNull($this->owner->fresh()->routeNotificationForWhatsapp());
        $this->assertSame(0, Consent::active()->where('user_id', $this->owner->id)->where('purpose', 'whatsapp_updates')->count());

        // A deactivated account never gets messages.
        $this->owner->forceFill(['whatsapp_opt_in_at' => now(), 'deactivated_at' => now()])->save();
        $this->assertNull($this->owner->fresh()->routeNotificationForWhatsapp());
    }

    public function test_enquiry_opt_in_is_explicit_and_follows_the_learner(): void
    {
        $this->enquire()->assertCreated();
        $this->assertNull(Inquiry::withoutGlobalScope('school')->latest('id')->first()->whatsapp_opt_in_at);

        $this->enquire(['whatsappOptIn' => true])->assertCreated();
        $lead = Inquiry::withoutGlobalScope('school')->latest('id')->first();
        $this->assertNotNull($lead->whatsapp_opt_in_at);
        $this->assertNotNull($lead->whatsapp_opt_in_ip);

        Sanctum::actingAs($this->owner->refresh());
        $this->postJson("/api/inquiries/{$lead->id}/convert")->assertCreated();
        $learner = Learner::withoutGlobalScope('school')->where('converted_from_inquiry_id', $lead->id)->firstOrFail();
        $this->assertSame('98765 43210', $learner->whatsappNumber());

        // A school cannot switch it on for someone who did not opt in.
        $other = Learner::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => 'Bina', 'mobile' => '9876500000', 'status' => 'active']);
        $this->patchJson("/api/learners/{$other->id}", ['whatsappOptInAt' => now()->toISOString()])->assertOk();
        $this->assertNull($other->fresh()->whatsappNumber());
    }

    public function test_learner_with_an_account_decides_for_themself(): void
    {
        $user = User::factory()->create(['role' => 'learner', 'school_id' => $this->school->id]);
        $learner = Learner::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'name' => 'Asha', 'mobile' => '9876543210',
            'status' => 'active', 'whatsapp_opt_in_at' => now(),
        ]);
        $this->assertNull($learner->whatsappNumber(), 'account holder has not opted in on their account');

        Sanctum::actingAs($user);
        $this->putJson('/api/me/whatsapp', ['phone' => '9876501234', 'optIn' => true])->assertOk();
        $this->assertSame('+919876501234', $learner->fresh()->whatsappNumber());

        $this->putJson('/api/me/whatsapp', ['optIn' => false])->assertOk();
        $this->assertNull($learner->fresh()->whatsappNumber());
        $this->assertNull($learner->fresh()->whatsapp_opt_in_at);
    }

    public function test_signed_in_enquirer_gets_a_consent_row_and_school_switch_is_a_setting(): void
    {
        $user = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($user);
        $this->enquire(['whatsappOptIn' => true])->assertCreated();
        $this->assertTrue(Consent::active()->where('user_id', $user->id)->where('purpose', 'whatsapp_updates')->exists());

        Sanctum::actingAs($this->owner->refresh());
        $this->getJson("/api/schools/{$this->school->id}/settings")->assertOk()->assertJsonPath('settings.notifications.whatsapp', false);
        $this->putJson("/api/schools/{$this->school->id}/settings", ['settings' => ['notifications' => ['whatsapp' => true]]])
            ->assertOk()->assertJsonPath('settings.notifications.whatsapp', true);
    }
}
