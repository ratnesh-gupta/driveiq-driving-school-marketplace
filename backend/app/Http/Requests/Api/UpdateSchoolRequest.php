<?php

namespace App\Http\Requests\Api;

class UpdateSchoolRequest extends BaseFormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:schools,slug,' . $this->route('school')],
            'localityId' => ['sometimes', 'integer', 'exists:localities,id'],
            'address' => ['sometimes', 'string'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'serviceRadiusKm' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
            'rating' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'reviewCount' => ['sometimes', 'integer', 'min:0'],
            'verified' => ['sometimes', 'boolean'],
            'hasPickup' => ['sometimes', 'boolean'],
            'womenInstructor' => ['sometimes', 'boolean'],
            'weekendClasses' => ['sometimes', 'boolean'],
            'vehicleTypes' => ['sometimes', 'array'],
            'vehicleTypes.*' => ['string'],
            'transmission' => ['sometimes', 'array'],
            'transmission.*' => ['string'],
            'priceFrom' => ['sometimes', 'numeric', 'min:0'],
            'priceTo' => ['sometimes', 'numeric', 'min:0'],
            'timings' => ['nullable', 'string', 'max:255'],
            'serviceAreas' => ['sometimes', 'array'],
            'serviceAreas.*' => ['string'],
        ];
    }

    public function toSnakeCase(): array
    {
        return $this->camelToSnake($this->validated(), self::FIELD_MAP);
    }
}
