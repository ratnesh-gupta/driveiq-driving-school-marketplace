<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DIQ-501: search cost must not grow with the number of schools. */
class SearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 500 schools around Baner, a third on paid plans.
        $now = now();
        $rows = [];
        for ($i = 1; $i <= 500; $i++) {
            $rows[] = [
                'name' => "School {$i}",
                'slug' => "perf-school-{$i}",
                'latitude' => 18.5590 + ($i % 50) * 0.001,
                'longitude' => 73.7868 + intdiv($i, 50) * 0.001,
                'rating' => ($i % 5) + 0.5,
                'review_count' => $i % 40,
                'verified' => $i % 2 === 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            School::insert($chunk);
        }

        $premium = Plan::where('code', 'premium')->firstOrFail();
        $subs = School::orderBy('id')->limit(170)->pluck('id')->map(fn ($id) => [
            'school_id' => $id, 'plan_id' => $premium->id, 'status' => 'active',
            'starts_at' => $now, 'expires_at' => $now->copy()->addMonth(),
            'created_at' => $now, 'updated_at' => $now,
        ])->all();
        Subscription::withoutGlobalScope('school')->insert($subs);
    }

    private function queriesFor(string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$response, $count];
    }

    public function test_list_uses_constant_queries_and_paginates_in_sql(): void
    {
        [$response, $queries] = $this->queriesFor('/api/schools?limit=20&offset=40');

        $this->assertCount(20, $response->json());
        $this->assertSame('500', $response->headers->get('X-Total-Count'));
        // count + page + eager-loaded localities; was ~1000 (one subscription query per school).
        $this->assertLessThanOrEqual(4, $queries);
    }

    public function test_geo_search_uses_constant_queries_and_reports_total(): void
    {
        [$response, $queries] = $this->queriesFor('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=10&limit=10');

        $this->assertCount(10, $response->json());
        $this->assertSame('500', $response->headers->get('X-Total-Count'));
        $this->assertLessThanOrEqual(4, $queries);

        // Top placement (premium) first, then descending score.
        $first = $response->json()[0];
        $this->assertSame('premium', $first['planCode']);
    }

    public function test_pages_do_not_overlap_and_order_is_deterministic(): void
    {
        $page1 = collect($this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=10&limit=25&offset=0')->json())->pluck('id');
        $page2 = collect($this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=10&limit=25&offset=25')->json())->pluck('id');
        $again = collect($this->getJson('/api/schools?nearLat=18.5590&nearLng=73.7868&radiusKm=10&limit=25&offset=0')->json())->pluck('id');

        $this->assertCount(0, $page1->intersect($page2));
        $this->assertSame($page1->all(), $again->all());
    }
}
