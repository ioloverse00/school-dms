<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentTrendForecaster
{
    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    public function perDocumentTypeHistory(int $historyMonths = 24, int $limitTypes = 0): array
    {
        $historyMonths = max(6, min(60, $historyMonths));
        return $this->buildPerDocumentTypeSeries($historyMonths, $limitTypes);
    }

    public function forecast(int $historyMonths = 24, int $forecastMonths = 12, array $context = []): array
    {
        $historyMonths = max(6, min(60, $historyMonths));
        $forecastMonths = max(1, min(36, $forecastMonths));

        $series = $this->buildMonthlySeries($historyMonths);
        $perTypeSeries = $this->buildPerDocumentTypeSeries($historyMonths, (int) ($context['limit_types'] ?? 0));

        $historyTotal = array_sum(array_map('intval', $series['values'] ?? []));
        $nonZeroMonths = count(array_filter(array_map('intval', $series['values'] ?? []), fn($v) => $v > 0));
        $insufficientHistory = $historyTotal < 10 || $nonZeroMonths < 2;

        $context['history_total_requests'] = $historyTotal;
        $context['history_nonzero_months'] = $nonZeroMonths;
        $context['insufficient_history'] = $insufficientHistory;

        $prompt = $this->buildPrompt($series, $perTypeSeries, $forecastMonths, $context);

        return $this->gemini->generateJson($prompt, [
            'temperature' => 0.1,
        ]);
    }

    private function buildMonthlySeries(int $historyMonths): array
    {
        $months = collect();
        for ($i = $historyMonths - 1; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i)->startOfMonth());
        }

        $driver = DB::connection()->getDriverName();
        $yearExpr = $driver === 'sqlite'
            ? "CAST(strftime('%Y', created_at) AS INTEGER)"
            : 'YEAR(created_at)';
        $monthExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';

        $raw = DocumentRequest::select(
                DB::raw($yearExpr . ' as year'),
                DB::raw($monthExpr . ' as month'),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths($historyMonths)->startOfMonth())
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn($r) => $r->year . '-' . $r->month);

        $labels = $months->map(fn($m) => $m->format('Y-m'))->values()->toArray();
        $values = $months->map(fn($m) => (int) (optional($raw->get($m->year . '-' . $m->month))->total ?? 0))->values()->toArray();

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function buildPerDocumentTypeSeries(int $historyMonths, int $limitTypes = 0): array
    {
        $months = collect();
        for ($i = $historyMonths - 1; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i)->startOfMonth());
        }

        $labels = $months->map(fn($m) => $m->format('Y-m'))->values()->toArray();

        $driver = DB::connection()->getDriverName();
        $yearExpr = $driver === 'sqlite'
            ? "CAST(strftime('%Y', created_at) AS INTEGER)"
            : 'YEAR(created_at)';
        $monthExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';

        $raw = DocumentRequest::select(
                'document_type_id',
                DB::raw($yearExpr . ' as year'),
                DB::raw($monthExpr . ' as month'),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths($historyMonths)->startOfMonth())
            ->groupBy('document_type_id', 'year', 'month')
            ->get();

        $rawByType = $raw->groupBy('document_type_id');

        $typesQuery = DocumentType::query()->orderBy('id');
        if ($limitTypes > 0) {
            $typesQuery->limit(min(50, $limitTypes));
        }
        $types = $typesQuery->get();

        return $types->map(function (DocumentType $type) use ($labels, $months, $rawByType) {
            $keyed = ($rawByType->get($type->id) ?? collect())
                ->keyBy(fn($r) => $r->year . '-' . $r->month);

            $values = $months->map(function ($m) use ($keyed) {
                return (int) (optional($keyed->get($m->year . '-' . $m->month))->total ?? 0);
            })->values()->toArray();

            return [
                'document_type_id' => (int) $type->id,
                'name' => $type->name,
                'category' => $type->category,
                'history' => [
                    'labels' => $labels,
                    'values' => $values,
                ],
            ];
        })->values()->toArray();
    }

    private function buildPrompt(array $series, array $perTypeSeries, int $forecastMonths, array $context): string
    {
        $context = array_filter($context, fn($v) => $v !== null && $v !== '');
        $customPrompt = isset($context['custom_prompt']) && is_string($context['custom_prompt'])
            ? trim($context['custom_prompt'])
            : '';
        if ($customPrompt !== '') {
            $context['custom_prompt'] = $customPrompt;
        }

        $schemaHint = [
            'forecast_months' => $forecastMonths,
            'months' => array_fill(0, $forecastMonths, 'YYYY-MM'),
            'overall_forecast' => array_fill(0, $forecastMonths, 0),
            'per_document_type' => [
                [
                    'document_type_id' => 0,
                    'name' => 'string',
                    'forecast' => array_fill(0, $forecastMonths, 0),
                ],
            ],
            // Narrative fields (keep these concise; still return JSON only)
            'volume_forecast' => [
                'period' => 'YYYY-MM..YYYY-MM',
                'total_requests' => 0,
                'notes' => ['string'],
            ],
            'peak_period_analysis' => [
                'upcoming_peaks_30_60_days' => ['string'],
                'historical_peaks' => ['string'],
                'why_peaks_happen' => ['string'],
            ],
            'top_documents' => [
                [
                    'document_type_id' => 0,
                    'name' => 'string',
                    'forecast_share' => 0.0,
                    'notes' => 'string',
                ],
            ],
            'processing_workload' => [
                'notes' => ['string'],
            ],
            'staffing_recommendations' => [
                'normal_period' => ['string'],
                'peak_period' => ['string'],
            ],
            'insights' => ['string'],
            'assumptions' => ['string'],
        ];

        $lines = [
            'You are a school registrar analytics assistant. Your job is to analyze document request data from a school registrar\'s office and produce a clear operational forecast.',
            '',
            'The school year runs from June 15 to the first week of April the following year. Use this calendar to interpret seasonal patterns.',
            '',
            'Task: Forecast the next ' . $forecastMonths . ' months of demand and provide operational notes (peaks, workload, staffing).',
            'Return ONLY valid JSON (no markdown, no extra text, no code fences).',
            '',
            (!empty($context['insufficient_history']))
                ? 'NOTE: Historical request data is sparse/insufficient. Prioritize the custom instructions and produce conservative baseline forecasts.'
                : null,
            '',
            'Historical monthly total requests (labels are YYYY-MM):',
            json_encode($series, JSON_UNESCAPED_SLASHES),
            '',
            'Per-document-type historical monthly requests (labels are YYYY-MM):',
            json_encode($perTypeSeries, JSON_UNESCAPED_SLASHES),
            '',
            'Extra context (may affect demand; use if helpful):',
            json_encode($context, JSON_UNESCAPED_SLASHES),
            $customPrompt !== '' ? '' : null,
            $customPrompt !== '' ? 'Custom instructions (highest priority):' : null,
            $customPrompt !== '' ? $customPrompt : null,
            '',
            'Output JSON must match this shape (keys may have extra fields, but MUST include these):',
            json_encode($schemaHint, JSON_UNESCAPED_SLASHES),
            '',
            'Rules:',
            '- All forecasts must be non-negative integers.',
            '- months must be a list of the next ' . $forecastMonths . ' months in YYYY-MM format.',
            '- If context indicates a graduating batch, increase demand for graduation-related documents.',
            '- per_document_type MUST include one entry for every document_type_id provided in the per-document-type history.',
            (!empty($context['insufficient_history']))
                ? '- Because history is insufficient, keep totals small and explain assumptions in insights/assumptions; use seasonal rules to shape spikes.'
                : null,
            '- Keep forecasts realistic relative to recent history.',
            '- Write narrative strings in clear, professional Filipino-English (Taglish is acceptable for notes/suggestions).',
            '- Always present narrative content in this order: Volume → Peak Periods → Top Documents → Workload → Staffing (use the JSON keys in that order).',
        ];

        $lines = array_values(array_filter($lines, fn($line) => $line !== null));
        return implode("\n", $lines);
    }
}
