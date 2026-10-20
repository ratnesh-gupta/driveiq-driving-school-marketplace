<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1104: claim links for unclaimed listings, and the one-time codes that
 * prove the claimant controls the listing's email or phone. Only hashes of
 * tokens and codes are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            // Which outreach email carried the link, for funnel numbers (DIQ-1105).
            $table->unsignedBigInteger('outreach_message_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('listing_claim_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_claim_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 8); // email | sms
            $table->string('sent_to_masked', 64);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_claim_codes');
        Schema::dropIfExists('listing_claims');
    }
};
