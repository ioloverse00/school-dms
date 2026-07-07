{{--
    Layout:
    [Monthly Trend 70%] [By Category 30%]
    [Most Requested 70%] [Total Students 30%]
    [Total Requests] [Pending] [Processing] [Completed]
--}}

<h3 class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">
    Document Demand Forecast
</h3>

@php($geminiEnabled = (bool) config('gemini.enabled', false))

@if($geminiEnabled)
    {{-- AI Forecast Summary (auto-populated from cached AI forecast) --}}
    <div id="aiForecastSummary" class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                AI Summary
                <span id="aiForecastStatus" class="ml-2 text-[11px] text-gray-400 dark:text-gray-500">Loading…</span>
            </p>
        </div>
        <div class="flex flex-row gap-6 overflow-x-auto pb-2 pr-2">
            <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800 flex-1 min-w-[260px]">
                <div class="p-3 mr-4 text-teal-600 bg-teal-100 rounded-full dark:text-teal-100 dark:bg-teal-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 12a1 1 0 011 1v3a1 1 0 11-2 0v-3a1 1 0 011-1z"></path>
                        <path d="M6 10a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1z"></path>
                        <path d="M10 7a1 1 0 011 1v8a1 1 0 11-2 0V8a1 1 0 011-1z"></path>
                        <path d="M14 4a1 1 0 011 1v11a1 1 0 11-2 0V5a1 1 0 011-1z"></path>
                        <path d="M18 2a1 1 0 011 1v13a1 1 0 11-2 0V3a1 1 0 011-1z"></path>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="mb-1 text-sm font-medium text-gray-600 dark:text-gray-400">Peak request periods</p>
                    <p id="aiPeakPrimary" class="text-lg font-semibold text-gray-700 dark:text-gray-200">&mdash;</p>
                    <p id="aiPeakSecondary" class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate"></p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800 flex-1 min-w-[260px]">
                <div class="p-3 mr-4 text-pink-600 bg-pink-100 rounded-full dark:text-pink-100 dark:bg-pink-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zm-1 4a1 1 0 112 0v4.586l1.707 1.707a1 1 0 01-1.414 1.414l-2-2A1 1 0 019 11V6z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="mb-1 text-sm font-medium text-gray-600 dark:text-gray-400">Processing workload</p>
                    <p id="aiWorkloadPrimary" class="text-lg font-semibold text-gray-700 dark:text-gray-200">&mdash;</p>
                    <p id="aiWorkloadSecondary" class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate"></p>
                </div>
            </div>

            <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800 flex-1 min-w-[260px]">
                <div class="p-3 mr-4 text-green-600 bg-green-100 rounded-full dark:text-green-100 dark:bg-green-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 7a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v2h8v-2zM6 8a2 2 0 11-4 0 2 2 0 014 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="mb-1 text-sm font-medium text-gray-600 dark:text-gray-400">Staffing needs (busy days)</p>
                    <p id="aiStaffingPrimary" class="text-lg font-semibold text-gray-700 dark:text-gray-200">&mdash;</p>
                    <p id="aiStaffingSecondary" class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate"></p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ROW 1: Monthly Trend (70%) + By Category (30%) --}}
