<?php

/*
 | Platform billing (DIQ-803): what DriveQ invoices schools for plans, and
 | how they pay while collection is manual (UPI / bank transfer, recorded by
 | an admin). Fill these from the real business details before charging.
 */

return [
    'gst_rate' => (float) env('BILLING_GST_RATE', 18),
    'due_days' => (int) env('BILLING_DUE_DAYS', 7),
    'term_months' => [1, 3, 6, 12],

    'seller' => [
        'name' => env('BILLING_SELLER_NAME', 'DriveQ'),
        'address' => env('BILLING_SELLER_ADDRESS', 'Pune, Maharashtra, India'),
        'gstin' => env('BILLING_GSTIN'),
        'email' => env('BILLING_EMAIL', 'billing@driveq.in'),
    ],

    'payment' => [
        'upi_id' => env('BILLING_UPI_ID'),
        'bank_name' => env('BILLING_BANK_NAME'),
        'account_name' => env('BILLING_BANK_ACCOUNT_NAME'),
        'account_number' => env('BILLING_BANK_ACCOUNT_NUMBER'),
        'ifsc' => env('BILLING_BANK_IFSC'),
    ],
];
