<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

/** DIQ-304 (append-only audit), DIQ-305 (messaging pairs), DIQ-306 covered in ApiPhaseTwoModulesTest. */
class AuditAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function schoolWithOwner(): array
    {
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create(['name' => 'Audit School', 'slug' => 'audit-school', 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);

        return [$owner->refresh(), $school];
    }

    public function test_audit_entries_cannot_be_changed_via_eloquent(): void
    {
        $log = AuditLog::log('create', 'Thing', 1, [], ['a' => 1]);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_entries_cannot_be_deleted_via_eloquent(): void
    {
        $log = AuditLog::log('create', 'Thing', 1);

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_database_rejects_raw_update_and_delete(): void
    {
        $log = AuditLog::log('create', 'Thing', 1, [], ['a' => 1]);

        try {
            // Nested transaction = savepoint, so the rejected statement does not
            // abort the test's outer transaction.
            DB::transaction(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']));
            $this->fail('UPDATE should have been rejected');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            DB::transaction(fn () => DB::table('audit_logs')->where('id', $log->id)->delete());
            $this->fail('DELETE should have been rejected');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame('create', DB::table('audit_logs')->where('id', $log->id)->value('action'));
    }

    public function test_deleting_the_actor_keeps_the_entry_and_nulls_user_id(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($actor);
        $log = AuditLog::log('create', 'Thing', 1, [], ['a' => 1]);

        $actor->delete();

        $row = DB::table('audit_logs')->find($log->id);
        $this->assertNotNull($row);
        $this->assertNull($row->user_id);
        $this->assertSame('create', $row->action);
    }

    public function test_school_profile_update_is_audited_as_a_diff(): void
    {
        [$owner, $school] = $this->schoolWithOwner();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/schools/{$school->id}", ['description' => 'New description'])->assertOk();

        $entry = $this->getJson("/api/schools/{$school->id}/audit-logs")
            ->assertOk()
            ->collect()
            ->firstWhere('modelType', 'School');

        $this->assertSame('update', $entry['action']);
        $this->assertSame('New description', $entry['newValues']['description']);
        $this->assertArrayHasKey('description', $entry['oldValues']);
        $this->assertArrayNotHasKey('name', $entry['newValues']);
    }

    public function test_admin_edits_appear_in_the_schools_audit_log(): void
    {
        [$owner, $school] = $this->schoolWithOwner();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson("/api/schools/{$school->id}", ['verified' => true])->assertOk();

        Sanctum::actingAs($owner);
        $this->getJson("/api/schools/{$school->id}/audit-logs")
            ->assertOk()
            ->assertJsonFragment(['modelType' => 'School', 'newValues' => ['verified' => true]]);
    }

    public function test_platform_audit_log_is_admin_only(): void
    {
        [$owner, $school] = $this->schoolWithOwner();
        AuditLog::log('create', 'Thing', 1, [], [], $school->id);

        Sanctum::actingAs($owner);
        $this->getJson('/api/admin/audit-logs')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/audit-logs?schoolId='.$school->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.modelType', 'Thing');
    }

    public function test_learners_cannot_message_each_other(): void
    {
        [, $school] = $this->schoolWithOwner();
        $learnerA = User::factory()->create(['role' => 'learner', 'school_id' => $school->id]);
        $learnerB = User::factory()->create(['role' => 'learner', 'school_id' => $school->id]);

        Sanctum::actingAs($learnerA);
        $this->postJson('/api/messages', ['receiverId' => $learnerB->id, 'body' => 'hi'])
            ->assertStatus(422);
    }
}
