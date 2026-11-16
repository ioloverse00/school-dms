@extends('layouts.dashboard')

@section('content')
@if($errors->any())<div class="portal-auth-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="w-full">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">Programs / Courses</h2>

    @if(session('success'))
    <div class="p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="card spark-form-card w-full overflow-hidden rounded-lg shadow-xs">
        <div class="bg-white dark:bg-gray-800 px-6 py-4 border-b dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200 mb-3">Add Program</h3>
            <form action="{{ route('admin.settings.programs.add') }}" method="POST" class="flex items-center gap-3">
                @csrf
                <input type="text" name="program" placeholder="e.g. BS Data Science"
                    class="form-control-custom spark-field block flex-1 text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple"
                    required>
                <button type="submit"
                    class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 focus:outline-none whitespace-nowrap">
                    + Add
                </button>
            </form>
            @error('program')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="w-full overflow-x-auto">
            <table class="table table-custom w-full whitespace-no-wrap">
                <thead>
                    <tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-700">
                        <th class="px-4 py-3">Program</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                    @php
                        $categoryColors = [
                            'BS'       => 'color:#267363; background:#f3e8ff;',
                            'AB'       => 'color:#1d4ed8; background:#dbeafe;',
                            'Bachelor' => 'color:#0369a1; background:#e0f2fe;',
                            'Masteral' => 'color:#15803d; background:#dcfce7;',
                            'Doctoral' => 'color:#b45309; background:#fef3c7;',
                            'Others'   => 'color:#6b7280; background:#f3f4f6;',
                        ];
                    @endphp
                    @forelse($paginator as $program)
                    @php $cat = $categoryMap[$program] ?? 'Others'; @endphp
                    <tr class="text-gray-700 dark:text-gray-400">
                        <td class="px-4 py-3 text-sm">{{ $program }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span style="padding:2px 10px; border-radius:9999px; font-size:0.7rem; font-weight:600; {{ $categoryColors[$cat] }}">
                                {{ $cat }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form action="{{ route('admin.settings.programs.delete') }}" method="POST" style="display:inline;">
                                @csrf @method('DELETE')
                                <input type="hidden" name="program" value="{{ $program }}">
                                <button type="submit" data-confirm="Remove {{ $program }}?"
                                    style="background:transparent; border:none; cursor:pointer; color:#ef4444; padding:4px;">
                                    <svg style="width:1rem;height:1rem;" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-sm text-center text-gray-400">No programs yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $paginator->links() }}
    </div>
</div>
@endsection
