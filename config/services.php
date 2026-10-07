<?php

return [
    // WhatsApp gateway — Fonnte (https://fonnte.com/apidocs.php)
    'fonnte' => [
        'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
        'token' => env('FONNTE_TOKEN'),
        'enabled' => (bool) env('FONNTE_ENABLED', false),
    ],
];
