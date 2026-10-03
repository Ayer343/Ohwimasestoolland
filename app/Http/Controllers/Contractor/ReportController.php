<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\ConstructionContract;
use App\Models\ConstructionMilestone;
use App\Models\ConstructionProgressUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display the reports page
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        $user = auth()->user();
        
        // Get filter parameters
        $dateFrom = $request->input('date_from', now()->subMonths(6)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $status = $request->input('status', 'all');
        $reportType = $request->input('report_type', 'summary');
        
        // Build contract query
        $query = ConstructionContract::where('contractor_user_id', $userId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        $contracts = $query->with(['property', 'landlord', 'milestones'])->get();
        
        // Summary statistics
        $summary = [
            'total_contracts' => $contracts->count(),
            'total_value' => $contracts->sum('contract_amount'),
            'avg_value' => $contracts->count() > 0 ? $contracts->avg('contract_amount') : 0,
            'completed_contracts' => $contracts->where('status', 'completed')->count(),
            'active_contracts' => $contracts->whereIn('status', ['approved', 'in_progress'])->count(),
            'pending_contracts' => $contracts->where('status', 'pending_approval')->count(),
            'on_hold_contracts' => $contracts->where('status', 'on_hold')->count(),
            'overdue_contracts' => $contracts->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->where('estimated_completion_date', '<', now())
                ->count(),
        ];
        
        // Milestone statistics
        $milestoneStats = [
            'total' => ConstructionMilestone::whereHas('contract', function($query) use ($userId, $dateFrom, $dateTo) {
                $query->where('contractor_user_id', $userId)
                    ->whereBetween('created_at', [$dateFrom, $dateTo]);
            })->count(),
            'completed' => ConstructionMilestone::whereHas('contract', function($query) use ($userId, $dateFrom, $dateTo) {
                $query->where('contractor_user_id', $userId)
                    ->whereBetween('created_at', [$dateFrom, $dateTo]);
            })->where('status', 'completed')->count(),
            'pending' => ConstructionMilestone::whereHas('contract', function($query) use ($userId, $dateFrom, $dateTo) {
                $query->where('contractor_user_id', $userId)
                    ->whereBetween('created_at', [$dateFrom, $dateTo]);
            })->where('status', 'pending')->count(),
            'in_progress' => ConstructionMilestone::whereHas('contract', function($query) use ($userId, $dateFrom, $dateTo) {
                $query->where('contractor_user_id', $userId)
                    ->whereBetween('created_at', [$dateFrom, $dateTo]);
            })->where('status', 'in_progress')->count(),
            'completion_rate' => 0,
        ];
        
        $milestoneStats['completion_rate'] = $milestoneStats['total'] > 0 
            ? round(($milestoneStats['completed'] / $milestoneStats['total']) * 100) 
            : 0;
        
        // Progress data for chart
        $progressData = $this->getProgressChartData($userId, $dateFrom, $dateTo);
        
        // Status distribution for pie chart
        $statusDistribution = $this->getStatusDistribution($userId, $dateFrom, $dateTo);
        
        // Monthly trend data
        $monthlyTrend = $this->getMonthlyTrend($userId, $dateFrom, $dateTo);
        
        // Recent activity
        $recentActivity = ConstructionProgressUpdate::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })
        ->with(['contract', 'user'])
        ->whereBetween('created_at', [$dateFrom, $dateTo])
        ->latest()
        ->take(10)
        ->get();
        
        return view('contractor.reports', compact(
            'user',
            'contracts',
            'summary',
            'milestoneStats',
            'progressData',
            'statusDistribution',
            'monthlyTrend',
            'recentActivity',
            'dateFrom',
            'dateTo',
            'status',
            'reportType'
        ));
    }
    
    /**
     * Get progress chart data
     */
    private function getProgressChartData($userId, $dateFrom, $dateTo)
    {
        $contracts = ConstructionContract::where('contractor_user_id', $userId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();
        
        $labels = [];
        $progressData = [];
        
        foreach ($contracts as $contract) {
            $labels[] = $contract->title;
            $progressData[] = $contract->progress_percentage ?? 0;
        }
        
        return [
            'labels' => $labels,
            'data' => $progressData,
        ];
    }
    
    /**
     * Get status distribution for pie chart
     */
    private function getStatusDistribution($userId, $dateFrom, $dateTo)
    {
        return ConstructionContract::where('contractor_user_id', $userId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
    }
    
    /**
     * Get monthly trend data
     */
    private function getMonthlyTrend($userId, $dateFrom, $dateTo)
    {
        $months = [];
        $contractCounts = [];
        $values = [];
        
        $start = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        
        while ($start->lte($end)) {
            $month = $start->format('Y-m');
            $months[] = $start->format('M Y');
            
            $count = ConstructionContract::where('contractor_user_id', $userId)
                ->whereYear('created_at', $start->year)
                ->whereMonth('created_at', $start->month)
                ->count();
            
            $value = ConstructionContract::where('contractor_user_id', $userId)
                ->whereYear('created_at', $start->year)
                ->whereMonth('created_at', $start->month)
                ->sum('contract_amount') ?? 0;
            
            $contractCounts[] = $count;
            $values[] = $value;
            
            $start->addMonth();
        }
        
        return [
            'months' => $months,
            'contract_counts' => $contractCounts,
            'values' => $values,
        ];
    }
    
    /**
     * Export report as CSV
     */
    public function exportCsv(Request $request)
    {
        $userId = auth()->id();
        $dateFrom = $request->input('date_from', now()->subMonths(6)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $status = $request->input('status', 'all');
        
        $query = ConstructionContract::where('contractor_user_id', $userId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with(['property', 'landlord', 'milestones']);
        
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        $contracts = $query->get();
        
        $csvData = [];
        $csvData[] = [
            'Contract Number',
            'Title',
            'Property',
            'Landlord',
            'Status',
            'Amount',
            'Start Date',
            'End Date',
            'Progress %',
            'Milestones',
            'Created At',
        ];
        
        foreach ($contracts as $contract) {
            $csvData[] = [
                $contract->contract_number,
                $contract->title,
                $contract->property->property_name ?? 'N/A',
                $contract->landlord->name ?? 'N/A',
                ucfirst(str_replace('_', ' ', $contract->status)),
                $contract->contract_amount,
                $contract->contract_start_date?->format('Y-m-d') ?? 'N/A',
                $contract->contract_end_date?->format('Y-m-d') ?? 'N/A',
                $contract->progress_percentage ?? 0,
                $contract->milestones()->count(),
                $contract->created_at->format('Y-m-d'),
            ];
        }
        
        $fileName = 'contractor_report_' . date('Y-m-d') . '.csv';
        
        return $this->arrayToCsv($csvData, $fileName);
    }
    
    /**
     * Convert array to CSV download
     */
    private function arrayToCsv($data, $filename)
    {
        $handle = fopen('php://temp', 'r+');
        
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}