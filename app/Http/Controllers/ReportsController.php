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
        if (($data['format'] ?? '') === 'csv') {
            $csvRows = [
                ['SCHOOL DMS - DOCUMENT SERVICES REPORT'],
                ['Reporting period', CarbonImmutable::parse($from)->format('F j, Y').' to '.CarbonImmutable::parse($to)->format('F j, Y')],
                ['Generated by', $request->user()->name],
                ['Generated on', now()->timezone('Asia/Manila')->format('F j, Y h:i A').' (Asia/Manila)'],
                ['Counts reflect current status of requests submitted during this period.'],
                [], ['APPLIED FILTERS'], ['Filter', 'Selection'],
                ['Academic year', $year ? $saved['years'][$year]['name'] : 'All years'],
                ['Semester', $term ? $saved['terms'][$term]['academic_year'].' / '.$saved['terms'][$term]['name'] : 'All semesters'],
                ['Current status', RequestStatus::LABELS[$data['status'] ?? ''] ?? 'All statuses'],
                ['Student category', $categoryLabels[$data['category'] ?? ''] ?? 'All categories'],
                ['Document', $labels[$data['document'] ?? ''] ?? 'All documents'],
                ['Request scope', ['all' => 'Active and archived', 'active' => 'Active only', 'archived' => 'Archived only'][$scope]],
                [], ['REQUEST SUMMARY'], ['Metric', 'Total'],
                ['Total requests', $total], ['Copies requested', $copies], ['Completed requests', $statuses['completed'] ?? 0], ['Archived requests', $archived],
            ];
            foreach ([['DOCUMENT DEMAND', 'Document type', $documents, $labels], ['REQUESTOR CATEGORIES', 'Requestor category', $categories, $categoryLabels]] as [$title, $column, $counts, $names]) {
                arsort($counts); $csvRows[] = []; $csvRows[] = [$title];
                $csvRows[] = ['Rank', $column, 'Requests', 'Share of filtered requests (%)'];
                $rank = 0;
                foreach ($counts as $key => $count) $csvRows[] = [++$rank, $names[$key] ?? $key, $count, $total ? round($count / $total * 100, 1) : 0];
                if (!$counts) $csvRows[] = ['', 'No matching requests', 0, 0];
            }
            $csvRows[] = []; $csvRows[] = ['REQUEST STATUS']; $csvRows[] = ['Status', 'Requests'];
            foreach ($statuses as $key => $count) {
                if (!empty($data['status']) && $key !== $data['status']) continue;
                $csvRows[] = [RequestStatus::LABELS[$key] ?? $key, $count];
            }
            $csvRows[] = []; $csvRows[] = ['MONTHLY SUBMISSIONS']; $csvRows[] = ['Month', 'Requests'];
            foreach ($months as $month => $count) $csvRows[] = [CarbonImmutable::parse($month.'-01')->format('F Y'), $count];
            if ($demo) { $csvRows[] = []; $csvRows[] = ['Data classification', 'Synthetic local UAT data']; }
            return response()->streamDownload(function () use ($csvRows) {
                $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
                foreach ($csvRows as $row) fputcsv($out, array_map([CsvCell::class, 'escape'], array_pad($row, 4, '')), ',', '"', '');
                fclose($out);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }
        if (($data['format'] ?? '') === 'pdf') {
            $pdfRows = array_values(array_filter(array_slice($rows, 1), fn ($row) => in_array($row[0], ['Filters', 'Summary'], true)));
            $pdfRows[] = ['Filters', 'Academic year', $year ? $saved['years'][$year]['name'] : 'All years'];
            $pdfRows[] = ['Filters', 'Semester', $term ? $saved['terms'][$term]['academic_year'].' / '.$saved['terms'][$term]['name'] : 'All semesters'];
            $rankedDocuments = $documents + array_fill_keys(!empty($data['document']) ? [$data['document']] : array_keys($labels), 0);
            $rankedCategories = $categories + array_fill_keys(!empty($data['category']) ? [$data['category']] : array_keys($categoryLabels), 0);
            arsort($rankedDocuments); arsort($rankedCategories);
            foreach ($rankedDocuments as $key => $value) $pdfRows[] = ['Document', $labels[$key] ?? 'Unknown document', $value.' requests ('.($total ? round($value / $total * 100, 1) : 0).'%)'];
            foreach ($rankedCategories as $key => $value) $pdfRows[] = ['Student category', $categoryLabels[$key] ?? $key, $value.' requests ('.($total ? round($value / $total * 100, 1) : 0).'%)'];
            foreach (array_slice($rows, 1) as $row) {
                if ($row[0] === 'Status' && !empty($data['status']) && $row[1] !== RequestStatus::LABELS[$data['status']]) continue;
                if (in_array($row[0], ['Status', 'Month', 'Source'], true)) $pdfRows[] = $row;
            }
            foreach ($pdfRows as &$row) {
                if ($row[0] === 'Month') $row[1] = CarbonImmutable::parse($row[1].'-01')->format('F Y');
                if ($row[0] === 'Filters') {
                    if ($row[1] === 'Status') $row[2] = RequestStatus::LABELS[$row[2]] ?? 'All statuses';
                    elseif ($row[1] === 'Student category') $row[2] = $categoryLabels[$row[2]] ?? 'All student categories';
                    elseif ($row[1] === 'Archive scope') $row[2] = ['all' => 'Active and archived', 'active' => 'Active only', 'archived' => 'Archived only'][$row[2]];
                    elseif ($row[1] === 'Document' && $row[2] === 'all') $row[2] = 'All document types';
                }
            }
            unset($row);
            $pdf = SummaryPdf::render($pdfRows, [
                'generated_by' => $request->user()->name,
                'generated_at' => now()->timezone('Asia/Manila')->format('Y-m-d h:i A').' (Asia/Manila)',
            ]);
            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"', 'Cache-Control' => 'no-store']);
        }
        return response()->view('reports.index', compact('saved', 'from', 'to', 'scope', 'total', 'copies', 'archived', 'statuses', 'months', 'documents', 'categories', 'types', 'labels', 'categoryLabels', 'demo'))->header('Cache-Control', 'no-store');
    }
}
