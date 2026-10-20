<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1101: a listing is either a school or an independent trainer, and only
 * published listings are public. Existing rows were all public, so they stay
 * published (also the column default, which admin-created schools use).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('listing_type', 16)->default('school')->after('slug');
            $table->string('listing_status', 16)->default('published')->after('listing_type');
            $table->timestamp('claimed_at')->nullable();
            $table->string('source', 16)->default('organic');
            $table->string('google_place_id')->nullable()->unique();
            $table->index(['listing_status', 'listing_type']);
        });

        DB::statement("ALTER TABLE schools ADD CONSTRAINT schools_listing_type_check CHECK (listing_type IN ('school', 'trainer'))");
        DB::statement("ALTER TABLE schools ADD CONSTRAINT schools_listing_status_check CHECK (listing_status IN ('unclaimed', 'draft', 'published', 'suspended'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE schools DROP CONSTRAINT IF EXISTS schools_listing_type_check');
        DB::statement('ALTER TABLE schools DROP CONSTRAINT IF EXISTS schools_listing_status_check');
        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex(['listing_status', 'listing_type']);
            $table->dropColumn(['listing_type', 'listing_status', 'claimed_at', 'source', 'google_place_id']);
        });
    }
};
