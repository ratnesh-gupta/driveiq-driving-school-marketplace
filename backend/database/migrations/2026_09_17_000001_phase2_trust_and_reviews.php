<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->boolean('phone_verified')->default(false)->after('verified');
            $table->boolean('business_verified')->default(false)->after('phone_verified');
            $table->boolean('location_verified')->default(false)->after('business_verified');
            $table->boolean('premium_verified')->default(false)->after('location_verified');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('school_id');
            $table->unsignedBigInteger('inquiry_id')->nullable()->after('user_id');
            $table->string('eligibility_source')->nullable()->after('inquiry_id');
            $table->unsignedInteger('report_count')->default(0)->after('approved');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('inquiry_id')->references('id')->on('inquiries')->nullOnDelete();
            $table->index(['school_id', 'user_id']);
        });

        Schema::create('review_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('reporter_id')->nullable();
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('pending')->index(); // pending|reviewed|dismissed
            $table->unsignedBigInteger('resolved_by_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['review_id', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['inquiry_id']);
            $table->dropColumn(['user_id', 'inquiry_id', 'eligibility_source', 'report_count']);
        });

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn([
                'phone_verified',
                'business_verified',
                'location_verified',
                'premium_verified',
            ]);
        });
    }
};
