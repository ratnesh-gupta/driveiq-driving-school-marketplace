<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\OutreachMessage;
use App\Models\Prospect;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DIQ-1202: the demo showcase loads and looks lived-in. */
class DemoShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_loads_a_rich_marketplace(): void
    {
        $this->artisan('driveiq:demo --fresh')->assertSuccessful();

        $this->assertGreaterThanOrEqual(12, School::query()->public()->count());
        $this->assertSame(2, School::query()->public()->where('listing_type', 'trainer')->count());
        $this->assertSame(0, School::query()->where('image_url', 'like', 'http%')->count(), 'no hot-linked photos');

        $skyline = School::where('slug', 'skyline-driving-academy')->first();
        $statuses = Inquiry::withoutGlobalScope('school')->where('school_id', $skyline->id)->distinct()->pluck('status')->sort()->values()->all();
        $this->assertSame(['contacted', 'converted', 'follow_up', 'interested', 'lost', 'pending'], $statuses);

        $this->getJson('/api/schools?listingType=trainer')->assertJsonCount(2);
        $this->assertTrue(collect($this->getJson('/api/schools')->json())->contains('isSponsored', true));
        $this->assertGreaterThan(0, Prospect::count());
        $this->assertGreaterThan(0, OutreachMessage::whereNotNull('clicked_at')->count());

        // Running it again does not duplicate anything.
        $count = School::count();
        $this->artisan('driveiq:demo')->assertSuccessful();
        $this->assertSame($count, School::count());
    }

    public function test_refuses_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('driveiq:demo')->assertFailed();
    }
}
