<?php

/*
 | Data retention (DIQ-606), following docs/DPDP-Compliance.md
 | "Indicative retention". Periods are in months. The driveiq:retention command
 | reports by default and only deletes with --execute (or RETENTION_EXECUTE=true
 | for the scheduled run).
 |
 | Not handled here, on purpose:
 |  - audit_logs: append-only (DB trigger); security record, never erased by this job
 |  - payments / invoices: 8 years or as tax law requires
 |  - training records (schedules, attendance, progress): 36 months after course
 |    end; needs a per-learner "course end" date first
 |  - accounts: erased through the data-request workflow, not by age
 */

return [
    'execute' => env('RETENTION_EXECUTE', false),

    // Schools whose data is under legal hold: nothing of theirs is deleted.
    'legal_hold_school_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('RETENTION_LEGAL_HOLD_SCHOOLS', ''))
    ))),

    'months' => [
        'inquiries' => 24,        // from last activity
        'messages' => 24,
        'contact_messages' => 24, // closed messages, from last update
        'data_subject_requests' => 36, // from closure
        'prospects' => 12,        // not on board, from last activity (DIQ-1103)
        'outreach_messages' => 12, // masked outreach email log (DIQ-1105)
    ],
];
