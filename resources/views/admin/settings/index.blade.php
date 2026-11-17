@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
        System Settings
    </h2>
    <p class="mb-4"><a class="btn btn-outline-secondary" href="{{ route('admin.settings.academic-calendar') }}">Academic calendar</a></p>

    @if(session('success'))
    <div x-data="{ feedbackVisible: true }" x-show="feedbackVisible" x-init="setTimeout(() => feedbackVisible = false, 3000)" class="p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    <div id="programs" style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: start;">

        {{-- ===== PROGRAMS / COURSES ===== --}}
        <div class="card spark-form-card bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">Programs / Courses</h3>
                <p class="text-xs text-gray-500 mt-1">Manage the list of available academic programs.</p>
            </div>

            {{-- Add Program --}}
            <div class="px-6 py-4 border-b dark:border-gray-700" style="background:#faf5ff;">
                <form action="{{ route('admin.settings.programs.add') }}" method="POST" class="flex items-center gap-3">
                    @csrf
                    <input type="text" name="program" placeholder="e.g. BS Data Science"
                        class="form-control-custom spark-field block flex-1 text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple"
                        required>
                    <button type="submit"
                        class="btn btn-primary px-4 py-2 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple whitespace-nowrap"
                        style="margin-left: 0.75rem;">
                        + Add
                    </button>
                </form>
                @error('program')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Programs List --}}
            <ul class="divide-y dark:divide-gray-700" style="max-height: 480px; overflow-y: auto;">
                @forelse($programs as $program)
                <li class="flex items-center justify-between px-6 py-3">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $program }}</span>
                    <form action="{{ route('admin.settings.programs.delete') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="program" value="{{ $program }}">
                        <button type="submit"
                            data-confirm="Remove {{ $program }}?"
                            style="background:transparent; border:none; cursor:pointer; color:#ef4444; padding:6px;"
                            aria-label="Delete">
                            <svg style="width:1rem;height:1rem;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </form>
                </li>
                @empty
                <li class="px-6 py-6 text-sm text-center text-gray-400">No programs yet.</li>
                @endforelse
            </ul>

            <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 border-t dark:border-gray-700 rounded-b-lg">
                <p class="text-xs text-gray-400">{{ count($programs) }} program(s) total</p>
            </div>
        </div>

        {{-- ===== AI FORECAST PROMPT ===== --}}
        <div class="card spark-form-card bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">AI Forecast Prompt</h3>
                <p class="text-xs text-gray-500 mt-1">Custom instructions Gemini will follow when forecasting trends.</p>
            </div>

            <form action="{{ route('admin.settings.ai-forecast-prompt.update') }}" method="POST">
                @csrf
                <div class="px-6 py-4">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Prompt (optional)</label>
                    <textarea name="prompt" rows="8"
                        placeholder="Example: Graduation season is June–July; expect more Transcript of Records and Certificate of Graduation requests."
                        class="form-control-custom spark-field block w-full text-sm form-textarea dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">{{ old('prompt', $aiForecastPrompt ?? '') }}</textarea>
                    @error('prompt')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-gray-400">Max 2000 characters.</p>
                </div>

                <div class="px-6 py-4 border-t dark:border-gray-700">
                    <button type="submit"
                        class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                        Save Prompt
                    </button>
                </div>
            </form>
        </div>

        {{-- ===== STUDENT TYPES ===== --}}
        <div id="student-types">
            <div class="card spark-form-card bg-white rounded-lg shadow-xs dark:bg-gray-800 mb-6">
                <div class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">Student Categories</h3>
                    <p class="text-xs text-gray-500 mt-1">Manage student classification types used in requests and priority scoring.</p>
                </div>

                {{-- Add Student Type --}}
                <div class="px-6 py-4 border-b dark:border-gray-700" style="background:#faf5ff;">
                    <form action="{{ route('admin.settings.student-types.add') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Value (no spaces)</label>
                                <input type="text" name="type_value" placeholder="e.g. transferee"
                                    class="form-control-custom spark-field block w-full text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple"
                                    required>
                                @error('type_value')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Display Label</label>
                                <input type="text" name="type_label" placeholder="e.g. Transferee"
                                    class="form-control-custom spark-field block w-full text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple"
                                    required>
                                @error('type_label')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <button type="submit"
                            class="btn btn-primary px-4 py-2 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                            style="margin-top: 0.75rem;">
                            + Add Student Type
                        </button>
                    </form>
                </div>

                {{-- Student Types List --}}
                <ul class="divide-y dark:divide-gray-700">
                    @forelse($studentTypes as $type)
                    <li class="flex items-center justify-between px-6 py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $type['label'] }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $type['value'] }}</p>
                        </div>
                        <form action="{{ route('admin.settings.student-types.delete') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="type_value" value="{{ $type['value'] }}">
                            <button type="submit"
                                data-confirm="Remove {{ $type['label'] }}?"
                                style="background:transparent; border:none; cursor:pointer; color:#ef4444; padding:6px;"
                                aria-label="Delete">
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

            {{-- Info card --}}
            <div class="p-4 rounded-lg" style="background:#ede9fe;">
                <p class="text-xs font-semibold" style="color:#5521b5;">Note</p>
                <p class="text-xs mt-1" style="color:#6d28d9;">Changes to programs and student types apply immediately to the Add/Edit Student forms. Existing student records are not affected.</p>
            </div>
        </div>

    </div>

    {{-- ===== DOCUMENT MANAGEMENT ===== --}}
    <h3 id="document-management" class="mt-10 mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">Document Management</h3>
    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; align-items: start;">

        {{-- Document Type Weights --}}
        <div class="card spark-form-card bg-white rounded-lg shadow-xs dark:bg-gray-800">
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
                            class="form-control-custom spark-field w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                    </div>
                    @endforeach
                </div>
                <div class="px-6 py-4 border-t dark:border-gray-700">
                    <button type="submit"
                        class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                        Save Weights
                    </button>
                </div>
            </form>
        </div>

        {{-- Student Category Weights --}}
        <div class="card spark-form-card bg-white rounded-lg shadow-xs dark:bg-gray-800">
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
                            class="form-control-custom spark-field w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                    </div>
                    @endforeach
                </div>
                <div class="px-6 py-4 border-t dark:border-gray-700">
                    <button type="submit"
                        class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                        Save Weights
                    </button>
                </div>
            </form>
        </div>

        {{-- Urgency Level Values --}}
        <div class="card spark-form-card bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h4 class="text-base font-semibold text-gray-700 dark:text-gray-200">Urgency Level</h4>
                <p class="text-xs text-gray-500 mt-1">Formula: <span class="font-mono">(Urgency Weight × Urgency Level) + Student Weight + Document Weight</span></p>
            </div>
            <form action="{{ route('admin.settings.urgency-levels.update') }}" method="POST">
                @csrf
                {{-- Urgency Level Values --}}
                <div class="px-6 pt-3 pb-1">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Urgency Level</p>
                </div>
                <div class="divide-y dark:divide-gray-700">
                    @foreach($urgencyLevels as $level => $value)
                    <div class="flex items-center justify-between px-6 py-3 gap-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300 flex-1 capitalize">{{ \App\Support\RequestUrgency::label($level) }}</span>
                        <input type="number" name="levels[{{ $level }}]" value="{{ $value }}" min="1" max="10"
                            class="form-control-custom spark-field w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                    </div>
                    @endforeach
                </div>
                {{-- Urgency Weight --}}
                <div class="px-6 pt-4 pb-1 border-t dark:border-gray-700">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Urgency Weight</p>
                </div>
                <div class="flex items-center justify-between px-6 py-3 gap-4">
                    <span class="text-sm text-gray-700 dark:text-gray-300 flex-1">Urgency Weight</span>
                    <input type="number" name="urgency_weight" value="{{ $urgencyWeight }}" min="1" max="20"
                        class="form-control-custom spark-field w-16 text-sm text-center form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                </div>
                <div class="px-6 py-4 border-t dark:border-gray-700">
                    <button type="submit"
                        class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                        Save
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection
