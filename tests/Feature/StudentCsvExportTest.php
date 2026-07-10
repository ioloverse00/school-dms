<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_students_as_csv(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student', [
            'student_id' => '210113570',
            'name' => 'Doe, John Ambatugan',
            'email' => 'student@school.edu',
            'course' => 'Bachelor of Science in Psychology (BSPsych)',
            'student_type' => 'regular',
        ]);
        $documentType = DocumentType::create([
            'name' => 'Transcript of Records',
            'category' => 'academic',
            'weight' => 4,
        ]);

        DocumentRequest::create([
            'request_id' => 'REQ-STUDENT-001',
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'purpose' => 'Student export request count',
            'status' => 'processing',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.students.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Student ID",Name,Email,Course,"Year Graduated","Student Category","Request Count","Created At"', $csv);
        $this->assertStringContainsString('210113570,"Doe, John Ambatugan",student@school.edu,"Bachelor of Science in Psychology (BSPsych)",,Regular,1', $csv);
    }

    public function test_registrar_can_export_students_as_csv(): void
    {
        $registrar = $this->userWithRole('registrar');

        $this->userWithRole('student', [
            'student_id' => '210113571',
            'name' => 'Reyes, Maria Santos',
            'email' => 'reyes.maria@school.edu',
            'student_type' => 'graduating',
        ]);
        $this->userWithRole('student', [
            'student_id' => '210113572',
            'name' => 'Santos, Lovely Anne',
            'email' => 'santos.lovely@school.edu',
            'student_type' => 'regular',
        ]);

        $response = $this->actingAs($registrar)->get(route('registrar.students.export', ['student_type' => 'graduating']));

        $response->assertOk();
        $response->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('210113571,"Reyes, Maria Santos",reyes.maria@school.edu', $csv);
        $this->assertStringNotContainsString('210113572,"Santos, Lovely Anne",santos.lovely@school.edu', $csv);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate($role);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
