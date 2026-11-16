@extends('layouts.dashboard')

@section('content')
<div class="new-request-page">
    <h1 class="new-request-title">
        New Document Request
    </h1>
    <p class="new-request-intro">Choose your document, tell us what you need it for, and attach your payment proof.</p>

    <div class="card new-request-card">

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
            $categoryLabels = \App\Support\StudentCategory::labels();
        @endphp
        <div class="new-request-student">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4 uppercase tracking-wide">Student Information</h3>
            <div class="new-request-student-grid">
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
                    <input type="number" name="year_graduated" required form="document-request-form"
                        value="{{ old('year_graduated', $user->year_graduated) }}"
                        min="1990" max="{{ date('Y') }}"
                        placeholder="e.g. {{ date('Y') }}"
                        class="form-control-custom spark-field w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 focus:outline-none focus:border-purple-400">
                    @error('year_graduated')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @endif
            </div>
        </div>

        <form id="document-request-form" action="{{ route('student.requests.store') }}" method="POST" enctype="multipart/form-data" class="new-request-form">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="new-request-fields">
                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Document Category <span class="text-red-500">*</span></span>
                    <select name="category" id="category" required
                        class="form-select-custom spark-field block w-full mt-1 text-sm form-select dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300"
                        onchange="filterDocumentTypes(this.value)">
                        <option value="">-- Select Category --</option>
                        <option value="academic"       {{ old('category') === 'academic'       ? 'selected' : '' }}>Academic Documents</option>
                        <option value="certification"  {{ old('category') === 'certification'  ? 'selected' : '' }}>Certification Documents</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Document Type <span class="text-red-500">*</span></span>
                    <select name="document_type_id" id="document_type_id" required disabled
                        class="form-select-custom spark-field block w-full mt-1 text-sm form-select dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300">
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
                    <span class="text-sm text-gray-700 dark:text-gray-400">Purpose <span class="text-red-500">*</span></span>
                    <select name="purpose" required
                        class="form-select-custom spark-field block w-full mt-1 text-sm form-select dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300">
                        <option value="">-- Select Purpose --</option>
                        <option value="Employment" @selected(old('purpose') === 'Employment')>Employment</option>
                        <option value="Transfer to another school" @selected(old('purpose') === 'Transfer to another school')>Transfer to another school</option>
                        <option value="Further studies" @selected(old('purpose') === 'Further studies')>Further studies</option>
                        <option value="Scholarship" @selected(old('purpose') === 'Scholarship')>Scholarship</option>
                        <option value="Job application" @selected(old('purpose') === 'Job application')>Job application</option>
                        <option value="Personal requirements" @selected(old('purpose') === 'Personal requirements')>Personal requirements</option>
                        <option value="Others" @selected(old('purpose') === 'Others')>Others</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Urgency Level <span class="text-red-500">*</span></span>
                    <select name="urgency" required
                        class="form-select-custom spark-field block w-full mt-1 text-sm form-select dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300">
                        <option value="regular" @selected(old('urgency', 'regular') === 'regular')>Normal</option>
                        <option value="urgent" @selected(old('urgency', 'regular') === 'urgent')>Urgent</option>
                        <option value="very_urgent" @selected(old('urgency', 'regular') === 'very_urgent')>Very Urgent</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Number of Copies <span class="text-red-500">*</span></span>
                    <input type="number" name="copies" required value="{{ old('copies', 1) }}" min="1" max="{{ config('document_requests.max_copies', 100) }}"
                        class="form-control-custom spark-field block w-full mt-1 text-sm form-input dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300">
                </label>
            </div>

            <h2 class="new-request-section-title">Payment proof</h2><p class="new-request-hint">Upload a PDF, JPG or PNG, up to 10 MB. All fields marked * are required.</p>

            {{-- Section: Attachments --}}
            
            <div class="new-request-fields">
                <label class="block">
                    <span class="text-sm text-gray-700 dark:text-gray-400">Proof of Payment <span class="text-red-500">*</span></span>
                    <input type="file" name="proof_of_payment" required accept=".pdf,.jpg,.jpeg,.png"
                        class="form-control-custom spark-field block w-full mt-1 text-sm form-input dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    @error('proof_of_payment')
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </label>
            </div>

            <div class="new-request-actions">
                <a href="{{ route('student.dashboard') }}"
                    class="btn btn-outline-primary">
                    Cancel
                </a>
                <button type="submit"
                    class="btn btn-primary px-5 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">
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
    select.disabled = !category;

    options.forEach(function(opt) {
        var categoryMatch = !!category && opt.dataset.category === category;
        var alumniMatch = opt.dataset.alumniOnly !== '1' || isAlumni;

        if (categoryMatch && alumniMatch) {
            opt.style.display = '';
            opt.disabled = false;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
            if (opt.selected) opt.selected = false;
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const savedCategory = @json(old('category', ''));
    filterDocumentTypes(savedCategory);
    if (savedCategory) {
        filterDocumentTypes(savedCategory);
        const oldDocType = @json(old('document_type_id', ''));
        if (oldDocType) {
            document.getElementById('document_type_id').value = oldDocType;
        }
    }
});
</script>
@endsection
