@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <div class="flex items-center justify-between my-6">
        <h2 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">Request Details</h2>
        <div style="display:flex; align-items:center; gap:1rem;">
            <a href="{{ route('registrar.requests.index') }}"
                class="px-4 py-2 text-sm font-medium leading-5 text-gray-700 transition-colors duration-150 border border-gray-300 rounded-lg hover:border-gray-500 focus:outline-none">
                ← Back to All Requests
            </a>
            @if($documentRequest->archived_at)
            <form method="POST" action="{{ route('registrar.requests.unarchive', $documentRequest->id) }}">
                @csrf @method('PATCH')
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium leading-5 text-white bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 transition-colors duration-150 focus:outline-none">
                    Unarchive
                </button>
            </form>
            @else
            <form method="POST" action="{{ route('registrar.requests.archive', $documentRequest->id) }}">
                @csrf @method('PATCH')
                <button type="submit"
                    onclick="return confirm('Archive this request?')"
                    class="px-4 py-2 text-sm font-medium leading-5 text-white bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 transition-colors duration-150 focus:outline-none">
                    Archive
                </button>
            </form>
            @endif
        </div>
    </div>

    <div class="grid gap-6 mb-8 md:grid-cols-2">
        <!-- Request Info -->
        <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <h3 class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">
                Request Information
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Request ID:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->request_id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Student:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->user->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Student ID:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->user->student_id ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Course:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->user->course ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Document:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->documentType->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Purpose:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->purpose }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Copies:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->copies }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Urgency:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ ucwords(str_replace('_', ' ', $documentRequest->urgency)) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Student Category:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ ['regular'=>'Regular Student','graduating'=>'Graduating Student','alumni'=>'Alumni'][$documentRequest->student_category] ?? ucfirst($documentRequest->student_category) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Priority Score:</span>
                    <span class="px-2 py-1 text-sm font-bold text-purple-700 bg-purple-100 rounded-full">{{ $documentRequest->priority_score }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Date Submitted:</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->created_at->format('M d, Y h:i A') }}</span>
                </div>
            </div>
        </div>

        <!-- Update Status -->
        <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <h3 class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">
                Update Status
            </h3>

            @if(session('success'))
            <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
                {{ session('success') }}
            </div>
            @endif

            <form action="{{ route('registrar.requests.update', $documentRequest->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
                        Current Status
                    </label>
                    <select name="status" class="block w-full mt-1 text-sm form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                        <option value="pending_requirements" {{ $documentRequest->status == 'pending_requirements' ? 'selected' : '' }}>Pending Requirements</option>
                        <option value="pending_verification" {{ $documentRequest->status == 'pending_verification' ? 'selected' : '' }}>Pending Verification</option>
                        <option value="processing" {{ $documentRequest->status == 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="ready_for_release" {{ $documentRequest->status == 'ready_for_release' ? 'selected' : '' }}>Ready for Release</option>
                        <option value="completed" {{ $documentRequest->status == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="rejected" {{ $documentRequest->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
                        Remarks (optional)
                    </label>
                    <textarea name="remarks" rows="4"
                        class="block w-full mt-1 text-sm form-textarea dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">{{ $documentRequest->remarks }}</textarea>
                </div>

                <button type="submit"
                    class="w-full px-5 py-3 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                    Update Status
                </button>
            </form>
        </div>
    </div>

    <!-- Uploaded Files -->
    <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800 mb-8">
        <h3 class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">
            Uploaded Documents
        </h3>
        <div class="grid gap-4 md:grid-cols-3">
            @forelse($documentRequest->requiredFiles as $file)
            <div class="p-3 border rounded-lg dark:border-gray-700">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                    {{ ucwords(str_replace('_', ' ', $file->file_type)) }}
                </p>
                <p class="text-xs text-gray-500 mb-2">{{ $file->original_name }}</p>
                <a href="{{ route('files.view', $file->id) }}" target="_blank"
                    class="px-3 py-1 text-xs font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">
                    View File
                </a>
            </div>
            @empty
            <p class="text-sm text-gray-500">No files uploaded.</p>
            @endforelse
        </div>
    </div>

</div>
@endsection
