<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePackageRequest;
use App\Http\Requests\Api\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\DrivePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'schoolId' => ['nullable', 'integer'],
        ]);

        $query = DrivePackage::query();

        if ($request->filled('schoolId')) {
            $query->where('school_id', $request->input('schoolId'));
        }

        return response()->json(PackageResource::collection($query->get()));
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = DrivePackage::create($request->toSnakeCase());

        return response()->json(new PackageResource($package), 201);
    }

    public function update(UpdatePackageRequest $request, int $id): JsonResponse
    {
        $package = DrivePackage::find($id);

        if (! $package) {
            return response()->json(['message' => 'Package not found'], 404);
        }

        $package->fill($request->toSnakeCase());
        $package->save();

        return response()->json(new PackageResource($package));
    }

    public function delete(int $id): JsonResponse
    {
        $package = DrivePackage::find($id);

        if (! $package) {
            return response()->json(['message' => 'Package not found'], 404);
        }

        $package->delete();

        return response()->json(null, 204);
    }
}
