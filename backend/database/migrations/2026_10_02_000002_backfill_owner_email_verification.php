<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DIQ-1102: email confirmation now gates publishing. School accounts that
 * existed before it were already live, so they count as confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'school')->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Not reversible: we cannot tell backfilled rows from real confirmations.
    }
};
