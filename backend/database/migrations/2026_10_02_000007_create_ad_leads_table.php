<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1106: Google Ads lead form deliveries, one row per lead id so a retry
 * is never processed twice. Contact details live on the prospect only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_leads', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16)->default('google_ads');
            $table->string('lead_id')->unique();
            $table->string('form_id')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('adgroup_id')->nullable();
            $table->boolean('is_test')->default(false);
            $table->foreignId('prospect_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 24); // created | updated | test | suppressed
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_leads');
    }
};
