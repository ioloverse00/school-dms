<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="data()" :class="{ 'theme-dark': dark }" :data-bs-theme="dark ? 'dark' : 'light'">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Account access | Registrar Portal</title>
@include('partials.portal-styles')
<script src="{{ asset('assets/js/init-alpine.js') }}"></script><script src="{{ asset('assets/vendor/alpine/alpine.min.js') }}" defer></script>
</head><body class="portal-auth">
<div class="login-wrapper portal-auth-shell"><div class="login-bg-shape login-bg-shape-1" aria-hidden="true"></div><div class="login-bg-shape login-bg-shape-2" aria-hidden="true"></div>
<main class="portal-auth-main"><div class="login-card portal-auth-panel">
<a class="spark-login-brand" href="{{ route('login') }}"><i class="bi bi-asterisk" aria-hidden="true"></i><span>Registrar Portal</span></a>
@php
$authHeading = match (true) {
request()->routeIs('login') => 'Welcome back', request()->routeIs('register') => 'Activate your account',
request()->routeIs('password.request') => 'Reset your password', request()->routeIs('password.reset') => 'Choose a new password',
request()->routeIs('password.confirm') => 'Confirm your password', request()->routeIs('verification.notice') => 'Verify your email', default => 'Account access',
};
@endphp
<p class="portal-eyebrow">School document services</p><h1 class="h2">{{ $authHeading }}</h1><p class="portal-subtitle">Your school document services, in one place.</p>
{{ $slot }}
<p class="portal-auth-help">Need access? Contact your school for an account invitation.</p>
@if(!request()->routeIs('login', 'password.request'))<a class="portal-inline-link" href="{{ route('login') }}">Back to sign in &rarr;</a>@endif
@include('partials.spark-credit')
</div></main></div>@include('partials.feedback-toasts')<script src="{{ asset('assets/js/spark-portal.js') }}" defer></script></body></html>
