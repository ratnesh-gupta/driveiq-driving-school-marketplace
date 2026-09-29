<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Locality;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolService
{
    /**
     * Search (DIQ-501): filtering, plan boost, ranking and pagination all run
     * in SQL, so the cost does not grow with the number of schools.
     *
     * @return array{items: Collection<int, School>, total: int}
     */
    public function search(array $filters): array
    {
        $query = $this->withPlanMeta(School::query()->with('locality'));
        $this->applyFilters($query, $filters);

        $nearLat = isset($filters['nearLat']) ? (float) $filters['nearLat'] : null;
        $nearLng = isset($filters['nearLng']) ? (float) $filters['nearLng'] : null;

        if ($nearLat !== null && $nearLng !== null) {
            $radiusKm = isset($filters['radiusKm'])
                ? (float) $filters['radiusKm']
                : (float) config('geo.default_radius_km', 5.0);
            $this->applyGeo($query, $nearLat, $nearLng, $radiusKm, $filters['sortBy'] ?? 'rank');
        } else {
            // Paid tiers first, then rating.
            $query->orderByDesc('plan_boost')
                ->orderByDesc('schools.rating')
                ->orderByDesc('schools.review_count')
                ->orderBy('schools.id');
        }

        $total = (clone $query)->reorder()->count('schools.id');

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

    public function featured(): Collection
    {
        return $this->withPlanMeta(School::query()->with('locality'))
            ->where('schools.verified', true)
            ->orderByDesc('homepage_featured')
            ->orderByDesc('plan_boost')
            ->orderByDesc('schools.rating')
            ->orderBy('schools.id')
            ->limit(6)
            ->get();
    }

    public function findById(int $id): ?School
    {
        return $this->withPlanMeta(School::query()->with('locality'))->where('schools.id', $id)->first();
    }

    public function findBySlug(string $slug): ?School
    {
        return $this->withPlanMeta(School::query()->with('locality'))->where('schools.slug', $slug)->first();
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
    private function withPlanMeta(Builder $query): Builder
    {
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
            ->select('schools.*')
            ->selectRaw("COALESCE(ap.code, 'basic') AS plan_code")
            ->selectRaw('LEAST(GREATEST(COALESCE(ap.ranking_boost, 0), 0), 100) / 100.0 AS plan_boost')
            ->selectRaw('COALESCE(ap.is_sponsored, false) AS is_sponsored')
            ->selectRaw('COALESCE(ap.homepage_featured, false) AS homepage_featured')
            ->selectRaw("({$topSql}) AS top_placement", $topPlans);
    }

    /**
     * Radius filter + distance + ranking score in SQL (PostGIS). The score is
     * the documented formula in docs/PHASE-1-GEO-RANKING.md with the weights
     * from config/geo.php.
     */
    private function applyGeo(Builder $query, float $lat, float $lng, float $radiusKm, string $sortBy): void
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

        if ($sortBy === 'distance') {
            $query->orderBy('distance_km')
                ->orderByDesc('plan_boost')
                ->orderByDesc('schools.verified')
                ->orderByDesc('schools.rating');
        } else {
            $query->orderByDesc('top_placement')
                ->orderByDesc('ranking_score')
                ->orderBy('distance_km');
        }

        $query->orderBy('schools.id');
    }
}
