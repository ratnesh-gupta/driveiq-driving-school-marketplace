<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-706: internal notes on a lead, optionally with a follow-up date.
 * inquiries.next_follow_up_at holds the latest follow-up date for filtering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamps();

            $table->index(['inquiry_id', 'created_at']);
        });

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->timestamp('next_follow_up_at')->nullable();
            $table->index(['school_id', 'next_follow_up_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'next_follow_up_at']);
            $table->dropColumn('next_follow_up_at');
        });
        Schema::dropIfExists('lead_notes');
    }
};
