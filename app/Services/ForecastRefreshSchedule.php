<?php

namespace App\Services;

use App\Models\SystemSetting;

class ForecastRefreshSchedule
{
    public static function parameters(): array
    {
        return ['version' => 2, 'history' => 24, 'months' => 12, 'limit_types' => 0,
            'expected_graduates' => null, 'notes' => null,
            'custom_prompt' => trim((string) SystemSetting::get('ai_forecast_prompt', '')),
            'start_month' => now()->startOfMonth()->format('Y-m')];
    }

    public static function next(): string
    {
        $now = now(); $midnight = $now->copy()->startOfDay(); $noon = $midnight->copy()->addHours(12);
        return ($now->lt($noon) ? $noon : $midnight->addDay())->toIso8601String();
    }
}
