<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAffectedUser;
use App\Models\EmergencyMode;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmergencyModeController extends Controller
{
    /**
     * User types allowed to manage emergency modes.
     */
    public const AUTHORIZED_USER_TYPES = [
        User::TYPE_SUPER_ADMIN,
        User::TYPE_DEVELOPER,
    ];

    /**
     * Module keys the emergency system understands.
     */
    public const MODULES = [
        'user_management', 'property_management', 'payment_processing',
        'communication', 'reporting', 'api', 'dashboard', 'authentication',
        'database', 'queue',
    ];

    /**
     * Notification channels supported.
     */
    public const CHANNELS = ['email', 'sms', 'whatsapp', 'in_app'];

    protected NotificationService $notificationService;
    protected SystemHealthService $healthService;

    public function __construct(
        NotificationService $notificationService,
        SystemHealthService $healthService
    ) {
        $this->notificationService = $notificationService;
        $this->healthService       = $healthService;
    }

    /* ============================================================
     | AUTHORIZATION
     * ============================================================ */

    private function canManageEmergency(): bool
    {
        $user = Auth::user();

        return $user
            && in_array((int) $user->type, self::AUTHORIZED_USER_TYPES, true);
    }

    private function authorizeOrAbort(bool $json = false)
    {
        if ($this->canManageEmergency()) {
            return null;
        }

        Log::warning('Emergency mode access denied', [
            'user_id'   => Auth::id(),
            'user_type' => Auth::user()->type ?? null,
            'ip'        => request()->ip(),
            'path'      => request()->path(),
        ]);

        if ($json) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Developer or Super Admin access required.',
            ], 403);
        }

        abort(403, 'Unauthorized to manage emergency modes.');
    }

    /* ============================================================
     | INDEX / CREATE / SHOW
     * ============================================================ */

    public function index(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $query = EmergencyMode::with(['activatedByUser', 'deactivatedByUser'])
            ->orderByDesc('created_at');

        $query->when($request->filled('status'),   fn ($q) => $q->where('status', $request->status));
        $query->when($request->filled('severity'), fn ($q) => $q->where('severity_level', $request->severity));

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->string('search')->toString();
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('reference_id', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        });

        $emergencies = $query->paginate(20)->withQueryString();
        $statistics  = EmergencyMode::getStatistics();

        return view('developer.emergency.index', compact('emergencies', 'statistics'));
    }

    public function create()
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $defaultModules = [
            'user_management'     => 'User Management',
            'property_management' => 'Property Management',
            'payment_processing'  => 'Payment Processing',
            'communication'       => 'Communication',
            'reporting'           => 'Reporting',
            'api'                 => 'API Services',
            'dashboard'           => 'Dashboard',
            'authentication'      => 'Authentication',
        ];

        $moduleDescriptions = [
            'user_management'     => 'User registration, profile management, and user administration features',
            'property_management' => 'Property listings, management, and related operations',
            'payment_processing'  => 'All payment processing and transaction management',
            'communication'       => 'Messaging, notifications, and user communication features',
            'reporting'           => 'Reports, analytics, and data export features',
            'api'                 => 'External API services and integrations',
            'dashboard'           => 'User dashboard and main interface',
            'authentication'      => 'Login, registration, and authentication systems',
            'database'            => 'Database operations and data management',
            'queue'               => 'Background job processing and queues',
        ];

        $severityLevels = [
            EmergencyMode::SEVERITY_LOW      => 'Low',
            EmergencyMode::SEVERITY_MEDIUM   => 'Medium',
            EmergencyMode::SEVERITY_HIGH     => 'High',
            EmergencyMode::SEVERITY_CRITICAL => 'Critical',
        ];

        return view('developer.emergency.create', compact(
            'defaultModules',
            'severityLevels',
            'moduleDescriptions'
        ));
    }

    public function show($id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $emergencyMode = EmergencyMode::with([
            'activatedByUser',
            'deactivatedByUser',
            'logs' => fn ($q) => $q->latest()->take(20),
            'affectedUsers' => fn ($q) => $q->with('user')->take(10),
        ])->findOrFail($id);

        $affectedUsersCount = $emergencyMode->affectedUsers()->count();
        $recentLogs         = $emergencyMode->logs()->latest()->take(10)->get();

        $impactMetrics = $emergencyMode->isActive()
            ? $this->calculateCurrentImpact($emergencyMode)
            : [];

        return view('developer.emergency.show', compact(
            'emergencyMode',
            'affectedUsersCount',
            'recentLogs',
            'impactMetrics'
        ));
    }

    /* ============================================================
     | STORE
     * ============================================================ */

    public function store(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'reason'                => 'required|string|min:10',
            'severity_level'        => 'required|in:low,medium,high,critical',
            'affected_modules'      => 'required|array|min:1',
            'affected_modules.*'    => ['string', Rule::in(self::MODULES)],
            'restricted_features'   => 'nullable|array',
            'allowed_operations'    => 'nullable|array',
            'notify_users'          => 'boolean',
            'notification_channels' => 'nullable|array',
            'notification_channels.*' => ['string', Rule::in(self::CHANNELS)],
            'auto_recovery'         => 'boolean',
            'activate_immediately'  => 'boolean',
            'schedule_for_later'    => 'boolean',
            'scheduled_start'       => 'nullable|date|after:now',
            'scheduled_end'         => 'nullable|date|after:scheduled_start',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the validation errors.');
        }

        DB::beginTransaction();

        try {
            $activateNow      = $request->boolean('activate_immediately');
            $scheduleForLater = $request->boolean('schedule_for_later');

            $emergencyMode = new EmergencyMode();
            $emergencyMode->fill([
                'reference_id'          => EmergencyMode::generateReferenceId(),
                'name'                  => $request->name,
                'description'           => $request->description,
                'reason'                => $request->reason,
                'severity_level'        => $request->severity_level,
                'affected_modules'      => $request->affected_modules,
                'restricted_features'   => $request->restricted_features ?? [],
                'allowed_operations'    => $request->allowed_operations ?? ['view_dashboard', 'basic_read'],
                'notify_users'          => $request->boolean('notify_users'),
                'notification_channels' => $request->notification_channels ?? ['email', 'in_app'],
                'auto_recovery'         => $request->boolean('auto_recovery'),
            ]);

            if ($scheduleForLater && $request->scheduled_start) {
                $emergencyMode->scheduled_start = $request->scheduled_start;
                $emergencyMode->scheduled_end   = $request->scheduled_end;
                $emergencyMode->status          = EmergencyMode::STATUS_INACTIVE;
                $emergencyMode->is_active       = false;
            } elseif ($activateNow) {
                $emergencyMode->activated_at = now();
                $emergencyMode->activated_by = Auth::id();
                $emergencyMode->status       = EmergencyMode::STATUS_ACTIVATING;
                $emergencyMode->is_active    = false;
            } else {
                $emergencyMode->status    = EmergencyMode::STATUS_INACTIVE;
                $emergencyMode->is_active = false;
            }

            $emergencyMode->save();

            $emergencyMode->logs()->create([
                'action'       => 'created',
                'details'      => 'Emergency mode configuration created',
                'metadata'     => [
                    'activated_immediately' => $activateNow,
                    'scheduled'             => $scheduleForLater,
                    'scheduled_start'       => $request->scheduled_start,
                ],
                'performed_by' => Auth::id(),
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            DB::commit();

            $message = 'Emergency mode configuration saved successfully.';

            if ($activateNow) {
                $message .= ' Activating emergency mode...';
                dispatch(fn () => $this->activateEmergencyMode($emergencyMode))->afterResponse();
            } elseif ($scheduleForLater) {
                $message .= " Scheduled for {$request->scheduled_start}.";
            }

            return redirect()
                ->route('developer.emergency.show', $emergencyMode->id)
                ->with('success', $message);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create emergency mode', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create emergency mode: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | ACTIVATE
     * ============================================================ */

    public function activate($id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $emergencyMode = EmergencyMode::findOrFail($id);

        if ($emergencyMode->isActive()) {
            return redirect()->back()->with('warning', 'Emergency mode is already active.');
        }

        DB::beginTransaction();

        try {
            $emergencyMode->update(['status' => EmergencyMode::STATUS_ACTIVATING]);

            $emergencyMode->logs()->create([
                'action'       => 'activation_started',
                'details'      => 'Emergency mode activation initiated',
                'performed_by' => Auth::id(),
                'ip_address'   => request()->ip(),
                'user_agent'   => request()->userAgent(),
            ]);

            DB::commit();

            dispatch(fn () => $this->activateEmergencyMode($emergencyMode))->afterResponse();

            return redirect()
                ->route('developer.emergency.show', $emergencyMode->id)
                ->with('success', 'Emergency mode activation initiated. This may take a few moments.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to start emergency mode activation', [
                'emergency_id' => $id,
                'error'        => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to initiate emergency mode activation.');
        }
    }

    private function activateEmergencyMode(EmergencyMode $emergencyMode): void
    {
        DB::beginTransaction();

        try {
            $activationLog = [];
            $startTime     = now();

            $activationLog[] = [
                'step'      => 'preparation',
                'timestamp' => now()->toDateTimeString(),
                'status'    => 'started',
            ];

            if ($emergencyMode->environment_overrides) {
                $this->applyEnvironmentOverrides($emergencyMode);
                $activationLog[] = [
                    'step'      => 'environment_overrides',
                    'timestamp' => now()->toDateTimeString(),
                    'status'    => 'applied',
                ];
            }

            $affectedUsers = $this->calculateAffectedUsers($emergencyMode);
            $emergencyMode->affected_users_count = count($affectedUsers);

            $activationLog[] = [
                'step'           => 'user_impact_calculation',
                'timestamp'      => now()->toDateTimeString(),
                'status'         => 'completed',
                'affected_users' => count($affectedUsers),
            ];

            if ($emergencyMode->notify_users) {
                $this->notifyAffectedUsers($emergencyMode, $affectedUsers);
                $emergencyMode->last_notified_at = now();

                $activationLog[] = [
                    'step'      => 'user_notification',
                    'timestamp' => now()->toDateTimeString(),
                    'status'    => 'sent',
                    'channels'  => $emergencyMode->notification_channels,
                ];
            }

            $this->applySystemRestrictions($emergencyMode);

            $activationLog[] = [
                'step'                => 'system_restrictions',
                'timestamp'           => now()->toDateTimeString(),
                'status'              => 'applied',
                'restricted_features' => $emergencyMode->restricted_features,
            ];

            $emergencyMode->update([
                'activated_at'    => now(),
                'status'          => EmergencyMode::STATUS_ACTIVE,
                'is_active'       => true,
                'activation_log'  => $activationLog,
            ]);

            $emergencyMode->logs()->create([
                'action'       => 'activated',
                'details'      => 'Emergency mode successfully activated',
                'metadata'     => [
                    'duration_seconds' => now()->diffInSeconds($startTime),
                    'affected_users'   => count($affectedUsers),
                    'affected_modules' => $emergencyMode->affected_modules,
                ],
                'performed_by' => $emergencyMode->activated_by,
                'ip_address'   => request()->ip() ?? 'background_job',
            ]);

            DB::commit();

            Log::info('Emergency mode activated successfully', [
                'emergency_id'   => $emergencyMode->id,
                'reference_id'   => $emergencyMode->reference_id,
                'affected_users' => count($affectedUsers),
                'activation_time'=> now()->diffInSeconds($startTime),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            $emergencyMode->update([
                'status'    => EmergencyMode::STATUS_INACTIVE,
                'is_active' => false,
            ]);

            $emergencyMode->logs()->create([
                'action'       => 'activation_failed',
                'details'      => 'Emergency mode activation failed: ' . $e->getMessage(),
                'metadata'     => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
                'performed_by' => $emergencyMode->activated_by,
            ]);

            Log::error('Emergency mode activation failed', [
                'emergency_id' => $emergencyMode->id,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);
        }
    }

    /* ============================================================
     | DEACTIVATE
     * ============================================================ */

    public function deactivate($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $emergencyMode = EmergencyMode::findOrFail($id);

        if (!$emergencyMode->isActive()) {
            return redirect()->back()->with('warning', 'Emergency mode is not active.');
        }

        $validator = Validator::make($request->all(), [
            'deactivation_reason' => 'required|string|min:10',
            'confirm'             => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide a valid deactivation reason.');
        }

        DB::beginTransaction();

        try {
            $emergencyMode->update([
                'status'              => EmergencyMode::STATUS_DEACTIVATING,
                'deactivation_reason' => $request->deactivation_reason,
            ]);

            $emergencyMode->logs()->create([
                'action'       => 'deactivation_started',
                'details'      => 'Emergency mode deactivation initiated',
                'metadata'     => ['reason' => $request->deactivation_reason],
                'performed_by' => Auth::id(),
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            DB::commit();

            dispatch(fn () => $this->deactivateEmergencyMode($emergencyMode, $request->deactivation_reason))
                ->afterResponse();

            return redirect()
                ->route('developer.emergency.show', $emergencyMode->id)
                ->with('success', 'Emergency mode deactivation initiated. This may take a few moments.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to start emergency mode deactivation', [
                'emergency_id' => $id,
                'error'        => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to initiate emergency mode deactivation.');
        }
    }

    private function deactivateEmergencyMode(EmergencyMode $emergencyMode, string $reason): void
    {
        DB::beginTransaction();

        try {
            $deactivationLog = [];
            $startTime       = now();

            $deactivationLog[] = [
                'step'      => 'preparation',
                'timestamp' => now()->toDateTimeString(),
                'status'    => 'started',
            ];

            if ($emergencyMode->auto_recovery) {
                $emergencyMode->update([
                    'status'               => EmergencyMode::STATUS_RECOVERING,
                    'recovery_started_at'  => now(),
                ]);

                $recoveryResults = $this->runRecoveryChecks($emergencyMode);
                $deactivationLog[] = [
                    'step'      => 'recovery_checks',
                    'timestamp' => now()->toDateTimeString(),
                    'status'    => $recoveryResults['success'] ? 'passed' : 'failed',
                    'results'   => $recoveryResults,
                ];
            }

            $this->removeSystemRestrictions($emergencyMode);

            $deactivationLog[] = [
                'step'      => 'system_restrictions',
                'timestamp' => now()->toDateTimeString(),
                'status'    => 'removed',
            ];

            if ($emergencyMode->environment_overrides) {
                $this->revertEnvironmentOverrides($emergencyMode);
                $deactivationLog[] = [
                    'step'      => 'environment_overrides',
                    'timestamp' => now()->toDateTimeString(),
                    'status'    => 'reverted',
                ];
            }

            $emergencyMode->update([
                'deactivated_at'       => now(),
                'deactivated_by'       => Auth::id(),
                'status'               => EmergencyMode::STATUS_INACTIVE,
                'is_active'            => false,
                'duration_minutes'     => $emergencyMode->current_duration,
                'deactivation_log'     => $deactivationLog,
                'recovery_completed_at'=> $emergencyMode->auto_recovery ? now() : null,
            ]);

            if ($emergencyMode->notify_users) {
                $this->notifyDeactivation($emergencyMode);
                $deactivationLog[] = [
                    'step'      => 'deactivation_notification',
                    'timestamp' => now()->toDateTimeString(),
                    'status'    => 'sent',
                ];
            }

            $emergencyMode->logs()->create([
                'action'       => 'deactivated',
                'details'      => 'Emergency mode successfully deactivated',
                'metadata'     => [
                    'reason'                    => $reason,
                    'duration_minutes'          => $emergencyMode->duration_minutes,
                    'deactivation_time_seconds' => now()->diffInSeconds($startTime),
                    'auto_recovery'             => $emergencyMode->auto_recovery,
                ],
                'performed_by' => Auth::id(),
                'ip_address'   => request()->ip() ?? 'background_job',
            ]);

            DB::commit();

            Log::info('Emergency mode deactivated successfully', [
                'emergency_id'     => $emergencyMode->id,
                'reference_id'     => $emergencyMode->reference_id,
                'duration_minutes' => $emergencyMode->duration_minutes,
                'deactivation_time'=> now()->diffInSeconds($startTime),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            // Fail safe: keep the emergency mode active rather than half-unlocked
            $emergencyMode->update(['status' => EmergencyMode::STATUS_ACTIVE]);

            $emergencyMode->logs()->create([
                'action'       => 'deactivation_failed',
                'details'      => 'Emergency mode deactivation failed: ' . $e->getMessage(),
                'metadata'     => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
                'performed_by' => Auth::id(),
            ]);

            Log::error('Emergency mode deactivation failed', [
                'emergency_id' => $emergencyMode->id,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);
        }
    }

    /* ============================================================
     | EXTEND / CANCEL
     * ============================================================ */

    public function extend($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $emergencyMode = EmergencyMode::findOrFail($id);

        if (!$emergencyMode->isActive()) {
            return redirect()->back()->with('warning', 'Cannot extend inactive emergency mode.');
        }

        $validator = Validator::make($request->all(), [
            'extension_minutes' => 'required|integer|min:15|max:1440',
            'extension_reason'  => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide valid extension details.');
        }

        try {
            $settings     = $emergencyMode->settings ?? [];
            $extensions   = $settings['extensions'] ?? [];

            $extensions[] = [
                'timestamp'   => now()->toDateTimeString(),
                'minutes'     => $request->extension_minutes,
                'reason'      => $request->extension_reason,
                'extended_by' => Auth::id(),
            ];

            $settings['extensions'] = $extensions;

            $emergencyMode->update([
                'last_extended_at' => now(),
                'extensions_count' => $emergencyMode->extensions_count + 1,
                'settings'         => $settings,
            ]);

            $emergencyMode->logs()->create([
                'action'       => 'extended',
                'details'      => 'Emergency mode duration extended',
                'metadata'     => [
                    'minutes_added'    => $request->extension_minutes,
                    'reason'           => $request->extension_reason,
                    'total_extensions' => $emergencyMode->extensions_count,
                ],
                'performed_by' => Auth::id(),
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            return redirect()->back()
                ->with('success', "Emergency mode extended by {$request->extension_minutes} minutes.");

        } catch (\Throwable $e) {
            Log::error('Failed to extend emergency mode', [
                'emergency_id' => $id,
                'error'        => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to extend emergency mode.');
        }
    }

    public function cancel($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $emergencyMode = EmergencyMode::findOrFail($id);

        if (!$emergencyMode->isScheduled()) {
            return redirect()->back()->with('warning', 'Only scheduled emergency modes can be cancelled.');
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
            $originalStart = $emergencyMode->scheduled_start;
            $originalEnd   = $emergencyMode->scheduled_end;

            $emergencyMode->update([
                'scheduled_start' => null,
                'scheduled_end'   => null,
            ]);

            $emergencyMode->logs()->create([
                'action'       => 'cancelled',
                'details'      => 'Scheduled emergency mode cancelled',
                'metadata'     => [
                    'reason'            => $request->cancellation_reason,
                    'original_schedule' => [
                        'start' => optional($originalStart)->toDateTimeString(),
                        'end'   => optional($originalEnd)->toDateTimeString(),
                    ],
                ],
                'performed_by' => Auth::id(),
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            return redirect()
                ->route('developer.emergency.show', $emergencyMode->id)
                ->with('success', 'Scheduled emergency mode cancelled successfully.');

        } catch (\Throwable $e) {
            Log::error('Failed to cancel scheduled emergency mode', [
                'emergency_id' => $id,
                'error'        => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to cancel scheduled emergency mode.');
        }
    }

    /* ============================================================
     | HISTORY / STATISTICS
     * ============================================================ */

    public function history(Request $request, $id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $emergencyMode = EmergencyMode::with(['activatedByUser', 'deactivatedByUser'])
            ->findOrFail($id);

        $query = $emergencyMode->logs()->with([
            'performer' => fn ($q) => $q->select('id', 'name', 'email', 'profile_photo_path'),
        ]);

        $query->when($request->filled('action'),
            fn ($q) => $q->where('action', $request->action));

        $query->when($request->filled('user'),
            fn ($q) => $q->where('performed_by', $request->user));

        $query->when($request->filled('date_from'),
            fn ($q) => $q->whereDate('created_at', '>=', $request->date_from));

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('developer.emergency.history', compact('emergencyMode', 'logs'));
    }

    public function statistics()
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $statistics = EmergencyMode::getStatistics();

        $recentEmergencies = EmergencyMode::orderByDesc('created_at')->take(10)->get();

        $severityDistribution = EmergencyMode::select('severity_level')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('severity_level')
            ->pluck('count', 'severity_level');

        // Cross-database month expression
        $driver = DB::connection()->getDriverName();

        $monthExpr = match ($driver) {
            'mysql'  => 'DATE_FORMAT(created_at, "%Y-%m")',
            'pgsql'  => "TO_CHAR(created_at, 'YYYY-MM')",
            'sqlite' => 'strftime("%Y-%m", created_at)',
            default  => 'DATE_FORMAT(created_at, "%Y-%m")',
        };

        $monthlyTrend = EmergencyMode::selectRaw("{$monthExpr} as month")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get();

        return view('developer.emergency.statistics', compact(
            'statistics',
            'recentEmergencies',
            'severityDistribution',
            'monthlyTrend'
        ));
    }

    /* ============================================================
     | API
     * ============================================================ */

    public function apiActive(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            $activeEmergencies = EmergencyMode::active()
                ->with('activatedByUser')
                ->get()
                ->map(fn ($emergency) => [
                    'id'                   => $emergency->id,
                    'reference_id'         => $emergency->reference_id,
                    'name'                 => $emergency->name,
                    'reason'               => $emergency->reason,
                    'severity_level'       => $emergency->severity_level,
                    'activated_at'         => optional($emergency->activated_at)->toISOString(),
                    'duration_minutes'     => $emergency->current_duration,
                    'formatted_duration'   => $emergency->formatted_duration,
                    'affected_modules'     => $emergency->affected_modules_list,
                    'affected_users_count' => $emergency->affected_users_count,
                    'activated_by'         => $emergency->activatedByUser?->name,
                ]);

            return response()->json([
                'success'            => true,
                'active_emergencies' => $activeEmergencies,
                'count'              => $activeEmergencies->count(),
                'timestamp'          => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch active emergencies', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active emergencies',
            ], 500);
        }
    }

    public function apiCheckModule(Request $request, $module)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            $activeEmergency = EmergencyMode::active()
                ->whereJsonContains('affected_modules', $module)
                ->first();

            $isAffected = !is_null($activeEmergency);

            return response()->json([
                'success'     => true,
                'module'      => $module,
                'is_affected' => $isAffected,
                'emergency'   => $isAffected ? [
                    'id'                  => $activeEmergency->id,
                    'name'                => $activeEmergency->name,
                    'severity'            => $activeEmergency->severity_level,
                    'restricted_features' => $activeEmergency->restricted_features,
                    'allowed_operations'  => $activeEmergency->allowed_operations,
                ] : null,
                'timestamp'   => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to check module emergency status', [
                'module' => $module,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check module status',
            ], 500);
        }
    }

    /* ============================================================
     | PRIVATE HELPERS
     * ============================================================ */

    private function calculateAffectedUsers(EmergencyMode $emergencyMode): array
    {
        $affectedModules = $emergencyMode->affected_modules ?? [];
        $affectedUsers   = [];

        foreach ($affectedModules as $module) {
            $users = $this->usersAffectedByModule($module);

            foreach ($users as $user) {
                if (!isset($affectedUsers[$user->id])) {
                    $affectedUsers[$user->id] = [
                        'user'              => $user,
                        'affected_features' => [],
                        'user_type'         => $user->type,
                    ];
                }

                $affectedUsers[$user->id]['affected_features'][] = $module;
            }
        }

        return $affectedUsers;
    }

    /**
     * Return the collection of users affected by a single module.
     * Fixed the OR-precedence bug for 'communication'.
     */
    private function usersAffectedByModule(string $module)
    {
        return match ($module) {
            'user_management' => User::where('status', User::STATUS_ACTIVE)->get(),

            'property_management' => User::whereIn('type', [
                    User::TYPE_LANDLORD,
                    User::TYPE_TENANT,
                ])
                ->where('status', User::STATUS_ACTIVE)
                ->get(),

            'payment_processing' => User::whereHas('payments', fn ($q) => $q->where('status', 'pending'))
                ->where('status', User::STATUS_ACTIVE)
                ->get(),

            'communication' => User::where('status', User::STATUS_ACTIVE)
                ->where(function ($q) {
                    $q->whereHas('notifications')
                      ->orWhereHas('messages');
                })
                ->get(),

            default => collect(),
        };
    }

    private function notifyAffectedUsers(EmergencyMode $emergencyMode, array $affectedUsers): void
    {
        $channels         = $emergencyMode->notification_channels ?? ['email', 'in_app'];
        $notificationData = [
            'emergency_name'     => $emergencyMode->name,
            'reason'             => $emergencyMode->reason,
            'severity'           => $emergencyMode->severity_level,
            'affected_modules'   => $emergencyMode->affected_modules_list,
            'start_time'         => optional($emergencyMode->activated_at)->format('Y-m-d H:i:s'),
            'estimated_duration' => 'Until further notice',
            'allowed_operations' => $emergencyMode->allowed_operations,
            'contact_support'    => true,
        ];

        foreach ($affectedUsers as $userId => $data) {
            $user = $data['user'];

            EmergencyAffectedUser::firstOrCreate(
                [
                    'emergency_mode_id' => $emergencyMode->id,
                    'user_id'           => $user->id,
                ],
                [
                    'user_type'         => $user->type,
                    'affected_features' => $data['affected_features'],
                ]
            );

            foreach ($channels as $channel) {
                try {
                    $this->notificationService->sendEmergencyNotification(
                        $user,
                        $channel,
                        $notificationData
                    );
                } catch (\Throwable $e) {
                    Log::warning("Failed to send {$channel} notification to user {$user->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function applySystemRestrictions(EmergencyMode $emergencyMode): void
    {
        $restrictedFeatures = $emergencyMode->restricted_features ?? [];

        foreach ($restrictedFeatures as $feature) {
            match ($feature) {
                'user_registration'   => config(['app.allow_registration' => false]),
                'property_creation'   => config(['app.allow_property_creation' => false]),
                'payment_processing'  => config(['app.allow_payments' => false]),
                'email_notifications' => config(['app.send_non_critical_emails' => false]),
                'api_write_operations'=> config(['app.api_write_enabled' => false]),
                default               => null,
            };
        }

        cache()->put('emergency_restrictions', $restrictedFeatures, now()->addHours(24));
    }

    private function removeSystemRestrictions(EmergencyMode $emergencyMode): void
    {
        $restrictedFeatures = $emergencyMode->restricted_features ?? [];

        foreach ($restrictedFeatures as $feature) {
            match ($feature) {
                'user_registration'   => config(['app.allow_registration' => true]),
                'property_creation'   => config(['app.allow_property_creation' => true]),
                'payment_processing'  => config(['app.allow_payments' => true]),
                'email_notifications' => config(['app.send_non_critical_emails' => true]),
                'api_write_operations'=> config(['app.api_write_enabled' => true]),
                default               => null,
            };
        }

        cache()->forget('emergency_restrictions');
    }

    /**
     * Snapshot-based override application so revert restores the TRUE original.
     */
    private function applyEnvironmentOverrides(EmergencyMode $emergencyMode): void
    {
        $overrides = $emergencyMode->environment_overrides ?? [];
        if (empty($overrides)) {
            return;
        }

        $snapshot = [];
        foreach (array_keys($overrides) as $key) {
            $snapshot[$key] = config($key);
        }

        cache()->put('emergency_env_overrides_snapshot', $snapshot, now()->addHours(24));

        foreach ($overrides as $key => $value) {
            config([$key => $value]);
        }

        cache()->put('emergency_env_overrides', $overrides, now()->addHours(24));
    }

    private function revertEnvironmentOverrides(EmergencyMode $emergencyMode): void
    {
        $snapshot = cache()->get('emergency_env_overrides_snapshot', []);

        foreach ($snapshot as $key => $original) {
            config([$key => $original]);
        }

        cache()->forget('emergency_env_overrides_snapshot');
        cache()->forget('emergency_env_overrides');
    }

    private function runRecoveryChecks(EmergencyMode $emergencyMode): array
    {
        $checks    = $emergencyMode->recovery_checks ?? ['database', 'services', 'queues'];
        $results   = [];
        $allPassed = true;

        foreach ($checks as $check) {
            try {
                $result = $this->performRecoveryCheck($check);

                $results[$check] = [
                    'status'  => $result ? 'passed' : 'failed',
                    'message' => $result ? 'Check passed' : 'Check failed',
                ];

                if (!$result) {
                    $allPassed = false;
                }
            } catch (\Throwable $e) {
                $results[$check] = [
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ];
                $allPassed = false;
            }
        }

        return [
            'success'   => $allPassed,
            'checks'    => $results,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    private function performRecoveryCheck(string $check): bool
    {
        return match ($check) {
            'database' => $this->checkDatabase(),
            'services' => $this->checkServices(),
            'queues'   => $this->checkQueues(),
            'storage'  => $this->checkStorage(),
            default    => true,
        };
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function checkServices(): bool
    {
        $health = $this->healthService->getOverallHealth();
        return ($health['score'] ?? 0) > 70;
    }

    private function checkQueues(): bool
    {
        try {
            return DB::table('failed_jobs')->count() < 10;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            $freeSpace  = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());

            if ($totalSpace <= 0) {
                return false;
            }

            $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            return $usagePercent < 90;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function notifyDeactivation(EmergencyMode $emergencyMode): void
    {
        $affectedUsers = $emergencyMode->affectedUsers()->with('user')->get();
        $channels      = $emergencyMode->notification_channels ?? ['email', 'in_app'];

        $notificationData = [
            'emergency_name'    => $emergencyMode->name,
            'deactivation_time' => optional($emergencyMode->deactivated_at)->format('Y-m-d H:i:s'),
            'duration'          => $emergencyMode->formatted_duration,
            'summary'           => 'Emergency mode has been deactivated. All systems are now operational.',
        ];

        foreach ($affectedUsers as $affectedUser) {
            $user = $affectedUser->user;
            if (!$user) {
                continue;
            }

            foreach ($channels as $channel) {
                try {
                    $this->notificationService->sendEmergencyDeactivationNotification(
                        $user,
                        $channel,
                        $notificationData
                    );
                } catch (\Throwable $e) {
                    Log::warning("Failed to send deactivation notification to user {$affectedUser->user_id}", [
                        'channel' => $channel,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            $affectedUser->update(['notified_at' => now()]);
        }
    }

    private function calculateCurrentImpact(EmergencyMode $emergencyMode): array
    {
        $affectedUsers = $emergencyMode->affectedUsers()->with('user')->get();
        $impactByType  = [];
        $totalAffected = $affectedUsers->count();

        foreach ($affectedUsers as $affectedUser) {
            $userType = $affectedUser->user_type;
            $impactByType[$userType] = ($impactByType[$userType] ?? 0) + 1;
        }

        $totalUsers         = User::where('status', User::STATUS_ACTIVE)->count();
        $affectedPercentage = $totalUsers > 0 ? ($totalAffected / $totalUsers) * 100 : 0;

        return [
            'total_affected'      => $totalAffected,
            'total_users'         => $totalUsers,
            'affected_percentage' => round($affectedPercentage, 2),
            'by_user_type'        => $impactByType,
            'severity_level'      => $emergencyMode->severity_level,
            'duration_minutes'    => $emergencyMode->current_duration,
            'estimated_impact'    => $this->estimateBusinessImpact($emergencyMode, $totalAffected),
        ];
    }

    private function estimateBusinessImpact(EmergencyMode $emergencyMode, int $affectedUsers): array
    {
        $severityMultiplier = match ($emergencyMode->severity_level) {
            'critical' => 1.5,
            'high'     => 1.2,
            'medium'   => 1.0,
            'low'      => 0.8,
            default    => 1.0,
        };

        $durationHours = ($emergencyMode->current_duration ?? 0) / 60;

        $estimatedCost = $affectedUsers * $durationHours * $severityMultiplier * 0.5;

        return [
            'estimated_users_affected' => $affectedUsers,
            'duration_hours'           => round($durationHours, 2),
            'severity_multiplier'      => $severityMultiplier,
            'estimated_cost_units'     => round($estimatedCost, 2),
            'impact_level'             => match (true) {
                $estimatedCost > 100 => 'high',
                $estimatedCost > 50  => 'medium',
                default              => 'low',
            },
        ];
    }
}