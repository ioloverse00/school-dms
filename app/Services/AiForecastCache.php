<?php

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\Carbon;

class AiForecastCache
{
    private const KEY = 'ai_document_forecast_cache';

    public function get(): ?array
    {
        $cached = SystemSetting::get(self::KEY);
        return is_array($cached) ? $cached : null;
    }

    public function put(array $payload): void
    {
        SystemSetting::set(self::KEY, $payload);
    }

    public function isFresh(?array $cached, int $ttlMinutes): bool
    {
        if (!$cached) {
            return false;
        }
        $generatedAt = $cached['generated_at'] ?? null;
        if (!is_string($generatedAt) || $generatedAt === '') {
            return false;
        }

        try {
            $ts = Carbon::parse($generatedAt);
        } catch (\Throwable) {
            return false;
        }

        return $ts->greaterThanOrEqualTo(Carbon::now()->subMinutes(max(1, $ttlMinutes)));
    }
}

