<?php

/*
 | Plan entitlements (DIQ-802). The single source of truth for which school
 | features each plan unlocks. Marketplace visibility (ranking boost,
 | sponsored, homepage) lives on the plans table / config/geo.php.
 |
 | When a school lacks a feature, reads stay allowed (its data is never
 | locked away) and writes get 402 plan_required, except features listed in
 | gate_reads, which are premium views rather than the school's own data.
 */

$premium = ['learners', 'documents', 'instructors', 'schedules', 'vehicles', 'payments', 'analytics_advanced'];

return [
    // Kill switch for environments that are not charging yet.
    'enforce' => env('PLANS_ENFORCE', true),

    // Every school starts with this many days of the trial plan's features
    // (features only: a trial never gets paid visibility).
    'trial_days' => (int) env('PLAN_TRIAL_DAYS', 30),
    'trial_plan' => 'premium',

    'features' => [
        'basic' => [],
        'featured' => ['learners', 'documents'],
        'premium' => $premium,
        'enterprise' => $premium,
    ],

    // Cheapest plan first; used to tell a school what to upgrade to.
    'upgrade_order' => ['featured', 'premium', 'enterprise'],

    'gate_reads' => ['analytics_advanced'],

    // Route URI (as Laravel reports it) => feature.
    'routes' => [
        // Featured
        'api/schools/{id}/learners' => 'learners',
        'api/learners/{id}' => 'learners',
        'api/learners/{id}/assign' => 'learners',
        'api/inquiries/{id}/convert' => 'learners',
        'api/learners/{id}/progress' => 'learners',
        'api/learners/{id}/driving-tests' => 'learners',
        'api/driving-tests/{id}' => 'learners',
        'api/learners/{id}/documents' => 'documents',
        'api/learners/{id}/documents/{docId}' => 'documents',

        // Premium
        'api/schools/{id}/instructors' => 'instructors',
        'api/instructors/{id}' => 'instructors',
        'api/instructors/{id}/documents' => 'instructors',
        'api/instructors/{id}/login' => 'instructors',
        'api/instructors/{id}/documents/{docId}' => 'instructors',
        'api/schools/{id}/vehicles' => 'vehicles',
        'api/vehicles/{id}' => 'vehicles',
        'api/vehicles/{id}/documents' => 'vehicles',
        'api/schools/{id}/schedules' => 'schedules',
        'api/schedules/{id}' => 'schedules',
        'api/schedules/{id}/attendance' => 'schedules',
        'api/schools/{id}/leave-requests' => 'schedules',
        'api/leave-requests/{id}' => 'schedules',
        'api/schools/{id}/payments' => 'payments',
        'api/schools/{id}/payments/package' => 'payments',
        'api/payments/{id}/mark-paid' => 'payments',
        'api/payments/{id}/mark-failed' => 'payments',
        'api/schools/{id}/analytics' => 'analytics_advanced',
        'api/schools/{id}/analytics/instructors' => 'analytics_advanced',
    ],
];
