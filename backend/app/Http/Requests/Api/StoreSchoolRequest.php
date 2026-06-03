<?php

namespace App\Http\Requests\Api;

class StoreSchoolRequest extends BaseFormRequest
{
    private const FIELD_MAP = [
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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:schools,slug'],
            'localityId' => ['required', 'integer', 'exists:localities,id'],
            'address' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'serviceRadiusKm' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'phone' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'reviewCount' => ['nullable', 'integer', 'min:0'],
            'verified' => ['nullable', 'boolean'],
            'hasPickup' => ['nullable', 'boolean'],
            'womenInstructor' => ['nullable', 'boolean'],
            'weekendClasses' => ['nullable', 'boolean'],
            'vehicleTypes' => ['nullable', 'array'],
            'vehicleTypes.*' => ['string'],
            'transmission' => ['nullable', 'array'],
            'transmission.*' => ['string'],
            'priceFrom' => ['nullable', 'numeric', 'min:0'],
            'priceTo' => ['nullable', 'numeric', 'min:0'],
            'timings' => ['nullable', 'string', 'max:255'],
            'serviceAreas' => ['nullable', 'array'],
            'serviceAreas.*' => ['string'],
        ];
    }

    public function toSnakeCase(): array
    {
        return $this->camelToSnake($this->validated(), self::FIELD_MAP);
    }
}
