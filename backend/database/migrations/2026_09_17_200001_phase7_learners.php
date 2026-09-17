<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('converted_from_inquiry_id')->nullable()->index();
            $table->string('name');
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->unsignedBigInteger('assigned_instructor_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_vehicle_id')->nullable()->index();
            $table->string('learner_license_number')->nullable();
            $table->date('license_issue_date')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('permanent_license_status')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('converted_from_inquiry_id')->references('id')->on('inquiries')->nullOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->nullOnDelete();
            $table->foreign('assigned_instructor_id')->references('id')->on('instructors')->nullOnDelete();
            $table->foreign('assigned_vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
        });

        Schema::create('learner_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('learner_id')->index();
            $table->unsignedBigInteger('school_id')->index();
            $table->string('type');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('status')->default('pending')->index();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('learner_id')->references('id')->on('learners')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('learner_assignment_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('learner_id')->index();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('instructor_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('action')->default('assign');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('learner_id')->references('id')->on('learners')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });

        Schema::table('schedules', function (Blueprint $table): void {
            $table->foreign('learner_id')->references('id')->on('learners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropForeign(['learner_id']);
        });

        Schema::dropIfExists('learner_assignment_history');
        Schema::dropIfExists('learner_documents');
        Schema::dropIfExists('learners');
    }
};
