<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DIQ-802: schools that existed before plan gating get the same feature trial
 * as new ones, so nobody loses access on deploy day. Schools already on a paid
 * plan keep it and get no trial.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plan = DB::table('plans')->where('code', config('plans.trial_plan', 'premium'))->value('id');
        if (! $plan) {
            return;
        }

        $now = now();
        $ends = $now->copy()->addDays((int) config('plans.trial_days', 30));

        $schools = DB::table('schools')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('subscriptions')
                ->whereColumn('subscriptions.school_id', 'schools.id')
                ->where('subscriptions.status', 'active')
                ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now)))
            ->pluck('id');

        foreach ($schools->chunk(500) as $chunk) {
            DB::table('subscriptions')->insert($chunk->map(fn ($id) => [
                'school_id' => $id,
                'plan_id' => $plan,
                'status' => 'trial',
                'starts_at' => $now,
                'expires_at' => $ends,
                'notes' => 'Feature trial (existing school)',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        DB::table('subscriptions')->where('status', 'trial')->delete();
    }
};
