<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-913: each scheduled reminder is sent once. Expiry alerts remember the
 * date they were sent for, so a renewed document (new expiry) alerts again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('reminded_2h_at')->nullable();
        });
        Schema::table('learners', function (Blueprint $table) {
            $table->date('license_expiry_notified_for')->nullable();
            $table->timestamp('documents_nudged_at')->nullable();
        });
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->date('expiry_notified_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', fn (Blueprint $t) => $t->dropColumn(['reminded_24h_at', 'reminded_2h_at']));
        Schema::table('learners', fn (Blueprint $t) => $t->dropColumn(['license_expiry_notified_for', 'documents_nudged_at']));
        Schema::table('vehicle_documents', fn (Blueprint $t) => $t->dropColumn('expiry_notified_for'));
    }
};
