@extends('layouts.dashboard')

@section('content')
<div class="container px-6 mx-auto">
    <h2 class="mt-6 mb-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
        New Document Request
    </h2>

    <div class="bg-white rounded-lg shadow-xs dark:bg-gray-800 p-8 max-w-5xl mx-auto">

        @if($errors->any())
        <div class="p-4 mb-6 text-sm text-red-700 bg-red-100 rounded-lg">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Student Info --}}
        @php
            $categoryLabels = ['regular' => 'Regular Student', 'graduating' => 'Graduating Student', 'alumni' => 'Alumni'];
        @endphp
        <div class="mb-8 p-6 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4 uppercase tracking-wide">Student Information</h3>
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 1rem 3rem;">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Student ID</p>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $user->student_id ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Full Name</p>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $user->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Course/Program</p>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $user->course ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Student Category</p>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ $categoryLabels[$user->student_type] ?? ucfirst($user->student_type) }}
                    </p>
                </div>
                @if($user->student_type === 'alumni')
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Year Graduated</label>
                    <input type="number" name="year_graduated"
                        value="{{ old('year_graduated', $user->year_graduated) }}"
                        min="1990" max="{{ date('Y') }}"
                        placeholder="e.g. {{ date('Y') }}"
                        class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 focus:outline-none focus:border-purple-400">
                    @error('year_graduated')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @endif
            </div>
        </div>

        <form action="{{ route('student.requests.store') }}" method="POST" enctype="multipart/form-data" class="px-6 pb-6">
            @csrf

            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem 3rem;" class="mb-6">
                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Document Category <span class="text-red-500">*</span></span>
                    <select name="category" id="category"
                        class="block w-full mt-1 text-sm form-select dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300"
                        onchange="filterDocumentTypes(this.value)">
                        <option value="">-- Select Category --</option>
                        <option value="academic"       {{ old('category') === 'academic'       ? 'selected' : '' }}>Academic Documents</option>
                        <option value="certification"  {{ old('category') === 'certification'  ? 'selected' : '' }}>Certification Documents</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Document Type <span class="text-red-500">*</span></span>
                    <select name="document_type_id" id="document_type_id"
                        class="block w-full mt-1 text-sm form-select dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300">
                        <option value="">-- Select Category First --</option>
                        @foreach($documentTypes as $cat => $types)
                            @foreach($types as $type)
                                <option value="{{ $type->id }}"
                                    data-category="{{ $type->category }}"
                                    data-alumni-only="{{ $type->alumni_only ? '1' : '0' }}"
                                    {{ old('document_type_id') == $type->id ? 'selected' : '' }}
                                    style="display:none;">
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('document_type_id')
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Purpose</span>
                    <select name="purpose"
                        class="block w-full mt-1 text-sm form-select dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300">
                        <option value="">-- Select Purpose --</option>
                        <option value="Employment">Employment</option>
                        <option value="Transfer to another school">Transfer to another school</option>
                        <option value="Further studies">Further studies</option>
                        <option value="Scholarship">Scholarship</option>
                        <option value="Job application">Job application</option>
                        <option value="Personal requirements">Personal requirements</option>
                        <option value="Others">Others</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Urgency Level</span>
                    <select name="urgency"
                        class="block w-full mt-1 text-sm form-select dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300">
                        <option value="regular">Regular</option>
                        <option value="urgent">Urgent</option>
                        <option value="very_urgent">Very Urgent</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Number of Copies</span>
                    <input type="number" name="copies" value="1" min="1"
                        class="block w-full mt-1 text-sm form-input dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300">
                </label>
            </div>

            <hr class="mb-6 border-gray-200 dark:border-gray-700">

            {{-- Section: Attachments --}}
            <p class="mb-4 text-sm font-semibold text-gray-600 dark:text-gray-400">Attachments</p>
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem 3rem;" class="mb-6">
                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Valid ID <span class="text-red-500">*</span></span>
                    <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full mt-1 text-sm form-input dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    @error('valid_id')
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Proof of Payment <span class="text-red-500">*</span></span>
                    <input type="file" name="proof_of_payment" accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full mt-1 text-sm form-input dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    @error('proof_of_payment')
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Request Form <span class="text-xs text-gray-400">(optional)</span></span>
                    <input type="file" name="request_form" accept=".pdf,.jpg,.jpeg,.png"
                        class="block w-full mt-1 text-sm form-input dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                </label>
            </div>

            <div class="flex flex-col mt-8 mb-4 space-y-4 sm:flex-row sm:justify-end sm:space-y-0 sm:space-x-6">
                <a href="{{ route('student.dashboard') }}"
                    class="px-5 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:border-gray-600 dark:hover:bg-gray-700">
                    Cancel
                </a>
                <button type="submit"
                    class="px-5 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var isAlumni = '{{ $user->student_type }}' === 'alumni';

function filterDocumentTypes(category) {
    const select = document.getElementById('document_type_id');
    const options = select.querySelectorAll('option[data-category]');
    const placeholder = select.querySelector('option:not([data-category])');

    placeholder.textContent = category ? '-- Select Document Type --' : '-- Select Category First --';
    select.value = '';

    options.forEach(function(opt) {
        var categoryMatch = !category || opt.dataset.category === category;
        var alumniMatch = opt.dataset.alumniOnly !== '1' || isAlumni;

        if (categoryMatch && alumniMatch) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
            if (opt.selected) opt.selected = false;
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const savedCategory = '{{ old('category') }}';
    if (savedCategory) {
        filterDocumentTypes(savedCategory);
        const oldDocType = '{{ old('document_type_id') }}';
        if (oldDocType) {
            document.getElementById('document_type_id').value = oldDocType;
        }
    }
});
</script>
@endsection
