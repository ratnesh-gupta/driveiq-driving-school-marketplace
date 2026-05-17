<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'locality_id', 'address', 'latitude', 'longitude', 'service_radius_km',
        'phone', 'whatsapp', 'email', 'description', 'image_url', 'rating', 'review_count',
        'verified', 'has_pickup', 'women_instructor', 'weekend_classes', 'vehicle_types',
        'transmission', 'price_from', 'price_to', 'timings', 'service_areas',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'has_pickup' => 'boolean',
            'women_instructor' => 'boolean',
            'weekend_classes' => 'boolean',
            'vehicle_types' => 'array',
            'transmission' => 'array',
            'service_areas' => 'array',
            'rating' => 'float',
            'price_from' => 'float',
            'price_to' => 'float',
            'review_count' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'service_radius_km' => 'float',
        ];
    }
}
