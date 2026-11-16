<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CsvExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function staffRoles(): array
    {
        return [['admin'], ['registrar']];
    }

    #[DataProvider('staffRoles')]
    public function test_student_exports_escape_formula_cells_for_each_staff_role(string $role): void
    {
        $this->staff($role);
        Role::findOrCreate('student');
        $student = User::factory()->create(['student_id' => '+1234', 'name' => '=1+1', 'course' => "\t=1+1"]);
        $student->assignRole('student');

        $csv = $this->get(route($role . '.students.export'))->assertOk()->streamedContent();
        $row = $this->dataRow($csv);
        $this->assertSame("'+1234", $row[0]);
        $this->assertSame("'=1+1", $row[1]);
        $this->assertSame("'\t=1+1", $row[3]);
        $this->assertSame('0', $row[6]);
    }

    #[DataProvider('staffRoles')]
    public function test_request_exports_escape_user_text_without_changing_numeric_counts(string $role): void
    {
        $this->staff($role);
        $student = User::factory()->create(['name' => '@SUM(1,2)']);
        $type = DocumentType::create(['name' => 'Transcript', 'category' => 'academic', 'weight' => 4]);
        DocumentRequest::create([
            'request_id' => 'REQ-CSV-001', 'user_id' => $student->id, 'document_type_id' => $type->id,
            'purpose' => ' =1+1', 'remarks' => "\u{FEFF}@SUM(1,2)", 'copies' => 2, 'status' => 'processing',
        ]);
        $csv = $this->get(route($role . '.requests.export'))->assertOk()->streamedContent();
        $row = $this->dataRow($csv);
        $this->assertSame("'@SUM(1,2)", $row[2]);
        $this->assertSame('2', $row[5]);
        $this->assertSame("' =1+1", $row[6]);
        $this->assertSame("'\u{FEFF}@SUM(1,2)", $row[11]);
    }

    private function staff(string $role): void
    {
        Role::findOrCreate($role);
        $staff = User::factory()->create();
        $staff->assignRole($role);
        $this->actingAs($staff);
    }

    private function dataRow(string $csv): array
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        fgetcsv($handle, 0, ',', '"', '');
        $row = fgetcsv($handle, 0, ',', '"', '');
        fclose($handle);

        return $row;
    }
}
