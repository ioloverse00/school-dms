<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\User;
use App\Support\RequestStatus;
use Carbon\Carbon;

class StaffingCapacity
{
    public function defaults(): array
    {
        return ['hours' => 8, 'productive_hours' => 6, 'days' => [1, 2, 3, 4, 5, 6], 'minutes' => 10];
    }

    public function assess(?string $month, int $requests): array
    {
        if (!$month) {
            return ['assessment_status' => 'capacity_inputs_needed'];
        }
        $assumptions = $this->defaults();
        $start = Carbon::createFromFormat('!Y-m', $month);
        $events = app(AcademicCalendar::class)->get()['events'];
        $days = 0;
        for ($date = $start->copy(); $date->month === $start->month; $date->addDay()) {
            if (!in_array($date->dayOfWeek, $assumptions['days'], true)) {
                continue;
            }
            $closed = false;
            foreach ($events as $event) {
                if (($event['office_closed'] ?? false) && $date->toDateString() >= $event['starts_on'] && $date->toDateString() <= $event['ends_on']) {
                    $closed = true;
                    break;
                }
            }
            if (!$closed) {
                $days++;
            }
        }

        // Match the Registrar Management list, including its role exclusions.
        $registrars = User::whereHas('roles', fn ($query) => $query->where('name', 'registrar')->where('guard_name', 'web'))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['admin', 'student']))
            ->count();
        $backlog = DocumentRequest::whereNull('archived_at')->whereIn('status', RequestStatus::ACTIONABLE)->count();
        $workloadHours = (max(0, $requests) + $backlog) * $assumptions['minutes'] / 60;
        $hoursPerRegistrar = $days * $assumptions['productive_hours'];
        $required = $hoursPerRegistrar > 0 ? (int) ceil($workloadHours / $hoursPerRegistrar) : null;
        $additional = $required !== null ? max(0, $required - $registrars) : null;

        return [
            'assessment_status' => $hoursPerRegistrar <= 0 ? 'no_working_days' : ($additional > 0 ? 'additional_staff_needed' : 'sufficient'),
            'additional_staff' => $additional,
            'registrar_count' => $registrars,
            'required_staff' => $required,
            'working_days' => $days,
            'productive_hours_per_staff_per_day' => $assumptions['productive_hours'],
            'backlog_requests' => $backlog,
            'workload_hours' => round($workloadHours, 1),
            'capacity_hours' => round($registrars * $hoursPerRegistrar, 1),
            'description' => 'Overall monthly estimate using registrar accounts, forecast requests and current actionable backlog. Assumes full attendance, 6 productive hours within an 8-hour shift, Monday-Saturday, and 10 minutes/request. Calendar closures excluded; absences and peak-day surges are not modeled.',
        ];
    }
}
