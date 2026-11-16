@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
        Edit Student
    </h2>

    <div class="card spark-form-card bg-white rounded-lg shadow-xs dark:bg-gray-800 p-6">

        @if($errors->any())
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route($routePrefix . '.students.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                        Student ID <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="student_id" value="{{ old('student_id', $user->student_id) }}"
                        class="form-control-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                        class="form-control-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                        class="form-control-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                        New Password (leave blank to keep current)
                    </label>
                    <input type="password" name="password"
                        placeholder="Leave blank to keep current"
                        class="form-control-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                        Course / Program <span class="text-red-500">*</span>
                    </label>
                    <select name="course" class="form-select-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
                        @foreach($programs as $program)
                            <option value="{{ $program }}" {{ old('course', $user->course) == $program ? 'selected' : '' }}>{{ $program }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                        Year Graduated (if Alumni)
                    </label>
                    <input type="text" name="year_graduated" value="{{ old('year_graduated', $user->year_graduated) }}"
                        class="form-control-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                    Student Category <span class="text-red-500">*</span>
                </label>
                <select name="student_type"
                    class="form-select-custom spark-field w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:text-gray-300 text-sm">
                    @foreach($studentTypes as $type)
                    <option value="{{ $type['value'] }}" @selected(old('student_type', $user->student_type) === $type['value'])>{{ $type['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end gap-4">
                <a href="{{ route($routePrefix . '.students.index') }}"
                    class="px-4 py-2 text-sm font-medium text-gray-700 border rounded-lg hover:bg-gray-100">
                    Cancel
                </a>
                <button type="submit"
                    class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">
                    Update Student
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
