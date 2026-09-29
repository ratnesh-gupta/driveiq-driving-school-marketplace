<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Locality;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SchoolService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function list(array $filters): Collection
    {
        $query = School::with('locality');

        $this->applyFilters($query, $filters);

        $nearLat = isset($filters['nearLat']) ? (float) $filters['nearLat'] : null;
        $nearLng = isset($filters['nearLng']) ? (float) $filters['nearLng'] : null;
        $radiusKm = isset($filters['radiusKm'])
            ? (float) $filters['radiusKm']
            : (float) config('geo.default_radius_km', 5.0);

        if ($nearLat !== null && $nearLng !== null) {
            return $this->listWithGeo($query, $nearLat, $nearLng, $radiusKm, $filters);
        }

        $schools = $query
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->get()
            ->map(fn (School $s) => $this->attachPlanMeta($s));

        // Paid tiers first when not using pure distance sort
        $schools = $schools->sortBy([
            ['plan_boost', 'desc'],
            ['rating', 'desc'],
            ['review_count', 'desc'],
        ])->values();

        return $this->paginate($schools, $filters);
    }

    public function featured(): Collection
    {
        $schools = School::with('locality')
            ->verified()
            ->orderByDesc('rating')
            ->limit(24)
            ->get()
            ->map(fn (School $s) => $this->attachPlanMeta($s));

        return $schools
            ->sortBy([
                ['homepage_featured', 'desc'],
                ['plan_boost', 'desc'],
                ['rating', 'desc'],
            ])
            ->take(6)
            ->values();
    }

    public function findById(int $id): ?School
    {
        $school = School::with('locality')->find($id);

        return $school ? $this->attachPlanMeta($school) : null;
    }

    public function findBySlug(string $slug): ?School
    {
        $school = School::with('locality')->where('slug', $slug)->first();

        return $school ? $this->attachPlanMeta($school) : null;
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

    private function listWithGeo(Builder $query, float $lat, float $lng, float $radiusKm, array $filters): Collection
    {
        // PostGIS: schools.location (geography, GIST-indexed) is kept in sync
        // with latitude/longitude by a DB trigger. ST_DWithin uses the index.
        $origin = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        $schools = $query
            ->select('schools.*')
            ->selectRaw("ST_Distance(schools.location, {$origin}) / 1000.0 AS distance_km", [$lng, $lat])
            ->whereRaw("ST_DWithin(schools.location, {$origin}, ?)", [$lng, $lat, $radiusKm * 1000])
            ->get()
            ->map(function (School $school) use ($radiusKm) {
                $this->attachPlanMeta($school);
                $school->ranking_score = $this->computeRankingScore($school, $radiusKm);

                return $school;
            });

        $sortBy = $filters['sortBy'] ?? 'rank';

        if ($sortBy === 'distance') {
            $schools = $schools
                ->sortBy([
                    ['distance_km', 'asc'],
                    ['plan_boost', 'desc'],
                    ['verified', 'desc'],
                    ['rating', 'desc'],
                    ['id', 'asc'],
                ])
                ->values();
        } else {
            $schools = $schools
                ->sortBy([
                    ['top_placement', 'desc'],
                    ['ranking_score', 'desc'],
                    ['distance_km', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
        }

        return $this->paginate($schools, $filters);
    }

    public function computeRankingScore(School $school, float $radiusKm): float
    {
        $weights = config('geo.ranking');
        $reviewCap = max(1, (int) ($weights['review_cap'] ?? 50));

        $distanceKm = (float) ($school->distance_km ?? $radiusKm);
        $distanceScore = $radiusKm > 0
            ? max(0.0, 1.0 - min($distanceKm, $radiusKm) / $radiusKm)
            : 0.0;

        $ratingScore = min(max((float) ($school->rating ?? 0) / 5.0, 0.0), 1.0);
        $reviewScore = min((int) ($school->review_count ?? 0) / $reviewCap, 1.0);
        $verifiedBonus = $school->verified ? 1.0 : 0.0;
        $premiumBoost = (float) ($school->plan_boost ?? 0.0);

        $score =
            ((float) ($weights['weight_distance'] ?? 0.4) * $distanceScore)
            + ((float) ($weights['weight_rating'] ?? 0.25) * $ratingScore)
            + ((float) ($weights['weight_reviews'] ?? 0.12) * $reviewScore)
            + ((float) ($weights['weight_verified'] ?? 0.08) * $verifiedBonus)
            + ((float) ($weights['weight_premium'] ?? 0.15) * $premiumBoost);

        return round($score, 6);
    }

    private function attachPlanMeta(School $school): School
    {
        $sub = $this->subscriptions->activeForSchool((int) $school->id);
        $school->plan_code = $sub?->plan?->code ?? 'basic';
        $school->plan_boost = $sub?->plan?->rankingBoostScore() ?? 0.0;
        $school->is_sponsored = (bool) ($sub?->plan?->is_sponsored);
        $school->homepage_featured = (bool) ($sub?->plan?->homepage_featured);
        $school->top_placement = in_array($school->plan_code, config('geo.top_placement_plans', []), true);

        return $school;
    }

    private function paginate(Collection $schools, array $filters): Collection
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 20);

        return $schools->slice($offset, $limit)->values();
    }
}
