<?php

namespace App\Console\Commands;

use App\Support\DocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * BSEC-15 migration path: encrypts documents uploaded before DOCUMENTS_ENCRYPT.
 * Per file: write the encrypted copy, repoint the row, then delete the plain
 * file, so a crash never loses a document. Safe to re-run (encrypted files are
 * skipped). Dry run unless --execute.
 */
class EncryptDocumentsCommand extends Command
{
    protected $signature = 'driveiq:encrypt-documents {--execute : Convert the files instead of only counting them}';

    protected $description = 'Encrypt existing private document files at rest (dry run by default)';

    public function handle(DocumentStorage $storage): int
    {
        $execute = (bool) $this->option('execute');
        $rows = [];

        foreach (['learner_documents', 'instructor_documents', 'vehicle_documents'] as $table) {
            $todo = $converted = $skipped = 0;
            DB::table($table)->whereNotNull('file_path')->orderBy('id')->chunkById(200, function ($docs) use ($table, $storage, $execute, &$todo, &$converted, &$skipped) {
                foreach ($docs as $doc) {
                    if ($storage->isEncrypted($doc->file_path)) {
                        continue;
                    }
                    if (! $storage->belongsToSchool($doc->file_path, (int) $doc->school_id) || ! $storage->disk()->exists($doc->file_path)) {
                        $skipped++; // legacy client path or file missing: left alone

                        continue;
                    }
                    $todo++;
                    if (! $execute) {
                        continue;
                    }
                    $new = $storage->encryptExisting($doc->file_path, (int) $doc->school_id);
                    if ($new === null) {
                        $skipped++;

                        continue;
                    }
                    DB::table($table)->where('id', $doc->id)->update(['file_path' => $new, 'updated_at' => now()]);
                    $storage->delete($doc->file_path, (int) $doc->school_id);
                    $converted++;
                }
            });
            $rows[] = [$table, $todo, $execute ? $converted : 0, $skipped];
        }

        $this->info($execute ? 'Encrypting existing documents.' : 'Dry run (pass --execute to convert).');
        $this->table(['Table', 'Plain files', 'Converted', 'Skipped'], $rows);

        return self::SUCCESS;
    }
}
