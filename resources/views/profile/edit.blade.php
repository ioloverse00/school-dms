@extends('layouts.dashboard')
@section('title', 'Profile & account')
@section('content')
    <div class="portal-page-header">
        <div>
            <h1 class="portal-heading">{{ __('Profile') }}</h1>
            <p class="portal-subtitle">Manage your account details and keep your password up to date.</p>
        </div>
    </div>

    <div class="portal-profile-page">
        <div class="spark-profile-grid">
            <div class="card spark-form-card profile-account-card">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="card spark-form-card profile-password-card">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="card spark-form-card profile-delete-card">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
@endsection
