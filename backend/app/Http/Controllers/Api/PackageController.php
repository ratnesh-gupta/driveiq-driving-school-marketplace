<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePackageRequest;
use App\Http\Requests\Api\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\AuditLog;
use App\Models\DrivePackage;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'schoolId' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        // Cross-school read, so scoping is explicit (BSEC-09): the public sees active
        // packages of published listings only; the school's own staff and admins see all.
        $query = DrivePackage::withoutGlobalScope('school')->orderBy('id');
        $user = $request->user('sanctum');
        $schoolId = $request->filled('schoolId') ? (int) $request->input('schoolId') : null;
        $privileged = $user && ($user->isAdmin() || ($schoolId !== null && (int) $user->school_id === $schoolId && $user->isSchool()));

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        if (! $privileged) {
            $query->where('active', true)
                ->whereIn('school_id', School::query()->public()->select('schools.id'));
        }

        $total = (clone $query)->count();
        $max = $privileged ? 1000 : 100;
        $limit = min((int) $request->input('limit', $privileged ? $max : 50), $max);
        $items = $query->offset((int) $request->input('offset', 0))->limit($limit)->get();

        return response()->json(PackageResource::collection($items))->header('X-Total-Count', (string) $total);
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $data = $request->toSnakeCase();
        $user = $request->user();

        // School role may only create packages for their own school (BSEC-19): the
        // client's schoolId is ignored for them and a school role with no school is refused.
        if ($user->isSchool()) {
            $data['school_id'] = $user->school_id;
        }

        if ($deny = $this->access()->school($request, (int) ($data['school_id'] ?? 0))) {
            return $deny;
        }

        $package = DrivePackage::withoutGlobalScope('school')->create($data);

        AuditLog::log('create', 'DrivePackage', $package->id, [], $package->toArray(), (int) $package->school_id);

        return response()->json(new PackageResource($package), 201);
    }

    public function update(UpdatePackageRequest $request, int $id): JsonResponse
    {
        $package = DrivePackage::withoutGlobalScope('school')->find($id);

        // Same answer for "no such package" and "another school's package".
        if (! $package || ($request->user()->isSchool() && (int) $request->user()->school_id !== (int) $package->school_id)) {
            return response()->json(['message' => 'Package not found'], 404);
        }

        if ($deny = $this->access()->school($request, (int) $package->school_id)) {
            return $deny;
        }

        $oldValues = $package->toArray();
        $package->fill($request->toSnakeCase());
        $package->save();

        AuditLog::log('update', 'DrivePackage', $package->id, $oldValues, $package->toArray(), (int) $package->school_id);

        return response()->json(new PackageResource($package));
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        $package = DrivePackage::find($id);

        if (! $package) {
            return response()->json(['message' => 'Package not found'], 404);
        }

        // PBAC: deleting packages is owner-only (managers may create/edit).
        if ($deny = $this->access()->school($request, (int) $package->school_id, ownerOnly: true)) {
            return $deny;
        }

        AuditLog::log('delete', 'DrivePackage', $package->id, $package->toArray(), []);
        $package->delete();

        return response()->json(null, 204);
    }
}
