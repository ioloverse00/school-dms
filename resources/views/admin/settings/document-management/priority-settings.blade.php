@extends('layouts.dashboard')
@section('title', 'Priority settings')
@section('content')
<div class="portal-page-header"><div><h1 class="portal-heading">Priority settings</h1><p class="portal-subtitle">Manage student categories and all values used to prioritize document requests.</p></div></div>
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>Please check your entries.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-4 align-items-start">
    <div class="col-lg-6"><section class="card portal-card"><div class="card-header portal-card-header"><div><h2>Category weights</h2><p class="portal-subtitle">Weight added to the priority score per student category.</p></div></div>
    <form action="{{ route('admin.settings.student-weights.update') }}" method="POST">@csrf
        @foreach($studentWeights as $category => $weight)
        <div class="d-flex align-items-center justify-content-between gap-3 py-3 border-bottom"><label for="weight-{{ $category }}">{{ \App\Support\StudentCategory::labels()[$category] ?? str_replace('_', ' ', $category) }}</label><input id="weight-{{ $category }}" class="spark-field form-control-custom" style="width:90px" type="number" name="weights[{{ $category }}]" value="{{ old('weights.'.$category, $weight) }}" min="1" max="20" required></div>
        @endforeach
        <button class="btn btn-primary mt-3" type="submit">Save category weights</button>
    </form></section></div>
    <div class="col-lg-6"><section class="card portal-card"><div class="card-header portal-card-header"><div><h2>Urgency levels</h2><p class="portal-subtitle">Priority = ({{ $urgencyWeight }} &times; urgency level) + category weight + document weight.</p></div></div>
    <form action="{{ route('admin.settings.urgency-levels.update') }}" method="POST">@csrf
        @foreach($urgencyLevels as $level => $value)
        <div class="d-flex align-items-center justify-content-between gap-3 py-3 border-bottom"><label for="level-{{ $level }}">{{ \App\Support\RequestUrgency::label($level) }}</label><input id="level-{{ $level }}" class="spark-field form-control-custom" style="width:90px" type="number" name="levels[{{ $level }}]" value="{{ old('levels.'.$level, $value) }}" min="1" max="10" required></div>
        @endforeach
        <button class="btn btn-primary mt-3" type="submit">Save urgency levels</button>
    </form></section></div>
</div>
<section class="card portal-card mt-4 mb-4"><div class="card-header portal-card-header"><div><h2>Document weights</h2><p class="portal-subtitle">Weight added for each document type. <a href="{{ route('admin.settings.document-management.document-weights') }}">Manage document types</a></p></div></div>
<form action="{{ route('admin.settings.document-weights.update') }}" method="POST">@csrf
<div class="row g-3">
@foreach($documentTypes as $document)
<div class="col-md-6"><div class="d-flex align-items-center justify-content-between gap-3 py-2"><label for="priority-document-{{ $document->id }}">{{ $document->name }}</label><input id="priority-document-{{ $document->id }}" class="spark-field form-control-custom" style="width:90px;flex-shrink:0" type="number" name="weights[{{ $document->id }}]" value="{{ old('weights.'.$document->id, $document->weight) }}" min="1" max="20" required></div></div>
@endforeach
</div>
@if($documentTypes->isNotEmpty())<button type="submit" class="btn btn-primary mt-3">Save document weights</button>@else<p>No document types yet.</p>@endif
</form></section>
@include('partials.priority-categories')
@endsection
