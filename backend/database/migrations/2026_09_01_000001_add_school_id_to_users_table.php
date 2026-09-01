<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('school_id')->nullable()->after('role');
            $table->index('school_id');
        });

        // Backfill: link school owners to their school via schools.user_id
        if (Schema::hasTable('schools') && Schema::hasColumn('schools', 'user_id')) {
            DB::table('schools')
                ->whereNotNull('user_id')
                ->orderBy('id')
                ->get(['id', 'user_id'])
                ->each(function ($school): void {
                    DB::table('users')
                        ->where('id', $school->user_id)
                        ->whereNull('school_id')
                        ->update(['school_id' => $school->id]);
                });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};
