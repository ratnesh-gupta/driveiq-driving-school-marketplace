<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schoolId' => $this->school_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'sessions' => (int) $this->sessions,
            'vehicleType' => $this->vehicle_type,
            'transmission' => $this->transmission,
            'hasPickup' => (bool) $this->has_pickup,
            'active' => (bool) $this->active,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
