<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-604: server-side consent records (DPDP). Each grant is a new row; a
 * withdrawal stamps withdrawn_at, so the history is kept. Signed-in grants are
 * keyed by user_id; the anonymous cookie banner by a random device_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('device_id', 64)->nullable();
            $table->string('purpose', 40);   // terms | privacy | processing | role_portal | cookies_optional
            $table->string('role', 20)->nullable(); // for role_portal
            $table->string('version', 32);
            $table->timestamp('granted_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'withdrawn_at']);
            $table->index(['device_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
