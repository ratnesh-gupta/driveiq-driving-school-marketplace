<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1002: WhatsApp needs a number and the person's own opt-in. Accounts
 * keep theirs on users; people without an account opt in on the enquiry
 * (with IP as evidence), and a converted enquiry's opt-in follows the learner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable();
            $table->timestamp('whatsapp_opt_in_at')->nullable();
        });
        Schema::table('inquiries', function (Blueprint $table) {
            $table->timestamp('whatsapp_opt_in_at')->nullable();
            $table->string('whatsapp_opt_in_ip', 45)->nullable();
        });
        Schema::table('learners', function (Blueprint $table) {
            $table->timestamp('whatsapp_opt_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'whatsapp_opt_in_at']));
        Schema::table('inquiries', fn (Blueprint $t) => $t->dropColumn(['whatsapp_opt_in_at', 'whatsapp_opt_in_ip']));
        Schema::table('learners', fn (Blueprint $t) => $t->dropColumn('whatsapp_opt_in_at'));
    }
};
