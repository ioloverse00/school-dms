@include('partials.notification-toolbar', ['fullPage' => false])
<div class="portal-notification-list">
    @forelse($portalNotifications as $notification)
        @include('partials.notification-item', ['notification' => $notification])
    @empty
        <p class="portal-empty-small">{{ $notificationFilter === 'unread' ? 'No unread notifications.' : 'No notifications yet.' }}</p>
    @endforelse
</div>
<div class="portal-notification-footer">
    <a href="{{ route('notifications.index', ['notification_filter' => $notificationFilter]) }}">View all notifications</a>
</div>
