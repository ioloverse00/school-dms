<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentRequestController extends Controller
{
    public function index()
    {
        $requests = DocumentRequest::where('user_id', auth()->id())
            ->with('documentType')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('student.requests.index', compact('requests'));
    }

    public function create()
    {
        $documentTypes = DocumentType::all()->groupBy('category');
        $user = auth()->user();
        return view('student.requests.create', compact('documentTypes', 'user'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'purpose'          => 'required|string',
            'copies'           => 'required|integer|min:1',
            'urgency'          => 'required|in:regular,urgent,very_urgent',
            'year_graduated'   => (auth()->user()->student_type === 'alumni' ? 'required' : 'nullable') . '|digits:4|integer|min:1990|max:' . date('Y'),
            'valid_id'         => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'proof_of_payment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'request_form'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $user = auth()->user();

        // Save year_graduated back to the user profile if alumni and changed
        if ($user->student_type === 'alumni' && $user->year_graduated !== $request->year_graduated) {
            $user->year_graduated = $request->year_graduated;
            $user->save();
        }

        // Check alumni-only restriction
        $documentType = DocumentType::find($request->document_type_id);
        if ($documentType->alumni_only && $user->student_type !== 'alumni') {
            return back()->withErrors(['document_type_id' => 'Diploma Copy can only be requested by alumni students.'])->withInput();
        }

        // Calculate priority score (weights configurable via settings)
        $urgencyLevels  = SystemSetting::get('urgency_level_values', ['regular' => 1, 'urgent' => 2, 'very_urgent' => 3]);
        $studentWeights = SystemSetting::get('student_category_weights', ['regular' => 2, 'graduating' => 5, 'alumni' => 3]);
        $urgencyWeight  = SystemSetting::get('urgency_weight', 5);

        $urgencyScore   = ($urgencyLevels[$request->urgency] ?? 1) * $urgencyWeight;
        $studentScore   = $studentWeights[$user->student_type] ?? 1;
        $documentScore  = $documentType->weight;
        $priorityScore = $urgencyScore + $studentScore + $documentScore;

        // Create request
        $docRequest = DocumentRequest::create([
            'request_id' => 'REQ-' . date('Y') . '-' . strtoupper(\Illuminate\Support\Str::random(5)),
            'user_id' => $user->id,
            'document_type_id' => $request->document_type_id,
            'purpose' => $request->purpose,
            'copies' => $request->copies,
            'urgency' => $request->urgency,
            'student_category' => $user->student_type,
            'priority_score' => $priorityScore,
            'status' => 'pending_verification',
        ]);

        // Handle file uploads
        $fileTypes = ['valid_id', 'proof_of_payment', 'request_form'];
        foreach ($fileTypes as $fileType) {
            if ($request->hasFile($fileType)) {
                $file = $request->file($fileType);
                $path = $file->store('request_files', 'public');

                $docRequest->requiredFiles()->create([
                    'file_type' => $fileType,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        return redirect()->route('student.requests.index')
            ->with('success', 'Request submitted! Your Request ID is: ' . $docRequest->request_id);
    }

    public function show($id)
    {
        $documentRequest = DocumentRequest::with('documentType', 'requiredFiles', 'user')->findOrFail($id);
        return view('student.requests.show', compact('documentRequest'));
    }
}
