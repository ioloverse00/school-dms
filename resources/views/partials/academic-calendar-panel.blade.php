<div class="academic-calendar-panel mb-4">
<section class="card portal-card" aria-labelledby="calendar-month-title">
    <div class="calendar-toolbar">
        <div><p class="portal-eyebrow">{{ $selectedYear ? 'Academic year '.$selectedYear['name'] : 'Academic schedule' }}</p><h2 id="calendar-month-title">{{ $month->format('F Y') }}</h2>@if($selectedYear)<p class="portal-note">{{ \Carbon\CarbonImmutable::parse($selectedYear['starts_on'])->format('M j, Y') }} &ndash; {{ \Carbon\CarbonImmutable::parse($selectedYear['ends_on'])->format('M j, Y') }}</p>@endif</div>
        <nav class="d-flex gap-2" aria-label="Calendar months">
            @if(!$selectedYear || $month->format('Y-m') > substr($selectedYear['starts_on'], 0, 7))<a class="btn btn-outline-secondary" aria-label="Previous month" href="{{ route($calendarRoute, array_merge($calendarParams, ['month' => $month->subMonth()->format('Y-m')])) }}">&larr;</a>@else<button type="button" class="btn btn-outline-secondary" disabled aria-label="Previous month">&larr;</button>@endif
            <a class="btn btn-outline-secondary" href="{{ route($calendarRoute, array_filter(['year' => $yearId])) }}">{{ !$selectedYear || (now()->toDateString() >= $selectedYear['starts_on'] && now()->toDateString() <= $selectedYear['ends_on']) ? 'Today' : 'Start of year' }}</a>
            @if(!$selectedYear || $month->format('Y-m') < substr($selectedYear['ends_on'], 0, 7))<a class="btn btn-outline-secondary" aria-label="Next month" href="{{ route($calendarRoute, array_merge($calendarParams, ['month' => $month->addMonth()->format('Y-m')])) }}">&rarr;</a>@else<button type="button" class="btn btn-outline-secondary" disabled aria-label="Next month">&rarr;</button>@endif
        </nav>
    </div>
    <form method="GET" action="{{ route($calendarRoute) }}" class="calendar-month-picker">
        <label for="calendar-year">Academic year</label><select class="spark-field form-select-custom" id="calendar-year" name="year" required>@foreach(collect($calendar['years'])->sortByDesc('starts_on') as $id => $year)<option value="{{ $id }}" @selected($yearId === $id)>{{ $year['name'] }}</option>@endforeach</select><button class="btn btn-outline-secondary" type="submit">Show</button>
    </form>
    <div class="calendar-weekdays" aria-hidden="true">@foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)<span>{{ $weekday }}</span>@endforeach</div>
    <div class="calendar-month-grid" aria-label="{{ $month->format('F Y') }} dates">
    @php
        $gridStart = $month->startOfWeek();
        $weeks = (int) ceil(($month->dayOfWeekIso - 1 + $month->daysInMonth) / 7);
    @endphp
    @for($i = 0; $i < $weeks * 7; $i++)
        @php
            $day = $gridStart->addDays($i);
            $date = $day->toDateString();
            $dayEvents = $monthEvents->filter(fn($event) => $event['starts_on'] <= $date && $event['ends_on'] >= $date);
            $isCurrentMonth = $day->month === $month->month;
        @endphp
        <div class="calendar-day {{ !$isCurrentMonth ? 'calendar-day-outside' : '' }} {{ $day->isToday() ? 'calendar-day-today' : '' }}" aria-label="{{ $day->format('F j, Y') }}{{ $dayEvents->isNotEmpty() && $isCurrentMonth ? ', '.$dayEvents->count().' event(s)' : '' }}">
            <time datetime="{{ $date }}">{{ $day->day }}</time>
            @if($isCurrentMonth)
            @foreach($dayEvents as $eventId => $event)
                @if($canEdit)<a class="calendar-event {{ $event['office_closed'] ? 'calendar-event-closed' : '' }}" href="{{ route($calendarRoute, array_merge($calendarParams, ['edit_event' => $eventId])) }}#calendar-forms" title="{{ $event['name'] }}{{ $event['office_closed'] ? ' — Office closed' : '' }}"><span>{{ $event['name'] }}</span></a>
                @else<span class="calendar-event {{ $event['office_closed'] ? 'calendar-event-closed' : '' }}" title="{{ $event['name'] }}"><span>{{ $event['name'] }}</span></span>@endif
            @endforeach
            @endif
        </div>
    @endfor
    </div>
    <p class="portal-note">Blue: academic event &middot; Teal: office closed</p>
</section>
<section class="card portal-card calendar-agenda" aria-labelledby="agenda-title">
    <div class="card-header portal-card-header"><div><h2 id="agenda-title">This month's events</h2><p class="portal-card-subtitle">{{ $monthEvents->count() }} {{ Str::plural('event', $monthEvents->count()) }}</p></div></div>
    @forelse($monthEvents as $eventId => $event)
    <article class="calendar-agenda-item">
        <span class="portal-status">{{ ucfirst(str_replace('_', ' ', $event['type'])) }}</span>
        <h3>@if($canEdit)<a href="{{ route($calendarRoute, array_merge($calendarParams, ['edit_event' => $eventId])) }}#calendar-forms">{{ $event['name'] }}</a>@else{{ $event['name'] }}@endif</h3>
        <p>{{ $event['starts_on'] }} &ndash; {{ $event['ends_on'] }}</p>
        <p>{{ $calendar['terms'][$event['term_id']]['academic_year'] }} / {{ $calendar['terms'][$event['term_id']]['name'] }}</p>
        <p>{{ $event['office_closed'] ? 'Office closed' : 'Normal office schedule' }}{{ $event['archived'] ? ' · Archived' : '' }}</p>
    </article>
    @empty<p class="portal-note">No events in this month. Use the month picker to browse historical or upcoming schedules.</p>@endforelse
</section>
</div>
