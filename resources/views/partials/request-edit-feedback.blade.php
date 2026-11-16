@if($errors->any())
    <div class="portal-auth-errors" role="alert">
        <strong>{{ $editConflict ? 'Changes were not saved. Review the latest request.' : 'Please check the form.' }}</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @if($editConflict && session()->hasOldInput('status'))
            <details style="margin-top:12px">
                <summary>Your unsaved draft (for reference)</summary>
                <p>Status: {{ is_string(old('status')) ? (\App\Support\RequestStatus::LABELS[old('status')] ?? old('status')) : '(invalid)' }}</p>
                <p style="white-space:pre-wrap">Remarks: {{ is_string(old('remarks')) ? old('remarks') : '(none)' }}</p>
            </details>
            <p style="margin-top:10px">The editor now shows the latest saved values. Compare your draft before applying any changes.</p>
        @endif
    </div>
@endif
