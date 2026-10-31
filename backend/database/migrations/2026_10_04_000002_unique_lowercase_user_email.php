<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BSEC-06: emails are stored trimmed and lowercase and are unique
 * case-insensitively.
 *
 * Existing case-variant duplicates ("A@x.com" and "a@x.com") are NOT merged
 * automatically: they are two real accounts with their own tokens, schools and
 * data, and silently picking a winner could hand one person another's account.
 * The migration fails loudly instead, listing the colliding addresses, so an
 * admin can deactivate or rename one account of each pair and re-run it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dupes = DB::table('users')
            ->selectRaw('LOWER(TRIM(email)) as e, COUNT(*) as n')
            ->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->limit(25)
            ->pluck('n', 'e');

        if ($dupes->isNotEmpty()) {
            $list = $dupes->map(fn ($n, $e) => "{$e} ({$n} accounts)")->implode(', ');

            throw new RuntimeException(
                'Cannot enforce case-insensitive unique users.email: these addresses exist more than once '
                ."ignoring case/whitespace: {$list}. Resolve each pair manually (rename or delete one account), then run migrate again."
            );
        }

        DB::statement('UPDATE users SET email = LOWER(TRIM(email)) WHERE email <> LOWER(TRIM(email))');
        DB::statement('UPDATE password_reset_tokens SET email = LOWER(TRIM(email)) WHERE email <> LOWER(TRIM(email))');
        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
