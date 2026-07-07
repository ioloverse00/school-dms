@extends('layouts.dashboard')

@section('content')
<div class="container px-6 mx-auto">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">Student Category Weight</h2>

    @if(session('success'))
    <div class="p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="w-full overflow-hidden rounded-lg shadow-xs bg-white dark:bg-gray-800">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">Student Category Weight</h3>
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
</div>
@endsection
