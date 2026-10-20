<?php

/*
 | School and trainer outreach email (DIQ-1105).
 |
 | OUTREACH_MAILER picks the Laravel mailer: "log" (default) writes emails to
 | the log; set it to "outreach" once the OUTREACH_MAIL_* SMTP settings point
 | at the outreach mailbox (e.g. Google Workspace with an app password or
 | the SMTP relay). The From address should be on a subdomain with its own
 | SPF, DKIM and DMARC records (see README "Outreach email").
 */

return [
    'mailer' => env('OUTREACH_MAILER', 'log'),

    'from' => [
        'address' => env('OUTREACH_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'name' => env('OUTREACH_FROM_NAME', 'DriveQ Partnerships'),
    ],
    'reply_to' => env('OUTREACH_REPLY_TO'),

    // Shown in every email footer: who is writing and from where.
    'postal_address' => env('OUTREACH_POSTAL_ADDRESS', 'DriveQ, Pune, Maharashtra, India'),

    // Never more than this many outreach emails a day (Workspace allows
    // about 2,000; staying well below protects the domain's reputation).
    'daily_cap' => (int) env('OUTREACH_DAILY_CAP', 150),

    // Sent only on these days and hours, local time.
    'timezone' => 'Asia/Kolkata',
    'days' => [1, 2, 3, 4, 5, 6], // ISO weekdays: Monday to Saturday
    'start_hour' => (int) env('OUTREACH_START_HOUR', 10),
    'end_hour' => (int) env('OUTREACH_END_HOUR', 18),

    'max_steps' => 3,
];
