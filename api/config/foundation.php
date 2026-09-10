<?php

return [
    'password' => [
        'min_length' => env('FOUNDATION_PASSWORD_MIN_LENGTH', 12),
        'mixed_case' => (bool) env('FOUNDATION_PASSWORD_MIXED_CASE', true),
        'numbers' => (bool) env('FOUNDATION_PASSWORD_NUMBERS', true),
        'symbols' => (bool) env('FOUNDATION_PASSWORD_SYMBOLS', true),
    ],
];
