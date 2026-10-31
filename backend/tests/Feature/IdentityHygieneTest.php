<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\TeamInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/** BSEC-06 / 23 / 26: lowercase emails, enumeration parity, token hygiene. */
class IdentityHygieneTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email, string $role = 'learner')
    {
        return $this->postJson('/api/auth/register', [
            'name' => 'Pat', 'email' => $email, 'password' => 'Passw0rdOK', 'role' => $role,
        ]);
    }

    public function test_emails_are_stored_lowercase_and_case_variants_cannot_register(): void
    {
        $this->register('  Victim@Example.COM ')->assertCreated();
        $this->assertSame('victim@example.com', User::first()->email);

        $this->register('victim@example.com')->assertUnprocessable()->assertJsonValidationErrors('email', 'errors');
        $this->register('VICTIM@EXAMPLE.COM')->assertUnprocessable();
        $this->assertSame(1, User::count());

        // Whatever writes a user, the spelling is canonical.
        $u = User::factory()->create(['email' => 'MiXed@Example.com']);
        $this->assertSame('mixed@example.com', $u->email);
    }

    public function test_database_refuses_a_case_variant_even_if_the_app_layer_is_bypassed(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);
        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'X', 'email' => 'DUP@example.com', 'password' => 'x', 'role' => 'learner', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_login_and_reset_ignore_email_case(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'case@example.com', 'password' => 'Passw0rdOK']);

        $this->postJson('/api/auth/login', ['email' => 'CASE@Example.com', 'password' => 'Passw0rdOK'])->assertOk();

        $this->postJson('/api/auth/forgot-password', ['email' => 'Case@Example.com'])->assertOk();
        Notification::assertSentTo(User::first(), QueuedResetPassword::class);
    }

    public function test_reset_mail_is_queued_and_forgot_answers_identically(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new QueuedResetPassword('t'));
        Notification::fake();
        User::factory()->create(['email' => 'exists@example.com']);

        $a = $this->postJson('/api/auth/forgot-password', ['email' => 'exists@example.com']);
        $b = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $this->assertSame($a->status(), $b->status());
        $this->assertSame($a->json(), $b->json());
    }

    public function test_migration_fails_loudly_on_case_duplicates_and_backfills_otherwise(): void
    {
        DB::statement('DROP INDEX users_email_lower_unique');
        DB::table('users')->insert([
            ['name' => 'A', 'email' => 'Twin@Example.com', 'password' => 'x', 'role' => 'learner', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'B', 'email' => 'twin@example.com', 'password' => 'x', 'role' => 'learner', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $migration = require database_path('migrations/2026_10_04_000002_unique_lowercase_user_email.php');

        try {
            $migration->up();
            $this->fail('expected the migration to refuse duplicates');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('twin@example.com (2 accounts)', $e->getMessage());
        }

        DB::table('users')->where('name', 'B')->delete();
        $migration->up();
        $this->assertSame('twin@example.com', DB::table('users')->value('email'));
    }

    public function test_invite_answer_is_the_same_for_unknown_and_unmanageable_accounts(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create(['name' => 'S1', 'slug' => 's1', 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);
        SchoolAdmin::create(['school_id' => $school->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);

        $otherOwner = User::factory()->create(['role' => 'school']);
        $school2 = School::create(['name' => 'S2', 'slug' => 's2', 'user_id' => $otherOwner->id]);
        $otherOwner->update(['school_id' => $school2->id]);
        $learner = User::factory()->create(['role' => 'learner', 'school_id' => $school2->id]);

        Sanctum::actingAs($owner);
        $fresh = $this->postJson("/api/schools/{$school->id}/team", ['email' => 'brandnew@example.com'])->assertCreated();
        $taken = $this->postJson("/api/schools/{$school->id}/team", ['email' => $otherOwner->email])->assertCreated();
        $learnerRes = $this->postJson("/api/schools/{$school->id}/team", ['email' => $learner->email])->assertCreated();

        foreach ([$taken, $learnerRes] as $res) {
            $this->assertSame(array_keys($fresh->json()), array_keys($res->json()));
            $this->assertNull($res->json('userId'));
        }
        // The other school's owner is untouched and cannot be pulled in by accepting.
        $this->assertSame($school2->id, $otherOwner->fresh()->school_id);
    }

    public function test_accepting_an_invite_signs_other_sessions_out_but_keeps_the_current_one(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create(['name' => 'S1', 'slug' => 's1x', 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);
        SchoolAdmin::create(['school_id' => $school->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);

        $invitee = User::factory()->create(['role' => 'school', 'password' => 'Passw0rdOK']);
        $old = $invitee->createToken('old-device')->plainTextToken;
        $current = $invitee->createToken('current')->plainTextToken;

        Sanctum::actingAs($owner);
        $this->postJson("/api/schools/{$school->id}/team", ['email' => $invitee->email])->assertCreated();
        $token = null;
        Notification::assertSentTo(new AnonymousNotifiable, TeamInvitation::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->app['auth']->forgetGuards();

        $this->withToken($current)->postJson('/api/team/accept', ['token' => $token])->assertOk();

        $this->assertNull(PersonalAccessToken::findToken($old));
        $this->assertNotNull(PersonalAccessToken::findToken($current));
    }

    public function test_messaging_does_not_reveal_whether_a_user_exists_in_another_school(): void
    {
        $a = User::factory()->create(['role' => 'school']);
        $sa = School::create(['name' => 'MA', 'slug' => 'ma', 'user_id' => $a->id]);
        $a->update(['school_id' => $sa->id]);
        $b = User::factory()->create(['role' => 'school']);
        $sb = School::create(['name' => 'MB', 'slug' => 'mb', 'user_id' => $b->id]);
        $b->update(['school_id' => $sb->id]);

        Sanctum::actingAs($a);
        $missing = $this->postJson('/api/messages', ['receiverId' => 999999, 'body' => 'hi'])->assertUnprocessable();
        $foreign = $this->postJson('/api/messages', ['receiverId' => $b->id, 'body' => 'hi'])->assertUnprocessable();
        $this->assertSame($missing->json(), $foreign->json());
    }

    public function test_tokens_are_named_admins_expire_sooner_and_logout_all_revokes_everything(): void
    {
        User::factory()->create(['email' => 'adm@example.com', 'role' => 'admin', 'password' => 'Passw0rdOK']);
        User::factory()->create(['email' => 'lrn@example.com', 'role' => 'learner', 'password' => 'Passw0rdOK']);

        $adminToken = $this->postJson('/api/auth/login', ['email' => 'adm@example.com', 'password' => 'Passw0rdOK'], ['User-Agent' => 'UA1'])->json('token');
        $learnerToken = $this->postJson('/api/auth/login', ['email' => 'lrn@example.com', 'password' => 'Passw0rdOK'])->json('token');

        $adminRow = PersonalAccessToken::findToken($adminToken);
        $this->assertStringStartsWith('web:', $adminRow->name);
        $this->assertTrue($adminRow->expires_at->lte(now()->addHours(8)->addMinute()));
        $this->assertNull(PersonalAccessToken::findToken($learnerToken)->expires_at, 'role tokens use the global sanctum expiry');

        $second = User::where('email', 'lrn@example.com')->first()->createToken('other')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($learnerToken)->postJson('/api/auth/logout-all')->assertOk();
        $this->assertNull(PersonalAccessToken::findToken($learnerToken));
        $this->assertNull(PersonalAccessToken::findToken($second));
        // Other accounts are unaffected.
        $this->assertNotNull(PersonalAccessToken::findToken($adminToken));

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/logout-all')->assertUnauthorized();
    }
}
