<?php

namespace Database\Seeders;

use App\Models\School;
use App\Services\SubscriptionService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            LocalitiesSeeder::class,
            SchoolsSeeder::class,
            ReviewsSeeder::class,
            PackagesSeeder::class,
            InquiriesSeeder::class,
            OpsDemoSeeder::class,
        ]);

        // Model events are off while seeding, so School's "start a trial on
        // create" hook did not run: give seeded schools their trial here (DIQ-802).
        $subscriptions = app(SubscriptionService::class);
        School::query()->pluck('id')->each(fn (int $id) => $subscriptions->startTrial($id));

        // Same reason: the saving hook that scores profile completeness did not run.
        School::query()->each(fn (School $s) => $s->forceFill(['profile_completeness' => $s->calculateProfileCompleteness()])->saveQuietly());
    }
}
