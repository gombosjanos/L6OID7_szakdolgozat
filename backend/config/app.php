<?php

return [

    'name' => env('Kertigép Szervíz', 'Laravel'),
    'env' => env('szakdoga_env', 'production'),
    'debug' => (bool) env('szakdoga_debug', false),
    'url' => env('kertigepsz_url', 'http://localhost'),
    'frontend_url' => env('KERTIGEPSZ_FRONTEND_URL', env('APP_URL', 'http://localhost')),
    'timezone' => 'UTC',
    'locale' => env('KERTIGEPSZ_LOCALE', 'hu'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'hu'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'hu_HU'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
