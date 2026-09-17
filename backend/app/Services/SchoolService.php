<?php

namespace App\Services;

use App\Models\Locality;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SchoolService
{
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
            ->get();

        return $this->paginate($schools, $filters);
    }

    public function featured(): Collection
    {
        return School::with('locality')
            ->verified()
            ->orderByDesc('rating')
            ->limit(6)
            ->get();
    }

    public function findById(int $id): ?School
    {
        return School::with('locality')->find($id);
    }

    public function findBySlug(string $slug): ?School
    {
        return School::with('locality')->where('slug', $slug)->first();
    }

    public function create(array $data): School
    {
        $school = School::create($data);

        return $school->load('locality');
    }

    public function update(School $school, array $data): School
    {
        $school->fill($data);
        $school->save();

        return $school->load('locality');
    }

    public function delete(School $school): void
    {
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
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / max(111.0 * cos(deg2rad($lat)), 0.01);

        $haversine = sprintf(
            '(6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(%1$s)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%2$s)) + sin(radians(%1$s)) * sin(radians(latitude))))))',
            $lat,
            $lng
        );

        $schools = $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->selectRaw("schools.*, {$haversine} as distance_km")
            ->havingRaw('distance_km <= ?', [$radiusKm])
            ->get();

        $schools = $schools->map(function (School $school) use ($radiusKm) {
            $school->ranking_score = $this->computeRankingScore($school, $radiusKm);

            return $school;
        });

        $sortBy = $filters['sortBy'] ?? 'rank';

        if ($sortBy === 'distance') {
            $schools = $schools
                ->sortBy([
                    ['distance_km', 'asc'],
                    ['verified', 'desc'],
                    ['rating', 'desc'],
                ])
                ->values();
        } else {
            $schools = $schools
                ->sortBy([
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

        $score =
            ((float) $weights['weight_distance'] * $distanceScore)
            + ((float) $weights['weight_rating'] * $ratingScore)
            + ((float) $weights['weight_reviews'] * $reviewScore)
            + ((float) $weights['weight_verified'] * $verifiedBonus);

        return round($score, 6);
    }

    private function paginate(Collection $schools, array $filters): Collection
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 20);

        return $schools->slice($offset, $limit)->values();
    }
}
