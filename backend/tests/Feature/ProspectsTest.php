<?php

namespace Tests\Feature;

use App\Models\Locality;
use App\Models\Prospect;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\OutreachSuppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-1103: admin prospects CRM, CSV import and unclaimed listings. */
class ProspectsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Locality $baner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->baner = Locality::create(['name' => 'Baner', 'slug' => 'baner']);
        Sanctum::actingAs($this->admin);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('schools.csv', $content);
    }

    public function test_only_admins_reach_prospects(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'school']));
        $this->getJson('/api/admin/prospects')->assertForbidden();
        $this->postJson('/api/admin/prospects', ['type' => 'school', 'name' => 'X'])->assertForbidden();
    }

    public function test_add_list_and_reject_duplicates(): void
    {
        $this->postJson('/api/admin/prospects', [
            'type' => 'school', 'name' => 'Sai Motor Driving School', 'contactPerson' => 'Sunil',
            'phone' => '098220 12345', 'email' => 'Sai@Example.com', 'localityId' => $this->baner->id,
        ])->assertCreated()->assertJsonPath('localityName', 'Baner')->assertJsonPath('stage', 'new');

        // Same number written differently, or same email in another case.
        $this->postJson('/api/admin/prospects', ['type' => 'school', 'name' => 'Other', 'phone' => '+91 98220-12345'])
            ->assertUnprocessable()->assertJsonPath('duplicateOf.name', 'Sai Motor Driving School');
        $this->postJson('/api/admin/prospects', ['type' => 'trainer', 'name' => 'Other', 'email' => 'sai@example.com '])
            ->assertUnprocessable();

        $this->postJson('/api/admin/prospects', ['type' => 'trainer', 'name' => 'Asha Kulkarni'])->assertCreated();

        $this->getJson('/api/admin/prospects?search=9822012')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/prospects?type=trainer')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Asha Kulkarni');
        $this->getJson('/api/admin/prospects')->assertJsonPath('meta.stageCounts.new', 2);
    }

    public function test_csv_preview_then_import(): void
    {
        Prospect::create(['type' => 'school', 'name' => 'Already Here', 'phone' => '9822000001']);

        $content = "\xEF\xBB\xBFSchool Name,Mobile,Email,Area,Lat,Lng,Notes\n"
            ."Wheels Academy,9822000002,wheels@example.com,Baner,18.56,73.78,Met at RTO\n"
            ."Already Here Again,9822000001,,Baner,,,\n"
            ."Wheels Copy,98220 00002,,,,,\n"
            .",9822000003,,,,,\n"
            ."Mystery Drive,9822000004,not-an-email,,,,\n"
            ."Kothrud Cars,9822000005,,Kothrud,,,\n"
            ."\n";

        $preview = $this->post('/api/admin/prospects/import', ['file' => $this->csv($content), 'type' => 'school', 'preview' => 1], ['Accept' => 'application/json'])
            ->assertOk()->json();

        $this->assertSame(['School Name' => 'name', 'Mobile' => 'phone', 'Email' => 'email', 'Area' => 'locality', 'Lat' => 'latitude', 'Lng' => 'longitude', 'Notes' => 'notes'], $preview['mapping']);
        $this->assertSame(['new', 'duplicate', 'repeated', 'invalid', 'invalid', 'new'], array_column($preview['rows'], 'status'));
        $this->assertSame($this->baner->id, $preview['rows'][0]['data']['locality_id']);
        $this->assertSame(['Unknown locality "Kothrud"'], $preview['rows'][5]['warnings']);
        $this->assertSame(1, Prospect::count(), 'preview saves nothing');

        $this->post('/api/admin/prospects/import', ['file' => $this->csv($content), 'type' => 'school'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('created', 2)->assertJsonPath('skipped', 4);

        $wheels = Prospect::where('name', 'Wheels Academy')->sole();
        $this->assertSame('csv', $wheels->source);
        $this->assertSame('+919822000002', $wheels->phone_e164);
        $this->assertSame($this->admin->id, $wheels->owner_admin_id);
    }

    public function test_csv_without_a_name_column_is_refused(): void
    {
        $this->post('/api/admin/prospects/import', ['file' => $this->csv("phone,email\n9822000002,a@b.com\n"), 'type' => 'school'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('message', 'No name column found. Add a column called "name" (or "school name").');
    }

    public function test_unclaimed_listing_is_hidden_and_has_no_trial(): void
    {
        $prospect = Prospect::create([
            'type' => 'trainer', 'name' => 'Meena Deshpande', 'phone' => '9822000009', 'email' => 'meena@example.com',
            'locality_id' => $this->baner->id, 'latitude' => 18.56, 'longitude' => 73.78, 'google_place_id' => 'place-1',
        ]);

        $this->postJson("/api/admin/prospects/{$prospect->id}/listing")->assertCreated()
            ->assertJsonPath('listing.status', 'unclaimed');
        $this->postJson("/api/admin/prospects/{$prospect->id}/listing")->assertUnprocessable();

        $school = School::find($prospect->fresh()->school_id);
        $this->assertSame('trainer', $school->listing_type);
        $this->assertSame('outreach', $school->source);
        $this->assertNull($school->user_id);
        $this->assertSame(0, Subscription::withoutGlobalScope('school')->where('school_id', $school->id)->count());

        $this->getJson('/api/schools')->assertJsonCount(0);
        $this->getJson("/api/schools/slug/{$school->slug}")->assertNotFound();
    }

    public function test_do_not_contact_removes_the_unclaimed_listing_and_suppresses(): void
    {
        $prospect = Prospect::create(['type' => 'school', 'name' => 'No Thanks', 'phone' => '9822000010', 'email' => 'no@example.com']);
        $this->postJson("/api/admin/prospects/{$prospect->id}/listing")->assertCreated();
        $schoolId = $prospect->fresh()->school_id;

        $this->patchJson("/api/admin/prospects/{$prospect->id}", ['stage' => 'do_not_contact'])
            ->assertOk()->assertJsonPath('stage', 'do_not_contact')->assertJsonPath('contactable', false)->assertJsonPath('listing', null);

        $this->assertNull(School::find($schoolId));
        $this->assertTrue(app(OutreachSuppression::class)->isSuppressed('NO@example.com'));
        $this->assertTrue(app(OutreachSuppression::class)->isSuppressed(null, '+91 98220 00010'));
        $this->assertDatabaseMissing('outreach_suppressions', ['value_hash' => 'no@example.com']);

        $this->postJson("/api/admin/prospects/{$prospect->id}/listing")->assertUnprocessable();
    }

    public function test_retention_drops_stale_prospects_but_keeps_claimed_ones(): void
    {
        $stale = Prospect::create(['type' => 'school', 'name' => 'Stale']);
        $claimed = Prospect::create(['type' => 'school', 'name' => 'Claimed']);
        $claimed->forceFill(['stage' => 'claimed'])->save();
        Prospect::query()->update(['updated_at' => now()->subMonths(13)]);
        Prospect::create(['type' => 'school', 'name' => 'Fresh']);

        $this->artisan('driveiq:retention --execute')->assertSuccessful();

        $this->assertNull(Prospect::find($stale->id));
        $this->assertNotNull(Prospect::find($claimed->id));
        $this->assertSame(2, Prospect::count());
    }
}
