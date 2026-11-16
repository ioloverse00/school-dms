<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicCalendar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicCalendarController extends Controller
{
    public function index(Request $request, AcademicCalendar $service)
    {
        $calendar = $service->get();
        $canEdit = $request->user()->hasRole('admin');
        $editTerm = is_string($request->query('edit_term')) ? $request->query('edit_term') : null;
        $editEvent = is_string($request->query('edit_event')) ? $request->query('edit_event') : null;
        $editYear = is_string($request->query('edit_year')) ? $request->query('edit_year') : null;
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'year' => ['nullable', 'uuid']]);
        $contextTerm = $editTerm ?? ($calendar['events'][$editEvent ?? '']['term_id'] ?? null);
        $yearId = $request->query('year') ?: ($editYear ?? ($calendar['terms'][$contextTerm ?? '']['year_id'] ?? null));
        if ($yearId) abort_unless(isset($calendar['years'][$yearId]), 404);
        $contextDate = $calendar['events'][$editEvent ?? '']['starts_on'] ?? $calendar['terms'][$editTerm ?? '']['starts_on'] ?? now()->toDateString();
        if (!$yearId) {
            $targetDate = $request->query('month') ? $request->query('month').'-01' : $contextDate;
            $yearId = collect($calendar['years'])->sortByDesc('starts_on')->filter(fn ($year) => $year['starts_on'] <= $targetDate && $year['ends_on'] >= $targetDate)->keys()->first()
                ?? collect($calendar['years'])->sortByDesc('starts_on')->keys()->first();
        }
        $selectedYear = $calendar['years'][$yearId ?? ''] ?? null;
        $month = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $request->query('month') ?: substr($contextDate, 0, 7))->startOfMonth();
        if ($selectedYear && ($month->format('Y-m') < substr($selectedYear['starts_on'], 0, 7) || $month->format('Y-m') > substr($selectedYear['ends_on'], 0, 7))) {
            $month = \Carbon\CarbonImmutable::parse($selectedYear['starts_on'])->startOfMonth();
        }
        $yearTerms = collect($calendar['terms'])->filter(fn ($term) => !$yearId || $term['year_id'] === $yearId);
        $monthEvents = collect($calendar['events'])->filter(fn ($event) => $yearTerms->has($event['term_id']) && $event['starts_on'] <= $month->endOfMonth()->toDateString() && $event['ends_on'] >= $month->toDateString())->sortBy('starts_on');
        $calendarParams = array_filter(['year' => $yearId, 'month' => $month->format('Y-m')]);
        $calendarRoute = $canEdit ? 'admin.settings.academic-calendar' : 'registrar.academic-calendar';
        return view('admin.settings.academic-calendar', compact('calendar', 'canEdit', 'editTerm', 'editEvent', 'editYear', 'month', 'monthEvents', 'calendarRoute', 'yearId', 'selectedYear', 'yearTerms', 'calendarParams'));
    }

    public function store(Request $request, AcademicCalendar $service)
    {
        $input = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'kind' => ['required', Rule::in(['year', 'term', 'event'])],
            'id' => ['nullable', 'uuid'], 'name' => ['required', 'string', 'max:100'],
            'year_id' => ['required_if:kind,term', 'nullable', 'uuid'],
            'term_id' => ['required_if:kind,event', 'nullable', 'uuid'],
            'type' => ['required_if:kind,event', 'nullable', Rule::in(AcademicCalendar::TYPES)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'office_closed' => ['sometimes', 'boolean'], 'archived' => ['sometimes', 'boolean'],
        ]);
        $service->save($input, $request->user()->id);
        $calendar = $service->get();
        $yearId = $input['kind'] === 'term' ? $input['year_id'] : ($input['kind'] === 'event' ? $calendar['terms'][$input['term_id']]['year_id'] : ($input['id'] ?? array_key_last($calendar['years'])));
        return redirect()->route('admin.settings.academic-calendar', ['year' => $yearId])->with('success', 'Calendar saved.');
    }
}
