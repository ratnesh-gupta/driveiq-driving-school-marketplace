<?php

namespace Database\Seeders;

use App\Models\DrivePackage;
use App\Models\School;
use Illuminate\Database\Seeder;

class PackagesSeeder extends Seeder
{
    public function run(): void
    {
        $schoolIds = School::query()->pluck('id', 'slug');

        $packages = [
            ['school_slug' => 'skyline-driving-academy', 'name' => 'Starter Manual', 'description' => '12 sessions manual beginner package', 'price' => 4500, 'sessions' => 12, 'vehicle_type' => 'car', 'transmission' => 'manual', 'has_pickup' => true, 'active' => true],
            ['school_slug' => 'skyline-driving-academy', 'name' => 'Comfort Automatic', 'description' => '10 sessions automatic package', 'price' => 6200, 'sessions' => 10, 'vehicle_type' => 'car', 'transmission' => 'automatic', 'has_pickup' => true, 'active' => true],
            ['school_slug' => 'puneauto-academy', 'name' => 'City Confidence', 'description' => '15 sessions focused on city traffic', 'price' => 5200, 'sessions' => 15, 'vehicle_type' => 'car', 'transmission' => 'manual', 'has_pickup' => true, 'active' => true],
            ['school_slug' => 'roadstar', 'name' => 'Economy Pack', 'description' => '10 session budget package', 'price' => 3500, 'sessions' => 10, 'vehicle_type' => 'car', 'transmission' => 'manual', 'has_pickup' => false, 'active' => true],
            ['school_slug' => 'eastern-drive', 'name' => 'Commuter Pro', 'description' => '14 sessions for office commuters', 'price' => 5000, 'sessions' => 14, 'vehicle_type' => 'car', 'transmission' => 'manual', 'has_pickup' => false, 'active' => true],
            ['school_slug' => 'kothrud-driving-hub', 'name' => 'Premium Automatic', 'description' => '12 sessions with flexible slots', 'price' => 6800, 'sessions' => 12, 'vehicle_type' => 'car', 'transmission' => 'automatic', 'has_pickup' => true, 'active' => true],
            ['school_slug' => 'pimpri-autodrive', 'name' => 'Quick Start', 'description' => '8 sessions quick learner package', 'price' => 3200, 'sessions' => 8, 'vehicle_type' => 'car', 'transmission' => 'manual', 'has_pickup' => false, 'active' => true],
            ['school_slug' => 'puneauto-academy', 'name' => 'Weekend Batch', 'description' => 'Weekend-only 10 sessions', 'price' => 4800, 'sessions' => 10, 'vehicle_type' => 'car', 'transmission' => 'manual', 'has_pickup' => true, 'active' => true],
        ];

        foreach ($packages as $package) {
            $schoolId = $schoolIds[$package['school_slug']] ?? null;
            if (!$schoolId) {
                continue;
            }

            unset($package['school_slug']);
            $package['school_id'] = $schoolId;

            DrivePackage::query()->updateOrCreate(
                [
                    'school_id' => $package['school_id'],
                    'name' => $package['name'],
                ],
                $package,
            );
        }
    }
}
