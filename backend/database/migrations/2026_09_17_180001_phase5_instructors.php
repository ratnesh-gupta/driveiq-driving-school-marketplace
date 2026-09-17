<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('gender')->nullable(); // male|female|other
            $table->date('dob')->nullable();
            $table->string('address')->nullable();
            $table->string('employee_id')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('status')->default('active')->index(); // active|inactive|terminated
            $table->string('employment_type')->default('full_time'); // full_time|part_time|contract
            $table->string('license_number')->nullable();
            $table->string('license_category')->nullable();
            $table->date('license_expiry')->nullable();
            $table->unsignedSmallInteger('years_experience')->default(0);
            $table->json('skills')->nullable(); // vehicle types etc.
            $table->json('languages')->nullable();
            $table->boolean('women_instructor')->default(false);
            $table->boolean('public_visible')->default(false);
            $table->string('photo_url')->nullable();
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('total_learners_trained')->default(0);
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('instructor_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instructor_id')->index();
            $table->unsignedBigInteger('school_id')->index();
            $table->string('type'); // driving_license|aadhaar|pan|photo|certificate
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('status')->default('pending')->index(); // pending|uploaded|verified|rejected
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('instructor_id')->references('id')->on('instructors')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_documents');
        Schema::dropIfExists('instructors');
    }
};
