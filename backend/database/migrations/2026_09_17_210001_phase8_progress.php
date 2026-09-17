<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_progress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('learner_id')->index();
            $table->unsignedBigInteger('school_id')->index();
            $table->string('skill_name');
            $table->unsignedTinyInteger('percentage')->default(0); // 0-100
            $table->unsignedBigInteger('updated_by_instructor_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('learner_id')->references('id')->on('learners')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('updated_by_instructor_id')->references('id')->on('instructors')->nullOnDelete();
            $table->foreign('session_id')->references('id')->on('schedules')->nullOnDelete();
            $table->unique(['learner_id', 'skill_name']);
        });

        Schema::create('driving_tests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('learner_id')->index();
            $table->unsignedBigInteger('school_id')->index();
            $table->date('test_date');
            $table->string('rto_name')->nullable();
            $table->string('rto_location')->nullable();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status')->default('scheduled')->index(); // scheduled|completed|passed|failed
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('learner_id')->references('id')->on('learners')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driving_tests');
        Schema::dropIfExists('training_progress');
    }
};
