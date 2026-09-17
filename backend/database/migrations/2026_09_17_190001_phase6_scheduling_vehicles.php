<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->string('registration_number');
            $table->string('type')->default('car'); // car|bike|scooter|heavy
            $table->string('transmission')->nullable(); // manual|automatic
            $table->string('fuel_type')->nullable(); // petrol|diesel|electric|cng
            $table->string('status')->default('active')->index(); // active|maintenance|retired
            $table->string('make_model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->unique(['school_id', 'registration_number']);
        });

        Schema::create('vehicle_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->unsignedBigInteger('school_id')->index();
            $table->string('type'); // registration|insurance|pollution|permit
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('pending'); // pending|valid|expired
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });

        Schema::create('schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('learner_id')->nullable()->index(); // Phase 7 will add FK
            $table->string('learner_name')->nullable(); // interim until learners module
            $table->unsignedBigInteger('instructor_id')->index();
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->date('session_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('pickup_location')->nullable();
            $table->string('status')->default('scheduled')->index(); // scheduled|completed|cancelled|rescheduled
            $table->text('notes')->nullable();
            $table->text('session_summary')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('instructor_id')->references('id')->on('instructors')->cascadeOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('attendance', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('schedule_id')->unique();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('learner_id')->nullable();
            $table->unsignedBigInteger('instructor_id');
            $table->string('status'); // present|absent|rescheduled|cancelled
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('instructor_id')->references('id')->on('instructors')->cascadeOnDelete();
            $table->foreign('marked_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instructor_id')->index();
            $table->unsignedBigInteger('school_id')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->string('status')->default('pending')->index(); // pending|approved|rejected
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('instructor_id')->references('id')->on('instructors')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('vehicle_documents');
        Schema::dropIfExists('vehicles');
    }
};
