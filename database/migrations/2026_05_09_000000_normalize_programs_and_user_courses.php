<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $programs = config('programs.default', []);

        if (!empty($programs)) {
            SystemSetting::set('programs', $programs);
        }

        $map = [
            // Common short names -> official list
            'BS Information Technology' => 'Bachelor of Science in Information Technology (BSIT)',
            'BS Information Systems' => 'Bachelor of Science in Information Systems (BSIS)',
            'BS Computer Engineering' => 'Bachelor of Science in Computer Engineering (BSCpE)',
            'BS Criminology' => 'Bachelor of Science in Criminology (BSCrim)',
            'BS Psychology' => 'Bachelor of Science in Psychology (BSPsych)',
            'BS Business Administration' => 'Bachelor of Science in Business Administration (BSBA)',
            'BS Entrepreneurship' => 'Bachelor of Science in Entrepreneurship (BSENTREP)',
            'BS Hospitality Management' => 'Bachelor of Science in Hospitality Management (BSHM)',
            'BS Tourism Management' => 'Bachelor of Science in Tourism Management (BSTM)',
            'BS Accountancy' => 'Bachelor of Science in Accountancy (BSA)',
            'BS Accounting Information System' => 'Bachelor of Science in Accounting Information System (BSAIS)',
            'Bachelor of Elementary Education' => 'Bachelor of Elementary Education (BEEd)',
            'Bachelor of Secondary Education' => 'Bachelor of Secondary Education (BSEd)',
            'Bachelor of Physical Education' => 'Bachelor of Physical Education (BPED)',
            'Bachelor of Technical-Vocational Teacher Education' => 'Bachelor of Technical-Vocational Teacher Education (BTVTEd)',
            'BS Office Administration' => 'Bachelor of Science in Office Administration (BSOA)',

            // Abbreviation-only values (if ever used)
            'BSIT' => 'Bachelor of Science in Information Technology (BSIT)',
            'BSIS' => 'Bachelor of Science in Information Systems (BSIS)',
            'BSCpE' => 'Bachelor of Science in Computer Engineering (BSCpE)',
            'BSCrim' => 'Bachelor of Science in Criminology (BSCrim)',
            'BSPsych' => 'Bachelor of Science in Psychology (BSPsych)',
            'BSBA' => 'Bachelor of Science in Business Administration (BSBA)',
            'BSENTREP' => 'Bachelor of Science in Entrepreneurship (BSENTREP)',
            'BSHM' => 'Bachelor of Science in Hospitality Management (BSHM)',
            'BSTM' => 'Bachelor of Science in Tourism Management (BSTM)',
            'BSA' => 'Bachelor of Science in Accountancy (BSA)',
            'BSAIS' => 'Bachelor of Science in Accounting Information System (BSAIS)',
            'BEEd' => 'Bachelor of Elementary Education (BEEd)',
            'BSEd' => 'Bachelor of Secondary Education (BSEd)',
            'BPED' => 'Bachelor of Physical Education (BPED)',
            'BTVTEd' => 'Bachelor of Technical-Vocational Teacher Education (BTVTEd)',
            'BSOA' => 'Bachelor of Science in Office Administration (BSOA)',
        ];

        foreach ($map as $old => $new) {
            DB::table('users')->where('course', $old)->update(['course' => $new]);
        }

        if (!empty($programs)) {
            DB::table('users')
                ->whereNotNull('course')
                ->whereNotIn('course', $programs)
                ->update(['course' => null]);
        }
    }

    public function down(): void
    {
        // No down migration: this is a data normalization step.
    }
};

