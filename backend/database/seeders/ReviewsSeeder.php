<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\School;
use Illuminate\Database\Seeder;

class ReviewsSeeder extends Seeder
{
    public function run(): void
    {
        $schoolIds = School::query()->pluck('id', 'slug');

        $reviews = [
            ['school_slug' => 'skyline-driving-academy', 'author_name' => 'Aarav', 'rating' => 5, 'content' => 'Excellent instructors and clean practice cars.', 'approved' => true],
            ['school_slug' => 'skyline-driving-academy', 'author_name' => 'Sneha', 'rating' => 4, 'content' => 'Great pickup support and flexible timing.', 'approved' => true],
            ['school_slug' => 'puneauto-academy', 'author_name' => 'Vikram', 'rating' => 5, 'content' => 'Very structured lessons. Good for beginners.', 'approved' => true],
            ['school_slug' => 'puneauto-academy', 'author_name' => 'Anjali', 'rating' => 4, 'content' => 'Helped me gain confidence quickly.', 'approved' => true],
            ['school_slug' => 'roadstar', 'author_name' => 'Nitin', 'rating' => 4, 'content' => 'Budget-friendly and decent training.', 'approved' => true],
            ['school_slug' => 'eastern-drive', 'author_name' => 'Ritika', 'rating' => 5, 'content' => 'Professional and patient instructors.', 'approved' => true],
            ['school_slug' => 'kothrud-driving-hub', 'author_name' => 'Meera', 'rating' => 5, 'content' => 'Women instructor option was very helpful.', 'approved' => true],
            ['school_slug' => 'kothrud-driving-hub', 'author_name' => 'Karan', 'rating' => 4, 'content' => 'Good experience overall and punctual staff.', 'approved' => true],
            ['school_slug' => 'pimpri-autodrive', 'author_name' => 'Soham', 'rating' => 4, 'content' => 'Affordable pricing and practical guidance.', 'approved' => true],
        ];

        foreach ($reviews as $review) {
            $schoolId = $schoolIds[$review['school_slug']] ?? null;
            if (!$schoolId) {
                continue;
            }

            unset($review['school_slug']);
            $review['school_id'] = $schoolId;

            Review::query()->updateOrCreate(
                [
                    'school_id' => $review['school_id'],
                    'author_name' => $review['author_name'],
                    'content' => $review['content'],
                ],
                $review,
            );
        }
    }
}
