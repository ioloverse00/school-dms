@extends('layouts.dashboard')
@section('title', 'Student overview')
@section('content')
<div class="page-title-row portal-page-header"><div><p class="portal-eyebrow">Student services</p><h1 class="page-title portal-heading">Your document requests</h1><p class="portal-subtitle">Submit a request and follow its progress from review to release.</p></div><a class="btn btn-primary" href="{{ route('student.requests.create') }}">+ New document request</a></div>
<div class="portal-metrics">
    @foreach([
        ['Total requests', $totalRequests, 'Your complete request history'],
        ['Pending review', $pendingRequests, 'Awaiting verification'],
        ['In processing', $processingRequests, 'Documents being prepared'],
        ['Ready / Done', $readyRequests, 'Ready for release & completed'],
    ] as [$label, $value, $note])
        <div class="card card-stat portal-metric"><div class="stat-label portal-metric-label">{{ $label }}</div><div class="stat-value portal-metric-value">{{ number_format($value) }}</div><div class="portal-metric-note">{{ $note }}</div></div>
    @endforeach
</div>
@if(session('success'))<div class="portal-auth-status" role="status">{{ session('success') }}</div>@endif
<div class="portal-dashboard-grid">
    <section class="card portal-card" aria-labelledby="recent-heading"><div class="card-header portal-card-header"><div><h2 id="recent-heading">Recent requests</h2><p class="portal-card-subtitle">Your latest submissions and their current status</p></div><a class="portal-inline-link" href="{{ route('student.requests.index') }}">View all →</a></div>
        <div class="portal-table-wrap"><table class="table table-custom portal-table"><thead><tr><th>Document / request</th><th>Status</th><th>Requested</th><th>Actions</th></tr></thead><tbody>
        @forelse($recentRequests as $req)
            <tr><td><div class="portal-person">{{ $req->documentType->name }}</div><div class="portal-table-sub">{{ $req->request_id }} · {{ $req->copies }} {{ Str::plural('copy', $req->copies) }}</div></td><td><span class="portal-status portal-status-{{ $req->status }}">{{ \App\Support\RequestStatus::LABELS[$req->status] ?? $req->status }}</span></td><td>{{ $req->created_at->format('M d, Y') }}</td><td><a class="portal-table-view" href="{{ route('student.requests.show', $req->id) }}" aria-label="View request {{ $req->request_id }}" title="View request {{ $req->request_id }}"><i class="bi bi-eye-fill" aria-hidden="true"></i></a></td></tr>
        @empty<tr><td colspan="4" class="portal-empty">No requests yet. <a href="{{ route('student.requests.create') }}">Submit your first request →</a></td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="card portal-card"><div class="card-header portal-card-header"><h2>Before you request</h2></div><div class="card-body portal-card-body"><div class="portal-guidance"><h3>Prepare your requirements</h3><p>Choose the document you need and attach the required proof of payment.</p></div><div class="portal-guidance"><h3>Follow your progress</h3><p>Open a request to see registrar remarks and its recorded status history.</p></div><div class="portal-guidance"><h3>Wait for release confirmation</h3><p>Check your notifications for updates from the registrar.</p></div></div></section>
</div>
<p class="portal-note">Your totals include archived requests. Pending means awaiting verification; Ready / Done includes ready-for-release and completed requests.</p>
@endsection