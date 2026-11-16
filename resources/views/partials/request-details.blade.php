@php
    $isStaff = !empty($staffPrefix);
    $routePrefix = $isStaff ? $staffPrefix : 'student';
    $editConflict = $errors->has('edit_token');
    $editToken = \App\Support\RequestEditToken::for($documentRequest);
    $formToken = $editConflict ? $editToken : old('edit_token', $editToken);
    $selectedStatus = $editConflict ? $documentRequest->status : old('status', $documentRequest->status);
    $editingRemarks = $editConflict ? $documentRequest->remarks : old('remarks', $documentRequest->remarks);
    $statuses = \App\Support\RequestStatus::LABELS;
    $category = \App\Support\StudentCategory::labels()[$documentRequest->student_category] ?? ucfirst($documentRequest->student_category);
    $steps = array_keys(array_diff_key($statuses, ['rejected' => true]));
    $currentStep = array_search($documentRequest->status, $steps, true);
    $statusHelp = [
        'pending_verification' => 'Your request is awaiting verification by the registrar.',
        'processing' => 'Your document is currently being prepared.',
        'ready_for_release' => 'Your document is ready! Please claim it at the registrar\'s office.',
        'completed' => 'Your request has been completed.',
        'rejected' => 'Your request was rejected. See the registrar remarks below.',
    ];
@endphp
<div class="request-detail">
    @if($isStaff) @include('partials.request-edit-feedback') @endif
    <header class="request-detail-header">
        <div>
            @if($isStaff)<a class="request-back" href="{{ route($routePrefix . '.requests.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> All requests</a>@endif
            <h1>Request Details</h1>
            <p class="request-reference">{{ $documentRequest->request_id }}</p>
        </div>
        @if(!$isStaff)
        <a class="btn btn-primary request-my-requests" href="{{ route('student.requests.index') }}">My requests</a>
        @endif
        @if($isStaff)
        <form method="POST" action="{{ route($staffPrefix . '.requests.' . ($documentRequest->archived_at ? 'unarchive' : 'archive'), $documentRequest->id) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="edit_token" value="{{ $formToken }}">
            <button class="btn btn-outline-primary" type="submit" @if(!$documentRequest->archived_at) data-confirm="Archive this request?" @endif><i class="bi bi-archive" aria-hidden="true"></i> {{ $documentRequest->archived_at ? 'Unarchive' : 'Archive' }}</button>
        </form>
        @endif
    </header>

    <section class="card request-summary" aria-labelledby="request-document-heading">
        <div class="request-document-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></div>
        <div class="request-summary-copy">
            <p class="request-eyebrow">Requested document</p>
            <h2 id="request-document-heading">{{ $documentRequest->documentType->name ?? 'Document unavailable' }}</h2>
            <p>{{ $documentRequest->copies }} {{ Str::plural('copy', $documentRequest->copies) }} &middot; Submitted {{ $documentRequest->created_at?->format('F j, Y · g:i A') }}</p>
        </div>
        <div class="request-summary-status">
            <span class="portal-status portal-status-{{ $documentRequest->status }}">{{ $statuses[$documentRequest->status] ?? $documentRequest->status }}</span>
            @if($documentRequest->archived_at)<span class="portal-status">Archived</span>@endif
        </div>
    </section>

    @if(!$isStaff)
    <section class="card request-section" aria-labelledby="request-progress-heading">
        <h2 id="request-progress-heading">Request progress</h2>
        <p class="request-help">{{ $statusHelp[$documentRequest->status] ?? '' }}</p>
        @if($currentStep !== false)
        <ol class="request-progress">
            @foreach($steps as $index => $step)
            <li class="{{ $index <= $currentStep ? 'is-reached' : '' }}" @if($index === $currentStep) aria-current="step" @endif><span>{{ $index < $currentStep ? '✓' : $index + 1 }}</span>{{ $statuses[$step] }}</li>
            @endforeach
        </ol>
        @endif
    </section>
    @endif

    <div class="request-detail-grid">
            <section class="card request-section request-info-panel" aria-labelledby="request-information-heading">
                <h2 id="request-information-heading">Request Information</h2>
                <dl class="request-facts">
                    <div class="request-fact-wide"><dt>Purpose</dt><dd>{{ $documentRequest->purpose ?: 'Not specified' }}</dd></div>
                    <div><dt>Urgency</dt><dd>{{ \App\Support\RequestUrgency::label($documentRequest->urgency) }}</dd></div>
                    <div><dt>Priority Score</dt><dd><span class="request-priority">{{ $documentRequest->priority_score }}</span></dd></div>
                    <div><dt>Student Category</dt><dd>{{ $category }}</dd></div>
                    <div><dt>Copies</dt><dd>{{ $documentRequest->copies }}</dd></div>
                </dl>
            </section>
            <section class="card request-section request-student-panel" aria-labelledby="request-student-heading">
                <h2 id="request-student-heading">{{ $isStaff ? 'Student Information' : 'Your Information' }}</h2>
                <dl class="request-facts">
                    <div><dt>Full Name</dt><dd>{{ $documentRequest->user->name ?? 'N/A' }}</dd></div>
                    <div><dt>Student ID</dt><dd>{{ $documentRequest->user->student_id ?? 'N/A' }}</dd></div>
                    <div><dt>Course / Program</dt><dd>{{ $documentRequest->user->course ?? 'N/A' }}</dd></div>
                    <div><dt>Year Graduated</dt><dd>{{ $documentRequest->user->year_graduated ?? 'N/A' }}</dd></div>
                </dl>
            </section>
            <section class="card request-section request-files-panel" aria-labelledby="request-files-heading">
                <h2 id="request-files-heading">Uploaded Documents <span class="request-count">{{ $documentRequest->requiredFiles->count() }}</span></h2>
                <div class="request-files">
                    @forelse($documentRequest->requiredFiles as $file)
                    <a class="request-file" href="{{ route('files.view', $file->id) }}" target="_blank" rel="noopener" title="View {{ $file->original_name }} (opens in a new tab)">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                        <span><strong>{{ ucwords(str_replace('_', ' ', $file->file_type)) }}</strong><small>{{ $file->original_name }}</small></span>
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                    </a>
                    @empty
                    <p class="request-empty">No files uploaded.</p>
                    @endforelse
                </div>
            </section>
            @if($isStaff)
            <section class="card request-section request-update-panel" aria-labelledby="request-update-heading">
                <h2 id="request-update-heading">Update Status</h2>
                <p class="request-help">Keep the student informed of their request.</p>
                <form action="{{ route($staffPrefix . '.requests.update', $documentRequest->id) }}" method="POST" class="request-update-form">
                    @csrf @method('PUT')
                    <input type="hidden" name="edit_token" value="{{ $formToken }}">
                    <div><label for="request-status">Current Status</label>
                    <select id="request-status" name="status" class="form-select spark-field">
                        @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach
                    </select></div>
                    <div><label for="request-remarks">Remarks <span>(required)</span></label>
                    <textarea id="request-remarks" name="remarks" rows="5" required placeholder="Explain the update and what the student should do next." class="form-control spark-field">{{ $editingRemarks }}</textarea></div>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            </section>
            @else
            <section class="card request-section request-update-panel" aria-labelledby="request-remarks-heading">
                <h2 id="request-remarks-heading">Remarks from Registrar</h2>
                <p class="request-remarks">{{ $documentRequest->remarks ?: 'No remarks yet.' }}</p>
            </section>
            @endif
    </div>
    @include('partials.request-history', ['showActor' => $isStaff])
</div>
