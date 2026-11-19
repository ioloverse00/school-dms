@php($geminiEnabled = (bool) config('openai.forecasting_enabled', false))
<div class="portal-analytics">
    <div class="mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><div class="subheader">Registrar analytics</div><h2 class="h2 mb-1">Document demand &amp; forecasting</h2><p class="text-secondary mb-0">Historical requests include archived records. Forecasts are estimates.</p></div>
        <span class="badge bg-green-lt">School records</span>
    </div>
    @if($geminiEnabled)
    <section id="aiForecastSummary" class="mb-4" aria-label="AI forecast summary">
        <div class="forecast-update-toolbar mb-3">
            <div class="forecast-update-copy">
                <p class="portal-note mb-1">Updates daily at 12 AM and 12 PM &middot; Philippine time</p>
                <p id="aiForecastStatus" class="text-secondary mb-0" style="font-size:11px;font-style:italic" role="status">Loading saved forecast...</p>
            </div>
            @role('admin')<button id="aiForecastGenerate" type="button" class="btn btn-outline-primary btn-sm">Refresh now</button>@endrole
        </div>
        <div class="row row-cards forecast-summary-cards">
            @foreach([['aiPeak', 'Expected peak', 'AI forecast'], ['aiWorkload', 'Peak-month workload', 'Forecast-based estimate'], ['aiStaffing', 'Staffing assessment', 'Capacity estimate']] as [$id, $title, $sourceLabel])
            <div class="col-md-4"><div class="card h-100 forecast-summary-card"><div class="card-body"><div class="subheader">{{ $title }}</div><div id="{{ $id }}Primary" class="forecast-card-value my-2">&mdash;</div><div id="{{ $id }}Secondary" class="forecast-card-description"></div></div><div class="forecast-source-footer"><i class="bi bi-stars forecast-source-sparkle" aria-hidden="true"></i><span>{{ $sourceLabel }}</span></div></div></div>
            @endforeach
        </div>
    </section>
    @endif
    <div class="row row-cards">
        <div class="col-lg-8"><section class="card h-100" aria-labelledby="trend-title">
            <div class="card-header"><div><h3 class="card-title" id="trend-title">Monthly request trend</h3><div class="text-secondary small">Six months of actual demand and estimated future requests</div></div></div>
            <div class="card-body px-2 pb-0"><div id="monthlyLineChart" class="chart" role="img" aria-label="Monthly actual requests and forecast"></div></div>
            <div class="card-footer text-secondary small">Solid line: actual requests. Dashed line: forecast.</div>
        </section></div>
        <div class="col-lg-4"><section class="card h-100" aria-labelledby="category-title">
            <div class="card-header"><h3 class="card-title" id="category-title">Requests by category</h3><span class="ms-auto text-secondary small">All time</span></div>
            <div class="card-body">
                @if(($academicCount + $certificationCount) > 0)
                <div id="categoryDoughnut" class="chart" role="img" aria-label="Academic {{ $academicCount }} requests; certification {{ $certificationCount }} requests"></div>
                @else <div class="portal-chart-empty">No category data yet</div> @endif
                <div class="d-flex justify-content-between py-2 border-top"><span><span class="badge me-2" style="background:var(--portal-primary)"></span>Academic</span><strong>{{ number_format($academicCount) }}</strong></div>
                <div class="d-flex justify-content-between py-2"><span><span class="badge me-2" style="background:var(--portal-secondary)"></span>Certification</span><strong>{{ number_format($certificationCount) }}</strong></div>
            </div>
        </section></div>
        <div class="col-lg-8"><section class="card h-100" aria-labelledby="demand-title">
            <div class="card-header"><h3 class="card-title" id="demand-title">Most requested documents</h3><span class="ms-auto text-secondary small">All time</span></div>
            <div class="card-body px-2">
                @if($documentDemand->isEmpty())<div class="portal-chart-empty">No document requests yet</div>
                @else<div id="demandBarChart" class="chart" role="img" aria-label="Most requested documents"></div>@endif
            </div>
        </section></div>
        <div class="col-lg-4"><section class="card h-100" aria-labelledby="students-title">
            <div class="card-body"><div class="subheader" id="students-title">Student accounts</div><div class="display-5 fw-bold my-3">{{ number_format($totalStudents) }}</div><p class="text-secondary">Registered students in the portal</p>
                <div class="border-top pt-3 mt-3"><div class="subheader">Trending document</div>
                @if($trendingDoc && $trendingDoc->documentType)<h3 class="h3 mt-2">{{ $trendingDoc->documentType->name }}</h3><p class="text-secondary">{{ number_format($trendingDoc->total) }} requests in the last 30 days</p>
                @else<p class="text-secondary mt-2">No requests in the last 30 days</p>@endif</div>
                @if(isset($secondTrendingDoc) && $secondTrendingDoc->documentType)
                <div class="mt-3"><div class="subheader">{{ $secondTrendingDoc->total === $trendingDoc->total ? 'Joint most requested' : 'Second most requested' }}</div><h4 class="mt-2 mb-1" style="font-size:16px;font-weight:600">{{ $secondTrendingDoc->documentType->name }}</h4><p class="text-secondary mb-0" style="font-size:11px">{{ number_format($secondTrendingDoc->total) }} requests in the last 30 days</p></div>
                @endif
            </div>
        </section></div>
    </div>
