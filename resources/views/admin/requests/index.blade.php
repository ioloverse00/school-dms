@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <div class="flex flex-col gap-3 my-6 sm:flex-row sm:items-center" style="width: 100%;">
        <h2 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
            {{ $showArchived ? 'Archived Document Requests' : 'Active Document Requests' }}
        </h2>
        <div class="flex flex-col gap-2 sm:flex-row" style="margin-left: auto;">
            <a href="{{ route('admin.requests.export', array_filter(['archived' => $showArchived ? 1 : null, 'status' => $status])) }}"
                class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-purple-700 bg-purple-100 border border-transparent rounded-lg hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-100 dark:hover:bg-purple-800">
                Export CSV
            </a>
            <form method="GET" action="{{ route('admin.requests.index') }}" class="flex flex-col gap-2 sm:flex-row">
                @if($showArchived)
                <input type="hidden" name="archived" value="1">
                @endif
                <select name="status" onchange="this.form.submit()"
                    class="px-4 py-2 text-sm border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple">
                    <option value="">All</option>
                    @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    <div class="w-full overflow-hidden rounded-lg shadow-xs mb-8">
        <div class="w-full overflow-x-auto">
            <table class="w-full whitespace-no-wrap">
                <thead>
                    <tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                        <th class="px-4 py-3">Rank</th>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Document</th>
                        <th class="px-4 py-3">Urgency</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Score</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($requests as $req)
                    <tr class="text-gray-700 dark:text-gray-400">
                        <td class="px-4 py-3 text-sm font-bold text-purple-600">#{{ $requests->firstItem() + $loop->index }}</td>

                        <td class="px-4 py-3">
                            <div class="flex items-center text-sm">
                                <div class="relative hidden w-8 h-8 mr-3 rounded-full md:block">
                                    <div style="width:100%;height:100%;border-radius:50%;background:#7e3af2;display:flex;align-items:center;justify-content:center;">
                                        <span style="color:white;font-size:0.65rem;font-weight:700;line-height:1;">{{ strtoupper(substr($req->user->name,0,2)) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-semibold">{{ $req->user->name }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $req->user->student_id ?? $req->request_id }}</p>
                                </div>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center text-sm">
                                <div class="relative hidden w-8 h-8 mr-3 rounded-full md:block">
                                    <div style="width:100%;height:100%;border-radius:50%;background:#e3a008;display:flex;align-items:center;justify-content:center;">
                                        <svg style="width:1rem;height:1rem;color:white;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-semibold">{{ $req->documentType->name }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $req->copies }} {{ Str::plural('copy', $req->copies) }}</p>
                                </div>
                            </div>
                        </td>

                        <td class="px-4 py-3 text-xs">
                            @php $uc = ['regular'=>'text-gray-700 bg-gray-100','urgent'=>'text-orange-700 bg-orange-100','very_urgent'=>'text-red-700 bg-red-100'][$req->urgency] ?? 'text-gray-700 bg-gray-100'; @endphp
                            <span class="px-2 py-1 font-semibold leading-tight rounded-full {{ $uc }}">
                                {{ ucwords(str_replace('_', ' ', $req->urgency)) }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-sm">
                            {{ ['regular'=>'Regular','graduating'=>'Graduating','alumni'=>'Alumni'][$req->student_category] ?? ucfirst($req->student_category) }}
                        </td>

                        <td class="px-4 py-3 text-xs">
                            <span class="px-2 py-1 font-semibold leading-tight text-purple-700 bg-purple-100 rounded-full">
                                {{ $req->priority_score }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-xs">
                            @php
                                $color = ['pending_requirements'=>'text-yellow-700 bg-yellow-100','pending_verification'=>'text-blue-700 bg-blue-100','processing'=>'text-orange-700 bg-orange-100','ready_for_release'=>'text-green-700 bg-green-100','completed'=>'text-gray-700 bg-gray-100','rejected'=>'text-red-700 bg-red-100'][$req->status] ?? 'text-gray-700 bg-gray-100';
                            @endphp
                            <span class="px-2 py-1 font-semibold leading-tight rounded-full {{ $color }}">
                                {{ ucwords(str_replace('_', ' ', $req->status)) }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-sm">{{ $req->created_at->format('M d, Y') }}</td>

                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 text-sm">
                                <a href="{{ route('admin.requests.show', $req->id) }}"
                                    class="p-2 text-purple-600 rounded-lg hover:bg-purple-50 dark:text-gray-400 focus:outline-none" aria-label="View">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="text-gray-700 dark:text-gray-400">
                        <td colspan="9" class="px-4 py-3 text-sm text-center">
                            {{ $showArchived ? 'No archived requests.' : 'No requests yet.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    </div>
</div>
@endsection
