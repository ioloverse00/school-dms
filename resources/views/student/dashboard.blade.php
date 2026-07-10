@extends('layouts.dashboard')

@section('content')
<div class="w-full py-8">

    {{-- Welcome Banner --}}
    <div class="rounded-lg shadow-xs mb-8 mt-6 overflow-hidden" style="background: linear-gradient(135deg, #7e3af2 0%, #5521b5 100%);">
        <div class="flex items-center justify-between" style="padding: 2rem 2rem;">
            <div>
                <p class="text-sm font-medium" style="color: rgba(255,255,255,0.7); margin-bottom: 0.25rem;">Welcome back,</p>
                <h1 class="text-2xl font-bold text-white" style="margin-bottom: 0.75rem;">{{ auth()->user()->name }}</h1>
                <div class="flex items-center flex-wrap" style="gap: 0.5rem;">
                    @if(auth()->user()->student_id)
                    <span class="text-xs font-medium" style="color: rgba(255,255,255,0.8);">ID: {{ auth()->user()->student_id }}</span>
                    @endif
                    @if(auth()->user()->student_id && auth()->user()->course)
                    <span style="color: rgba(255,255,255,0.4);">|</span>
                    @endif
                    @if(auth()->user()->course)
                    <span class="text-xs font-medium" style="color: rgba(255,255,255,0.8);">{{ auth()->user()->course }}</span>
                    @endif
                    @if(auth()->user()->student_type)
                    <span class="text-xs font-semibold" style="background:rgba(255,255,255,0.2); color:white; padding: 2px 10px; border-radius: 9999px;">
                        {{ ucfirst(auth()->user()->student_type) }}
                    </span>
                    @endif
                </div>
            </div>
            <div style="opacity:0.12; flex-shrink:0; margin-left: 1.5rem;">
                <svg style="width:6rem;height:6rem;color:white;" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                </svg>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="p-4 mb-6 text-sm text-green-700 bg-green-100 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid gap-6 mb-8 md:grid-cols-2 xl:grid-cols-4">

        {{-- Total --}}
        <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="p-3 mr-4 text-purple-500 bg-purple-100 rounded-full dark:text-purple-100 dark:bg-purple-500">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div>
                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Total Requests</p>
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $totalRequests }}</p>
            </div>
        </div>

        {{-- Pending --}}
        <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="p-3 mr-4 rounded-full" style="color:#d97706; background-color:#fef3c7;">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div>
                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Pending</p>
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $pendingRequests }}</p>
            </div>
        </div>

        {{-- Processing --}}
        <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="p-3 mr-4 text-blue-500 bg-blue-100 rounded-full dark:text-blue-100 dark:bg-blue-500">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div>
                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Processing</p>
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $processingRequests }}</p>
            </div>
        </div>

        {{-- Ready / Completed --}}
        <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
            <div class="p-3 mr-4 text-green-500 bg-green-100 rounded-full dark:text-green-100 dark:bg-green-500">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div>
                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Ready / Done</p>
                <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $readyRequests }}</p>
            </div>
        </div>
    </div>

    {{-- Recent Requests --}}
    <div>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Recent Requests</h2>
                <a href="{{ route('student.requests.index') }}" class="text-sm text-purple-600 hover:underline">View all →</a>
            </div>
            <div class="w-full overflow-hidden rounded-lg shadow-xs">
                <div class="w-full overflow-x-auto">
                    <table class="w-full whitespace-no-wrap">
                        <thead>
                            <tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                                <th class="px-4 py-3">Document</th>
                                <th class="px-4 py-3">Urgency</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                            @forelse($recentRequests as $req)
                            <tr class="text-gray-700 dark:text-gray-400">
                                {{-- Document with icon --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center text-sm">
                                        <div class="relative hidden w-8 h-8 mr-3 rounded-full md:block">
                                            <div style="width:100%;height:100%;border-radius:50%;background:#7e3af2;display:flex;align-items:center;justify-content:center;">
                                                <svg style="width:1rem;height:1rem;color:white;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="font-semibold">{{ $req->documentType->name }}</p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ $req->request_id }}</p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Urgency --}}
                                <td class="px-4 py-3 text-xs">
                                    @php
                                        $uc = ['standard'=>'text-gray-700 bg-gray-100','rush'=>'text-orange-700 bg-orange-100','super_rush'=>'text-red-700 bg-red-100'][$req->urgency] ?? 'text-gray-700 bg-gray-100';
                                    @endphp
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full {{ $uc }}">
                                        {{ ucwords(str_replace('_', ' ', $req->urgency)) }}
                                    </span>
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-3 text-xs">
                                    @php
                                        $sc = [
                                            'pending_requirements' => 'text-yellow-700 bg-yellow-100',
                                            'pending_verification' => 'text-blue-700 bg-blue-100',
                                            'processing'           => 'text-orange-700 bg-orange-100',
                                            'ready_for_release'    => 'text-green-700 bg-green-100',
                                            'completed'            => 'text-gray-700 bg-gray-100',
                                            'rejected'             => 'text-red-700 bg-red-100',
                                        ][$req->status] ?? 'text-gray-700 bg-gray-100';
                                    @endphp
                                    <span class="px-2 py-1 font-semibold leading-tight rounded-full {{ $sc }}">
                                        {{ ucwords(str_replace('_', ' ', $req->status)) }}
                                    </span>
                                </td>

                                {{-- Date --}}
                                <td class="px-4 py-3 text-sm">{{ $req->created_at->format('M d, Y') }}</td>

                                {{-- Action --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-4 text-sm">
                                        <a href="{{ route('student.requests.show', $req->id) }}"
                                            class="flex items-center justify-between px-2 py-2 text-sm font-medium leading-5 text-purple-600 rounded-lg dark:text-gray-400 focus:outline-none focus:shadow-outline-gray" aria-label="View">
                                            <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr class="text-gray-700 dark:text-gray-400">
                                <td colspan="5" class="px-4 py-6 text-sm text-center">
                                    No requests yet.
                                    <a href="{{ route('student.requests.create') }}" class="text-purple-600 hover:underline">Submit your first request!</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
