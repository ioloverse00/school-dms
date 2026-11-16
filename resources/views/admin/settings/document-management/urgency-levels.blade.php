@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">Urgency Level</h2>

    @if(session('success'))
    <div class="p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="card spark-form-card w-full overflow-hidden rounded-lg shadow-xs bg-white dark:bg-gray-800">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">Urgency Level</h3>
            <p class="text-xs text-gray-500 mt-1">Formula: <span class="font-mono">(Urgency Weight × Urgency Level) + Student Weight + Document Weight</span></p>
            <p class="text-xs text-gray-400 mt-1">Urgency Weight is fixed at <span class="font-semibold text-gray-500">5</span>.</p>
        </div>
        <form action="{{ route('admin.settings.urgency-levels.update') }}" method="POST">
            @csrf
            <div class="divide-y dark:divide-gray-700" style="margin-top: 0.5rem;">
                @foreach($urgencyLevels as $level => $value)
                <div class="flex items-center justify-between px-6 py-3 gap-4">
                    <span class="text-sm text-gray-700 dark:text-gray-300 flex-1 capitalize">{{ \App\Support\RequestUrgency::label($level) }}</span>
                    <input type="number" name="levels[{{ $level }}]" value="{{ $value }}" min="1" max="10"
                        class="form-control-custom spark-field w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                </div>
                @endforeach
            </div>
            <div class="px-6 py-4 border-t dark:border-gray-700">
                <button type="submit"
                    class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
