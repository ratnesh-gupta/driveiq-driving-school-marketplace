<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-registration now offers only "school" (owner) and "learner".
 * - The generic "user" role is retired: existing rows become learners.
 * - Every school's user_id gets an active owner row in school_admins,
 *   so owner-vs-manager checks have a single source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('learner')->change();
        });

        DB::table('users')->where('role', 'user')->update(['role' => 'learner']);

        $now = now();
        $owners = DB::table('schools')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('school_admins')
                    ->whereColumn('school_admins.school_id', 'schools.id')
                    ->whereColumn('school_admins.user_id', 'schools.user_id');
            })
            ->get(['id', 'user_id']);

        foreach ($owners as $school) {
            DB::table('school_admins')->insert([
                'school_id' => $school->id,
                'user_id' => $school->user_id,
                'role' => 'owner',
                'status' => 'active',
                'invited_at' => $now,
                'accepted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Role data and owner rows are not reverted (cannot tell backfilled from real).
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('user')->change();
        });
    }
};
