<?php

namespace App\Support;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentCsvExporter
{
    public static function download(?string $studentType = null): StreamedResponse
    {
        $typeName = $studentType ? '-' . str_replace('_', '-', $studentType) : '';
        $filename = 'students' . $typeName . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($studentType) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Student ID',
                'Name',
                'Email',
                'Course',
                'Year Graduated',
                'Student Category',
                'Request Count',
                'Created At',
            ]);

            User::role('student')
                ->withCount('documentRequests')
                ->when($studentType, fn ($query) => $query->where('student_type', $studentType))
                ->orderBy('name')
                ->chunk(200, function ($students) use ($handle) {
                    foreach ($students as $student) {
                        fputcsv($handle, [
                            $student->student_id,
                            $student->name,
                            $student->email,
                            $student->course,
                            $student->year_graduated,
                            ucwords(str_replace('_', ' ', $student->student_type)),
                            $student->document_requests_count,
                            $student->created_at?->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
