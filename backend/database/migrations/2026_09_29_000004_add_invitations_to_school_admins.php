<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manager invitations (DIQ-403): a pending school_admins row holds the invited
 * email and a hashed, expiring token. No user account is created or changed
 * until the invitee accepts, so user_id is null for invitees without an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_admins', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('invite_email')->nullable()->after('user_id');
            $table->string('invite_token_hash', 64)->nullable()->unique()->after('invite_email');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token_hash');
            $table->index(['school_id', 'invite_email']);
        });
    }

    public function down(): void
    {
        Schema::table('school_admins', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'invite_email']);
            $table->dropUnique(['invite_token_hash']);
            $table->dropColumn(['invite_email', 'invite_token_hash', 'invite_expires_at']);
        });
    }
};
