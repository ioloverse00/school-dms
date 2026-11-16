<section class="card spark-form-card p-4 mb-8 bg-white rounded-lg shadow-xs dark:bg-gray-800" aria-labelledby="request-history-heading">
    <h3 id="request-history-heading" class="mb-2 text-lg font-semibold text-gray-700 dark:text-gray-200">Request History</h3>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Only actions recorded after history tracking was enabled are shown. Earlier actions are unavailable.</p>
    @forelse($history as $event)
        <article class="py-3 border-b dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300">
            <p class="font-semibold">{{ ucwords(str_replace('_', ' ', $event->action)) }}</p>
            <p><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M d, Y H:i T') }}</time>@if($showActor ?? false) &middot; {{ $event->actor_name ?? 'System' }}@endif</p>
            @if($event->from_status !== $event->to_status)
                <p>{{ \App\Support\RequestStatus::LABELS[$event->from_status] ?? 'New request' }} &rarr; {{ \App\Support\RequestStatus::LABELS[$event->to_status] ?? $event->to_status }}</p>
            @endif
            @if($event->old_remarks !== $event->new_remarks)
                @if($showActor ?? false)
                    <p class="mt-2 whitespace-pre-wrap">Previous remarks: {{ $event->old_remarks ?? '(none)' }}</p>
                @endif
                <p class="mt-2 whitespace-pre-wrap">Remarks: {{ $event->new_remarks ?? '(cleared)' }}</p>
            @endif
        </article>
    @empty
        <p class="text-sm text-gray-600 dark:text-gray-400">No recorded changes yet.</p>
    @endforelse
    <div class="mt-4">{{ $history->withQueryString()->links() }}</div>
</section>
