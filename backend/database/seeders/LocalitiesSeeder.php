<?php

namespace Database\Seeders;

use App\Models\Locality;
use Illuminate\Database\Seeder;

class LocalitiesSeeder extends Seeder
{
    public function run(): void
    {
        $localities = [
            ['name' => 'Baner', 'slug' => 'baner', 'description' => 'Premium residential and commercial locality in Pune.'],
            ['name' => 'Hinjewadi', 'slug' => 'hinjewadi', 'description' => 'IT hub area with high demand for learner driving services.'],
            ['name' => 'Wakad', 'slug' => 'wakad', 'description' => 'Fast-growing neighborhood with family and student population.'],
            ['name' => 'Hadapsar', 'slug' => 'hadapsar', 'description' => 'Large mixed-use area serving east Pune commuters.'],
            ['name' => 'Kothrud', 'slug' => 'kothrud', 'description' => 'Established residential zone with strong weekday demand.'],
            ['name' => 'Pimpri', 'slug' => 'pimpri', 'description' => 'Busy urban region with industrial and residential segments.'],
        ];

        foreach ($localities as $locality) {
            Locality::query()->updateOrCreate(
                ['slug' => $locality['slug']],
                $locality,
            );
        }
    }
}
