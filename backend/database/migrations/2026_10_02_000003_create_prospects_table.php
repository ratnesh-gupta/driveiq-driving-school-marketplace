<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1103: schools and independent trainers the team is trying to bring on
 * board. A prospect may get an unclaimed listing that its owner later claims.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('school');
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            // Normalised copies, used to find duplicates.
            $table->string('phone_e164', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('email_normalized')->nullable()->index();
            $table->string('website')->nullable();
            $table->foreignId('locality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('stage', 20)->default('new');
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contacted_at')->nullable();
            // Source-specific details, e.g. Google Ads lead and campaign ids.
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['stage', 'updated_at']);
        });

        DB::statement("ALTER TABLE prospects ADD CONSTRAINT prospects_type_check CHECK (type IN ('school', 'trainer'))");
        DB::statement("ALTER TABLE prospects ADD CONSTRAINT prospects_stage_check CHECK (stage IN ('new', 'contacted', 'replied', 'claimed', 'lost', 'do_not_contact'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
