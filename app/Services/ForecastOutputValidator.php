<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;


class ForecastOutputValidator
{
    public function validate(array $data, array $types, int $horizon, array $months): array
    {
        $rules = [
            'forecast_months' => 'required|integer|in:'.$horizon,
            'months' => 'present|array|size:'.$horizon,
            'months.*' => 'required|string|date_format:Y-m',
            'overall_forecast' => 'present|array|size:'.$horizon,
            'overall_forecast.*' => 'required|integer|between:0,1000000',
            'per_document_type' => 'present|array|size:'.count($types),
            'per_document_type.*.document_type_id' => 'required|integer',
            'per_document_type.*.name' => 'required|string|max:255',
            'per_document_type.*.forecast' => 'present|array|size:'.$horizon,
            'per_document_type.*.forecast.*' => 'required|integer|between:0,1000000',
            'volume_forecast.period' => 'required|string|max:100',
            'volume_forecast.total_requests' => 'required|integer|between:0,36000000',
            'top_documents' => 'present|array|max:50',
            'top_documents.*.document_type_id' => 'required|integer|distinct',
            'top_documents.*.name' => 'required|string|max:255',
            'top_documents.*.forecast_share' => 'required|numeric|between:0,1',
            'top_documents.*.notes' => 'present|string|max:2000',
        ];
        foreach (['volume_forecast.notes', 'peak_period_analysis.upcoming_peaks_30_60_days',
            'peak_period_analysis.historical_peaks', 'peak_period_analysis.why_peaks_happen',
            'processing_workload.notes', 'staffing_recommendations.normal_period',
            'staffing_recommendations.peak_period', 'insights', 'assumptions'] as $field) {
            $rules[$field] = 'present|array|list|max:20';
            $rules[$field.'.*'] = 'required|string|max:2000';
        }
        $rules = array_map(fn ($rule) => 'bail|'.$rule, $rules);
        $validator = Validator::make($data, $rules);
        if ($validator->fails() || $data['months'] !== $months
            || !array_is_list($data['overall_forecast']) || !array_is_list($data['per_document_type'])) {
            throw new ForecastFailure('schema_invalid');
        }
        $names = array_column($types, 'name', 'document_type_id');
        $seen = [];
        $totals = array_fill(0, $horizon, 0);
        foreach ($data['per_document_type'] as &$row) {
            $id = $row['document_type_id'];
            if (!is_int($id) || !isset($names[$id]) || isset($seen[$id]) || !array_is_list($row['forecast'])) {
                throw new ForecastFailure('document_types_invalid');
            }
            $seen[$id] = true;
            $row['name'] = $names[$id];
            foreach ($row['forecast'] as $i => $value) {
                if (!is_int($value)) { throw new ForecastFailure('counts_invalid'); }
                $totals[$i] += $value;
            }
        }
        unset($row);
        if ($totals !== $data['overall_forecast'] || array_sum($totals) !== $data['volume_forecast']['total_requests']) {
            throw new ForecastFailure('totals_inconsistent');
        }
        foreach ($data['top_documents'] as &$row) {
            if (!isset($names[$row['document_type_id']])) { throw new ForecastFailure('document_types_invalid'); }
            $row['name'] = $names[$row['document_type_id']];
        }
        unset($row);
        // Never trust a provider-supplied summary; the application computes it.
        unset($data['summary']);
        return $data;
    }

    public static function months(int $horizon): array
    {
        $start = now()->startOfMonth();
        return array_map(fn ($i) => $start->copy()->addMonths($i)->format('Y-m'), range(1, $horizon));
    }
}
