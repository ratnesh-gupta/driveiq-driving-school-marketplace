<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authSchool($request, $schoolId)) {
            return $deny;
        }

        $items = Vehicle::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->orderBy('registration_number')
            ->get()
            ->map(fn (Vehicle $v) => $this->serialize($v));

        return response()->json($items);
    }

    public function store(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authSchool($request, $schoolId)) {
            return $deny;
        }

        $data = $request->validate([
            'registrationNumber' => ['required', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'in:car,bike,scooter,heavy'],
            'transmission' => ['nullable', 'string', 'in:manual,automatic'],
            'fuelType' => ['nullable', 'string', 'in:petrol,diesel,electric,cng'],
            'status' => ['nullable', 'string', 'in:active,maintenance,retired'],
            'makeModel' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $vehicle = Vehicle::withoutGlobalScope('school')->create([
            'school_id' => $schoolId,
            'registration_number' => strtoupper($data['registrationNumber']),
            'type' => $data['type'] ?? 'car',
            'transmission' => $data['transmission'] ?? null,
            'fuel_type' => $data['fuelType'] ?? null,
            'status' => $data['status'] ?? 'active',
            'make_model' => $data['makeModel'] ?? null,
            'year' => $data['year'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::log('create', 'Vehicle', $vehicle->id, [], ['registration' => $vehicle->registration_number]);

        return response()->json($this->serialize($vehicle), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::withoutGlobalScope('school')->find($id);
        if (! $vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }
        if ($deny = $this->authSchool($request, (int) $vehicle->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'registrationNumber' => ['sometimes', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'in:car,bike,scooter,heavy'],
            'transmission' => ['nullable', 'string', 'in:manual,automatic'],
            'fuelType' => ['nullable', 'string', 'in:petrol,diesel,electric,cng'],
            'status' => ['sometimes', 'string', 'in:active,maintenance,retired'],
            'makeModel' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $map = [
            'registrationNumber' => 'registration_number',
            'type' => 'type',
            'transmission' => 'transmission',
            'fuelType' => 'fuel_type',
            'status' => 'status',
            'makeModel' => 'make_model',
            'year' => 'year',
            'notes' => 'notes',
        ];

        $payload = [];
        foreach ($map as $c => $s) {
            if (array_key_exists($c, $data)) {
                $payload[$s] = $c === 'registrationNumber' ? strtoupper($data[$c]) : $data[$c];
            }
        }

        $vehicle->fill($payload)->save();

        return response()->json($this->serialize($vehicle));
    }

    public function addDocument(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::withoutGlobalScope('school')->find($id);
        if (! $vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }
        if ($deny = $this->authSchool($request, (int) $vehicle->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:registration,insurance,pollution,permit'],
            'filePath' => ['nullable', 'string', 'max:2048'],
            'fileName' => ['nullable', 'string', 'max:255'],
            'expiryDate' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,valid,expired'],
        ]);

        $doc = VehicleDocument::withoutGlobalScope('school')->create([
            'vehicle_id' => $vehicle->id,
            'school_id' => $vehicle->school_id,
            'type' => $data['type'],
            'file_path' => $data['filePath'] ?? null,
            'file_name' => $data['fileName'] ?? null,
            'expiry_date' => $data['expiryDate'] ?? null,
            'status' => $data['status'] ?? 'pending',
        ]);

        return response()->json([
            'id' => $doc->id,
            'type' => $doc->type,
            'filePath' => $doc->file_path,
            'expiryDate' => $doc->expiry_date?->toDateString(),
            'status' => $doc->status,
        ], 201);
    }

    private function serialize(Vehicle $v): array
    {
        return [
            'id' => $v->id,
            'schoolId' => $v->school_id,
            'registrationNumber' => $v->registration_number,
            'type' => $v->type,
            'transmission' => $v->transmission,
            'fuelType' => $v->fuel_type,
            'status' => $v->status,
            'makeModel' => $v->make_model,
            'year' => $v->year,
            'notes' => $v->notes,
        ];
    }

    private function authSchool(Request $request, int $schoolId): ?JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }
        $user = $request->user();
        if ($user->isAdmin() || ($user->isSchool() && (int) $user->school_id === $schoolId)) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
