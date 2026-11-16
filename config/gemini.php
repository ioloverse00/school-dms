<?php

return [
    'enabled' => env('GEMINI_ENABLED', false),
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
    // One provider request, capped at 25 seconds; retries are explicit staff actions.
    'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 25),
    'cache_ttl_minutes' => (int) env('GEMINI_CACHE_TTL_MINUTES', 720),
];
