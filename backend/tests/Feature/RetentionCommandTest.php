<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DIQ-606: retention job is a dry run by default and respects legal holds. */
class RetentionCommandTest extends TestCase
{
    use RefreshDatabase;

    private function inquiry(School $school, string $updatedAt): int
    {
        return DB::table('inquiries')->insertGetId([
            'school_id' => $school->id, 'name' => 'Lead', 'phone' => '9000000000', 'vehicle_type' => 'car',
            'status' => 'pending', 'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ]);
    }

    private function seedData(): array
    {
        $school = School::create(['name' => 'Old School', 'slug' => 'old-school']);
        $held = School::create(['name' => 'Held School', 'slug' => 'held-school']);
        $old = now()->subMonths(30)->toDateTimeString();
        $recent = now()->subMonths(2)->toDateTimeString();

        $a = User::factory()->create(['role' => 'school', 'school_id' => $school->id]);
        $b = User::factory()->create(['role' => 'learner', 'school_id' => $school->id]);
        $thread = DB::table('message_threads')->insertGetId([
            'school_id' => $school->id, 'participant_a_id' => $a->id, 'participant_b_id' => $b->id,
            'created_at' => $old, 'updated_at' => $old,
        ]);
        DB::table('messages')->insert([
            'school_id' => $school->id, 'thread_id' => $thread, 'sender_id' => $a->id, 'receiver_id' => $b->id,
            'body' => 'old', 'created_at' => $old, 'updated_at' => $old,
        ]);
        DB::table('contact_messages')->insert([
            ['name' => 'X', 'email' => 'x@example.com', 'subject' => 's', 'message' => 'm', 'status' => 'closed', 'created_at' => $old, 'updated_at' => $old],
            ['name' => 'Y', 'email' => 'y@example.com', 'subject' => 's', 'message' => 'm', 'status' => 'new', 'created_at' => $old, 'updated_at' => $old],
        ]);
        AuditLog::log('create', 'Thing', 1, [], [], $school->id);

        return [
            'oldLead' => $this->inquiry($school, $old),
            'recentLead' => $this->inquiry($school, $recent),
            'heldLead' => $this->inquiry($held, $old),
            'heldSchool' => $held,
        ];
    }

    public function test_default_run_reports_without_deleting(): void
    {
        $this->seedData();

        $this->artisan('driveiq:retention')
            ->expectsOutputToContain('dry run')
            ->assertSuccessful();

        $this->assertSame(3, DB::table('inquiries')->count());
        $this->assertSame(1, DB::table('messages')->count());
        $this->assertSame(2, DB::table('contact_messages')->count());
    }

    public function test_execute_deletes_expired_rows_but_keeps_holds_recent_rows_and_audit_logs(): void
    {
        $ids = $this->seedData();
        config(['retention.legal_hold_school_ids' => [$ids['heldSchool']->id]]);

        $this->artisan('driveiq:retention', ['--execute' => true])->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$ids['recentLead'], $ids['heldLead']],
            DB::table('inquiries')->pluck('id')->all()
        );
        $this->assertSame(0, DB::table('messages')->count());
        $this->assertSame(0, DB::table('message_threads')->count());
        // Only closed contact messages expire.
        $this->assertSame(['new'], DB::table('contact_messages')->pluck('status')->all());
        $this->assertSame(1, DB::table('audit_logs')->count());
    }
}
