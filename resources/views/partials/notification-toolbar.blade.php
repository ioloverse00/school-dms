<div class="portal-notification-toolbar">
    <nav class="portal-notification-filters" aria-label="Notification filters">
        @foreach(['all' => 'All', 'unread' => 'Unread ('.$unreadCount.')'] as $filter => $label)
            <a @unless($fullPage) data-notification-filter="{{ $filter }}" @endunless href="{{ $fullPage ? route('notifications.index', ['notification_filter' => $filter]) : '?notification_filter='.$filter }}" @if($notificationFilter === $filter) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="portal-notification-bulk">
        <form action="{{ route('notifications.markAllRead') }}" method="POST">@csrf<button type="submit" class="portal-text-button">Mark all as read</button></form>
        <form action="{{ route('notifications.markAllUnread') }}" method="POST">@csrf<button type="submit" class="portal-text-button">Mark all as unread</button></form>
    </div>
</div>
