<?php

return [
    'name' => env('APP_NAME', 'Mei Bali Ops API'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),

    /** First origin of FRONTEND_URL (it may be a comma-separated CORS list): where links in emails point. */
    // Alamat frontend (bukan API) untuk tautan di email, mis. /login dan /reset-password. Ambil entri pertama FRONTEND_URL.
    'frontend_url' => trim(explode(',', env('FRONTEND_URL', 'http://localhost:5173'))[0]),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
		'aliases' => [
			'Curl' => Ixudra\Curl\Facades\Curl::class,
			'Auth' => Illuminate\Support\Facades\Auth::class,
			'NEWPDF1' => Spatie\LaravelPdf\Facades\Pdf::class,
		],
];
