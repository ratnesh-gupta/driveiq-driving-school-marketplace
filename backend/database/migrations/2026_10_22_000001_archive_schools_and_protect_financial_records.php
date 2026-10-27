<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1304 (QA BUG-04): removing a school archives it instead of deleting it,
 * and the database refuses to delete a school that still has money records.
 * Payments, school invoices, subscriptions and platform invoices must be kept
 * for 8 years (tax); before this, a hard delete cascaded them away (or, for
 * platform invoices, cut the link to the school).
 */
return new class extends Migration
{
    /** table => delete rule it had before */
    private const FINANCIAL = [
        'payments' => 'cascade',
        'invoices' => 'cascade',
        'subscriptions' => 'cascade',
        'platform_invoices' => 'set null',
    ];

    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
        });

        foreach (array_keys(self::FINANCIAL) as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->dropForeign(['school_id']);
                $t->foreign('school_id')->references('id')->on('schools')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::FINANCIAL as $table => $rule) {
            Schema::table($table, function (Blueprint $t) use ($rule): void {
                $t->dropForeign(['school_id']);
                $fk = $t->foreign('school_id')->references('id')->on('schools');
                $rule === 'cascade' ? $fk->cascadeOnDelete() : $fk->nullOnDelete();
            });
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
