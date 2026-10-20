<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1001: every WhatsApp / SMS attempt. The number is stored masked plus a
 * hash, so the log proves what was sent without keeping a reusable number;
 * the unique key stops a retried job from messaging someone twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->index();
            $table->string('channel', 16);
            $table->string('template', 64);
            $table->string('to_masked', 20);
            $table->string('to_hash', 64);
            $table->string('related_type', 40)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('status', 16); // sending | sent | failed
            $table->string('provider_message_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->unique(['template', 'related_type', 'related_id', 'to_hash'], 'outbound_messages_once');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
