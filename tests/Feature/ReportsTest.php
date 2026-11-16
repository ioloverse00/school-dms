<?php

namespace Tests\Feature;

use App\Models\{DocumentRequest, DocumentType, User};
use App\Support\SummaryPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role): void
    {
        Role::findOrCreate($role); $user = User::factory()->create(); $user->assignRole($role); $this->actingAs($user);
    }

    private function seedRequests(): void
    {
        $user = User::factory()->create();
        $type = DocumentType::create(['name' => '=Unsafe spreadsheet name', 'category' => 'academic', 'weight' => 2]);
        foreach ([['2026-01-01 00:00:00', 'completed', '2026-02-01'], ['2026-01-31 23:59:59', 'processing', null], ['2026-02-01 00:00:00', 'completed', null]] as $i => [$date, $status, $archive]) {
            $request = new DocumentRequest;
            $request->forceFill(['request_id' => 'REPORT-'.$i, 'user_id' => $user->id, 'document_type_id' => $type->id, 'purpose' => 'Test', 'copies' => 2, 'urgency' => 'regular', 'student_category' => 'regular', 'priority_score' => 9, 'status' => $status, 'archived_at' => $archive, 'created_at' => $date, 'updated_at' => $date])->save();
        }
    }

    public function test_staff_access_and_student_restrictions_cover_exports(): void
    {
        foreach (['admin', 'registrar'] as $role) {
            $this->login($role);
            $this->get(route($role.'.reports'))->assertOk()->assertSee('Reports &amp; analytics', false);
        }
        $this->login('student');
        foreach (['admin', 'registrar'] as $role) foreach (['', 'csv', 'pdf'] as $format) $this->get(route($role.'.reports', ['format' => $format]))->assertForbidden();
    }

    public function test_page_csv_and_pdf_share_inclusive_date_range_and_archive_filters(): void
    {
        $this->login('admin'); $this->seedRequests();
        $filters = ['from' => '2026-01-01', 'to' => '2026-01-31'];
        $this->get(route('admin.reports', $filters))->assertOk()->assertViewHas('total', 2)->assertViewHas('copies', 4)->assertViewHas('archived', 1);
        $this->get(route('admin.reports', $filters + ['scope' => 'active']))->assertViewHas('total', 1);
        $csv = $this->get(route('admin.reports', $filters + ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Total requests",2', $csv);
        $this->assertStringContainsString("'=Unsafe spreadsheet name", $csv);
        $pdf = $this->get(route('admin.reports', $filters + ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Summary | Total requests | 2', $pdf);
        $this->assertStringNotContainsString('REPORT-0', $csv);
    }

    public function test_invalid_filters_are_rejected_and_empty_reports_work(): void
    {
        $this->login('admin');
        foreach ([['from'=>'2026-02-01','to'=>'2026-01-01'], ['status'=>'invalid'], ['year'=>'11111111-1111-4111-8111-111111111111'], ['document'=>9999], ['from'=>'2000-01-01','to'=>'2026-01-01']] as $filters) $this->getJson(route('admin.reports', $filters))->assertUnprocessable();
        $this->get(route('admin.reports'))->assertOk()->assertSee('No requests match these filters.');
    }

    public function test_pdf_pagination_and_xref_offsets_are_valid(): void
    {
        $pdf = SummaryPdf::render(array_fill(0, 100, 'Text (with) \\ symbols'));
        $this->assertStringContainsString('/Count 3', $pdf);
        preg_match('/startxref\n(\d+)/', $pdf, $match);
        $this->assertSame('xref', substr($pdf, (int) $match[1], 4));
        preg_match_all('/(\d+) 0 obj/', $pdf, $objects, PREG_OFFSET_CAPTURE);
        foreach ($objects[0] as [$object, $offset]) $this->assertStringContainsString(sprintf('%010d 00000 n', $offset), $pdf);
    }
}
