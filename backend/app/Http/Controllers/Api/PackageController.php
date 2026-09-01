<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePackageRequest;
use App\Http\Requests\Api\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\AuditLog;
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

        // Public listing is cross-school.
        $query = DrivePackage::withoutGlobalScope('school');

        if ($request->filled('schoolId')) {
            $query->where('school_id', $request->input('schoolId'));
        }

        return response()->json(PackageResource::collection($query->get()));
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $data = $request->toSnakeCase();
        $user = $request->user();

        // School role may only create packages for their own school.
        if ($user && $user->isSchool() && $user->school_id) {
            $data['school_id'] = $user->school_id;
        }

        $package = DrivePackage::withoutGlobalScope('school')->create($data);

        AuditLog::log('create', 'DrivePackage', $package->id, [], $package->toArray());

        return response()->json(new PackageResource($package), 201);
    }

    public function update(UpdatePackageRequest $request, int $id): JsonResponse
    {
        $package = DrivePackage::find($id);

        if (! $package) {
            return response()->json(['message' => 'Package not found'], 404);
        }

        $oldValues = $package->toArray();
        $package->fill($request->toSnakeCase());
        $package->save();

        AuditLog::log('update', 'DrivePackage', $package->id, $oldValues, $package->toArray());

        return response()->json(new PackageResource($package));
    }

    public function delete(int $id): JsonResponse
    {
        $package = DrivePackage::find($id);

        if (! $package) {
            return response()->json(['message' => 'Package not found'], 404);
        }

        AuditLog::log('delete', 'DrivePackage', $package->id, $package->toArray(), []);
        $package->delete();

        return response()->json(null, 204);
    }
}
