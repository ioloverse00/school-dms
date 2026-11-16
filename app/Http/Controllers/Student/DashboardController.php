<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $totalRequests     = DocumentRequest::where('user_id', $user->id)->count();
        $pendingRequests   = DocumentRequest::where('user_id', $user->id)
                                ->whereIn('status', \App\Support\RequestStatus::PENDING)->count();
        $processingRequests = DocumentRequest::where('user_id', $user->id)
                                ->where('status', 'processing')->count();
        $readyRequests     = DocumentRequest::where('user_id', $user->id)
                                ->whereIn('status', ['ready_for_release', 'completed'])->count();

        $recentRequests = DocumentRequest::where('user_id', $user->id)
                            ->with('documentType')
                            ->latest()
                            ->take(5)
                            ->get();

        return view('student.dashboard', compact(
            'totalRequests', 'pendingRequests', 'processingRequests',
            'readyRequests', 'recentRequests'
        ));
    }
}
