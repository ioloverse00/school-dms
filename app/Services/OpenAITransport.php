<?php

namespace App\Services;

class OpenAITransport
{
    public static function options(): array
    {
        // Optional local DNS workaround. HTTPS still verifies api.openai.com's certificate.
        $ip = config('openai.local_resolve_ip');
        if (!app()->environment('local') || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return [];
        return ['curl' => [CURLOPT_RESOLVE => ['api.openai.com:443:'.$ip]]];
    }
}
