<?php

namespace Database\Seeders;

use App\Models\Inquiry;
use App\Models\School;
use Illuminate\Database\Seeder;

class InquiriesSeeder extends Seeder
{
    public function run(): void
    {
        $schoolIds = School::query()->pluck('id', 'slug');

        $inquiries = [
            ['school_slug' => 'skyline-driving-academy', 'name' => 'Rohan Kulkarni', 'phone' => '+91 9987000001', 'email' => 'rohan@example.com', 'vehicle_type' => 'car', 'area' => 'Baner', 'preferred_timing' => 'Morning', 'channel' => 'form', 'message' => 'Need weekday classes before office.', 'status' => 'pending'],
            ['school_slug' => 'puneauto-academy', 'name' => 'Ishita Patil', 'phone' => '+91 9987000002', 'email' => 'ishita@example.com', 'vehicle_type' => 'car', 'area' => 'Hinjewadi', 'preferred_timing' => 'Evening', 'channel' => 'form', 'message' => 'Interested in weekend batch.', 'status' => 'contacted'],
            ['school_slug' => 'roadstar', 'name' => 'Aditya Jadhav', 'phone' => '+91 9987000003', 'email' => 'aditya@example.com', 'vehicle_type' => 'car', 'area' => 'Wakad', 'preferred_timing' => 'Afternoon', 'channel' => 'whatsapp', 'message' => 'Please share package details.', 'status' => 'pending'],
            ['school_slug' => 'eastern-drive', 'name' => 'Pooja Nair', 'phone' => '+91 9987000004', 'email' => 'pooja@example.com', 'vehicle_type' => 'car', 'area' => 'Hadapsar', 'preferred_timing' => 'Morning', 'channel' => 'form', 'message' => 'Need women instructor.', 'status' => 'closed'],
            ['school_slug' => 'kothrud-driving-hub', 'name' => 'Siddharth Rao', 'phone' => '+91 9987000005', 'email' => 'sid@example.com', 'vehicle_type' => 'car', 'area' => 'Kothrud', 'preferred_timing' => 'Evening', 'channel' => 'form', 'message' => 'Looking for automatic training.', 'status' => 'pending'],
            ['school_slug' => 'pimpri-autodrive', 'name' => 'Neha More', 'phone' => '+91 9987000006', 'email' => 'neha@example.com', 'vehicle_type' => 'car', 'area' => 'Pimpri', 'preferred_timing' => 'Weekend', 'channel' => 'call', 'message' => 'Need fast-track course.', 'status' => 'contacted'],
        ];

        foreach ($inquiries as $inquiry) {
            $schoolId = $schoolIds[$inquiry['school_slug']] ?? null;
            if (!$schoolId) {
                continue;
            }

            unset($inquiry['school_slug']);
            $inquiry['school_id'] = $schoolId;

            Inquiry::query()->updateOrCreate(
                [
                    'school_id' => $inquiry['school_id'],
                    'name' => $inquiry['name'],
                    'phone' => $inquiry['phone'],
                ],
                $inquiry,
            );
        }
    }
}
