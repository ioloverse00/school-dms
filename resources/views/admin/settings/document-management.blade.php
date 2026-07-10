@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">Document Management</h2>

    @if(session('success'))
    <div class="p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-lg">{{ session('success') }}</div>
    @endif

    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; align-items: start;">

        {{-- Document Type Weights --}}
        <div class="bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h4 class="text-base font-semibold text-gray-700 dark:text-gray-200">Document Type Weight</h4>
                <p class="text-xs text-gray-500 mt-1">Weight added to priority score per document type.</p>
            </div>
            <form action="{{ route('admin.settings.document-weights.update') }}" method="POST">
                @csrf
                <div class="divide-y dark:divide-gray-700">
                    @foreach($documentTypes as $docType)
                    <div class="flex items-center justify-between px-6 py-3 gap-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300 flex-1">{{ $docType->name }}</span>
                        <input type="number" name="weights[{{ $docType->id }}]" value="{{ $docType->weight }}" min="1" max="20"
                            class="w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                    </div>
                    @endforeach
                </div>
                <div class="px-6 py-4 border-t dark:border-gray-700">
                    <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none">
                        Save Weights
                    </button>
                </div>
            </form>
        </div>

        {{-- Student Category Weights --}}
        <div class="bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h4 class="text-base font-semibold text-gray-700 dark:text-gray-200">Student Category Weight</h4>
                <p class="text-xs text-gray-500 mt-1">Weight added to priority score per student category.</p>
            </div>
            <form action="{{ route('admin.settings.student-weights.update') }}" method="POST">
                @csrf
                <div class="divide-y dark:divide-gray-700">
                    @foreach($studentWeights as $category => $weight)
                    <div class="flex items-center justify-between px-6 py-3 gap-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300 flex-1 capitalize">{{ str_replace('_', ' ', $category) }}</span>
                        <input type="number" name="weights[{{ $category }}]" value="{{ $weight }}" min="1" max="20"
                            class="w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                    </div>
                    @endforeach
                </div>
                <div class="px-6 py-4 border-t dark:border-gray-700">
                    <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none">
                        Save Weights
                    </button>
                </div>
            </form>
        </div>

        {{-- Urgency Level --}}
        <div class="bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h4 class="text-base font-semibold text-gray-700 dark:text-gray-200">Urgency Level</h4>
                <p class="text-xs text-gray-500 mt-1">Formula: <span class="font-mono">(Urgency Weight × Urgency Level) + Student Weight + Document Weight</span></p>
            </div>
            <form action="{{ route('admin.settings.urgency-levels.update') }}" method="POST">
                @csrf
                <div class="px-6 pt-3 pb-1">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Urgency Level</p>
                </div>
                <div class="divide-y dark:divide-gray-700">
                    @foreach($urgencyLevels as $level => $value)
                    <div class="flex items-center justify-between px-6 py-3 gap-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300 flex-1 capitalize">{{ ucwords(str_replace('_', ' ', $level)) }}</span>
                        <input type="number" name="levels[{{ $level }}]" value="{{ $value }}" min="1" max="10"
                            class="w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                    </div>
                    @endforeach
                </div>
                <div class="px-6 pt-4 pb-1 border-t dark:border-gray-700">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Urgency Weight</p>
                </div>
                <div class="flex items-center justify-between px-6 py-3 gap-4">
                    <span class="text-sm text-gray-700 dark:text-gray-300 flex-1">Urgency Weight</span>
                    <input type="number" name="urgency_weight" value="{{ $urgencyWeight }}" min="1" max="20"
                        class="w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                </div>
                <div class="px-6 py-4 border-t dark:border-gray-700">
                    <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none">
                        Save
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection
