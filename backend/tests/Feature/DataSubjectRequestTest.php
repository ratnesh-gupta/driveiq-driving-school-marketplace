<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DataSubjectRequest;
use App\Models\Learner;
use App\Models\School;
use App\Models\User;
use App\Notifications\DataRequestVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** BSEC-13: verified data-subject requests, audited admin handling, erasure. */
class DataSubjectRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function submit(string $email = 'asha@example.com', string $type = 'deletion'): array
    {
        Notification::fake();
        $res = $this->postJson('/api/data-requests', ['name' => 'Asha', 'email' => $email, 'requestType' => $type])->assertCreated();
        $token = null;
        Notification::assertSentOnDemand(DataRequestVerification::class, function ($n) use (&$token) {
            parse_str(parse_url($n->verifyUrl, PHP_URL_QUERY), $q);
            $token = $q['token'];

            return true;
        });

        return [$res->json('id'), $token];
    }

    public function test_submitting_sends_a_verification_link_and_stores_only_a_hash(): void
    {
        [$id, $token] = $this->submit();

        $row = DataSubjectRequest::find($id);
        $this->assertNull($row->verified_at);
        $this->assertSame(hash('sha256', $token), $row->verification_token_hash);
        $this->assertNotSame($token, $row->verification_token_hash);
        $this->assertEqualsWithDelta(30, now()->diffInDays($row->due_at, true), 1);

        $audit = AuditLog::where('model_type', 'DataSubjectRequest')->where('model_id', $id)->firstOrFail();
        $this->assertStringNotContainsString('asha@example.com', json_encode($audit->new_values));
        $this->assertStringNotContainsString($token, json_encode($audit->new_values));
    }

    public function test_verification_link_works_once_and_expires(): void
    {
        [$id, $token] = $this->submit();

        $this->postJson('/api/data-requests/verify', ['token' => 'x'.str_repeat('a', 30)])->assertStatus(422);
        $this->postJson('/api/data-requests/verify', [])->assertStatus(422);

        $this->postJson('/api/data-requests/verify', ['token' => $token])->assertOk()->assertJsonPath('verified', true);
        $this->assertNotNull(DataSubjectRequest::find($id)->verified_at);
        $this->postJson('/api/data-requests/verify', ['token' => $token])->assertStatus(422);

        [$id2, $token2] = $this->submit('late@example.com');
        $this->travel(49)->hours();
        $this->postJson('/api/data-requests/verify', ['token' => $token2])->assertStatus(422);
        $this->assertNull(DataSubjectRequest::find($id2)->verified_at);
    }

    public function test_confirmation_mail_is_capped_per_address(): void
    {
        RateLimiter::clear('dsr-email:'.sha1('cap@example.com'));
        for ($i = 0; $i < 3; $i++) {
            $this->submit('cap@example.com', 'access');
            $this->app['cache']->forget('throttle'); // keep IP throttle (5/min) out of the way
        }
        $this->postJson('/api/data-requests', ['name' => 'A', 'email' => 'cap@example.com', 'requestType' => 'access'])
            ->assertStatus(429);
        $this->assertSame(3, DataSubjectRequest::where('email', 'cap@example.com')->count());
    }

    public function test_admin_cannot_process_or_complete_an_unverified_request(): void
    {
        [$id] = $this->submit();
        Sanctum::actingAs($this->admin());

        foreach (['in_progress', 'completed'] as $status) {
            $this->patchJson("/api/admin/data-requests/{$id}", ['status' => $status])
                ->assertStatus(409)->assertJsonPath('code', 'verification_required');
        }
        $this->assertSame('pending', DataSubjectRequest::find($id)->status);

        // Rejecting is always allowed, and is audited.
        $this->patchJson("/api/admin/data-requests/{$id}", ['status' => 'rejected'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'data_subject_request_status')->where('model_id', $id)->count());
    }

    public function test_verified_request_can_be_handled_and_is_audited(): void
    {
        [$id, $token] = $this->submit('access@example.com', 'access');
        $this->postJson('/api/data-requests/verify', ['token' => $token])->assertOk();
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/data-requests/{$id}", ['status' => 'completed'])->assertOk();

        $log = AuditLog::where('action', 'data_subject_request_status')->where('model_id', $id)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('pending', $log->old_values['status']);
        $this->assertSame('completed', $log->new_values['status']);
    }

    public function test_only_admins_see_or_handle_requests(): void
    {
        [$id] = $this->submit();

        $this->getJson('/api/admin/data-requests')->assertUnauthorized();
        $this->postJson("/api/admin/data-requests/{$id}/erase")->assertUnauthorized();

        foreach (['school', 'learner', 'instructor'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/admin/data-requests')->assertForbidden();
            $this->patchJson("/api/admin/data-requests/{$id}", ['status' => 'rejected'])->assertForbidden();
            $this->postJson("/api/admin/data-requests/{$id}/erase")->assertForbidden();
        }
    }

    public function test_index_is_paginated_and_exposes_verification_state(): void
    {
        foreach (['a', 'b', 'c'] as $n) {
            $this->submit("{$n}@example.com", 'access');
        }
        Sanctum::actingAs($this->admin());

        $res = $this->getJson('/api/admin/data-requests?perPage=2&page=2')->assertOk();
        $res->assertJsonCount(1);
        $this->assertSame('3', $res->headers->get('X-Total-Count'));
        $res->assertJsonPath('0.verifiedAt', null);
        $this->assertNotNull($res->json('0.dueAt'));
    }

    // --- erasure -----------------------------------------------------------

    /** @return array{0: School, 1: User, 2: Learner} */
    private function learnerWorld(string $slug, string $email): array
    {
        $school = School::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $user = User::factory()->create(['role' => 'learner', 'school_id' => $school->id, 'email' => $email, 'name' => 'Real Name', 'phone' => '+919800000001']);
        $learner = Learner::withoutGlobalScope('school')->create([
            'school_id' => $school->id, 'user_id' => $user->id, 'name' => 'Real Name', 'mobile' => '9800000001',
            'email' => $email, 'address' => '12 MG Road', 'status' => 'active',
        ]);
        DB::table('inquiries')->insert([
            'school_id' => $school->id, 'name' => 'Real Name', 'phone' => '9800000001', 'email' => $email,
            'message' => 'private words', 'vehicle_type' => 'car', 'status' => 'pending', 'user_id' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'school_id' => $school->id, 'user_id' => $user->id,
            'type' => 'x', 'title' => 'Real Name enquired', 'body' => 'b', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('consents')->insert([
            'user_id' => $user->id, 'purpose' => 'privacy', 'version' => '1', 'granted_at' => now(),
            'ip_address' => '1.2.3.4', 'user_agent' => 'UA', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $path = "schools/{$school->id}/learners/{$learner->id}/doc.pdf";
        Storage::disk('local')->put($path, 'x');
        DB::table('learner_documents')->insert([
            'learner_id' => $learner->id, 'school_id' => $school->id, 'type' => 'aadhaar', 'file_path' => $path,
            'file_name' => 'a.pdf', 'status' => 'uploaded', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoice = DB::table('invoices')->insertGetId([
            'school_id' => $school->id, 'learner_id' => $learner->id, 'invoice_number' => 'INV-'.$slug, 'purpose' => 'package',
            'amount' => 5000, 'currency' => 'INR', 'status' => 'paid', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'school_id' => $school->id, 'invoice_id' => $invoice, 'learner_id' => $learner->id, 'purpose' => 'package',
            'amount' => 5000, 'currency' => 'INR', 'method' => 'upi', 'status' => 'paid', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user->createToken('t');

        return [$school, $user, $learner];
    }

    private function verifiedDeletion(string $email): int
    {
        [$id, $token] = $this->submit($email);
        $this->postJson('/api/data-requests/verify', ['token' => $token])->assertOk();

        return $id;
    }

    public function test_erasure_requires_verification_and_a_deletion_request(): void
    {
        $this->learnerWorld('s1', 'asha@example.com');
        [$id] = $this->submit('asha@example.com');
        [$accessId, $t] = $this->submit('asha@example.com', 'access');
        $this->postJson('/api/data-requests/verify', ['token' => $t]);
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/data-requests/{$id}/erase")->assertStatus(409)->assertJsonPath('code', 'verification_required');
        $this->postJson("/api/admin/data-requests/{$accessId}/erase")->assertStatus(422);
        $this->assertSame('Real Name', User::where('email', 'asha@example.com')->value('name'));
    }

    public function test_erasure_anonymises_pii_keeps_billing_and_other_schools_untouched(): void
    {
        [$school, $user, $learner] = $this->learnerWorld('s1', 'asha@example.com');
        [$otherSchool, $otherUser, $otherLearner] = $this->learnerWorld('s2', 'other@example.com');
        $id = $this->verifiedDeletion('asha@example.com');
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/data-requests/{$id}/erase")
            ->assertOk()->assertJsonPath('result', 'erased')->assertJsonPath('status', 'completed')
            ->assertJsonPath('counts.learner_documents', 1);

        $user->refresh();
        $this->assertSame('Erased user', $user->name);
        $this->assertSame("erased-{$user->id}@erased.invalid", $user->email);
        $this->assertNull($user->phone);
        $this->assertNotNull($user->deactivated_at);
        $this->assertSame(0, $user->tokens()->count());

        $l = DB::table('learners')->find($learner->id);
        $this->assertSame('Erased learner', $l->name);
        $this->assertNull($l->mobile);
        $this->assertNull($l->address);
        $this->assertSame(0, DB::table('learner_documents')->where('learner_id', $learner->id)->count());
        Storage::disk('local')->assertMissing("schools/{$school->id}/learners/{$learner->id}/doc.pdf");

        $inq = DB::table('inquiries')->where('school_id', $school->id)->first();
        $this->assertNull($inq->email);
        $this->assertNull($inq->message);
        $this->assertSame(0, DB::table('notifications')->where('user_id', $user->id)->count());
        $consent = DB::table('consents')->where('user_id', $user->id)->first();
        $this->assertNotNull($consent);
        $this->assertNull($consent->ip_address);

        // Legally required billing survives, still attached to the (anonymised) learner.
        $this->assertSame(1, DB::table('payments')->where('learner_id', $learner->id)->count());
        $this->assertSame(1, DB::table('invoices')->where('learner_id', $learner->id)->count());

        // The other school's identically-shaped data is untouched.
        $this->assertSame('Real Name', DB::table('learners')->find($otherLearner->id)->name);
        $this->assertSame('other@example.com', DB::table('inquiries')->where('school_id', $otherSchool->id)->value('email'));
        $this->assertSame(1, DB::table('learner_documents')->where('learner_id', $otherLearner->id)->count());
        $this->assertSame(1, $otherUser->tokens()->count());

        $audit = AuditLog::where('action', 'erase_user')->where('model_id', $user->id)->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertStringNotContainsString('asha@example.com', json_encode($audit->new_values));
        $this->assertSame(1, AuditLog::where('action', 'data_subject_request_erased')->count());
        $this->assertNotNull(DataSubjectRequest::find($id)->erased_at);
    }

    public function test_erasure_is_idempotent(): void
    {
        $this->learnerWorld('s1', 'asha@example.com');
        $id = $this->verifiedDeletion('asha@example.com');
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/data-requests/{$id}/erase")->assertOk()->assertJsonPath('result', 'erased');
        $this->postJson("/api/admin/data-requests/{$id}/erase")->assertOk()->assertJsonPath('result', 'already_erased');
        $this->assertSame('completed', DataSubjectRequest::find($id)->status);
    }

    public function test_erasure_for_an_email_without_account_clears_enquiries_only(): void
    {
        $school = School::create(['name' => 'S', 'slug' => 's']);
        DB::table('inquiries')->insert([
            'school_id' => $school->id, 'name' => 'Lead', 'phone' => '9811111111', 'email' => 'lead@example.com',
            'vehicle_type' => 'car', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = $this->verifiedDeletion('lead@example.com');
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/data-requests/{$id}/erase")->assertOk()->assertJsonPath('counts.inquiries', 1);
        $this->assertNull(DB::table('inquiries')->value('email'));
    }

    public function test_erasure_is_refused_for_school_owners_admins_and_legal_hold(): void
    {
        $owner = User::factory()->create(['role' => 'school', 'email' => 'owner@example.com']);
        $school = School::create(['name' => 'S', 'slug' => 's', 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);
        $id = $this->verifiedDeletion('owner@example.com');
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/data-requests/{$id}/erase")->assertStatus(409)->assertJsonPath('blockers.0', 'school_owner');
        $this->assertSame($owner->name, $owner->fresh()->name);

        $adminTarget = User::factory()->create(['role' => 'admin', 'email' => 'boss@example.com']);
        $id2 = $this->verifiedDeletion('boss@example.com');
        $this->postJson("/api/admin/data-requests/{$id2}/erase")->assertStatus(409)->assertJsonPath('blockers.0', 'platform_admin');

        [$heldSchool] = $this->learnerWorld('held', 'held@example.com');
        config(['retention.legal_hold_school_ids' => [$heldSchool->id]]);
        $id3 = $this->verifiedDeletion('held@example.com');
        $this->postJson("/api/admin/data-requests/{$id3}/erase")->assertStatus(409)->assertJsonPath('blockers.0', 'legal_hold');
        $this->assertSame('Real Name', User::where('email', 'held@example.com')->value('name'));
    }

    public function test_erase_command_is_a_dry_run_by_default(): void
    {
        [, $user] = $this->learnerWorld('s1', 'asha@example.com');

        $this->artisan('driveiq:erase-user', ['email' => 'asha@example.com'])->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame('Real Name', $user->fresh()->name);

        $this->artisan('driveiq:erase-user', ['email' => 'asha@example.com', '--execute' => true])->expectsOutputToContain('Erased')->assertSuccessful();
        $this->assertSame('Erased user', $user->fresh()->name);

        $this->artisan('driveiq:erase-user', ['email' => "erased-{$user->id}@erased.invalid", '--execute' => true])
            ->expectsOutputToContain('Already erased')->assertSuccessful();
        $this->artisan('driveiq:erase-user', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
