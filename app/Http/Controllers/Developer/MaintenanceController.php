<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceAffectedUser;
use App\Models\User;
use App\Models\EmergencyMode;
use App\Services\NotificationService;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MaintenanceController extends Controller
{
    protected $notificationService;
    protected $healthService;
    
    public function __construct(
        NotificationService $notificationService,
        SystemHealthService $healthService
    ) {
        $this->notificationService = $notificationService;
        $this->healthService = $healthService;
    }

   /**
 * Display all maintenance schedules
 */
public function index(Request $request)
{
    $query = Maintenance::with(['creator', 'approver', 'completer'])
        ->orderBy('scheduled_start', 'desc')
        ->orderBy('created_at', 'desc');

    // Filters
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('type')) {
        $query->where('maintenance_type', $request->type);
    }

    if ($request->filled('impact')) {
        $query->where('impact_level', $request->impact);
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('reference_id', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Date filters
    if ($request->filled('date_from')) {
        $query->whereDate('scheduled_start', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->whereDate('scheduled_start', '<=', $request->date_to);
    }

    $maintenances = $query->paginate(20);
    $statistics = Maintenance::getStatistics();
    
    // Get trash statistics
    $trashCount = Maintenance::onlyTrashed()->count();
    $oldestTrashItem = Maintenance::onlyTrashed()->orderBy('deleted_at')->first();
    
    // Get active maintenance for the banner
    $activeMaintenance = Maintenance::active()->first();

    return view('developer.maintenance.index', compact(
        'maintenances', 
        'statistics', 
        'trashCount',
        'oldestTrashItem',
        'activeMaintenance'
    ));
}

    /**
     * Show maintenance creation form
     */
    public function create()
    {
        $defaultModules = [
            'user_management' => 'User Management',
            'property_management' => 'Property Management',
            'payment_processing' => 'Payment Processing',
            'communication' => 'Communication',
            'reporting' => 'Reporting',
            'api' => 'API Services',
            'dashboard' => 'Dashboard',
            'authentication' => 'Authentication',
            'database' => 'Database',
            'queue' => 'Queue System',
            'storage' => 'File Storage',
            'cache' => 'Cache System',
        ];

        $userTypes = [
            '0' => 'Super Admin',
            '1' => 'Admin',
            '2' => 'Landlord',
            '3' => 'Tenant',
            '4' => 'Field Agent',
            '5' => 'Developer',
            '6' => 'Security Checkpoint',
        ];

        $impactLevels = [
            Maintenance::IMPACT_LOW => 'Low',
            Maintenance::IMPACT_MEDIUM => 'Medium',
            Maintenance::IMPACT_HIGH => 'High',
            Maintenance::IMPACT_CRITICAL => 'Critical',
        ];

        $maintenanceTypes = [
            Maintenance::TYPE_PLANNED => 'Planned Maintenance',
            Maintenance::TYPE_EMERGENCY => 'Emergency Maintenance',
            Maintenance::TYPE_HOTFIX => 'Hotfix',
            Maintenance::TYPE_UPGRADE => 'System Upgrade',
            Maintenance::TYPE_SECURITY => 'Security Patch',
        ];

        // Get active emergencies for linking
        $activeEmergencies = EmergencyMode::active()
            ->select('id', 'name', 'reference_id')
            ->get();

        return view('developer.maintenance.create', compact(
            'defaultModules',
            'userTypes',
            'impactLevels',
            'maintenanceTypes',
            'activeEmergencies'
        ));
    }

    /**
     * Store a new maintenance schedule
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'technical_details' => 'nullable|string',
            'user_impact_description' => 'required|string|min:20',
            'maintenance_type' => 'required|in:planned,emergency,hotfix,upgrade,security',
            'impact_level' => 'required|in:low,medium,high,critical',
            'scheduled_start' => 'required|date|after:now',
            'scheduled_end' => 'required|date|after:scheduled_start',
            'estimated_duration_minutes' => 'required|integer|min:1|max:1440',
            'affected_modules' => 'required|array|min:1',
            'affected_modules.*' => 'string|in:user_management,property_management,payment_processing,communication,reporting,api,dashboard,authentication,database,queue,storage,cache,email,sms,whatsapp',
            'affected_user_types' => 'required|array|min:1',
            'affected_user_types.*' => 'string|in:0,1,2,3,4,5,6',
            'notify_users' => 'boolean',
            'notification_channels' => 'nullable|array',
            'notification_channels.*' => 'in:email,sms,whatsapp,in_app',
            'has_rollback_plan' => 'boolean',
            'is_emergency' => 'boolean',
            'emergency_reason' => 'required_if:is_emergency,true|nullable|string|min:10',
            'related_emergency_id' => 'nullable|exists:emergency_modes,id',
        ], [
            'scheduled_end.after' => 'The end time must be after the start time.',
            'emergency_reason.required_if' => 'Emergency reason is required for emergency maintenance.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the validation errors.');
        }

        DB::beginTransaction();

        try {
            $isEmergency = $request->boolean('is_emergency');
            
            $maintenance = new Maintenance();
            $maintenance->reference_id = Maintenance::generateReferenceId();
            $maintenance->title = $request->title;
            $maintenance->description = $request->description;
            $maintenance->technical_details = $request->technical_details;
            $maintenance->user_impact_description = $request->user_impact_description;
            $maintenance->maintenance_type = $request->maintenance_type;
            $maintenance->impact_level = $request->impact_level;
            $maintenance->scheduled_start = $request->scheduled_start;
            $maintenance->scheduled_end = $request->scheduled_end;
            $maintenance->estimated_duration_minutes = $request->estimated_duration_minutes;
            $maintenance->affected_modules = $request->affected_modules;
            $maintenance->affected_user_types = $request->affected_user_types;
            $maintenance->notify_users = $request->boolean('notify_users', true);
            $maintenance->notification_channels = $request->notification_channels ?? ['email', 'in_app'];
            $maintenance->has_rollback_plan = $request->boolean('has_rollback_plan', false);
            $maintenance->is_emergency = $isEmergency;
            $maintenance->emergency_reason = $isEmergency ? $request->emergency_reason : null;
            $maintenance->related_emergency_id = $request->related_emergency_id;
            $maintenance->status = Maintenance::STATUS_DRAFT;
            $maintenance->created_by = Auth::id();
            
            // Set allowed operations based on impact level
            $maintenance->allowed_operations = $this->getAllowedOperationsForImpact($request->impact_level);
            
            // Calculate estimated affected users
            $maintenance->estimated_affected_users = $this->calculateEstimatedAffectedUsers($request->affected_user_types);
            
            $maintenance->save();

            // Create initial log
            $maintenance->logs()->create([
                'action' => 'created',
                'details' => 'Maintenance schedule created',
                'metadata' => [
                    'type' => $maintenance->maintenance_type,
                    'impact' => $maintenance->impact_level,
                    'scheduled_start' => $maintenance->scheduled_start,
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance schedule created successfully. Please review and approve.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create maintenance schedule', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create maintenance schedule: ' . $e->getMessage());
        }
    }

    /**
     * Show maintenance details
     */
    public function show($id)
    {
        $maintenance = Maintenance::with([
            'creator',
            'approver',
            'completer',
            'emergencyMode',
            'logs' => function($query) {
                $query->orderBy('created_at', 'desc')->take(20);
            },
            'affectedUsers' => function($query) {
                $query->with('user')->take(10);
            }
        ])->findOrFail($id);

        $affectedUsersCount = $maintenance->affectedUsers()->count();
        $recentLogs = $maintenance->logs()->orderBy('created_at', 'desc')->take(10)->get();

        // Calculate impact metrics
        $impactMetrics = $this->calculateMaintenanceImpact($maintenance);

        // Get related maintenance (same time period)
        $relatedMaintenances = Maintenance::where('id', '!=', $id)
            ->where(function($query) use ($maintenance) {
                $query->whereBetween('scheduled_start', [$maintenance->scheduled_start, $maintenance->scheduled_end])
                      ->orWhereBetween('scheduled_end', [$maintenance->scheduled_start, $maintenance->scheduled_end])
                      ->orWhere(function($q) use ($maintenance) {
                          $q->where('scheduled_start', '<=', $maintenance->scheduled_start)
                            ->where('scheduled_end', '>=', $maintenance->scheduled_end);
                      });
            })
            ->whereIn('status', [Maintenance::STATUS_SCHEDULED, Maintenance::STATUS_IN_PROGRESS])
            ->take(5)
            ->get();

        return view('developer.maintenance.show', compact(
            'maintenance',
            'affectedUsersCount',
            'recentLogs',
            'impactMetrics',
            'relatedMaintenances'
        ));
    }

    /**
     * Approve maintenance schedule
     */
    public function approve($id, Request $request)
    {
        $maintenance = Maintenance::findOrFail($id);

        if ($maintenance->status !== Maintenance::STATUS_DRAFT) {
            return redirect()->back()
                ->with('warning', 'Only draft maintenance schedules can be approved.');
        }

        $validator = Validator::make($request->all(), [
            'approval_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide valid approval notes.');
        }

        DB::beginTransaction();

        try {
            $maintenance->status = Maintenance::STATUS_SCHEDULED;
            $maintenance->approved_by = Auth::id();
            $maintenance->approved_at = now();
            $maintenance->save();

            // Create approval log
            $maintenance->logs()->create([
                'action' => 'approved',
                'details' => 'Maintenance schedule approved',
                'metadata' => [
                    'notes' => $request->approval_notes,
                    'approved_by' => Auth::id(),
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send notifications if enabled
            if ($maintenance->notify_users) {
                dispatch(function () use ($maintenance) {
                    $this->notifyUsersOfScheduledMaintenance($maintenance);
                })->afterResponse();
            }

            DB::commit();

            return redirect()->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance schedule approved and notifications sent.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to approve maintenance schedule', [
                'maintenance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to approve maintenance schedule.');
        }
    }

    /**
     * Start maintenance
     */
    public function start($id, Request $request)
    {
        $maintenance = Maintenance::findOrFail($id);

        if ($maintenance->status !== Maintenance::STATUS_SCHEDULED) {
            return redirect()->back()
                ->with('warning', 'Only scheduled maintenance can be started.');
        }

        $validator = Validator::make($request->all(), [
            'start_notes' => 'nullable|string',
            'confirm_pre_checks' => 'required|accepted',
        ], [
            'confirm_pre_checks.accepted' => 'You must confirm that pre-maintenance checks have been completed.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please confirm pre-maintenance checks.');
        }

        DB::beginTransaction();

        try {
            $maintenance->status = Maintenance::STATUS_IN_PROGRESS;
            $maintenance->actual_start = now();
            $maintenance->updated_by = Auth::id();
            $maintenance->save();

            // Create start log
            $maintenance->logs()->create([
                'action' => 'started',
                'details' => 'Maintenance started',
                'metadata' => [
                    'notes' => $request->start_notes,
                    'actual_start' => now()->toDateTimeString(),
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance started successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to start maintenance', [
                'maintenance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to start maintenance.');
        }
    }

    /**
     * Complete maintenance
     */
    public function complete($id, Request $request)
    {
        $maintenance = Maintenance::findOrFail($id);

        if ($maintenance->status !== Maintenance::STATUS_IN_PROGRESS) {
            return redirect()->back()
                ->with('warning', 'Only maintenance in progress can be completed.');
        }

        $validator = Validator::make($request->all(), [
            'completion_notes' => 'required|string|min:10',
            'post_checks_passed' => 'required|accepted',
            'estimated_vs_actual' => 'required|in:within_estimate,under_estimate,over_estimate',
            'actual_duration_minutes' => 'required|integer|min:1',
            'actual_affected_users' => 'required|integer|min:0',
            'downtime_minutes' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide all completion details.');
        }

        DB::beginTransaction();

        try {
            $maintenance->status = Maintenance::STATUS_COMPLETED;
            $maintenance->actual_end = now();
            $maintenance->completed_by = Auth::id();
            $maintenance->completed_at = now();
            $maintenance->actual_duration_minutes = $request->actual_duration_minutes;
            $maintenance->actual_affected_users = $request->actual_affected_users;
            $maintenance->downtime_minutes = $request->downtime_minutes;
            
            // Calculate if completed within estimate
            $estimated = $maintenance->estimated_duration_minutes;
            $actual = $request->actual_duration_minutes;
            $maintenance->completed_within_estimate = abs($estimated - $actual) <= ($estimated * 0.1); // Within 10%
            
            // Store post-maintenance checks result
            $maintenance->all_checks_passed = true;
            $maintenance->checks_completed_at = now();
            
            $maintenance->save();

            // Create completion log
            $maintenance->logs()->create([
                'action' => 'completed',
                'details' => 'Maintenance completed successfully',
                'metadata' => [
                    'notes' => $request->completion_notes,
                    'actual_duration' => $request->actual_duration_minutes,
                    'affected_users' => $request->actual_affected_users,
                    'downtime' => $request->downtime_minutes,
                    'completed_within_estimate' => $maintenance->completed_within_estimate,
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send completion notifications
            if ($maintenance->notify_users) {
                dispatch(function () use ($maintenance) {
                    $this->notifyUsersOfCompletion($maintenance);
                })->afterResponse();
            }

            DB::commit();

            return redirect()->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance completed successfully. Notifications sent to users.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to complete maintenance', [
                'maintenance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to complete maintenance.');
        }
    }

    /**
     * Cancel maintenance
     */
    public function cancel($id, Request $request)
    {
        $maintenance = Maintenance::findOrFail($id);

        if (!in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED])) {
            return redirect()->back()
                ->with('warning', 'Only draft or scheduled maintenance can be cancelled.');
        }

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide a cancellation reason.');
        }

        try {
            $previousStatus = $maintenance->status;
            
            $maintenance->status = Maintenance::STATUS_CANCELLED;
            $maintenance->cancelled_by = Auth::id();
            $maintenance->cancelled_at = now();
            $maintenance->cancellation_reason = $request->cancellation_reason;
            $maintenance->save();

            // Create cancellation log
            $maintenance->logs()->create([
                'action' => 'cancelled',
                'details' => 'Maintenance cancelled',
                'metadata' => [
                    'reason' => $request->cancellation_reason,
                    'previous_status' => $previousStatus,
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send cancellation notifications if was scheduled
            if ($previousStatus === Maintenance::STATUS_SCHEDULED && $maintenance->notify_users) {
                dispatch(function () use ($maintenance) {
                    $this->notifyUsersOfCancellation($maintenance);
                })->afterResponse();
            }

            return redirect()->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance cancelled successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to cancel maintenance', [
                'maintenance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to cancel maintenance.');
        }
    }

    /**
 * Show the form for editing the specified maintenance schedule.
 */
public function edit($id)
{
    $maintenance = Maintenance::with(['creator', 'approver', 'completer'])
        ->findOrFail($id);

    // Check if maintenance can be edited
    if (!in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED])) {
        return redirect()->route('developer.maintenance.show', $maintenance->id)
            ->with('warning', 'Only draft or scheduled maintenance can be edited.');
    }

    $defaultModules = [
        'user_management' => 'User Management',
        'property_management' => 'Property Management',
        'payment_processing' => 'Payment Processing',
        'communication' => 'Communication',
        'reporting' => 'Reporting',
        'api' => 'API Services',
        'dashboard' => 'Dashboard',
        'authentication' => 'Authentication',
        'database' => 'Database',
        'queue' => 'Queue System',
        'storage' => 'File Storage',
        'cache' => 'Cache System',
    ];

    $userTypes = [
        '0' => 'Super Admin',
        '1' => 'Admin',
        '2' => 'Landlord',
        '3' => 'Tenant',
        '4' => 'Field Agent',
        '5' => 'Developer',
        '6' => 'Security Checkpoint',
    ];

    $impactLevels = [
        Maintenance::IMPACT_LOW => 'Low',
        Maintenance::IMPACT_MEDIUM => 'Medium',
        Maintenance::IMPACT_HIGH => 'High',
        Maintenance::IMPACT_CRITICAL => 'Critical',
    ];

    $maintenanceTypes = [
        Maintenance::TYPE_PLANNED => 'Planned Maintenance',
        Maintenance::TYPE_EMERGENCY => 'Emergency Maintenance',
        Maintenance::TYPE_HOTFIX => 'Hotfix',
        Maintenance::TYPE_UPGRADE => 'System Upgrade',
        Maintenance::TYPE_SECURITY => 'Security Patch',
    ];

    // Get active emergencies for linking
    $activeEmergencies = EmergencyMode::active()
        ->select('id', 'name', 'reference_id')
        ->get();

    return view('developer.maintenance.edit', compact(
        'maintenance',
        'defaultModules',
        'userTypes',
        'impactLevels',
        'maintenanceTypes',
        'activeEmergencies'
    ));
}

    /**
     * Update maintenance schedule
     */
    public function update($id, Request $request)
    {
        $maintenance = Maintenance::findOrFail($id);

        if (!in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED])) {
            return redirect()->back()
                ->with('warning', 'Only draft or scheduled maintenance can be updated.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'technical_details' => 'nullable|string',
            'user_impact_description' => 'required|string|min:20',
            'scheduled_start' => 'required|date',
            'scheduled_end' => 'required|date|after:scheduled_start',
            'estimated_duration_minutes' => 'required|integer|min:1|max:1440',
            'update_notes' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the validation errors.');
        }

        try {
            $oldData = [
                'scheduled_start' => $maintenance->scheduled_start,
                'scheduled_end' => $maintenance->scheduled_end,
                'title' => $maintenance->title,
            ];
            
            $maintenance->title = $request->title;
            $maintenance->description = $request->description;
            $maintenance->technical_details = $request->technical_details;
            $maintenance->user_impact_description = $request->user_impact_description;
            $maintenance->scheduled_start = $request->scheduled_start;
            $maintenance->scheduled_end = $request->scheduled_end;
            $maintenance->estimated_duration_minutes = $request->estimated_duration_minutes;
            $maintenance->updated_by = Auth::id();
            $maintenance->save();

            // Create update log
            $maintenance->logs()->create([
                'action' => 'updated',
                'details' => 'Maintenance schedule updated',
                'metadata' => [
                    'notes' => $request->update_notes,
                    'changes' => [
                        'old' => $oldData,
                        'new' => [
                            'scheduled_start' => $maintenance->scheduled_start,
                            'scheduled_end' => $maintenance->scheduled_end,
                            'title' => $maintenance->title,
                        ],
                    ],
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send update notifications if scheduled
            if ($maintenance->status === Maintenance::STATUS_SCHEDULED && $maintenance->notify_users) {
                dispatch(function () use ($maintenance) {
                    $this->notifyUsersOfUpdate($maintenance);
                })->afterResponse();
            }

            return redirect()->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance schedule updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update maintenance schedule', [
                'maintenance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update maintenance schedule.');
        }
    }

    /**
     * Get maintenance history
     */
    public function history($id)
    {
        $maintenance = Maintenance::findOrFail($id);
        $logs = $maintenance->logs()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('developer.maintenance.history', compact('maintenance', 'logs'));
    }

   /**
 * Get maintenance statistics
 */
public function statistics()
{
    $statistics = Maintenance::getStatistics();
    $trends = Maintenance::getTrends();
    
    // Additional statistics
    $recentMaintenances = Maintenance::orderBy('created_at', 'desc')
        ->take(10)
        ->get();
        
    $impactDistribution = Maintenance::select('impact_level')
        ->selectRaw('COUNT(*) as count')
        ->groupBy('impact_level')
        ->get()
        ->pluck('count', 'impact_level');
        
    $typeDistribution = Maintenance::select('maintenance_type')
        ->selectRaw('COUNT(*) as count')
        ->groupBy('maintenance_type')
        ->get()
        ->pluck('count', 'maintenance_type');

    // Status distribution
    $statusDistribution = Maintenance::select('status')
        ->selectRaw('COUNT(*) as count')
        ->groupBy('status')
        ->get()
        ->pluck('count', 'status');

    // Get trash statistics
    $trashCount = Maintenance::onlyTrashed()->count();
    $oldestTrashItem = Maintenance::onlyTrashed()->orderBy('deleted_at')->first();
    
    // Get trash by user (for non-null deleted_by)
    $trashByUser = \DB::table('maintenances')
        ->select('deleted_by', \DB::raw('COUNT(*) as count'))
        ->whereNotNull('deleted_at')
        ->whereNotNull('deleted_by')
        ->groupBy('deleted_by')
        ->get();

    // Calculate restored count - simplified version
    $restoredCount = 0;
    
    // Try to get from logs first
    if (class_exists('App\Models\MaintenanceLog')) {
        $restoredCount = \App\Models\MaintenanceLog::where('action', 'restored')->count();
    }
    
    // Fallback calculation
    if ($restoredCount === 0) {
        $totalTrashedEver = Maintenance::withTrashed()
            ->whereNotNull('deleted_at')
            ->count();
        $restoredCount = max(0, $totalTrashedEver - $trashCount);
    }

    return view('developer.maintenance.statistics', compact(
        'statistics',
        'trends',
        'recentMaintenances',
        'impactDistribution',
        'typeDistribution',
        'statusDistribution',
        'trashCount',
        'oldestTrashItem',
        'trashByUser',
        'restoredCount' // Now defined
    ));
}

    /**
     * API: Get upcoming maintenance
     */
    public function apiUpcoming(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);
            
            $upcoming = Maintenance::upcoming()
                ->orderBy('scheduled_start', 'asc')
                ->limit($limit)
                ->get()
                ->map(function ($maintenance) {
                    return [
                        'id' => $maintenance->id,
                        'reference_id' => $maintenance->reference_id,
                        'title' => $maintenance->title,
                        'description' => $maintenance->user_impact_description,
                        'type' => $maintenance->maintenance_type,
                        'impact_level' => $maintenance->impact_level,
                        'scheduled_start' => $maintenance->scheduled_start->toISOString(),
                        'scheduled_end' => $maintenance->scheduled_end->toISOString(),
                        'time_until_start' => $maintenance->time_until_start,
                        'affected_modules' => $maintenance->affected_modules_list,
                        'estimated_duration_minutes' => $maintenance->estimated_duration_minutes,
                        'is_emergency' => $maintenance->is_emergency,
                        'notify_users' => $maintenance->notify_users,
                    ];
                });

            return response()->json([
                'success' => true,
                'upcoming_maintenance' => $upcoming,
                'count' => $upcoming->count(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch upcoming maintenance', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upcoming maintenance',
            ], 500);
        }
    }

    /**
     * API: Get active maintenance
     */
    public function apiActive(Request $request)
    {
        try {
            $active = Maintenance::active()
                ->orderBy('actual_start', 'asc')
                ->get()
                ->map(function ($maintenance) {
                    return [
                        'id' => $maintenance->id,
                        'reference_id' => $maintenance->reference_id,
                        'title' => $maintenance->title,
                        'description' => $maintenance->user_impact_description,
                        'type' => $maintenance->maintenance_type,
                        'impact_level' => $maintenance->impact_level,
                        'actual_start' => $maintenance->actual_start->toISOString(),
                        'current_duration' => $maintenance->current_duration,
                        'affected_modules' => $maintenance->affected_modules_list,
                        'estimated_duration_minutes' => $maintenance->estimated_duration_minutes,
                        'is_overdue' => $maintenance->is_overdue,
                        'allowed_operations' => $maintenance->allowed_operations,
                    ];
                });

            return response()->json([
                'success' => true,
                'active_maintenance' => $active,
                'count' => $active->count(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch active maintenance', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active maintenance',
            ], 500);
        }
    }

    /**
     * API: Check if module is under maintenance
     */
    public function apiCheckModule(Request $request, $module)
    {
        try {
            // Check active maintenance affecting this module
            $activeMaintenance = Maintenance::active()
                ->whereJsonContains('affected_modules', $module)
                ->first();

            // Check upcoming maintenance (starting within 1 hour)
            $upcomingMaintenance = Maintenance::upcoming()
                ->whereJsonContains('affected_modules', $module)
                ->where('scheduled_start', '<=', now()->addHour())
                ->first();
            
            $isUnderMaintenance = !is_null($activeMaintenance);
            $isMaintenanceImminent = !is_null($upcomingMaintenance);
            
            return response()->json([
                'success' => true,
                'module' => $module,
                'is_under_maintenance' => $isUnderMaintenance,
                'is_maintenance_imminent' => $isMaintenanceImminent,
                'active_maintenance' => $isUnderMaintenance ? [
                    'id' => $activeMaintenance->id,
                    'title' => $activeMaintenance->title,
                    'started_at' => $activeMaintenance->actual_start->toISOString(),
                    'current_duration_minutes' => $activeMaintenance->current_duration,
                    'allowed_operations' => $activeMaintenance->allowed_operations,
                ] : null,
                'upcoming_maintenance' => $isMaintenanceImminent ? [
                    'id' => $upcomingMaintenance->id,
                    'title' => $upcomingMaintenance->title,
                    'scheduled_start' => $upcomingMaintenance->scheduled_start->toISOString(),
                    'time_until_start' => $upcomingMaintenance->time_until_start,
                ] : null,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check module maintenance status', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check module status',
            ], 500);
        }
    }

    /**
 * Remove the specified maintenance schedule from storage.
 */
public function destroy($id)
{
    $maintenance = Maintenance::findOrFail($id);

    // Check if maintenance can be deleted
    if ($maintenance->status !== Maintenance::STATUS_DRAFT) {
        return redirect()->back()
            ->with('warning', 'Only draft maintenance schedules can be deleted.');
    }

    try {
        $maintenance->delete();
        
        return redirect()->route('developer.maintenance.index')
            ->with('success', 'Maintenance schedule deleted successfully.');
            
    } catch (\Exception $e) {
        Log::error('Failed to delete maintenance schedule', [
            'maintenance_id' => $id,
            'error' => $e->getMessage(),
        ]);

        return redirect()->back()
            ->with('error', 'Failed to delete maintenance schedule.');
    }
}

/**
 * View deleted maintenance schedules
 */
public function trash(Request $request)
{
    $query = Maintenance::onlyTrashed()
        ->with(['creator', 'deletedBy'])
        ->orderBy('deleted_at', 'desc');

    // Filters
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('reference_id', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    if ($request->filled('deleted_by')) {
        $query->where('deleted_by', $request->deleted_by);
    }

    if ($request->filled('deleted_from')) {
        $query->whereDate('deleted_at', '>=', $request->deleted_from);
    }

    if ($request->filled('deleted_to')) {
        $query->whereDate('deleted_at', '<=', $request->deleted_to);
    }

    $trashedMaintenances = $query->paginate(20);
    
    // Get users who have deleted maintenance
    $deletedByUsers = User::whereIn('id', 
        Maintenance::onlyTrashed()->pluck('deleted_by')->filter()->unique()
    )->get();
    
    // Get oldest item for statistics
    $oldestItem = Maintenance::onlyTrashed()->orderBy('deleted_at')->first();
    
    // Count of restored items (you might need to track this separately)
    $restoredCount = 0; // Implement based on your tracking system

    return view('developer.maintenance.trash', compact(
        'trashedMaintenances',
        'deletedByUsers',
        'oldestItem',
        'restoredCount'
    ));
}

/**
 * Restore a deleted maintenance schedule
 */
public function restore($id)
{
    $maintenance = Maintenance::onlyTrashed()->findOrFail($id);
    
    try {
        $maintenance->restore();
        
        // Update status to draft when restoring
        $maintenance->update([
            'status' => Maintenance::STATUS_DRAFT,
            'restored_at' => now(),
            'restored_by' => Auth::id(),
        ]);
        
        // Log the restoration
        $maintenance->logs()->create([
            'action' => 'restored',
            'details' => 'Maintenance schedule restored from trash',
            'performed_by' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        return redirect()->route('developer.maintenance.trash')
            ->with('success', 'Maintenance schedule restored successfully.');
            
    } catch (\Exception $e) {
        Log::error('Failed to restore maintenance schedule', [
            'maintenance_id' => $id,
            'error' => $e->getMessage(),
        ]);
        
        return redirect()->back()
            ->with('error', 'Failed to restore maintenance schedule.');
    }
}

/**
 * Permanently delete a maintenance schedule
 */
public function forceDelete($id)
{
    $maintenance = Maintenance::onlyTrashed()->findOrFail($id);
    
    try {
        // Store reference for logging
        $referenceId = $maintenance->reference_id;
        
        // Delete associated records
        $maintenance->logs()->delete();
        $maintenance->affectedUsers()->delete();
        
        // Permanently delete the maintenance
        $maintenance->forceDelete();
        
        // Log the permanent deletion
        Log::info('Maintenance schedule permanently deleted', [
            'reference_id' => $referenceId,
            'deleted_by' => Auth::id(),
            'deleted_at' => now()->toDateTimeString(),
        ]);
        
        return redirect()->route('developer.maintenance.trash')
            ->with('success', 'Maintenance schedule permanently deleted.');
            
    } catch (\Exception $e) {
        Log::error('Failed to permanently delete maintenance schedule', [
            'maintenance_id' => $id,
            'error' => $e->getMessage(),
        ]);
        
        return redirect()->back()
            ->with('error', 'Failed to permanently delete maintenance schedule.');
    }
}

/**
 * Bulk restore maintenance schedules
 */
public function bulkRestore(Request $request)
{
    $request->validate([
        'items' => 'required|array',
        'items.*' => 'exists:maintenances,id',
    ]);
    
    $restoredCount = 0;
    
    foreach ($request->items as $id) {
        try {
            $maintenance = Maintenance::onlyTrashed()->find($id);
            
            if ($maintenance) {
                $maintenance->restore();
                $maintenance->update([
                    'status' => Maintenance::STATUS_DRAFT,
                    'restored_at' => now(),
                    'restored_by' => Auth::id(),
                ]);
                
                $restoredCount++;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to restore maintenance during bulk operation', [
                'maintenance_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    return redirect()->route('developer.maintenance.trash')
        ->with('success', "{$restoredCount} maintenance schedule(s) restored successfully.");
}

/**
 * Bulk force delete maintenance schedules
 */
public function bulkForceDelete(Request $request)
{
    $request->validate([
        'items' => 'required|array',
        'items.*' => 'exists:maintenances,id',
    ]);
    
    $deletedCount = 0;
    
    foreach ($request->items as $id) {
        try {
            $maintenance = Maintenance::onlyTrashed()->find($id);
            
            if ($maintenance) {
                // Store reference for logging
                $referenceId = $maintenance->reference_id;
                
                // Delete associated records
                $maintenance->logs()->delete();
                $maintenance->affectedUsers()->delete();
                
                // Permanently delete
                $maintenance->forceDelete();
                
                Log::info('Maintenance schedule permanently deleted (bulk)', [
                    'reference_id' => $referenceId,
                    'deleted_by' => Auth::id(),
                ]);
                
                $deletedCount++;
            }
        } catch (\Exception $e) {
            Log::error('Failed to permanently delete maintenance during bulk operation', [
                'maintenance_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    return redirect()->route('developer.maintenance.trash')
        ->with('success', "{$deletedCount} maintenance schedule(s) permanently deleted.");
}

/**
 * Empty the trash
 */
public function emptyTrash(Request $request)
{
    try {
        $maintenances = Maintenance::onlyTrashed()->get();
        $count = $maintenances->count();
        
        foreach ($maintenances as $maintenance) {
            try {
                // Delete associated records
                $maintenance->logs()->delete();
                $maintenance->affectedUsers()->delete();
                
                // Permanently delete
                $maintenance->forceDelete();
            } catch (\Exception $e) {
                Log::warning('Failed to delete maintenance during empty trash operation', [
                    'maintenance_id' => $maintenance->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        Log::info('Trash emptied', [
            'count' => $count,
            'emptied_by' => Auth::id(),
        ]);
        
        return redirect()->route('developer.maintenance.trash')
            ->with('success', "Trash emptied successfully. {$count} item(s) permanently deleted.");
            
    } catch (\Exception $e) {
        Log::error('Failed to empty trash', [
            'error' => $e->getMessage(),
        ]);
        
        return redirect()->back()
            ->with('error', 'Failed to empty trash.');
    }
}

/**
 * Get maintenance details for trash view (AJAX)
 */
public function trashDetails($id)
{
    try {
        $maintenance = Maintenance::onlyTrashed()
            ->with(['creator', 'approver', 'completer', 'deletedBy'])
            ->findOrFail($id);
        
        $html = view('developer.maintenance.partials.trash-details', compact('maintenance'))->render();
        
        return response()->json([
            'success' => true,
            'html' => $html,
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to fetch maintenance details for trash', [
            'maintenance_id' => $id,
            'error' => $e->getMessage(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to load details',
        ], 500);
    }
}

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Get allowed operations based on impact level
     */
    private function getAllowedOperationsForImpact(string $impactLevel): array
    {
        $operations = [
            'view_dashboard',
            'read_profile',
            'basic_read',
        ];

        switch ($impactLevel) {
            case 'low':
                $operations = array_merge($operations, [
                    'create_records',
                    'update_records',
                    'delete_records',
                    'process_payments',
                    'send_messages',
                ]);
                break;
                
            case 'medium':
                $operations = array_merge($operations, [
                    'create_records',
                    'update_records',
                    'read_reports',
                ]);
                break;
                
            case 'high':
                $operations = array_merge($operations, [
                    'read_only',
                ]);
                break;
                
            case 'critical':
                $operations = [
                    'emergency_read',
                    'system_status',
                ];
                break;
        }

        return $operations;
    }

    /**
     * Calculate estimated affected users
     */
    private function calculateEstimatedAffectedUsers(array $userTypes): int
    {
        $total = 0;
        
        foreach ($userTypes as $type) {
            $count = User::where('type', $type)
                ->where('status', 'active')
                ->count();
            $total += $count;
        }
        
        return $total;
    }

    /**
     * Calculate maintenance impact metrics
     */
    private function calculateMaintenanceImpact(Maintenance $maintenance): array
    {
        $affectedUsers = $maintenance->affectedUsers()->count();
        $acknowledgedUsers = $maintenance->affectedUsers()->where('acknowledged', true)->count();
        
        // Calculate actual vs estimated
        $estimated = $maintenance->estimated_affected_users;
        $actual = $maintenance->actual_affected_users;
        $accuracy = $estimated > 0 ? min(100, ($actual / $estimated) * 100) : 0;
        
        // Calculate notification metrics
        $notifiedUsers = $maintenance->affectedUsers()->whereNotNull('notified_at')->count();
        $notificationRate = $affectedUsers > 0 ? ($notifiedUsers / $affectedUsers) * 100 : 0;
        
        // Calculate acknowledgment rate
        $acknowledgmentRate = $notifiedUsers > 0 ? ($acknowledgedUsers / $notifiedUsers) * 100 : 0;
        
        // Calculate duration metrics
        $durationMetrics = [];
        if ($maintenance->status === Maintenance::STATUS_COMPLETED) {
            $estimatedDuration = $maintenance->estimated_duration_minutes;
            $actualDuration = $maintenance->actual_duration_minutes;
            $durationAccuracy = $estimatedDuration > 0 
                ? min(100, ($actualDuration / $estimatedDuration) * 100) 
                : 100;
                
            $durationMetrics = [
                'estimated_minutes' => $estimatedDuration,
                'actual_minutes' => $actualDuration,
                'accuracy_percentage' => round($durationAccuracy, 1),
                'within_estimate' => $maintenance->completed_within_estimate,
                'downtime_minutes' => $maintenance->downtime_minutes,
            ];
        }

        return [
            'user_impact' => [
                'estimated_users' => $estimated,
                'actual_users' => $actual,
                'accuracy_percentage' => round($accuracy, 1),
                'affected_users_count' => $affectedUsers,
                'notified_users_count' => $notifiedUsers,
                'notification_rate' => round($notificationRate, 1),
                'acknowledged_users_count' => $acknowledgedUsers,
                'acknowledgment_rate' => round($acknowledgmentRate, 1),
            ],
            'duration_metrics' => $durationMetrics,
            'financial_impact' => $this->estimateFinancialImpact($maintenance),
        ];
    }

    /**
     * Estimate financial impact
     */
    private function estimateFinancialImpact(Maintenance $maintenance): array
    {
        // Very simplified financial impact estimation
        // In a real system, this would use actual business metrics
        
        $affectedUsers = $maintenance->actual_affected_users ?: $maintenance->estimated_affected_users;
        $downtimeHours = $maintenance->downtime_minutes ? $maintenance->downtime_minutes / 60 : 0;
        
        // Base impact per user per hour (adjust based on your business)
        $baseImpactPerUserPerHour = 0.50; // Example: $0.50 per user per hour
        
        $estimatedCost = $affectedUsers * $downtimeHours * $baseImpactPerUserPerHour;
        
        // Adjust based on impact level
        $impactMultiplier = match($maintenance->impact_level) {
            'critical' => 2.0,
            'high' => 1.5,
            'medium' => 1.2,
            'low' => 1.0,
            default => 1.0,
        };
        
        $adjustedCost = $estimatedCost * $impactMultiplier;
        
        return [
            'affected_users' => $affectedUsers,
            'downtime_hours' => round($downtimeHours, 2),
            'base_impact_per_user_hour' => $baseImpactPerUserPerHour,
            'impact_multiplier' => $impactMultiplier,
            'estimated_cost' => round($adjustedCost, 2),
            'impact_level' => match(true) {
                $adjustedCost > 1000 => 'high',
                $adjustedCost > 500 => 'medium',
                $adjustedCost > 100 => 'low',
                default => 'minimal'
            },
        ];
    }

    /**
     * Notify users of scheduled maintenance
     */
    private function notifyUsersOfScheduledMaintenance(Maintenance $maintenance)
    {
        $userTypes = $maintenance->affected_user_types ?? [];
        $channels = $maintenance->notification_channels ?? ['email', 'in_app'];
        
        foreach ($userTypes as $userType) {
            $users = User::where('type', $userType)
                ->where('status', 'active')
                ->get();
            
            foreach ($users as $user) {
                // Record affected user
                MaintenanceAffectedUser::create([
                    'maintenance_id' => $maintenance->id,
                    'user_id' => $user->id,
                    'user_type' => $user->type,
                    'affected_modules' => $maintenance->affected_modules,
                ]);
                
                // Send notifications
                foreach ($channels as $channel) {
                    try {
                        $this->notificationService->sendMaintenanceNotification(
                            $user,
                            $channel,
                            [
                                'maintenance_title' => $maintenance->title,
                                'description' => $maintenance->user_impact_description,
                                'scheduled_start' => $maintenance->scheduled_start->format('Y-m-d H:i:s'),
                                'scheduled_end' => $maintenance->scheduled_end->format('Y-m-d H:i:s'),
                                'estimated_duration' => $maintenance->estimated_duration_minutes . ' minutes',
                                'affected_modules' => $maintenance->affected_modules_list,
                                'impact_level' => $maintenance->impact_level,
                                'type' => $maintenance->maintenance_type,
                                'reference_id' => $maintenance->reference_id,
                                'allowed_operations' => $maintenance->allowed_operations,
                                'contact_support' => true,
                            ]
                        );
                    } catch (\Exception $e) {
                        Log::warning("Failed to send {$channel} notification to user {$user->id}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
        
        // Update maintenance notification timestamp
        $maintenance->update([
            'notification_sent_at' => now(),
        ]);
        
        // Log notification
        $maintenance->logs()->create([
            'action' => 'notified',
            'details' => 'Users notified of scheduled maintenance',
            'metadata' => [
                'channels' => $channels,
                'user_types' => $userTypes,
                'estimated_users' => count($userTypes),
            ],
            'performed_by' => Auth::id() ?? 'system',
        ]);
    }

    /**
     * Notify users of maintenance update
     */
    private function notifyUsersOfUpdate(Maintenance $maintenance)
    {
        // Similar to notifyUsersOfScheduledMaintenance but with update message
        // Implementation would be similar with different message template
    }

    /**
     * Notify users of maintenance completion
     */
    private function notifyUsersOfCompletion(Maintenance $maintenance)
    {
        $affectedUsers = $maintenance->affectedUsers()
            ->with('user')
            ->get();
            
        $channels = $maintenance->notification_channels ?? ['email', 'in_app'];
        
        foreach ($affectedUsers as $affectedUser) {
            foreach ($channels as $channel) {
                try {
                    $this->notificationService->sendMaintenanceCompletionNotification(
                        $affectedUser->user,
                        $channel,
                        [
                            'maintenance_title' => $maintenance->title,
                            'completed_at' => $maintenance->actual_end->format('Y-m-d H:i:s'),
                            'actual_duration' => $maintenance->actual_duration_minutes . ' minutes',
                            'downtime' => $maintenance->downtime_minutes . ' minutes',
                            'summary' => 'Maintenance completed successfully. All systems are now operational.',
                        ]
                    );
                } catch (\Exception $e) {
                    Log::warning("Failed to send completion notification to user {$affectedUser->user_id}", [
                        'channel' => $channel,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Notify users of maintenance cancellation
     */
    private function notifyUsersOfCancellation(Maintenance $maintenance)
    {
        // Similar to notifyUsersOfCompletion but with cancellation message
        // Implementation would be similar with different message template
    }
}