<div style="display:grid; grid-template-columns:7fr 3fr; gap:1.5rem; margin-bottom:1.5rem;">

    {{-- Line Chart --}}
    <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Monthly Request Trend</h4>
            <div class="flex items-center gap-3 text-xs text-gray-400">
                <span style="display:inline-flex;align-items:center;gap:4px;">
                    <span style="display:inline-block;width:18px;height:2px;background:#7e3af2;border-radius:1px;"></span> Actual
                </span>
                <span style="display:inline-flex;align-items:center;gap:4px;">
                    <span style="display:inline-block;width:18px;height:0;border-top:2px dashed #a78bfa;"></span> Forecast
                </span>
            </div>
        </div>
        <div style="position:relative; height:200px;">
            <canvas id="monthlyLineChart"></canvas>
        </div>
    </div>

    {{-- Doughnut Chart --}}
    <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200">By Category</h4>
            <span class="text-xs text-gray-400 dark:text-gray-500">All time</span>
        </div>
        @if(($academicCount + $certificationCount) === 0)
            <p class="text-sm text-gray-400 text-center" style="padding:3rem 0;">No data yet.</p>
        @else
            <div class="flex items-center justify-center" style="height:140px;">
                <canvas id="categoryDoughnut"></canvas>
            </div>
            <div class="flex flex-col gap-2" style="margin-top:1.5rem;">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:#7e3af2; display:inline-block; flex-shrink:0;"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">Academic</span>
                    </div>
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ $academicCount }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:#0694a2; display:inline-block; flex-shrink:0;"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">Certification</span>
                    </div>
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ $certificationCount }}</span>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- ROW 2: Most Requested Bar Chart (70%) + Total Students card (30%) --}}
<div style="display:grid; grid-template-columns:7fr 3fr; gap:1.5rem; margin-bottom:1.5rem;">

    {{-- Horizontal Bar Chart --}}
    <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Most Requested Documents</h4>
            <span class="text-xs text-gray-400 dark:text-gray-500">All time</span>
        </div>
        @if($documentDemand->isEmpty())
            <p class="text-sm text-gray-400 text-center" style="padding:3rem 0;">No data yet.</p>
        @else
            <div style="position:relative; height:200px;">
                <canvas id="demandBarChart"></canvas>
            </div>
        @endif
    </div>

    {{-- Total Students card --}}
    <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800 flex flex-col justify-between">
        <div>
            <div class="flex items-center mb-4">
                <div class="p-3 mr-4 rounded-full" style="color:#7c3aed; background-color:#ede9fe;">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v1h8v-1zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-1a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v1h-3zM4.75 12.094A5.973 5.973 0 004 15v1H1v-1a3 3 0 013.75-2.906z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Students</p>
                    <p class="text-3xl font-bold text-gray-700 dark:text-gray-200">{{ $totalStudents }}</p>
                </div>
            </div>

            @if($trendingDoc && $trendingDoc->documentType)
            <div class="p-3 rounded-lg" style="background:#f5f3ff;">
                <p class="text-xs font-semibold" style="color:#7c3aed;">Trending Document</p>
                <p class="text-sm font-bold text-gray-700 mt-0.5">{{ $trendingDoc->documentType->name }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $trendingDoc->total }} request{{ $trendingDoc->total > 1 ? 's' : '' }} in last 30 days</p>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ROW 3: Stats Cards — Total Requests | Pending | Processing | Completed --}}
<div class="grid gap-6 mb-8 md:grid-cols-2 xl:grid-cols-4">

    <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
        <div class="p-3 mr-4 text-orange-500 bg-orange-100 rounded-full dark:text-orange-100 dark:bg-orange-500">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
            </svg>
        </div>
        <div>
            <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Total Requests</p>
            <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $totalRequests }}</p>
        </div>
    </div>

    <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
        <div class="p-3 mr-4 rounded-full" style="color:#d97706; background-color:#fef3c7;">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
            </svg>
        </div>
        <div>
            <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Pending</p>
            <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $pendingRequests }}</p>
        </div>
    </div>

    <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
        <div class="p-3 mr-4 text-blue-500 bg-blue-100 rounded-full dark:text-blue-100 dark:bg-blue-500">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
            </svg>
        </div>
        <div>
            <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Processing</p>
            <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $processingRequests }}</p>
        </div>
    </div>

    <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
        <div class="p-3 mr-4 text-green-500 bg-green-100 rounded-full dark:text-green-100 dark:bg-green-500">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
        </div>
        <div>
            <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Completed</p>
            <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $completedRequests }}</p>
        </div>
    </div>
</div>

