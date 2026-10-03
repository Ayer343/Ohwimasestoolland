<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\ConstructionContract;
use App\Models\ConstructionMilestone;
use App\Models\ConstructionProgressUpdate;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ContractorController extends Controller
{
    /**
 * Show the contractor dashboard
 */
public function dashboard()
{
    $userId = auth()->id();
    $user = auth()->user();

    // Get system settings for currency
    $settings = SystemSetting::getSettings();
    $currencySymbol = $settings->currency_symbol ?? '$';
    $currencyCode = $settings->currency_code ?? 'USD';
    $currencyPosition = $settings->currency_position ?? 'left';

    // Determine the correct date column for milestones
    $dateColumn = $this->getMilestoneDateColumn();

    // ============================================ //
    // CONTRACT STATISTICS                          //
    // ============================================ //
    $stats = [
        'total' => ConstructionContract::where('contractor_user_id', $userId)->count(),
        'active' => ConstructionContract::where('contractor_user_id', $userId)
            ->whereIn('status', [
                ConstructionContract::STATUS_APPROVED,
                ConstructionContract::STATUS_IN_PROGRESS
            ])->count(),
        'in_progress' => ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_IN_PROGRESS)->count(),
        'completed' => ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_COMPLETED)->count(),
        'on_hold' => ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_ON_HOLD)->count(),
        'pending' => ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_PENDING_APPROVAL)->count(),
        'overdue' => ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', '!=', ConstructionContract::STATUS_COMPLETED)
            ->where('status', '!=', ConstructionContract::STATUS_CANCELLED)
            ->where('estimated_completion_date', '<', now())
            ->count(),
    ];

    // Calculate total contract value
    $totalContractValue = ConstructionContract::where('contractor_user_id', $userId)
        ->sum('contract_amount') ?? 0;

    // Calculate average contract value
    $avgContractValue = $stats['total'] > 0 
        ? $totalContractValue / $stats['total'] 
        : 0;

    // ============================================ //
    // MILESTONE STATISTICS                         //
    // ============================================ //
    $milestoneStats = [
        'total' => ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })->count(),
        'completed' => ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })->where('status', 'completed')->count(),
        'pending' => ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })->where('status', 'pending')->count(),
        'in_progress' => ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })->where('status', 'in_progress')->count(),
        'overdue' => ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })
        ->where('status', '!=', 'completed')
        ->where($dateColumn, '<', now())
        ->count(),
        'upcoming' => ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })
        ->where('status', '!=', 'completed')
        ->whereBetween($dateColumn, [now(), now()->addDays(7)])
        ->count(),
    ];

    // Calculate milestone completion rate
    $milestoneCompletionRate = $milestoneStats['total'] > 0 
        ? round(($milestoneStats['completed'] / $milestoneStats['total']) * 100) 
        : 0;

    // ============================================ //
    // RECENT CONTRACTS                             //
    // ============================================ //
    $recentContracts = ConstructionContract::where('contractor_user_id', $userId)
        ->with(['property', 'landlord'])
        ->latest()
        ->take(5)
        ->get();

    // ============================================ //
    // UPCOMING MILESTONES                          //
    // ============================================ //
    $upcomingMilestones = ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
        $query->where('contractor_user_id', $userId);
    })
    ->where('status', '!=', 'completed')
    ->whereBetween($dateColumn, [now(), now()->addDays(7)])
    ->with('contract')
    ->orderBy($dateColumn)
    ->take(5)
    ->get();

    // ============================================ //
    // RECENT ACTIVITY                              //
    // ============================================ //
    $recentActivity = $this->getRecentActivity($userId);

    // ============================================ //
    // CONTRACTOR METADATA                          //
    // ============================================ //
    $metadata = $user->metadata ?? [];
    $specializations = $metadata['specializations'] ?? [];
    $licenseNumber = $metadata['license_number'] ?? null;
    $experienceYears = $metadata['experience_years'] ?? 0;
    $rating = $metadata['rating'] ?? 0;
    $reviewsCount = $metadata['reviews_count'] ?? 0;

    // ============================================ //
    // PERFORMANCE METRICS                          //
    // ============================================ //
    $performanceMetrics = $this->calculatePerformanceMetrics($userId);

    // ============================================ //
    // CHART DATA FOR DASHBOARD                     //
    // ============================================ //
    $chartData = $this->getChartData($userId);

    // ============================================ //
    // QUICK STATS FOR WIDGETS                      //
    // ============================================ //
    $quickStats = [
        'total_contracts' => $stats['total'],
        'active_contracts' => $stats['active'],
        'completed_contracts' => $stats['completed'],
        'total_milestones' => $milestoneStats['total'],
        'completed_milestones' => $milestoneStats['completed'],
        'milestone_completion_rate' => $milestoneCompletionRate,
        'total_value' => $totalContractValue,
        'avg_value' => $avgContractValue,
        'contract_trend' => $this->calculateTrend($stats['total'], 'contracts', $userId),
        'active_trend' => $this->calculateTrend($stats['active'], 'active_contracts', $userId),
        'value_trend' => $this->calculateTrend($totalContractValue, 'contract_value', $userId),
        'milestone_trend' => $this->calculateTrend($milestoneCompletionRate, 'milestone_rate', $userId),
    ];

    // Pass all variables to the view including currency settings
    return view('contractor.dashboard', compact(
        'user',
        'stats',
        'milestoneStats',
        'milestoneCompletionRate',
        'totalContractValue',
        'avgContractValue',
        'recentContracts',
        'upcomingMilestones',
        'recentActivity',
        'specializations',
        'licenseNumber',
        'experienceYears',
        'rating',
        'reviewsCount',
        'performanceMetrics',
        'chartData',
        'quickStats',
        'dateColumn',
        'currencySymbol',
        'currencyCode',
        'currencyPosition'
    ));
}

