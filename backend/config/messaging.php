<?php

use App\Messaging\Drivers\LogSender;
use App\Messaging\Drivers\NullSender;

/*
| WhatsApp / SMS messaging (DIQ-1001). Provider-neutral: pick a driver with
| MESSAGING_DRIVER. "log" writes the rendered text to the application log
| (dev/staging), "null" sends nothing. A real provider (MSG91, Gupshup,
| Twilio...) is added as a class implementing App\Messaging\MessageSender and
| registered under "drivers" below; see README "WhatsApp / SMS".
|
| Both WhatsApp Business and Indian SMS (TRAI DLT) only deliver pre-approved
| templates. Each template below keeps its text for rendering and logs, plus
| the ids the provider / DLT portal assign once approved. `vars` fixes the
| positional order providers expect ({{1}}, {{2}}...).
*/

return [
    'driver' => env('MESSAGING_DRIVER', 'log'),

    'drivers' => [
        'log' => LogSender::class,
        'null' => NullSender::class,
    ],

    // Also send an SMS when WhatsApp delivery fails (needs DLT templates).
    'sms_fallback' => (bool) env('MESSAGING_SMS_FALLBACK', false),

    'default_country_code' => env('MESSAGING_COUNTRY_CODE', '91'),

    'templates' => [
        'lead_new' => [
            'text' => 'New enquiry for {{school}}: {{name}}, {{details}}. Reply quickly: {{link}}',
            'vars' => ['school', 'name', 'details', 'link'],
            'provider_template_id' => env('MSG_TPL_LEAD_NEW'),
            'dlt_template_id' => env('DLT_TPL_LEAD_NEW'),
        ],
        'lead_reminder' => [
            'text' => 'Reminder: {{name}} enquired {{ago}} and has not been contacted yet. {{link}}',
            'vars' => ['name', 'ago', 'link'],
            'provider_template_id' => env('MSG_TPL_LEAD_REMINDER'),
            'dlt_template_id' => env('DLT_TPL_LEAD_REMINDER'),
        ],
        'enquiry_confirmation' => [
            'text' => 'Hi {{name}}, {{school}} has received your enquiry and will contact you soon. Chat with them: {{chat_link}}',
            'vars' => ['name', 'school', 'chat_link'],
            'provider_template_id' => env('MSG_TPL_ENQUIRY_CONFIRMATION'),
            'dlt_template_id' => env('DLT_TPL_ENQUIRY_CONFIRMATION'),
        ],
        'session_reminder' => [
            'text' => '{{title}}: driving session {{when}} with {{with}}.{{pickup}}',
            'vars' => ['title', 'when', 'with', 'pickup'],
            'provider_template_id' => env('MSG_TPL_SESSION_REMINDER'),
            'dlt_template_id' => env('DLT_TPL_SESSION_REMINDER'),
        ],
    ],

    // Outbound message log rows are deleted after this many days (DPDP).
    'log_retention_days' => 90,
];
