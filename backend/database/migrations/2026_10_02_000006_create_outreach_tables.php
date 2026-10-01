<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DIQ-1105: outreach email campaigns, who is in them, and every email sent. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('audience', 16)->default('school'); // school | trainer
            $table->string('status', 16)->default('draft');    // draft | active | paused
            // Up to 3 steps: [{subject, body, delayDays}], delay counted from the previous email.
            $table->jsonb('steps');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outreach_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('outreach_campaigns')->cascadeOnDelete();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('next_step')->default(0);
            $table->timestamp('next_send_at')->nullable();
            $table->string('status', 16)->default('active'); // active | stopped | completed
            $table->string('stop_reason', 32)->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'prospect_id']);
            $table->index(['status', 'next_send_at']);
        });

        Schema::create('outreach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('outreach_campaigns')->cascadeOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('step');
            $table->string('to_masked', 64);
            $table->char('to_hash', 64);
            $table->string('status', 16); // sending | sent | failed | bounced
            $table->string('error', 500)->nullable();
            $table->char('unsubscribe_hash', 64)->unique();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            // The same email is never sent twice, even if the job runs twice.
            $table->unique(['campaign_id', 'step', 'prospect_id'], 'outreach_messages_once');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
        Schema::dropIfExists('outreach_enrollments');
        Schema::dropIfExists('outreach_campaigns');
    }
};
