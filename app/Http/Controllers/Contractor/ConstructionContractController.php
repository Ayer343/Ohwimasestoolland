<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\ConstructionContract;
use App\Models\ConstructionMilestone;
use App\Models\ConstructionProgressUpdate;
use App\Models\User;
use App\Notifications\GeneralNotification; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Notifications\ContractorProgressUpdated;
use App\Notifications\ContractorMilestoneCompleted;
use Carbon\Carbon;

class ConstructionContractController extends Controller
{
    /**
     * Display a listing of the contractor's contracts
     */
    public function index(Request $request)
    {
        $query = ConstructionContract::where('contractor_user_id', auth()->id())
            ->with(['property', 'landlord', 'milestones']);

        // Apply filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('contract_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%")
                  ->orWhereHas('property', function($propertyQuery) use ($search) {
                      $propertyQuery->where('property_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $allowedSorts = ['created_at', 'contract_number', 'title', 'contract_amount', 'status', 'contract_start_date'];
        
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $contracts = $query->paginate(15)->withQueryString();

        // Statistics for dashboard
        $stats = [
            'total' => ConstructionContract::where('contractor_user_id', auth()->id())->count(),
            'active' => ConstructionContract::where('contractor_user_id', auth()->id())
                ->whereIn('status', [
                    ConstructionContract::STATUS_APPROVED,
                    ConstructionContract::STATUS_IN_PROGRESS
                ])->count(),
            'in_progress' => ConstructionContract::where('contractor_user_id', auth()->id())
                ->where('status', ConstructionContract::STATUS_IN_PROGRESS)->count(),
            'completed' => ConstructionContract::where('contractor_user_id', auth()->id())
                ->where('status', ConstructionContract::STATUS_COMPLETED)->count(),
            'on_hold' => ConstructionContract::where('contractor_user_id', auth()->id())
                ->where('status', ConstructionContract::STATUS_ON_HOLD)->count(),
            'pending' => ConstructionContract::where('contractor_user_id', auth()->id())
                ->where('status', ConstructionContract::STATUS_PENDING_APPROVAL)->count(),
            'overdue' => ConstructionContract::where('contractor_user_id', auth()->id())
                ->where('status', '!=', ConstructionContract::STATUS_COMPLETED)
                ->where('status', '!=', ConstructionContract::STATUS_CANCELLED)
                ->where('estimated_completion_date', '<', now())
                ->count(),
        ];

        // Get unique statuses for filter dropdown
        $statuses = ConstructionContract::where('contractor_user_id', auth()->id())
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->toArray();

        return view('contractor.construction.contracts.index', compact('contracts', 'stats', 'statuses'));
    }

    /**
     * Display a specific construction contract with all details
     */
    public function show(ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to view this contract.');
        }

        // Determine the correct date column for milestones
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

        // Load relationships with dynamic order by
        $contract->load([
            'landlord',
            'property',
            'milestones' => function($query) use ($dateColumn) {
                $query->orderBy($dateColumn);
            },
            'progressUpdates' => function($query) {
                $query->latest()->limit(10);
            },
            'activityLogs' => function($query) {
                $query->with('user')->latest()->limit(50);
            },
            'contractorUser'
        ]);

        // Calculate progress
        $progress = $contract->progress_percentage;

        // Get milestone statistics with dynamic column
        $milestoneStats = [
            'total' => $contract->milestones()->count(),
            'pending' => $contract->milestones()->where('status', 'pending')->count(),
            'in_progress' => $contract->milestones()->where('status', 'in_progress')->count(),
            'completed' => $contract->milestones()->where('status', 'completed')->count(),
            'delayed' => $contract->milestones()->where('status', 'delayed')->count(),
        ];

        // Get overdue milestones with dynamic column
        $overdueMilestones = $contract->milestones()
            ->where('status', '!=', 'completed')
            ->where($dateColumn, '<', now())
            ->count();

        // Get upcoming milestones with dynamic column
        $upcomingMilestones = $contract->milestones()
            ->where('status', '!=', 'completed')
            ->whereBetween($dateColumn, [now(), now()->addDays(7)])
            ->count();

        // Check if contract is ready for completion
        $canComplete = $contract->canBeCompleted();

        return view('contractor.construction.contracts.show', compact(
            'contract',
            'progress',
            'milestoneStats',
            'overdueMilestones',
            'upcomingMilestones',
            'canComplete',
            'dateColumn'
        ));
    }

    /**
     * Show form to update contract progress
     */
    public function showProgressForm(ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to update this contract.');
        }

        // Only allow progress updates for active contracts
        if (!in_array($contract->status, [
            ConstructionContract::STATUS_APPROVED,
            ConstructionContract::STATUS_IN_PROGRESS,
            ConstructionContract::STATUS_ON_HOLD
        ])) {
            return redirect()->route('contractor.contracts.show', $contract)
                ->with('error', 'Progress updates are only available for active contracts.');
        }

        $milestones = $contract->milestones()
            ->where('status', '!=', 'completed')
            ->orderBy('due_date')
            ->get();

        $completedMilestones = $contract->milestones()
            ->where('status', 'completed')
            ->count();

        $totalMilestones = $contract->milestones()->count();

        return view('contractor.construction.contracts.progress', compact(
            'contract',
            'milestones',
            'completedMilestones',
            'totalMilestones'
        ));
    }

    /**
     * Update contract progress - FIXED VERSION
     */
    public function updateProgress(Request $request, ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to update this contract.');
        }

        $validator = Validator::make($request->all(), [
            'progress_percentage' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
            'milestone_ids' => 'nullable|array',
            'milestone_ids.*' => 'exists:construction_milestones,id',
            'photo' => 'nullable|image|max:5120', // 5MB max
            'status' => 'nullable|in:in_progress,on_hold,completed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $updates = [];
            $oldProgress = $contract->progress_percentage ?? 0;
            $progressUpdated = false;

            // ✅ FIX 1: Update progress percentage - ALWAYS update if provided
            if ($request->has('progress_percentage')) {
                $newProgress = (int) $request->progress_percentage;
                $updates['progress_percentage'] = $newProgress;
                $progressUpdated = true;
                
                Log::info('Progress percentage being updated', [
                    'contract_id' => $contract->id,
                    'old_progress' => $oldProgress,
                    'new_progress' => $newProgress
                ]);
            }

            // Update status if provided and valid
            if ($request->has('status') && $request->status) {
                $newStatus = $request->status;
                
                // Validate status transition
                if ($this->canTransitionTo($contract, $newStatus)) {
                    $updates['status'] = $newStatus;
                } else {
                    return redirect()->back()
                        ->with('error', 'Invalid status transition.');
                }
            }

            // ✅ FIX 2: Update contract and refresh
            if (!empty($updates)) {
                $contract->update($updates);
                $contract->refresh(); // Refresh to get updated values
                
                Log::info('Contract updated successfully', [
                    'contract_id' => $contract->id,
                    'updates' => $updates,
                    'new_progress' => $contract->progress_percentage
                ]);
            }

            // Process milestone updates
            if ($request->has('milestone_ids')) {
                $milestoneIds = $request->milestone_ids;
                
                // Update selected milestones to 'completed'
                ConstructionMilestone::whereIn('id', $milestoneIds)
                    ->where('contract_id', $contract->id)
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                // Log each completed milestone
                foreach ($milestoneIds as $milestoneId) {
                    $milestone = ConstructionMilestone::find($milestoneId);
                    if ($milestone) {
                        try {
                            if (method_exists($contract, 'logActivity')) {
                                $contract->logActivity('milestone_completed', "Milestone '{$milestone->title}' completed by contractor");
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to log milestone activity: ' . $e->getMessage());
                        }
                    }
                }
            }

            // ✅ FIX 3: Handle photo upload - use refreshed progress
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $photoPath = $photo->store('contract-progress', 'public');
            }

            // ✅ FIX 4: Save progress update with refreshed progress value
            if ($request->has('notes') || $request->has('progress_percentage') || $request->hasFile('photo')) {
                ConstructionProgressUpdate::create([
                    'contract_id' => $contract->id,
                    'user_id' => auth()->id(),
                    'notes' => $request->notes ?? 'Progress update',
                    'photo_path' => $photoPath,
                    'progress_percentage' => $contract->progress_percentage, // Use refreshed value
                ]);
            }

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $logMessage = 'Contract progress updated by contractor';
                    if ($request->has('status')) {
                        $logMessage .= " - Status changed to {$request->status}";
                    }
                    if ($progressUpdated) {
                        $logMessage .= " - Progress: {$contract->progress_percentage}%";
                    }
                    $contract->logActivity('progress_updated', $logMessage);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log progress activity: ' . $e->getMessage());
            }

            // ✅ FIX 5: Notify landlord with correct data
            $this->notifyLandlord($contract, 'progress_updated', [
                'notes' => $request->notes,
                'progress_percentage' => $contract->progress_percentage,
                'old_progress' => $oldProgress,
                'status' => $request->status,
                'milestones_completed' => $request->milestone_ids ?? [],
            ]);

            DB::commit();

            // ✅ FIX 6: Return with specific success message
            return redirect()->route('contractor.contracts.show', $contract)
                ->with('success', "Progress updated successfully! Current progress: {$contract->progress_percentage}%");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update contract progress: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'contractor_id' => auth()->id(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update progress. Please try again.')
                ->withInput();
        }
    }

    /**
 * Show form to submit milestone
 */
public function showMilestoneForm(ConstructionContract $contract, ConstructionMilestone $milestone)
{
    // ✅ Authorization check
    if ($contract->contractor_user_id !== auth()->id()) {
        abort(403, 'Unauthorized to access this milestone.');
    }

    // Ensure milestone belongs to contract
    if ($milestone->contract_id !== $contract->id) {
        abort(404, 'Milestone not found.');
    }

    // ✅ Get milestone statistics for the contract
    $milestoneStats = [
        'total' => $contract->milestones()->count(),
        'completed' => $contract->milestones()->where('status', 'completed')->count(),
        'pending' => $contract->milestones()->where('status', 'pending')->count(),
        'in_progress' => $contract->milestones()->where('status', 'in_progress')->count(),
        'overdue' => $contract->milestones()
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', now())
            ->count(),
    ];

    return view('contractor.construction.milestones.submit', compact(
        'contract',
        'milestone',
        'milestoneStats'
    ));
}

    /**
     * Submit milestone completion
     */
    public function submitMilestone(Request $request, ConstructionContract $contract, ConstructionMilestone $milestone)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            abort(403, 'Unauthorized to update this milestone.');
        }

