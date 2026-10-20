<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-601: real uploads on a private disk, served only through short-lived signed links. */
class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{User, School} */
    private function schoolWithOwner(string $slug): array
    {
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create(['name' => ucfirst($slug), 'slug' => $slug, 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);

        return [$owner->refresh(), $school];
    }

    /** @return array{User, Learner} */
    private function learner(School $school): array
    {
        $user = User::factory()->create(['role' => 'learner', 'school_id' => $school->id]);
        $learner = Learner::withoutGlobalScope('school')->create([
            'school_id' => $school->id, 'user_id' => $user->id, 'name' => 'Learner', 'status' => 'active',
        ]);

        return [$user, $learner];
    }

    /** @return array{User, Instructor} */
    private function instructor(School $school): array
    {
        $user = User::factory()->create(['role' => 'instructor', 'school_id' => $school->id]);
        $instructor = Instructor::withoutGlobalScope('school')->create([
            'school_id' => $school->id, 'user_id' => $user->id, 'name' => 'Coach', 'status' => 'active',
        ]);

        return [$user, $instructor];
    }

    private function pdf(string $name = 'aadhaar.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%fake\n");
    }

    public function test_learner_uploads_own_document_and_downloads_it_via_signed_link(): void
    {
        [, $school] = $this->schoolWithOwner('upload-school');
        [$user, $learner] = $this->learner($school);

        Sanctum::actingAs($user);
        $doc = $this->post("/api/learners/{$learner->id}/documents", [
            'type' => 'aadhaar',
            'file' => $this->pdf(),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('hasFile', true)
            ->assertJsonPath('fileName', 'aadhaar.pdf')
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonMissingPath('filePath');

        // The server chose a path inside the school's folder.
        $path = LearnerDocument::withoutGlobalScope('school')->find($doc->json('id'))->file_path;
        $this->assertStringStartsWith("schools/{$school->id}/learners/{$learner->id}/", $path);
        Storage::disk('local')->assertExists($path);

        $this->getJson("/api/learners/{$learner->id}/documents")->assertOk()->assertJsonCount(1);

        $url = $this->getJson("/api/documents/learner/{$doc->json('id')}/link")
            ->assertOk()
            ->json('url');

        $this->app['auth']->forgetGuards();
        $response = $this->get($url)->assertOk();
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_file_route_requires_a_valid_unexpired_signature(): void
    {
        [$owner, $school] = $this->schoolWithOwner('sig-school');
        [, $learner] = $this->learner($school);

        Sanctum::actingAs($owner);
        $id = $this->post("/api/learners/{$learner->id}/documents", ['type' => 'pan', 'file' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertCreated()->json('id');
        $url = $this->getJson("/api/documents/learner/{$id}/link")->assertOk()->json('url');

        // Unsigned, tampered, or pointed at another document.
        $this->get("/api/documents/learner/{$id}/file")->assertForbidden();
        $this->get($url.'x')->assertForbidden();
        $this->get(str_replace("/learner/{$id}/", '/learner/'.($id + 1).'/', $url))->assertForbidden();

        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_other_schools_and_other_learners_cannot_see_documents(): void
    {
        [$ownerA, $schoolA] = $this->schoolWithOwner('docs-a');
        [$ownerB] = $this->schoolWithOwner('docs-b');
        [, $learner] = $this->learner($schoolA);
        [$otherLearnerUser] = $this->learner($schoolA);

        Sanctum::actingAs($ownerA);
        $id = $this->post("/api/learners/{$learner->id}/documents", ['type' => 'pan', 'file' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertCreated()->json('id');

        foreach ([$ownerB, $otherLearnerUser] as $intruder) {
            Sanctum::actingAs($intruder);
            $this->getJson("/api/documents/learner/{$id}/link")->assertForbidden();
            $this->getJson("/api/learners/{$learner->id}/documents")->assertForbidden();
            $this->post("/api/learners/{$learner->id}/documents", ['type' => 'pan', 'file' => $this->pdf()], ['Accept' => 'application/json'])
                ->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/documents/learner/{$id}/link")->assertUnauthorized();
    }

    public function test_only_pdf_and_images_up_to_5mb_are_accepted(): void
    {
        [$owner, $school] = $this->schoolWithOwner('mime-school');
        [, $learner] = $this->learner($school);
        Sanctum::actingAs($owner);

        $bad = [
            UploadedFile::fake()->createWithContent('run.exe', "MZ\x90\x00binary"),
            UploadedFile::fake()->createWithContent('page.html', '<html><script>alert(1)</script></html>'),
            UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
        ];

        foreach ($bad as $file) {
            $this->post("/api/learners/{$learner->id}/documents", ['type' => 'other', 'file' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        }

        $this->post("/api/learners/{$learner->id}/documents", [
            'type' => 'photo',
            'file' => UploadedFile::fake()->image('me.png'),
        ], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_client_supplied_paths_are_ignored_and_legacy_paths_never_served(): void
    {
        [$owner, $school] = $this->schoolWithOwner('legacy-school');
        [, $learner] = $this->learner($school);
        Sanctum::actingAs($owner);

        $id = $this->postJson("/api/learners/{$learner->id}/documents", [
            'type' => 'pan',
            'filePath' => '../../.env',
        ])->assertCreated()->assertJsonPath('hasFile', false)->json('id');
        $this->assertNull(LearnerDocument::withoutGlobalScope('school')->find($id)->file_path);

        // Rows written by the old API could name any path, even one that exists:
        // outside the school's folder, another school's folder, or traversal.
        $otherSchoolFile = 'schools/'.($school->id + 1).'/learners/1/secret.pdf';
        Storage::disk('local')->put('uploads/aadhaar.pdf', 'X');
        Storage::disk('local')->put($otherSchoolFile, 'X');

        foreach (['uploads/aadhaar.pdf', $otherSchoolFile, "schools/{$school->id}/../../.env", '/etc/passwd'] as $path) {
            $legacy = LearnerDocument::withoutGlobalScope('school')->create([
                'learner_id' => $learner->id, 'school_id' => $school->id, 'type' => 'pan',
                'file_path' => $path, 'status' => 'uploaded',
            ]);

            $this->getJson("/api/documents/learner/{$legacy->id}/link")->assertNotFound();
        }
    }

    public function test_replacing_a_file_removes_the_old_one(): void
    {
        [$owner, $school] = $this->schoolWithOwner('replace-school');
        [, $learner] = $this->learner($school);
        Sanctum::actingAs($owner);

        $id = $this->post("/api/learners/{$learner->id}/documents", ['type' => 'pan', 'file' => $this->pdf('v1.pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->json('id');
        $old = LearnerDocument::withoutGlobalScope('school')->find($id)->file_path;

        $this->post("/api/learners/{$learner->id}/documents/{$id}", [
            '_method' => 'PATCH',
            'file' => $this->pdf('v2.pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('fileName', 'v2.pdf');

        $new = LearnerDocument::withoutGlobalScope('school')->find($id)->file_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    public function test_instructor_manages_only_own_documents_and_cannot_verify_them(): void
    {
        [$owner, $school] = $this->schoolWithOwner('coach-school');
        [$coachUser, $coach] = $this->instructor($school);
        [$colleagueUser] = $this->instructor($school);

        Sanctum::actingAs($coachUser);
        $id = $this->post("/api/instructors/{$coach->id}/documents", [
            'type' => 'driving_license', 'file' => $this->pdf('dl.pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $this->getJson("/api/instructors/{$coach->id}/documents")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/documents/instructor/{$id}/link")->assertOk();
        $this->patchJson("/api/instructors/{$coach->id}/documents/{$id}", ['status' => 'verified'])
            ->assertUnprocessable();

        Sanctum::actingAs($colleagueUser);
        $this->getJson("/api/instructors/{$coach->id}/documents")->assertForbidden();
        $this->getJson("/api/documents/instructor/{$id}/link")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/instructors/{$coach->id}/documents/{$id}", ['status' => 'verified'])
            ->assertOk()
            ->assertJsonPath('status', 'verified');
    }

    public function test_vehicle_documents_can_be_uploaded_listed_and_opened_by_staff(): void
    {
        [$owner, $school] = $this->schoolWithOwner('fleet-school');
        [$ownerB] = $this->schoolWithOwner('fleet-other');
        $vehicle = Vehicle::withoutGlobalScope('school')->create([
            'school_id' => $school->id, 'registration_number' => 'MH12AB1234', 'type' => 'car', 'status' => 'active',
        ]);

        Sanctum::actingAs($owner);
        $id = $this->post("/api/vehicles/{$vehicle->id}/documents", [
            'type' => 'insurance', 'expiryDate' => '2027-03-31', 'file' => $this->pdf('policy.pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('hasFile', true)
            ->assertJsonPath('expiryDate', '2027-03-31')
            ->json('id');

        $this->getJson("/api/vehicles/{$vehicle->id}/documents")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/documents/vehicle/{$id}/link")->assertOk();

        Sanctum::actingAs($ownerB);
        $this->getJson("/api/vehicles/{$vehicle->id}/documents")->assertForbidden();
        $this->getJson("/api/documents/vehicle/{$id}/link")->assertForbidden();
    }
}
