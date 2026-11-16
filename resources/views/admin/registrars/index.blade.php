@extends('layouts.dashboard')

@section('content')
@error('account')
<div class="alert alert-danger" role="alert">{{ $message }}</div>
@enderror
<div class="w-full" x-data="registrarModal()">
    <div class="flex justify-between items-center my-6">
        <h2 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
            Registrar Management
        </h2>
        <button @click="modal = 'create'"
            class="btn btn-primary px-4 py-2 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
            + Add Registrar
        </button>
    </div>

    @if(session('success'))
    <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    <div class="card portal-card mb-8">
        <div class="portal-table-wrap">
            <table class="table table-custom portal-table">
                <thead>
                    <tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                        <th class="px-4 py-3">Registrar</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($registrars as $registrar)
                    <tr class="text-gray-700 dark:text-gray-400">
                        <td class="px-4 py-3">
                            <div class="flex items-center text-sm">
                                <div class="relative hidden w-8 h-8 mr-3 rounded-full md:block">
                                    <div style="width:100%;height:100%;border-radius:50%;background:#0694a2;display:flex;align-items:center;justify-content:center;">
                                        <span style="color:white;font-size:0.65rem;font-weight:700;line-height:1;">{{ strtoupper(substr($registrar->name,0,2)) }}</span>
                                    </div>
                                    <div class="absolute inset-0 rounded-full shadow-inner" aria-hidden="true"></div>
                                </div>
                                <div>
                                    <p class="font-semibold">{{ $registrar->name }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $registrar->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ $registrar->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-4 text-sm">
                                <button
                                    @click="openEdit({{ json_encode(['id' => $registrar->id, 'name' => $registrar->name, 'email' => $registrar->email]) }})"
                                    class="flex items-center justify-between px-2 py-2 text-sm font-medium leading-5 text-purple-600 rounded-lg dark:text-gray-400 focus:outline-none focus:shadow-outline-gray" aria-label="Edit">
                                    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                    </svg>
                                </button>
                                <button
                                    @click="openDelete({{ $registrar->id }}, '{{ addslashes($registrar->name) }}')"
                                    class="flex items-center justify-between px-2 py-2 text-sm font-medium leading-5 text-purple-600 rounded-lg dark:text-gray-400 focus:outline-none focus:shadow-outline-gray" aria-label="Delete">
                                    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="text-gray-700 dark:text-gray-400">
                        <td colspan="4" class="px-4 py-3 text-sm text-center">No registrars yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $registrars->links() }}
    </div>

    {{-- ===================== ADD REGISTRAR MODAL ===================== --}}
    <div
        x-cloak
        x-show="modal === 'create'"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="spark-dialog-backdrop fixed inset-0 z-30 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center">
        <div
            x-show="modal === 'create'"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 transform translate-y-1/2"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 transform translate-y-1/2"
            @click.away="closeModal"
            @keydown.escape.window="closeModal"
            class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4" style="max-width:36rem;">

            <header class="flex justify-between items-center mb-4">
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-300">Add New Registrar</p>
                <button @click="closeModal"
                    class="inline-flex items-center justify-center w-6 h-6 text-gray-400 transition-colors duration-150 rounded hover:text-gray-700 dark:hover:text-gray-200" aria-label="close">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" fill-rule="evenodd"/>
                    </svg>
                </button>
            </header>

            @if($errors->any() && old('_modal') === 'create')
            <div class="mb-4 p-3 text-sm text-red-700 bg-red-100 rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('admin.registrars.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_modal" value="create">

                <label class="block mb-6">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-400">Full Name <span class="text-red-500">*</span></span>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control-custom spark-field block w-full mt-2 text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                </label>
                <label class="block mb-6">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-400">Email <span class="text-red-500">*</span></span>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control-custom spark-field block w-full mt-2 text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                </label>
                <label class="block mb-6">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-400">Password <span class="text-red-500">*</span></span>
                    <input type="password" name="password" class="form-control-custom spark-field block w-full mt-2 text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                </label>

                <footer class="flex flex-col items-center justify-end px-6 py-3 -mx-6 -mb-4 space-y-4 sm:space-y-0 sm:space-x-6 sm:flex-row bg-gray-50 dark:bg-gray-800">
                    <button type="button" @click="closeModal"
                        class="w-full px-5 py-3 text-sm font-medium leading-5 text-gray-700 transition-colors duration-150 border border-gray-300 rounded-lg dark:text-gray-400 sm:px-4 sm:py-2 sm:w-auto hover:border-gray-500 focus:outline-none focus:shadow-outline-gray">
                        Cancel
                    </button>
                    <button type="submit"
                        class="btn btn-primary w-full px-5 py-3 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg sm:w-auto sm:px-4 sm:py-2 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                        Create Registrar
                    </button>
                </footer>
            </form>
        </div>
    </div>

    {{-- ===================== EDIT REGISTRAR MODAL ===================== --}}
    <div
        x-cloak
        x-show="modal === 'edit'"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="spark-dialog-backdrop fixed inset-0 z-30 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center">
        <div
            x-show="modal === 'edit'"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 transform translate-y-1/2"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 transform translate-y-1/2"
            @click.away="closeModal"
            @keydown.escape.window="closeModal"
            class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4" style="max-width:36rem;">

            <header class="flex justify-between items-center mb-4">
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-300">Edit Registrar</p>
                <button @click="closeModal"
                    class="inline-flex items-center justify-center w-6 h-6 text-gray-400 transition-colors duration-150 rounded hover:text-gray-700 dark:hover:text-gray-200" aria-label="close">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" fill-rule="evenodd"/>
                    </svg>
                </button>
            </header>

            @if($errors->any() && old('_modal') === 'edit')
            <div class="mb-4 p-3 text-sm text-red-700 bg-red-100 rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <form :action="'{{ url('admin/registrars') }}/' + editData.id" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="edit">
                <input type="hidden" name="_edit_id" :value="editData.id">

                <label class="block mb-6">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-400">Full Name <span class="text-red-500">*</span></span>
                    <input type="text" name="name" x-model="editData.name" class="form-control-custom spark-field block w-full mt-2 text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                </label>
                <label class="block mb-6">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-400">Email <span class="text-red-500">*</span></span>
                    <input type="email" name="email" x-model="editData.email" class="form-control-custom spark-field block w-full mt-2 text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                </label>
                <label class="block mb-6">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-400">New Password (leave blank to keep)</span>
                    <input type="password" name="password" class="form-control-custom spark-field block w-full mt-2 text-sm form-input dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                </label>

                <footer class="flex flex-col items-center justify-end px-6 py-3 -mx-6 -mb-4 space-y-4 sm:space-y-0 sm:space-x-6 sm:flex-row bg-gray-50 dark:bg-gray-800">
                    <button type="button" @click="closeModal"
                        class="w-full px-5 py-3 text-sm font-medium leading-5 text-gray-700 transition-colors duration-150 border border-gray-300 rounded-lg dark:text-gray-400 sm:px-4 sm:py-2 sm:w-auto hover:border-gray-500 focus:outline-none focus:shadow-outline-gray">
                        Cancel
                    </button>
                    <button type="submit"
                        class="btn btn-primary w-full px-5 py-3 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg sm:w-auto sm:px-4 sm:py-2 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                        Update Registrar
                    </button>
                </footer>
            </form>
        </div>
    </div>

    {{-- ===================== DELETE CONFIRM MODAL ===================== --}}
    <div
        x-cloak
        x-show="modal === 'delete'"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="spark-dialog-backdrop fixed inset-0 z-30 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center">
        <div
            x-show="modal === 'delete'"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 transform translate-y-1/2"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 transform translate-y-1/2"
            @click.away="closeModal"
            @keydown.escape.window="closeModal"
            class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4" style="max-width:28rem;">

            <header class="flex justify-end mb-2">
                <button @click="closeModal"
                    class="inline-flex items-center justify-center w-6 h-6 text-gray-400 transition-colors duration-150 rounded hover:text-gray-700 dark:hover:text-gray-200" aria-label="close">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" fill-rule="evenodd"/>
                    </svg>
                </button>
            </header>
            <div class="mt-2 mb-6 text-center">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full" style="background:#fee2e2;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:#dc2626;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <p class="mb-2 text-lg font-semibold text-gray-700 dark:text-gray-300">Delete Registrar</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete <strong x-text="deleteName" class="text-gray-700 dark:text-gray-200"></strong>?
                    This action cannot be undone.
                </p>
            </div>
            <form :action="'{{ url('admin/registrars') }}/' + deleteId" method="POST">
                @csrf
                @method('DELETE')
                <footer class="flex flex-col items-center justify-end px-6 py-3 -mx-6 -mb-4 space-y-4 sm:space-y-0 sm:space-x-6 sm:flex-row bg-gray-50 dark:bg-gray-800">
                    <button type="button" @click="closeModal"
                        class="w-full px-5 py-3 text-sm font-medium leading-5 text-gray-700 transition-colors duration-150 border border-gray-300 rounded-lg dark:text-gray-400 sm:px-4 sm:py-2 sm:w-auto hover:border-gray-500 focus:outline-none focus:shadow-outline-gray">
                        Cancel
                    </button>
                    <button type="submit"
                        class="w-full px-5 py-3 text-sm font-medium leading-5 text-white transition-colors duration-150 border border-transparent rounded-lg sm:w-auto sm:px-4 sm:py-2 focus:outline-none" style="background:#dc2626;">
                        Yes, Delete
                    </button>
                </footer>
            </form>
        </div>
    </div>
</div>

<script>
function registrarModal() {
    return {
        modal: @json($errors->any() ? old('_modal') : null),
        editData: {
            id:    @json(old('_edit_id', '')),
            name:  @json(old('name', '')),
            email: @json(old('email', '')),
        },
        deleteName: '',
        deleteId: null,
        openEdit(data) {
            this.editData = Object.assign({}, data);
            this.modal = 'edit';
        },
        openDelete(id, name) {
            this.deleteId = id;
            this.deleteName = name;
            this.modal = 'delete';
        },
        closeModal() {
            this.modal = null;
        },
    }
}
</script>
@endsection
