<?php

namespace App\Console\Commands;

use App\Services\DocumentTrendForecaster;
use Illuminate\Console\Command;

class ForecastDocumentTrends extends Command
{
    protected $signature = 'ai:forecast-documents
        {--history=24 : Number of history months}
        {--months=12 : Forecast horizon in months}
        {--expected_graduates= : Expected graduating students (number)}
        {--limit_types=0 : Limit document types included (0 = all)}
        {--notes= : Extra notes/context for the model}';

    protected $description = 'Use Gemini AI to forecast document request demand.';

    public function handle(DocumentTrendForecaster $forecaster): int
    {
        $history = (int) $this->option('history');
        $months = (int) $this->option('months');
        $expectedGraduates = $this->option('expected_graduates');
        $limitTypes = (int) $this->option('limit_types');
        $notes = $this->option('notes');

        $context = [
            'expected_graduates' => is_null($expectedGraduates) ? null : (int) $expectedGraduates,
            'limit_types' => $limitTypes > 0 ? $limitTypes : null,
            'notes' => $notes,
        ];

        try {
            $result = $forecaster->forecast($history, $months, $context);
        } catch (\Throwable $e) {
            $reference = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\Log::warning('Forecast command failed.', ['reference' => $reference] + \App\Services\ForecastFailure::diagnostics($e));
            $this->error('Forecast unavailable. Reference: '.$reference);
            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
