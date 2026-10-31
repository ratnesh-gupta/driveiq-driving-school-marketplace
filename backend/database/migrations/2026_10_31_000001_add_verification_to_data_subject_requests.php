<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BSEC-13: a data-subject request is only actionable once the requester has
 * proved control of the e-mail address (link sent by e-mail), and every
 * request carries a due date (30 days).
 *
 * Data risk on a populated DB: rows that already exist were never verified, so
 * verified_at stays NULL and admins cannot start or complete them until the
 * requester files again (they can still be rejected). due_at is backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_subject_requests', function (Blueprint $table) {
            $table->string('verification_token_hash', 64)->nullable()->unique();
            $table->timestamp('verification_expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->index('status');
        });

        DB::statement("UPDATE data_subject_requests SET due_at = created_at + interval '30 days' WHERE due_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('data_subject_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['verification_token_hash', 'verification_expires_at', 'verified_at', 'due_at', 'erased_at']);
        });
    }
};
