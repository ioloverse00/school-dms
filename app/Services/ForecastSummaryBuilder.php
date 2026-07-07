<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ForecastSummaryBuilder
{
    public function build(array $forecast, int $historyMonths = 24): array
    {
        $months = array_values(array_filter($forecast['months'] ?? [], fn ($m) => is_string($m) && $m !== ''));
        $overall = array_map(fn ($v) => max(0, (int) $v), is_array($forecast['overall_forecast'] ?? null) ? $forecast['overall_forecast'] : []);

        if (count($months) !== count($overall)) {
            $len = min(count($months), count($overall));
            $months = array_slice($months, 0, $len);
            $overall = array_slice($overall, 0, $len);
        }

        $totalFutureRequests = array_sum($overall);
        $peakMonths = $this->topNByValue($months, $overall, 3);
        $avgMonthlyForecast = count($overall) > 0 ? ($totalFutureRequests / max(1, count($overall))) : 0.0;

        $topDocTypes = $this->topDocumentTypes($forecast, 5);
        $periodStats = $this->historicalPeakPeriods($historyMonths);

        $avgMinutesPerRequest = $this->intSetting('forecast_avg_minutes_per_request', (int) config('forecasting.avg_minutes_per_request', 10), 1, 240);
        $productiveHoursPerStaffPerDay = $this->floatSetting('forecast_productive_hours_per_staff_per_day', (float) config('forecasting.productive_hours_per_staff_per_day', 6.0), 1.0, 24.0);
        $peakDayMultiplier = $this->floatSetting('forecast_peak_day_multiplier', (float) config('forecasting.peak_day_multiplier', 1.4), 1.0, 5.0);
        $businessDaysOnly = $this->boolSetting('forecast_business_days_only', (bool) config('forecasting.business_days_only', true));

        $workload = $this->workloadAndStaffing(
            $peakMonths[0]['month'] ?? null,
            $peakMonths[0]['requests'] ?? null,
            $totalFutureRequests,
            $avgMinutesPerRequest,
            $productiveHoursPerStaffPerDay,
            $peakDayMultiplier,
            $businessDaysOnly
        );

        $staffingRecommendations = $this->staffingRecommendationsByMonth(
            $months,
            $overall,
            $avgMonthlyForecast,
            $avgMinutesPerRequest,
            $productiveHoursPerStaffPerDay,
            $peakDayMultiplier,
            $businessDaysOnly
        );

        return [
            'future_requests_total' => (int) $totalFutureRequests,
            'peak_request_periods' => [
                'months' => $peakMonths,
                'weekdays' => $periodStats['weekdays'],
                'hours' => $periodStats['hours'],
            ],
            'most_requested_documents' => $topDocTypes,
            'expected_processing_workload' => $workload['workload'],
            'staffing_needs' => array_merge($workload['staffing'], [
                'monthly_surge_threshold_ratio' => 1.3,
                'monthly_surge_recommendations' => $staffingRecommendations,
            ]),
            'assumptions' => $workload['assumptions'],
        ];
    }

    private function topDocumentTypes(array $forecast, int $limit): array
    {
        $rows = is_array($forecast['per_document_type'] ?? null) ? $forecast['per_document_type'] : [];
        $totals = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['document_type_id']) ? (int) $row['document_type_id'] : null;
            $name = isset($row['name']) && is_string($row['name']) ? $row['name'] : null;
            $forecastVals = is_array($row['forecast'] ?? null) ? $row['forecast'] : [];

            if ($id === null || $id <= 0) {
                continue;
            }

            $sum = 0;
            foreach ($forecastVals as $v) {
                $sum += max(0, (int) $v);
            }

            $totals[] = [
                'document_type_id' => $id,
                'name' => $name ?? ('Document Type #' . $id),
                'forecast_total' => (int) $sum,
            ];
        }

        usort($totals, fn ($a, $b) => ($b['forecast_total'] <=> $a['forecast_total']) ?: ($a['document_type_id'] <=> $b['document_type_id']));
        return array_slice($totals, 0, max(1, min(25, $limit)));
    }

    private function historicalPeakPeriods(int $historyMonths): array
    {
        $historyMonths = max(1, min(60, $historyMonths));
        $since = Carbon::now()->subMonths($historyMonths)->startOfMonth();

        $driver = DB::connection()->getDriverName();
        $weekdayExpr = $driver === 'sqlite'
            ? "CAST(strftime('%w', created_at) AS INTEGER)" // 0=Sun..6=Sat
            : 'DAYOFWEEK(created_at)'; // 1=Sun..7=Sat (MySQL)
        $hourExpr = $driver === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)" // 00..23
            : 'HOUR(created_at)';

        $weekdayRaw = DocumentRequest::query()
            ->select(DB::raw($weekdayExpr . ' as wk'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('wk')
            ->get();

        $hourRaw = DocumentRequest::query()
            ->select(DB::raw($hourExpr . ' as hr'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('hr')
            ->get();

        $weekdayMap = [
            0 => 'Sun',
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
        ];

        $wkTotals = [];
        $wkSum = 0;
        foreach ($weekdayRaw as $row) {
            $wk = (int) ($row->wk ?? 0);
            if ($driver !== 'sqlite') {
                $wk = ($wk + 5) % 7; // convert MySQL 1..7 (Sun..Sat) to 0..6
            }
            $count = (int) ($row->total ?? 0);
            $wkSum += $count;
            $wkTotals[$wk] = ($wkTotals[$wk] ?? 0) + $count;
        }

        $hrTotals = [];
        $hrSum = 0;
        foreach ($hourRaw as $row) {
            $hr = (int) ($row->hr ?? 0);
            $count = (int) ($row->total ?? 0);
            $hrSum += $count;
            $hrTotals[$hr] = ($hrTotals[$hr] ?? 0) + $count;
        }

        $weekdays = [];
        foreach ($wkTotals as $wk => $count) {
            $weekdays[] = [
                'weekday' => $weekdayMap[$wk] ?? (string) $wk,
                'requests' => (int) $count,
                'share' => $wkSum > 0 ? round($count / $wkSum, 4) : 0.0,
            ];
        }
        usort($weekdays, fn ($a, $b) => $b['requests'] <=> $a['requests']);
        $weekdays = array_slice($weekdays, 0, 3);

        $hours = [];
        foreach ($hrTotals as $hr => $count) {
            $hours[] = [
                'hour' => (int) $hr,
                'requests' => (int) $count,
                'share' => $hrSum > 0 ? round($count / $hrSum, 4) : 0.0,
            ];
        }
        usort($hours, fn ($a, $b) => $b['requests'] <=> $a['requests']);
        $hours = array_slice($hours, 0, 5);

        return [
            'weekdays' => $weekdays,
            'hours' => $hours,
        ];
    }

    private function workloadAndStaffing(
        ?string $peakMonth,
        ?int $peakMonthRequests,
        int $totalFutureRequests,
        int $avgMinutesPerRequest,
        float $productiveHoursPerStaffPerDay,
        float $peakDayMultiplier,
        bool $businessDaysOnly
    ): array {
        $totalMinutes = $totalFutureRequests * $avgMinutesPerRequest;
        $totalHours = $totalMinutes / 60.0;

        $peakDay = null;
        $staffNeeded = null;
        $productivityMinutes = (int) round($productiveHoursPerStaffPerDay * 60);

        if (is_string($peakMonth) && $peakMonth !== '' && is_int($peakMonthRequests) && $peakMonthRequests >= 0) {
            try {
                $monthStart = Carbon::createFromFormat('Y-m', $peakMonth)->startOfMonth();
                $daysInMonth = $businessDaysOnly
                    ? $this->countBusinessDaysInMonth($monthStart)
                    : (int) $monthStart->daysInMonth;
                $daysInMonth = max(1, $daysInMonth);

                $avgPerDay = $peakMonthRequests / $daysInMonth;
                $estPeakDayRequests = (int) ceil($avgPerDay * $peakDayMultiplier);

                $peakDay = [
                    'month' => $peakMonth,
                    'estimated_requests' => $estPeakDayRequests,
                    'estimated_minutes' => $estPeakDayRequests * $avgMinutesPerRequest,
                ];
                $staffNeeded = (int) ceil(($peakDay['estimated_minutes'] ?? 0) / max(1, $productivityMinutes));
            } catch (\Throwable) {
                // ignore parsing issues
            }
        }

        return [
            'workload' => [
                'avg_minutes_per_request' => $avgMinutesPerRequest,
                'total_minutes' => (int) $totalMinutes,
                'total_hours' => round($totalHours, 1),
                'peak_day' => $peakDay,
            ],
            'staffing' => [
                'productive_hours_per_staff_per_day' => round($productiveHoursPerStaffPerDay, 2),
                'estimated_staff_needed_on_peak_day' => $staffNeeded,
            ],
            'assumptions' => [
                'peak_day_multiplier' => round($peakDayMultiplier, 2),
                'business_days_only' => $businessDaysOnly,
            ],
        ];
    }

    private function staffingRecommendationsByMonth(
        array $months,
        array $overall,
        float $avgMonthlyForecast,
        int $avgMinutesPerRequest,
        float $productiveHoursPerStaffPerDay,
        float $peakDayMultiplier,
        bool $businessDaysOnly
    ): array {
        if (count($months) === 0 || count($overall) === 0) {
            return [];
        }

        $thresholdRatio = 1.3;
        $threshold = (int) ceil($avgMonthlyForecast * $thresholdRatio);

        $productivityMinutes = (int) round(max(1.0, $productiveHoursPerStaffPerDay) * 60);
        $baselineMonthRequests = (int) round($avgMonthlyForecast);
        $baselineStaff = $this->estimatePeakDayStaffForMonth(
            null,
            $baselineMonthRequests,
            $avgMinutesPerRequest,
            $peakDayMultiplier,
            $businessDaysOnly,
            $productivityMinutes
        );

        $recs = [];
        for ($i = 0; $i < min(count($months), count($overall)); $i++) {
            $m = (string) $months[$i];
            $req = (int) $overall[$i];
            if ($req < $threshold) {
                continue;
            }

            $staff = $this->estimatePeakDayStaffForMonth(
                $m,
                $req,
                $avgMinutesPerRequest,
                $peakDayMultiplier,
                $businessDaysOnly,
                $productivityMinutes
            );

            $extra = null;
            if (is_int($staff) && is_int($baselineStaff)) {
                $extra = max(1, $staff - $baselineStaff);
            }

            $recs[] = [
                'month' => $m,
                'forecast_requests' => $req,
                'baseline_monthly_average' => (int) round($avgMonthlyForecast),
                'surge_ratio' => $avgMonthlyForecast > 0 ? round($req / $avgMonthlyForecast, 2) : null,
                'estimated_staff_needed_on_peak_day' => $staff,
                'extra_personnel_recommended' => $extra,
                'message' => $extra !== null
                    ? 'Additional releasing staff recommended during ' . $m . ' — consider assigning ' . $extra . ' extra personnel.'
                    : 'Additional releasing staff recommended during ' . $m . '.',
            ];
        }

        usort($recs, fn ($a, $b) => (($b['forecast_requests'] ?? 0) <=> ($a['forecast_requests'] ?? 0)) ?: strcmp((string) ($a['month'] ?? ''), (string) ($b['month'] ?? '')));
        return array_slice($recs, 0, 6);
    }

    private function estimatePeakDayStaffForMonth(
        ?string $month,
        int $monthRequests,
        int $avgMinutesPerRequest,
        float $peakDayMultiplier,
        bool $businessDaysOnly,
        int $productivityMinutes
    ): ?int {
        if ($monthRequests < 0) {
            return null;
        }

        try {
            $monthStart = null;
            if (is_string($month) && $month !== '') {
                $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            }

            $daysInMonth = $monthStart
                ? ($businessDaysOnly ? $this->countBusinessDaysInMonth($monthStart) : (int) $monthStart->daysInMonth)
                : 22; // reasonable default business days
            $daysInMonth = max(1, (int) $daysInMonth);

            $avgPerDay = $monthRequests / $daysInMonth;
            $estPeakDayRequests = (int) ceil($avgPerDay * $peakDayMultiplier);
            $estPeakMinutes = $estPeakDayRequests * $avgMinutesPerRequest;

            return (int) ceil($estPeakMinutes / max(1, $productivityMinutes));
        } catch (\Throwable) {
            return null;
        }
    }

    private function countBusinessDaysInMonth(Carbon $monthStart): int
    {
        $start = $monthStart->copy()->startOfMonth();
        $end = $monthStart->copy()->endOfMonth();
        $count = 0;
        for ($d = $start; $d->lessThanOrEqualTo($end); $d->addDay()) {
            if ($d->isWeekday()) {
                $count++;
            }
        }
        return $count;
    }

    private function topNByValue(array $labels, array $values, int $n): array
    {
        $pairs = [];
        for ($i = 0; $i < min(count($labels), count($values)); $i++) {
            $pairs[] = ['month' => (string) $labels[$i], 'requests' => (int) $values[$i]];
        }
        usort($pairs, fn ($a, $b) => ($b['requests'] <=> $a['requests']) ?: strcmp($a['month'], $b['month']));
        return array_slice($pairs, 0, max(1, min(12, $n)));
    }

    private function intSetting(string $key, int $default, int $min, int $max): int
    {
        $v = SystemSetting::get($key, $default);
        $i = (int) $v;
        return max($min, min($max, $i));
    }

    private function floatSetting(string $key, float $default, float $min, float $max): float
    {
        $v = SystemSetting::get($key, $default);
        $f = (float) $v;
        return max($min, min($max, $f));
    }

    private function boolSetting(string $key, bool $default): bool
    {
        $v = SystemSetting::get($key, $default);
        return (bool) $v;
    }
}
