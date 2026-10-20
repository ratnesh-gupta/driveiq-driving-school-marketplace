<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DIQ-807: each reminder / ended notice is sent at most once per subscription. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('reminded_7d_at')->nullable();
            $table->timestamp('reminded_1d_at')->nullable();
            $table->timestamp('ended_notified_at')->nullable();
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn(['reminded_7d_at', 'reminded_1d_at', 'ended_notified_at']);
        });
    }
};
