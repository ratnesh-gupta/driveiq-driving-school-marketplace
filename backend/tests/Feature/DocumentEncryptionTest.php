<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\School;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** BSEC-15: private uploads are encrypted at rest and decrypted only on signed download. */
class DocumentEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private const PLAIN = "%PDF-1.4\n%secret-aadhaar-1234\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{User, School, Learner} */
    private function world(string $slug): array
    {
        $owner = User::factory()->create(['role' => 'school']);
        $school = School::create(['name' => $slug, 'slug' => $slug, 'user_id' => $owner->id]);
        $owner->update(['school_id' => $school->id]);
        $learner = Learner::withoutGlobalScope('school')->create(['school_id' => $school->id, 'name' => 'L', 'status' => 'active']);

        return [$owner->refresh(), $school, $learner];
    }

    private function upload(User $owner, Learner $learner): LearnerDocument
    {
        Sanctum::actingAs($owner);
        $id = $this->post("/api/learners/{$learner->id}/documents", [
            'type' => 'aadhaar', 'file' => UploadedFile::fake()->createWithContent('aadhaar.pdf', self::PLAIN),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        return LearnerDocument::withoutGlobalScope('school')->findOrFail($id);
    }

    public function test_stored_bytes_are_ciphertext_and_download_returns_the_original(): void
    {
        [$owner, , $learner] = $this->world('enc-a');
        $doc = $this->upload($owner, $learner);

        $this->assertStringEndsWith('.enc', $doc->file_path);
        $stored = Storage::disk('local')->get($doc->file_path);
        $this->assertStringNotContainsString('secret-aadhaar', $stored);
        $this->assertStringNotContainsString('%PDF', $stored);

        $url = $this->getJson("/api/documents/learner/{$doc->id}/link")->assertOk()->json('url');
        $this->app['auth']->forgetGuards();
        $res = $this->get($url)->assertOk();
        $this->assertSame(self::PLAIN, $res->streamedContent());
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('aadhaar.pdf', $res->headers->get('Content-Disposition'));
    }

    public function test_encryption_can_be_switched_off_and_old_plain_files_still_download(): void
    {
        [$owner, $school, $learner] = $this->world('enc-b');
        config(['filesystems.documents_encrypt' => false]);
        $doc = $this->upload($owner, $learner);
        $this->assertStringEndsNotWith('.enc', $doc->file_path);
        $this->assertSame(self::PLAIN, Storage::disk('local')->get($doc->file_path));

        // Turn encryption on: the previously stored plain file keeps working.
        config(['filesystems.documents_encrypt' => true]);
        $url = $this->getJson("/api/documents/learner/{$doc->id}/link")->assertOk()->json('url');
        $this->app['auth']->forgetGuards();
        $this->assertSame(self::PLAIN, $this->get($url)->assertOk()->streamedContent());
    }

    public function test_tampered_ciphertext_is_not_served(): void
    {
        [$owner, , $learner] = $this->world('enc-c');
        $doc = $this->upload($owner, $learner);
        Storage::disk('local')->put($doc->file_path, 'not-a-valid-payload');

        $url = $this->getJson("/api/documents/learner/{$doc->id}/link")->assertOk()->json('url');
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertNotFound();

        $this->expectException(DecryptException::class);
        app(DocumentStorage::class)->contents($doc->file_path);
    }

    public function test_other_school_cannot_get_a_link_to_an_encrypted_document(): void
    {
        [$owner, , $learner] = $this->world('enc-d');
        [$intruder] = $this->world('enc-e');
        $doc = $this->upload($owner, $learner);

        Sanctum::actingAs($intruder);
        $this->getJson("/api/documents/learner/{$doc->id}/link")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get("/api/documents/learner/{$doc->id}/file")->assertForbidden(); // unsigned
    }

    public function test_migration_command_encrypts_existing_files_dry_run_first_and_is_idempotent(): void
    {
        [$owner, $school, $learner] = $this->world('enc-f');
        [, $otherSchool, $otherLearner] = $this->world('enc-g');
        $plain = "schools/{$school->id}/learners/{$learner->id}/old.pdf";
        Storage::disk('local')->put($plain, self::PLAIN);
        $mk = fn (int $sid, int $lid, string $path) => DB::table('learner_documents')->insertGetId([
            'learner_id' => $lid, 'school_id' => $sid, 'type' => 'pan', 'file_path' => $path, 'file_name' => 'old.pdf',
            'status' => 'uploaded', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = $mk($school->id, $learner->id, $plain);
        // A row whose path points into another school's folder is never touched.
        $foreign = "schools/{$otherSchool->id}/learners/{$otherLearner->id}/x.pdf";
        Storage::disk('local')->put($foreign, 'foreign');
        $foreignId = $mk($school->id, $learner->id, $foreign);

        $this->artisan('driveiq:encrypt-documents')->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame($plain, DB::table('learner_documents')->find($id)->file_path);

        $this->artisan('driveiq:encrypt-documents', ['--execute' => true])->assertSuccessful();
        $row = DB::table('learner_documents')->find($id);
        $this->assertSame($plain.'.enc', $row->file_path);
        Storage::disk('local')->assertMissing($plain);
        $this->assertSame(self::PLAIN, app(DocumentStorage::class)->contents($row->file_path));
        $this->assertSame($foreign, DB::table('learner_documents')->find($foreignId)->file_path);
        Storage::disk('local')->assertExists($foreign);

        // Second run changes nothing.
        $this->artisan('driveiq:encrypt-documents', ['--execute' => true])->assertSuccessful();
        $this->assertSame($plain.'.enc', DB::table('learner_documents')->find($id)->file_path);
    }
}
