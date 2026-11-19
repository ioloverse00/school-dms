@extends('layouts.dashboard')
@section('title', 'Notifications')
@section('content')
<div class="portal-page-header">
    <div><h1 class="portal-heading">Notifications</h1><p class="portal-subtitle">Request updates and activity in one place.</p></div>
</div>
<section class="card portal-card portal-notifications-page" aria-label="Your notifications">
    @include('partials.notification-toolbar', ['fullPage' => true])
    <div class="portal-notifications-page-list">
        @forelse($notifications as $notification)
            @include('partials.notification-item', ['notification' => $notification])
        @empty
            <p class="portal-empty-small">{{ $notificationFilter === 'unread' ? 'No unread notifications.' : 'No notifications yet.' }}</p>
        @endforelse
    </div>
    @if($notifications->hasPages())
        <div class="portal-card-footer">{{ $notifications->links() }}</div>
    @endif
</section>
@endsection
