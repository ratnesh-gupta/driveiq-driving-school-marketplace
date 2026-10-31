<?php

/*
 | Data retention (DIQ-606), following docs/DPDP-Compliance.md
 | "Indicative retention". Periods are in months. The driveiq:retention command
 | reports by default and only deletes with --execute (or RETENTION_EXECUTE=true
 | for the scheduled run).
 |
 | WP-8 (BSEC-14) added: in-app notifications, training records, inactive learner
 | accounts, payments/invoices, consent IP/user-agent minimisation and tokens of
 | deactivated users. A window of 0 switches that job off. Everything still
 | reports only unless --execute / RETENTION_EXECUTE=true.
 |
 | Not handled here, on purpose:
 |  - audit_logs: append-only (DB trigger); a security record kept under the
 |    legitimate-use / legal-obligation basis. It must not hold contact details:
 |    new entries log hashes, not e-mails (see docs/DPDP-Compliance.md)
 |  - platform_invoices (DriveQ's own sales invoices): kept for accounting review
 |  - accounts are anonymised (App\Services\ErasureService), never hard deleted
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

        // WP-8. Env-overridable; 0 disables the job.
        'notifications' => (int) env('RETENTION_MONTHS_NOTIFICATIONS', 6),
        // Schedules, attendance, progress, driving tests, assignment history of
        // learners who are no longer "active", from the learner's last update.
        'training_records' => (int) env('RETENTION_MONTHS_TRAINING_RECORDS', 36),
        // Learner accounts with no login / change for this long are anonymised.
        'accounts_inactive' => (int) env('RETENTION_MONTHS_ACCOUNTS_INACTIVE', 36),
        // Learner payments and their invoices: 8 years (tax records).
        'payments' => (int) env('RETENTION_MONTHS_PAYMENTS', 96),
        // IP address / user agent on consent rows are dropped (the consent stays).
        'consent_details' => (int) env('RETENTION_MONTHS_CONSENT_DETAILS', 12),
    ],
];
