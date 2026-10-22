<?php

namespace Database\Seeders;

use App\Models\Locality;
use Illuminate\Database\Seeder;

/**
 * Production reference data (DIQ-1303): the Pune localities learners search
 * by. Safe to run again; it never creates users, schools or other demo
 * records. Plans come from migrations. Run with `make prod-seed`.
 */
class ReferenceDataSeeder extends Seeder
{
    /** Pune and Pimpri-Chinchwad areas, west to east. */
    private const LOCALITIES = [
        'Baner', 'Balewadi', 'Aundh', 'Pashan', 'Bavdhan', 'Sus', 'Hinjewadi', 'Wakad', 'Tathawade',
        'Pimple Saudagar', 'Pimple Nilakh', 'Ravet', 'Pimpri', 'Chinchwad', 'Akurdi', 'Nigdi', 'Bhosari', 'Moshi',
        'Kothrud', 'Karve Nagar', 'Warje', 'Erandwane', 'Deccan', 'Shivajinagar', 'Model Colony',
        'Sinhagad Road', 'Dhayari', 'Katraj', 'Bibwewadi', 'Swargate', 'Sahakar Nagar',
        'Camp', 'Koregaon Park', 'Kalyani Nagar', 'Yerawada', 'Vishrantwadi', 'Dhanori', 'Lohegaon',
        'Viman Nagar', 'Kharadi', 'Wagholi', 'Hadapsar', 'Magarpatta', 'Mundhwa', 'Wanowrie', 'Kondhwa', 'NIBM Road', 'Undri',
    ];

    public function run(): void
    {
        foreach (self::LOCALITIES as $name) {
            $slug = str($name)->slug()->toString();

            $locality = Locality::query()->firstOrNew(['slug' => $slug]);
            $locality->name = $name;
            // Keep any description an admin has already written.
            $locality->description ??= "Driving schools and independent trainers serving {$name}, Pune.";
            $locality->save();
        }
    }
}
