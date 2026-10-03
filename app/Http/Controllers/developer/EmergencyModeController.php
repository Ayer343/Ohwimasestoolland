<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\EmergencyMode;
use App\Models\EmergencyModeLog;
use App\Models\EmergencyAffectedUser;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EmergencyModeController extends Controller
{
    protected $notificationService;
    protected $healthService;
    
    public function __construct(
        NotificationService $notificationService,
        SystemHealthService $healthService
    ) {
        $this->notificationService = $notificationService;
        $this->healthService = $healthService;
        
        // REMOVE THE MIDDLEWARE CALL FROM HERE
        // Middleware should be applied in routes/web.php, not in controller constructor
        // $this->middleware('check.emergency.mode')->except([
        //     'index', 'create', 'store', 'show', 'history', 'statistics'
        // ]);
    }

    /**
     * Display all emergency modes
     */
    public function index(Request $request)
    {
        $query = EmergencyMode::with(['activatedByUser', 'deactivatedByUser'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('severity')) {
            $query->where('severity_level', $request->severity);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $emergencies = $query->paginate(20);
        $statistics = EmergencyMode::getStatistics();

        return view('developer.emergency.index', compact('emergencies', 'statistics'));
    }

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
    ];
    
    // Add module descriptions
    $moduleDescriptions = [
        'user_management' => 'User registration, profile management, and user administration features',
        'property_management' => 'Property listings, management, and related operations',
        'payment_processing' => 'All payment processing and transaction management',
        'communication' => 'Messaging, notifications, and user communication features',
        'reporting' => 'Reports, analytics, and data export features',
        'api' => 'External API services and integrations',
        'dashboard' => 'User dashboard and main interface',
        'authentication' => 'Login, registration, and authentication systems',
        'database' => 'Database operations and data management',
        'queue' => 'Background job processing and queues',
    ];
    
    $severityLevels = [
        EmergencyMode::SEVERITY_LOW => 'Low',
        EmergencyMode::SEVERITY_MEDIUM => 'Medium',
        EmergencyMode::SEVERITY_HIGH => 'High',
        EmergencyMode::SEVERITY_CRITICAL => 'Critical',
    ];

    return view('developer.emergency.create', compact(
        'defaultModules', 
        'severityLevels',
        'moduleDescriptions'
    ));
}

    /**
     * Store a new emergency mode
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reason' => 'required|string|min:10',
            'severity_level' => 'required|in:low,medium,high,critical',
            'affected_modules' => 'required|array|min:1',
            'affected_modules.*' => 'string|in:user_management,property_management,payment_processing,communication,reporting,api,dashboard,authentication,database,queue',
            'restricted_features' => 'nullable|array',
            'allowed_operations' => 'nullable|array',
            'notify_users' => 'boolean',
            'notification_channels' => 'nullable|array',
            'notification_channels.*' => 'in:email,sms,whatsapp,in_app',
            'auto_recovery' => 'boolean',
            'activate_immediately' => 'boolean',
            'schedule_for_later' => 'boolean',
            'scheduled_start' => 'nullable|date|after:now',
            'scheduled_end' => 'nullable|date|after:scheduled_start',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the validation errors.');
        }

        DB::beginTransaction();

        try {
            $activateNow = $request->boolean('activate_immediately');
            $scheduleForLater = $request->boolean('schedule_for_later');
            
            $emergencyMode = new EmergencyMode();
            $emergencyMode->reference_id = EmergencyMode::generateReferenceId();
            $emergencyMode->name = $request->name;
            $emergencyMode->description = $request->description;
            $emergencyMode->reason = $request->reason;
            $emergencyMode->severity_level = $request->severity_level;
            $emergencyMode->affected_modules = $request->affected_modules;
            $emergencyMode->restricted_features = $request->restricted_features ?? [];
            $emergencyMode->allowed_operations = $request->allowed_operations ?? ['view_dashboard', 'basic_read'];
            $emergencyMode->notify_users = $request->boolean('notify_users');
            $emergencyMode->notification_channels = $request->notification_channels ?? ['email', 'in_app'];
            $emergencyMode->auto_recovery = $request->boolean('auto_recovery');
            
            if ($scheduleForLater && $request->scheduled_start) {
                $emergencyMode->scheduled_start = $request->scheduled_start;
                $emergencyMode->scheduled_end = $request->scheduled_end;
                $emergencyMode->status = EmergencyMode::STATUS_INACTIVE;
                $emergencyMode->is_active = false;
            } elseif ($activateNow) {
                $emergencyMode->activated_at = now();
                $emergencyMode->activated_by = Auth::id();
                $emergencyMode->status = EmergencyMode::STATUS_ACTIVATING;
                $emergencyMode->is_active = false; // Will be set to true after activation completes
            } else {
                $emergencyMode->status = EmergencyMode::STATUS_INACTIVE;
                $emergencyMode->is_active = false;
            }

            $emergencyMode->save();

            // Create initial log
            $logData = [
                'action' => 'created',
                'details' => 'Emergency mode configuration created',
                'metadata' => [
                    'activated_immediately' => $activateNow,
                    'scheduled' => $scheduleForLater,
                    'scheduled_start' => $request->scheduled_start,
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];

            $emergencyMode->logs()->create($logData);

            DB::commit();

            $message = 'Emergency mode configuration saved successfully.';
            
            if ($activateNow) {
                $message .= ' Activating emergency mode...';
                // Trigger activation in background
                dispatch(function () use ($emergencyMode) {
                    $this->activateEmergencyMode($emergencyMode);
                })->afterResponse();
            } elseif ($scheduleForLater) {
                $message .= " Scheduled for {$request->scheduled_start}.";
            }

            return redirect()->route('developer.emergency.show', $emergencyMode->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create emergency mode', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create emergency mode: ' . $e->getMessage());
        }
    }

   /**
 * Show emergency mode details
 */
public function show($id)
{
    $emergencyMode = EmergencyMode::with([
        'activatedByUser',
        'deactivatedByUser',
        'logs' => function($query) {
            $query->orderBy('created_at', 'desc')->take(20);
        },
        'affectedUsers' => function($query) {
            $query->with('user')->take(10);
        }
    ])->findOrFail($id);

    $affectedUsersCount = $emergencyMode->affectedUsers()->count();
    
    // Get recent logs - name it consistently
    $recentLogs = $emergencyMode->logs()
        ->orderBy('created_at', 'desc')
        ->take(10)
        ->get();

    // Calculate impact if active
    $impactMetrics = [];
    if ($emergencyMode->isActive()) {
        $impactMetrics = $this->calculateCurrentImpact($emergencyMode);
    }

    return view('developer.emergency.show', compact(
        'emergencyMode',
        'affectedUsersCount',
        'recentLogs', // ✅ Now this variable exists
        'impactMetrics'
    ));
}

    /**
     * Activate emergency mode
     */
    public function activate($id)
    {
        $emergencyMode = EmergencyMode::findOrFail($id);

        if ($emergencyMode->isActive()) {
            return redirect()->back()
                ->with('warning', 'Emergency mode is already active.');
        }

        DB::beginTransaction();

        try {
            $emergencyMode->status = EmergencyMode::STATUS_ACTIVATING;
            $emergencyMode->save();

            // Create activation log
            $emergencyMode->logs()->create([
                'action' => 'activation_started',
                'details' => 'Emergency mode activation initiated',
                'performed_by' => Auth::id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();

            // Trigger activation in background
            dispatch(function () use ($emergencyMode) {
                $this->activateEmergencyMode($emergencyMode);
            })->afterResponse();

            return redirect()->route('developer.emergency.show', $emergencyMode->id)
                ->with('success', 'Emergency mode activation initiated. This may take a few moments.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to start emergency mode activation', [
                'emergency_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to initiate emergency mode activation.');
        }
    }

    /**
     * Actual emergency mode activation logic
     */
    private function activateEmergencyMode(EmergencyMode $emergencyMode)
    {
        DB::beginTransaction();

        try {
            $activationLog = [];
            $startTime = now();

            // Step 1: Prepare activation
            $activationLog[] = [
                'step' => 'preparation',
                'timestamp' => now()->toDateTimeString(),
                'status' => 'started',
            ];

            // Step 2: Apply environment overrides if any
            if ($emergencyMode->environment_overrides) {
                $this->applyEnvironmentOverrides($emergencyMode);
                $activationLog[] = [
                    'step' => 'environment_overrides',
                    'timestamp' => now()->toDateTimeString(),
                    'status' => 'applied',
                ];
            }

            // Step 3: Calculate affected users
            $affectedUsers = $this->calculateAffectedUsers($emergencyMode);
            $emergencyMode->affected_users_count = count($affectedUsers);
            
            $activationLog[] = [
                'step' => 'user_impact_calculation',
                'timestamp' => now()->toDateTimeString(),
                'status' => 'completed',
                'affected_users' => count($affectedUsers),
            ];

            // Step 4: Notify users if enabled
            if ($emergencyMode->notify_users) {
                $this->notifyAffectedUsers($emergencyMode, $affectedUsers);
                $emergencyMode->last_notified_at = now();
                
                $activationLog[] = [
                    'step' => 'user_notification',
                    'timestamp' => now()->toDateTimeString(),
                    'status' => 'sent',
                    'channels' => $emergencyMode->notification_channels,
                ];
            }

            // Step 5: Apply system restrictions
            $this->applySystemRestrictions($emergencyMode);
            
            $activationLog[] = [
                'step' => 'system_restrictions',
                'timestamp' => now()->toDateTimeString(),
                'status' => 'applied',
                'restricted_features' => $emergencyMode->restricted_features,
            ];

            // Step 6: Update emergency mode status
            $emergencyMode->activated_at = now();
            $emergencyMode->status = EmergencyMode::STATUS_ACTIVE;
            $emergencyMode->is_active = true;
            $emergencyMode->activation_log = $activationLog;
            $emergencyMode->save();

            // Create activation complete log
            $emergencyMode->logs()->create([
                'action' => 'activated',
                'details' => 'Emergency mode successfully activated',
                'metadata' => [
                    'duration_seconds' => now()->diffInSeconds($startTime),
                    'affected_users' => count($affectedUsers),
                    'affected_modules' => $emergencyMode->affected_modules,
                ],
                'performed_by' => $emergencyMode->activated_by,
                'ip_address' => request()->ip() ?? 'background_job',
            ]);

            DB::commit();

            // Log successful activation
            Log::info('Emergency mode activated successfully', [
                'emergency_id' => $emergencyMode->id,
                'reference_id' => $emergencyMode->reference_id,
                'affected_users' => count($affectedUsers),
                'activation_time' => now()->diffInSeconds($startTime),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Update emergency mode to failed state
            $emergencyMode->update([
                'status' => EmergencyMode::STATUS_INACTIVE,
                'is_active' => false,
            ]);

            // Log failure
            $emergencyMode->logs()->create([
                'action' => 'activation_failed',
                'details' => 'Emergency mode activation failed: ' . $e->getMessage(),
                'metadata' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
                'performed_by' => $emergencyMode->activated_by,
            ]);

            Log::error('Emergency mode activation failed', [
                'emergency_id' => $emergencyMode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Deactivate emergency mode
     */
    public function deactivate($id, Request $request)
    {
        $emergencyMode = EmergencyMode::findOrFail($id);

        if (!$emergencyMode->isActive()) {
            return redirect()->back()
                ->with('warning', 'Emergency mode is not active.');
        }

        $validator = Validator::make($request->all(), [
            'deactivation_reason' => 'required|string|min:10',
            'confirm' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide a valid deactivation reason.');
        }

        DB::beginTransaction();

        try {
            $emergencyMode->status = EmergencyMode::STATUS_DEACTIVATING;
            $emergencyMode->deactivation_reason = $request->deactivation_reason;
            $emergencyMode->save();

            // Create deactivation log
            $emergencyMode->logs()->create([
                'action' => 'deactivation_started',
                'details' => 'Emergency mode deactivation initiated',
                'metadata' => [
                    'reason' => $request->deactivation_reason,
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            // Trigger deactivation in background
            dispatch(function () use ($emergencyMode, $request) {
                $this->deactivateEmergencyMode($emergencyMode, $request->deactivation_reason);
            })->afterResponse();

            return redirect()->route('developer.emergency.show', $emergencyMode->id)
                ->with('success', 'Emergency mode deactivation initiated. This may take a few moments.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to start emergency mode deactivation', [
                'emergency_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to initiate emergency mode deactivation.');
        }
    }

    /**
     * Actual emergency mode deactivation logic
     */
    private function deactivateEmergencyMode(EmergencyMode $emergencyMode, string $reason)
    {
        DB::beginTransaction();

        try {
            $deactivationLog = [];
            $startTime = now();

            // Step 1: Prepare deactivation
            $deactivationLog[] = [
                'step' => 'preparation',
                'timestamp' => now()->toDateTimeString(),
                'status' => 'started',
            ];

            // Step 2: Run recovery checks if auto-recovery is enabled
            if ($emergencyMode->auto_recovery) {
                $emergencyMode->status = EmergencyMode::STATUS_RECOVERING;
                $emergencyMode->recovery_started_at = now();
                $emergencyMode->save();

                $recoveryResults = $this->runRecoveryChecks($emergencyMode);
                $deactivationLog[] = [
                    'step' => 'recovery_checks',
                    'timestamp' => now()->toDateTimeString(),
                    'status' => $recoveryResults['success'] ? 'passed' : 'failed',
                    'results' => $recoveryResults,
                ];
            }

            // Step 3: Remove system restrictions
            $this->removeSystemRestrictions($emergencyMode);
            
            $deactivationLog[] = [
                'step' => 'system_restrictions',
                'timestamp' => now()->toDateTimeString(),
                'status' => 'removed',
            ];

            // Step 4: Revert environment overrides
            if ($emergencyMode->environment_overrides) {
                $this->revertEnvironmentOverrides($emergencyMode);
                $deactivationLog[] = [
                    'step' => 'environment_overrides',
                    'timestamp' => now()->toDateTimeString(),
                    'status' => 'reverted',
                ];
            }

            // Step 5: Update emergency mode status
            $emergencyMode->deactivated_at = now();
            $emergencyMode->deactivated_by = Auth::id();
            $emergencyMode->status = EmergencyMode::STATUS_INACTIVE;
            $emergencyMode->is_active = false;
            $emergencyMode->duration_minutes = $emergencyMode->current_duration;
            
            if ($emergencyMode->auto_recovery) {
                $emergencyMode->recovery_completed_at = now();
            }
            
            $emergencyMode->deactivation_log = $deactivationLog;
            $emergencyMode->save();

            // Step 6: Notify users of deactivation
            if ($emergencyMode->notify_users) {
                $this->notifyDeactivation($emergencyMode);
                
                $deactivationLog[] = [
                    'step' => 'deactivation_notification',
                    'timestamp' => now()->toDateTimeString(),
                    'status' => 'sent',
                ];
            }

            // Create deactivation complete log
            $emergencyMode->logs()->create([
                'action' => 'deactivated',
                'details' => 'Emergency mode successfully deactivated',
                'metadata' => [
                    'reason' => $reason,
                    'duration_minutes' => $emergencyMode->duration_minutes,
                    'deactivation_time_seconds' => now()->diffInSeconds($startTime),
                    'auto_recovery' => $emergencyMode->auto_recovery,
                ],
                'performed_by' => Auth::id(),
                'ip_address' => request()->ip() ?? 'background_job',
            ]);

            DB::commit();

            // Log successful deactivation
            Log::info('Emergency mode deactivated successfully', [
                'emergency_id' => $emergencyMode->id,
                'reference_id' => $emergencyMode->reference_id,
                'duration_minutes' => $emergencyMode->duration_minutes,
                'deactivation_time' => now()->diffInSeconds($startTime),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Update emergency mode to indicate deactivation failure
            $emergencyMode->update([
                'status' => EmergencyMode::STATUS_ACTIVE, // Keep it active if deactivation failed
            ]);

            // Log failure
            $emergencyMode->logs()->create([
                'action' => 'deactivation_failed',
                'details' => 'Emergency mode deactivation failed: ' . $e->getMessage(),
                'metadata' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
                'performed_by' => Auth::id(),
            ]);

            Log::error('Emergency mode deactivation failed', [
                'emergency_id' => $emergencyMode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Extend emergency mode duration
     */
    public function extend($id, Request $request)
    {
        $emergencyMode = EmergencyMode::findOrFail($id);

        if (!$emergencyMode->isActive()) {
            return redirect()->back()
                ->with('warning', 'Cannot extend inactive emergency mode.');
        }

        $validator = Validator::make($request->all(), [
            'extension_minutes' => 'required|integer|min:15|max:1440', // 1 minute to 24 hours
            'extension_reason' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide valid extension details.');
        }

        try {
            $emergencyMode->last_extended_at = now();
            $emergencyMode->extensions_count += 1;
            
            // Store extension in settings
            $settings = $emergencyMode->settings ?? [];
            $extensions = $settings['extensions'] ?? [];
            $extensions[] = [
                'timestamp' => now()->toDateTimeString(),
                'minutes' => $request->extension_minutes,
                'reason' => $request->extension_reason,
                'extended_by' => Auth::id(),
            ];
            
            $settings['extensions'] = $extensions;
            $emergencyMode->settings = $settings;
            $emergencyMode->save();

            // Create extension log
            $emergencyMode->logs()->create([
                'action' => 'extended',
                'details' => 'Emergency mode duration extended',
                'metadata' => [
                    'minutes_added' => $request->extension_minutes,
                    'reason' => $request->extension_reason,
                    'total_extensions' => $emergencyMode->extensions_count,
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->back()
                ->with('success', "Emergency mode extended by {$request->extension_minutes} minutes.");

        } catch (\Exception $e) {
            Log::error('Failed to extend emergency mode', [
                'emergency_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to extend emergency mode.');
        }
    }

    /**
     * Cancel scheduled emergency mode
     */
    public function cancel($id, Request $request)
    {
        $emergencyMode = EmergencyMode::findOrFail($id);

        if (!$emergencyMode->isScheduled()) {
            return redirect()->back()
                ->with('warning', 'Only scheduled emergency modes can be cancelled.');
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
            $emergencyMode->scheduled_start = null;
            $emergencyMode->scheduled_end = null;
            $emergencyMode->save();

            // Create cancellation log
            $emergencyMode->logs()->create([
                'action' => 'cancelled',
                'details' => 'Scheduled emergency mode cancelled',
                'metadata' => [
                    'reason' => $request->cancellation_reason,
                    'original_schedule' => [
                        'start' => $emergencyMode->getOriginal('scheduled_start'),
                        'end' => $emergencyMode->getOriginal('scheduled_end'),
                    ],
                ],
                'performed_by' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('developer.emergency.show', $emergencyMode->id)
                ->with('success', 'Scheduled emergency mode cancelled successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to cancel scheduled emergency mode', [
                'emergency_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to cancel scheduled emergency mode.');
        }
    }

    /**
 * Get emergency mode history
 */
public function history($id)
{
    $emergencyMode = EmergencyMode::with(['activatedByUser', 'deactivatedByUser'])->findOrFail($id);
    
    // Start building query
    $query = $emergencyMode->logs()->with(['performer' => function($q) {
        $q->select('id', 'name', 'email', 'profile_photo_path');
    }]);
    
    // Apply filters if present
    if (request()->has('action') && request()->action != '') {
        $query->where('action', request()->action);
    }
    
    if (request()->has('user') && request()->user != '') {
        $query->where('performed_by', request()->user);
    }
    
    if (request()->has('date_from') && request()->date_from != '') {
        $query->whereDate('created_at', '>=', request()->date_from);
    }
    
    // Get logs with pagination
    $logs = $query->orderBy('created_at', 'desc')->paginate(50);

    return view('developer.emergency.history', compact('emergencyMode', 'logs'));
}

    /**
     * Get emergency mode statistics
     */
    public function statistics()
    {
        $statistics = EmergencyMode::getStatistics();
        
        // Additional statistics
        $recentEmergencies = EmergencyMode::orderBy('created_at', 'desc')
            ->take(10)
            ->get();
            
        $severityDistribution = EmergencyMode::select('severity_level')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('severity_level')
            ->get()
            ->pluck('count', 'severity_level');
            
        $monthlyTrend = EmergencyMode::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->take(12)
            ->get();

        return view('developer.emergency.statistics', compact(
            'statistics',
            'recentEmergencies',
            'severityDistribution',
            'monthlyTrend'
        ));
    }

    /**
     * API: Get active emergency modes
     */
    public function apiActive(Request $request)
    {
        try {
            $activeEmergencies = EmergencyMode::active()
                ->with('activatedByUser')
                ->get()
                ->map(function ($emergency) {
                    return [
                        'id' => $emergency->id,
                        'reference_id' => $emergency->reference_id,
                        'name' => $emergency->name,
                        'reason' => $emergency->reason,
                        'severity_level' => $emergency->severity_level,
                        'activated_at' => $emergency->activated_at?->toISOString(),
                        'duration_minutes' => $emergency->current_duration,
                        'formatted_duration' => $emergency->formatted_duration,
                        'affected_modules' => $emergency->affected_modules_list,
                        'affected_users_count' => $emergency->affected_users_count,
                        'activated_by' => $emergency->activatedByUser?->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'active_emergencies' => $activeEmergencies,
                'count' => $activeEmergencies->count(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch active emergencies', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active emergencies',
            ], 500);
        }
    }

    /**
     * API: Check if emergency mode affects a specific module
     */
    public function apiCheckModule(Request $request, $module)
    {
        try {
            $activeEmergency = EmergencyMode::active()
                ->whereJsonContains('affected_modules', $module)
                ->first();

            $isAffected = !is_null($activeEmergency);
            
            return response()->json([
                'success' => true,
                'module' => $module,
                'is_affected' => $isAffected,
                'emergency' => $isAffected ? [
                    'id' => $activeEmergency->id,
                    'name' => $activeEmergency->name,
                    'severity' => $activeEmergency->severity_level,
                    'restricted_features' => $activeEmergency->restricted_features,
                    'allowed_operations' => $activeEmergency->allowed_operations,
                ] : null,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check module emergency status', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check module status',
            ], 500);
        }
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Calculate affected users for emergency mode
     */
    private function calculateAffectedUsers(EmergencyMode $emergencyMode): array
    {
        $affectedModules = $emergencyMode->affected_modules ?? [];
        $affectedUsers = [];

        foreach ($affectedModules as $module) {
            switch ($module) {
                case 'user_management':
                    // All active users are affected
                    $users = User::where('status', 'active')->get();
                    break;
                    
                case 'property_management':
                    // Landlords and tenants are affected
                    $users = User::whereIn('type', [2, 3])->where('status', 'active')->get();
                    break;
                    
                case 'payment_processing':
                    // Users with pending payments
                    $users = User::whereHas('payments', function($q) {
                        $q->where('status', 'pending');
                    })->where('status', 'active')->get();
                    break;
                    
                case 'communication':
                    // All users who have used communication features recently
                    $users = User::whereHas('notifications')
                        ->orWhereHas('messages')
                        ->where('status', 'active')
                        ->get();
                    break;
                    
                default:
                    $users = collect();
                    break;
            }

            foreach ($users as $user) {
                if (!isset($affectedUsers[$user->id])) {
                    $affectedUsers[$user->id] = [
                        'user' => $user,
                        'affected_features' => [],
                        'user_type' => $user->type,
                    ];
                }
                
                $affectedUsers[$user->id]['affected_features'][] = $module;
            }
        }

        return $affectedUsers;
    }

    /**
     * Notify affected users
     */
    private function notifyAffectedUsers(EmergencyMode $emergencyMode, array $affectedUsers)
    {
        $channels = $emergencyMode->notification_channels ?? ['email', 'in_app'];
        $notificationData = [
            'emergency_name' => $emergencyMode->name,
            'reason' => $emergencyMode->reason,
            'severity' => $emergencyMode->severity_level,
            'affected_modules' => $emergencyMode->affected_modules_list,
            'start_time' => $emergencyMode->activated_at->format('Y-m-d H:i:s'),
            'estimated_duration' => 'Until further notice',
            'allowed_operations' => $emergencyMode->allowed_operations,
            'contact_support' => true,
        ];

        foreach ($affectedUsers as $userId => $data) {
            $user = $data['user'];
            
            // Record affected user
            EmergencyAffectedUser::create([
                'emergency_mode_id' => $emergencyMode->id,
                'user_id' => $user->id,
                'user_type' => $user->type,
                'affected_features' => $data['affected_features'],
            ]);

            // Send notifications
            foreach ($channels as $channel) {
                try {
                    $this->notificationService->sendEmergencyNotification(
                        $user,
                        $channel,
                        $notificationData
                    );
                } catch (\Exception $e) {
                    Log::warning("Failed to send {$channel} notification to user {$user->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Apply system restrictions
     */
    private function applySystemRestrictions(EmergencyMode $emergencyMode)
    {
        $restrictedFeatures = $emergencyMode->restricted_features ?? [];
        
        // Apply restrictions based on features
        foreach ($restrictedFeatures as $feature) {
            switch ($feature) {
                case 'user_registration':
                    // Disable new user registration
                    config(['app.allow_registration' => false]);
                    break;
                    
                case 'property_creation':
                    // Disable new property creation
                    config(['app.allow_property_creation' => false]);
                    break;
                    
                case 'payment_processing':
                    // Disable payment processing
                    config(['app.allow_payments' => false]);
                    break;
                    
                case 'email_notifications':
                    // Disable non-critical email notifications
                    config(['app.send_non_critical_emails' => false]);
                    break;
                    
                case 'api_write_operations':
                    // Disable write operations in API
                    config(['app.api_write_enabled' => false]);
                    break;
            }
        }

        // Cache the restrictions
        cache()->put('emergency_restrictions', $restrictedFeatures, now()->addHours(24));
    }

    /**
     * Remove system restrictions
     */
    private function removeSystemRestrictions(EmergencyMode $emergencyMode)
    {
        $restrictedFeatures = $emergencyMode->restricted_features ?? [];
        
        // Remove restrictions
        foreach ($restrictedFeatures as $feature) {
            switch ($feature) {
                case 'user_registration':
                    config(['app.allow_registration' => true]);
                    break;
                    
                case 'property_creation':
                    config(['app.allow_property_creation' => true]);
                    break;
                    
                case 'payment_processing':
                    config(['app.allow_payments' => true]);
                    break;
                    
                case 'email_notifications':
                    config(['app.send_non_critical_emails' => true]);
                    break;
                    
                case 'api_write_operations':
                    config(['app.api_write_enabled' => true]);
                    break;
            }
        }

        // Clear restrictions cache
        cache()->forget('emergency_restrictions');
    }

    /**
     * Apply environment overrides
     */
    private function applyEnvironmentOverrides(EmergencyMode $emergencyMode)
    {
        $overrides = $emergencyMode->environment_overrides ?? [];
        
        foreach ($overrides as $key => $value) {
            config([$key => $value]);
        }

        // Cache overrides for recovery
        cache()->put('emergency_env_overrides', $overrides, now()->addHours(24));
    }

    /**
     * Revert environment overrides
     */
    private function revertEnvironmentOverrides(EmergencyMode $emergencyMode)
    {
        $overrides = $emergencyMode->environment_overrides ?? [];
        
        foreach ($overrides as $key => $value) {
            // Revert to original value (you might want to store original values)
            config([$key => env($key)]);
        }

        // Clear overrides cache
        cache()->forget('emergency_env_overrides');
    }

    /**
     * Run recovery checks
     */
    private function runRecoveryChecks(EmergencyMode $emergencyMode): array
    {
        $checks = $emergencyMode->recovery_checks ?? ['database', 'services', 'queues'];
        $results = [];
        $allPassed = true;

        foreach ($checks as $check) {
            try {
                $result = $this->performRecoveryCheck($check);
                $results[$check] = [
                    'status' => $result ? 'passed' : 'failed',
                    'message' => $result ? 'Check passed' : 'Check failed',
                ];
                
                if (!$result) {
                    $allPassed = false;
                }
            } catch (\Exception $e) {
                $results[$check] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
                $allPassed = false;
            }
        }

        return [
            'success' => $allPassed,
            'checks' => $results,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    /**
     * Perform individual recovery check
     */
    private function performRecoveryCheck(string $check): bool
    {
        switch ($check) {
            case 'database':
                // Check database connectivity
                try {
                    DB::connection()->getPdo();
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
                
            case 'services':
                // Check critical services
                $health = $this->healthService->getOverallHealth();
                return ($health['score'] ?? 0) > 70;
                
            case 'queues':
                // Check queue workers
                try {
                    $failedJobs = DB::table('failed_jobs')->count();
                    return $failedJobs < 10;
                } catch (\Exception $e) {
                    return false;
                }
                
            case 'storage':
                // Check storage availability
                try {
                    $freeSpace = disk_free_space(storage_path());
                    $totalSpace = disk_total_space(storage_path());
                    $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
                    return $usagePercent < 90;
                } catch (\Exception $e) {
                    return false;
                }
                
            default:
                return true;
        }
    }

    /**
     * Notify users of deactivation
     */
    private function notifyDeactivation(EmergencyMode $emergencyMode)
    {
        $affectedUsers = $emergencyMode->affectedUsers()
            ->with('user')
            ->get();

        $notificationData = [
            'emergency_name' => $emergencyMode->name,
            'deactivation_time' => $emergencyMode->deactivated_at->format('Y-m-d H:i:s'),
            'duration' => $emergencyMode->formatted_duration,
            'summary' => 'Emergency mode has been deactivated. All systems are now operational.',
        ];

        $channels = $emergencyMode->notification_channels ?? ['email', 'in_app'];

        foreach ($affectedUsers as $affectedUser) {
            foreach ($channels as $channel) {
                try {
                    $this->notificationService->sendEmergencyDeactivationNotification(
                        $affectedUser->user,
                        $channel,
                        $notificationData
                    );
                } catch (\Exception $e) {
                    Log::warning("Failed to send deactivation notification to user {$affectedUser->user_id}", [
                        'channel' => $channel,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update notification timestamp
            $affectedUser->update(['notified_at' => now()]);
        }
    }

    /**
     * Calculate current impact
     */
    private function calculateCurrentImpact(EmergencyMode $emergencyMode): array
    {
        $affectedUsers = $emergencyMode->affectedUsers()
            ->with('user')
            ->get();

        $impactByType = [];
        $totalAffected = $affectedUsers->count();

        foreach ($affectedUsers as $affectedUser) {
            $userType = $affectedUser->user_type;
            
            if (!isset($impactByType[$userType])) {
                $impactByType[$userType] = 0;
            }
            
            $impactByType[$userType]++;
        }

        // Calculate percentage of total users affected
        $totalUsers = User::where('status', 'active')->count();
        $affectedPercentage = $totalUsers > 0 ? ($totalAffected / $totalUsers) * 100 : 0;

        return [
            'total_affected' => $totalAffected,
            'total_users' => $totalUsers,
            'affected_percentage' => round($affectedPercentage, 2),
            'by_user_type' => $impactByType,
            'severity_level' => $emergencyMode->severity_level,
            'duration_minutes' => $emergencyMode->current_duration,
            'estimated_impact' => $this->estimateBusinessImpact($emergencyMode, $totalAffected),
        ];
    }

    /**
     * Estimate business impact
     */
    private function estimateBusinessImpact(EmergencyMode $emergencyMode, int $affectedUsers): array
    {
        $severityMultiplier = match($emergencyMode->severity_level) {
            'critical' => 1.5,
            'high' => 1.2,
            'medium' => 1.0,
            'low' => 0.8,
            default => 1.0,
        };

        $durationHours = ($emergencyMode->current_duration ?? 0) / 60;
        
        // Very basic impact estimation
        $estimatedCost = $affectedUsers * $durationHours * $severityMultiplier * 0.5; // Simplified cost model
        
        return [
            'estimated_users_affected' => $affectedUsers,
            'duration_hours' => round($durationHours, 2),
            'severity_multiplier' => $severityMultiplier,
            'estimated_cost_units' => round($estimatedCost, 2),
            'impact_level' => match(true) {
                $estimatedCost > 100 => 'high',
                $estimatedCost > 50 => 'medium',
                default => 'low'
            },
        ];
    }
}