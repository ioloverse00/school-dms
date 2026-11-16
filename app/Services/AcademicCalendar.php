<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicCalendar
{
    public const KEY = 'academic_calendar';
    public const TYPES = ['enrollment', 'exams', 'clearance', 'graduation', 'semester_end', 'holiday', 'other'];

    public function get(): array
    {
        return $this->normalize(SystemSetting::get(self::KEY, ['revision' => 0, 'years' => [], 'terms' => [], 'events' => [], 'history' => []]));
    }

    private function normalize(array $calendar): array
    {
        $calendar['years'] ??= [];
        foreach ($calendar['terms'] as &$term) {
            if (isset($term['year_id'], $calendar['years'][$term['year_id']])) continue;
            $label = $term['academic_year'];
            $hash = md5('academic-year:'.$label);
            $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
            if (!isset($calendar['years'][$id])) {
                $from = $term['starts_on']; $to = $term['ends_on'];
                if (preg_match('/^(\d{4})-(\d{4})$/', $label, $matches) && (int) $matches[2] === (int) $matches[1] + 1) {
                    $from = min($from, $matches[1].'-06-01'); $to = max($to, $matches[2].'-05-31');
                }
                $calendar['years'][$id] = ['name' => $label, 'starts_on' => $from, 'ends_on' => $to, 'archived' => true];
            }
            $calendar['years'][$id]['starts_on'] = min($calendar['years'][$id]['starts_on'], $term['starts_on']);
            $calendar['years'][$id]['ends_on'] = max($calendar['years'][$id]['ends_on'], $term['ends_on']);
            $calendar['years'][$id]['archived'] = $calendar['years'][$id]['archived'] && $term['archived'];
            $term['year_id'] = $id;
        }
        unset($term);
        return $calendar;
    }

    private function eventBounds(string $type, array $term, array $year): array
    {
        if ($type === 'graduation') return $year;
        if ($type === 'enrollment') return ['starts_on' => $year['starts_on'], 'ends_on' => $term['ends_on']];
        return $term;
    }

    public function save(array $input, int $actorId): void
    {
        DB::transaction(function () use ($input, $actorId) {
            SystemSetting::query()->insertOrIgnore(['key' => self::KEY, 'value' => json_encode(['revision' => 0, 'terms' => [], 'events' => [], 'history' => []]), 'created_at' => now(), 'updated_at' => now()]);
            $row = SystemSetting::where('key', self::KEY)->lockForUpdate()->firstOrFail();
            $calendar = $this->normalize(json_decode($row->value, true));
            if ((int) $input['revision'] !== $calendar['revision']) {
                throw ValidationException::withMessages(['revision' => 'The calendar changed in another session. Reload before saving.']);
            }
            $bucket = ['year' => 'years', 'term' => 'terms', 'event' => 'events'][$input['kind']];
            $id = $input['id'] ?? null;
            $before = $id ? ($calendar[$bucket][$id] ?? null) : null;
            if ($id && !$before) {
                throw ValidationException::withMessages(['id' => 'This calendar item no longer exists.']);
            }
            $fields = ['years' => ['name', 'starts_on', 'ends_on'], 'terms' => ['name', 'year_id', 'starts_on', 'ends_on'], 'events' => ['name', 'term_id', 'type', 'starts_on', 'ends_on', 'office_closed']];
            $item = collect($input)->only($fields[$bucket])->all();
            $item['archived'] = (bool) ($input['archived'] ?? false);
            if ($bucket === 'events') {
                $term = $calendar['terms'][$item['term_id']] ?? null;
                $bounds = $term ? $this->eventBounds($item['type'], $term, $calendar['years'][$term['year_id']]) : null;
                if (!$bounds || $item['starts_on'] < $bounds['starts_on'] || $item['ends_on'] > $bounds['ends_on']) {
                    throw ValidationException::withMessages(['term_id' => $item['type'] === 'graduation' ? 'Graduation dates must fall within the selected semester’s academic year.' : 'Event dates must fall within an existing semester.']);
                }
                $item['office_closed'] = (bool) ($item['office_closed'] ?? false);
            } elseif ($bucket === 'terms') {
                $year = $calendar['years'][$item['year_id']] ?? null;
                if (!$year || $item['starts_on'] < $year['starts_on'] || $item['ends_on'] > $year['ends_on']) {
                    throw ValidationException::withMessages(['year_id' => 'Semester dates must fall within the selected academic year.']);
                }
                $item['academic_year'] = $year['name'];
                foreach ($calendar['events'] as $event) {
                    $bounds = $this->eventBounds($event['type'], $item, $year);
                    if ($id && $event['term_id'] === $id && ($event['starts_on'] < $bounds['starts_on'] || $event['ends_on'] > $bounds['ends_on'])) {
                        throw ValidationException::withMessages(['starts_on' => 'These dates would exclude a saved event. Update its dates first.']);
                    }
                }
                foreach ($calendar['terms'] as $otherId => $term) {
                    if ($otherId !== $id && mb_strtolower($term['name']) === mb_strtolower($item['name']) && mb_strtolower($term['academic_year']) === mb_strtolower($item['academic_year'])) {
                        throw ValidationException::withMessages(['name' => 'That semester already exists in this academic year.']);
                    }
                }
            } else {
                foreach ($calendar['years'] as $otherId => $year) {
                    if ($otherId !== $id && mb_strtolower($year['name']) === mb_strtolower($item['name'])) {
                        throw ValidationException::withMessages(['name' => 'That academic year already exists.']);
                    }
                }
                foreach ($calendar['terms'] as &$term) {
                    if (($term['year_id'] ?? null) !== $id) continue;
                    if ($term['starts_on'] < $item['starts_on'] || $term['ends_on'] > $item['ends_on']) {
                        throw ValidationException::withMessages(['starts_on' => 'These dates would exclude an existing semester. Update it first.']);
                    }
                    $term['academic_year'] = $item['name'];
                    foreach ($calendar['events'] as $event) {
                        if (($calendar['terms'][$event['term_id']]['year_id'] ?? null) === $id && ($event['starts_on'] < $item['starts_on'] || $event['ends_on'] > $item['ends_on'])) {
                            throw ValidationException::withMessages(['starts_on' => 'These dates would exclude a saved event. Update its dates first.']);
                        }
                    }
                }
                unset($term);
            }
            $id ??= (string) \Illuminate\Support\Str::uuid();
            $calendar[$bucket][$id] = $item;
            $calendar['revision']++;
            $calendar['history'][] = ['revision' => $calendar['revision'], 'actor_id' => $actorId, 'at' => now()->toIso8601String(), 'kind' => $input['kind'], 'id' => $id, 'before' => $before, 'after' => $item];
            // The comparison also protects SQLite, where row-level locks are unavailable.
            $updated = SystemSetting::where('id', $row->id)->where('value', $row->value)->update(['value' => json_encode($calendar), 'updated_at' => now()]);
            if (!$updated) {
                throw ValidationException::withMessages(['revision' => 'The calendar changed. Reload before saving.']);
            }
        });
    }
}
