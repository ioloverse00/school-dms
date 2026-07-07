<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Stats cards
        $totalRequests    = DocumentRequest::count();
        $pendingRequests  = DocumentRequest::where('status', 'pending_verification')->count();
        $processingRequests = DocumentRequest::where('status', 'processing')->count();
        $completedRequests  = DocumentRequest::where('status', 'completed')->count();
        $totalStudents    = User::role('student')->count();

        // Priority queue
        $recentRequests = DocumentRequest::with(['user', 'documentType'])
            ->orderBy('priority_score', 'desc')
            ->take(5)
            ->get();

        // --- Forecasting data ---

        // Top 6 most requested document types (all time)
        $documentDemand = DocumentRequest::select('document_type_id', DB::raw('count(*) as total'))
            ->groupBy('document_type_id')
            ->orderBy('total', 'desc')
            ->with('documentType')
            ->take(6)
            ->get();

        // Monthly trend — last 6 months
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i));
        }

        $driver = DB::connection()->getDriverName();
        $yearExpr = $driver === 'sqlite'
            ? "CAST(strftime('%Y', created_at) AS INTEGER)"
            : 'YEAR(created_at)';
        $monthExpr = $driver === 'sqlite'
            ? "CAST(strftime('%m', created_at) AS INTEGER)"
            : 'MONTH(created_at)';

        $rawMonthly = DocumentRequest::select(
                DB::raw($yearExpr . ' as year'),
                DB::raw($monthExpr . ' as month'),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn($r) => $r->year . '-' . $r->month);

        $monthlyLabels = $months->map(fn($m) => $m->format('M Y'))->values()->toArray();
        $monthlyValues = $months->map(fn($m) => optional($rawMonthly->get($m->year . '-' . $m->month))->total ?? 0)->values()->toArray();

        // Linear regression on overall monthly totals → forecast next 3 months
        $forecastLabels = [];
        for ($i = 1; $i <= 3; $i++) {
            $forecastLabels[] = Carbon::now()->addMonths($i)->format('M Y');
        }
        $mv   = array_map('intval', $monthlyValues);
        $fn   = count($mv);
        $fxs  = range(1, $fn);
        $fSumX = array_sum($fxs); $fSumY = array_sum($mv);
        $fSumXY = 0; $fSumX2 = 0;
        foreach ($fxs as $fi => $fx) { $fSumXY += $fx * $mv[$fi]; $fSumX2 += $fx * $fx; }
        $fDenom = ($fn * $fSumX2 - $fSumX * $fSumX);
        $fSlope = $fDenom != 0 ? ($fn * $fSumXY - $fSumX * $fSumY) / $fDenom : 0;
        $fIntercept = ($fSumY - $fSlope * $fSumX) / $fn;
        $overallForecastValues = [];
        for ($i = 1; $i <= 3; $i++) {
            $overallForecastValues[] = max(0, (int) round($fIntercept + $fSlope * ($fn + $i)));
        }

        // Category breakdown (academic vs certification)
        $categoryBreakdown = DocumentRequest::select('document_types.category', DB::raw('count(*) as total'))
            ->join('document_types', 'document_requests.document_type_id', '=', 'document_types.id')
            ->groupBy('document_types.category')
            ->get()
            ->keyBy('category');

        $academicCount      = optional($categoryBreakdown->get('academic'))->total ?? 0;
        $certificationCount = optional($categoryBreakdown->get('certification'))->total ?? 0;

        // Trending document — most requested in last 30 days
        $trendingDoc = DocumentRequest::select('document_type_id', DB::raw('count(*) as total'))
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('document_type_id')
            ->orderBy('total', 'desc')
            ->with('documentType')
            ->first();

        $forecastLabels = [];
        for ($i = 1; $i <= 3; $i++) {
            $forecastLabels[] = Carbon::now()->addMonths($i)->format('M Y');
        }

        return view('admin.dashboard', compact(
            'totalRequests', 'pendingRequests', 'processingRequests',
            'completedRequests', 'totalStudents', 'recentRequests',
            'documentDemand', 'monthlyLabels', 'monthlyValues',
            'academicCount', 'certificationCount', 'trendingDoc',
            'forecastLabels', 'overallForecastValues'
        ));
    }
}
