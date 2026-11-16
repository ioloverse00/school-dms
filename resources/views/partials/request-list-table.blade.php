@php($categoryLabels = \App\Support\StudentCategory::labels())
<div class="card portal-card mb-8">
    <div class="portal-request-list">
        <table class="table table-custom portal-table portal-request-table">
            <colgroup><col style="width:6%"><col style="width:22%"><col style="width:24%"><col style="width:12%"><col style="width:16%"><col style="width:12%"><col style="width:8%"></colgroup>
            <thead><tr><th scope="col">Rank</th><th scope="col">Student</th><th scope="col">Document</th><th scope="col">Priority</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col">Actions</th></tr></thead>
            <tbody>
                @forelse($requests as $req)
                <tr>
                    <td data-label="Rank"><span class="portal-inline-link">#{{ $requests->firstItem() + $loop->index }}</span></td>
                    <td data-label="Student"><div class="portal-person">{{ $req->user->name }}</div><div class="portal-table-sub">{{ $req->user->student_id ?? $req->request_id }}</div><div class="portal-table-sub">{{ $categoryLabels[$req->student_category] ?? ucfirst($req->student_category) }}</div></td>
                    <td data-label="Document"><div class="portal-person">{{ $req->documentType->name }}</div><div class="portal-table-sub">{{ $req->copies }} {{ Str::plural('copy', $req->copies) }} · {{ $req->request_id }}</div></td>
                    <td data-label="Priority"><strong>{{ $req->priority_score }}</strong><div class="portal-table-sub">{{ \App\Support\RequestUrgency::label($req->urgency) }}</div></td>
                    <td data-label="Status"><span class="portal-status portal-status-{{ $req->status }}">{{ \App\Support\RequestStatus::LABELS[$req->status] ?? $req->status }}</span></td>
                    <td data-label="Date"><time datetime="{{ $req->created_at->toDateString() }}">{{ $req->created_at->format('M d, Y') }}</time></td>
                    <td data-label="Actions"><a class="portal-table-view" href="{{ route($requestRoutePrefix . '.requests.show', $req->id) }}" aria-label="View request {{ $req->request_id }}" title="View request {{ $req->request_id }}"><i class="bi bi-eye-fill" aria-hidden="true"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="7" class="portal-empty">{{ $showArchived ? 'No archived requests.' : 'No requests yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
</div>
