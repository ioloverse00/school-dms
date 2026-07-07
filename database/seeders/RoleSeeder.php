<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        Role::create(['name' => 'student']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'registrar']);

        // Create default admin account
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@school.edu',
            'password' => Hash::make('admin123'),
        ]);
        $admin->assignRole('admin');

        // Create default registrar account
        $registrar = User::create([
            'name' => 'Registrar',
            'email' => 'registrar@school.edu',
            'password' => Hash::make('registrar123'),
        ]);
        $registrar->assignRole('registrar');

        // Create default student account
        $student = User::create([
            'student_id' => '210113570',
            'name' => 'Doe, John Ambatugan',
            'email' => 'student@school.edu',
            'password' => Hash::make('student123'),
            'course' => 'Bachelor of Science in Psychology (BSPsych)',
            'year_graduated' => null,
            'student_type' => 'regular',
        ]);
        $student->assignRole('student');
    }
}
