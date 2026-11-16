{{-- Account components share the role-aware Spark shell and single Alpine runtime. --}}
@extends('layouts.dashboard')
@section('title', 'Profile & account')
@section('content')
@isset($header)<div class="page-title-row portal-page-header">{{ $header }}</div>@endisset
{{ $slot }}
@endsection