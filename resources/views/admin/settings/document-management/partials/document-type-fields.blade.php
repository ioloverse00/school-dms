<div class="grid gap-4">
    <div>
        <label for="{{ $prefix }}-name" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Document name</label>
        <input id="{{ $prefix }}-name" type="text" name="name" value="{{ old('name') }}" required maxlength="100"
            class="form-control-custom spark-field w-full text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $prefix }}-category" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
            <select id="{{ $prefix }}-category" name="category" required
                class="form-select-custom spark-field w-full text-sm form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                <option value="academic" {{ old('category', 'academic') === 'academic' ? 'selected' : '' }}>Academic</option>
                <option value="certification" {{ old('category') === 'certification' ? 'selected' : '' }}>Certification</option>
            </select>
        </div>
        <div>
            <label for="{{ $prefix }}-weight" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Weight</label>
            <input id="{{ $prefix }}-weight" type="number" name="weight" value="{{ old('weight', 1) }}" min="1" max="20" required
                class="form-control-custom spark-field w-full text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
        </div>
    </div>
    <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-300">
        <input type="checkbox" name="alumni_only" value="1" {{ old('alumni_only') ? 'checked' : '' }}
            class="form-checkbox text-purple-600 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
        <span class="ml-2">Alumni only</span>
    </label>
</div>
