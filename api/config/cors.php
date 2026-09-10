<?php

$csv = static function (string|array|null $value, array $default = []): array {
    $items = is_array($value) ? $value : explode(',', (string) ($value ?? implode(',', $default)));

    return array_values(array_filter(
        array_map(static fn (mixed $item): string => trim((string) $item), $items),
        static fn (string $item): bool => $item !== ''
    ));
};

$allowedOrigins = array_values(array_filter(
    $csv(env('CORS_ALLOWED_ORIGINS'), [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ]),
    static fn (string $origin): bool => $origin !== '*'
));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | CORS allowed origins are browser Origin values and therefore must include
    | the scheme. Because the SPA uses credentialed Sanctum session cookies,
    | wildcard origins are intentionally filtered out and credentials are on.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN', 'Accept', 'Origin', 'Authorization'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => true,

];
