<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\EmergencyMode;
use App\Models\Maintenance;
use App\Models\MaintenanceAffectedUser;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    /**
     * User types allowed to manage maintenance windows.
     * Reference User::TYPE_* constants so there is one source of truth.
     */
    public const AUTHORIZED_USER_TYPES = [
        User::TYPE_SUPER_ADMIN,
        User::TYPE_DEVELOPER,
    ];

    /**
     * All module keys recognised by the maintenance system.
     */
    public const MODULES = [
        'user_management', 'property_management', 'payment_processing',
        'communication', 'reporting', 'api', 'dashboard', 'authentication',
        'database', 'queue', 'storage', 'cache', 'email', 'sms', 'whatsapp',
    ];

    /**
     * Notification channels supported by the system.
     */
    public const CHANNELS = ['email', 'sms', 'whatsapp', 'in_app'];

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /* ============================================================
     | AUTHORIZATION
     * ============================================================ */

    private function canManageMaintenance(): bool
    {
        $user = Auth::user();

        return $user
            && in_array((int) $user->type, self::AUTHORIZED_USER_TYPES, true);
    }

    private function authorizeOrAbort(bool $json = false)
    {
        if ($this->canManageMaintenance()) {
            return null;
        }

        Log::warning('Maintenance access denied', [
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

        abort(403, 'Unauthorized to manage maintenance.');
    }

    /* ============================================================
     | SHARED FORM DATA
     * ============================================================ */

    private function moduleLabels(): array
    {
        return [
            'user_management'     => 'User Management',
            'property_management' => 'Property Management',
            'payment_processing'  => 'Payment Processing',
            'communication'       => 'Communication',
            'reporting'           => 'Reporting',
            'api'                 => 'API Services',
            'dashboard'           => 'Dashboard',
            'authentication'      => 'Authentication',
            'database'            => 'Database',
            'queue'               => 'Queue System',
            'storage'             => 'File Storage',
            'cache'               => 'Cache System',
        ];
    }

    private function impactLevels(): array
    {
        return [
            Maintenance::IMPACT_LOW      => 'Low',
            Maintenance::IMPACT_MEDIUM   => 'Medium',
            Maintenance::IMPACT_HIGH     => 'High',
            Maintenance::IMPACT_CRITICAL => 'Critical',
        ];
    }

    private function maintenanceTypes(): array
    {
        return [
            Maintenance::TYPE_PLANNED   => 'Planned Maintenance',
            Maintenance::TYPE_EMERGENCY => 'Emergency Maintenance',
            Maintenance::TYPE_HOTFIX    => 'Hotfix',
            Maintenance::TYPE_UPGRADE   => 'System Upgrade',
            Maintenance::TYPE_SECURITY  => 'Security Patch',
        ];
    }

    /* ============================================================
     | VIEWS
     * ============================================================ */

    public function index(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $query = Maintenance::with(['creator', 'approver', 'completer'])
            ->orderByDesc('scheduled_start')
            ->orderByDesc('created_at');

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));
        $query->when($request->filled('type'),   fn ($q) => $q->where('maintenance_type', $request->type));
        $query->when($request->filled('impact'), fn ($q) => $q->where('impact_level', $request->impact));

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->string('search')->toString();
            $q->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });

        $query->when($request->filled('date_from'),
            fn ($q) => $q->whereDate('scheduled_start', '>=', $request->date_from));

        $query->when($request->filled('date_to'),
            fn ($q) => $q->whereDate('scheduled_start', '<=', $request->date_to));

        $maintenances      = $query->paginate(20)->withQueryString();
        $statistics        = Maintenance::getStatistics();
        $trashCount        = Maintenance::onlyTrashed()->count();
        $oldestTrashItem   = Maintenance::onlyTrashed()->orderBy('deleted_at')->first();
        $activeMaintenance = Maintenance::active()->first();

        return view('developer.maintenance.index', compact(
            'maintenances',
            'statistics',
            'trashCount',
            'oldestTrashItem',
            'activeMaintenance'
        ));
    }

    public function create()
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $defaultModules    = $this->moduleLabels();
        $userTypes         = User::getUserTypes();
        $impactLevels      = $this->impactLevels();
        $maintenanceTypes  = $this->maintenanceTypes();
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

    public function show($id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::with([
            'creator',
            'approver',
            'completer',
            'emergencyMode',
            'logs'          => fn ($q) => $q->latest()->take(20),
            'affectedUsers' => fn ($q) => $q->with('user')->take(10),
        ])->findOrFail($id);

        $affectedUsersCount = $maintenance->affectedUsers()->count();
        $recentLogs         = $maintenance->logs()->latest()->take(10)->get();
        $impactMetrics      = $this->calculateMaintenanceImpact($maintenance);

        $relatedMaintenances = Maintenance::where('id', '!=', $id)
            ->where(function ($query) use ($maintenance) {
                $query->whereBetween('scheduled_start', [$maintenance->scheduled_start, $maintenance->scheduled_end])
                    ->orWhereBetween('scheduled_end', [$maintenance->scheduled_start, $maintenance->scheduled_end])
                    ->orWhere(function ($q) use ($maintenance) {
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

    public function edit($id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::with(['creator', 'approver', 'completer'])->findOrFail($id);

        if (!in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED], true)) {
            return redirect()
                ->route('developer.maintenance.show', $maintenance->id)
                ->with('warning', 'Only draft or scheduled maintenance can be edited.');
        }

        $defaultModules    = $this->moduleLabels();
        $userTypes         = User::getUserTypes();
        $impactLevels      = $this->impactLevels();
        $maintenanceTypes  = $this->maintenanceTypes();
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

    /* ============================================================
     | VALIDATION
     * ============================================================ */

    private function storeRules(): array
    {
        return [
            'title'                      => 'required|string|max:255',
            'description'                => 'required|string|min:20',
            'technical_details'          => 'nullable|string',
            'user_impact_description'    => 'required|string|min:20',
            'maintenance_type'           => 'required|in:planned,emergency,hotfix,upgrade,security',
            'impact_level'               => 'required|in:low,medium,high,critical',
            'scheduled_start'            => 'required|date|after:now',
            'scheduled_end'              => 'required|date|after:scheduled_start',
            'estimated_duration_minutes' => 'required|integer|min:1|max:1440',
            'affected_modules'           => 'required|array|min:1',
            'affected_modules.*'         => ['string', Rule::in(self::MODULES)],
            'affected_user_types'        => 'required|array|min:1',
            'affected_user_types.*'      => ['string', Rule::in(array_keys(User::getUserTypes()))],
            'notify_users'               => 'boolean',
            'notification_channels'      => 'nullable|array',
            'notification_channels.*'    => ['string', Rule::in(self::CHANNELS)],
            'has_rollback_plan'          => 'boolean',
            'is_emergency'               => 'boolean',
            'emergency_reason'           => 'required_if:is_emergency,true|nullable|string|min:10',
            'related_emergency_id'       => 'nullable|exists:emergency_modes,id',
        ];
    }

    private function storeMessages(): array
    {
        return [
            'scheduled_end.after'          => 'The end time must be after the start time.',
            'emergency_reason.required_if' => 'Emergency reason is required for emergency maintenance.',
        ];
    }

    private function updateRules(): array
    {
        return [
            'title'                      => 'required|string|max:255',
            'description'                => 'required|string|min:20',
            'technical_details'          => 'nullable|string',
            'user_impact_description'    => 'required|string|min:20',
            'scheduled_start'            => 'required|date',
            'scheduled_end'              => 'required|date|after:scheduled_start',
            'estimated_duration_minutes' => 'required|integer|min:1|max:1440',
            'update_notes'               => 'required|string|min:10',
        ];
    }

    /* ============================================================
     | STORE / UPDATE
     * ============================================================ */

    public function store(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $validator = Validator::make($request->all(), $this->storeRules(), $this->storeMessages());

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
            $maintenance->fill([
                'reference_id'               => Maintenance::generateReferenceId(),
                'title'                      => $request->title,
                'description'                => $request->description,
                'technical_details'          => $request->technical_details,
                'user_impact_description'    => $request->user_impact_description,
                'maintenance_type'           => $request->maintenance_type,
                'impact_level'               => $request->impact_level,
                'scheduled_start'            => $request->scheduled_start,
                'scheduled_end'              => $request->scheduled_end,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
                'affected_modules'           => $request->affected_modules,
                'affected_user_types'        => $request->affected_user_types,
                'notify_users'               => $request->boolean('notify_users', true),
                'notification_channels'      => $request->notification_channels ?? ['email', 'in_app'],
                'has_rollback_plan'          => $request->boolean('has_rollback_plan', false),
                'is_emergency'               => $isEmergency,
                'emergency_reason'           => $isEmergency ? $request->emergency_reason : null,
                'related_emergency_id'       => $request->related_emergency_id,
                'status'                     => Maintenance::STATUS_DRAFT,
                'created_by'                 => Auth::id(),
                'allowed_operations'         => $this->getAllowedOperationsForImpact($request->impact_level),
                'estimated_affected_users'   => $this->calculateEstimatedAffectedUsers($request->affected_user_types),
            ]);

            $maintenance->save();

            $this->writeLog($maintenance, 'created', 'Maintenance schedule created', [
                'type'            => $maintenance->maintenance_type,
                'impact'          => $maintenance->impact_level,
                'scheduled_start' => optional($maintenance->scheduled_start)->toDateTimeString(),
            ]);

            DB::commit();

            return redirect()
                ->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance schedule created successfully. Please review and approve.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create maintenance schedule', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create maintenance schedule: ' . $e->getMessage());
        }
    }

    public function update($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::findOrFail($id);

        if (!in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED], true)) {
            return redirect()->back()
                ->with('warning', 'Only draft or scheduled maintenance can be updated.');
        }

        $validator = Validator::make($request->all(), $this->updateRules());

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the validation errors.');
        }

        DB::beginTransaction();

        try {
            $oldData = [
                'scheduled_start' => optional($maintenance->scheduled_start)->toDateTimeString(),
                'scheduled_end'   => optional($maintenance->scheduled_end)->toDateTimeString(),
                'title'           => $maintenance->title,
            ];

            $maintenance->fill([
                'title'                      => $request->title,
                'description'                => $request->description,
                'technical_details'          => $request->technical_details,
                'user_impact_description'    => $request->user_impact_description,
                'scheduled_start'            => $request->scheduled_start,
                'scheduled_end'              => $request->scheduled_end,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
                'updated_by'                 => Auth::id(),
            ])->save();

            $this->writeLog($maintenance, 'updated', 'Maintenance schedule updated', [
                'notes'   => $request->update_notes,
                'changes' => [
                    'old' => $oldData,
                    'new' => [
                        'scheduled_start' => optional($maintenance->scheduled_start)->toDateTimeString(),
                        'scheduled_end'   => optional($maintenance->scheduled_end)->toDateTimeString(),
                        'title'           => $maintenance->title,
                    ],
                ],
            ]);

            DB::commit();

            if ($maintenance->status === Maintenance::STATUS_SCHEDULED && $maintenance->notify_users) {
                dispatch(fn () => $this->notifyUsersOfUpdate($maintenance))->afterResponse();
            }

            return redirect()
                ->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance schedule updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to update maintenance schedule', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update maintenance schedule.');
        }
    }

    /* ============================================================
     | STATE TRANSITIONS
     * ============================================================ */

    public function approve($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::findOrFail($id);

        if ($maintenance->status !== Maintenance::STATUS_DRAFT) {
            return redirect()->back()->with('warning', 'Only draft maintenance schedules can be approved.');
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
            $maintenance->update([
                'status'      => Maintenance::STATUS_SCHEDULED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $this->writeLog($maintenance, 'approved', 'Maintenance schedule approved', [
                'notes'       => $request->approval_notes,
                'approved_by' => Auth::id(),
            ]);

            DB::commit();

            if ($maintenance->notify_users) {
                dispatch(fn () => $this->notifyUsersOfScheduledMaintenance($maintenance))->afterResponse();
            }

            return redirect()
                ->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance schedule approved and notifications sent.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to approve maintenance schedule', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to approve maintenance schedule.');
        }
    }

    public function start($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::findOrFail($id);

        if ($maintenance->status !== Maintenance::STATUS_SCHEDULED) {
            return redirect()->back()->with('warning', 'Only scheduled maintenance can be started.');
        }

        $validator = Validator::make($request->all(), [
            'start_notes'        => 'nullable|string',
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
            $maintenance->update([
                'status'       => Maintenance::STATUS_IN_PROGRESS,
                'actual_start' => now(),
                'updated_by'   => Auth::id(),
            ]);

            $this->writeLog($maintenance, 'started', 'Maintenance started', [
                'notes'        => $request->start_notes,
                'actual_start' => now()->toDateTimeString(),
            ]);

            DB::commit();

            return redirect()
                ->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance started successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to start maintenance', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to start maintenance.');
        }
    }

    public function complete($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::findOrFail($id);

        if ($maintenance->status !== Maintenance::STATUS_IN_PROGRESS) {
            return redirect()->back()->with('warning', 'Only maintenance in progress can be completed.');
        }

        $validator = Validator::make($request->all(), [
            'completion_notes'        => 'required|string|min:10',
            'post_checks_passed'      => 'required|accepted',
            'estimated_vs_actual'     => 'required|in:within_estimate,under_estimate,over_estimate',
            'actual_duration_minutes' => 'required|integer|min:1',
            'actual_affected_users'   => 'required|integer|min:0',
            'downtime_minutes'        => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide all completion details.');
        }

        DB::beginTransaction();

        try {
            $estimated = (int) $maintenance->estimated_duration_minutes;
            $actual    = (int) $request->actual_duration_minutes;

            $withinEstimate = $estimated > 0
                ? abs($estimated - $actual) <= ($estimated * 0.1)
                : true;

            $maintenance->update([
                'status'                    => Maintenance::STATUS_COMPLETED,
                'actual_end'                => now(),
                'completed_by'              => Auth::id(),
                'completed_at'              => now(),
                'actual_duration_minutes'   => $actual,
                'actual_affected_users'     => $request->actual_affected_users,
                'downtime_minutes'          => $request->downtime_minutes,
                'completed_within_estimate' => $withinEstimate,
                'all_checks_passed'         => true,
                'checks_completed_at'       => now(),
            ]);

            $this->writeLog($maintenance, 'completed', 'Maintenance completed successfully', [
                'notes'                     => $request->completion_notes,
                'actual_duration'           => $actual,
                'affected_users'            => $request->actual_affected_users,
                'downtime'                  => $request->downtime_minutes,
                'completed_within_estimate' => $withinEstimate,
            ]);

            DB::commit();

            if ($maintenance->notify_users) {
                dispatch(fn () => $this->notifyUsersOfCompletion($maintenance))->afterResponse();
            }

            return redirect()
                ->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance completed successfully. Notifications sent to users.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to complete maintenance', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to complete maintenance.');
        }
    }

    public function cancel($id, Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::findOrFail($id);

        if (!in_array($maintenance->status, [Maintenance::STATUS_DRAFT, Maintenance::STATUS_SCHEDULED], true)) {
            return redirect()->back()->with('warning', 'Only draft or scheduled maintenance can be cancelled.');
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

        DB::beginTransaction();

        try {
            $previousStatus = $maintenance->status;

            $maintenance->update([
                'status'              => Maintenance::STATUS_CANCELLED,
                'cancelled_by'        => Auth::id(),
                'cancelled_at'        => now(),
                'cancellation_reason' => $request->cancellation_reason,
            ]);

            $this->writeLog($maintenance, 'cancelled', 'Maintenance cancelled', [
                'reason'          => $request->cancellation_reason,
                'previous_status' => $previousStatus,
            ]);

            DB::commit();

            if ($previousStatus === Maintenance::STATUS_SCHEDULED && $maintenance->notify_users) {
                dispatch(fn () => $this->notifyUsersOfCancellation($maintenance))->afterResponse();
            }

            return redirect()
                ->route('developer.maintenance.show', $maintenance->id)
                ->with('success', 'Maintenance cancelled successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to cancel maintenance', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to cancel maintenance.');
        }
    }

    /* ============================================================
     | HISTORY / STATISTICS
     * ============================================================ */

    public function history($id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::findOrFail($id);
        $logs        = $maintenance->logs()->latest()->paginate(50);

        return view('developer.maintenance.history', compact('maintenance', 'logs'));
    }

    public function statistics()
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $statistics = Maintenance::getStatistics();
        $trends     = Maintenance::getTrends();

        $recentMaintenances = Maintenance::latest()->take(10)->get();

        $impactDistribution = Maintenance::select('impact_level')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('impact_level')
            ->pluck('count', 'impact_level');

        $typeDistribution = Maintenance::select('maintenance_type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('maintenance_type')
            ->pluck('count', 'maintenance_type');

        $statusDistribution = Maintenance::select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $trashCount      = Maintenance::onlyTrashed()->count();
        $oldestTrashItem = Maintenance::onlyTrashed()->orderBy('deleted_at')->first();

        $trashByUser = DB::table('maintenances')
            ->select('deleted_by', DB::raw('COUNT(*) as count'))
            ->whereNotNull('deleted_at')
            ->whereNotNull('deleted_by')
            ->groupBy('deleted_by')
            ->get();

        // Accurate restored count from logs (not guessed)
        $restoredCount = MaintenanceLog::where('action', 'restored')->count();

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
            'restoredCount'
        ));
    }

    /* ============================================================
     | API
     * ============================================================ */

    public function apiUpcoming(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            $limit = max(1, min(50, (int) $request->get('limit', 5)));

            $upcoming = Maintenance::upcoming()
                ->orderBy('scheduled_start')
                ->limit($limit)
                ->get()
                ->map(fn ($m) => [
                    'id'                         => $m->id,
                    'reference_id'               => $m->reference_id,
                    'title'                      => $m->title,
                    'description'                => $m->user_impact_description,
                    'type'                       => $m->maintenance_type,
                    'impact_level'               => $m->impact_level,
                    'scheduled_start'            => optional($m->scheduled_start)->toISOString(),
                    'scheduled_end'              => optional($m->scheduled_end)->toISOString(),
                    'time_until_start'           => $m->time_until_start,
                    'affected_modules'           => $m->affected_modules_list,
                    'estimated_duration_minutes' => $m->estimated_duration_minutes,
                    'is_emergency'               => $m->is_emergency,
                    'notify_users'               => $m->notify_users,
                ]);

            return response()->json([
                'success'              => true,
                'upcoming_maintenance' => $upcoming,
                'count'                => $upcoming->count(),
                'timestamp'            => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch upcoming maintenance', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upcoming maintenance',
            ], 500);
        }
    }

    public function apiActive(Request $request)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            $active = Maintenance::active()
                ->orderBy('actual_start')
                ->get()
                ->map(fn ($m) => [
                    'id'                         => $m->id,
                    'reference_id'               => $m->reference_id,
                    'title'                      => $m->title,
                    'description'                => $m->user_impact_description,
                    'type'                       => $m->maintenance_type,
                    'impact_level'               => $m->impact_level,
                    'actual_start'               => optional($m->actual_start)->toISOString(),
                    'current_duration'           => $m->current_duration,
                    'affected_modules'           => $m->affected_modules_list,
                    'estimated_duration_minutes' => $m->estimated_duration_minutes,
                    'is_overdue'                 => $m->is_overdue,
                    'allowed_operations'         => $m->allowed_operations,
                ]);

            return response()->json([
                'success'            => true,
                'active_maintenance' => $active,
                'count'              => $active->count(),
                'timestamp'          => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch active maintenance', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active maintenance',
            ], 500);
        }
    }

    public function apiCheckModule(Request $request, $module)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            $activeMaintenance = Maintenance::active()
                ->whereJsonContains('affected_modules', $module)
                ->first();

            $upcomingMaintenance = Maintenance::upcoming()
                ->whereJsonContains('affected_modules', $module)
                ->where('scheduled_start', '<=', now()->addHour())
                ->first();

            $isUnderMaintenance    = !is_null($activeMaintenance);
            $isMaintenanceImminent = !is_null($upcomingMaintenance);

            return response()->json([
                'success'                 => true,
                'module'                  => $module,
                'is_under_maintenance'    => $isUnderMaintenance,
                'is_maintenance_imminent' => $isMaintenanceImminent,
                'active_maintenance'      => $isUnderMaintenance ? [
                    'id'                       => $activeMaintenance->id,
                    'title'                    => $activeMaintenance->title,
                    'started_at'               => optional($activeMaintenance->actual_start)->toISOString(),
                    'current_duration_minutes' => $activeMaintenance->current_duration,
                    'allowed_operations'       => $activeMaintenance->allowed_operations,
                ] : null,
                'upcoming_maintenance'    => $isMaintenanceImminent ? [
                    'id'               => $upcomingMaintenance->id,
                    'title'            => $upcomingMaintenance->title,
                    'scheduled_start'  => optional($upcomingMaintenance->scheduled_start)->toISOString(),
                    'time_until_start' => $upcomingMaintenance->time_until_start,
                ] : null,
                'timestamp'               => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to check module maintenance status', [
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
     | TRASH
     * ============================================================ */

    public function destroy($id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::findOrFail($id);

        if ($maintenance->status !== Maintenance::STATUS_DRAFT) {
            return redirect()->back()->with('warning', 'Only draft maintenance schedules can be deleted.');
        }

        try {
            $maintenance->delete();

            return redirect()
                ->route('developer.maintenance.index')
                ->with('success', 'Maintenance schedule deleted successfully.');

        } catch (\Throwable $e) {
            Log::error('Failed to delete maintenance schedule', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to delete maintenance schedule.');
        }
    }

    public function trash(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $query = Maintenance::onlyTrashed()
            ->with(['creator', 'deletedBy'])
            ->orderByDesc('deleted_at');

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->string('search')->toString();
            $q->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });

        $query->when($request->filled('deleted_by'),
            fn ($q) => $q->where('deleted_by', $request->deleted_by));

        $query->when($request->filled('deleted_from'),
            fn ($q) => $q->whereDate('deleted_at', '>=', $request->deleted_from));

        $query->when($request->filled('deleted_to'),
            fn ($q) => $q->whereDate('deleted_at', '<=', $request->deleted_to));

        $trashedMaintenances = $query->paginate(20)->withQueryString();

        $deletedByUsers = User::whereIn(
            'id',
            Maintenance::onlyTrashed()->pluck('deleted_by')->filter()->unique()
        )->get();

        $oldestItem    = Maintenance::onlyTrashed()->orderBy('deleted_at')->first();
        $restoredCount = MaintenanceLog::where('action', 'restored')->count();

        return view('developer.maintenance.trash', compact(
            'trashedMaintenances',
            'deletedByUsers',
            'oldestItem',
            'restoredCount'
        ));
    }

    public function restore($id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::onlyTrashed()->findOrFail($id);

        DB::beginTransaction();

        try {
            $maintenance->restore();

            $maintenance->update([
                'status'      => Maintenance::STATUS_DRAFT,
                'restored_at' => now(),
                'restored_by' => Auth::id(),
            ]);

            $maintenance->logs()->create([
                'action'       => 'restored',
                'details'      => 'Maintenance schedule restored from trash',
                'performed_by' => Auth::id(),
                'ip_address'   => request()->ip(),
                'user_agent'   => request()->userAgent(),
            ]);

            DB::commit();

            return redirect()
                ->route('developer.maintenance.trash')
                ->with('success', 'Maintenance schedule restored successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to restore maintenance schedule', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to restore maintenance schedule.');
        }
    }

    public function forceDelete($id)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $maintenance = Maintenance::onlyTrashed()->findOrFail($id);

        DB::beginTransaction();

        try {
            $referenceId = $maintenance->reference_id;

            $maintenance->logs()->delete();
            $maintenance->affectedUsers()->delete();
            $maintenance->forceDelete();

            DB::commit();

            Log::info('Maintenance schedule permanently deleted', [
                'reference_id' => $referenceId,
                'deleted_by'   => Auth::id(),
            ]);

            return redirect()
                ->route('developer.maintenance.trash')
                ->with('success', 'Maintenance schedule permanently deleted.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to permanently delete maintenance schedule', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to permanently delete maintenance schedule.');
        }
    }

    public function bulkRestore(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $data = $request->validate([
            'items'   => 'required|array',
            'items.*' => 'integer|exists:maintenances,id',
        ]);

        $restoredCount = 0;

        DB::beginTransaction();

        try {
            foreach ($data['items'] as $id) {
                $maintenance = Maintenance::onlyTrashed()->find($id);

                if (!$maintenance) {
                    continue;
                }

                $maintenance->restore();
                $maintenance->update([
                    'status'      => Maintenance::STATUS_DRAFT,
                    'restored_at' => now(),
                    'restored_by' => Auth::id(),
                ]);

                $restoredCount++;
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Bulk restore failed', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Bulk restore failed.');
        }

        return redirect()
            ->route('developer.maintenance.trash')
            ->with('success', "{$restoredCount} maintenance schedule(s) restored successfully.");
    }

    public function bulkForceDelete(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        $data = $request->validate([
            'items'   => 'required|array',
            'items.*' => 'integer|exists:maintenances,id',
        ]);

        $deletedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($data['items'] as $id) {
                $maintenance = Maintenance::onlyTrashed()->find($id);

                if (!$maintenance) {
                    continue;
                }

                $referenceId = $maintenance->reference_id;

                $maintenance->logs()->delete();
                $maintenance->affectedUsers()->delete();
                $maintenance->forceDelete();

                Log::info('Maintenance schedule permanently deleted (bulk)', [
                    'reference_id' => $referenceId,
                    'deleted_by'   => Auth::id(),
                ]);

                $deletedCount++;
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Bulk force delete failed', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Bulk force delete failed.');
        }

        return redirect()
            ->route('developer.maintenance.trash')
            ->with('success', "{$deletedCount} maintenance schedule(s) permanently deleted.");
    }

    public function emptyTrash(Request $request)
    {
        if ($resp = $this->authorizeOrAbort()) {
            return $resp;
        }

        DB::beginTransaction();

        try {
            $maintenances = Maintenance::onlyTrashed()->get();
            $count        = $maintenances->count();

            foreach ($maintenances as $maintenance) {
                $maintenance->logs()->delete();
                $maintenance->affectedUsers()->delete();
                $maintenance->forceDelete();
            }

            DB::commit();

            Log::info('Trash emptied', [
                'count'      => $count,
                'emptied_by' => Auth::id(),
            ]);

            return redirect()
                ->route('developer.maintenance.trash')
                ->with('success', "Trash emptied successfully. {$count} item(s) permanently deleted.");

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to empty trash', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Failed to empty trash.');
        }
    }

    public function trashDetails($id)
    {
        if ($resp = $this->authorizeOrAbort(true)) {
            return $resp;
        }

        try {
            $maintenance = Maintenance::onlyTrashed()
                ->with(['creator', 'approver', 'completer', 'deletedBy'])
                ->findOrFail($id);

            $html = view('developer.maintenance.partials.trash-details', compact('maintenance'))->render();

            return response()->json([
                'success' => true,
                'html'    => $html,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch maintenance details for trash', [
                'maintenance_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load details',
            ], 500);
        }
    }

    /* ============================================================
     | PRIVATE HELPERS
     * ============================================================ */

    private function writeLog(Maintenance $maintenance, string $action, string $details, array $metadata = []): void
    {
        $maintenance->logs()->create([
            'action'       => $action,
            'details'      => $details,
            'metadata'     => $metadata,
            'performed_by' => Auth::id(),
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);
    }

    private function getAllowedOperationsForImpact(string $impactLevel): array
    {
        $operations = ['view_dashboard', 'read_profile', 'basic_read'];

        return match ($impactLevel) {
            'low' => array_merge($operations, [
                'create_records', 'update_records', 'delete_records',
                'process_payments', 'send_messages',
            ]),
            'medium' => array_merge($operations, [
                'create_records', 'update_records', 'read_reports',
            ]),
            'high' => array_merge($operations, ['read_only']),
            'critical' => ['emergency_read', 'system_status'],
            default => $operations,
        };
    }

    /**
     * Single query instead of N queries for the affected user count.
     */
    private function calculateEstimatedAffectedUsers(array $userTypes): int
    {
        if (empty($userTypes)) {
            return 0;
        }

        return User::whereIn('type', array_map('intval', $userTypes))
            ->where('status', User::STATUS_ACTIVE)
            ->count();
    }

    private function calculateMaintenanceImpact(Maintenance $maintenance): array
    {
        $affectedUsers     = $maintenance->affectedUsers()->count();
        $acknowledgedUsers = $maintenance->affectedUsers()->where('acknowledged', true)->count();

        $estimated = $maintenance->estimated_affected_users;
        $actual    = $maintenance->actual_affected_users;

        $accuracy = $estimated > 0 ? min(100, ($actual / $estimated) * 100) : 0;

        $notifiedUsers   = $maintenance->affectedUsers()->whereNotNull('notified_at')->count();
        $notificationRate = $affectedUsers > 0 ? ($notifiedUsers / $affectedUsers) * 100 : 0;
        $acknowledgmentRate = $notifiedUsers > 0 ? ($acknowledgedUsers / $notifiedUsers) * 100 : 0;

        $durationMetrics = [];
        if ($maintenance->status === Maintenance::STATUS_COMPLETED) {
            $estimatedDuration = $maintenance->estimated_duration_minutes;
            $actualDuration    = $maintenance->actual_duration_minutes;
            $durationAccuracy  = $estimatedDuration > 0
                ? min(100, ($actualDuration / $estimatedDuration) * 100)
                : 100;

            $durationMetrics = [
                'estimated_minutes'   => $estimatedDuration,
                'actual_minutes'      => $actualDuration,
                'accuracy_percentage' => round($durationAccuracy, 1),
                'within_estimate'     => $maintenance->completed_within_estimate,
                'downtime_minutes'    => $maintenance->downtime_minutes,
            ];
        }

        return [
            'user_impact' => [
                'estimated_users'          => $estimated,
                'actual_users'             => $actual,
                'accuracy_percentage'      => round($accuracy, 1),
                'affected_users_count'     => $affectedUsers,
                'notified_users_count'     => $notifiedUsers,
                'notification_rate'        => round($notificationRate, 1),
                'acknowledged_users_count' => $acknowledgedUsers,
                'acknowledgment_rate'      => round($acknowledgmentRate, 1),
            ],
            'duration_metrics' => $durationMetrics,
            'financial_impact' => $this->estimateFinancialImpact($maintenance),
        ];
    }

    private function estimateFinancialImpact(Maintenance $maintenance): array
    {
        $affectedUsers = $maintenance->actual_affected_users ?: $maintenance->estimated_affected_users;
        $downtimeHours = $maintenance->downtime_minutes ? $maintenance->downtime_minutes / 60 : 0;

        $baseImpactPerUserPerHour = (float) config('maintenance.financial.base_impact_per_user_hour', 0.50);

        $estimatedCost = $affectedUsers * $downtimeHours * $baseImpactPerUserPerHour;

        $impactMultiplier = match ($maintenance->impact_level) {
            'critical' => 2.0,
            'high'     => 1.5,
            'medium'   => 1.2,
            'low'      => 1.0,
            default    => 1.0,
        };

        $adjustedCost = $estimatedCost * $impactMultiplier;

        return [
            'affected_users'             => $affectedUsers,
            'downtime_hours'             => round($downtimeHours, 2),
            'base_impact_per_user_hour'  => $baseImpactPerUserPerHour,
            'impact_multiplier'          => $impactMultiplier,
            'estimated_cost'             => round($adjustedCost, 2),
            'impact_level'               => match (true) {
                $adjustedCost > 1000 => 'high',
                $adjustedCost > 500  => 'medium',
                $adjustedCost > 100  => 'low',
                default              => 'minimal',
            },
        ];
    }

    /* ============================================================
     | NOTIFICATION HELPERS
     * ============================================================ */

    private function notifyUsersOfScheduledMaintenance(Maintenance $maintenance): void
    {
        $userTypes = $maintenance->affected_user_types ?? [];
        $channels  = $maintenance->notification_channels ?? ['email', 'in_app'];

        $totalNotified = 0;

        foreach ($userTypes as $userType) {
            $users = User::where('type', (int) $userType)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            foreach ($users as $user) {
                MaintenanceAffectedUser::firstOrCreate(
                    [
                        'maintenance_id' => $maintenance->id,
                        'user_id'        => $user->id,
                    ],
                    [
                        'user_type'        => $user->type,
                        'affected_modules' => $maintenance->affected_modules,
                    ]
                );

                foreach ($channels as $channel) {
                    try {
                        $this->notificationService->sendMaintenanceNotification(
                            $user,
                            $channel,
                            [
                                'maintenance_title'  => $maintenance->title,
                                'description'        => $maintenance->user_impact_description,
                                'scheduled_start'    => optional($maintenance->scheduled_start)->format('Y-m-d H:i:s'),
                                'scheduled_end'      => optional($maintenance->scheduled_end)->format('Y-m-d H:i:s'),
                                'estimated_duration' => $maintenance->estimated_duration_minutes . ' minutes',
                                'affected_modules'   => $maintenance->affected_modules_list,
                                'impact_level'       => $maintenance->impact_level,
                                'type'               => $maintenance->maintenance_type,
                                'reference_id'       => $maintenance->reference_id,
                                'allowed_operations' => $maintenance->allowed_operations,
                                'contact_support'    => true,
                            ]
                        );
                    } catch (\Throwable $e) {
                        Log::warning("Failed to send {$channel} notification to user {$user->id}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $totalNotified++;
            }
        }

        $maintenance->update(['notification_sent_at' => now()]);

        $maintenance->logs()->create([
            'action'       => 'notified',
            'details'      => 'Users notified of scheduled maintenance',
            'metadata'     => [
                'channels'         => $channels,
                'user_types'       => $userTypes,
                'user_types_count' => count($userTypes),
                'users_notified'   => $totalNotified,
            ],
            'performed_by' => Auth::id(),
        ]);
    }

    private function notifyUsersOfUpdate(Maintenance $maintenance): void
    {
        $affectedUsers = $maintenance->affectedUsers()->with('user')->get();
        $channels      = $maintenance->notification_channels ?? ['email', 'in_app'];

        foreach ($affectedUsers as $affectedUser) {
            $user = $affectedUser->user;
            if (!$user) {
                continue;
            }

            foreach ($channels as $channel) {
                try {
                    $this->notificationService->sendMaintenanceUpdateNotification(
                        $user,
                        $channel,
                        [
                            'maintenance_title'  => $maintenance->title,
                            'reference_id'       => $maintenance->reference_id,
                            'new_start'          => optional($maintenance->scheduled_start)->format('Y-m-d H:i:s'),
                            'new_end'            => optional($maintenance->scheduled_end)->format('Y-m-d H:i:s'),
                            'estimated_duration' => $maintenance->estimated_duration_minutes . ' minutes',
                            'impact_level'       => $maintenance->impact_level,
                            'affected_modules'   => $maintenance->affected_modules_list,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning("Failed to send update notification to user {$affectedUser->user_id}", [
                        'channel' => $channel,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }

        $maintenance->logs()->create([
            'action'       => 'update_notified',
            'details'      => 'Users notified of schedule update',
            'metadata'     => [
                'channels'   => $channels,
                'user_count' => $affectedUsers->count(),
            ],
            'performed_by' => Auth::id(),
        ]);
    }

    private function notifyUsersOfCompletion(Maintenance $maintenance): void
    {
        $affectedUsers = $maintenance->affectedUsers()->with('user')->get();
        $channels      = $maintenance->notification_channels ?? ['email', 'in_app'];

        foreach ($affectedUsers as $affectedUser) {
            $user = $affectedUser->user;
            if (!$user) {
                continue;
            }

            foreach ($channels as $channel) {
                try {
                    $this->notificationService->sendMaintenanceCompletionNotification(
                        $user,
                        $channel,
                        [
                            'maintenance_title' => $maintenance->title,
                            'completed_at'      => optional($maintenance->actual_end)->format('Y-m-d H:i:s'),
                            'actual_duration'   => $maintenance->actual_duration_minutes . ' minutes',
                            'downtime'          => $maintenance->downtime_minutes . ' minutes',
                            'summary'           => 'Maintenance completed successfully. All systems are now operational.',
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning("Failed to send completion notification to user {$affectedUser->user_id}", [
                        'channel' => $channel,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function notifyUsersOfCancellation(Maintenance $maintenance): void
    {
        $affectedUsers = $maintenance->affectedUsers()->with('user')->get();
        $channels      = $maintenance->notification_channels ?? ['email', 'in_app'];

        foreach ($affectedUsers as $affectedUser) {
            $user = $affectedUser->user;
            if (!$user) {
                continue;
            }

            foreach ($channels as $channel) {
                try {
                    $this->notificationService->sendMaintenanceCancellationNotification(
                        $user,
                        $channel,
                        [
                            'maintenance_title' => $maintenance->title,
                            'reference_id'      => $maintenance->reference_id,
                            'cancelled_at'      => optional($maintenance->cancelled_at)->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                            'reason'            => $maintenance->cancellation_reason,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning("Failed to send cancellation notification to user {$affectedUser->user_id}", [
                        'channel' => $channel,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }

        $maintenance->logs()->create([
            'action'       => 'cancellation_notified',
            'details'      => 'Users notified of cancellation',
            'metadata'     => [
                'channels'   => $channels,
                'user_count' => $affectedUsers->count(),
            ],
            'performed_by' => Auth::id(),
        ]);
    }
}