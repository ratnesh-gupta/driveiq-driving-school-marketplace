<?php

/**
 * Phase 1 — Geo ranking configuration.
 *
 * rankingScore = w_distance * distanceScore
 *              + w_rating * ratingScore
 *              + w_reviews * reviewScore
 *              + w_verified * verifiedBonus
 *
 * distanceScore: linear decay from 1 (at 0km) to 0 (at radiusKm)
 * ratingScore: rating / 5
 * reviewScore: min(review_count / review_cap, 1)
 * verifiedBonus: 1 if verified else 0
 */
return [
    'default_radius_km' => 5.0,
    'max_radius_km' => 50.0,

    'ranking' => [
        'weight_distance' => 0.45,
        'weight_rating' => 0.30,
        'weight_reviews' => 0.15,
        'weight_verified' => 0.10,
        'review_cap' => 50,
    ],
];
