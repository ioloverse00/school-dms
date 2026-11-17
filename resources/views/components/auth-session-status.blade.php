@props(['status'])

@if ($status)
    <div x-data="{ feedbackVisible: true }" x-show="feedbackVisible" x-init="setTimeout(() => feedbackVisible = false, 3000)" {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
        {{ $status }}
    </div>
@endif