/**
 * Calculate trend percentage for dashboard stats
 */
private function calculateTrend($currentValue, $metric, $userId): float
{
    try {
        // Get previous month's value
        $previousMonth = now()->subMonth();
        
        switch ($metric) {
            case 'contracts':
                $previousValue = ConstructionContract::where('contractor_user_id', $userId)
                    ->whereMonth('created_at', $previousMonth->month)
                    ->whereYear('created_at', $previousMonth->year)
                    ->count();
                break;
                
            case 'active_contracts':
                $previousValue = ConstructionContract::where('contractor_user_id', $userId)
                    ->whereIn('status', [
                        ConstructionContract::STATUS_APPROVED,
                        ConstructionContract::STATUS_IN_PROGRESS
                    ])
                    ->whereMonth('created_at', $previousMonth->month)
                    ->whereYear('created_at', $previousMonth->year)
                    ->count();
                break;
                
            case 'contract_value':
                $previousValue = ConstructionContract::where('contractor_user_id', $userId)
                    ->whereMonth('created_at', $previousMonth->month)
                    ->whereYear('created_at', $previousMonth->year)
                    ->sum('contract_amount') ?? 0;
                break;
                
            case 'milestone_rate':
                $total = ConstructionMilestone::whereHas('contract', function($query) use ($userId, $previousMonth) {
                    $query->where('contractor_user_id', $userId)
                        ->whereMonth('created_at', $previousMonth->month)
                        ->whereYear('created_at', $previousMonth->year);
                })->count();
                
                $completed = ConstructionMilestone::whereHas('contract', function($query) use ($userId, $previousMonth) {
                    $query->where('contractor_user_id', $userId)
                        ->whereMonth('created_at', $previousMonth->month)
                        ->whereYear('created_at', $previousMonth->year);
                })->where('status', 'completed')->count();
                
                $previousValue = $total > 0 ? round(($completed / $total) * 100) : 0;
                break;
                
            default:
                $previousValue = 0;
        }
        
        // Calculate trend percentage
        if ($previousValue > 0) {
            return round((($currentValue - $previousValue) / $previousValue) * 100, 1);
        } elseif ($currentValue > 0) {
            return 100; // New record, 100% increase
        } else {
            return 0; // No change
        }
    } catch (\Exception $e) {
        Log::warning('Failed to calculate trend for metric: ' . $metric, [
            'error' => $e->getMessage()
        ]);
        return 0;
    }
}

    /**
     * Get the correct milestone date column
     */
    private function getMilestoneDateColumn(): string
    {
        $dateColumn = 'due_date';
        if (!Schema::hasColumn('construction_milestones', 'due_date')) {
            if (Schema::hasColumn('construction_milestones', 'completion_date')) {
                $dateColumn = 'completion_date';
            } elseif (Schema::hasColumn('construction_milestones', 'estimated_completion_date')) {
                $dateColumn = 'estimated_completion_date';
            } elseif (Schema::hasColumn('construction_milestones', 'milestone_date')) {
                $dateColumn = 'milestone_date';
            } elseif (Schema::hasColumn('construction_milestones', 'target_date')) {
                $dateColumn = 'target_date';
            }
        }
        return $dateColumn;
    }

    /**
     * Get recent activity for the contractor
     */
    private function getRecentActivity($userId)
    {
        $activities = collect();

        // Get recent progress updates
        $progressUpdates = ConstructionProgressUpdate::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })
        ->with(['contract', 'user'])
        ->latest()
        ->take(10)
        ->get()
        ->map(function($update) {
            return (object) [
                'type' => 'progress_updated',
                'icon' => 'fa-chart-line',
                'color' => 'blue',
                'description' => 'Progress updated to ' . ($update->progress_percentage ?? 0) . '%',
                'contract_number' => $update->contract->contract_number ?? 'N/A',
                'contract_title' => $update->contract->title ?? 'N/A',
                'created_at' => $update->created_at,
                'time_ago' => $update->created_at->diffForHumans(),
                'user_name' => $update->user->name ?? 'System',
            ];
        });

        // Get recent milestone completions
        $milestoneCompletions = ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })
        ->where('status', 'completed')
        ->whereNotNull('completed_at')
        ->with('contract')
        ->latest('completed_at')
        ->take(10)
        ->get()
        ->map(function($milestone) {
            return (object) [
                'type' => 'milestone_completed',
                'icon' => 'fa-flag-checkered',
                'color' => 'green',
                'description' => 'Milestone "' . $milestone->title . '" completed',
                'contract_number' => $milestone->contract->contract_number ?? 'N/A',
                'contract_title' => $milestone->contract->title ?? 'N/A',
                'created_at' => $milestone->completed_at,
                'time_ago' => $milestone->completed_at->diffForHumans(),
                'user_name' => 'System',
            ];
        });

        // Get recent contract status changes
        $contractChanges = ConstructionContract::where('contractor_user_id', $userId)
            ->whereNotNull('updated_at')
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(function($contract) {
                return (object) [
                    'type' => 'contract_updated',
                    'icon' => 'fa-file-signature',
                    'color' => 'purple',
                    'description' => 'Contract "' . $contract->title . '" status: ' . ucfirst(str_replace('_', ' ', $contract->status)),
                    'contract_number' => $contract->contract_number ?? 'N/A',
                    'contract_title' => $contract->title ?? 'N/A',
                    'created_at' => $contract->updated_at,
                    'time_ago' => $contract->updated_at->diffForHumans(),
                    'user_name' => 'System',
                ];
            });

        // Merge and sort by date
        $activities = $progressUpdates
            ->concat($milestoneCompletions)
            ->concat($contractChanges)
            ->sortByDesc('created_at')
            ->take(10);

        return $activities;
    }

    /**
     * Calculate performance metrics for the contractor
     */
    private function calculatePerformanceMetrics($userId): array
    {
        $totalContracts = ConstructionContract::where('contractor_user_id', $userId)->count();
        $completedContracts = ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_COMPLETED)
            ->count();

        // Calculate on-time completion rate
        $onTimeContracts = ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_COMPLETED)
            ->whereColumn('actual_completion_date', '<=', 'estimated_completion_date')
            ->count();

        $onTimeRate = $completedContracts > 0 
            ? round(($onTimeContracts / $completedContracts) * 100) 
            : 0;

        // Calculate average completion time (in days)
        $avgCompletionTime = ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_COMPLETED)
            ->whereNotNull('actual_completion_date')
            ->whereNotNull('contract_start_date')
            ->select(DB::raw('AVG(DATEDIFF(actual_completion_date, contract_start_date)) as avg_days'))
            ->value('avg_days') ?? 0;

        // Calculate milestone completion rate
        $totalMilestones = ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })->count();

        $completedMilestones = ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })->where('status', 'completed')->count();

        $milestoneRate = $totalMilestones > 0 
            ? round(($completedMilestones / $totalMilestones) * 100) 
            : 0;

        // Calculate overdue rate
        $overdueContracts = ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', '!=', ConstructionContract::STATUS_COMPLETED)
            ->where('status', '!=', ConstructionContract::STATUS_CANCELLED)
            ->where('estimated_completion_date', '<', now())
            ->count();

        $overdueRate = $totalContracts > 0 
            ? round(($overdueContracts / $totalContracts) * 100) 
            : 0;

        // Calculate overall performance score (0-100)
        $score = 0;
        $score += $onTimeRate * 0.4; // 40% weight
        $score += $milestoneRate * 0.3; // 30% weight
        $score += (100 - $overdueRate) * 0.3; // 30% weight

        return [
            'completion_rate' => $totalContracts > 0 ? round(($completedContracts / $totalContracts) * 100) : 0,
            'on_time_rate' => $onTimeRate,
            'avg_completion_time' => round($avgCompletionTime, 1),
            'milestone_completion_rate' => $milestoneRate,
            'overdue_rate' => $overdueRate,
            'performance_score' => round($score, 1),
            'total_contracts' => $totalContracts,
            'completed_contracts' => $completedContracts,
            'total_milestones' => $totalMilestones,
            'completed_milestones' => $completedMilestones,
        ];
    }

    /**
     * Get chart data for the dashboard
     */
    private function getChartData($userId): array
    {
        // Monthly contract data for the last 6 months
        $monthlyContracts = ConstructionContract::where('contractor_user_id', $userId)
            ->where('created_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(contract_amount) as value')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $labels = [];
        $totals = [];
        $completed = [];
        $values = [];

        foreach ($monthlyContracts as $data) {
            $date = Carbon::create($data->year, $data->month, 1);
            $labels[] = $date->format('M Y');
            $totals[] = $data->total;
            $completed[] = $data->completed;
            $values[] = $data->value;
        }

        // Status distribution
        $statusDistribution = ConstructionContract::where('contractor_user_id', $userId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        return [
            'monthly_labels' => $labels,
            'monthly_totals' => $totals,
            'monthly_completed' => $completed,
            'monthly_values' => $values,
            'status_distribution' => $statusDistribution,
        ];
    }

    /**
     * Get contract status counts for AJAX dashboard widgets
     */
    public function getStatusCounts()
    {
        $userId = auth()->id();
        
        $counts = [
            'total' => ConstructionContract::where('contractor_user_id', $userId)->count(),
            'active' => ConstructionContract::where('contractor_user_id', $userId)
                ->whereIn('status', [
                    ConstructionContract::STATUS_APPROVED,
                    ConstructionContract::STATUS_IN_PROGRESS
                ])->count(),
            'in_progress' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_IN_PROGRESS)->count(),
            'completed' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_COMPLETED)->count(),
            'on_hold' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_ON_HOLD)->count(),
            'pending' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_PENDING_APPROVAL)->count(),
            'overdue' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', '!=', ConstructionContract::STATUS_COMPLETED)
                ->where('status', '!=', ConstructionContract::STATUS_CANCELLED)
                ->where('estimated_completion_date', '<', now())
                ->count(),
        ];

        return response()->json($counts);
    }

    /**
     * Get performance metrics for AJAX dashboard widgets
     */
    public function getPerformanceMetrics()
    {
        $userId = auth()->id();
        $metrics = $this->calculatePerformanceMetrics($userId);

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Get upcoming milestones for AJAX dashboard widgets
     */
    public function getUpcomingMilestones()
    {
        $userId = auth()->id();
        $dateColumn = $this->getMilestoneDateColumn();

        $milestones = ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })
        ->where('status', '!=', 'completed')
        ->whereBetween($dateColumn, [now(), now()->addDays(7)])
        ->with('contract')
        ->orderBy($dateColumn)
        ->take(10)
        ->get()
        ->map(function($milestone) use ($dateColumn) {
            return [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'contract_number' => $milestone->contract->contract_number ?? 'N/A',
                'contract_title' => $milestone->contract->title ?? 'N/A',
                'due_date' => $milestone->$dateColumn,
                'status' => $milestone->status,
                'days_remaining' => now()->diffInDays($milestone->$dateColumn),
                'is_overdue' => $milestone->$dateColumn < now(),
            ];
        });

        return response()->json([
            'success' => true,
            'milestones' => $milestones,
            'count' => $milestones->count()
        ]);
    }

    /**
     * Get recent activity for AJAX dashboard widgets
     */
    public function getRecentActivityAjax()
    {
        $userId = auth()->id();
        $activity = $this->getRecentActivity($userId);

        return response()->json([
            'success' => true,
            'activity' => $activity,
            'count' => $activity->count()
        ]);
    }

    /**
     * Get chart data for AJAX dashboard widgets
     */
    public function getChartDataAjax()
    {
        $userId = auth()->id();
        $chartData = $this->getChartData($userId);

        return response()->json([
            'success' => true,
            'chartData' => $chartData
        ]);
    }

    /**
     * Get quick stats for dashboard widgets
     */
    public function getQuickStats()
    {
        $userId = auth()->id();
        $dateColumn = $this->getMilestoneDateColumn();

        $totalContracts = ConstructionContract::where('contractor_user_id', $userId)->count();
        $activeContracts = ConstructionContract::where('contractor_user_id', $userId)
            ->whereIn('status', [
                ConstructionContract::STATUS_APPROVED,
                ConstructionContract::STATUS_IN_PROGRESS
            ])->count();
        $completedContracts = ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_COMPLETED)->count();
        
        $totalMilestones = ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })->count();
        
        $completedMilestones = ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })->where('status', 'completed')->count();
        
        $overdueMilestones = ConstructionMilestone::whereHas('contract', function($query) use ($userId) {
            $query->where('contractor_user_id', $userId);
        })
        ->where('status', '!=', 'completed')
        ->where($dateColumn, '<', now())
        ->count();

        $totalValue = ConstructionContract::where('contractor_user_id', $userId)
            ->sum('contract_amount') ?? 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'total_contracts' => $totalContracts,
                'active_contracts' => $activeContracts,
                'completed_contracts' => $completedContracts,
                'total_milestones' => $totalMilestones,
                'completed_milestones' => $completedMilestones,
                'overdue_milestones' => $overdueMilestones,
                'total_value' => $totalValue,
                'completion_rate' => $totalContracts > 0 ? round(($completedContracts / $totalContracts) * 100) : 0,
                'milestone_completion_rate' => $totalMilestones > 0 ? round(($completedMilestones / $totalMilestones) * 100) : 0,
            ]
        ]);
    }
}