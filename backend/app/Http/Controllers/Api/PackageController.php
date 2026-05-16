<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DrivePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), ['schoolId' => ['nullable', 'integer']]);
        if ($validator->fails()) return response()->json(['error' => $validator->errors()->toJson()], 400);

        $query = DrivePackage::query();
        if (isset($validator->validated()['schoolId'])) {
            $query->where('school_id', $validator->validated()['schoolId']);
        }
        return response()->json($query->get()->map(fn ($p) => $this->normalize($p))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schoolId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric'],
            'sessions' => ['required', 'integer', 'min:1'],
            'vehicleType' => ['required', 'string', 'max:255'],
            'transmission' => ['required', 'string', 'max:255'],
            'hasPickup' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) return response()->json(['error' => $validator->errors()->toJson()], 400);

        $p = DrivePackage::create([
            'school_id' => $validator->validated()['schoolId'],
            'name' => $validator->validated()['name'],
            'description' => $validator->validated()['description'] ?? null,
            'price' => $validator->validated()['price'],
            'sessions' => $validator->validated()['sessions'],
            'vehicle_type' => $validator->validated()['vehicleType'],
            'transmission' => $validator->validated()['transmission'],
            'has_pickup' => $validator->validated()['hasPickup'] ?? false,
            'active' => $validator->validated()['active'] ?? true,
        ]);

        return response()->json($this->normalize($p), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = DrivePackage::query()->find($id);
        if (!$p) return response()->json(['error' => 'Package not found'], 404);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric'],
            'sessions' => ['sometimes', 'integer', 'min:1'],
            'vehicleType' => ['sometimes', 'string', 'max:255'],
            'transmission' => ['sometimes', 'string', 'max:255'],
            'hasPickup' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) return response()->json(['error' => 'Invalid input'], 400);

        $map = [
            'name' => 'name',
            'description' => 'description',
            'price' => 'price',
            'sessions' => 'sessions',
            'vehicleType' => 'vehicle_type',
            'transmission' => 'transmission',
            'hasPickup' => 'has_pickup',
            'active' => 'active',
        ];

        foreach ($validator->validated() as $k => $v) {
            $p->{$map[$k]} = $v;
        }

        $p->save();
        return response()->json($this->normalize($p));
    }

    public function delete(int $id): JsonResponse
    {
        DrivePackage::query()->where('id', $id)->delete();
        return response()->json(null, 204);
    }

    private function normalize(DrivePackage $p): array
    {
        return [
            'id' => $p->id,
            'schoolId' => $p->school_id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => (float) $p->price,
            'sessions' => (int) $p->sessions,
            'vehicleType' => $p->vehicle_type,
            'transmission' => $p->transmission,
            'hasPickup' => (bool) $p->has_pickup,
            'active' => (bool) $p->active,
        ];
    }
}
