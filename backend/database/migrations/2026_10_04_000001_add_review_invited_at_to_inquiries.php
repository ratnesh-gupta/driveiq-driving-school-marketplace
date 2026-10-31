<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BSEC-08: the review link is only sent after the school responded; one invitation per enquirer per school. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->timestamp('review_invited_at')->nullable();
            $table->string('phone_key', 24)->nullable();
            $table->index(['school_id', 'phone_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'phone_key', 'created_at']);
            $table->dropColumn(['review_invited_at', 'phone_key']);
        });
    }
};
