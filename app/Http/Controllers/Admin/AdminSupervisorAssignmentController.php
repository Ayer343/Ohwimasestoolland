<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecuritySupervisorAssignment;
use App\Models\User;
use App\Models\SecurityPost;
use App\Models\SecurityShift;
use App\Models\SecuritySchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminSupervisorAssignmentController extends Controller
{
    /**
     * Display a listing of supervisor assignments (Admin Full View)
     */
    public function index(Request $request)
    {
        $query = SecuritySupervisorAssignment::with(['user', 'post', 'assignedBy']);

        // Admin can see ALL assignments
        $this->applyFilters($query, $request);
        
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $assignments = $query->paginate(20);

        // Get comprehensive statistics for admin
        $stats = $this->getAdminStats();

        // ✅ UPDATED: Get supervisors with supervisor capabilities
        // Now uses can_be_supervisor flag only (no levels)
        $supervisors = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('can_be_supervisor', true)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'can_be_supervisor']);

        $posts = SecurityPost::active()->orderBy('name')->get(['id', 'name', 'code']);

        $supervisorTypes = [
            'post_supervisor' => 'Post Supervisor',
            'shift_supervisor' => 'Shift Supervisor',
            'area_supervisor' => 'Area Supervisor',
            'relief_supervisor' => 'Relief Supervisor',
            'training_supervisor' => 'Supervisor in Training',
        ];

        return view('admin.supervisor-assignments.index', compact(
            'assignments',
            'stats',
            'supervisors',
            'posts',
            'supervisorTypes',
            'request'
        ));
    }

    /**
     * Show the form for creating a new supervisor assignment (Admin)
     */
    public function create()
    {
        // ✅ UPDATED: Get security personnel who are eligible to be supervisors
        // Uses can_be_supervisor flag only (no levels)
        $supervisors = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('can_be_supervisor', true)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'can_be_supervisor']);

        $posts = SecurityPost::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel']);

        $shifts = SecurityShift::active()
            ->orderBy('name')
            ->get(['id', 'name', 'start_time', 'end_time', 'category']);

        $supervisorTypes = [
            'post_supervisor' => 'Post Supervisor',
            'shift_supervisor' => 'Shift Supervisor',
            'area_supervisor' => 'Area Supervisor',
            'relief_supervisor' => 'Relief Supervisor',
            'training_supervisor' => 'Supervisor in Training',
        ];

        $daysOfWeek = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        $permissionOptions = [
            'can_override_checkins' => 'Override Check-ins',
            'can_approve_swaps' => 'Approve Shift Swaps',
            'can_approve_overtime' => 'Approve Overtime',
            'can_review_incidents' => 'Review Incidents',
            'can_verify_checkins' => 'Verify Check-ins',
            'can_request_backup' => 'Request Backup',
            'can_approve_breaks' => 'Approve Breaks',
            'can_escalate_issues' => 'Escalate Issues',
            'can_view_all_schedules' => 'View All Schedules',
            'can_edit_schedules' => 'Edit Schedules',
        ];

        return view('admin.supervisor-assignments.create', compact(
            'supervisors',
            'posts',
            'shifts',
            'supervisorTypes',
            'daysOfWeek',
            'permissionOptions'
        ));
    }

    /**
     * Store a newly created supervisor assignment (Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'security_post_id' => 'nullable|exists:security_posts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'supervisor_type' => 'required|in:post_supervisor,shift_supervisor,area_supervisor,relief_supervisor,training_supervisor',
            'shift_ids' => 'nullable|array',
            'shift_ids.*' => 'exists:security_shifts,id',
            'applicable_days' => 'nullable|array',
            'applicable_days.*' => 'integer|between:1,7',
            'is_primary_supervisor' => 'boolean',
            
            // Permissions
            'can_override_checkins' => 'boolean',
            'can_approve_swaps' => 'boolean',
            'can_approve_overtime' => 'boolean',
            'can_review_incidents' => 'boolean',
            'can_verify_checkins' => 'boolean',
            'can_request_backup' => 'boolean',
            'can_approve_breaks' => 'boolean',
            'can_escalate_issues' => 'boolean',
            'can_view_all_schedules' => 'boolean',
            'can_edit_schedules' => 'boolean',
            
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = User::find($request->user_id);
        
        // ✅ UPDATED: Check eligibility using can_be_supervisor flag only
        if (!$user->isSecurityPersonnel() || !$user->can_be_supervisor) {
            return redirect()->back()
                ->with('error', 'Selected user is not eligible to be a supervisor.')
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Admin can assign ANY supervisor type, including area supervisor
            $assignment = $this->createAssignment($request, $user);
            
            Log::info('Admin created supervisor assignment', [
                'assignment_id' => $assignment->id,
                'admin_id' => Auth::id(),
                'supervisor_id' => $user->id,
                'type' => $request->supervisor_type,
            ]);

            DB::commit();

            return redirect()->route('admin.supervisor-assignments.show', $assignment)
                ->with('success', 'Supervisor assigned successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin failed to create assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to assign supervisor. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified supervisor assignment (Admin)
     */
    public function show(SecuritySupervisorAssignment $assignment)
    {
        $assignment->load(['user', 'post', 'assignedBy']);

        // Admin gets FULL details including sensitive data
        $supervisionStats = $this->getDetailedStats($assignment);
        $pendingApprovals = $this->getPendingApprovals($assignment);
        $recentSchedules = $this->getRecentSchedules($assignment);
        $verificationLogs = $this->getVerificationLogs($assignment);
        
        // Admin-specific data
        $auditTrail = $assignment->metadata['update_history'] ?? [];
        $relatedAssignments = $this->getRelatedAssignments($assignment);

        return view('admin.supervisor-assignments.show', compact(
            'assignment',
            'supervisionStats',
            'pendingApprovals',
            'recentSchedules',
            'verificationLogs',
            'auditTrail',
            'relatedAssignments'
        ));
    }

    /**
     * Show the form for editing the specified assignment (Admin)
     */
    public function edit(SecuritySupervisorAssignment $assignment)
    {
        // ✅ UPDATED: Get security personnel who are eligible to be supervisors
        $supervisors = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('can_be_supervisor', true)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'can_be_supervisor']);

        $posts = SecurityPost::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $shifts = SecurityShift::active()
            ->orderBy('name')
            ->get(['id', 'name', 'start_time', 'end_time']);

        $supervisorTypes = [
            'post_supervisor' => 'Post Supervisor',
            'shift_supervisor' => 'Shift Supervisor',
            'area_supervisor' => 'Area Supervisor',
            'relief_supervisor' => 'Relief Supervisor',
            'training_supervisor' => 'Supervisor in Training',
        ];

        $daysOfWeek = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        $permissionOptions = [
            'can_override_checkins' => 'Override Check-ins',
            'can_approve_swaps' => 'Approve Shift Swaps',
            'can_approve_overtime' => 'Approve Overtime',
            'can_review_incidents' => 'Review Incidents',
            'can_verify_checkins' => 'Verify Check-ins',
            'can_request_backup' => 'Request Backup',
            'can_approve_breaks' => 'Approve Breaks',
            'can_escalate_issues' => 'Escalate Issues',
            'can_view_all_schedules' => 'View All Schedules',
            'can_edit_schedules' => 'Edit Schedules',
        ];

        return view('admin.supervisor-assignments.edit', compact(
            'assignment',
            'supervisors',
            'posts',
            'shifts',
            'supervisorTypes',
            'daysOfWeek',
            'permissionOptions'
        ));
    }

    /**
     * Update the specified assignment (Admin)
     */
    public function update(Request $request, SecuritySupervisorAssignment $assignment)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'security_post_id' => 'nullable|exists:security_posts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'supervisor_type' => 'required|in:post_supervisor,shift_supervisor,area_supervisor,relief_supervisor,training_supervisor',
            'shift_ids' => 'nullable|array',
            'shift_ids.*' => 'exists:security_shifts,id',
            'applicable_days' => 'nullable|array',
            'applicable_days.*' => 'integer|between:1,7',
            'is_primary_supervisor' => 'boolean',
            'is_active' => 'boolean',
            'can_override_checkins' => 'boolean',
            'can_approve_swaps' => 'boolean',
            'can_approve_overtime' => 'boolean',
            'can_review_incidents' => 'boolean',
            'can_verify_checkins' => 'boolean',
            'can_request_backup' => 'boolean',
            'can_approve_breaks' => 'boolean',
            'can_escalate_issues' => 'boolean',
            'can_view_all_schedules' => 'boolean',
            'can_edit_schedules' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Admin can update ANY assignment
            $this->updateAssignment($request, $assignment);

            Log::info('Admin updated supervisor assignment', [
                'assignment_id' => $assignment->id,
                'admin_id' => Auth::id(),
                'changes' => $request->except(['_token', '_method'])
            ]);

            DB::commit();

            return redirect()->route('admin.supervisor-assignments.show', $assignment)
                ->with('success', 'Supervisor assignment updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin failed to update assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to update supervisor assignment.')
                ->withInput();
        }
    }

    /**
     * Remove the specified assignment (Admin)
     */
    public function destroy(SecuritySupervisorAssignment $assignment)
    {
        try {
            $assignment->delete();

            Log::info('Admin deleted supervisor assignment', [
                'assignment_id' => $assignment->id,
                'admin_id' => Auth::id(),
            ]);

            return redirect()->route('admin.supervisor-assignments.index')
                ->with('success', 'Supervisor assignment moved to trash successfully.');

        } catch (\Exception $e) {
            Log::error('Admin failed to delete assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to delete supervisor assignment.');
        }
    }

    /**
     * Admin-specific: Display trashed assignments
     */
    public function trash(Request $request)
    {
        $assignments = SecuritySupervisorAssignment::onlyTrashed()
            ->with(['user', 'post', 'assignedBy'])
            ->orderBy('deleted_at', 'desc')
            ->paginate(20);

        return view('admin.supervisor-assignments.trash', compact('assignments'));
    }

    /**
     * Admin-specific: Restore a trashed assignment
     */
    public function restore($id)
    {
        try {
            $assignment = SecuritySupervisorAssignment::onlyTrashed()->findOrFail($id);
            $assignment->restore();

            Log::info('Admin restored supervisor assignment', [
                'assignment_id' => $assignment->id,
                'admin_id' => Auth::id(),
            ]);

            return redirect()->route('admin.supervisor-assignments.trash')
                ->with('success', 'Supervisor assignment restored successfully.');

        } catch (\Exception $e) {
            Log::error('Admin failed to restore assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to restore supervisor assignment.');
        }
    }

    /**
     * Admin-specific: Permanently delete a trashed assignment
     */
    public function forceDelete($id)
    {
        try {
            $assignment = SecuritySupervisorAssignment::onlyTrashed()->findOrFail($id);
            $assignment->forceDelete();

            Log::info('Admin permanently deleted supervisor assignment', [
                'assignment_id' => $id,
                'admin_id' => Auth::id(),
            ]);

            return redirect()->route('admin.supervisor-assignments.trash')
                ->with('success', 'Supervisor assignment permanently deleted.');

        } catch (\Exception $e) {
            Log::error('Admin failed to permanently delete assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to permanently delete supervisor assignment.');
        }
    }

    /**
     * Admin-specific: Bulk actions on multiple assignments
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete,extend',
            'ids' => 'required|array',
            'ids.*' => 'exists:security_supervisor_assignments,id',
            'extend_days' => 'required_if:action,extend|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $result = $this->processBulkAction($request);
            
            Log::info('Admin performed bulk action', [
                'action' => $request->action,
                'processed' => $result['processed'],
                'failed' => count($result['failed']),
                'admin_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'processed' => $result['processed'],
                'failed' => $result['failed']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin bulk action failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk action.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin-specific: Bulk restore from trash
     */
    public function bulkRestore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:security_supervisor_assignments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $restored = SecuritySupervisorAssignment::onlyTrashed()
                ->whereIn('id', $request->ids)
                ->restore();

            Log::info('Admin bulk restored assignments', [
                'count' => $restored,
                'admin_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$restored} assignment(s) restored successfully."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin bulk restore failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore assignments.'
            ], 500);
        }
    }

    /**
     * Admin-specific: Bulk force delete from trash
     */
    public function bulkForceDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:security_supervisor_assignments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $deleted = SecuritySupervisorAssignment::onlyTrashed()
                ->whereIn('id', $request->ids)
                ->forceDelete();

            Log::info('Admin bulk force deleted assignments', [
                'count' => $deleted,
                'admin_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$deleted} assignment(s) permanently deleted."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin bulk force delete failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete assignments.'
            ], 500);
        }
    }

    // ==================== ADMIN HELPER METHODS ====================

    private function applyFilters($query, $request)
    {
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'expired') {
                $query->expired();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('supervisor_type')) {
            $query->where('supervisor_type', $request->supervisor_type);
        }

        if ($request->filled('post_id')) {
            $query->where('security_post_id', $request->post_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('post', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date);
        }
    }

    private function getAdminStats()
    {
        return [
            'total_assignments' => SecuritySupervisorAssignment::count(),
            'active_assignments' => SecuritySupervisorAssignment::active()->count(),
            'expired_assignments' => SecuritySupervisorAssignment::expired()->count(),
            'expiring_soon' => SecuritySupervisorAssignment::expiringSoon()->count(),
            'by_type' => SecuritySupervisorAssignment::selectRaw('supervisor_type, count(*) as count')
                ->groupBy('supervisor_type')
                ->pluck('count', 'supervisor_type')
                ->toArray(),
            'by_status' => [
                'active' => SecuritySupervisorAssignment::where('is_active', true)->count(),
                'inactive' => SecuritySupervisorAssignment::where('is_active', false)->count(),
            ],
            'by_post' => SecuritySupervisorAssignment::whereNotNull('security_post_id')
                ->selectRaw('security_post_id, count(*) as count')
                ->groupBy('security_post_id')
                ->with('post')
                ->get()
                ->pluck('count', 'post.name')
                ->toArray(),
        ];
    }

    private function createAssignment($request, $user)
    {
        // Handle primary supervisor conflict
        if ($request->boolean('is_primary_supervisor') && $request->security_post_id) {
            SecuritySupervisorAssignment::where('security_post_id', $request->security_post_id)
                ->where('is_primary_supervisor', true)
                ->where('is_active', true)
                ->update(['is_primary_supervisor' => false]);
        }

        $defaultPermissions = SecuritySupervisorAssignment::getDefaultPermissions($request->supervisor_type);

        $assignmentData = [
            'user_id' => $request->user_id,
            'security_post_id' => $request->security_post_id,
            'assigned_by' => Auth::id(),
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'supervisor_type' => $request->supervisor_type,
            'shift_ids' => $request->shift_ids,
            'applicable_days' => $request->applicable_days,
            'is_active' => true,
            'is_primary_supervisor' => $request->boolean('is_primary_supervisor', false),
            'notes' => $request->notes,
            'metadata' => [
                'created_via' => 'admin_manual',
                'created_at' => now()->toDateTimeString(),
                'admin_id' => Auth::id(),
                'admin_name' => Auth::user()->name,
                // ✅ UPDATED: No longer storing supervisor_level or supervisor_score
                // These are managed through the Supervisor Assignment system only
                'assignment_type' => $request->security_post_id ? 'post_specific' : 'role_only',
                'post_assigned' => (bool) $request->security_post_id,
                'schedule_auto_created' => false,
            ],
        ];

        foreach ($defaultPermissions as $permission => $defaultValue) {
            $assignmentData[$permission] = $request->has($permission) 
                ? $request->boolean($permission) 
                : $defaultValue;
        }

        return SecuritySupervisorAssignment::create($assignmentData);
    }

    private function updateAssignment($request, $assignment)
    {
        if ($request->boolean('is_primary_supervisor') && 
            $request->security_post_id && 
            !$assignment->is_primary_supervisor) {
            
            SecuritySupervisorAssignment::where('security_post_id', $request->security_post_id)
                ->where('is_primary_supervisor', true)
                ->where('is_active', true)
                ->where('id', '!=', $assignment->id)
                ->update(['is_primary_supervisor' => false]);
        }

        $changes = [];
        foreach ($request->all() as $key => $value) {
            if ($assignment->$key != $value && !in_array($key, ['_token', '_method'])) {
                $changes[$key] = ['old' => $assignment->$key, 'new' => $value];
            }
        }

        $metadata = $assignment->metadata ?? [];
        $metadata['update_history'][] = [
            'updated_at' => now()->toDateTimeString(),
            'updated_by' => Auth::id(),
            'updated_by_name' => Auth::user()->name,
            'role' => 'admin',
            'changes' => $changes,
        ];

        $assignment->update(array_merge(
            $request->except(['_token', '_method']),
            ['metadata' => $metadata]
        ));
    }

    private function getDetailedStats($assignment)
    {
        try {
            return $assignment->getSupervisionHistory();
        } catch (\Exception $e) {
            return [
                'total_days' => 0,
                'schedules_overseen' => 0,
                'verifications_performed' => 0,
                'approvals_given' => 0,
                'incidents_reported' => 0,
                'incidents_resolved' => 0,
                'backup_requests' => 0,
                'overtime_approved' => 0,
                'swaps_approved' => 0,
            ];
        }
    }

    private function getPendingApprovals($assignment)
    {
        try {
            return $assignment->getPendingApprovals();
        } catch (\Exception $e) {
            return [
                'swap_requests' => 0,
                'overtime_requests' => 0,
                'pending_verifications' => 0,
            ];
        }
    }

    private function getRecentSchedules($assignment)
    {
        try {
            return SecuritySchedule::where('security_post_id', $assignment->security_post_id)
                ->whereBetween('assignment_date', [
                    $assignment->start_date,
                    $assignment->end_date ?? now()
                ])
                ->with(['securityUser', 'shift'])
                ->orderBy('assignment_date', 'desc')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    private function getVerificationLogs($assignment)
    {
        // Admin can see ALL verification logs
        try {
            if (!\Schema::hasTable('verification_logs')) {
                return collect([]);
            }

            $query = \DB::table('verification_logs');
            
            $supervisorIdColumn = null;
            $possibleColumns = ['verified_by', 'supervisor_id', 'user_id'];
            
            foreach ($possibleColumns as $column) {
                if (\Schema::hasColumn('verification_logs', $column)) {
                    $supervisorIdColumn = $column;
                    break;
                }
            }
            
            if (!$supervisorIdColumn) {
                return collect([]);
            }
            
            $query->where($supervisorIdColumn, $assignment->user_id);
            
            if (\Schema::hasColumn('verification_logs', 'created_at')) {
                $query->orderBy('created_at', 'desc');
            }
            
            return $query->limit(50)->get();
            
        } catch (\Exception $e) {
            Log::warning('Error getting verification logs: ' . $e->getMessage());
            return collect([]);
        }
    }

    private function getRelatedAssignments($assignment)
    {
        // Get other assignments by the same supervisor
        return SecuritySupervisorAssignment::where('user_id', $assignment->user_id)
            ->where('id', '!=', $assignment->id)
            ->where('is_active', true)
            ->with(['post'])
            ->get();
    }

    private function processBulkAction($request)
    {
        $assignments = SecuritySupervisorAssignment::whereIn('id', $request->ids)->get();
        $processed = 0;
        $failed = [];

        foreach ($assignments as $assignment) {
            try {
                switch ($request->action) {
                    case 'activate':
                        $assignment->update(['is_active' => true]);
                        break;
                    case 'deactivate':
                        $assignment->update(['is_active' => false]);
                        break;
                    case 'delete':
                        $assignment->delete();
                        break;
                    case 'extend':
                        if ($assignment->end_date) {
                            $newEndDate = Carbon::parse($assignment->end_date)
                                ->addDays($request->extend_days);
                            $assignment->extend($newEndDate, Auth::id());
                            
                            $metadata = $assignment->metadata ?? [];
                            $metadata['bulk_extension_reason'] = $request->reason;
                            $assignment->metadata = $metadata;
                            $assignment->save();
                        }
                        break;
                }
                $processed++;
            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $assignment->id,
                    'name' => $assignment->user->name ?? 'Unknown',
                    'reason' => $e->getMessage()
                ];
            }
        }

        $message = "{$processed} assignment(s) processed successfully.";
        if (!empty($failed)) {
            $message .= " " . count($failed) . " failed.";
        }

        return [
            'message' => $message,
            'processed' => $processed,
            'failed' => $failed
        ];
    }

    // ==================== ADDITIONAL METHODS FOR DISPLAY ====================

    /**
     * Get eligible security personnel for supervisor assignment (AJAX)
     * Used for dynamic dropdowns
     */
    public function getEligiblePersonnel(Request $request)
    {
        try {
            $search = $request->get('q', '');
            
            $personnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('can_be_supervisor', true)
                ->where('status', User::STATUS_ACTIVE)
                ->when($search, function($query, $search) {
                    return $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'email', 'can_be_supervisor']);

            return response()->json([
                'success' => true,
                'data' => $personnel->map(function($user) {
                    return [
                        'id' => $user->id,
                        'text' => $user->name . ' (' . $user->email . ')',
                        'name' => $user->name,
                        'email' => $user->email,
                        'can_be_supervisor' => $user->can_be_supervisor,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get eligible personnel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load personnel.'
            ], 500);
        }
    }

    /**
     * Get supervisor assignment statistics for dashboard
     */
    public function getStats()
    {
        try {
            $stats = $this->getAdminStats();
            
            // Add additional stats
            $stats['supervisor_count'] = User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('can_be_supervisor', true)
                ->where('status', User::STATUS_ACTIVE)
                ->count();
            
            $stats['active_supervisors'] = SecuritySupervisorAssignment::where('is_active', true)
                ->distinct('user_id')
                ->count('user_id');

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics.'
            ], 500);
        }
    }

    /**
     * Get supervisor assignment details for AJAX modal
     */
    public function getAssignmentDetails($id)
    {
        try {
            $assignment = SecuritySupervisorAssignment::with(['user', 'post', 'assignedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'assignment' => [
                    'id' => $assignment->id,
                    'supervisor' => [
                        'id' => $assignment->user->id,
                        'name' => $assignment->user->name,
                        'email' => $assignment->user->email,
                        'can_be_supervisor' => $assignment->user->can_be_supervisor,
                    ],
                    'post' => $assignment->post ? [
                        'id' => $assignment->post->id,
                        'name' => $assignment->post->name,
                        'code' => $assignment->post->code,
                    ] : null,
                    'type' => $assignment->supervisor_type,
                    'type_label' => $this->getSupervisorTypeLabel($assignment->supervisor_type),
                    'start_date' => $assignment->start_date->format('Y-m-d'),
                    'end_date' => $assignment->end_date ? $assignment->end_date->format('Y-m-d') : null,
                    'is_active' => $assignment->is_active,
                    'is_primary' => $assignment->is_primary_supervisor,
                    'permissions' => $this->getPermissionLabels($assignment),
                    'notes' => $assignment->notes,
                    'created_at' => $assignment->created_at->format('Y-m-d H:i:s'),
                    'assigned_by' => $assignment->assignedBy ? $assignment->assignedBy->name : null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get assignment details: ' . $e->getMessage(), [
                'assignment_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load assignment details.'
            ], 500);
        }
    }

    /**
     * Get supervisor type label
     */
    private function getSupervisorTypeLabel($type): string
    {
        $types = [
            'post_supervisor' => 'Post Supervisor',
            'shift_supervisor' => 'Shift Supervisor',
            'area_supervisor' => 'Area Supervisor',
            'relief_supervisor' => 'Relief Supervisor',
            'training_supervisor' => 'Supervisor in Training',
        ];
        return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Get permission labels for display
     */
    private function getPermissionLabels($assignment): array
    {
        $permissions = [];
        $permissionMap = [
            'can_override_checkins' => 'Override Check-ins',
            'can_approve_swaps' => 'Approve Shift Swaps',
            'can_approve_overtime' => 'Approve Overtime',
            'can_review_incidents' => 'Review Incidents',
            'can_verify_checkins' => 'Verify Check-ins',
            'can_request_backup' => 'Request Backup',
            'can_approve_breaks' => 'Approve Breaks',
            'can_escalate_issues' => 'Escalate Issues',
            'can_view_all_schedules' => 'View All Schedules',
            'can_edit_schedules' => 'Edit Schedules',
        ];

        foreach ($permissionMap as $field => $label) {
            if ($assignment->$field) {
                $permissions[] = $label;
            }
        }

        return $permissions;
    }
}