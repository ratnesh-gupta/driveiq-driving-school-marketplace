<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
        });
    }
};
