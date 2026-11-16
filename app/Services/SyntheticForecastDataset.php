<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/** Simulation only. Counts are assumptions, never observations about a school. */
class SyntheticForecastDataset
{
    public function build(int $seed): array
    {
        $terms = []; $events = []; $rows = [];
        $types = ['Transcript of Records', 'Certificate of Enrollment', 'Certificate of Grades'];
        for ($i = 0; $i < 6; $i++) {
            $year = 2023 + intdiv($i, 2);
            $start = CarbonImmutable::create($year, $i % 2 === 0 ? 1 : 8, 1)->startOfDay();
            $end = $start->addMonths(5)->subDay();
            $termId = sprintf('00000000-0000-4000-8000-%012d', $i + 1);
            $term = ['academic_year' => ($i % 2 === 0 ? ($year - 1).'-'.$year : $year.'-'.($year + 1)), 'name' => $i % 2 === 0 ? 'Second semester' : 'First semester', 'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(), 'archived' => true];
            $terms[$termId] = $term;
            foreach ([['enrollment', $start, $start->addDays(13), false], ['exams', $start->addWeeks(9), $start->addWeeks(10)->subDay(), false], ['clearance', $end->subDays(20), $end->subDays(7), false], ['graduation', $end->subDays(6), $end, false], ['holiday', $start->addWeeks(5), $start->addWeeks(5)->addDays(1), true]] as $j => [$type, $from, $to, $closed]) {
                $eventId = sprintf('00000000-0000-4000-9000-%012d', $i * 10 + $j + 1);
                $events[$eventId] = ['term_id' => $termId, 'name' => 'Demo '.ucfirst($type), 'type' => $type, 'starts_on' => $from->toDateString(), 'ends_on' => $to->toDateString(), 'office_closed' => $closed, 'archived' => true];
            }
            $split = $i < 4 ? 'train' : ($i === 4 ? 'validation' : 'test');
            // Assign a boundary week only to the term containing its Monday.
            // This prevents the same weekly date appearing in train and validation/test.
            $firstWeek = $start->isMonday() ? $start : $start->next('Monday');
            for ($week = $firstWeek; $week->lte($end); $week = $week->addWeek()) {
                $active = []; $working = 0; $counts = array_fill(0, count($types), 0);
                for ($day = $week; $day->lt($week->addWeek()); $day = $day->addDay()) {
                    if ($day->lt($start) || $day->gt($end)) continue;
                    $dailyEvents = []; $closed = false;
                    foreach ($events as $event) {
                        if ($event['term_id'] === $termId && $day->toDateString() >= $event['starts_on'] && $day->toDateString() <= $event['ends_on']) {
                            $dailyEvents[] = $event['type']; $active[$event['type']] = true; $closed = $closed || $event['office_closed'];
                        }
                    }
                    if ($day->isWeekend() || $closed) continue;
                    $working++;
                    foreach ($types as $t => $name) {
                        $noise = hexdec(substr(hash('sha256', "$seed|$i|$day|$t"), 0, 6)) % 9 - 4;
                        $season = (in_array('enrollment', $dailyEvents) ? 8 : 0) + (in_array('clearance', $dailyEvents) ? 6 : 0) + (in_array('graduation', $dailyEvents) ? 10 : 0);
                        // An unannounced test-period surge tests robustness; it is not an input feature.
                        $shock = $i === 5 && $day->betweenIncluded($start->addWeeks(12), $start->addWeeks(13)) ? 13 : 0;
                        $counts[$t] += max(0, 8 + $i + $season + $noise + $shock - $t * 2);
                    }
                }
                foreach ($types as $t => $name) {
                    $rows[] = ['source' => 'synthetic', 'week_start' => $week->toDateString(), 'academic_year' => $term['academic_year'], 'semester' => $term['name'], 'week_of_semester' => (int) floor($start->startOfWeek()->diffInDays($week) / 7) + 1, 'document_type' => $name, 'working_days' => $working, 'enrollment' => (int) isset($active['enrollment']), 'exams' => (int) isset($active['exams']), 'clearance' => (int) isset($active['clearance']), 'graduation' => (int) isset($active['graduation']), 'requests' => $counts[$t], 'split' => $split];
                }
            }
        }
        return ['calendar' => ['source' => 'synthetic_assumed_calendar', 'terms' => $terms, 'events' => $events], 'rows' => $rows,
            'manifest' => ['source' => 'synthetic', 'generator_version' => 1, 'seed' => $seed, 'semesters' => 6, 'rows' => count($rows), 'split' => 'First four semesters train; fifth validation; sixth final test. Do not tune using the test period.', 'assumptions' => 'Invented January-May/August-December terms, Monday-Friday office, assumed event demand effects and processing volumes. Exam flags do not automatically increase demand. Not client observations.', 'limitations' => 'No model is trained by generation. No accuracy or staffing claim. Weekly counts represent requests, not copies or unique people. Validate unseen scenarios and real records before operational use.']];
    }
}
