<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** BSEC-14: retention windows for notifications, training records, accounts, payments, consent details. */
class RetentionGapsTest extends TestCase
{
    use RefreshDatabase;

    private function old(int $months = 100): string
    {
        return now()->subMonths($months)->toDateTimeString();
    }

    /** @return array<string, mixed> */
    private function world(): array
    {
        $school = School::create(['name' => 'A', 'slug' => 'a']);
        $held = School::create(['name' => 'Held', 'slug' => 'held']);
        $old = $this->old();
        $recent = now()->subMonth()->toDateTimeString();

        $user = User::factory()->create(['role' => 'learner', 'school_id' => $school->id]);
        $notif = fn (string $at, int $sid) => DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'school_id' => $sid, 'user_id' => $user->id, 'type' => 't', 'title' => 't', 'body' => 'b',
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $notif($old, $school->id);
        $notif($recent, $school->id);
        $notif($old, $held->id);

        $learner = fn (int $sid, string $status, string $at) => DB::table('learners')->insertGetId([
            'school_id' => $sid, 'name' => 'L', 'status' => $status, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $done = $learner($school->id, 'completed', $old);
        $active = $learner($school->id, 'active', $old);
        $heldLearner = $learner($held->id, 'completed', $old);
        $coach = [
            $school->id => DB::table('instructors')->insertGetId(['school_id' => $school->id, 'name' => 'C', 'created_at' => $old, 'updated_at' => $old]),
            $held->id => DB::table('instructors')->insertGetId(['school_id' => $held->id, 'name' => 'C', 'created_at' => $old, 'updated_at' => $old]),
        ];
        foreach ([$done, $active, $heldLearner] as $lid) {
            $sid = DB::table('learners')->find($lid)->school_id;
            DB::table('schedules')->insert([
                'school_id' => $sid, 'instructor_id' => $coach[$sid], 'learner_id' => $lid, 'learner_name' => 'L',
                'session_date' => now()->subMonths(40)->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00',
                'status' => 'completed', 'created_at' => $old, 'updated_at' => $old,
            ]);
        }

        $pay = fn (int $sid, string $at) => DB::table('payments')->insert([
            'school_id' => $sid, 'purpose' => 'package', 'amount' => 100, 'currency' => 'INR', 'method' => 'cash',
            'status' => 'paid', 'created_at' => $at, 'updated_at' => $at,
        ]);
        $pay($school->id, $old);
        $pay($school->id, $recent);
        $pay($held->id, $old);

        DB::table('consents')->insert([
            ['user_id' => $user->id, 'purpose' => 'a', 'version' => '1', 'granted_at' => $old, 'ip_address' => '1.1.1.1', 'user_agent' => 'UA', 'created_at' => $old, 'updated_at' => $old],
            ['user_id' => $user->id, 'purpose' => 'b', 'version' => '1', 'granted_at' => $recent, 'ip_address' => '2.2.2.2', 'user_agent' => 'UA', 'created_at' => $recent, 'updated_at' => $recent],
        ]);

        $stale = User::factory()->create(['role' => 'learner', 'school_id' => $school->id, 'name' => 'Stale Person']);
        DB::table('users')->where('id', $stale->id)->update(['updated_at' => $old, 'created_at' => $old]);
        $fresh = User::factory()->create(['role' => 'learner', 'school_id' => $school->id, 'name' => 'Fresh Person']);
        $staleHeld = User::factory()->create(['role' => 'learner', 'school_id' => $held->id, 'name' => 'Held Person']);
        DB::table('users')->where('id', $staleHeld->id)->update(['updated_at' => $old, 'created_at' => $old]);

        return compact('school', 'held', 'stale', 'fresh', 'staleHeld');
    }

    public function test_dry_run_reports_counts_and_changes_nothing(): void
    {
        $w = $this->world();
        config(['retention.legal_hold_school_ids' => [$w['held']->id]]);

        $this->artisan('driveiq:retention')
            ->expectsOutputToContain('dry run')
            ->expectsTable(['Table', 'Rows', 'Action'], $this->expectedRows(execute: false))
            ->assertSuccessful();

        $this->assertSame(3, DB::table('notifications')->count());
        $this->assertSame(3, DB::table('payments')->count());
        $this->assertSame(3, DB::table('schedules')->count());
        $this->assertSame('Stale Person', $w['stale']->fresh()->name);
        $this->assertSame(2, DB::table('consents')->whereNotNull('ip_address')->count());
    }

    /** Rows for the new jobs are asserted by count; the generic ones are zero here. */
    private function expectedRows(bool $execute): array
    {
        $d = fn (string $verb) => $execute ? $verb.'d' : 'would '.$verb;
        $del = $execute ? 'deleted' : 'would delete';

        return [
            ['inquiries', 0, $del], ['messages', 0, $del], ['contact_messages', 0, $del], ['outbound_messages', 0, $del],
            ['prospects', 0, $del], ['outreach_messages', 0, $del], ['data_subject_requests', 0, $del],
            ['notifications', 1, $del], ['payments', 1, $del], ['invoices (learner)', 0, $del],
            ['training_progress (training)', 0, $del], ['driving_tests (training)', 0, $del],
            ['learner_assignment_history (training)', 0, $del], ['schedules (training)', 1, $del],
            ['consents (ip/user agent)', 1, $execute ? 'cleared' : 'would clear'],
            ['personal_access_tokens (deactivated users)', 0, $del],
            ['users (inactive learners)', 1, $execute ? 'anonymised' : 'would anonymise'],
            ['users (skipped: legal hold)', 1, 'kept'],
        ];
    }

    public function test_execute_applies_windows_and_respects_holds(): void
    {
        $w = $this->world();
        config(['retention.legal_hold_school_ids' => [$w['held']->id]]);

        $this->artisan('driveiq:retention', ['--execute' => true])
            ->expectsTable(['Table', 'Rows', 'Action'], $this->expectedRows(execute: true))
            ->assertSuccessful();

        // Old notification in the non-held school gone; recent and held kept.
        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertSame(2, DB::table('payments')->count());
        // Only the non-active learner's old schedule of the non-held school is gone.
        $this->assertSame(2, DB::table('schedules')->count());
        $this->assertSame(1, DB::table('consents')->whereNotNull('ip_address')->count());
        $this->assertSame(2, DB::table('consents')->count(), 'consent rows themselves stay');

        $this->assertSame('Erased user', $w['stale']->fresh()->name);
        $this->assertSame('Fresh Person', $w['fresh']->fresh()->name);
        $this->assertSame('Held Person', $w['staleHeld']->fresh()->name);
    }

    public function test_a_zero_window_switches_a_job_off_and_tokens_of_deactivated_users_go(): void
    {
        $w = $this->world();
        config(['retention.months.notifications' => 0, 'retention.months.payments' => 0, 'retention.months.accounts_inactive' => 0]);
        $w['fresh']->createToken('t');
        $w['fresh']->forceFill(['deactivated_at' => now()])->save();
        $w['stale']->createToken('t');

        $this->artisan('driveiq:retention', ['--execute' => true])->assertSuccessful();

        $this->assertSame(3, DB::table('notifications')->count());
        $this->assertSame(3, DB::table('payments')->count());
        $this->assertSame('Stale Person', $w['stale']->fresh()->name);
        $this->assertSame(0, $w['fresh']->tokens()->count());
        $this->assertSame(1, $w['stale']->tokens()->count());
    }
}
