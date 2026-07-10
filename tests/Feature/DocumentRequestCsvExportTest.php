<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentRequestCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_active_document_requests_as_csv(): void
    {
        $admin = $this->userWithRole('admin');
        $student = User::factory()->create([
            'student_id' => '210113570',
            'name' => 'Doe, John Ambatugan',
            'email' => 'student@school.edu',
            'student_type' => 'regular',
        ]);
        $documentType = DocumentType::create([
            'name' => 'Transcript of Records',
            'category' => 'academic',
            'weight' => 4,
        ]);

        DocumentRequest::create([
            'request_id' => 'REQ-TEST-001',
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'purpose' => 'Presentation dry run',
            'copies' => 2,
            'urgency' => 'urgent',
            'student_category' => 'regular',
            'priority_score' => 8,
            'status' => 'processing',
            'remarks' => 'Ready for CSV',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.requests.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Request ID","Student ID","Student Name","Student Email",Document', $csv);
        $this->assertStringContainsString('REQ-TEST-001,210113570,"Doe, John Ambatugan",student@school.edu,"Transcript of Records"', $csv);
        $this->assertStringContainsString('"Presentation dry run"', $csv);
    }

    public function test_registrar_can_export_archived_document_requests_as_csv(): void
    {
        $registrar = $this->userWithRole('registrar');
        $student = User::factory()->create(['student_id' => '210113571']);
        $documentType = DocumentType::create([
            'name' => 'Certificate of Enrollment',
            'category' => 'academic',
            'weight' => 2,
        ]);

        DocumentRequest::create([
            'request_id' => 'REQ-ACTIVE-001',
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'purpose' => 'Active request',
            'status' => 'pending_verification',
        ]);

        DocumentRequest::create([
            'request_id' => 'REQ-ARCHIVED-001',
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'purpose' => 'Archived request',
            'status' => 'completed',
            'archived_at' => now(),
        ]);

        $response = $this->actingAs($registrar)->get(route('registrar.requests.export', ['archived' => 1]));

        $response->assertOk();
        $response->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('REQ-ARCHIVED-001', $csv);
        $this->assertStringNotContainsString('REQ-ACTIVE-001', $csv);
    }

    private function userWithRole(string $role): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate($role);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
