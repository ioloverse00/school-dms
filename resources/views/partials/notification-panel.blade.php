                        <div class="portal-notification-toolbar"><nav class="portal-notification-filters" aria-label="Notification filters">
                            <a data-notification-filter="all" href="?notification_filter=all" @if($notificationFilter === 'all') aria-current="page" @endif>All</a>
                            <a data-notification-filter="unread" href="?notification_filter=unread" @if($notificationFilter === 'unread') aria-current="page" @endif>Unread ({{ $unreadCount }})</a>
                        </nav>
                        <div class="portal-notification-bulk">
                            <form action="{{ route('notifications.markAllRead') }}" method="POST">@csrf<button type="submit" class="portal-text-button">Mark all as read</button></form>
                            <form action="{{ route('notifications.markAllUnread') }}" method="POST">@csrf<button type="submit" class="portal-text-button">Mark all as unread</button></form>
                        </div>
                        </div><div class="portal-notification-list">
                            @forelse($portalNotifications as $notification)
                                <div class="portal-notification-item">
                                    <a class="portal-notification {{ is_null($notification->read_at) ? 'is-unread' : '' }}" href="{{ route('notifications.read', $notification->id) }}"><span>{{ $notification->data['message'] ?? 'Request updated' }}</span><small>{{ $notification->created_at->diffForHumans() }}</small></a>
                                    <form class="portal-notification-state" action="{{ route(is_null($notification->read_at) ? 'notifications.markRead' : 'notifications.markUnread', $notification->id) }}" method="POST">@csrf<button type="submit" class="portal-text-button">{{ is_null($notification->read_at) ? 'Mark as read' : 'Mark as unread' }}</button></form>
                                </div>
                            @empty<p class="portal-empty-small">{{ $notificationFilter === 'unread' ? 'No unread notifications.' : 'No notifications yet.' }}</p>@endforelse
                        </div>
                        @if($portalNotifications->count() === 8)<p class="portal-empty-small">Showing the latest 8 {{ $notificationFilter === 'unread' ? 'unread ' : '' }}notifications.</p>@endif
