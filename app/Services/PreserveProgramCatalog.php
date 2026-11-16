<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PreserveProgramCatalog
{
    public function handle(): void
    {
        DB::transaction(function () {
            $setting = SystemSetting::where('key', 'programs')->lockForUpdate()->first();
            $existing = $setting ? json_decode($setting->value, true, 512, JSON_THROW_ON_ERROR) : [];
            if (! is_array($existing) || array_filter($existing, fn ($value) => ! is_string($value)) !== []) {
                throw new RuntimeException('Program catalog has an invalid structure. Review it before migration.');
            }
            $programs = [];
            $append = function (string $value) use (&$programs) {
                if ($value !== '') {
                    // Exact deduplication preserves case/custom labels across database collations.
                    $programs[hash('sha256', $value)] = $value;
                }
            };
            foreach ([...$existing, ...config('programs.default', [])] as $program) {
                $append($program);
            }
            foreach (DB::table('users')->select('course')->whereNotNull('course')->orderBy('id')->cursor() as $user) {
                $append($user->course);
            }
            $merged = array_values($programs);
            if (! $setting || $merged !== $existing) {
                SystemSetting::set('programs', $merged);
            }
            // Never rewrite user courses, guess aliases or clear unmatched values.
        });
    }
}
