<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Mail\DocumentStatusUpdated;
use App\Notifications\DocumentStatusNotification;
use App\Support\DocumentRequestCsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class DocumentRequestController extends Controller
{
    private const EXPORTABLE_STATUSES = [
        'pending_verification',
        'processing',
        'ready_for_release',
        'completed',
        'rejected',
    ];

    public function index(Request $request)
    {
        $showArchived = $request->boolean('archived');
        $status = $this->validatedStatus($request);

        $requests = DocumentRequest::with(['user', 'documentType'])
            ->when($showArchived, fn($q) => $q->whereNotNull('archived_at'))
            ->when(!$showArchived, fn($q) => $q->whereNull('archived_at'))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('priority_score', 'desc')
            ->paginate(10)
            ->withQueryString();

        $statusOptions = $this->statusOptions();

        return view('registrar.requests.index', compact('requests', 'showArchived', 'status', 'statusOptions'));
    }

    public function export(Request $request)
    {
        return DocumentRequestCsvExporter::download($request->boolean('archived'), $this->validatedStatus($request));
    }

    public function show($id)
    {
        $documentRequest = DocumentRequest::with(['user', 'documentType', 'requiredFiles'])
            ->findOrFail($id);

        return view('registrar.requests.show', compact('documentRequest'));
    }

    public function update(Request $request, $id)
    {
        $documentRequest = DocumentRequest::with(['user', 'documentType'])
            ->findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending_requirements,pending_verification,processing,ready_for_release,completed,rejected',
            'remarks' => 'nullable|string',
        ]);

        $documentRequest->update([
            'status' => $request->status,
            'remarks' => $request->remarks,
        ]);

        Mail::to($documentRequest->user->email)
            ->send(new DocumentStatusUpdated($documentRequest));

        $documentRequest->user->notify(new DocumentStatusNotification($documentRequest));

        return redirect()->route('registrar.requests.show', $id)
            ->with('success', 'Status updated and student notified!');
    }

    public function archive($id)
    {
        $documentRequest = DocumentRequest::findOrFail($id);
        $documentRequest->update(['archived_at' => now()]);

        return redirect()->route('registrar.requests.index')
            ->with('success', 'Request archived.');
    }

    public function unarchive($id)
    {
        $documentRequest = DocumentRequest::findOrFail($id);
        $documentRequest->update(['archived_at' => null]);

        return redirect()->route('registrar.requests.show', $id)
            ->with('success', 'Request unarchived.');
    }

    private function validatedStatus(Request $request): ?string
    {
        $status = $request->query('status');

        return in_array($status, self::EXPORTABLE_STATUSES, true) ? $status : null;
    }

    private function statusOptions(): array
    {
        return [
            'pending_verification' => 'Pending',
            'processing' => 'Processing',
            'ready_for_release' => 'Ready for Release',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
        ];
    }
}
