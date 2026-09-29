<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-702: one lead lifecycle (Inquiry::STATUSES):
 *   pending (shown as "New") → contacted → follow_up → interested → converted | lost
 * "closed" becomes "lost" and "enrolled" (offered by the old dashboard, never
 * accepted by the API) becomes "converted". Status history rows keep the
 * values they were written with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->string('lost_reason')->nullable();
        });

        DB::table('inquiries')->where('status', 'closed')->update(['status' => 'lost']);
        DB::table('inquiries')->where('status', 'enrolled')->update(['status' => 'converted']);
    }

    public function down(): void
    {
        DB::table('inquiries')->where('status', 'lost')->update(['status' => 'closed']);
        DB::table('inquiries')->whereIn('status', ['follow_up', 'interested'])->update(['status' => 'contacted']);

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropColumn('lost_reason');
        });
    }
};
