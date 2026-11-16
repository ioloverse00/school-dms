@extends('layouts.dashboard')
@section('title', 'Academic calendar')
@section('content')
@php
    $formKind = old('kind') ?? ($editYear ? 'year' : ($editTerm ? 'term' : ($editEvent ? 'event' : request()->query('form'))));
    if (!in_array($formKind, ['year', 'term', 'event'], true) || !$canEdit) $formKind = null;
    $panel = request()->query('panel');
    $defaultStartYear = now()->month >= 6 ? now()->year : now()->year - 1;
@endphp
<div class="portal-page-header calendar-page-heading"><div><h1 class="portal-heading">Academic calendar</h1><p class="portal-subtitle">Enrollment, exams, graduation and office schedules in one place.</p></div>
@if($canEdit && !$formKind && $selectedYear)<div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="{{ route($calendarRoute, array_merge($calendarParams, ['form' => 'term'])) }}">Add semester</a><a class="btn btn-primary" href="{{ route($calendarRoute, array_merge($calendarParams, ['form' => 'event'])) }}">Add event</a></div>@endif
</div>
@if(session('success'))<div class="alert alert-success mb-4" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger mb-4" role="alert"><strong>Please check the following:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@if($errors->has('revision'))<p class="mb-0 mt-2">Reload this page before editing again.</p>@endif</div>@endif
@if(!$formKind)
<nav class="calendar-view-nav" aria-label="Calendar views">
<a href="{{ route($calendarRoute, array_merge($calendarParams, ['panel' => 'years'])) }}" @if($panel === 'years') aria-current="page" @endif>Academic years</a>
<a href="{{ route($calendarRoute, $calendarParams) }}" @if(!in_array($panel, ['years', 'semesters', 'history'], true)) aria-current="page" @endif>Calendar</a>
<a href="{{ route($calendarRoute, array_merge($calendarParams, ['panel' => 'semesters'])) }}" @if($panel === 'semesters') aria-current="page" @endif>Semesters</a>
@if($canEdit)<a href="{{ route($calendarRoute, array_merge($calendarParams, ['panel' => 'history'])) }}" @if($panel === 'history') aria-current="page" @endif>Change history</a>@endif
</nav>
@if(!in_array($panel, ['years', 'semesters', 'history'], true) || ($panel === 'history' && !$canEdit))
@if($selectedYear)
@include('partials.academic-calendar-panel')
@else
<section class="card portal-card"><h2>Set up your academic year</h2><p class="portal-subtitle mb-3">Start with the school year, then add its semesters and events.</p>@if($canEdit)<a class="btn btn-primary" href="{{ route($calendarRoute, ['form' => 'year']) }}">Add academic year</a>@endif</section>
@endif
@endif
@endif
@if(!$formKind && $panel === 'years')
<section class="card portal-card mb-4"><div class="card-header portal-card-header"><h2>Academic years</h2>@if($canEdit)<a class="btn btn-primary" href="{{ route($calendarRoute, ['form' => 'year']) }}">Add academic year</a>@endif</div>
<div class="portal-table-wrap"><table class="table table-custom portal-table"><thead><tr><th>Academic year</th><th>Starts</th><th>Ends</th><th>State</th>@if($canEdit)<th>Action</th>@endif</tr></thead><tbody>
@forelse($calendar['years'] as $id => $year)<tr><td><a href="{{ route($calendarRoute, ['year' => $id]) }}">{{ $year['name'] }}</a></td><td>{{ $year['starts_on'] }}</td><td>{{ $year['ends_on'] }}</td><td>{{ $year['archived'] ? 'Archived' : 'Active' }}</td>@if($canEdit)<td><a href="{{ route($calendarRoute, ['edit_year' => $id]) }}">Edit</a></td>@endif</tr>@empty<tr><td colspan="5">Add an academic year to start planning.</td></tr>@endforelse
</tbody></table></div></section>
@endif
@if(!$formKind && $panel === 'semesters')
<section class="card portal-card mb-4" aria-labelledby="semester-title"><div class="card-header portal-card-header"><h2 id="semester-title">Academic years &amp; semesters</h2></div>
<div class="portal-table-wrap"><table class="table table-custom portal-table"><thead><tr><th>Academic year</th><th>Semester</th><th>Dates</th><th>State</th>@if($canEdit)<th>Action</th>@endif</tr></thead><tbody>
@forelse($yearTerms as $id => $term)<tr><td>{{ $term['academic_year'] }}</td><td>{{ $term['name'] }}</td><td>{{ $term['starts_on'] }} &ndash; {{ $term['ends_on'] }}</td><td>{{ $term['archived'] ? 'Archived' : 'Active' }}</td>@if($canEdit)<td><a class="portal-inline-link" href="{{ route('admin.settings.academic-calendar', array_merge($calendarParams, ['edit_term' => $id])) }}#calendar-forms">Edit</a></td>@endif</tr>
@empty<tr><td colspan="{{ $canEdit ? 5 : 4 }}">No semesters yet. Add a semester before adding events.</td></tr>@endforelse
</tbody></table></div></section>
@endif

