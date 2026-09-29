<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-703: when a school first responded to a lead. Set once, by
 * Inquiry::markResponded(); backfilled from the earliest status change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->timestamp('first_responded_at')->nullable();
            $table->unsignedInteger('response_seconds')->nullable();
            $table->index(['school_id', 'first_responded_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE inquiries i
                SET first_responded_at = h.first_at,
                    response_seconds = GREATEST(0, EXTRACT(EPOCH FROM (h.first_at - i.created_at)))::int
                FROM (
                    SELECT inquiry_id, MIN(created_at) AS first_at
                    FROM lead_status_history
                    WHERE to_status <> 'pending'
                    GROUP BY inquiry_id
                ) h
                WHERE h.inquiry_id = i.id AND i.first_responded_at IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'first_responded_at']);
            $table->dropColumn(['first_responded_at', 'response_seconds']);
        });
    }
};
