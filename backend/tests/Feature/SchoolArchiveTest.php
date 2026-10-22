<?php

namespace Tests\Feature;

use App\Models\Locality;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * DIQ-1304 (QA BUG-04): removing a school archives it. Its records stay,
 * staff are signed out, and the database itself refuses to drop money records.
 */
class SchoolArchiveTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $locality = Locality::create(['name' => 'Baner', 'slug' => 'baner']);
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create([
            'name' => 'Archive Driving School',
            'slug' => 'archive-driving-school',
            'locality_id' => $locality->id,
            'address' => 'Baner Road',
            'phone' => '9000011111',
            'user_id' => $this->owner->id,
            'latitude' => 18.559,
            'longitude' => 73.786,
        ]);
        $this->owner->update(['school_id' => $this->school->id]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function archive(): void
    {
        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/schools/{$this->school->id}?confirm={$this->school->slug}")->assertNoContent();
    }

    public function test_delete_needs_the_slug_typed_to_confirm(): void
    {
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/schools/{$this->school->id}")->assertStatus(422);
        $this->deleteJson("/api/schools/{$this->school->id}?confirm=wrong")->assertStatus(422);

        $this->assertNull($this->school->fresh()->archived_at);
    }

    public function test_delete_archives_the_school_and_keeps_its_records(): void
    {
        $this->owner->createToken('phone');
        $trainer = User::factory()->create(['role' => 'instructor', 'school_id' => $this->school->id]);
        $learner = User::factory()->create(['role' => 'learner', 'school_id' => $this->school->id]);
        $this->assertSame(1, Subscription::where('school_id', $this->school->id)->count()); // the trial
        $this->getJson("/api/schools/slug/{$this->school->slug}")->assertOk();

        $this->archive();

        $school = $this->school->fresh();
        $this->assertNotNull($school, 'The school row must still exist.');
        $this->assertNotNull($school->archived_at);
        $this->assertSame('suspended', $school->listing_status);
        $this->assertSame(1, Subscription::where('school_id', $school->id)->count());

        $this->assertNotNull($this->owner->fresh()->deactivated_at);
        $this->assertSame(0, $this->owner->tokens()->count());
        $this->assertNotNull($trainer->fresh()->deactivated_at);
        $this->assertNull($learner->fresh()->deactivated_at, 'Learners keep access to their own records.');

        $this->getJson("/api/schools/slug/{$school->slug}")->assertNotFound();
    }

    public function test_restore_brings_back_the_listing_and_only_the_staff_archiving_blocked(): void
    {
        $manager = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id, 'deactivated_at' => now()->subMonth()]);

        $this->archive();
        $this->postJson("/api/admin/schools/{$this->school->id}/restore")->assertOk();

        $school = $this->school->fresh();
        $this->assertNull($school->archived_at);
        $this->assertSame('published', $school->listing_status);
        $this->assertNull($this->owner->fresh()->deactivated_at);
        $this->assertNotNull($manager->fresh()->deactivated_at, 'Someone blocked earlier stays blocked.');
    }

    public function test_an_archived_school_cannot_be_republished_without_restoring(): void
    {
        $this->archive();

        $this->patchJson("/api/admin/schools/{$this->school->id}/listing-status", ['status' => 'published'])
            ->assertStatus(422);
    }

    public function test_the_database_refuses_to_delete_a_school_with_money_records(): void
    {
        $this->expectException(QueryException::class);

        $this->school->delete();
    }

    public function test_purge_waits_out_the_tax_retention_period(): void
    {
        $this->archive();

        $this->artisan('driveiq:purge-school', ['id' => $this->school->id, '--force' => true])->assertFailed();
        $this->assertNotNull($this->school->fresh());

        $this->travel(8)->years();
        $this->travel(1)->days();

        $this->artisan('driveiq:purge-school', ['id' => $this->school->id])->assertSuccessful(); // dry run
        $this->assertNotNull($this->school->fresh());

        $this->artisan('driveiq:purge-school', ['id' => $this->school->id, '--force' => true])->assertSuccessful();
        $this->assertNull(School::find($this->school->id));
        $this->assertSame(0, Subscription::where('school_id', $this->school->id)->count());
    }

    public function test_purge_refuses_a_school_that_is_not_archived(): void
    {
        $this->artisan('driveiq:purge-school', ['id' => $this->school->id, '--force' => true])->assertFailed();
    }
}
