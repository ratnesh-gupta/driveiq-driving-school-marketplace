<?php

namespace App\Models;

use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'slug', 'locality_id', 'address', 'latitude', 'longitude', 'service_radius_km',
        'phone', 'whatsapp', 'email', 'description', 'image_url', 'rating', 'review_count',
        'verified', 'phone_verified', 'business_verified', 'location_verified', 'premium_verified',
        'has_pickup', 'women_instructor', 'weekend_classes', 'vehicle_types',
        'transmission', 'price_from', 'price_to', 'timings', 'service_areas',
        'languages', 'batch_timings', 'pickup_radius_km', 'simulator_training',
        'ac_vehicle', 'rto_assistance', 'established_year', 'total_vehicles',
        'total_instructors', 'accepted_payments', 'cancellation_policy', 'profile_completeness',
    ];

    /** PostGIS geography, derived from latitude/longitude by a DB trigger. */
    protected $hidden = ['location'];

    protected function casts(): array
    {
        return [
            // Computed by SchoolService search queries.
            'distance_km' => 'float',
            'ranking_score' => 'float',
            'plan_boost' => 'float',
            'is_sponsored' => 'boolean',
            'homepage_featured' => 'boolean',
            'top_placement' => 'boolean',
            'verified' => 'boolean',
            'phone_verified' => 'boolean',
            'business_verified' => 'boolean',
            'location_verified' => 'boolean',
            'premium_verified' => 'boolean',
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
            'languages' => 'array',
            'batch_timings' => 'array',
            'pickup_radius_km' => 'float',
            'simulator_training' => 'boolean',
            'ac_vehicle' => 'boolean',
            'rto_assistance' => 'boolean',
            'accepted_payments' => 'array',
            'total_vehicles' => 'integer',
            'total_instructors' => 'integer',
            'profile_completeness' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (School $school) {
            $school->profile_completeness = $school->calculateProfileCompleteness();
        });

        // Every new school starts on a feature trial (DIQ-802), however it was
        // created (registration, admin, seeder).
        static::created(fn (School $school) => app(SubscriptionService::class)->startTrial($school->id));
    }

    /**
     * Rating and review count are derived from approved reviews only;
     * they are never accepted as input from a school.
     */
    public function recalculateRating(): void
    {
        $approved = Review::withoutGlobalScope('school')
            ->where('school_id', $this->id)
            ->where('approved', true);

        $this->update([
            'rating' => round((float) ($approved->clone()->avg('rating') ?? 0), 1),
            'review_count' => $approved->count(),
        ]);
    }

    public function calculateProfileCompleteness(): int
    {
        $fields = [
            'name', 'phone', 'email', 'description', 'address', 'timings',
            'image_url', 'vehicle_types', 'transmission', 'price_from',
            'languages', 'batch_timings', 'service_areas',
            'established_year', 'total_vehicles', 'total_instructors',
            'accepted_payments',
        ];
        $booleanFields = [
            'has_pickup', 'women_instructor', 'weekend_classes',
            'simulator_training', 'ac_vehicle', 'rto_assistance',
        ];

        $filled = 0;
        $total = count($fields) + count($booleanFields);

        foreach ($fields as $field) {
            $value = $this->getAttribute($field);
            // Numeric 0 is a column default (e.g. price_from), not a filled-in answer.
            $isZero = (is_int($value) || is_float($value)) && $value == 0;
            if (! is_null($value) && $value !== '' && $value !== [] && ! $isZero) {
                $filled++;
            }
        }
        $filled += count($booleanFields);

        return (int) round(($filled / $total) * 100);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(DrivePackage::class);
    }

    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 5.0)
    {
        $haversine = sprintf(
            '(6371 * acos(cos(radians(%s)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%s)) + sin(radians(%s)) * sin(radians(latitude))))',
            $lat, $lng, $lat
        );

        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("{$haversine} as distance_km")
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km');
    }
}
