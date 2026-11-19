<div class="portal-notification-item {{ is_null($notification->read_at) ? 'is-unread' : '' }}">
    <a class="portal-notification" href="{{ route('notifications.read', $notification->id) }}">
        <span>{{ $notification->data['message'] ?? 'Request updated' }}</span>
        <small><time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->format('M j, Y g:i A') }}">{{ $notification->created_at->diffForHumans() }}</time></small>
    </a>
    <form class="portal-notification-state" action="{{ route(is_null($notification->read_at) ? 'notifications.markRead' : 'notifications.markUnread', $notification->id) }}" method="POST">
        @csrf
        <button type="submit" class="portal-text-button">{{ is_null($notification->read_at) ? 'Mark as read' : 'Mark as unread' }}</button>
    </form>
</div>
