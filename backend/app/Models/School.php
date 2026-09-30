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

    /**
     * Column defaults, mirrored so a new model computes the same profile
     * completeness before and after it is reloaded from the database.
     */
    protected $attributes = [
        'has_pickup' => false,
        'women_instructor' => false,
        'weekend_classes' => false,
        'simulator_training' => false,
        'ac_vehicle' => false,
        'rto_assistance' => true,
    ];

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

    /** Profile fields that count towards completeness, with the label shown to schools. */
    private const PROFILE_FIELDS = [
        'name' => 'name', 'phone' => 'phone', 'email' => 'email', 'description' => 'description',
        'address' => 'address', 'timings' => 'timings', 'image_url' => 'photo',
        'vehicle_types' => 'vehicle types', 'transmission' => 'transmission', 'price_from' => 'starting price',
        'languages' => 'languages', 'batch_timings' => 'batch timings', 'service_areas' => 'service areas',
        'established_year' => 'established year', 'total_vehicles' => 'number of vehicles',
        'total_instructors' => 'number of trainers', 'accepted_payments' => 'payment methods',
    ];

    private const FEATURE_FLAGS = [
        'has_pickup', 'women_instructor', 'weekend_classes',
        'simulator_training', 'ac_vehicle', 'rto_assistance',
    ];

    public function calculateProfileCompleteness(): int
    {
        // The feature flags count as one item (see missingProfileFields()).
        $total = count(self::PROFILE_FIELDS) + 1;

        return (int) round((($total - count($this->missingProfileFields())) / $total) * 100);
    }

    /**
     * Labels of what is still missing from the public profile. The feature
     * flags default to false, so "unanswered" and "no" look the same; they
     * count as one "features" item, filled once any feature is ticked.
     *
     * @return list<string>
     */
    public function missingProfileFields(): array
    {
        $missing = [];
        foreach (self::PROFILE_FIELDS as $field => $label) {
            $value = $this->getAttribute($field);
            // Numeric 0 is a column default (e.g. price_from), not a filled-in answer.
            $isZero = (is_int($value) || is_float($value)) && $value == 0;
            if (is_null($value) || $value === '' || $value === [] || $isZero) {
                $missing[] = $label;
            }
        }
        if (! collect(self::FEATURE_FLAGS)->contains(fn ($f) => (bool) $this->getAttribute($f))) {
            $missing[] = 'features';
        }

        return $missing;
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
