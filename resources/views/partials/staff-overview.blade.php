@php $staffPrefix = auth()->user()->hasRole('admin') ? 'admin' : 'registrar'; $categoryLabels = \App\Support\StudentCategory::labels(); @endphp
<div class="page-title-row portal-page-header">
    <div><p class="portal-eyebrow">Office of the Registrar</p><h1 class="page-title portal-heading">Document services overview</h1><p class="portal-subtitle">A clear view of requests that need your attention.</p></div>
</div>
<div class="portal-metrics">
    @foreach([
        ['Active requests', $totalRequests, 'Excludes completed and archived records', null, null],
        ['Pending review', $pendingRequests, 'Awaiting verification', '#d2a654', 'pending_review'],
        ['In processing', $processingRequests, 'Currently being prepared', '#6786b1', 'processing'],
        ['Ready for release', $readyRequests, 'Awaiting student collection', '#548d70', 'ready_for_release'],
    ] as [$label, $value, $note, $color, $filter])
        <a class="card card-stat portal-metric portal-metric-link" href="{{ route($staffPrefix . '.requests.index', $filter ? ['status' => $filter] : []) }}" title="View {{ strtolower($label) }}" aria-label="{{ $label }}: {{ number_format($value) }}. View matching requests"><div class="stat-label portal-metric-label">{{ $label }}</div><div class="stat-value portal-metric-value">{{ number_format($value) }}</div><div class="portal-metric-note">@if($color)<span class="portal-dot" style="background:{{ $color }}"></span>@endif{{ $note }}</div></a>
    @endforeach
</div>
@if(session('success'))<div x-data="{ feedbackVisible: true }" x-show="feedbackVisible" x-init="setTimeout(() => feedbackVisible = false, 3000)" class="portal-auth-status" role="status">{{ session('success') }}</div>@endif
<div class="portal-dashboard-grid">
    <section class="card portal-card" aria-labelledby="queue-heading">
        <div class="card-header portal-card-header"><div><h2 id="queue-heading">Priority queue</h2><p class="portal-card-subtitle">Highest priority first · Oldest first when scores match</p></div><span class="portal-status">Top {{ $recentRequests->count() }} requests</span></div>
        <div class="portal-table-wrap"><table class="table table-custom portal-table"><thead><tr><th>Student / request</th><th>Document</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse($recentRequests as $req)
                <tr><td><div class="portal-person">{{ $req->user->name }}</div><div class="portal-table-sub">{{ $req->request_id }} · {{ $categoryLabels[$req->student_category] ?? ucfirst($req->student_category) }}</div></td><td>{{ $req->documentType->name }}<div class="portal-table-sub">{{ $req->copies }} {{ Str::plural('copy', $req->copies) }}</div></td><td><strong>{{ $req->priority_score }}</strong><div class="portal-table-sub">{{ \App\Support\RequestUrgency::label($req->urgency) }}</div></td><td><span class="portal-status portal-status-{{ $req->status }}">{{ \App\Support\RequestStatus::LABELS[$req->status] ?? $req->status }}</span></td><td><a class="portal-table-view" href="{{ route($staffPrefix . '.requests.show', $req->id) }}" aria-label="View request {{ $req->request_id }}" title="View request {{ $req->request_id }}"><i class="bi bi-eye-fill" aria-hidden="true"></i></a></td></tr>
            @empty<tr><td colspan="5" class="portal-empty">No active requests in the queue. New submissions will appear here.</td></tr>@endforelse
        </tbody></table></div>
        <div class="card-footer portal-card-footer"><span>Unarchived pending, processing and ready requests</span><a class="portal-inline-link" href="{{ route($staffPrefix . '.requests.index') }}">View all →</a></div>
    </section>
    <section class="card portal-card" aria-labelledby="glance-heading"><div class="card-header portal-card-header"><h2 id="glance-heading">At a glance</h2></div><div class="card-body portal-card-body">
        <div class="portal-guidance"><h3>Completed requests <span style="float:right">{{ number_format($completedRequests) }}</span></h3><p>All-time completed records, including archived requests.</p></div>
        <div class="portal-guidance"><h3>Student accounts <span style="float:right">{{ number_format($totalStudents) }}</span></h3><a href="{{ route($staffPrefix . '.students.index') }}" class="portal-inline-link">Manage students →</a></div>
        <div class="portal-guidance"><h3>Keep the queue moving</h3><p>Review submitted requirements and payment proofs before preparation.</p></div>
        <div class="portal-guidance"><h3>Release with confidence</h3><p>Confirm the request and student identity before marking a document completed.</p></div>
        <div class="portal-guidance"><h3>Keep records organized</h3><p>Archive completed requests after release to keep the working list focused. Archived records remain available for reports.</p></div>
    </div></section>
</div>
<section class="portal-demand" aria-label="Document demand and forecasting">
    @include('partials.forecasting', ['hideRequestCards' => true])
</section>
