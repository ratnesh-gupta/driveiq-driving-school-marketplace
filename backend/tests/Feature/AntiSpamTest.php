<?php

namespace Tests\Feature;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DIQ-404/405: public enquiry form anti-spam and rate limits. */
class AntiSpamTest extends TestCase
{
    use RefreshDatabase;

    private function payload(School $school, array $overrides = []): array
    {
        return array_merge([
            'schoolId' => $school->id,
            'name' => 'Lead',
            'phone' => '9000000001',
            'vehicleType' => 'car',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ], $overrides);
    }

    public function test_honeypot_field_rejects_bots(): void
    {
        $school = School::create(['name' => 'Spam School', 'slug' => 'spam-school']);

        $this->postJson('/api/inquiries', $this->payload($school, ['website' => 'http://spam.example']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('website');

        // An empty honeypot (what the real form sends) is fine.
        $this->postJson('/api/inquiries', $this->payload($school, ['website' => '']))->assertCreated();
    }

    public function test_form_submitted_too_fast_or_without_timestamp_is_rejected(): void
    {
        $school = School::create(['name' => 'Spam School', 'slug' => 'spam-school']);

        $this->postJson('/api/inquiries', $this->payload($school, ['formStartedAt' => now()->getTimestampMs()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('formStartedAt');

        $payload = $this->payload($school);
        unset($payload['formStartedAt']);
        $this->postJson('/api/inquiries', $payload)->assertUnprocessable()->assertJsonValidationErrors('formStartedAt');

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_inquiries_limited_to_five_per_minute_per_ip(): void
    {
        $school = School::create(['name' => 'Spam School', 'slug' => 'spam-school']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/inquiries', $this->payload($school, ['phone' => '900000000'.$i]))->assertCreated();
        }

        $this->postJson('/api/inquiries', $this->payload($school))->assertStatus(429);
    }

    public function test_login_is_limited_per_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong'])->assertStatus(429);
        // A different email from the same IP still has room under the IP limit.
        $this->postJson('/api/auth/login', ['email' => 'other@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }
}
