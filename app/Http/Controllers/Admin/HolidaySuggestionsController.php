<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\{AcademicCalendar, HolidaySuggestions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HolidaySuggestionsController extends Controller
{
    public function check(Request $request, AcademicCalendar $calendar, HolidaySuggestions $suggestions)
    {
        $input = $request->validate(['year_id' => ['required', 'uuid']]);
        $year = $calendar->get()['years'][$input['year_id']] ?? null;
        abort_unless($year, 404);
        $suggestions->check($input['year_id'], $year);
        return to_route('admin.settings.academic-calendar', ['year' => $input['year_id'], 'panel' => 'holidays'])->with('success', 'Holiday sources checked. Review suggestions before saving events.');
    }

    public function apply(Request $request, AcademicCalendar $calendar, HolidaySuggestions $suggestions)
    {
        $input = $request->validate(['year_id' => ['required', 'uuid'], 'suggestion_id' => ['required', 'uuid'], 'revision' => ['required', 'integer'], 'office_closed' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($request, $input, $calendar, $suggestions) {
            $saved = $calendar->get(); $year = $saved['years'][$input['year_id']] ?? null;
            abort_unless($year, 404);
            $check = $suggestions->saved($input['year_id']);
            $holiday = collect($check['holidays'] ?? [])->firstWhere('id', $input['suggestion_id']);
            if (!$holiday || !$holiday['date'] || ($check['year_starts_on'] ?? null) !== $year['starts_on'] || ($check['year_ends_on'] ?? null) !== $year['ends_on']) throw ValidationException::withMessages(['holidays' => 'Recheck holiday suggestions for this academic year.']);
            $existing = collect($saved['events'])->first(fn ($event) => ($event['year_id'] ?? $saved['terms'][$event['term_id']]['year_id'] ?? null) === $input['year_id'] && $event['type'] === 'holiday' && mb_strtolower($event['name']) === mb_strtolower($holiday['name']));
            if ($existing) throw ValidationException::withMessages(['holidays' => 'A holiday with this name already exists. Review and edit its saved event instead.']);
            $calendar->save(['kind' => 'event', 'revision' => $input['revision'], 'name' => $holiday['name'], 'year_id' => $input['year_id'], 'term_id' => null, 'type' => 'holiday', 'starts_on' => $holiday['date'], 'ends_on' => $holiday['date'], 'holiday_classification' => $holiday['classification'], 'office_closed' => in_array($holiday['classification'], ['regular', 'special_non_working'], true), 'archived' => false], $request->user()->id);
        });
        return to_route('admin.settings.academic-calendar', ['year' => $input['year_id'], 'panel' => 'holidays'])->with('success', 'Holiday added to the calendar.');
    }
}
