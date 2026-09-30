<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DIQ-904 changed how profile completeness is scored, and rows written
 * without model events (seeders, imports) never got a score. Re-score all.
 */
return new class extends Migration
{
    public function up(): void
    {
        School::query()->chunkById(200, function ($schools) {
            foreach ($schools as $school) {
                DB::table('schools')->where('id', $school->id)
                    ->update(['profile_completeness' => $school->calculateProfileCompleteness()]);
            }
        });
    }

    public function down(): void
    {
        // Scores are derived data; nothing to restore.
    }
};
