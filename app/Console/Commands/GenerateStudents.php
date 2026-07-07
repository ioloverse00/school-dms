<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateStudents extends Command
{
    protected $signature = 'db:generate-students {count=50 : Number of students to create} {--password=student123 : Default password for created accounts}';
    protected $description = 'Generate demo student accounts for local testing.';

    public function handle(): int
    {
        $count = max(1, min(5000, (int) $this->argument('count')));
        $password = (string) $this->option('password');

        $programs = SystemSetting::get('programs', config('programs.default', []));
        if (empty($programs)) {
            $this->error('No programs found. Set programs in System Settings first.');
            return self::FAILURE;
        }

        $created = 0;
        $studentTypes = ['regular', 'graduating', 'alumni'];

        while ($created < $count) {
            $studentId = (string) random_int(200000000, 219999999);
            $email = 'student' . $studentId . '@school.local';

            if (User::where('student_id', $studentId)->orWhere('email', $email)->exists()) {
                continue;
            }

            $name = fake()->name();
            $course = $programs[array_rand($programs)];

            // Bias towards regular, then graduating, then alumni
            $roll = random_int(1, 100);
            $studentType = $roll <= 70 ? 'regular' : ($roll <= 90 ? 'graduating' : 'alumni');

            $yearGraduated = null;
            if ($studentType === 'alumni') {
                $yearGraduated = (string) random_int((int) date('Y') - 6, (int) date('Y') - 1);
            }

            $user = User::create([
                'student_id' => $studentId,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'course' => $course,
                'year_graduated' => $yearGraduated,
                'student_type' => $studentType,
                'remember_token' => Str::random(10),
            ]);

            $user->assignRole('student');
            $created++;
        }

        $this->info("Created {$created} student(s). Default password: {$password}");
        return self::SUCCESS;
    }
}

