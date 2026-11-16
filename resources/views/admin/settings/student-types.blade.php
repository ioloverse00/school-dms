@extends('layouts.dashboard')
@section('content')
<div class="w-full">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">Student Categories</h2>
    @if(session('success'))<div class="portal-auth-status" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="portal-auth-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="card portal-card mb-4"><div class="card-header portal-card-header"><h3>Add category</h3></div>
        <form action="{{ route('admin.settings.student-types.add') }}" method="POST" class="calendar-form">@csrf
            <input type="hidden" name="mode" value="create">
            <div class="row g-3"><div class="col-md-5"><label for="new-category-label">Category name</label><input id="new-category-label" name="type_label" class="spark-field form-control-custom w-full" value="{{ old('mode') === 'create' ? old('type_label') : '' }}" maxlength="100" required></div>
            <div class="col-md-4"><label for="new-category-code">Unique code</label><input id="new-category-code" name="type_value" class="spark-field form-control-custom w-full" value="{{ old('mode') === 'create' ? old('type_value') : '' }}" pattern="[a-z][a-z0-9_]*" maxlength="50" placeholder="e.g. transferee" required><small>Lowercase letters, numbers and underscores.</small></div>
            <div class="col-md-3"><label for="new-category-weight">Priority weight</label><input id="new-category-weight" name="weight" class="spark-field form-control-custom w-full" type="number" min="1" max="20" value="{{ old('weight', 2) }}" required></div></div>
            <div><button type="submit" class="btn btn-primary">Add category</button></div>
        </form>
    </section>
    <div class="card portal-card mb-6">
        <div class="card-header portal-card-header"><div><h3>Student categories</h3><p class="portal-card-subtitle">Edit display labels here. Adjust priority weights in Weights &amp; urgency. Custom categories follow standard student processing rules.</p></div></div>
        <div class="card-body portal-card-body">
            <form action="{{ route('admin.settings.student-types.add') }}" method="POST">
                @csrf
                <div class="grid gap-4 md:grid-cols-2 mb-4">
                    <div><label for="category-code" class="block mb-1">Category</label><select id="category-code" name="type_value" class="form-select-custom spark-field block w-full" required>@foreach($studentTypes as $type)<option value="{{ $type['value'] }}" @selected(old('type_value') === $type['value'])>{{ $type['label'] }} ({{ $type['value'] }})</option>@endforeach</select></div>
                    <div><label for="category-label" class="block mb-1">New display label</label><input id="category-label" type="text" name="type_label" value="{{ old('type_label') }}" maxlength="100" class="form-control-custom spark-field block w-full" required></div>
                </div>
                <button type="submit" class="btn btn-primary">Save display label</button>
            </form>
        </div>
        <ul class="divide-y">@foreach($studentTypes as $type)<li class="flex items-center justify-between px-6 py-4"><div><strong>{{ $type['label'] }}</strong><div class="portal-table-sub">{{ $type['value'] }}</div></div><span class="portal-status">{{ array_key_exists($type['value'], \App\Support\StudentCategory::LABELS) ? 'Required category' : 'Custom category' }}</span></li>@endforeach</ul>
    </div>
    @if(count($unsupportedTypes))
        <section class="card portal-card"><div class="card-header portal-card-header"><div><h3>Legacy unsupported entries</h3><p class="portal-card-subtitle">Retained for review. These entries cannot be selected for new students. Removal is blocked if existing records reference them.</p></div></div><ul class="divide-y">
        @foreach($unsupportedTypes as $type)<li class="flex items-center justify-between px-6 py-4"><div><strong>{{ $type['label'] }}</strong><div class="portal-table-sub">{{ $type['value'] }}</div></div><form action="{{ route('admin.settings.student-types.delete') }}" method="POST">@csrf @method('DELETE')<input type="hidden" name="type_value" value="{{ $type['value'] }}"><button type="submit" class="btn btn-outline-secondary">Remove unused entry</button></form></li>@endforeach
        </ul></section>
    @endif
</div>
@endsection
