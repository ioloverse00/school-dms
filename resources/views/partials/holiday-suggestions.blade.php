@php
    $holidayService = app(\App\Services\HolidaySuggestions::class);
    $holidayCheck = $yearId ? $holidayService->saved($yearId) : null;
    if ($holidayCheck) {
        $holidayCheck['holidays'] = collect($holidayCheck['holidays'])
            ->sortBy(fn($holiday) => ($holiday['date'] ?? '9999-12-31').'|'.mb_strtolower($holiday['name']))
            ->values()->all();
    }
    $holidaySourceNames = collect($holidayCheck['holidays'] ?? [])->map(function ($holiday) {
        $host = strtolower(parse_url($holiday['source_url'], PHP_URL_HOST) ?? '');
        return $host === 'officialgazette.gov.ph' || str_ends_with($host, '.officialgazette.gov.ph') ? 'Official Gazette' : ($host === 'pco.gov.ph' || str_ends_with($host, '.pco.gov.ph') ? 'PCO' : null);
    })->filter()->unique()->join(' and ');
@endphp
@once
<style>
.holiday-toolbar .btn,.holiday-toolbar select{height:42px;box-sizing:border-box;margin-bottom:0}
.holiday-toolbar .btn{display:inline-flex;align-items:center;justify-content:center}
.holiday-toolbar form{margin:0}
.holiday-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px}@media(min-width:1200px){.holiday-card-grid{grid-template-columns:repeat(5,minmax(0,1fr))}}.holiday-date-card{min-width:0}
.holiday-date-card{border:1px solid #e0e7ed;border-radius:18px;padding:22px 18px;text-align:center;background:#f8fbfa;display:flex;flex-direction:column;align-items:center;gap:12px}
.holiday-date-tile{width:110px;min-height:136px;border-radius:16px;background:#fff;box-shadow:0 5px 0 #dce7e1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:12px;gap:3px}
.holiday-month{color:#be3535;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1px}
.holiday-day{font-size:38px;line-height:1;color:#192c3d}.holiday-year{font-size:11px;color:#667085}
.holiday-office{display:block;border-radius:20px;padding:5px 13px;font-size:10px;font-weight:800;letter-spacing:.6px;margin-top:5px;border:3px solid #e5e7eb}
.holiday-office.is-closed{background:#b8313d;color:#fff}.holiday-office.is-open{background:#227548;color:#fff}.holiday-office.is-review{background:#fff0cc;color:#704b00}
.holiday-date-card h3{font-size:15px;font-weight:700;margin:4px 0 0}.holiday-date-card p{margin:0}.holiday-card-actions{margin-top:auto;width:100%;font-size:12px}.holiday-card-actions select{max-width:100%}
</style>
@endonce
<section class="card portal-card" aria-labelledby="holiday-suggestions-title">
<div class="portal-card-header"><div><h2 id="holiday-suggestions-title">Philippine holiday suggestions</h2><p class="portal-card-subtitle">Review official declarations before adding calendar events.</p></div></div>
<div class="portal-card-body">
<div class="holiday-toolbar d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
<form method="GET" action="{{ route($calendarRoute) }}" class="d-flex flex-wrap align-items-end gap-3 mb-0">
<input type="hidden" name="panel" value="holidays"><div><label for="holiday-year">Academic year</label><select id="holiday-year" name="year" class="spark-field form-select-custom" required>@foreach($calendar['years'] as $id => $year)<option value="{{ $id }}" @selected($id === $yearId)>{{ $year['name'] }}</option>@endforeach</select></div><button class="btn btn-outline-secondary">View year</button>
</form>
@if($selectedYear)
<div class="d-flex flex-wrap align-items-center justify-content-end gap-3 ms-auto">
<form method="POST" action="{{ route('admin.settings.academic-calendar.holidays.check') }}" class="mb-0">@csrf<input type="hidden" name="year_id" value="{{ $yearId }}"><button class="btn btn-primary" @disabled(!$holidayService->ready())>Check holiday updates</button></form>
</div>
@else<p>Add an academic year before checking holidays.</p>@endif
</div>
@if(!$holidayService->ready())<p class="portal-note mb-3">Holiday updates are not configured yet.</p>@endif
@if($holidayCheck)
<div class="forecast-source-footer mb-3" role="note"><i class="bi bi-stars forecast-source-sparkle" aria-hidden="true"></i><span><strong>AI-generated holiday suggestions.</strong> @if($holidaySourceNames)Based on holiday declarations from {{ $holidaySourceNames }}. @endif Review source links before adding holidays. Pending dates await official confirmation.</span></div>
<p class="portal-note">Last checked: {{ \Carbon\CarbonImmutable::parse($holidayCheck['checked_at'])->timezone('Asia/Manila')->format('F j, Y h:i A') }} (PH time).</p>
<div class="holiday-card-grid mt-3">
@forelse($holidayCheck['holidays'] as $holiday)
@php
    $matchingEventId = collect($calendar['events'])->search(fn($event) => ($event['year_id'] ?? $calendar['terms'][$event['term_id']]['year_id'] ?? null) === $yearId && $event['type'] === 'holiday' && mb_strtolower($event['name']) === mb_strtolower($holiday['name']));
    $matchingEvent = $matchingEventId !== false ? $calendar['events'][$matchingEventId] : null;
@endphp
<article class="holiday-date-card"><div class="holiday-date-tile">@if($holiday['date'])<span class="holiday-month">{{ \Carbon\CarbonImmutable::parse($holiday['date'])->format('M') }}</span><strong class="holiday-day">{{ \Carbon\CarbonImmutable::parse($holiday['date'])->format('d') }}</strong><span class="holiday-year">{{ \Carbon\CarbonImmutable::parse($holiday['date'])->format('Y') }}</span>@else<strong>Pending date</strong>@endif<span class="holiday-office {{ !$holiday['date'] ? 'is-review' : (in_array($holiday['classification'], ['regular', 'special_non_working'], true) ? 'is-closed' : ($holiday['classification'] === 'special_working' ? 'is-open' : 'is-review')) }}">{{ !$holiday['date'] ? 'PENDING' : (in_array($holiday['classification'], ['regular', 'special_non_working'], true) ? 'CLOSED' : ($holiday['classification'] === 'special_working' ? 'OPEN' : 'REVIEW')) }}</span></div><h3>{{ $holiday['name'] }}</h3><span class="portal-note">{{ ['national' => 'Nationwide', 'ncr' => 'NCR / Metro Manila', 'quezon_city' => 'Quezon City'][$holiday['scope'] ?? 'national'] }}</span><p class="portal-note">{{ ucfirst(str_replace('_', ' ', $holiday['classification'])) }}<br><a href="{{ $holiday['source_url'] }}" target="_blank" rel="noopener noreferrer">{{ str_ends_with(strtolower(parse_url($holiday['source_url'], PHP_URL_HOST) ?? ''), 'officialgazette.gov.ph') ? 'Official Gazette source' : (str_ends_with(strtolower(parse_url($holiday['source_url'], PHP_URL_HOST) ?? ''), 'pco.gov.ph') ? 'PCO · Government source' : 'Previously checked source') }}</a></p><div class="holiday-card-actions">
@if($matchingEvent)
{{ $matchingEvent['starts_on'] === $holiday['date'] ? 'Already in calendar' : 'Date differs — review saved event' }}<br><a href="{{ route($calendarRoute, ['year' => $yearId, 'edit_event' => $matchingEventId]) }}">Review event</a>
@elseif(!$holiday['date']) Await official date
@else
<form method="POST" action="{{ route('admin.settings.academic-calendar.holidays.apply') }}">@csrf<input type="hidden" name="year_id" value="{{ $yearId }}"><input type="hidden" name="suggestion_id" value="{{ $holiday['id'] }}"><input type="hidden" name="revision" value="{{ $calendar['revision'] }}">
<button class="btn btn-outline-primary btn-sm">Add to calendar</button></form>
@endif</div></article>
@empty<p class="portal-empty">No sourced suggestions returned. Check the coverage note above.</p>@endforelse
</div>
@else<p class="portal-empty">No holiday sources checked for this academic year yet.</p>@endif
</div></section>
