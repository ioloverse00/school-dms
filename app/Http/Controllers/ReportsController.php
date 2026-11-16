<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Services\AcademicCalendar;
use App\Support\{CsvCell, RequestStatus, StudentCategory, SummaryPdf};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportsController extends Controller
{
    public function __invoke(Request $request, AcademicCalendar $calendar)
    {
        $data = $request->validate([
            'year' => ['nullable', 'uuid'], 'term' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(array_keys(RequestStatus::LABELS))],
            'category' => ['nullable', Rule::in(array_keys(StudentCategory::labels()))],
            'document' => ['nullable', 'integer', 'exists:document_types,id'],
            'scope' => ['nullable', Rule::in(['all', 'active', 'archived'])],
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
        ]);
        $saved = $calendar->get(); $year = $data['year'] ?? null; $term = $data['term'] ?? null;
        if ($year && !isset($saved['years'][$year])) throw ValidationException::withMessages(['year' => 'Select an existing academic year.']);
        if ($term && (!isset($saved['terms'][$term]) || ($year && $saved['terms'][$term]['year_id'] !== $year))) throw ValidationException::withMessages(['term' => 'Select a semester belonging to the chosen academic year.']);
        $period = $term ? $saved['terms'][$term] : ($year ? $saved['years'][$year] : null);
        $from = $data['from'] ?? ($period['starts_on'] ?? now()->startOfYear()->format('Y-m-d'));
        $to = $data['to'] ?? ($period['ends_on'] ?? now()->format('Y-m-d'));
        if ($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 3660) throw ValidationException::withMessages(['from' => 'Choose an ordered date range of at most ten years.']);
        $scope = $data['scope'] ?? 'all';
        $query = DocumentRequest::query()->where('created_at', '>=', $from.' 00:00:00')->where('created_at', '<', CarbonImmutable::parse($to)->addDay()->format('Y-m-d').' 00:00:00');
        foreach (['status' => 'status', 'category' => 'student_category', 'document' => 'document_type_id'] as $filter => $column) if (!empty($data[$filter])) $query->where($column, $data[$filter]);
        if ($scope === 'active') $query->whereNull('archived_at');
        if ($scope === 'archived') $query->whereNotNull('archived_at');
        $statuses = array_fill_keys(array_keys(RequestStatus::LABELS), 0); $documents = []; $categories = []; $months = []; $total = 0; $copies = 0; $archived = 0;
        for ($month = CarbonImmutable::parse($from)->startOfMonth(); $month->lte(CarbonImmutable::parse($to)); $month = $month->addMonth()) $months[$month->format('Y-m')] = 0;
        foreach ($query->select(['id', 'created_at', 'status', 'document_type_id', 'student_category', 'copies', 'archived_at'])->cursor() as $row) {
            $total++; $copies += $row->copies; $archived += $row->archived_at ? 1 : 0;
            $statuses[$row->status] = ($statuses[$row->status] ?? 0) + 1;
            $documents[$row->document_type_id] = ($documents[$row->document_type_id] ?? 0) + 1;
            $categories[$row->student_category] = ($categories[$row->student_category] ?? 0) + 1;
            $months[$row->created_at->format('Y-m')]++;
        }
        arsort($documents); $types = DocumentType::orderBy('name')->get(); $labels = $types->pluck('name', 'id')->all(); $categoryLabels = StudentCategory::labels();
        $rows = [['Section', 'Metric', 'Value'], ['Filters', 'From', $from], ['Filters', 'To', $to], ['Filters', 'Archive scope', $scope], ['Filters', 'Status', $data['status'] ?? 'all'], ['Filters', 'Student category', $data['category'] ?? 'all'], ['Filters', 'Document', $labels[$data['document'] ?? ''] ?? 'all'], ['Summary', 'Total requests', $total], ['Summary', 'Copies requested', $copies], ['Summary', 'Archived requests', $archived]];
        foreach ($statuses as $key => $value) $rows[] = ['Status', RequestStatus::LABELS[$key] ?? $key, $value];
        foreach ($months as $key => $value) $rows[] = ['Month', $key, $value];
        foreach ($documents as $key => $value) $rows[] = ['Document', $labels[$key] ?? 'Unknown document', $value];
        foreach ($categories as $key => $value) $rows[] = ['Student category', $categoryLabels[$key] ?? $key, $value];
        $demo = basename(storage_path()) === 'local-uat';
        if ($demo) $rows[] = ['Source', 'Data classification', 'Synthetic local UAT data'];
        $filename = 'request-report-'.$from.'-to-'.$to;
        if (($data['format'] ?? '') === 'csv') return response()->streamDownload(function () use ($rows) { $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF"); foreach ($rows as $row) fputcsv($out, array_map([CsvCell::class, 'escape'], $row), ',', '"', ''); fclose($out); }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
        if (($data['format'] ?? '') === 'pdf') {
            $lines = ['School DMS - Reports & Analytics', 'Generated: '.now()->format('Y-m-d H:i'), 'Counts reflect current status of requests submitted in the selected range.', ''];
            foreach (array_slice($rows, 1) as $row) $lines[] = implode(' | ', $row);
            return response(SummaryPdf::render($lines), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"', 'Cache-Control' => 'no-store']);
        }
        return response()->view('reports.index', compact('saved', 'from', 'to', 'scope', 'total', 'copies', 'archived', 'statuses', 'months', 'documents', 'categories', 'types', 'labels', 'categoryLabels', 'demo'))->header('Cache-Control', 'no-store');
    }
}
