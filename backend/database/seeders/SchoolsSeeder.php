<?php

namespace Database\Seeders;

use App\Models\Locality;
use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolsSeeder extends Seeder
{
    public function run(): void
    {
        $localityIds = Locality::query()->pluck('id', 'slug');

        $schools = [
            [
                'name' => 'Skyline Driving Academy',
                'slug' => 'skyline-driving-academy',
                'locality_slug' => 'baner',
                'address' => 'Baner Road, Pune',
                'latitude' => 18.5590,
                'longitude' => 73.7868,
                'service_radius_km' => 8,
                'phone' => '+91 9876500001',
                'whatsapp' => '+91 9876500001',
                'email' => 'info@skylinedrive.in',
                'description' => 'Professional instructors with flexible schedules and pickup support.',
                'image_url' => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d',
                'rating' => 4.8,
                'review_count' => 2,
                'verified' => true,
                'has_pickup' => true,
                'women_instructor' => true,
                'weekend_classes' => true,
                'vehicle_types' => ['car'],
                'transmission' => ['manual', 'automatic'],
                'price_from' => 4500,
                'price_to' => 9000,
                'timings' => '7 AM - 8 PM',
                'service_areas' => ['Baner', 'Balewadi', 'Aundh'],
            ],
            [
                'name' => 'PuneAuto Academy',
                'slug' => 'puneauto-academy',
                'locality_slug' => 'hinjewadi',
                'address' => 'Phase 1, Hinjewadi, Pune',
                'latitude' => 18.5912,
                'longitude' => 73.7389,
                'service_radius_km' => 10,
                'phone' => '+91 9876500002',
                'whatsapp' => '+91 9876500002',
                'email' => 'hello@puneautoacademy.in',
                'description' => 'Structured curriculum focused on city and highway confidence.',
                'image_url' => 'https://images.unsplash.com/photo-1493238792000-8113da705763',
                'rating' => 4.6,
                'review_count' => 2,
                'verified' => true,
                'has_pickup' => true,
                'women_instructor' => false,
                'weekend_classes' => true,
                'vehicle_types' => ['car'],
                'transmission' => ['manual'],
                'price_from' => 4200,
                'price_to' => 8200,
                'timings' => '6 AM - 9 PM',
                'service_areas' => ['Hinjewadi', 'Wakad', 'Tathawade'],
            ],
            [
                'name' => 'RoadStar',
                'slug' => 'roadstar',
                'locality_slug' => 'wakad',
                'address' => 'Wakad Main Road, Pune',
                'latitude' => 18.5975,
                'longitude' => 73.7637,
                'service_radius_km' => 7,
                'phone' => '+91 9876500003',
                'whatsapp' => '+91 9876500003',
                'email' => 'support@roadstar.in',
                'description' => 'Affordable plans for beginners and quick refresher modules.',
                'image_url' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70',
                'rating' => 4.3,
                'review_count' => 1,
                'verified' => false,
                'has_pickup' => true,
                'women_instructor' => false,
                'weekend_classes' => true,
                'vehicle_types' => ['car'],
                'transmission' => ['manual', 'automatic'],
                'price_from' => 3500,
                'price_to' => 7600,
                'timings' => '8 AM - 7 PM',
                'service_areas' => ['Wakad', 'Pimple Saudagar'],
            ],
            [
                'name' => 'Eastern Drive',
                'slug' => 'eastern-drive',
                'locality_slug' => 'hadapsar',
                'address' => 'Magarpatta Road, Hadapsar, Pune',
                'latitude' => 18.5089,
                'longitude' => 73.9260,
                'service_radius_km' => 9,
                'phone' => '+91 9876500004',
                'whatsapp' => '+91 9876500004',
                'email' => 'contact@easterndrive.in',
                'description' => 'Experienced trainers for office commuters and daily drivers.',
                'image_url' => 'https://images.unsplash.com/photo-1542282088-fe8426682b8f',
                'rating' => 4.5,
                'review_count' => 1,
                'verified' => true,
                'has_pickup' => false,
                'women_instructor' => true,
                'weekend_classes' => false,
                'vehicle_types' => ['car'],
                'transmission' => ['manual'],
                'price_from' => 4000,
                'price_to' => 7800,
                'timings' => '7 AM - 6 PM',
                'service_areas' => ['Hadapsar', 'Magarpatta', 'Mundhwa'],
            ],
            [
                'name' => 'Kothrud Driving Hub',
                'slug' => 'kothrud-driving-hub',
                'locality_slug' => 'kothrud',
                'address' => 'Paud Road, Kothrud, Pune',
                'latitude' => 18.5074,
                'longitude' => 73.8077,
                'service_radius_km' => 6,
                'phone' => '+91 9876500005',
                'whatsapp' => '+91 9876500005',
                'email' => 'team@kothrudhub.in',
                'description' => 'Focused one-on-one driving sessions with women instructors available.',
                'image_url' => 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf',
                'rating' => 4.7,
                'review_count' => 2,
                'verified' => true,
                'has_pickup' => true,
                'women_instructor' => true,
                'weekend_classes' => true,
                'vehicle_types' => ['car'],
                'transmission' => ['automatic'],
                'price_from' => 4800,
                'price_to' => 9200,
                'timings' => '6 AM - 8 PM',
                'service_areas' => ['Kothrud', 'Karve Nagar', 'Erandwane'],
            ],
            [
                'name' => 'Pimpri AutoDrive',
                'slug' => 'pimpri-autodrive',
                'locality_slug' => 'pimpri',
                'address' => 'Pimpri Chowk, PCMC, Pune',
                'latitude' => 18.6298,
                'longitude' => 73.7997,
                'service_radius_km' => 8,
                'phone' => '+91 9876500006',
                'whatsapp' => '+91 9876500006',
                'email' => 'care@pimpriautodrive.in',
                'description' => 'High-volume training center with budget-friendly pricing.',
                'image_url' => 'https://images.unsplash.com/photo-1485463611174-f302f6a5c1c9',
                'rating' => 4.1,
                'review_count' => 1,
                'verified' => false,
                'has_pickup' => false,
                'women_instructor' => false,
                'weekend_classes' => true,
                'vehicle_types' => ['car'],
                'transmission' => ['manual'],
                'price_from' => 3200,
                'price_to' => 7000,
                'timings' => '8 AM - 8 PM',
                'service_areas' => ['Pimpri', 'Chinchwad', 'Akurdi'],
            ],
        ];

        foreach ($schools as $school) {
            $localityId = $localityIds[$school['locality_slug']] ?? null;
            if (!$localityId) {
                continue;
            }

            unset($school['locality_slug']);
            $school['locality_id'] = $localityId;

            School::query()->updateOrCreate(
                ['slug' => $school['slug']],
                $school,
            );
        }
    }
}
