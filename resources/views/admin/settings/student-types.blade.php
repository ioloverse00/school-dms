@extends('layouts.dashboard')

@section('content')
<div class="container px-6 mx-auto">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">Student Categories</h2>

    @if(session('success'))
    <div class="p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="w-full overflow-hidden rounded-lg shadow-xs bg-white dark:bg-gray-800">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">Student Categories</h3>
            <p class="text-xs text-gray-500 mt-1">Manage student classification types used in requests and priority scoring.</p>
        </div>

        <div class="px-6 py-4 border-b dark:border-gray-700" style="background:#faf5ff;">
            <form action="{{ route('admin.settings.student-types.add') }}" method="POST">
                @csrf
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Value (no spaces)</label>
                        <input type="text" name="type_value" placeholder="e.g. transferee"
                            class="block w-full text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple"
                            required>
                        @error('type_value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Display Label</label>
                        <input type="text" name="type_label" placeholder="e.g. Transferee"
                            class="block w-full text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple"
                            required>
                        @error('type_label')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 focus:outline-none">
                    + Add Student Type
                </button>
            </form>
        </div>

        <ul class="divide-y dark:divide-gray-700">
            @forelse($studentTypes as $type)
            <li class="flex items-center justify-between px-6 py-3">
                <div>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $type['label'] }}</p>
                    <p class="text-xs text-gray-400 font-mono">{{ $type['value'] }}</p>
                </div>
                <form action="{{ route('admin.settings.student-types.delete') }}" method="POST">
                    @csrf @method('DELETE')
                    <input type="hidden" name="type_value" value="{{ $type['value'] }}">
                    <button type="submit" onclick="return confirm('Remove \'{{ addslashes($type['label']) }}\'?')"
                        style="background:transparent; border:none; cursor:pointer; color:#ef4444; padding:6px;">
                        <svg style="width:1rem;height:1rem;" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </form>
            </li>
            @empty
            <li class="px-6 py-6 text-sm text-center text-gray-400">No student types yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
