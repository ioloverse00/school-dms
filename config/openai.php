<?php

return [
    'local_resolve_ip' => env('OPENAI_LOCAL_RESOLVE_IP'),
    'forecasting_enabled' => env('OPENAI_FORECASTING_ENABLED', false),
    'holiday_suggestions_enabled' => env('OPENAI_HOLIDAY_SUGGESTIONS_ENABLED', false),
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
    'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 45),
    'cache_ttl_minutes' => (int) env('OPENAI_CACHE_TTL_MINUTES', 720),
];
