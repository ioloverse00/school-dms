<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Mail\DocumentStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Notifications\DocumentStatusNotification;

class DocumentRequestController extends Controller
{
    public function index(Request $request)
    {
        $showArchived = $request->boolean('archived');

        $requests = DocumentRequest::with(['user', 'documentType'])
            ->when($showArchived, fn($q) => $q->whereNotNull('archived_at'))
            ->when(!$showArchived, fn($q) => $q->whereNull('archived_at'))
            ->orderBy('priority_score', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('admin.requests.index', compact('requests', 'showArchived'));
    }

    public function show($id)
    {
        $documentRequest = DocumentRequest::with(['user', 'documentType', 'requiredFiles'])
            ->findOrFail($id);

        return view('admin.requests.show', compact('documentRequest'));
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

        return redirect()->route('admin.requests.show', $id)
            ->with('success', 'Status updated and student notified!');
    }

    public function archive($id)
    {
        $documentRequest = DocumentRequest::findOrFail($id);
        $documentRequest->update(['archived_at' => now()]);

        return redirect()->route('admin.requests.index')
            ->with('success', 'Request archived.');
    }

    public function unarchive($id)
    {
        $documentRequest = DocumentRequest::findOrFail($id);
        $documentRequest->update(['archived_at' => null]);

        return redirect()->route('admin.requests.show', $id)
            ->with('success', 'Request unarchived.');
    }
}
