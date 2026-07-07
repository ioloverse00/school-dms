<?php

return [
    'enabled' => env('GEMINI_ENABLED', false),
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    'fallback_models' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_FALLBACK_MODELS', ''))))),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
    'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 30),
    'retries' => (int) env('GEMINI_RETRIES', 2),
    'cache_ttl_minutes' => (int) env('GEMINI_CACHE_TTL_MINUTES', 720),
];
