<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AiForecastCache;
use App\Services\DocumentTrendForecaster;
use App\Services\ForecastSummaryBuilder;
use App\Services\ForecastOutputValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiForecastController extends Controller
{
    public function __invoke(Request $request, DocumentTrendForecaster $forecaster, AiForecastCache $cache, ForecastSummaryBuilder $summaryBuilder)
    {
        $input = $request->validate([
            'history' => 'sometimes|integer|between:6,60', 'months' => 'sometimes|integer|between:1,36',
            'limit_types' => 'sometimes|integer|between:0,50', 'expected_graduates' => 'nullable|integer|between:0,1000000',
            'notes' => 'nullable|string|max:2000', 'prompt' => 'nullable|string|max:10000',
            'include_history' => 'sometimes|boolean', 'force' => 'sometimes|boolean', 'cache_only' => 'sometimes|boolean',
        ]);
        $history = (int) ($input['history'] ?? 24);
        $months = (int) ($input['months'] ?? 12);
        $limit = (int) ($input['limit_types'] ?? 0);
        $params = [
            'version' => 2, 'history' => $history, 'months' => $months, 'limit_types' => $limit,
            'expected_graduates' => isset($input['expected_graduates']) ? (int) $input['expected_graduates'] : null,
            'notes' => $input['notes'] ?? null,
            'custom_prompt' => trim($input['prompt'] ?? (string) SystemSetting::get('ai_forecast_prompt', '')),
            'start_month' => now()->startOfMonth()->format('Y-m'),
        ];
        $cached = $cache->get();
        $cachedParams = is_array($cached['params'] ?? null) ? $cached['params'] : [];
        $samePeriod = $cachedParams === $params;
        $compatible = collect($cachedParams)->except('start_month')->all() === collect($params)->except('start_month')->all();
        if (is_array($cached) && ($samePeriod || ($request->isMethod('get') && $compatible)) && is_array($cached['forecast'] ?? null)
            && is_array($cached['history']['per_document_type'] ?? null)) {
            try {
                $expectedMonths = ForecastOutputValidator::months($months);
                if (!$samePeriod && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $cachedParams['start_month'] ?? '')) {
                    $origin = \Carbon\Carbon::createFromFormat('!Y-m', $cachedParams['start_month']);
                    $expectedMonths = array_map(fn($i) => $origin->copy()->addMonths($i)->format('Y-m'), range(1, $months));
                }
                $validated = app(ForecastOutputValidator::class)->validate($cached['forecast'], $cached['history']['per_document_type'], $months, $expectedMonths);
                $validated['summary'] = $summaryBuilder->build($validated, $history);
                $payload = ['cached' => true, 'generated_at' => $cached['generated_at'], 'forecast' => $validated,
                    'stale' => !$samePeriod || !$cache->isFresh($cached, (int) config('gemini.cache_ttl_minutes', 720)),
                    'next_scheduled_at' => \App\Services\ForecastRefreshSchedule::next()];
                if ($request->boolean('include_history')) { $payload['history'] = $cached['history']; }
                if ($request->isMethod('get') || (!$request->boolean('force') && !$payload['stale'])) {
                    return response()->json($payload);
                }
            } catch (\Throwable) {
                // Invalid legacy/corrupt cache is never rendered or overwritten by a GET.
            }
        }
        if ($request->isMethod('get')) {
            return response()->json(['cached' => false, 'pending' => true, 'next_scheduled_at' => \App\Services\ForecastRefreshSchedule::next()], 202);
        }
        if (!config('gemini.enabled', false)) {
            return response()->json(['error' => true, 'message' => 'AI forecasting is disabled.'], 503);
        }
        $lock = Cache::lock('ai-forecast-generation', 180);
        if (!$lock->get()) {
            return response()->json(['error' => true, 'message' => 'A forecast is already being generated. Try again shortly.'], 409);
        }
        try {
            if (!Cache::add('ai-forecast-cooldown', true, 60)) {
                return response()->json(['error' => true, 'message' => 'Please wait before generating another forecast.'], 429)->header('Retry-After', '60');
            }
            $result = $forecaster->forecastWithHistory($history, $months, $params);
            $forecast = $result['forecast'];
            $forecast['summary'] = $summaryBuilder->build($forecast, $history);
            $historyPayload = $result['history'];
            $generatedAt = now()->toIso8601String();
            $cache->put(['generated_at' => $generatedAt, 'params' => $params, 'forecast' => $forecast, 'history' => $historyPayload]);
            $payload = ['cached' => false, 'stale' => false, 'generated_at' => $generatedAt, 'forecast' => $forecast, 'next_scheduled_at' => \App\Services\ForecastRefreshSchedule::next()];
            if ($request->boolean('include_history')) { $payload['history'] = $historyPayload; }
            return response()->json($payload);
        } catch (\Throwable $e) {
            $id = (string) Str::uuid();
            Log::warning('Forecast generation failed.', ['reference' => $id] + \App\Services\ForecastFailure::diagnostics($e));
            return response()->json(['error' => true, 'message' => 'Forecast unavailable. Try again later.', 'reference' => $id], 503);
        } finally {
            $lock->release();
        }
    }
}
