<?php

namespace Tests\Feature;

use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DIQ-1303: production seeding creates reference data only. */
class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_seed_adds_pune_localities_and_nothing_else(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(ReferenceDataSeeder::class); // safe to run twice

        $this->assertGreaterThanOrEqual(40, Locality::count());
        $this->assertSame(1, Locality::where('slug', 'pimple-saudagar')->count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, School::count());
    }

    public function test_reference_seed_keeps_an_edited_description(): void
    {
        Locality::create(['name' => 'Baner', 'slug' => 'baner', 'description' => 'Written by our team.']);

        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame('Written by our team.', Locality::where('slug', 'baner')->value('description'));
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        // Called directly: `db:seed` would first ask for confirmation in production.
        app(DatabaseSeeder::class)->setContainer($this->app)->run();

        $this->assertSame(0, User::count());
    }
}
