@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
        Add New Registrar
    </h2>

    <div class="card spark-form-card bg-white rounded-lg shadow-xs dark:bg-gray-800 p-6 max-w-lg">

        @if($errors->any())
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert" x-data="{ errorVisible: true }" x-show="errorVisible" style="position:relative;padding-right:48px"><button type="button" aria-label="Dismiss error message" @click="errorVisible = false" style="position:absolute;top:8px;right:8px;width:32px;height:32px;border:0;background:transparent;color:inherit;font-size:24px;line-height:1;cursor:pointer">&times;</button>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('admin.registrars.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}"
                    placeholder="e.g. Maria Santos"
                    class="form-control-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                    Email <span class="text-red-500">*</span>
                </label>
                <input type="email" name="email" value="{{ old('email') }}"
                    placeholder="e.g. registrar@school.edu"
                    class="form-control-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                    Password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password"
                    placeholder="Minimum 8 characters"
                    class="form-control-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
            </div>

            <div class="flex justify-end gap-4">
                <a href="{{ route('admin.registrars.index') }}"
                    class="px-4 py-2 text-sm font-medium text-gray-700 border rounded-lg hover:bg-gray-100">
                    Cancel
                </a>
                <button type="submit"
                    class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">
                    Create Registrar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
