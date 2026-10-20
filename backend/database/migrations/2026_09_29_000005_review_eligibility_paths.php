<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Review eligibility (DIQ-407) has two paths:
 *  - an enrolled learner reviews from their account  -> reviews.learner_id
 *  - an inquirer reviews via the one-time link emailed with their inquiry
 *    confirmation -> inquiries.review_token_hash (sha256, single use, 90 days)
 * At most one review per inquiry and one per learner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('learner_id')->nullable()->after('inquiry_id');
            $table->foreign('learner_id')->references('id')->on('learners')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX reviews_one_per_inquiry ON reviews (inquiry_id) WHERE inquiry_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX reviews_one_per_learner ON reviews (learner_id) WHERE learner_id IS NOT NULL');

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->string('review_token_hash', 64)->nullable()->unique();
            $table->timestamp('review_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropUnique(['review_token_hash']);
            $table->dropColumn(['review_token_hash', 'review_token_expires_at']);
        });

        DB::statement('DROP INDEX IF EXISTS reviews_one_per_learner');
        DB::statement('DROP INDEX IF EXISTS reviews_one_per_inquiry');

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['learner_id']);
            $table->dropColumn('learner_id');
        });
    }
};
