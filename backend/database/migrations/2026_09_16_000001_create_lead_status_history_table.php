<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_status_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inquiry_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedBigInteger('changed_by_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('inquiry_id')
                ->references('id')
                ->on('inquiries')
                ->cascadeOnDelete();
            $table->foreign('changed_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['inquiry_id', 'created_at']);
        });

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->index(['school_id', 'created_at'], 'idx_inquiry_school_created');
            $table->index(['school_id', 'status'], 'idx_inquiry_school_status');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->index(['school_id', 'created_at'], 'idx_review_school_created');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->index(['school_id'], 'idx_packages_school_id');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropIndex('idx_packages_school_id');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex('idx_review_school_created');
        });

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropIndex('idx_inquiry_school_created');
            $table->dropIndex('idx_inquiry_school_status');
        });

        Schema::dropIfExists('lead_status_history');
    }
};
