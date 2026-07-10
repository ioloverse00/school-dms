<?php

namespace App\Support;

use App\Models\DocumentRequest;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentRequestCsvExporter
{
    public static function download(bool $showArchived, ?string $status = null): StreamedResponse
    {
        $statusName = $status ? '-' . str_replace('_', '-', $status) : '';
        $filename = ($showArchived ? 'archived' : 'active') . $statusName . '-document-requests-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($showArchived, $status) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
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
            ]);

            self::query($showArchived, $status)->chunk(200, function ($requests) use ($handle) {
                foreach ($requests as $request) {
                    fputcsv($handle, [
                        $request->request_id,
                        $request->user?->student_id,
                        $request->user?->name,
                        $request->user?->email,
                        $request->documentType?->name,
                        $request->copies,
                        $request->purpose,
                        ucwords(str_replace('_', ' ', $request->urgency)),
                        ucwords(str_replace('_', ' ', $request->student_category)),
                        $request->priority_score,
                        ucwords(str_replace('_', ' ', $request->status)),
                        $request->remarks,
                        $request->created_at?->format('Y-m-d H:i:s'),
                        $request->archived_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function query(bool $showArchived, ?string $status = null): Builder
    {
        return DocumentRequest::with(['user', 'documentType'])
            ->when($showArchived, fn ($query) => $query->whereNotNull('archived_at'))
            ->when(! $showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('priority_score', 'desc')
            ->orderBy('created_at', 'desc');
    }
}