        if ($milestone->contract_id !== $contract->id) {
            abort(404, 'Milestone not found.');
        }

        $validator = Validator::make($request->all(), [
            'completion_notes' => 'nullable|string|max:1000',
            'evidence' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx', // 10MB max
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Update milestone
            $milestone->status = 'completed';
            $milestone->completed_at = now();
            $milestone->completion_notes = $request->completion_notes;
            
            if ($request->hasFile('evidence')) {
                $path = $request->file('evidence')->store('milestone-evidence', 'public');
                $milestone->evidence_path = $path;
            }
            
            $milestone->save();

            // Log activity
            try {
                if (method_exists($contract, 'logActivity')) {
                    $contract->logActivity('milestone_submitted', "Milestone '{$milestone->title}' submitted for review by contractor");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to log milestone activity: ' . $e->getMessage());
            }

            // Notify landlord
            $this->notifyLandlord($contract, 'milestone_completed', [
                'milestone_title' => $milestone->title,
                'notes' => $request->completion_notes,
            ]);

            DB::commit();

            return redirect()->route('contractor.contracts.show', $contract)
                ->with('success', 'Milestone submitted successfully! The landlord will review it.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to submit milestone: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'milestone_id' => $milestone->id,
                'contractor_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to submit milestone. Please try again.')
                ->withInput();
        }
    }

    /**
     * Check if contract can transition to new status
     */
    private function canTransitionTo(ConstructionContract $contract, string $newStatus): bool
    {
        $allowedTransitions = [
            ConstructionContract::STATUS_APPROVED => ['in_progress', 'on_hold'],
            ConstructionContract::STATUS_IN_PROGRESS => ['on_hold', 'completed'],
            ConstructionContract::STATUS_ON_HOLD => ['in_progress'],
            ConstructionContract::STATUS_PENDING_APPROVAL => [], // Cannot transition from pending
            ConstructionContract::STATUS_COMPLETED => [], // Cannot transition from completed
            ConstructionContract::STATUS_CANCELLED => [], // Cannot transition from cancelled
        ];

        $currentStatus = $contract->status;
        
        if (!isset($allowedTransitions[$currentStatus])) {
            return false;
        }

        return in_array($newStatus, $allowedTransitions[$currentStatus]);
    }

    /**
 * Notify landlord about contract updates - Using GeneralNotification
 */
private function notifyLandlord(ConstructionContract $contract, string $action, array $data = [])
{
    try {
        $landlord = $contract->landlord;
        
        if (!$landlord) {
            Log::warning('Landlord not found for contract notification', [
                'contract_id' => $contract->id
            ]);
            return;
        }

        // ✅ Build notification data based on action
        $notificationData = $this->buildNotificationData($contract, $action, $data);
        
        if (!$notificationData) {
            Log::warning('No notification data to send', [
                'contract_id' => $contract->id,
                'action' => $action
            ]);
            return;
        }

        // ✅ Send notification using GeneralNotification
        $landlord->notify(new GeneralNotification(
            title: $notificationData['title'],
            message: $notificationData['message'],
            icon: $notificationData['icon'],
            category: $notificationData['category'],
            priority: $notificationData['priority'],
            actionUrl: $notificationData['action_url'],
            data: $notificationData['metadata'],
            roles: $notificationData['roles'] ?? ['landlord'] // Target landlord role
        ));

        Log::info('Landlord notified about contractor action', [
            'contract_id' => $contract->id,
            'landlord_id' => $landlord->id,
            'action' => $action,
            'notification_type' => $notificationData['category']
        ]);

    } catch (\Exception $e) {
        Log::error('Failed to notify landlord: ' . $e->getMessage(), [
            'contract_id' => $contract->id,
            'action' => $action,
            'error_trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Build notification data for different actions
 */
private function buildNotificationData(ConstructionContract $contract, string $action, array $data = []): ?array
{
    $baseData = [
        'contract_id' => $contract->id,
        'contract_number' => $contract->contract_number,
        'contract_title' => $contract->title,
        'timestamp' => now()->toISOString(),
    ];

    // ✅ FIX: Get the correct route for the landlord
    $contractRoute = $this->getContractRoute($contract);

    switch ($action) {
        case 'progress_updated':
            $progress = $data['progress_percentage'] ?? $contract->progress_percentage ?? 0;
            $oldProgress = $data['old_progress'] ?? 0;
            $notes = $data['notes'] ?? null;
            
            return [
                'title' => "📊 Progress Update: {$contract->contract_number}",
                'message' => "The contractor has updated the progress on **{$contract->title}**.\n\n" .
                             "Progress: {$oldProgress}% → **{$progress}%**" .
                             ($notes ? "\n\nNotes: {$notes}" : '') .
                             "\n\nProperty: " . ($contract->property->property_name ?? 'N/A'),
                'icon' => 'fas fa-chart-line text-primary',
                'category' => 'progress_update',
                'priority' => 2,
                'action_url' => $contractRoute,
                'metadata' => array_merge($baseData, [
                    'progress_percentage' => $progress,
                    'old_progress' => $oldProgress,
                    'notes' => $notes,
                    'status' => $data['status'] ?? null,
                    'milestones_completed' => $data['milestones_completed'] ?? [],
                    'type' => 'progress_updated',
                ]),
                'roles' => ['landlord', 'admin'],
            ];
            
        case 'milestone_completed':
            $milestoneTitle = $data['milestone_title'] ?? 'Unknown Milestone';
            $notes = $data['notes'] ?? null;
            
            return [
                'title' => "✅ Milestone Completed: {$milestoneTitle}",
                'message' => "The contractor has completed the milestone **{$milestoneTitle}** on **{$contract->title}**." .
                             ($notes ? "\n\nNotes: {$notes}" : '') .
                             "\n\nContract: {$contract->contract_number}" .
                             "\nProperty: " . ($contract->property->property_name ?? 'N/A'),
                'icon' => 'fas fa-flag-checkered text-success',
                'category' => 'milestone_completed',
                'priority' => 2,
                'action_url' => $contractRoute,
                'metadata' => array_merge($baseData, [
                    'milestone_title' => $milestoneTitle,
                    'notes' => $notes,
                    'type' => 'milestone_completed',
                ]),
                'roles' => ['landlord', 'admin'],
            ];
            
        default:
            Log::warning('Unknown notification action', ['action' => $action]);
            return null;
    }
}

/**
 * Get the correct route for the contract based on user role
 */
private function getContractRoute(ConstructionContract $contract): string
{
    // Try different route names that might exist
    $possibleRoutes = [
        'landlord.contracts.show',
        'admin.contracts.show',
        'contractor.contracts.show',
        'property.contracts.show',
        'contracts.show',
        'contract.show',
    ];
    
    foreach ($possibleRoutes as $routeName) {
        try {
            if (\Illuminate\Support\Facades\Route::has($routeName)) {
                return route($routeName, $contract->id);
            }
        } catch (\Exception $e) {
            // Route exists but has parameters, continue
            continue;
        }
    }
    
    // If no route found, return a fallback URL
    Log::warning('No contract route found, using fallback URL', [
        'contract_id' => $contract->id,
        'tried_routes' => $possibleRoutes
    ]);
    
    return url("/contracts/{$contract->id}");
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
     * Get milestone statistics for a specific contract
     */
    public function getMilestoneStats(ConstructionContract $contract)
    {
        // ✅ Authorization check
        if ($contract->contractor_user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Determine the correct date column for milestones
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

        $stats = [
            'total' => $contract->milestones()->count(),
            'pending' => $contract->milestones()->where('status', 'pending')->count(),
            'in_progress' => $contract->milestones()->where('status', 'in_progress')->count(),
            'completed' => $contract->milestones()->where('status', 'completed')->count(),
            'delayed' => $contract->milestones()
                ->where('status', '!=', 'completed')
                ->where($dateColumn, '<', now())
                ->count(),
            'upcoming' => $contract->milestones()
                ->where('status', '!=', 'completed')
                ->whereBetween($dateColumn, [now(), now()->addDays(7)])
                ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Display active projects for the contractor
     */
    public function activeProjects()
    {
        $userId = auth()->id();
        
        $contracts = ConstructionContract::where('contractor_user_id', $userId)
            ->whereIn('status', [
                ConstructionContract::STATUS_APPROVED,
                ConstructionContract::STATUS_IN_PROGRESS
            ])
            ->with(['property', 'landlord', 'milestones'])
            ->latest()
            ->paginate(15);
        
        $stats = [
            'total' => ConstructionContract::where('contractor_user_id', $userId)
                ->whereIn('status', [
                    ConstructionContract::STATUS_APPROVED,
                    ConstructionContract::STATUS_IN_PROGRESS
                ])->count(),
            'in_progress' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_IN_PROGRESS)->count(),
            'approved' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_APPROVED)->count(),
            'on_hold' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_ON_HOLD)->count(),
        ];
        
        return view('contractor.projects.active', compact('contracts', 'stats'));
    }

    /**
     * Display completed projects for the contractor
     */
    public function completedProjects()
    {
        $userId = auth()->id();
        
        $contracts = ConstructionContract::where('contractor_user_id', $userId)
            ->where('status', ConstructionContract::STATUS_COMPLETED)
            ->with(['property', 'landlord', 'milestones'])
            ->latest('updated_at')
            ->paginate(15);
        
        $stats = [
            'total' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_COMPLETED)->count(),
            'this_month' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_COMPLETED)
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->count(),
            'this_year' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_COMPLETED)
                ->whereYear('completed_at', now()->year)
                ->count(),
            'avg_completion_days' => ConstructionContract::where('contractor_user_id', $userId)
                ->where('status', ConstructionContract::STATUS_COMPLETED)
                ->whereNotNull('completed_at')
                ->whereNotNull('contract_start_date')
                ->select(DB::raw('AVG(DATEDIFF(completed_at, contract_start_date)) as avg_days'))
                ->value('avg_days') ?? 0,
        ];
        
        return view('contractor.projects.completed', compact('contracts', 'stats'));
    }
}