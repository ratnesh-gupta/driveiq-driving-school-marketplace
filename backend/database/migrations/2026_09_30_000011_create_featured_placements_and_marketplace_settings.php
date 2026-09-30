<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-805: sponsored placement inventory.
 *  - featured_placements: admin-run campaign windows that put a school in
 *    search_top, on the homepage, or at the top of one locality's results.
 *  - marketplace_settings: admin-tunable slot counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('placement', 16); // search_top | homepage | locality
            $table->foreignId('locality_id')->nullable()->constrained('localities')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['placement', 'starts_at', 'ends_at']);
        });

        Schema::create('marketplace_settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->json('value');
            $table->timestamps();
        });

        DB::table('marketplace_settings')->insert([
            ['key' => 'sponsored_slots_per_page', 'value' => json_encode(2), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'homepage_slots', 'value' => json_encode(6), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_settings');
        Schema::dropIfExists('featured_placements');
    }
};
