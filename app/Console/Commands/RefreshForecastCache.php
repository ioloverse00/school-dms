<?php

namespace App\Console\Commands;

use App\Services\{AiForecastCache, DocumentTrendForecaster, ForecastFailure, ForecastRefreshSchedule, ForecastSummaryBuilder};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log};
use Illuminate\Support\Str;

class RefreshForecastCache extends Command
{
    protected $signature = 'ai:refresh-forecast';
    protected $description = 'Generate and save the shared dashboard forecast for the scheduled refresh.';

    public function handle(DocumentTrendForecaster $forecaster, AiForecastCache $cache, ForecastSummaryBuilder $summary): int
    {
        if (!config('openai.forecasting_enabled') || !config('openai.api_key')) {
            $this->info('Skipped: forecasting is disabled or not configured.'); return self::SUCCESS;
        }
        $lock = Cache::lock('ai-forecast-generation', 180);
        if (!$lock->get()) { $this->warn('Skipped: generation is already running.'); return self::SUCCESS; }
        try {
            if (!Cache::add('ai-forecast-cooldown', true, 60)) { $this->warn('Skipped: generation cooldown is active.'); return self::SUCCESS; }
            $params = ForecastRefreshSchedule::parameters();
            $revision = app(\App\Services\AcademicCalendar::class)->get()['revision'];
            $result = $forecaster->forecastWithHistory(24, 12, $params);
            $forecast = $result['forecast']; $forecast['summary'] = $summary->build($forecast, 24);
            // If calendar settings changed during generation, keep the old result instead of blessing stale output.
            if ($revision !== app(\App\Services\AcademicCalendar::class)->get()['revision']) throw new \RuntimeException('Calendar changed during generation.');
            $cache->put(['generated_at' => now()->toIso8601String(), 'params' => $params, 'forecast' => $forecast, 'history' => $result['history']]);
            $this->info('Shared forecast refreshed successfully.'); return self::SUCCESS;
        } catch (\Throwable $exception) {
            $reference = (string) Str::uuid();
            Log::warning('Scheduled forecast refresh failed.', ['reference' => $reference] + ForecastFailure::diagnostics($exception));
            $this->error('Forecast refresh failed; previous result retained. Reference: '.$reference); return self::FAILURE;
        } finally { $lock->release(); }
    }
}
