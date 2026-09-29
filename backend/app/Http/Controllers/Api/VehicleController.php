<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\DocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
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
        if ($deny = $this->access()->school($request, $schoolId)) {
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
        if ($deny = $this->access()->school($request, (int) $vehicle->school_id)) {
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

    public function listDocuments(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::withoutGlobalScope('school')->find($id);
        if (! $vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }
        if ($deny = $this->access()->school($request, (int) $vehicle->school_id)) {
            return $deny;
        }

        $docs = VehicleDocument::withoutGlobalScope('school')
            ->where('vehicle_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (VehicleDocument $d) => $this->serializeDoc($d));

        return response()->json($docs);
    }

    public function addDocument(Request $request, int $id, DocumentStorage $storage): JsonResponse
    {
        $vehicle = Vehicle::withoutGlobalScope('school')->find($id);
        if (! $vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }
        if ($deny = $this->access()->school($request, (int) $vehicle->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:registration,insurance,pollution,permit'],
            'file' => ['nullable', ...DocumentStorage::FILE_RULE],
            'expiryDate' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,valid,expired'],
        ]);

        $file = $request->file('file');
        $schoolId = (int) $vehicle->school_id;

        $doc = VehicleDocument::withoutGlobalScope('school')->create([
            'vehicle_id' => $vehicle->id,
            'school_id' => $schoolId,
            'type' => $data['type'],
            'file_path' => $file ? $storage->store($file, $schoolId, 'vehicles', $vehicle->id) : null,
            'file_name' => $file ? DocumentStorage::displayName($file) : null,
            'expiry_date' => $data['expiryDate'] ?? null,
            'status' => $data['status'] ?? 'pending',
        ]);

        return response()->json($this->serializeDoc($doc), 201);
    }

    private function serializeDoc(VehicleDocument $d): array
    {
        return [
            'id' => $d->id,
            'vehicleId' => $d->vehicle_id,
            'type' => $d->type,
            'hasFile' => $d->file_path !== null,
            'fileName' => $d->file_name,
            'expiryDate' => $d->expiry_date?->toDateString(),
            'status' => $d->status,
            'createdAt' => $d->created_at?->toISOString(),
        ];
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
}
