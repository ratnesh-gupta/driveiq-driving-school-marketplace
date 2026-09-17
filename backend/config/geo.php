<?php

/**
 * Geo ranking configuration (Phase 1 + Phase 4 premium boost).
 *
 * rankingScore =
 *   w_distance * distanceScore
 * + w_rating * ratingScore
 * + w_reviews * reviewScore
 * + w_verified * verifiedBonus
 * + w_premium * premiumBoost   (0–1 from active subscription plan)
 */
return [
    'default_radius_km' => 5.0,
    'max_radius_km' => 50.0,

    'ranking' => [
        'weight_distance' => 0.40,
        'weight_rating' => 0.25,
        'weight_reviews' => 0.12,
        'weight_verified' => 0.08,
        'weight_premium' => 0.15,
        'review_cap' => 50,
    ],
];
