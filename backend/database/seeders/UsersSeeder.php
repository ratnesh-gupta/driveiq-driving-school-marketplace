<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Rahul Sharma', 'email' => 'rahul@gmail.com', 'password' => 'password123', 'role' => 'user'],
            ['name' => 'Skyline Driving Academy', 'email' => 'info@skylinedrive.in', 'password' => 'password123', 'role' => 'school'],
            ['name' => 'Priya Deshpande', 'email' => 'priya@gmail.com', 'password' => 'password123', 'role' => 'user'],
            ['name' => 'Admin User', 'email' => 'admin@driveiq.in', 'password' => 'password123', 'role' => 'admin'],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                $user,
            );
        }
    }
}
