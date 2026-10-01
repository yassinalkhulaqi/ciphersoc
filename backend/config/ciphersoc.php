<?php

return [
    'name' => 'cipherSOC',
    'version' => '1.0.0',
    'retention' => [
        'events_days' => env('CIPHERSOC_EVENTS_RETENTION_DAYS', 90),
        'audit_days' => env('CIPHERSOC_AUDIT_RETENTION_DAYS', 365),
        'reports_days' => env('CIPHERSOC_REPORTS_RETENTION_DAYS', 365),
    ],
];
