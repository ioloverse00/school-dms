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
        return $this->forecastWithHistory($historyMonths, $forecastMonths, $context)['forecast'];
    }

    public function forecastWithHistory(int $historyMonths = 24, int $forecastMonths = 12, array $context = []): array
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
        $calendar = app(AcademicCalendar::class)->get();
        $calendarFrom = now()->startOfMonth()->subMonths($historyMonths - 1)->toDateString();
        $calendarTo = now()->startOfMonth()->addMonths($forecastMonths)->endOfMonth()->toDateString();
        $context['academic_calendar'] = ['revision' => $calendar['revision']];
        foreach (['years', 'terms', 'events'] as $bucket) {
            $context['academic_calendar'][$bucket] = collect($calendar[$bucket])
                ->filter(fn ($item) => $item['starts_on'] <= $calendarTo && $item['ends_on'] >= $calendarFrom)
                ->values()->all();
        }

        $prompt = $this->buildPrompt($series, $perTypeSeries, $forecastMonths, $context);

        $output = $this->gemini->generateJson($prompt, [
            'temperature' => 0.1,
            'response_schema' => ForecastResponseSchema::make($forecastMonths, count($perTypeSeries)),
        ]);
        // Aggregate totals are arithmetic, not a second independent AI prediction.
        $rows = $output['per_document_type'] ?? null;
        $totals = array_fill(0, $forecastMonths, 0);
        $canAggregate = is_array($rows) && array_is_list($rows) && count($rows) === count($perTypeSeries);
        $overall = $output['overall_forecast'] ?? null;
        if (!is_array($overall) || !array_is_list($overall) || count($overall) !== $forecastMonths) $canAggregate = false;
        foreach (is_array($overall) ? $overall : [] as $value) {
            if (!is_int($value) || $value < 0 || $value > 1000000) $canAggregate = false;
        }
        foreach (is_array($rows) ? $rows : [] as $row) {
            $values = is_array($row) ? ($row['forecast'] ?? null) : null;
            if (!is_array($values) || !array_is_list($values) || count($values) !== $forecastMonths) { $canAggregate = false; break; }
            foreach ($values as $i => $value) {
                if (!is_int($value) || $value < 0 || $value > 1000000) { $canAggregate = false; break 2; }
                $totals[$i] += $value;
            }
        }
        if ($canAggregate && is_array($output['volume_forecast'] ?? null)) {
            $output['overall_forecast'] = $totals;
            $output['volume_forecast']['total_requests'] = array_sum($totals);
        }
        return [
            'forecast' => app(ForecastOutputValidator::class)->validate($output, $perTypeSeries, $forecastMonths, ForecastOutputValidator::months($forecastMonths)),
            'history' => ['history_months' => $historyMonths, 'overall' => $series, 'per_document_type' => $perTypeSeries],
        ];
    }

    private function buildMonthlySeries(int $historyMonths): array
    {
        $months = collect();
        for ($i = $historyMonths - 1; $i >= 0; $i--) {
            $months->push(Carbon::now()->startOfMonth()->subMonths($i));
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
            ->where('created_at', '>=', Carbon::now()->startOfMonth()->subMonths($historyMonths - 1))
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
            $months->push(Carbon::now()->startOfMonth()->subMonths($i));
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
            ->where('created_at', '>=', Carbon::now()->startOfMonth()->subMonths($historyMonths - 1))
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
            'Use the saved academic_calendar in the context for actual academic years, semesters, enrollment, exams, graduation and office closures. Archived calendar entries remain historical context. If dates are missing, disclose that limitation rather than invent school dates. Consider post-graduation demand for alumni records.',
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
            '- All forecasts must be non-negative integers, at most 1000000 per month.',
            '- Overall monthly totals MUST equal the sum of per-document-type forecasts; volume total MUST equal their sum.',
            'Exact required future months: ' . json_encode(ForecastOutputValidator::months($forecastMonths)),
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
