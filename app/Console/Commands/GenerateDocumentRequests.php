<?php

namespace App\Console\Commands;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateDocumentRequests extends Command
{
    protected $signature = 'db:generate-requests
        {months=12 : How many past months to generate (including current)}
        {per_month=30 : Base requests per month}
        {--seed= : Random seed for repeatable data}';

    protected $description = 'Generate demo document requests with seasonal patterns for forecasting tests.';

    public function handle(): int
    {
        $months = max(1, min(60, (int) $this->argument('months')));
        $perMonth = max(1, min(2000, (int) $this->argument('per_month')));
        $seed = $this->option('seed');

        if ($seed !== null && $seed !== '') {
            mt_srand((int) $seed);
        }

        $students = User::role('student')->pluck('id')->all();
        if (count($students) === 0) {
            $this->error('No student users found. Run db:generate-students first.');
            return self::FAILURE;
        }

        $types = DocumentType::all();
        if ($types->isEmpty()) {
            $this->error('No document types found. Run migrations/seeders first.');
            return self::FAILURE;
        }

        $typeGroups = $this->groupDocumentTypes($types);

        $urgencyLevels = SystemSetting::get('urgency_level_values', ['regular' => 1, 'urgent' => 2, 'very_urgent' => 3]);
        $studentWeights = SystemSetting::get('student_category_weights', ['regular' => 2, 'graduating' => 5, 'alumni' => 3]);
        $urgencyWeight = SystemSetting::get('urgency_weight', 5);

        $created = 0;
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $count = (int) round($perMonth * $this->monthMultiplier($month));
            $count = max(1, $count);

            for ($j = 0; $j < $count; $j++) {
                $userId = $students[array_rand($students)];
                $user = User::find($userId);
                if (!$user) {
                    continue;
                }

                $docType = $this->pickDocTypeForMonth($month, $typeGroups, $user->student_type);
                if (!$docType) {
                    continue;
                }

                $urgency = $this->pickUrgency();
                $copies = $this->pickCopies();
                $purpose = $this->pickPurpose($docType->name);
                $status = $this->pickStatus();

                $urgencyScore = ($urgencyLevels[$urgency] ?? 1) * $urgencyWeight;
                $studentScore = $studentWeights[$user->student_type] ?? 1;
                $documentScore = (int) ($docType->weight ?? 1);
                $priorityScore = $urgencyScore + $studentScore + $documentScore;

                $createdAt = $this->randomDateInMonth($month);

                DocumentRequest::unguarded(function () use (
                    $user,
                    $docType,
                    $purpose,
                    $copies,
                    $urgency,
                    $priorityScore,
                    $status,
                    $createdAt,
                    &$created
                ) {
                    $req = DocumentRequest::create([
                        'request_id' => 'REQ-' . $createdAt->format('Y') . '-' . strtoupper(Str::random(5)),
                        'user_id' => $user->id,
                        'document_type_id' => $docType->id,
                        'purpose' => $purpose,
                        'copies' => $copies,
                        'urgency' => $urgency,
                        'student_category' => $user->student_type,
                        'priority_score' => $priorityScore,
                        'status' => $status,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    // Ensure uniqueness for request_id collisions (rare)
                    if (!$req) {
                        return;
                    }
                    $created++;
                });
            }

            $this->line($month->format('Y-m') . ': ' . $count . ' request(s)');
        }

        $this->newLine();
        $this->info("Created {$created} document request(s) across {$months} month(s).");
        return self::SUCCESS;
    }

    private function monthMultiplier(Carbon $month): float
    {
        // Seasonal assumptions:
        // - May graduation (2nd week) => spike in May + June
        // - Late May onward 1st sem enrollment => spike in late May + June + July
        // - October 2nd sem enrollment => spike in Oct + early Nov
        return match ((int) $month->month) {
            5 => 1.8,
            6 => 1.6,
            7 => 1.3,
            10 => 1.5,
            11 => 1.2,
            default => 1.0,
        };
    }

    private function groupDocumentTypes($types): array
    {
        $graduation = [];
        $enrollment = [];
        $others = [];
        $all = [];

        foreach ($types as $t) {
            $all[] = $t;
            $name = strtolower((string) $t->name);
            if (str_contains($name, 'transcript') || str_contains($name, 'graduation') || str_contains($name, 'diploma')) {
                $graduation[] = $t;
                continue;
            }
            if (str_contains($name, 'enrollment') || str_contains($name, 'grades') || str_contains($name, 'good moral')) {
                $enrollment[] = $t;
                continue;
            }
            $others[] = $t;
        }

        return [
            'graduation' => $graduation,
            'enrollment' => $enrollment,
            'others' => $others,
            'all' => $all,
        ];
    }

    private function pickDocTypeForMonth(Carbon $month, array $groups, string $studentType)
    {
        $m = (int) $month->month;

        // Weight seasonal types
        $pool = [];
        if (in_array($m, [5, 6], true)) {
            $pool = array_merge(
                array_fill(0, 5, 'graduation'),
                array_fill(0, 3, 'enrollment'),
                array_fill(0, 2, 'others'),
            );
        } elseif (in_array($m, [6, 7], true)) {
            $pool = array_merge(
                array_fill(0, 4, 'enrollment'),
                array_fill(0, 2, 'graduation'),
                array_fill(0, 2, 'others'),
            );
        } elseif (in_array($m, [10, 11], true)) {
            $pool = array_merge(
                array_fill(0, 5, 'enrollment'),
                array_fill(0, 1, 'graduation'),
                array_fill(0, 2, 'others'),
            );
        } else {
            $pool = array_merge(
                array_fill(0, 2, 'enrollment'),
                array_fill(0, 1, 'graduation'),
                array_fill(0, 5, 'others'),
            );
        }

        $group = $pool[array_rand($pool)];
        $candidates = $groups[$group] ?? [];
        $fallback = $groups['others'] ?? [];
        $all = $groups['all'] ?? [];

        // Alumni-only docs (e.g., Diploma Copy) should be requested by alumni
        $tries = 0;
        while ($tries < 10) {
            $picked = $candidates ? $candidates[array_rand($candidates)] : null;
            if (!$picked) {
                break;
            }
            if (!empty($picked->alumni_only) && $studentType !== 'alumni') {
                $tries++;
                continue;
            }
            return $picked;
        }

        // Fallback to any allowed type
        foreach (range(1, 10) as $_) {
            $picked = $fallback ? $fallback[array_rand($fallback)] : ($all ? $all[array_rand($all)] : null);
            if (!$picked) {
                continue;
            }
            if (!empty($picked->alumni_only) && $studentType !== 'alumni') {
                continue;
            }
            return $picked;
        }

        return null;
    }

    private function pickUrgency(): string
    {
        $roll = random_int(1, 100);
        return $roll <= 70 ? 'regular' : ($roll <= 92 ? 'urgent' : 'very_urgent');
    }

    private function pickCopies(): int
    {
        $roll = random_int(1, 100);
        return $roll <= 75 ? 1 : ($roll <= 92 ? 2 : 3);
    }

    private function pickStatus(): string
    {
        $roll = random_int(1, 100);
        return $roll <= 65 ? 'completed' : ($roll <= 80 ? 'ready_for_release' : ($roll <= 92 ? 'processing' : 'pending_verification'));
    }

    private function pickPurpose(string $docName): string
    {
        $name = strtolower($docName);
        if (str_contains($name, 'enrollment')) return 'Enrollment';
        if (str_contains($name, 'grades')) return 'Scholarship / Requirements';
        if (str_contains($name, 'transcript')) return 'Employment / Board Exam';
        if (str_contains($name, 'graduation')) return 'Graduation Requirement';
        if (str_contains($name, 'good moral')) return 'Enrollment Requirement';
        return 'Personal Use';
    }

    private function randomDateInMonth(Carbon $month): Carbon
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $days = max(1, $start->diffInDays($end) + 1);
        $dayOffset = random_int(0, $days - 1);
        $dt = $start->copy()->addDays($dayOffset);
        $dt->setTime(random_int(8, 18), random_int(0, 59), random_int(0, 59));
        return $dt;
    }
}
