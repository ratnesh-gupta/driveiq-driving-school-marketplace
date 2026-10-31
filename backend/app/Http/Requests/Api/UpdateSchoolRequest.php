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
        'phoneVerified' => 'phone_verified',
        'businessVerified' => 'business_verified',
        'locationVerified' => 'location_verified',
        'premiumVerified' => 'premium_verified',
        'hasPickup' => 'has_pickup',
        'womenInstructor' => 'women_instructor',
        'weekendClasses' => 'weekend_classes',
        'vehicleTypes' => 'vehicle_types',
        'transmission' => 'transmission',
        'priceFrom' => 'price_from',
        'priceTo' => 'price_to',
        'timings' => 'timings',
        'serviceAreas' => 'service_areas',
        'languages' => 'languages',
        'batchTimings' => 'batch_timings',
        'pickupRadiusKm' => 'pickup_radius_km',
        'simulatorTraining' => 'simulator_training',
        'acVehicle' => 'ac_vehicle',
        'rtoAssistance' => 'rto_assistance',
        'establishedYear' => 'established_year',
        'totalVehicles' => 'total_vehicles',
        'totalInstructors' => 'total_instructors',
        'acceptedPayments' => 'accepted_payments',
        'cancellationPolicy' => 'cancellation_policy',
    ];

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:schools,slug,'.$this->route('id')],
            'localityId' => ['sometimes', 'integer', 'exists:localities,id'],
            'address' => ['sometimes', 'string', 'max:500'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'serviceRadiusKm' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'imageUrl' => ['nullable', 'url:https', 'max:2048'],
            'rating' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'reviewCount' => ['sometimes', 'integer', 'min:0'],
            'verified' => ['sometimes', 'boolean'],
            'phoneVerified' => ['sometimes', 'boolean'],
            'businessVerified' => ['sometimes', 'boolean'],
            'locationVerified' => ['sometimes', 'boolean'],
            'premiumVerified' => ['sometimes', 'boolean'],
            'hasPickup' => ['sometimes', 'boolean'],
            'womenInstructor' => ['sometimes', 'boolean'],
            'weekendClasses' => ['sometimes', 'boolean'],
            'vehicleTypes' => ['sometimes', 'array', 'max:20'],
            'vehicleTypes.*' => ['string', 'max:50'],
            'transmission' => ['sometimes', 'array', 'max:10'],
            'transmission.*' => ['string', 'max:50'],
            'priceFrom' => ['sometimes', 'numeric', 'min:0'],
            'priceTo' => ['sometimes', 'numeric', 'min:0'],
            'timings' => ['nullable', 'string', 'max:255'],
            'serviceAreas' => ['sometimes', 'array', 'max:50'],
            'serviceAreas.*' => ['string', 'max:100'],
            'languages' => ['sometimes', 'array', 'max:20'],
            'languages.*' => ['string', 'max:50'],
            'batchTimings' => ['sometimes', 'array', 'max:20'],
            'pickupRadiusKm' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'simulatorTraining' => ['sometimes', 'boolean'],
            'acVehicle' => ['sometimes', 'boolean'],
            'rtoAssistance' => ['sometimes', 'boolean'],
            'establishedYear' => ['nullable', 'string', 'max:4'],
            'totalVehicles' => ['nullable', 'integer', 'min:0'],
            'totalInstructors' => ['nullable', 'integer', 'min:0'],
            'acceptedPayments' => ['sometimes', 'array', 'max:20'],
            'acceptedPayments.*' => ['string', 'max:50'],
            'cancellationPolicy' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toSnakeCase(): array
    {
        $data = $this->camelToSnake($this->validated(), self::FIELD_MAP);

        // Only platform admins may set verification flags, the public slug,
        // or rating/review_count (otherwise derived from approved reviews).
        $user = $this->user();
        if (! $user || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            unset(
                $data['slug'],
                $data['rating'],
                $data['review_count'],
                $data['verified'],
                $data['phone_verified'],
                $data['business_verified'],
                $data['location_verified'],
                $data['premium_verified'],
            );
        }

        return $data;
    }
}
