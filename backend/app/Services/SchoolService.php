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
        $radiusKm = isset($filters['radiusKm']) ? (float) $filters['radiusKm'] : 5.0;

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
        if (!empty($filters['locality'])) {
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

        if (!empty($filters['vehicleType'])) {
            $query->whereJsonContains('vehicle_types', $filters['vehicleType']);
        }

        if (!empty($filters['transmission'])) {
            $query->whereJsonContains('transmission', $filters['transmission']);
        }
    }

    private function listWithGeo(Builder $query, float $lat, float $lng, float $radiusKm, array $filters): Collection
    {
        $haversine = sprintf(
            '(6371 * acos(cos(radians(%s)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%s)) + sin(radians(%s)) * sin(radians(latitude))))',
            $lat, $lng, $lat
        );

        $schools = $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("*, {$haversine} as distance_km")
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km')
            ->orderByDesc('verified')
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->get();

        return $this->paginate($schools, $filters);
    }

    private function paginate(Collection $schools, array $filters): Collection
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 20);

        return $schools->slice($offset, $limit)->values();
    }
}
