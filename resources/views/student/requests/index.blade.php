@extends('layouts.dashboard')

@section('content')
<div class="w-full py-8">
    <div class="student-request-header">
        <h1 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
            My Document Requests
        </h1>
        <form method="GET" action="{{ route('student.requests.index') }}" class="student-request-filters">
            <input type="hidden" name="q" value="{{ $search }}">
            <select aria-label="Filter by Status" onchange="this.form.submit()" id="student-request-status" name="status" class="form-select-custom spark-field px-4 py-2 text-sm border-gray-300 rounded-lg"><option value="">All</option>@foreach(\App\Support\RequestStatus::LABELS as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <select aria-label="Filter by Document" onchange="this.form.submit()" id="student-request-document" name="document_type_id" class="form-select-custom spark-field px-4 py-2 text-sm border-gray-300 rounded-lg"><option value="">All document types</option>@foreach($documentTypes as $type)<option value="{{ $type->id }}" @selected((string) ($filters['document_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>@endforeach</select>
            <noscript><button class="btn btn-primary" type="submit">Apply filters</button></noscript>
        </form>
        <a href="{{ route('student.requests.create') }}"
            class="btn btn-primary px-4 py-2 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
            + New Request
        </a>
    </div>

    @if(session('success'))
    <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    <div class="card portal-card mb-8">

        <div class="portal-table-wrap">
            <table class="table table-custom portal-table">
                <thead>
                    <tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                        <th class="px-4 py-3">Document</th>
                        <th class="px-4 py-3">Purpose</th>
                        <th class="px-4 py-3">Urgency</th>
                        <th class="px-4 py-3">Score</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($requests as $req)
                    <tr data-request-id="{{ $req->request_id }}" class="text-gray-700 dark:text-gray-400">
                        {{-- Document with icon --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center text-sm">
                                <div class="relative hidden w-8 h-8 mr-3 rounded-full md:block">
                                    <div style="width:100%;height:100%;border-radius:50%;background:#267363;display:flex;align-items:center;justify-content:center;">
                                        <svg style="width:1rem;height:1rem;color:white;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="absolute inset-0 rounded-full shadow-inner" aria-hidden="true"></div>
                                </div>
                                <div>
                                    <p class="font-semibold">{{ $req->documentType->name }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $req->request_id }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Purpose --}}
                        <td class="px-4 py-3 text-sm">{{ $req->purpose }}</td>

                        {{-- Urgency --}}
                        <td class="px-4 py-3 text-xs">
                            @php
                                $urgencyColors = ['standard'=>'text-gray-700 bg-gray-100','rush'=>'text-orange-700 bg-orange-100','super_rush'=>'text-red-700 bg-red-100'];
                                $uc = $urgencyColors[$req->urgency] ?? 'text-gray-700 bg-gray-100';
                            @endphp
                            <span class="px-2 py-1 font-semibold leading-tight rounded-full {{ $uc }}">
                                {{ \App\Support\RequestUrgency::label($req->urgency) }}
                            </span>
                        </td>

                        {{-- Priority score --}}
                        <td class="px-4 py-3 text-xs">
                            <span class="px-2 py-1 font-semibold leading-tight text-purple-700 bg-purple-100 rounded-full">
                                {{ $req->priority_score }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3 text-xs">
                            @php
                                $statusColors = [
                                    'pending_verification' => 'text-blue-700 bg-blue-100',
                                    'processing'           => 'text-orange-700 bg-orange-100',
                                    'ready_for_release'    => 'text-green-700 bg-green-100',
                                    'completed'            => 'text-gray-700 bg-gray-100',
                                    'rejected'             => 'text-red-700 bg-red-100',
                                ];
                                $color = $statusColors[$req->status] ?? 'text-gray-700 bg-gray-100';
                            @endphp
                            <span class="px-2 py-1 font-semibold leading-tight rounded-full {{ $color }}">
                                {{ ucwords(str_replace('_', ' ', $req->status)) }}
                            </span>
                        </td>

                        {{-- Date --}}
                        <td class="px-4 py-3 text-sm">{{ $req->created_at->format('M d, Y') }}</td>

                        {{-- Action --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-4 text-sm">
                                <a class="portal-table-view" href="{{ route('student.requests.show', $req->id) }}" aria-label="View request {{ $req->request_id }}" title="View request {{ $req->request_id }}"><i class="bi bi-eye-fill" aria-hidden="true"></i></a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="text-gray-700 dark:text-gray-400">
                        <td colspan="7" class="px-4 py-3 text-sm text-center">
                            @if($search !== '' || array_filter($filters)) No requests match your filters. @else No requests yet. <a href="{{ route('student.requests.create') }}" class="text-purple-600 hover:underline">Create one now!</a> @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    </div>
</div>
@if(session('highlight'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    const targetId = '{{ session("highlight") }}';
    const row = document.querySelector('[data-request-id="' + targetId + '"]');
    if (row) {
        row.style.transition = 'background-color 0.4s ease';
        row.style.backgroundColor = '#f3e8ff';
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => { row.style.backgroundColor = ''; }, 3500);
    }
});
</script>
@endif
@endsection
