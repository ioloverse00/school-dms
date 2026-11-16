<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentRequestCsvExporter
{
    public static function download(bool $showArchived, ?string $status = null, string $search = '', ?int $documentTypeId = null): StreamedResponse
    {
        $statusName = $status ? '-' . str_replace('_', '-', $status) : '';
        $filename = ($showArchived ? 'archived' : 'active') . $statusName . '-document-requests-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($showArchived, $status, $search, $documentTypeId) {
            $handle = fopen('php://output', 'w');
            $categoryLabels = StudentCategory::labels();

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map([CsvCell::class, 'escape'], [
                'Request ID',
                'Student ID',
                'Student Name',
                'Student Email',
                'Document',
                'Copies',
                'Purpose',
                'Urgency',
                'Student Category',
                'Priority Score',
                'Status',
                'Remarks',
                'Requested At',
                'Archived At',
            ]), ',', '"', '');

            DocumentRequestListing::query($showArchived, $status, $search, $documentTypeId)->chunk(200, function ($requests) use ($handle, $categoryLabels) {
                foreach ($requests as $request) {
                    fputcsv($handle, array_map([CsvCell::class, 'escape'], [
                        $request->request_id,
                        $request->user?->student_id,
                        $request->user?->name,
                        $request->user?->email,
                        $request->documentType?->name,
                        $request->copies,
                        $request->purpose,
                        RequestUrgency::label($request->urgency),
                        $categoryLabels[$request->student_category] ?? $request->student_category,
                        $request->priority_score,
                        ucwords(str_replace('_', ' ', $request->status)),
                        $request->remarks,
                        $request->created_at?->format('Y-m-d H:i:s'),
                        $request->archived_at?->format('Y-m-d H:i:s'),
                    ]), ',', '"', '');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
