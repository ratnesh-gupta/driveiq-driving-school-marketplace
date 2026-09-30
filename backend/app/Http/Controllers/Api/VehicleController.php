<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\DocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class VehicleController extends Controller
{
    /** Insurance, PUC and permits are flagged this many days before they lapse. */
    private const EXPIRY_WARNING_DAYS = 30;

    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $vehicles = Vehicle::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->orderBy('registration_number')
            ->get();

        // Per vehicle: documents already expired, or expiring within 30 days.
        $docs = VehicleDocument::withoutGlobalScope('school')
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(self::EXPIRY_WARNING_DAYS)->toDateString())
            ->get(['vehicle_id', 'expiry_date'])
            ->groupBy('vehicle_id');

        $items = $vehicles->map(fn (Vehicle $v) => $this->serialize($v) + [
            'expiredDocuments' => ($docs[$v->id] ?? collect())->filter(fn ($d) => $d->expiry_date->lt(today()))->count(),
            'expiringDocuments' => ($docs[$v->id] ?? collect())->filter(fn ($d) => $d->expiry_date->gte(today()))->count(),
        ]);

        return response()->json($items);
    }

    public function store(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $this->normalizeRegistration($request);
        $data = $request->validate([
            'registrationNumber' => ['required', 'string', 'max:32', $this->uniqueRegistration($schoolId)],
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

        $this->normalizeRegistration($request);
        $data = $request->validate([
            'registrationNumber' => ['sometimes', 'string', 'max:32', $this->uniqueRegistration((int) $vehicle->school_id, $vehicle->id)],
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

        $old = $vehicle->only(array_keys($payload));
        $vehicle->fill($payload)->save();

        AuditLog::log('update', 'Vehicle', $vehicle->id, $old, $payload, (int) $vehicle->school_id);

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

    /** Registrations are stored upper-case, so compare them that way too. */
    private function normalizeRegistration(Request $request): void
    {
        if (is_string($request->input('registrationNumber'))) {
            $request->merge(['registrationNumber' => strtoupper(trim($request->input('registrationNumber')))]);
        }
    }

    private function uniqueRegistration(int $schoolId, ?int $ignoreId = null): Unique
    {
        return Rule::unique('vehicles', 'registration_number')->where('school_id', $schoolId)->ignore($ignoreId);
    }

    /**
     * Vehicle papers are not reviewed like learner documents: their status
     * follows the expiry date, so an expired PUC shows as expired without
     * anyone having to change it.
     */
    private function serializeDoc(VehicleDocument $d): array
    {
        $expiry = $d->expiry_date;
        $status = match (true) {
            $expiry !== null && $expiry->lt(today()) => 'expired',
            $d->file_path !== null => 'valid',
            default => 'pending',
        };

        return [
            'id' => $d->id,
            'vehicleId' => $d->vehicle_id,
            'type' => $d->type,
            'hasFile' => $d->file_path !== null,
            'fileName' => $d->file_name,
            'expiryDate' => $expiry?->toDateString(),
            'status' => $status,
            'expiringSoon' => $status !== 'expired' && $expiry !== null && $expiry->lte(today()->addDays(self::EXPIRY_WARNING_DAYS)),
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