@if($canEdit && $formKind)
<p class="mb-4"><a class="portal-inline-link" href="{{ route($calendarRoute, $calendarParams) }}">&larr; Back to calendar</a></p>
<div class="calendar-editor" id="calendar-forms">
@foreach([$formKind] as $kind)
@php
    $editingId = $kind === 'year' ? $editYear : ($kind === 'term' ? $editTerm : $editEvent);
    if(old('kind') === $kind) $editingId = old('id');
    $item = $calendar[['year' => 'years', 'term' => 'terms', 'event' => 'events'][$kind]][$editingId ?? ''] ?? [];
    $field = fn($key, $default = '') => old('kind') === $kind ? old($key, $default) : ($item[$key] ?? $default);
@endphp
<section class="card portal-card"><div class="card-header portal-card-header"><h2>{{ $editingId ? 'Edit' : 'Add' }} {{ ['year' => 'academic year', 'term' => 'semester', 'event' => 'event'][$kind] }}</h2></div>
<form action="{{ route('admin.settings.academic-calendar.save') }}" method="POST" class="calendar-form">
@csrf
<input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="revision" value="{{ old('kind') === $kind ? old('revision', $calendar['revision']) : $calendar['revision'] }}">
@if($editingId)<input type="hidden" name="id" value="{{ $editingId }}">@endif
<div><label for="{{ $kind }}-name">{{ ['year' => 'Academic year name', 'term' => 'Semester name', 'event' => 'Event name'][$kind] }}</label><input class="spark-field form-control-custom" id="{{ $kind }}-name" name="name" maxlength="100" required value="{{ $field('name', $kind === 'year' ? $defaultStartYear.'-'.($defaultStartYear + 1) : '') }}" placeholder="{{ $kind === 'term' ? 'First semester' : 'Enrollment period' }}"></div>
@if($kind === 'year')
<p class="portal-note">Default school year: June 1 to May 31. Adjust the dates below if your school follows a different schedule.</p>
@elseif($kind === 'term')
<div><label for="term-year">Academic year</label><select class="spark-field form-select-custom" id="term-year" name="year_id" required><option value="">Select academic year</option>@foreach($calendar['years'] as $id => $year)<option value="{{ $id }}" @selected($field('year_id', $yearId) === $id)>{{ $year['name'] }}</option>@endforeach</select></div>
@else
<div><label for="event-term">Semester</label><select class="spark-field form-select-custom" id="event-term" name="term_id" required><option value="">Select semester</option>@foreach($yearTerms as $termId => $term)<option value="{{ $termId }}" @selected($field('term_id') === $termId)>{{ $term['academic_year'] }} / {{ $term['name'] }}{{ $term['archived'] ? ' (archived)' : '' }}</option>@endforeach</select></div>
<div><label for="event-type">Event type</label><select class="spark-field form-select-custom" id="event-type" name="type" required>@foreach(\App\Services\AcademicCalendar::TYPES as $type)<option value="{{ $type }}" @selected($field('type', 'enrollment') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>@endforeach</select></div>
<p class="portal-note">Enrollment may start before classes, within the academic year, and must end by semester end. Graduation may fall anywhere within its academic year. Other events must fall within the semester.</p>
@endif
<div class="calendar-date-grid"><div><label for="{{ $kind }}-start">Start date</label><input class="spark-field form-control-custom" type="date" id="{{ $kind }}-start" name="starts_on" required value="{{ $field('starts_on', $kind === 'year' ? $defaultStartYear.'-06-01' : '') }}"></div><div><label for="{{ $kind }}-end">End date (inclusive)</label><input class="spark-field form-control-custom" type="date" id="{{ $kind }}-end" name="ends_on" required value="{{ $field('ends_on', $kind === 'year' ? ($defaultStartYear + 1).'-05-31' : '') }}"></div></div>
@if($kind === 'event')<label><input type="checkbox" name="office_closed" value="1" @checked($field('office_closed', false))> Office closed throughout this event</label>@endif
<label><input type="checkbox" name="archived" value="1" @checked($field('archived', false))> Archived (retain historical record)</label>
<div class="d-flex gap-3 align-items-center"><button class="btn btn-primary" type="submit">Save {{ ['year' => 'academic year', 'term' => 'semester', 'event' => 'event'][$kind] }}</button><a href="{{ route($calendarRoute, $calendarParams) }}">Cancel</a></div>
</form></section>
@endforeach
</div>
@endif
@if($canEdit && !$formKind && $panel === 'history')
<section class="card portal-card mt-4"><div class="card-header portal-card-header"><h2>Recent changes</h2></div><ul class="calendar-history">@forelse(array_reverse(array_slice($calendar['history'], -10)) as $change)<li>Revision {{ $change['revision'] }} &middot; {{ $change['at'] }} &middot; Admin #{{ $change['actor_id'] }} &middot; {{ $change['before'] ? 'Updated' : 'Added' }} {{ $change['kind'] }}: {{ $change['after']['name'] }}</li>@empty<li>No changes yet.</li>@endforelse</ul><p class="portal-note">The full before/after change history is retained in settings storage.</p></section>
@endif
@endsection