</div>
<script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
<script src="{{ asset('assets/js/portal-charts.js') }}"></script>
<script src="{{ asset('assets/js/forecast-cards.js') }}"></script>
<script>
(() => {
    const monthLabels = @json($monthlyLabels);
    const monthValues = @json(array_map('intval', $monthlyValues));
    const monthlyTrendChart = PortalCharts.mount({
        labels: monthLabels, values: monthValues,
        futureLabels: @json($forecastLabels ?? []), futureValues: @json($overallForecastValues ?? []),
        categoryValues: @json([(int) $academicCount, (int) $certificationCount]),
        demandLabels: @json($documentDemand->map(fn($d) => $d->documentType->name ?? 'Unknown')->values()->all()),
        demandValues: @json($documentDemand->pluck('total')->map(fn($v) => (int) $v)->values()->all()),
    });
    let lastSavedForecastStatus = '';
    async function loadAiForecastForCharts(generate = false) {
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
                if (statusEl) {
                    const formatTime = value => new Date(value).toLocaleString('en-PH', {month:'short',day:'numeric',hour:'numeric',minute:'2-digit',timeZone:'Asia/Manila'});
                    statusEl.textContent = `${payload?.stale ? 'Outdated forecast. ' : ''}Last updated: ${formatTime(payload.generated_at)}. Next scheduled: ${formatTime(payload.next_scheduled_at)} (Philippine time).`;
                    lastSavedForecastStatus = statusEl.textContent;
                }

                const cards = window.PortalForecastCards.present(summary, forecast);
                for (const [key, prefix] of [['peak','aiPeak'], ['workload','aiWorkload'], ['staffing','aiStaffing']]) {
                    const primary = document.getElementById(prefix + 'Primary');
                    const secondary = document.getElementById(prefix + 'Secondary');
                    if (primary) primary.textContent = cards[key].primary;
                    if (secondary) secondary.textContent = cards[key].secondary;
                }

            }

            if (!monthlyTrendChart || !Array.isArray(aiMonths) || aiMonths.length === 0) return;

            const formatYm = (ym) => {
                const d = new Date(ym + '-01T00:00:00');
                if (Number.isNaN(d.getTime())) return ym;
                return d.toLocaleString('en-US', { month: 'short', year: 'numeric' });
            };

            const futureLabels = aiMonths.map(formatYm);
            monthlyTrendChart.updateForecast(futureLabels, overallForecast);
        };

        const button = document.getElementById('aiForecastGenerate');
        if (button) button.disabled = true;
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), generate ? 35000 : 5000);
        try {
            if (statusEl) statusEl.textContent = generate ? 'Generating...' : 'Loading saved forecast...';
            const url = new URL('{{ route(request()->routeIs('registrar.*') ? 'registrar.ai-forecast' : 'admin.ai-forecast') }}', window.location.origin);
            url.searchParams.set('history', '24');
            url.searchParams.set('months', '12');
            url.searchParams.set('include_history', '1');
            if (generate) url.searchParams.set('force', '1');
            const res = await fetch(url.toString(), {
                method: generate ? 'POST' : 'GET',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                signal: controller.signal,
            });
            const payload = await res.json().catch(() => null);
            if (res.status === 202) {
                if (statusEl) statusEl.textContent = 'No saved forecast yet. Waiting for the scheduled update.';
            } else if (res.ok && payload?.forecast?.summary) {
                applyForecast(payload);
                if (generate) window.dispatchEvent(new CustomEvent('portal:feedback', {detail: {tone: 'success', message: 'Forecast updated successfully.'}}));
            } else {
                if (statusEl) statusEl.textContent = lastSavedForecastStatus ? `Refresh failed. Showing saved forecast. ${lastSavedForecastStatus}` : (payload?.message || 'Forecast unavailable. Try again later.');
                if (generate) window.dispatchEvent(new CustomEvent('portal:feedback', {detail: {tone: 'danger', message: payload?.message || 'Forecast unavailable. Try again later.'}}));
            }
        } catch (e) {
            if (statusEl) statusEl.textContent = lastSavedForecastStatus ? `Refresh failed. Showing saved forecast. ${lastSavedForecastStatus}` : (e?.name === 'AbortError' ? 'Timed out. Check saved forecast before retrying.' : 'Forecast unavailable.');
            if (generate) window.dispatchEvent(new CustomEvent('portal:feedback', {detail: {tone: 'danger', message: statusEl?.textContent || 'Forecast unavailable.'}}));
        } finally {
            clearTimeout(timeoutId);
            if (button) button.disabled = false;
        }
    }
    document.getElementById('aiForecastGenerate')?.addEventListener('click', () => loadAiForecastForCharts(true));

    loadAiForecastForCharts();
})();
</script>
