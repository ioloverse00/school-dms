<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiForecastCache;
use App\Services\DocumentTrendForecaster;
use App\Services\ForecastSummaryBuilder;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class AiForecastController extends Controller
{
    public function __invoke(Request $request, DocumentTrendForecaster $forecaster, AiForecastCache $cache, ForecastSummaryBuilder $summaryBuilder)
    {
        $history = (int) $request->integer('history', 24);
        $months = (int) $request->integer('months', 12);
        $expectedGraduates = $request->integer('expected_graduates');
        $limitTypes = (int) $request->integer('limit_types', 0);
        $includeHistory = (bool) $request->boolean('include_history', false);
        $force = (bool) $request->boolean('force', false);
        $cacheOnly = (bool) $request->boolean('cache_only', false);
        $notes = $request->string('notes')->toString();

        $ttl = (int) config('gemini.cache_ttl_minutes', 720);
        $cached = $cache->get();
        $customPrompt = trim((string) SystemSetting::get('ai_forecast_prompt', ''));
        $promptOverride = trim((string) $request->string('prompt')->toString());
        $effectivePrompt = $promptOverride !== '' ? $promptOverride : $customPrompt;
        $params = [
            'history' => $history,
            'months' => $months,
            'expected_graduates' => $expectedGraduates ?: null,
            'limit_types' => $limitTypes > 0 ? $limitTypes : null,
            'notes' => $notes !== '' ? $notes : null,
            'custom_prompt' => $effectivePrompt !== '' ? $effectivePrompt : null,
        ];

        $canUseCache = !$force
            && $cache->isFresh($cached, $ttl)
            && is_array($cached['params'] ?? null)
            && ($cached['params'] === $params)
            && is_array($cached['forecast'] ?? null)
            && is_array($cached['history'] ?? null);

        if ($canUseCache) {
            $cachedForecast = $cached['forecast'];
            if (is_array($cachedForecast) && !is_array($cachedForecast['summary'] ?? null)) {
                $cachedForecast['summary'] = $summaryBuilder->build($cachedForecast, $history);
                $cached['forecast'] = $cachedForecast;
                $cache->put($cached);
            }

            $payload = [
                'cached' => true,
                'generated_at' => $cached['generated_at'] ?? null,
            ];
            if ($includeHistory) {
                $payload['history'] = $cached['history'];
            }
            $payload['forecast'] = $cachedForecast ?? $cached['forecast'];
            return response()->json($payload);
        }

        if ($cacheOnly) {
            return response()->json([
                'cached' => false,
                'pending' => true,
            ], 202);
        }

        try {
            $forecast = $forecaster->forecast($history, $months, [
                'expected_graduates' => $expectedGraduates ?: null,
                'limit_types' => $limitTypes > 0 ? $limitTypes : null,
                'notes' => $notes !== '' ? $notes : null,
                'custom_prompt' => $effectivePrompt !== '' ? $effectivePrompt : null,
            ]);

            if (is_array($forecast)) {
                $forecast['summary'] = $summaryBuilder->build($forecast, $history);
            }

            $historyPayload = [
                'history_months' => $history,
                'per_document_type' => $forecaster->perDocumentTypeHistory($history, $limitTypes),
            ];

            $cache->put([
                'generated_at' => now()->toIso8601String(),
                'params' => $params,
                'forecast' => $forecast,
                'history' => $historyPayload,
            ]);
        } catch (\Throwable $e) {
            $status = str_contains($e->getMessage(), '(503)') ? 503 : 500;
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], $status);
        }

        $payload = [
            'cached' => false,
            'generated_at' => now()->toIso8601String(),
            'forecast' => $forecast,
        ];
        if ($includeHistory) {
            $payload['history'] = $historyPayload;
        }
        return response()->json($payload);
    }
}
