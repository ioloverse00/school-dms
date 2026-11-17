@php
    $feedback = [];
    foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info', 'status' => 'success'] as $key => $tone) {
        $message = session($key);
        if (is_string($message) && $message !== '') {
            $message = match ($message) {
                'profile-updated' => 'Profile updated successfully.',
                'password-updated' => 'Password updated successfully.',
                'verification-link-sent' => 'Verification email sent.',
                default => $message,
            };
            $feedback[] = ['tone' => $tone, 'message' => $message];
        }
    }
    if (collect($errors->getBags())->contains(fn($bag) => $bag->any())) $feedback[] = ['tone' => 'danger', 'message' => request()->routeIs('login') ? $errors->first() : 'The action could not be completed. Please review the errors on this page.'];
@endphp
<div id="portal-feedback-toasts" class="portal-toast-container" aria-label="Action feedback">
@foreach($feedback as $notice)
<div class="toast show portal-feedback-toast border-{{ $notice['tone'] }}" role="{{ $notice['tone'] === 'danger' ? 'alert' : 'status' }}" aria-atomic="true" data-tone="{{ $notice['tone'] }}">
    <div class="toast-header"><strong class="me-auto text-{{ $notice['tone'] }}">{{ ['success' => 'Success', 'danger' => 'Action unsuccessful', 'warning' => 'Attention', 'info' => 'Information'][$notice['tone']] }}</strong><button type="button" class="btn-close" aria-label="Dismiss notification" data-toast-dismiss></button></div>
    <div class="toast-body">{{ $notice['message'] }}</div>
</div>
@endforeach
</div>
