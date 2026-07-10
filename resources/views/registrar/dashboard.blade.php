@extends('layouts.dashboard')

@section('content')
<div class="w-full">
    <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
        Registrar Dashboard
    </h2>

    @include('partials.forecasting')

    <!-- Priority Queue -->
    <h3 class="my-6 text-lg font-semibold text-gray-700 dark:text-gray-200">
        Priority Queue — Top Requests
    </h3>

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
                        <th class="px-4 py-3">Score</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                    @forelse($recentRequests as $index => $req)
                    <tr class="text-gray-700 dark:text-gray-400">
                        {{-- Rank --}}
                        <td class="px-4 py-3 text-sm font-bold text-purple-600">#{{ $index + 1 }}</td>

                        {{-- Student with avatar --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center text-sm">
                                <div class="relative hidden w-8 h-8 mr-3 rounded-full md:block">
                                    <div style="width:100%;height:100%;border-radius:50%;background:#7e3af2;display:flex;align-items:center;justify-content:center;">
                                        <span style="color:white;font-size:0.65rem;font-weight:700;line-height:1;">{{ strtoupper(substr($req->user->name,0,2)) }}</span>
                                    </div>
                                    <div class="absolute inset-0 rounded-full shadow-inner" aria-hidden="true"></div>
                                </div>
                                <div>
                                    <p class="font-semibold">{{ $req->user->name }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $req->user->student_id ?? $req->request_id }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Document with icon --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center text-sm">
                                <div class="relative hidden w-8 h-8 mr-3 rounded-full md:block">
                                    <div style="width:100%;height:100%;border-radius:50%;background:#e3a008;display:flex;align-items:center;justify-content:center;">
                                        <svg style="width:1rem;height:1rem;color:white;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="absolute inset-0 rounded-full shadow-inner" aria-hidden="true"></div>
                                </div>
                                <div>
                                    <p class="font-semibold">{{ $req->documentType->name }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $req->copies }} {{ Str::plural('copy', $req->copies) }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Urgency --}}
                        <td class="px-4 py-3 text-xs">
                            @php
                                $urgencyColors = ['standard'=>'text-gray-700 bg-gray-100','rush'=>'text-orange-700 bg-orange-100','super_rush'=>'text-red-700 bg-red-100'];
                                $uc = $urgencyColors[$req->urgency] ?? 'text-gray-700 bg-gray-100';
                            @endphp
                            <span class="px-2 py-1 font-semibold leading-tight rounded-full {{ $uc }}">
                                {{ ucwords(str_replace('_', ' ', $req->urgency)) }}
                            </span>
                        </td>

                        {{-- Priority score --}}
                        <td class="px-4 py-3 text-xs">
                            <span class="px-2 py-1 font-semibold leading-tight text-purple-700 bg-purple-100 rounded-full">
                                {{ $req->priority_score }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3 text-xs">
                            @php
                                $statusColors = [
                                    'pending_requirements' => 'text-yellow-700 bg-yellow-100',
                                    'pending_verification' => 'text-blue-700 bg-blue-100',
                                    'processing'           => 'text-orange-700 bg-orange-100',
                                    'ready_for_release'    => 'text-green-700 bg-green-100',
                                    'completed'            => 'text-gray-700 bg-gray-100',
                                    'rejected'             => 'text-red-700 bg-red-100',
                                ];
                                $color = $statusColors[$req->status] ?? 'text-gray-700 bg-gray-100';
                            @endphp
                            <span class="px-2 py-1 font-semibold leading-tight rounded-full {{ $color }}">
                                {{ ucwords(str_replace('_', ' ', $req->status)) }}
                            </span>
                        </td>

                        {{-- Action --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-4 text-sm">
                                <a href="{{ route('registrar.requests.show', $req->id) }}"
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
                        <td colspan="7" class="px-4 py-3 text-sm text-center">No requests yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-t dark:border-gray-700">
            <a href="{{ route('registrar.requests.index') }}" class="text-sm text-purple-600 hover:underline">
                View all requests →
            </a>
        </div>
    </div>
</div>
@endsection
