<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Locality;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SchoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'locality' => ['nullable', 'string'],
            'vehicleType' => ['nullable', 'string'],
            'minRating' => ['nullable', 'numeric'],
            'hasPickup' => ['nullable', 'boolean'],
            'womenInstructor' => ['nullable', 'boolean'],
            'weekendClasses' => ['nullable', 'boolean'],
            'maxPrice' => ['nullable', 'numeric'],
            'transmission' => ['nullable', 'string'],
            'nearLat' => ['nullable', 'numeric', 'between:-90,90'],
            'nearLng' => ['nullable', 'numeric', 'between:-180,180'],
            'radiusKm' => ['nullable', 'numeric', 'min:1', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->toJson()], 400);
        }

        $v = $validator->validated();
        $nearLat = isset($v['nearLat']) ? (float) $v['nearLat'] : null;
        $nearLng = isset($v['nearLng']) ? (float) $v['nearLng'] : null;
        $radiusKm = isset($v['radiusKm']) ? (float) $v['radiusKm'] : 5.0;

        $query = $this->schoolBaseQuery();

        if (!empty($v['locality'])) {
            $loc = Locality::query()->where('slug', $v['locality'])->first();
            if ($loc) {
                $query->where('schools.locality_id', $loc->id);
            }
        }

        if (isset($v['minRating'])) $query->where('schools.rating', '>=', $v['minRating']);
        if (isset($v['hasPickup'])) $query->where('schools.has_pickup', (bool) $v['hasPickup']);
        if (isset($v['womenInstructor'])) $query->where('schools.women_instructor', (bool) $v['womenInstructor']);
        if (isset($v['weekendClasses'])) $query->where('schools.weekend_classes', (bool) $v['weekendClasses']);
        if (isset($v['maxPrice'])) $query->where('schools.price_from', '<=', $v['maxPrice']);
        if (!empty($v['vehicleType'])) $query->whereJsonContains('schools.vehicle_types', $v['vehicleType']);
        if (!empty($v['transmission'])) $query->whereJsonContains('schools.transmission', $v['transmission']);

        $rows = $query->get();

        if ($nearLat !== null && $nearLng !== null) {
            $rows = $rows
                ->filter(fn ($row) => $row->latitude !== null && $row->longitude !== null)
                ->map(function ($row) use ($nearLat, $nearLng): object {
                    $distance = $this->haversineKm($nearLat, $nearLng, (float) $row->latitude, (float) $row->longitude);
                    $row->distanceKm = round($distance, 2);
                    return $row;
                })
                ->filter(fn ($row) => $row->distanceKm <= $radiusKm)
                ->sort(function ($a, $b): int {
                    if ($a->distanceKm !== $b->distanceKm) {
                        return $a->distanceKm <=> $b->distanceKm;
                    }
                    if ((bool) $a->verified !== (bool) $b->verified) {
                        return (bool) $b->verified <=> (bool) $a->verified;
                    }
                    if ((float) $a->rating !== (float) $b->rating) {
                        return (float) $b->rating <=> (float) $a->rating;
                    }
                    return (int) $b->reviewCount <=> (int) $a->reviewCount;
                })
                ->values();
        } else {
            $rows = $rows
                ->sort(function ($a, $b): int {
                    if ((float) $a->rating !== (float) $b->rating) {
                        return (float) $b->rating <=> (float) $a->rating;
                    }
                    return (int) $b->reviewCount <=> (int) $a->reviewCount;
                })
                ->values();
        }

        $offset = (int) ($v['offset'] ?? 0);
        $limit = (int) ($v['limit'] ?? 20);
        $paged = $rows->slice($offset, $limit)->values();

        return response()->json($paged->map(fn ($r) => $this->normalizeSchool($r))->values());
    }

    public function featured(): JsonResponse
    {
        $rows = $this->schoolBaseQuery()
            ->where('schools.verified', true)
            ->orderByDesc('schools.rating')
            ->limit(6)
            ->get();

        return response()->json($rows->map(fn ($r) => $this->normalizeSchool($r))->values());
    }

    public function show(int $id): JsonResponse
    {
        $row = $this->schoolBaseQuery()->where('schools.id', $id)->first();
        if (!$row) return response()->json(['error' => 'School not found'], 404);
        return response()->json($this->normalizeSchool($row));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $row = $this->schoolBaseQuery()->where('schools.slug', $slug)->first();
        if (!$row) return response()->json(['error' => 'School not found'], 404);
        return response()->json($this->normalizeSchool($row));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->schoolRules(true));
        if ($validator->fails()) return response()->json(['error' => $validator->errors()->toJson()], 400);

        $school = School::create($this->toSchoolPayload($validator->validated()));
        return response()->json($this->normalizeModelSchool($school), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $school = School::query()->find($id);
        if (!$school) return response()->json(['error' => 'School not found'], 404);

        $validator = Validator::make($request->all(), $this->schoolRules(false));
        if ($validator->fails()) return response()->json(['error' => 'Invalid input'], 400);

        $school->fill($this->toSchoolPayload($validator->validated()));
        $school->save();

        return response()->json($this->normalizeModelSchool($school));
    }

    public function delete(int $id): JsonResponse
    {
        School::query()->where('id', $id)->delete();
        return response()->json(null, 204);
    }

    private function schoolBaseQuery()
    {
        return School::query()->leftJoin('localities', 'schools.locality_id', '=', 'localities.id')->select([
            'schools.id', 'schools.name', 'schools.slug',
            DB::raw('schools.locality_id as "localityId"'),
            DB::raw('localities.name as "localityName"'),
            'schools.address', 'schools.phone', 'schools.whatsapp', 'schools.email',
            'schools.description', DB::raw('schools.image_url as "imageUrl"'),
            'schools.rating', DB::raw('schools.review_count as "reviewCount"'),
            'schools.verified', DB::raw('schools.has_pickup as "hasPickup"'),
            DB::raw('schools.women_instructor as "womenInstructor"'),
            DB::raw('schools.weekend_classes as "weekendClasses"'),
            DB::raw('schools.vehicle_types as "vehicleTypes"'),
            'schools.transmission', DB::raw('schools.price_from as "priceFrom"'),
            DB::raw('schools.price_to as "priceTo"'),
            'schools.timings', DB::raw('schools.service_areas as "serviceAreas"'),
            DB::raw('schools.latitude as "latitude"'),
            DB::raw('schools.longitude as "longitude"'),
            DB::raw('schools.service_radius_km as "serviceRadiusKm"'),
            DB::raw('schools.created_at as "createdAt"'),
        ]);
    }

    private function schoolRules(bool $create): array
    {
        return [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => [$create ? 'required' : 'sometimes', 'string', 'max:255'],
            'localityId' => [$create ? 'required' : 'sometimes', 'integer'],
            'address' => [$create ? 'required' : 'sometimes', 'string'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'serviceRadiusKm' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'phone' => [$create ? 'required' : 'sometimes', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
            'rating' => ['sometimes', 'numeric'],
            'reviewCount' => ['sometimes', 'integer', 'min:0'],
            'verified' => ['sometimes', 'boolean'],
            'hasPickup' => ['sometimes', 'boolean'],
            'womenInstructor' => ['sometimes', 'boolean'],
            'weekendClasses' => ['sometimes', 'boolean'],
            'vehicleTypes' => ['sometimes', 'array'],
            'vehicleTypes.*' => ['string'],
            'transmission' => ['sometimes', 'array'],
            'transmission.*' => ['string'],
            'priceFrom' => ['sometimes', 'numeric'],
            'priceTo' => ['sometimes', 'numeric'],
            'timings' => ['nullable', 'string', 'max:255'],
            'serviceAreas' => ['sometimes', 'array'],
            'serviceAreas.*' => ['string'],
        ];
    }

    private function toSchoolPayload(array $v): array
    {
        $map = [
            'name' => 'name',
            'slug' => 'slug',
            'localityId' => 'locality_id',
            'address' => 'address',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'serviceRadiusKm' => 'service_radius_km',
            'phone' => 'phone',
            'whatsapp' => 'whatsapp',
            'email' => 'email',
            'description' => 'description',
            'imageUrl' => 'image_url',
            'rating' => 'rating',
            'reviewCount' => 'review_count',
            'verified' => 'verified',
            'hasPickup' => 'has_pickup',
            'womenInstructor' => 'women_instructor',
            'weekendClasses' => 'weekend_classes',
            'vehicleTypes' => 'vehicle_types',
            'transmission' => 'transmission',
            'priceFrom' => 'price_from',
            'priceTo' => 'price_to',
            'timings' => 'timings',
            'serviceAreas' => 'service_areas',
        ];

        $payload = [];
        foreach ($map as $inputKey => $dbKey) {
            if (array_key_exists($inputKey, $v)) {
                $payload[$dbKey] = $v[$inputKey];
            }
        }

        return $payload;
    }

    private function normalizeSchool(object $row): array
    {
        $data = (array) $row;
        $data['createdAt'] = optional($row->createdAt)->toISOString() ?? (string) $row->createdAt;
        return $data;
    }

    private function normalizeModelSchool(School $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
            'localityId' => $s->locality_id,
            'address' => $s->address,
            'latitude' => $s->latitude,
            'longitude' => $s->longitude,
            'serviceRadiusKm' => $s->service_radius_km,
            'phone' => $s->phone,
            'whatsapp' => $s->whatsapp,
            'email' => $s->email,
            'description' => $s->description,
            'imageUrl' => $s->image_url,
            'rating' => (float) $s->rating,
            'reviewCount' => (int) $s->review_count,
            'verified' => (bool) $s->verified,
            'hasPickup' => (bool) $s->has_pickup,
            'womenInstructor' => (bool) $s->women_instructor,
            'weekendClasses' => (bool) $s->weekend_classes,
            'vehicleTypes' => $s->vehicle_types ?? [],
            'transmission' => $s->transmission ?? [],
            'priceFrom' => (float) $s->price_from,
            'priceTo' => (float) $s->price_to,
            'timings' => $s->timings,
            'serviceAreas' => $s->service_areas ?? [],
            'createdAt' => $s->created_at?->toISOString(),
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }
}
