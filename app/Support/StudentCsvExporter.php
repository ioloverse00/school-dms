<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentCsvExporter
{
    public static function download(?string $studentType = null, string $search = ''): StreamedResponse
    {
        $typeName = $studentType ? '-' . str_replace('_', '-', $studentType) : '';
        $filename = 'students' . $typeName . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($studentType, $search) {
            $handle = fopen('php://output', 'w');
            $categoryLabels = StudentCategory::labels();

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map([CsvCell::class, 'escape'], [
                'Student ID',
                'Name',
                'Email',
                'Course',
                'Year Graduated',
                'Student Category',
                'Request Count',
                'Created At',
            ]), ',', '"', '');

            StudentListing::query($studentType, $search)
                ->chunk(200, function ($students) use ($handle, $categoryLabels) {
                    foreach ($students as $student) {
                        fputcsv($handle, array_map([CsvCell::class, 'escape'], [
                            $student->student_id,
                            $student->name,
                            $student->email,
                            $student->course,
                            $student->year_graduated,
                            $categoryLabels[$student->student_type] ?? $student->student_type,
                            $student->document_requests_count,
                            $student->created_at?->format('Y-m-d H:i:s'),
                        ]), ',', '"', '');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
