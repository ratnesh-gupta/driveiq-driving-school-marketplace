<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_subject_requests', function (Blueprint $table) {
            $table->string('nominee_name')->nullable()->after('details');
            $table->string('nominee_email')->nullable()->after('nominee_name');
            $table->string('nominee_relation')->nullable()->after('nominee_email');
        });
    }

    public function down(): void
    {
        Schema::table('data_subject_requests', function (Blueprint $table) {
            $table->dropColumn(['nominee_name', 'nominee_email', 'nominee_relation']);
        });
    }
};