{{-- Chart.js --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"></script>
<script>
    Chart.defaults.global.defaultFontFamily = 'Inter, sans-serif';
    Chart.defaults.global.defaultFontSize   = 12;
    Chart.defaults.global.defaultFontColor  = '#374151';

    const demandLabels    = {!! json_encode($documentDemand->map(fn($d) => $d->documentType->name ?? 'Unknown')->toArray()) !!};
    const demandData      = {!! json_encode($documentDemand->pluck('total')->map(fn($v) => (int)$v)->toArray()) !!};
    const monthLabels     = {!! json_encode($monthlyLabels) !!};
    const monthValues     = {!! json_encode(array_map('intval', $monthlyValues)) !!};
    const forecastLabels  = {!! json_encode($forecastLabels ?? []) !!};
    const forecastValues  = {!! json_encode($overallForecastValues ?? []) !!};
    let monthlyTrendChart = null;

    // Auto-load cached AI forecast on page load (no UI; updates the existing Monthly Trend chart)
    async function loadAiForecastForCharts() {
        @if(!$geminiEnabled)
            return;
        @endif

        const statusEl = document.getElementById('aiForecastStatus');
        if (statusEl) statusEl.textContent = 'Loading…';

        const applyForecast = (payload) => {
            const forecast = payload?.forecast || {};
            const summary = forecast?.summary || null;
            const aiMonths = forecast.months || [];
            const overallForecast = (forecast.overall_forecast || []).map(v => parseInt(v, 10) || 0);

            const summaryRoot = document.getElementById('aiForecastSummary');
            if (summaryRoot && summary && typeof summary === 'object') {
                if (statusEl) statusEl.textContent = payload?.cached ? 'Cached' : 'Updated';

                const peakPrimaryEl = document.getElementById('aiPeakPrimary');
                const peakSecondaryEl = document.getElementById('aiPeakSecondary');
                if (peakPrimaryEl) {
                    const months = summary?.peak_request_periods?.months || [];
                    const top = Array.isArray(months) && months.length ? months[0] : null;
                    peakPrimaryEl.textContent = top?.month
                        ? `${top.month} · ${parseInt(top.requests ?? 0, 10) || 0} req`
                        : '—';
                }
                if (peakSecondaryEl) {
                    const weekdays = summary?.peak_request_periods?.weekdays || [];
                    const hours = summary?.peak_request_periods?.hours || [];
                    const wkTxt = Array.isArray(weekdays) && weekdays.length
                        ? 'Busy days: ' + weekdays.slice(0, 2).map(w => `${w.weekday} (${Math.round((w.share ?? 0) * 100)}%)`).join(', ')
                        : '';
                    const hrTxt = Array.isArray(hours) && hours.length
                        ? 'Busy hours: ' + hours.slice(0, 2).map(h => `${String(parseInt(h.hour ?? 0, 10) || 0).padStart(2, '0')}:00`).join(', ')
                        : '';
                    peakSecondaryEl.textContent = [wkTxt, hrTxt].filter(Boolean).join(' • ');
                }

                const workloadPrimaryEl = document.getElementById('aiWorkloadPrimary');
                const workloadSecondaryEl = document.getElementById('aiWorkloadSecondary');
                if (workloadPrimaryEl) {
                    const hrs = summary?.expected_processing_workload?.total_hours;
                    const mins = summary?.expected_processing_workload?.total_minutes;
                    if (typeof hrs === 'number') {
                        workloadPrimaryEl.textContent = `${hrs.toLocaleString('en-US')} hrs`;
                    } else if (typeof mins === 'number') {
                        workloadPrimaryEl.textContent = `${mins.toLocaleString('en-US')} mins`;
                    } else {
                        workloadPrimaryEl.textContent = '—';
                    }
                }
                if (workloadSecondaryEl) {
                    const peakDay = summary?.expected_processing_workload?.peak_day;
                    if (peakDay?.month) {
                        const req = parseInt(peakDay.estimated_requests ?? 0, 10) || 0;
                        const mins = parseInt(peakDay.estimated_minutes ?? 0, 10) || 0;
                        workloadSecondaryEl.textContent = `Peak day in ${peakDay.month}: ${req} req (~${mins} mins)`;
                    } else {
                        workloadSecondaryEl.textContent = '';
                    }
                }

                const staffingPrimaryEl = document.getElementById('aiStaffingPrimary');
                const staffingSecondaryEl = document.getElementById('aiStaffingSecondary');
                if (staffingPrimaryEl) {
                    const staff = summary?.staffing_needs?.estimated_staff_needed_on_peak_day;
                    staffingPrimaryEl.textContent = (staff === null || staff === undefined) ? '—' : `${staff} staff`;
                }
                if (staffingSecondaryEl) {
                    const recs = summary?.staffing_needs?.monthly_surge_recommendations || [];
                    if (Array.isArray(recs) && recs.length && typeof recs[0]?.message === 'string') {
                        staffingSecondaryEl.textContent = recs[0].message;
                    } else {
                        const peakDay = summary?.expected_processing_workload?.peak_day;
                        staffingSecondaryEl.textContent = peakDay?.month
                            ? `Based on ${peakDay.month} peak day (${parseInt(peakDay.estimated_requests ?? 0, 10) || 0} req)`
                            : '';
                    }
                }
            }

            if (!monthlyTrendChart || !Array.isArray(aiMonths) || aiMonths.length === 0) return;

            const formatYm = (ym) => {
                const d = new Date(ym + '-01T00:00:00');
                if (Number.isNaN(d.getTime())) return ym;
                return d.toLocaleString('en-US', { month: 'short', year: 'numeric' });
            };

            const futureLabels = aiMonths.map(formatYm);
            const allLabels = [...monthLabels, ...futureLabels];
            const n = monthLabels.length;
            const historicalData = [...monthValues, ...Array(futureLabels.length).fill(null)];
            const fcData = [...Array(n - 1).fill(null), monthValues[n - 1], ...overallForecast];

            monthlyTrendChart.data.labels = allLabels;
            if (monthlyTrendChart.data.datasets?.[0]) monthlyTrendChart.data.datasets[0].data = historicalData;
            if (monthlyTrendChart.data.datasets?.[1]) monthlyTrendChart.data.datasets[1].data = fcData;
            monthlyTrendChart.update();
        };

        const fetchForecast = async ({ cacheOnly, timeoutMs }) => {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

            const url = new URL('{{ route('admin.ai-forecast') }}', window.location.origin);
            url.searchParams.set('history', '24');
            url.searchParams.set('months', '12');
            url.searchParams.set('expected_graduates', '0');
            url.searchParams.set('include_history', '0');
            url.searchParams.set('limit_types', '0');
            if (cacheOnly) url.searchParams.set('cache_only', '1');

            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' },
                signal: controller.signal,
            });
            clearTimeout(timeoutId);

            const ct = (res.headers.get('content-type') || '').toLowerCase();
            if (!ct.includes('application/json')) {
                if (statusEl) statusEl.textContent = 'Sign in required';
                return { ok: false };
            }

            const payload = await res.json().catch(() => null);
            return { ok: res.ok, status: res.status, payload, statusText: res.statusText };
        };

        try {
            // 1) Fast path: cached-only (should return immediately)
            const cachedAttempt = await fetchForecast({ cacheOnly: true, timeoutMs: 2500 });
            if (cachedAttempt.ok && cachedAttempt.payload?.forecast?.summary) {
                applyForecast(cachedAttempt.payload);
                return;
            }

            // 2) Slow path: allow generation; show user what's happening
            if (statusEl) statusEl.textContent = 'Generating…';
            const freshAttempt = await fetchForecast({ cacheOnly: false, timeoutMs: 70000 });
            if (!freshAttempt.ok) {
                if (freshAttempt.status === 202) {
                    if (statusEl) statusEl.textContent = 'Generating…';
                } else if (freshAttempt.payload?.error) {
                    const msg = typeof freshAttempt.payload?.message === 'string' ? freshAttempt.payload.message : '';
                    const short = msg ? (msg.length > 80 ? (msg.slice(0, 77) + '…') : msg) : '';
                    if (statusEl) statusEl.textContent = short
                        ? `Error (${freshAttempt.status || '—'}): ${short}`
                        : `Error (${freshAttempt.status || '—'})`;
                } else if (freshAttempt.status) {
                    if (statusEl) statusEl.textContent = `Unavailable (${freshAttempt.status})`;
                } else {
                    if (statusEl) statusEl.textContent = 'Unavailable';
                }
                return;
            }
            if (freshAttempt.payload?.error) {
                const msg = typeof freshAttempt.payload?.message === 'string' ? freshAttempt.payload.message : '';
                const short = msg ? (msg.length > 80 ? (msg.slice(0, 77) + '…') : msg) : '';
                if (statusEl) statusEl.textContent = short ? `Error: ${short}` : 'Error';
                return;
            }

            applyForecast(freshAttempt.payload);
        } catch (e) {
            if (statusEl) statusEl.textContent = (e && e.name === 'AbortError') ? 'Timed out' : 'Unavailable';
        }
    }

    // Line — Monthly Trend (solid) + 3-month forecast (dashed)
    const lineCtx = document.getElementById('monthlyLineChart');
    if (lineCtx) {
        const allLabels = [...monthLabels, ...forecastLabels];
        const n = monthLabels.length;

        // Solid historical dataset: actual values + null for forecast months
        const historicalData = [...monthValues, ...Array(forecastLabels.length).fill(null)];

        // Dashed forecast dataset: nulls for past, connect from last actual point
        const forecastData = [...Array(n - 1).fill(null), monthValues[n - 1], ...forecastValues];

        const shadingPlugin = {
            beforeDraw: function(chart) {
                const ctx2  = chart.ctx;
                const xAxis = chart.scales['x-axis-0'];
                const yAxis = chart.scales['y-axis-0'];
                if (!xAxis || !yAxis) return;
                const startX = xAxis.getPixelForTick(n - 1);
                ctx2.save();
                ctx2.fillStyle = 'rgba(126,58,242,0.05)';
                ctx2.fillRect(startX, yAxis.top, xAxis.right - startX, yAxis.bottom - yAxis.top);
                ctx2.restore();
            }
        };

        monthlyTrendChart = new Chart(lineCtx, {
            type: 'line',
            plugins: [shadingPlugin],
            data: {
                labels: allLabels,
                datasets: [
                    {
                        label: 'Actual',
                        backgroundColor: 'rgba(126,58,242,0.08)',
                        borderColor: '#7e3af2',
                        pointBackgroundColor: '#7e3af2',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        data: historicalData,
                        fill: true,
                        lineTension: 0.4,
                    },
                    {
                        label: 'Forecast',
                        backgroundColor: 'transparent',
                        borderColor: '#a78bfa',
                        borderDash: [6, 4],
                        pointBackgroundColor: '#a78bfa',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        data: forecastData,
                        fill: false,
                        lineTension: 0.4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    filter: item => item.yLabel !== null,
                    callbacks: {
                        title: (items) => items[0].xLabel,
                        label: (item, data) => {
                            const label = data.datasets[item.datasetIndex].label;
                            return '  ' + label + ': ' + item.yLabel + ' requests';
                        }
                    }
                },
                scales: {
                    xAxes: [{ gridLines: { display: false }, ticks: { padding: 10 } }],
                    yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: 'rgba(0,0,0,0.04)' } }]
                }
            }
        });

        // Replace the default forecast line with AI (cached) forecast when available
        loadAiForecastForCharts();
    }

    // Doughnut — Category
    const donutCtx = document.getElementById('categoryDoughnut');
    if (donutCtx) {
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Academic', 'Certification'],
                datasets: [{
                    data: [{{ $academicCount }}, {{ $certificationCount }}],
                    backgroundColor: ['#7e3af2', '#0694a2'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                cutoutPercentage: 72,
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        label: (ctx, data) => '  ' + data.labels[ctx.index] + ': ' + data.datasets[0].data[ctx.index]
                    }
                }
            }
        });
    }

    // Forecast Line Chart — historical (solid) + predicted (dashed)
    const forecastCtx = document.getElementById('forecastLineChart');
    if (forecastCtx) {
        const forecastData  = @json($forecastData ?? []);
        const pastLabels    = @json($monthlyLabels ?? []);
        const futureLabels  = @json($forecastLabels ?? []);
        const allLabels     = [...pastLabels, ...futureLabels];
        const n             = pastLabels.length;
        const fcColors      = ['#7e3af2','#0694a2','#e3a008','#e74694','#31c48d'];

        const datasets = [];
        forecastData.slice(0, 3).forEach((doc, idx) => {
            const color = fcColors[idx];
            // Historical: solid line (n points + nulls for future)
            const histVals = [...doc.historical, ...Array(futureLabels.length).fill(null)];
            // Predicted: null for past months, then connect from last historical point + 3 predicted
            const predVals = [...Array(n - 1).fill(null), doc.historical[n - 1], ...doc.predictions];

            datasets.push({
                label: doc.name + ' (actual)',
                data: histVals,
                borderColor: color,
                backgroundColor: 'transparent',
                pointBackgroundColor: color,
                pointRadius: 4,
                borderWidth: 2,
                fill: false,
                lineTension: 0.3,
            });
            datasets.push({
                label: doc.name + ' (forecast)',
                data: predVals,
                borderColor: color,
                backgroundColor: 'transparent',
                pointBackgroundColor: color,
                pointRadius: 4,
                borderWidth: 2,
                borderDash: [6, 4],
                fill: false,
                lineTension: 0.3,
            });
        });

        // Shade the forecast region using a background color on the canvas
        const forecastPlugin = {
            beforeDraw: function(chart) {
                const ctx   = chart.ctx;
                const xAxis = chart.scales['x-axis-0'];
                const yAxis = chart.scales['y-axis-0'];
                if (!xAxis || !yAxis) return;
                // Find x pixel for where forecast starts (index n-1 to n)
                const startX = xAxis.getPixelForTick(n - 1);
                const endX   = xAxis.right;
                ctx.save();
                ctx.fillStyle = 'rgba(126,58,242,0.04)';
                ctx.fillRect(startX, yAxis.top, endX - startX, yAxis.bottom - yAxis.top);
                ctx.restore();
            }
        };

        new Chart(forecastCtx, {
            type: 'line',
            plugins: [forecastPlugin],
            data: { labels: allLabels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    filter: item => item.yLabel !== null,
                },
                scales: {
                    xAxes: [{ gridLines: { display: false } }],
                    yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: 'rgba(0,0,0,0.04)' } }]
                }
            }
        });
    }

    // Horizontal Bar — Top Documents
    const barCtx = document.getElementById('demandBarChart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'horizontalBar',
            data: {
                labels: demandLabels,
                datasets: [{
                    label: 'Requests',
                    backgroundColor: ['#7e3af2','#8b5cf6','#a78bfa','#c4b5fd','#ddd6fe','#ede9fe'],
                    borderWidth: 0,
                    data: demandData,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    callbacks: { label: ctx => '  ' + ctx.xLabel + ' requests' }
                },
                scales: {
                    xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: 'rgba(0,0,0,0.04)' } }],
                    yAxes: [{ gridLines: { display: false }, ticks: { fontSize: 11 } }]
                }
            }
        });
    }
</script>
