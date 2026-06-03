<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'localityId' => $this->locality_id,
            'localityName' => $this->whenLoaded('locality', fn () => $this->locality->name),
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'serviceRadiusKm' => $this->service_radius_km,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'description' => $this->description,
            'imageUrl' => $this->image_url,
            'rating' => (float) ($this->rating ?? 0),
            'reviewCount' => (int) ($this->review_count ?? 0),
            'verified' => (bool) $this->verified,
            'hasPickup' => (bool) $this->has_pickup,
            'womenInstructor' => (bool) $this->women_instructor,
            'weekendClasses' => (bool) $this->weekend_classes,
            'vehicleTypes' => $this->vehicle_types ?? [],
            'transmission' => $this->transmission ?? [],
            'priceFrom' => (float) ($this->price_from ?? 0),
            'priceTo' => (float) ($this->price_to ?? 0),
            'timings' => $this->timings,
            'serviceAreas' => $this->service_areas ?? [],
            'distanceKm' => $this->when(isset($this->distance_km), fn () => round((float) $this->distance_km, 2)),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
