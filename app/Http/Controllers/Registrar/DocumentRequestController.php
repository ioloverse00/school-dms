<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Support\DocumentRequestCsvExporter;
use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    public function index(Request $request)
    {
        $showArchived = $request->boolean('archived');
        $status = $this->validatedStatus($request);

        $search = \App\Support\ListSearch::term($request);
        $documentTypeId = \App\Support\DocumentRequestListing::documentTypeFilter($request);
        $documentTypes = \App\Models\DocumentType::orderBy('name')->get();
        $requests = \App\Support\DocumentRequestListing::query($showArchived, $status, $search, $documentTypeId)
            ->paginate(10)
            ->withQueryString();

        $statusOptions = $this->statusOptions();

        return view('registrar.requests.index', compact('requests', 'showArchived', 'status', 'statusOptions', 'search', 'documentTypeId', 'documentTypes'));
    }

    public function export(Request $request)
    {
        return DocumentRequestCsvExporter::download($request->boolean('archived'), $this->validatedStatus($request), \App\Support\ListSearch::term($request), \App\Support\DocumentRequestListing::documentTypeFilter($request));
    }

    public function show($id)
    {
        $documentRequest = DocumentRequest::with(['user', 'documentType', 'requiredFiles'])
            ->findOrFail($id);

        $history = $documentRequest->events()->latest('id')->paginate(20, ['*'], 'history_page');
        return view('registrar.requests.show', compact('documentRequest', 'history'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Support\RequestStatus::LABELS))],
            'remarks' => 'required|string|max:10000',
            'edit_token' => ['required', 'string', 'size:64'],
        ], ['edit_token.required' => \App\Support\RequestEditToken::MESSAGE, 'edit_token.size' => \App\Support\RequestEditToken::MESSAGE]);

        $changed = app(\App\Services\UpdateDocumentRequestStatus::class)->handle((int) $id, $validated, $request->user());

        return redirect()->route('registrar.requests.show', $id)
            ->with('success', $changed
                ? 'Status saved. In-app notification recorded; email queued for delivery.'
                : 'No changes to save. The request is already up to date.');
    }

    public function archive(Request $request, $id)
    {
        $validated = $request->validate(['edit_token' => ['required', 'string', 'size:64']], ['edit_token.required' => \App\Support\RequestEditToken::MESSAGE, 'edit_token.size' => \App\Support\RequestEditToken::MESSAGE]);
        app(\App\Services\ArchiveDocumentRequest::class)->handle((int) $id, true, $request->user(), $validated['edit_token']);

        return redirect()->route('registrar.requests.index')
            ->with('success', 'Request archived.');
    }

    public function unarchive(Request $request, $id)
    {
        $validated = $request->validate(['edit_token' => ['required', 'string', 'size:64']], ['edit_token.required' => \App\Support\RequestEditToken::MESSAGE, 'edit_token.size' => \App\Support\RequestEditToken::MESSAGE]);
        app(\App\Services\ArchiveDocumentRequest::class)->handle((int) $id, false, $request->user(), $validated['edit_token']);

        return redirect()->route('registrar.requests.show', $id)
            ->with('success', 'Request unarchived.');
    }

    private function validatedStatus(Request $request): ?string
    {
        if ($request->boolean('archived')) return null;
        $status = $request->query('status');

        return in_array($status, array_keys($this->statusOptions()), true) ? $status : null;
    }

    private function statusOptions(): array
    {
        return ['pending_review' => 'Pending review'] + array_diff_key(\App\Support\RequestStatus::LABELS, ['completed' => true]);
    }
}
