<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_admins', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role')->default('manager'); // owner|manager
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('status')->default('pending')->index(); // pending|active|revoked
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['school_id', 'user_id']);
        });

        Schema::create('school_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('school_admins');
    }
};
