<?php

namespace Database\Seeders;

use App\Models\DrivePackage;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local / staging demo accounts (do not use these passwords in production).
 *
 * admin@driveiq.in              / password123  → /admin
 * info@skylinedrive.in          / password123  → /dashboard
 * trainer.skyline@driveiq.in    / password123  → /instructor
 * learner.asha@driveiq.in       / password123  → /learner
 */
class OpsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::query()->where('slug', 'skyline-driving-academy')->first();
        if (! $school) {
            return;
        }

        $owner = User::query()->where('email', 'info@skylinedrive.in')->first();
        if ($owner) {
            $owner->update([
                'role' => 'school',
                'school_id' => $school->id,
            ]);
            if (! $school->user_id) {
                $school->update(['user_id' => $owner->id]);
            }
        }

        $trainerUser = User::query()->updateOrCreate(
            ['email' => 'trainer.skyline@driveiq.in'],
            [
                'name' => 'Neha Kulkarni',
                'password' => 'password123',
                'role' => 'instructor',
                'school_id' => $school->id,
            ]
        );

        $instructor = Instructor::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $school->id, 'email' => 'trainer.skyline@driveiq.in'],
            [
                'user_id' => $trainerUser->id,
                'name' => 'Neha Kulkarni',
                'mobile' => '9876501111',
                'status' => 'active',
                'employment_type' => 'full_time',
                'years_experience' => 6,
                'women_instructor' => true,
                'public_visible' => true,
                'languages' => ['English', 'Marathi', 'Hindi'],
                'bio' => 'City and highway trainer at Skyline.',
            ]
        );

        $learnerUser = User::query()->updateOrCreate(
            ['email' => 'learner.asha@driveiq.in'],
            [
                'name' => 'Asha Patil',
                'password' => 'password123',
                'role' => 'learner',
                'school_id' => $school->id,
            ]
        );

        $package = DrivePackage::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('name', 'Starter Manual')
            ->first();

        $learner = Learner::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $school->id, 'email' => 'learner.asha@driveiq.in'],
            [
                'user_id' => $learnerUser->id,
                'name' => 'Asha Patil',
                'mobile' => '9876502222',
                'status' => 'active',
                'vehicle_type' => 'car',
                'package_id' => $package?->id,
                'assigned_instructor_id' => $instructor->id,
                'start_date' => now()->subDays(10)->toDateString(),
            ]
        );

        $date = now()->addDay()->toDateString();
        Schedule::withoutGlobalScope('school')->updateOrCreate(
            [
                'school_id' => $school->id,
                'instructor_id' => $instructor->id,
                'learner_id' => $learner->id,
                'session_date' => $date,
                'start_time' => '07:00',
            ],
            [
                'learner_name' => $learner->name,
                'end_time' => '08:00',
                'pickup_location' => 'Baner Road',
                'status' => 'scheduled',
                'created_by' => $owner?->id,
            ]
        );
    }
}
