@extends('layouts.dashboard')

@section('content')
<div class="w-full document-weights-page" x-data="documentTypeManager()">
    <div class="my-6" style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <h2 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">Document types</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage requestable documents. Configure priority weights in Priority settings.</p>
        </div>
        <div style="display:flex;align-items:end;gap:0.75rem;margin-left:auto;flex-wrap:wrap;justify-content:flex-end;">
            <div class="document-weights-picker">
                <label for="document-to-edit" class="visually-hidden">Document to edit</label>
                <select id="document-to-edit" x-model="selectedId"
                    class="form-select-custom spark-field w-full text-sm form-select sm:w-72 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                    <option value="">Select document</option>
                    @foreach($documentTypes as $docType)
                    <option value="{{ $docType->id }}">{{ $docType->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" @click="openSelectedEdit()" :disabled="!selectedId"
                class="px-4 py-2 text-sm font-medium text-purple-600 border border-purple-200 rounded-lg hover:bg-purple-50 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                Edit
            </button>
            <button type="button" @click="openCreate()"
                class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none">
                Add Document
            </button>
        </div>
    </div>

    @if(session('success'))
    <div x-data="{ feedbackVisible: true }" x-show="feedbackVisible" x-init="setTimeout(() => feedbackVisible = false, 3000)" class="p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-lg">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="p-4 mb-6 text-sm text-red-700 bg-red-100 rounded-lg" role="alert" x-data="{ errorVisible: true }" x-show="errorVisible" style="position:relative;padding-right:48px"><button type="button" aria-label="Dismiss error message" @click="errorVisible = false" style="position:absolute;top:8px;right:8px;width:32px;height:32px;border:0;background:transparent;color:inherit;font-size:24px;line-height:1;cursor:pointer">&times;</button>
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="card spark-form-card w-full overflow-hidden rounded-lg shadow-xs bg-white dark:bg-gray-800">
        <div class="px-6 py-4 border-b dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">Documents</h3>
            <p class="text-xs text-gray-500 mt-1">Weight added to priority score per document type.</p>
        </div>
        <div>
            <div class="document-weights-grid">
                @foreach($documentTypes as $docType)
                <div class="document-weight-row">
                    <div class="document-weight-description">
                        <div class="document-weight-title">
                            <label for="document-weight-{{ $docType->id }}" class="text-sm text-gray-700 dark:text-gray-300">{{ $docType->name }}</label>
                            @if($docType->alumni_only)
                            <span class="px-2 py-0.5 text-[11px] font-medium text-purple-700 bg-purple-100 rounded-full">Alumni</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 capitalize">{{ $docType->category }} &middot; {{ $docType->document_requests_count }} request{{ $docType->document_requests_count === 1 ? '' : 's' }}</p>
                    </div>
                    <span class="badge bg-secondary">Weight {{ $docType->weight }}</span>
                </div>
                @endforeach
            </div>
            <div class="px-6 py-4 border-t dark:border-gray-700">
                <a class="btn btn-outline-primary" href="{{ route('admin.settings.document-management.student-weights') }}">Edit priority weights</a>
            </div>
        </form>
    </div>

    <div x-show="modal === 'create'" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="spark-dialog-backdrop fixed inset-0 z-30 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center">
        <div x-show="modal === 'create'" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 transform translate-y-1/2"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 transform translate-y-1/2"
            @click.away="close()"
            class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4 sm:max-w-xl">
            <div class="flex items-center justify-between mb-4">
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-300">Add Document</p>
                <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 focus:outline-none">&times;</button>
            </div>
            <form action="{{ route('admin.settings.document-types.add') }}" method="POST">
                @csrf
                <input type="hidden" name="_modal" value="create">
                @include('admin.settings.document-management.partials.document-type-fields', ['prefix' => 'create', 'documentType' => null])
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="close()" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">Add Document</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="modal === 'edit'" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="spark-dialog-backdrop fixed inset-0 z-30 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center">
        <div x-show="modal === 'edit'" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 transform translate-y-1/2"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 transform translate-y-1/2"
            @click.away="close()"
            class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4 sm:max-w-xl">
            <div class="flex items-center justify-between mb-4">
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-300">Edit Document</p>
                <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 focus:outline-none">&times;</button>
            </div>
            <form :action="edit.update_url" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="edit">
                <div class="grid gap-4">
                    <div>
                        <label for="edit-name" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Document name</label>
                        <input id="edit-name" type="text" name="name" x-model="edit.name" required maxlength="100"
                            class="form-control-custom spark-field w-full text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="edit-category" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                            <select id="edit-category" name="category" x-model="edit.category" required
                                class="form-select-custom spark-field w-full text-sm form-select dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                                <option value="academic">Academic</option>
                                <option value="certification">Certification</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit-weight" class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Weight</label>
                            <input id="edit-weight" type="number" name="weight" x-model="edit.weight" min="1" max="20" required
                                class="form-control-custom spark-field w-full text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple rounded-lg">
                        </div>
                    </div>
                    <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="alumni_only" value="1" x-model="edit.alumni_only"
                            class="form-checkbox text-purple-600 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                        <span class="ml-2">Alumni only</span>
                    </label>
                </div>
                <div class="flex flex-col gap-3 mt-6 sm:flex-row sm:items-center sm:justify-between">
                    <button type="submit" x-show="edit.requests === 0" :form="'delete-document-type-' + edit.id"
                        class="px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                        Delete
                    </button>
                    <span x-show="edit.requests > 0" class="text-xs text-gray-400">Cannot delete documents with existing requests.</span>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="close()" class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @foreach($documentTypes as $docType)
    @if($docType->document_requests_count === 0)
    <form id="delete-document-type-{{ $docType->id }}" action="{{ route('admin.settings.document-types.delete', $docType) }}" method="POST" class="hidden"
        data-confirm="Delete {{ $docType->name }}?">
        @csrf
        @method('DELETE')
    </form>
    @endif
    @endforeach
</div>

@php
    $documentTypeOptions = $documentTypes->map(function ($docType) {
        return [
            'id' => $docType->id,
            'name' => $docType->name,
            'category' => $docType->category,
            'weight' => $docType->weight,
            'alumni_only' => (bool) $docType->alumni_only,
            'requests' => $docType->document_requests_count,
            'update_url' => route('admin.settings.document-types.update', $docType),
            'delete_url' => route('admin.settings.document-types.delete', $docType),
        ];
    })->values();
@endphp

<script>
    function documentTypeManager() {
        return {
            modal: @json($errors->any() ? old('_modal') : null),
            selectedId: '',
            documents: @json($documentTypeOptions),
            edit: {
                id: null,
                name: '',
                category: 'academic',
                weight: 1,
                alumni_only: false,
                requests: 0,
                update_url: '',
                delete_url: '',
            },
            openCreate() {
                this.modal = 'create';
            },
            openSelectedEdit() {
                const documentType = this.documents.find((item) => String(item.id) === String(this.selectedId));
                if (documentType) {
                    this.openEdit(documentType);
                }
            },
            openEdit(data) {
                this.edit = data;
                this.modal = 'edit';
            },
            close() {
                this.modal = null;
            },
        };
    }
</script>
@endsection
