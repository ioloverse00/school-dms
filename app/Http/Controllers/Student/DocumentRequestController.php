<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\DocumentType;

class DocumentRequestController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $search = \App\Support\ListSearch::term($request);
        $filters = $request->validate([
            'status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\RequestStatus::LABELS))],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'urgency' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\RequestUrgency::LABELS))],
        ]);
        $requests = DocumentRequest::where('user_id', auth()->id())
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['document_type_id'] ?? null, fn ($q, $value) => $q->where('document_type_id', $value))
            ->when($filters['urgency'] ?? null, fn ($q, $value) => $q->where('urgency', $value))
            ->when($search !== '', fn ($q) => \App\Support\DocumentRequestListing::search($q, $search, false))
            ->with('documentType')
            ->orderBy('created_at', 'desc')
            ->orderByDesc('id')->paginate(10)->withQueryString();

        $documentTypes = DocumentType::orderBy('name')->get();
        return view('student.requests.index', compact('requests', 'search', 'filters', 'documentTypes'));
    }

    public function create()
    {
        $documentTypes = DocumentType::all()->groupBy('category');
        $user = auth()->user();
        return view('student.requests.create', compact('documentTypes', 'user'));
    }

    public function store(\App\Http\Requests\StoreDocumentRequest $request, \App\Services\SubmitDocumentRequest $submit)
    {
        try {
            $docRequest = $submit->handle($request->user(), $request->validated(), $request->file('proof_of_payment'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Document request submission failed.', [
                'user_id' => $request->user()->id, 'error_type' => $exception::class,
            ]);

            return back()->withErrors(['submission' => 'Your request could not be saved. Please attach the proof again and retry.'])
                ->withInput($request->except('proof_of_payment'));
        }

        return redirect()->route('student.requests.index')
            ->with('success', 'Request submitted! Your Request ID is: ' . $docRequest->request_id);
    }

    public function show($id)
    {
        $documentRequest = auth()->user()->documentRequests()
            ->with('documentType', 'requiredFiles', 'user')
            ->findOrFail($id);
        $history = $documentRequest->events()->latest('id')->paginate(20, ['*'], 'history_page');
        return view('student.requests.show', compact('documentRequest', 'history'));
    }
}
