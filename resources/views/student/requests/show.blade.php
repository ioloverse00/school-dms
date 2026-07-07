@extends('layouts.dashboard')

@section('content')
@php
    $categoryLabels = [
        'regular'    => 'Regular Student',
        'graduating' => 'Graduating Student',
        'alumni'     => 'Alumni',
    ];
    $statusSteps = [
        'pending_requirements' => 1,
        'pending_verification' => 2,
        'processing'           => 3,
        'ready_for_release'    => 4,
        'completed'            => 5,
    ];
    $currentStep = $statusSteps[$documentRequest->status] ?? 0;
    $isRejected  = $documentRequest->status === 'rejected';

    $statusColors = [
        'pending_requirements' => 'text-yellow-700 bg-yellow-100',
        'pending_verification' => 'text-blue-700 bg-blue-100',
        'processing'           => 'text-orange-700 bg-orange-100',
        'ready_for_release'    => 'text-green-700 bg-green-100',
        'completed'            => 'text-gray-700 bg-gray-100',
        'rejected'             => 'text-red-700 bg-red-100',
    ];
    $badgeColor = $statusColors[$documentRequest->status] ?? 'text-gray-700 bg-gray-100';
@endphp

<div class="container px-6 mx-auto">
    <div class="flex items-center justify-between mt-6 mb-6">
        <h2 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
            Request Details
        </h2>
        <a href="{{ route('student.requests.index') }}"
            class="px-4 py-2 text-sm font-medium text-gray-700 border rounded-lg hover:bg-gray-100 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
            ← Back to My Requests
        </a>
    </div>

    {{-- Status Banner --}}
    <div class="mb-6 p-4 rounded-lg flex items-center gap-3
        {{ $isRejected ? 'bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700' : 'bg-purple-50 dark:bg-gray-800 border border-purple-100 dark:border-gray-700' }}">
        <span class="px-3 py-1 text-sm font-bold rounded-full {{ $badgeColor }}">
            {{ ucwords(str_replace('_', ' ', $documentRequest->status)) }}
        </span>
        <span class="text-sm text-gray-600 dark:text-gray-400">
            @if($documentRequest->status === 'pending_requirements')
                Please ensure all required documents are uploaded.
            @elseif($documentRequest->status === 'pending_verification')
                Your request is awaiting verification by the registrar.
            @elseif($documentRequest->status === 'processing')
                Your document is currently being prepared.
            @elseif($documentRequest->status === 'ready_for_release')
                Your document is ready! Please claim it at the registrar's office.
            @elseif($documentRequest->status === 'completed')
                Your request has been completed.
            @elseif($documentRequest->status === 'rejected')
                Your request was rejected. See remarks below.
            @endif
        </span>
    </div>

    {{-- Status Timeline --}}
    @if(!$isRejected)
    <div class="mb-6 p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
        <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-200">Progress</h3>
        @php
            $steps = [
                1 => 'Pending Requirements',
                2 => 'Pending Verification',
                3 => 'Processing',
                4 => 'Ready for Release',
                5 => 'Completed',
            ];
        @endphp
        <div class="flex">
            @foreach($steps as $step => $label)
            <div class="flex-1 flex flex-col items-center" style="position:relative;">
                {{-- Left line --}}
                @if(!$loop->first)
                <div style="position:absolute; top:14px; right:50%; left:0; height:4px;"
                    class="{{ $currentStep >= $step ? 'bg-purple-600' : 'bg-gray-300 dark:bg-gray-600' }}"></div>
                @endif
                {{-- Right line --}}
                @if(!$loop->last)
                <div style="position:absolute; top:14px; left:50%; right:0; height:4px;"
                    class="{{ $currentStep > $step ? 'bg-purple-600' : 'bg-gray-300 dark:bg-gray-600' }}"></div>
                @endif
                {{-- Circle --}}
                <div style="position:relative; z-index:1;"
                    class="w-8 h-8 flex items-center justify-center rounded-full border-2
                    {{ $currentStep > $step ? 'bg-purple-600 border-purple-600 text-white' : ($currentStep == $step ? 'bg-white dark:bg-gray-800 border-purple-600 text-purple-600 dark:text-purple-400' : 'bg-white dark:bg-gray-800 border-gray-300 text-gray-300 dark:border-gray-600') }}">
                    @if($currentStep > $step)
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    @else
                    <span class="text-xs font-bold">{{ $step }}</span>
                    @endif
                </div>
                {{-- Label --}}
                <span class="text-xs text-center mt-2
                    {{ $currentStep == $step ? 'text-purple-600 dark:text-purple-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">
                    {{ $label }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="grid gap-6 mb-6 md:grid-cols-2">
        {{-- Request Info --}}
        <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <h3 class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">Request Information</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Request ID</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->request_id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Document</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->documentType->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Purpose</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->purpose }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Copies</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->copies }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Urgency</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ ucwords(str_replace('_', ' ', $documentRequest->urgency)) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Student Category</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $categoryLabels[$documentRequest->student_category] ?? ucfirst($documentRequest->student_category) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Priority Score</span>
                    <span class="px-2 py-1 text-sm font-bold text-purple-700 bg-purple-100 rounded-full">{{ $documentRequest->priority_score }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Date Submitted</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->created_at?->format('M d, Y g:i A') ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        {{-- Student Info --}}
        <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <h3 class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">Your Information</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Student ID</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->user->student_id ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Full Name</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->user->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Course / Program</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->user->course ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Year Graduated</span>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $documentRequest->user->year_graduated ?? 'N/A' }}</span>
                </div>
            </div>

            {{-- Remarks --}}
            @if($documentRequest->remarks)
            <div class="mt-6 p-3 bg-yellow-50 dark:bg-gray-700 rounded-lg border border-yellow-200 dark:border-gray-600">
                <p class="text-xs font-semibold text-yellow-700 dark:text-yellow-400 mb-1">Remarks from Registrar</p>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $documentRequest->remarks }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Uploaded Files --}}
    <div class="p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800 mb-8">
        <h3 class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">Uploaded Documents</h3>
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
            @forelse($documentRequest->requiredFiles as $file)
            <div class="p-3 border rounded-lg dark:border-gray-700" style="display:flex; flex-direction:column; gap:0.5rem;">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ ucwords(str_replace('_', ' ', $file->file_type)) }}
                    </p>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $file->original_name }}</p>
                <a href="{{ Storage::url($file->file_path) }}" target="_blank"
                    class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700"
                    style="align-self: flex-start;">
                    View File
                </a>
            </div>
            @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No files uploaded.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
