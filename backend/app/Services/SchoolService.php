<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Locality;
use App\Models\MarketplaceSetting;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolService
{
    /** Sponsored slots per page, read inside the search SQL (no extra query). */
    private const SLOTS_SQL = "COALESCE((SELECT (value #>> '{}')::int FROM marketplace_settings WHERE key = ?), ?)";

    /**
     * Search (DIQ-501): filtering, plan boost, ranking and pagination all run
     * in SQL, so the cost does not grow with the number of schools.
     *
     * @return array{items: Collection<int, School>, total: int}
     */
    public function search(array $filters): array
    {
        // Admins may list every listing, whatever its status (DIQ-1101).
        $inner = $this->withPlanMeta(
            School::query()->when(empty($filters['includeHidden']), fn ($q) => $q->public()),
            $filters['locality'] ?? null,
        );
        if (! empty($filters['includeHidden']) && ! empty($filters['listingStatus'])) {
            $inner->where('schools.listing_status', $filters['listingStatus']);
        }
        $this->applyFilters($inner, $filters);

        $nearLat = isset($filters['nearLat']) ? (float) $filters['nearLat'] : null;
        $nearLng = isset($filters['nearLng']) ? (float) $filters['nearLng'] : null;
        $geo = $nearLat !== null && $nearLng !== null;
        $sortBy = $filters['sortBy'] ?? 'rank';

        if ($geo) {
            $radiusKm = isset($filters['radiusKm'])
                ? (float) $filters['radiusKm']
                : (float) config('geo.default_radius_km', 5.0);
            $this->applyGeo($inner, $nearLat, $nearLng, $radiusKm);
        } else {
            $this->applyScoreWithoutDistance($inner);
        }

        // Outer query so the sponsored slot cap can use a window function.
        $query = School::query()->fromSub($inner, 'schools')->with('locality')->select('schools.*');

        if ($geo && $sortBy === 'distance') {
            // The visitor asked for nearest first: no pinned slots.
            $query->orderBy('distance_km')
                ->orderByDesc('plan_boost')
                ->orderByDesc('verified')
                ->orderByDesc('rating');
        } else {
            // DIQ-805: at most N sponsor-eligible schools (top-placement plan or
            // live campaign) are pinned to the top, best-ranked first; the rest
            // are ranked by score like everyone else (their boost is in it).
            $query->selectRaw(
                '(schools.top_placement AND ROW_NUMBER() OVER (PARTITION BY schools.top_placement ORDER BY schools.ranking_score DESC, schools.id) <= '.self::SLOTS_SQL.') AS is_pinned',
                ['sponsored_slots_per_page', MarketplaceSetting::DEFAULTS['sponsored_slots_per_page']]
            )
                ->orderByDesc('is_pinned')
                ->orderByDesc('ranking_score');
            if ($geo) {
                $query->orderBy('distance_km');
            }
        }
        $query->orderBy('schools.id');

        $total = (clone $query)->reorder()->count();

        $items = $query
            ->offset((int) ($filters['offset'] ?? 0))
            ->limit((int) ($filters['limit'] ?? 20))
            ->get();

        return ['items' => $items, 'total' => $total];
    }

    /** @return Collection<int, School> */
    public function list(array $filters): Collection
    {
        return $this->search($filters)['items'];
    }

    /**
     * Homepage: live homepage campaigns first, then plans with homepage
     * placement, then the best-rated verified schools (DIQ-805).
     */
    public function featured(): Collection
    {
        $campaigns = DB::table('featured_placements')
            ->where('placement', 'homepage')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->select('school_id')
            ->distinct();

        return $this->withPlanMeta(School::query()->public()->with('locality'))
            ->leftJoinSub($campaigns, 'hp', 'hp.school_id', '=', 'schools.id')
            ->where(fn ($q) => $q->where('schools.verified', true)->orWhereNotNull('hp.school_id'))
            ->orderByRaw('(hp.school_id IS NOT NULL) DESC')
            ->orderByDesc('homepage_featured')
            ->orderByDesc('plan_boost')
            ->orderByDesc('schools.rating')
            ->orderBy('schools.id')
            ->limit((int) MarketplaceSetting::get('homepage_slots'))
            ->get();
    }

    /** Hidden listings (DIQ-1101) are only found when $includeHidden (staff/admin). */
    public function findById(int $id, bool $includeHidden = false): ?School
    {
        return $this->withPlanMeta(School::query()->with('locality'))
            ->when(! $includeHidden, fn ($q) => $q->public())
            ->where('schools.id', $id)->first();
    }

    public function findBySlug(string $slug): ?School
    {
        return $this->withPlanMeta(School::query()->public()->with('locality'))->where('schools.slug', $slug)->first();
    }

    /**
     * Side-by-side comparison (DIQ-505, School-Comparison-Engine.md §13):
     * schools in the requested order, with package pricing, review summary
     * and the spec's insight badges. Constant number of queries.
     *
     * @param  int[]  $ids
     * @return array{schools: Collection<int, School>, packages: array, reviews: array, badges: array}
     */
    public function compare(array $ids): array
    {
        $schools = $this->withPlanMeta(School::query()->public()->with('locality'))
            ->whereIn('schools.id', $ids)
            ->get()
            ->sortBy(fn (School $s) => array_search($s->id, $ids, true))
            ->values();

        $found = $schools->pluck('id')->all();

        $packages = DB::table('packages')
            ->whereIn('school_id', $found)
            ->where('active', true)
            ->groupBy('school_id')
            ->selectRaw('school_id, COUNT(*) AS count, MIN(price) AS min_price, MAX(price) AS max_price')
            ->get()
            ->keyBy('school_id');

        $reviewStats = DB::table('reviews')
            ->whereIn('school_id', $found)
            ->where('approved', true)
            ->groupBy('school_id')
            ->selectRaw('school_id, COUNT(*) AS count, AVG(rating) AS average')
            ->get()
            ->keyBy('school_id');

        $topReviews = DB::table('reviews')
            ->whereIn('school_id', $found)
            ->where('approved', true)
            ->selectRaw('DISTINCT ON (school_id) school_id, author_name, rating, content')
            ->orderBy('school_id')
            ->orderByDesc('rating')
            ->orderByDesc('created_at')
            ->get()
            ->keyBy('school_id');

        $packageSummary = [];
        $reviewSummary = [];
        foreach ($found as $id) {
            $p = $packages->get($id);
            $packageSummary[$id] = [
                'count' => (int) ($p->count ?? 0),
                'minPrice' => $p ? (float) $p->min_price : null,
                'maxPrice' => $p ? (float) $p->max_price : null,
            ];
            $r = $reviewStats->get($id);
            $top = $topReviews->get($id);
            $reviewSummary[$id] = [
                'count' => (int) ($r->count ?? 0),
                'average' => $r ? round((float) $r->average, 2) : null,
                'topReview' => $top ? [
                    'authorName' => $top->author_name,
                    'rating' => (int) $top->rating,
                    'content' => $top->content,
                ] : null,
            ];
        }

        return [
            'schools' => $schools,
            'packages' => $packageSummary,
            'reviews' => $reviewSummary,
            'badges' => $this->comparisonBadges($schools),
        ];
    }

    /**
     * Insight badges from the comparison spec. Only meaningful with 2+ schools;
     * ties go to more reviews, then the lower id, so results are deterministic.
     */
    private function comparisonBadges(Collection $schools): array
    {
        $none = ['bestRated' => null, 'mostAffordable' => null, 'bestValue' => null, 'mostReviewed' => null, 'womenFriendly' => []];
        if ($schools->count() < 2) {
            return $none;
        }

        $pick = fn (Collection $c, callable $score) => $c
            ->sortBy([
                fn ($a, $b) => $score($b) <=> $score($a),
                fn ($a, $b) => $b->review_count <=> $a->review_count,
                fn ($a, $b) => $a->id <=> $b->id,
            ])
            ->first()?->id;

        $priced = $schools->filter(fn (School $s) => (float) $s->price_from > 0);

        return [
            'bestRated' => $pick($schools, fn (School $s) => (float) $s->rating),
            'mostAffordable' => $pick($priced, fn (School $s) => -(float) $s->price_from),
            // Rating per rupee of starting price.
            'bestValue' => $pick($priced, fn (School $s) => (float) $s->rating / (float) $s->price_from),
            'mostReviewed' => $pick($schools, fn (School $s) => (int) $s->review_count),
            'womenFriendly' => $schools
                ->filter(fn (School $s) => $s->women_instructor && (float) $s->rating >= 4.0)
                ->pluck('id')
                ->values()
                ->all(),
        ];
    }

    public function create(array $data): School
    {
        $school = School::create($data);

        AuditLog::log('create', 'School', $school->id, [], $school->only(array_keys($data)), $school->id);

        return $school->load('locality');
    }

    public function update(School $school, array $data): School
    {
        $original = $school->getOriginal();
        $school->fill($data);
        $school->save();

        // Log only what actually changed (old -> new), not the whole row.
        $changed = array_diff_key($school->getChanges(), ['updated_at' => true]);
        if ($changed !== []) {
            AuditLog::log(
                'update',
                'School',
                $school->id,
                array_intersect_key($original, $changed),
                $changed,
                $school->id,
            );
        }

        return $school->load('locality');
    }

    public function delete(School $school): void
    {
        AuditLog::log('delete', 'School', $school->id, $school->only(['name', 'slug', 'user_id']), [], $school->id);
        $school->delete();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['locality'])) {
            $locality = Locality::where('slug', $filters['locality'])->first();
            if ($locality) {
                $query->where('locality_id', $locality->id);
            }
        }

        if (! empty($filters['listingType'])) {
            $query->where('schools.listing_type', $filters['listingType']);
        }

        if (isset($filters['minRating'])) {
            $query->where('rating', '>=', $filters['minRating']);
        }

        if (isset($filters['hasPickup'])) {
            $query->where('has_pickup', (bool) $filters['hasPickup']);
        }

        if (isset($filters['womenInstructor'])) {
            $query->where('women_instructor', (bool) $filters['womenInstructor']);
        }

        if (isset($filters['weekendClasses'])) {
            $query->where('weekend_classes', (bool) $filters['weekendClasses']);
        }

        if (isset($filters['maxPrice'])) {
            $query->where('price_from', '<=', $filters['maxPrice']);
        }

        if (isset($filters['verified']) && $filters['verified']) {
            $query->where('verified', true);
        }

        if (! empty($filters['vehicleType'])) {
            $value = strtolower($filters['vehicleType']);
            $query->where(function (Builder $q) use ($filters, $value) {
                $q->whereJsonContains('vehicle_types', $value)
                    ->orWhereJsonContains('vehicle_types', $filters['vehicleType']);
            });
        }

        if (! empty($filters['transmission'])) {
            $value = strtolower($filters['transmission']);
            $query->where(function (Builder $q) use ($filters, $value) {
                $q->whereJsonContains('transmission', $value)
                    ->orWhereJsonContains('transmission', $filters['transmission']);
            });
        }
    }

    /**
     * Adds each school's current plan (latest active, unexpired subscription)
     * as plan_code, plan_boost (0-1), is_sponsored, homepage_featured and
     * top_placement, in one joined subquery instead of a query per school.
     */
    private function withPlanMeta(Builder $query, ?string $localitySlug = null): Builder
    {
        // Live sponsored campaigns (DIQ-805): search-wide, or for the locality
        // being browsed.
        $campaigns = DB::table('featured_placements as fp')
            ->where('fp.starts_at', '<=', now())
            ->where('fp.ends_at', '>', now())
            ->where(function ($q) use ($localitySlug) {
                $q->where('fp.placement', 'search_top');
                if ($localitySlug) {
                    $q->orWhere(fn ($w) => $w->where('fp.placement', 'locality')
                        ->whereIn('fp.locality_id', DB::table('localities')->select('id')->where('slug', $localitySlug)));
                }
            })
            ->select('fp.school_id')
            ->distinct();

        $activePlans = DB::table('subscriptions as sub')
            ->join('plans as p', 'p.id', '=', 'sub.plan_id')
            ->where('sub.status', 'active')
            ->where(fn ($q) => $q->whereNull('sub.expires_at')->orWhere('sub.expires_at', '>', now()))
            ->selectRaw('DISTINCT ON (sub.school_id) sub.school_id, p.code, p.ranking_boost, p.is_sponsored, p.homepage_featured')
            ->orderBy('sub.school_id')
            ->orderByDesc('sub.id');

        $topPlans = array_values(config('geo.top_placement_plans', []));
        $topSql = $topPlans === []
            ? 'false'
            : 'COALESCE(ap.code, \'basic\') IN ('.implode(', ', array_fill(0, count($topPlans), '?')).')';

        return $query
            ->leftJoinSub($activePlans, 'ap', 'ap.school_id', '=', 'schools.id')
            ->leftJoinSub($campaigns, 'camp', 'camp.school_id', '=', 'schools.id')
            ->select('schools.*')
            ->selectRaw("COALESCE(ap.code, 'basic') AS plan_code")
            ->selectRaw('LEAST(GREATEST(COALESCE(ap.ranking_boost, 0), 0), 100) / 100.0 AS plan_boost')
            ->selectRaw('COALESCE(ap.is_sponsored, false) AS is_sponsored')
            ->selectRaw('COALESCE(ap.homepage_featured, false) AS homepage_featured')
            ->selectRaw('(camp.school_id IS NOT NULL) AS has_campaign')
            // Eligible for a sponsored top slot: top-placement plan or live campaign.
            ->selectRaw("(({$topSql}) OR camp.school_id IS NOT NULL) AS top_placement", $topPlans);
    }

    /** Ranking score without the distance term, for non-geo search (DIQ-805). */
    private function applyScoreWithoutDistance(Builder $query): void
    {
        $w = config('geo.ranking');

        $query->selectRaw('ROUND((
              ? * LEAST(GREATEST(COALESCE(schools.rating, 0) / 5.0, 0.0), 1.0)
            + ? * LEAST(COALESCE(schools.review_count, 0)::float / ?, 1.0)
            + ? * (CASE WHEN schools.verified THEN 1.0 ELSE 0.0 END)
            + ? * (LEAST(GREATEST(COALESCE(ap.ranking_boost, 0), 0), 100) / 100.0)
        )::numeric, 6)::float AS ranking_score', [
            (float) ($w['weight_rating'] ?? 0.25),
            (float) ($w['weight_reviews'] ?? 0.12), max(1, (int) ($w['review_cap'] ?? 50)),
            (float) ($w['weight_verified'] ?? 0.08),
            (float) ($w['weight_premium'] ?? 0.15),
        ]);
    }

    /**
     * Radius filter + distance + ranking score in SQL (PostGIS). The score is
     * the documented formula in docs/PHASE-1-GEO-RANKING.md with the weights
     * from config/geo.php.
     */
    private function applyGeo(Builder $query, float $lat, float $lng, float $radiusKm): void
    {
        $w = config('geo.ranking');
        $reviewCap = max(1, (int) ($w['review_cap'] ?? 50));
        $origin = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';
        $distance = "(ST_Distance(schools.location, {$origin}) / 1000.0)";

        $score = "(
            ? * GREATEST(0.0, 1.0 - LEAST({$distance}, ?) / ?)
          + ? * LEAST(GREATEST(COALESCE(schools.rating, 0) / 5.0, 0.0), 1.0)
          + ? * LEAST(COALESCE(schools.review_count, 0)::float / ?, 1.0)
          + ? * (CASE WHEN schools.verified THEN 1.0 ELSE 0.0 END)
          + ? * (LEAST(GREATEST(COALESCE(ap.ranking_boost, 0), 0), 100) / 100.0)
        )";

        $query
            ->selectRaw("{$distance} AS distance_km", [$lng, $lat])
            ->selectRaw("ROUND(({$score})::numeric, 6)::float AS ranking_score", [
                (float) ($w['weight_distance'] ?? 0.4), $lng, $lat, $radiusKm, $radiusKm,
                (float) ($w['weight_rating'] ?? 0.25),
                (float) ($w['weight_reviews'] ?? 0.12), $reviewCap,
                (float) ($w['weight_verified'] ?? 0.08),
                (float) ($w['weight_premium'] ?? 0.15),
            ])
            ->whereRaw("ST_DWithin(schools.location, {$origin}, ?)", [$lng, $lat, $radiusKm * 1000]);
    }
}
