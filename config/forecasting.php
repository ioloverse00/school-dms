<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Forecasting Assumptions
    |--------------------------------------------------------------------------
    |
    | These defaults are used to convert request-count forecasts into operational
    | metrics such as workload (time) and staffing needs. You can override them
    | at runtime via SystemSetting keys (see ForecastSummaryBuilder).
    |
    */

    // Average time to process a single document request.
    'avg_minutes_per_request' => (int) env('FORECAST_AVG_MINUTES_PER_REQUEST', 10),

    // Productive processing time per staff per working day.
    'productive_hours_per_staff_per_day' => (float) env('FORECAST_PRODUCTIVE_HOURS_PER_STAFF_PER_DAY', 6),

    // Peak day multiplier applied on top of the average daily volume in the peak month.
    'peak_day_multiplier' => (float) env('FORECAST_PEAK_DAY_MULTIPLIER', 1.4),

    // If true, daily estimates use Mon–Fri business days; otherwise, use calendar days.
    'business_days_only' => (bool) env('FORECAST_BUSINESS_DAYS_ONLY', true),
];

