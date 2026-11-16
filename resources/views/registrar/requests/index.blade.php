@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <div class="flex flex-col gap-3 my-6 sm:flex-row sm:items-center" style="width: 100%;">
        <h2 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
            {{ $showArchived ? 'Archived Document Requests' : 'Document Requests' }}
        </h2>
        <div class="flex flex-col gap-2 sm:flex-row" style="margin-left: auto;">
            <a href="{{ route('registrar.requests.export', array_filter(['archived' => $showArchived ? 1 : null, 'status' => $status, 'q' => $search, 'document_type_id' => $documentTypeId], fn ($value) => $value !== null && $value !== '')) }}"
                class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-purple-700 bg-purple-100 border border-transparent rounded-lg hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-100 dark:hover:bg-purple-800">
                Export CSV
            </a>
            <form method="GET" action="{{ route('registrar.requests.index') }}" class="flex flex-col gap-2 sm:flex-row">
                @if($showArchived)
                <input type="hidden" name="archived" value="1">
                @endif
                <input type="hidden" name="q" value="{{ $search }}">
                @if(!$showArchived)
                <select aria-label="Filter by request status" name="status" onchange="this.form.submit()"
                    class="form-select-custom spark-field px-4 py-2 text-sm border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                    <option value="">All</option>
                    @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @endif
                <select aria-label="Filter by document type" name="document_type_id" onchange="this.form.submit()" class="form-select-custom spark-field px-4 py-2 text-sm border-gray-300 rounded-lg">
                    <option value="">All document types</option>
                    @foreach($documentTypes as $type)
                    <option value="{{ $type->id }}" @selected($documentTypeId === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @include('partials.request-list-table', ['requestRoutePrefix' => 'registrar'])
</div>
@if(session('highlight'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const targetId = '{{ session('highlight') }}';
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
