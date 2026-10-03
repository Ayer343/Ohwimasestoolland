<?php

namespace App\Http\Controllers\Security;

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

class SupervisorAssignmentController extends Controller
{
    /**
     * Display a listing of supervisor assignments (Supervisor View)
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        
        // Get the supervisor's area assignments
        $areaAssignments = $this->getUserAreaAssignments($currentUser);
        
        if ($areaAssignments->isEmpty()) {
            return redirect()->route('security.dashboard')
                ->with('error', 'You do not have permission to view supervisor assignments.');
        }

        // Build query based on supervisor's scope
        $query = SecuritySupervisorAssignment::with(['user', 'post', 'assignedBy']);
        
        // If supervisor has area scope, show assignments within their area
        $this->applySupervisorScope($query, $currentUser, $areaAssignments);
        
        // Apply filters
        $this->applyFilters($query, $request);
        
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $assignments = $query->paginate(20);

        // Get statistics for supervisor
        $stats = $this->getSupervisorStats($currentUser, $areaAssignments);

        // ✅ UPDATED: Supervisors that this user can assign (using can_be_supervisor flag)
        $assignableSupervisors = $this->getAssignableSupervisors($currentUser);

        $posts = $this->getAccessiblePosts($currentUser, $areaAssignments);

        $supervisorTypes = $this->getAssignableTypes($currentUser);

        return view('security.supervisor-assignments.index', compact(
            'assignments',
            'stats',
            'assignableSupervisors',
            'posts',
            'supervisorTypes',
            'request'
        ));
    }

    /**
     * Show the form for creating a new supervisor assignment (Supervisor)
     */
    public function create()
    {
        $currentUser = Auth::user();
        
        // Check if user has area supervisor role
        if (!$this->isAreaSupervisor($currentUser)) {
            return redirect()->route('security.dashboard')
                ->with('error', 'Only Area Supervisors can create supervisor assignments.');
        }

        // ✅ UPDATED: Get supervisors that can be assigned (using can_be_supervisor flag)
        $assignableSupervisors = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->where('can_be_supervisor', true)
            ->where('id', '!=', $currentUser->id)
            ->orderBy('name')
            ->get(['id', 'name', 'can_be_supervisor'])
            ->map(function($user) {
                // Add eligibility label for display
                $user->eligibility_label = $user->can_be_supervisor ? '✅ Eligible' : '❌ Not Eligible';
                return $user;
            });

        Log::info('Assignable supervisors found', [
            'count' => $assignableSupervisors->count(),
            'ids' => $assignableSupervisors->pluck('id')->toArray(),
            'current_user' => $currentUser->id,
        ]);

        // Get posts within the supervisor's area
        $posts = $this->getAccessiblePosts($currentUser);

        $shifts = SecurityShift::active()
            ->orderBy('name')
            ->get(['id', 'name', 'start_time', 'end_time', 'category']);

        $supervisorTypes = $this->getAssignableTypes($currentUser);

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

        return view('security.supervisor-assignments.create', compact(
            'assignableSupervisors',
            'posts',
            'shifts',
            'supervisorTypes',
            'daysOfWeek',
            'permissionOptions'
        ));
    }

    /**
     * Store a newly created supervisor assignment (Supervisor)
     */
    public function store(Request $request)
    {
        $currentUser = Auth::user();
        
        // Verify area supervisor status
        if (!$this->isAreaSupervisor($currentUser)) {
            return redirect()->back()
                ->with('error', 'Only Area Supervisors can create supervisor assignments.')
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'security_post_id' => 'nullable|exists:security_posts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'supervisor_type' => 'required|in:' . implode(',', array_keys($this->getAssignableTypes($currentUser))),
            'shift_ids' => 'nullable|array',
            'shift_ids.*' => 'exists:security_shifts,id',
            'applicable_days' => 'nullable|array',
            'applicable_days.*' => 'integer|between:1,7',
            'is_primary_supervisor' => 'boolean',
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

        // ✅ UPDATED: Validate target user eligibility using can_be_supervisor
        $targetUser = User::find($request->user_id);
        if (!$targetUser->isSecurityPersonnel() || !$targetUser->can_be_supervisor) {
            return redirect()->back()
                ->with('error', 'Selected user is not eligible to be a supervisor.')
                ->withInput();
        }

        // Validate post accessibility
        if ($request->security_post_id) {
            $accessiblePosts = $this->getAccessiblePosts($currentUser)->pluck('id')->toArray();
            if (!in_array($request->security_post_id, $accessiblePosts)) {
                return redirect()->back()
                    ->with('error', 'You do not have access to the selected post.')
                    ->withInput();
            }
        }

        // Prevent assigning area supervisors
        if ($request->supervisor_type === 'area_supervisor') {
            return redirect()->back()
                ->with('error', 'Area Supervisors can only be assigned by Administrators.')
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Handle primary supervisor conflict
            if ($request->boolean('is_primary_supervisor') && $request->security_post_id) {
                SecuritySupervisorAssignment::where('security_post_id', $request->security_post_id)
                    ->where('is_primary_supervisor', true)
                    ->where('is_active', true)
                    ->update(['is_primary_supervisor' => false]);
            }

            $defaultPermissions = SecuritySupervisorAssignment::getDefaultPermissions($request->supervisor_type);

            // ✅ UPDATED: Removed supervisor_level_at_time and supervisor_score_at_time
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
                    'created_via' => 'supervisor_manual',
                    'created_at' => now()->toDateTimeString(),
                    'assigned_by_supervisor_id' => Auth::id(),
                    'assigned_by_supervisor_name' => Auth::user()->name,
                    'assigned_by_supervisor_type' => 'area_supervisor',
                    'is_eligible' => $targetUser->can_be_supervisor,
                    'assignment_scope' => $request->security_post_id ? 'post_specific' : 'role_only',
                    'post_assigned' => (bool) $request->security_post_id,
                    'schedule_auto_created' => false,
                ],
            ];

            foreach ($defaultPermissions as $permission => $defaultValue) {
                $assignmentData[$permission] = $request->has($permission) 
                    ? $request->boolean($permission) 
                    : $defaultValue;
            }

            $assignment = SecuritySupervisorAssignment::create($assignmentData);

            Log::info('Supervisor created assignment', [
                'assignment_id' => $assignment->id,
                'supervisor_id' => $targetUser->id,
                'assigned_by' => Auth::id(),
                'assigned_by_name' => Auth::user()->name,
                'type' => $request->supervisor_type,
                'post_assigned' => (bool) $request->security_post_id,
            ]);

            DB::commit();

            $message = 'Supervisor assigned successfully!';
            if ($request->security_post_id) {
                $post = SecurityPost::find($request->security_post_id);
                $postName = $post ? $post->name : 'Unknown Post';
                $message .= " Assigned to post: {$postName}.";
            } else {
                $message .= " (Role only - no post assigned).";
            }

            return redirect()->route('security.supervisor-assignments.show', $assignment)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Supervisor failed to create assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to assign supervisor. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified supervisor assignment (Supervisor View)
     */
    public function show(SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();
        
        // Verify access to this assignment
        if (!$this->canAccessAssignment($currentUser, $assignment)) {
            return redirect()->route('security.supervisor-assignments.index')
                ->with('error', 'You do not have access to this assignment.');
        }

        $assignment->load(['user', 'post', 'assignedBy']);

        // Get basic stats (limited view for supervisors)
        $supervisionStats = $this->getLimitedStats($assignment);
        $pendingApprovals = $this->getPendingApprovals($assignment);
        $recentSchedules = $this->getRecentSchedules($assignment);
        $verificationLogs = $this->getVerificationLogs($assignment);
        
        // Supervisor-specific data
        $relatedAssignments = $this->getRelatedAssignments($assignment);

        return view('security.supervisor-assignments.show', compact(
            'assignment',
            'supervisionStats',
            'pendingApprovals',
            'recentSchedules',
            'verificationLogs',
            'relatedAssignments'
        ));
    }

    /**
     * Show the form for editing the specified assignment (Supervisor)
     */
    public function edit(SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();
        
        // Verify access and edit permissions
        if (!$this->canEditAssignment($currentUser, $assignment)) {
            return redirect()->route('security.supervisor-assignments.index')
                ->with('error', 'You do not have permission to edit this assignment.');
        }

        // Only allow editing of non-area-supervisor assignments
        if ($assignment->supervisor_type === 'area_supervisor') {
            return redirect()->route('security.supervisor-assignments.show', $assignment)
                ->with('error', 'Area Supervisor assignments cannot be edited by supervisors.');
        }

        // ✅ UPDATED: Get supervisors that can be assigned (using can_be_supervisor flag)
        $assignableSupervisors = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('can_be_supervisor', true)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'can_be_supervisor']);

        $posts = $this->getAccessiblePosts($currentUser);

        $shifts = SecurityShift::active()
            ->orderBy('name')
            ->get(['id', 'name', 'start_time', 'end_time']);

        $supervisorTypes = $this->getAssignableTypes($currentUser);

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

        return view('security.supervisor-assignments.edit', compact(
            'assignment',
            'assignableSupervisors',
            'posts',
            'shifts',
            'supervisorTypes',
            'daysOfWeek',
            'permissionOptions'
        ));
    }

    /**
     * Update the specified assignment (Supervisor)
     */
    public function update(Request $request, SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();
        
        if (!$this->canEditAssignment($currentUser, $assignment)) {
            return redirect()->route('security.supervisor-assignments.index')
                ->with('error', 'You do not have permission to update this assignment.');
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'security_post_id' => 'nullable|exists:security_posts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'supervisor_type' => 'required|in:' . implode(',', array_keys($this->getAssignableTypes($currentUser))),
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

        // Prevent changing to area supervisor
        if ($request->supervisor_type === 'area_supervisor') {
            return redirect()->back()
                ->with('error', 'Cannot change assignment to Area Supervisor.')
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Track changes for audit
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
                'role' => 'supervisor',
                'changes' => $changes,
            ];

            $assignment->update(array_merge(
                $request->except(['_token', '_method']),
                ['metadata' => $metadata]
            ));

            Log::info('Supervisor updated assignment', [
                'assignment_id' => $assignment->id,
                'supervisor_id' => Auth::id(),
                'changes' => $changes,
            ]);

            DB::commit();

            return redirect()->route('security.supervisor-assignments.show', $assignment)
                ->with('success', 'Supervisor assignment updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Supervisor failed to update assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to update supervisor assignment.')
                ->withInput();
        }
    }

    /**
     * Remove the specified assignment (Supervisor)
     */
    public function destroy(SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();
        
        if (!$this->canDeleteAssignment($currentUser, $assignment)) {
            return redirect()->route('security.supervisor-assignments.index')
                ->with('error', 'You do not have permission to delete this assignment.');
        }

        try {
            $assignment->delete();

            Log::info('Supervisor deleted assignment', [
                'assignment_id' => $assignment->id,
                'supervisor_id' => Auth::id(),
            ]);

            return redirect()->route('security.supervisor-assignments.index')
                ->with('success', 'Supervisor assignment moved to trash successfully.');

        } catch (\Exception $e) {
            Log::error('Supervisor failed to delete assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to delete supervisor assignment.');
        }
    }

    /**
     * Toggle active status of an assignment (Supervisor)
     */
    public function toggleActive(SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();
        
        if (!$this->canEditAssignment($currentUser, $assignment)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify this assignment.'
            ], 403);
        }

        try {
            $assignment->update(['is_active' => !$assignment->is_active]);
            $status = $assignment->is_active ? 'activated' : 'deactivated';

            Log::info("Supervisor {$status} assignment", [
                'assignment_id' => $assignment->id,
                'supervisor_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Assignment {$status} successfully.",
                'is_active' => $assignment->is_active
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle assignment status.'
            ], 500);
        }
    }

    /**
     * Extend an assignment's end date (Supervisor)
     */
    public function extend(Request $request, SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();
        
        if (!$this->canEditAssignment($currentUser, $assignment)) {
            return redirect()->back()
                ->with('error', 'You do not have permission to extend this assignment.');
        }

        $validator = Validator::make($request->all(), [
            'new_end_date' => 'required|date|after:start_date',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $assignment->extend($request->new_end_date, Auth::id());

            $metadata = $assignment->metadata ?? [];
            $metadata['last_extension_reason'] = $request->reason;
            $metadata['last_extension_by'] = Auth::id();
            $metadata['last_extension_by_name'] = Auth::user()->name;
            $assignment->metadata = $metadata;
            $assignment->save();

            Log::info('Supervisor extended assignment', [
                'assignment_id' => $assignment->id,
                'new_end_date' => $request->new_end_date,
                'supervisor_id' => Auth::id(),
            ]);

            return redirect()->route('security.supervisor-assignments.show', $assignment)
                ->with('success', 'Assignment extended successfully.');

        } catch (\Exception $e) {
            Log::error('Supervisor failed to extend assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to extend assignment.');
        }
    }

    /**
     * Terminate an assignment early (Supervisor)
     */
    public function terminate(Request $request, SecuritySupervisorAssignment $assignment)
    {
        $currentUser = Auth::user();
        
        if (!$this->canEditAssignment($currentUser, $assignment)) {
            return redirect()->back()
                ->with('error', 'You do not have permission to terminate this assignment.');
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $assignment->terminate($request->reason, Auth::id());

            Log::info('Supervisor terminated assignment', [
                'assignment_id' => $assignment->id,
                'reason' => $request->reason,
                'supervisor_id' => Auth::id(),
            ]);

            return redirect()->route('security.supervisor-assignments.show', $assignment)
                ->with('success', 'Assignment terminated successfully.');

        } catch (\Exception $e) {
            Log::error('Supervisor failed to terminate assignment: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to terminate assignment.');
        }
    }

    /**
     * API: Get eligible supervisors for a post (Supervisor)
     */
    public function getEligibleSupervisors(Request $request)
    {
        $currentUser = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|exists:security_posts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify post accessibility
        $accessiblePosts = $this->getAccessiblePosts($currentUser)->pluck('id')->toArray();
        if (!in_array($request->post_id, $accessiblePosts)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this post.'
            ], 403);
        }

        $post = SecurityPost::find($request->post_id);

        // ✅ UPDATED: Get eligible supervisors using can_be_supervisor flag
        $eligibleSupervisors = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('can_be_supervisor', true)
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', $currentUser->id)
            ->whereDoesntHave('activeSupervisorAssignments', function($query) use ($post) {
                $query->where('security_post_id', $post->id)
                      ->where('supervisor_type', 'area_supervisor');
            })
            ->with(['schedules' => function($query) use ($post) {
                $query->where('security_post_id', $post->id)
                      ->orderBy('assignment_date', 'desc')
                      ->limit(5);
            }])
            ->get()
            ->map(function($user) use ($post) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_eligible' => $user->can_be_supervisor,
                    'experience_years' => $user->created_at->diffInYears(now()),
                    'shifts_completed' => $user->schedules()->where('status', 'completed')->count(),
                    'last_shift' => $user->schedules()
                        ->where('security_post_id', $post->id)
                        ->latest('assignment_date')
                        ->first()?->assignment_date?->format('Y-m-d'),
                ];
            });

        return response()->json([
            'success' => true,
            'supervisors' => $eligibleSupervisors,
        ]);
    }

    /**
     * Export supervisor assignments (Supervisor)
     */
    public function export(Request $request)
    {
        $currentUser = Auth::user();
        
        $query = SecuritySupervisorAssignment::with(['user', 'post', 'assignedBy']);
        
        // Apply supervisor scope
        $areaAssignments = $this->getUserAreaAssignments($currentUser);
        $this->applySupervisorScope($query, $currentUser, $areaAssignments);

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'expired') {
                $query->expired();
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        $assignments = $query->orderBy('created_at', 'desc')->get();

        $fileName = 'supervisor_assignments_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($assignments) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID',
                'Supervisor',
                'Post',
                'Assigned By',
                'Start Date',
                'End Date',
                'Type',
                'Primary',
                'Status',
                'Notes',
                'Created At',
            ]);

            foreach ($assignments as $assignment) {
                fputcsv($file, [
                    $assignment->id,
                    optional($assignment->user)->name ?? 'N/A',
                    optional($assignment->post)->name ?? 'Role Only',
                    optional($assignment->assignedBy)->name ?? 'System',
                    $assignment->start_date->format('Y-m-d'),
                    $assignment->end_date?->format('Y-m-d') ?? 'Indefinite',
                    $assignment->supervisor_type_name ?? ucfirst(str_replace('_', ' ', $assignment->supervisor_type)),
                    $assignment->is_primary_supervisor ? 'Yes' : 'No',
                    $assignment->is_current ? 'Active' : 'Inactive',
                    $assignment->notes ?? '',
                    $assignment->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk action on multiple assignments (Supervisor - Limited)
     */
    public function bulkAction(Request $request)
    {
        $currentUser = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate',
            'ids' => 'required|array',
            'ids.*' => 'exists:security_supervisor_assignments,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Supervisors can only activate/deactivate, not delete or extend in bulk
        DB::beginTransaction();

        try {
            $assignments = SecuritySupervisorAssignment::whereIn('id', $request->ids)->get();
            $processed = 0;
            $failed = [];

            foreach ($assignments as $assignment) {
                // Verify access to each assignment
                if (!$this->canEditAssignment($currentUser, $assignment)) {
                    $failed[] = [
                        'id' => $assignment->id,
                        'name' => optional($assignment->user)->name ?? 'Unknown',
                        'reason' => 'No permission to modify this assignment'
                    ];
                    continue;
                }

                try {
                    switch ($request->action) {
                        case 'activate':
                            $assignment->update(['is_active' => true]);
                            break;
                        case 'deactivate':
                            $assignment->update(['is_active' => false]);
                            break;
                    }
                    $processed++;
                } catch (\Exception $e) {
                    $failed[] = [
                        'id' => $assignment->id,
                        'name' => optional($assignment->user)->name ?? 'Unknown',
                        'reason' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            $message = "{$processed} assignment(s) processed successfully.";
            if (!empty($failed)) {
                $message .= " " . count($failed) . " failed.";
            }

            Log::info('Supervisor performed bulk action', [
                'action' => $request->action,
                'processed' => $processed,
                'failed' => count($failed),
                'supervisor_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'processed' => $processed,
                'failed' => $failed
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Supervisor bulk action failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk action.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== SECURITY HELPER METHODS ====================

    /**
     * Check if user is an active Area Supervisor
     */
    private function isAreaSupervisor(User $user): bool
    {
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('supervisor_type', 'area_supervisor')
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->exists();
    }

    /**
     * Get user's area assignments
     */
    private function getUserAreaAssignments(User $user)
    {
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('supervisor_type', 'area_supervisor')
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->get();
    }

    /**
     * Apply supervisor scope to query
     */
    private function applySupervisorScope($query, User $user, $areaAssignments)
    {
        // Get post IDs from area assignments (NULL means all posts)
        $postIds = $areaAssignments->pluck('security_post_id')->filter()->unique()->toArray();
        
        if (empty($postIds)) {
            // Area supervisor has access to all posts
            // No additional scope needed
            return;
        }

        // Scope to specific posts
        $query->where(function($q) use ($postIds) {
            $q->whereIn('security_post_id', $postIds)
              ->orWhereNull('security_post_id');
        });
    }

    /**
     * Get posts accessible to the supervisor
     */
    private function getAccessiblePosts(User $user)
    {
        $areaAssignments = $this->getUserAreaAssignments($user);
        
        if ($areaAssignments->isEmpty()) {
            return collect([]);
        }

        // Check if any area assignment has null post (all posts)
        $hasAllPosts = $areaAssignments->contains(function($assignment) {
            return is_null($assignment->security_post_id);
        });

        if ($hasAllPosts) {
            return SecurityPost::active()->orderBy('name')->get(['id', 'name', 'code', 'max_personnel']);
        }

        // Get specific posts
        $postIds = $areaAssignments->pluck('security_post_id')->filter()->unique()->toArray();
        return SecurityPost::active()
            ->whereIn('id', $postIds)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel']);
    }

    /**
     * Get assignable supervisor types for a supervisor
     */
    private function getAssignableTypes(User $user): array
    {
        if ($this->isAreaSupervisor($user)) {
            return [
                'post_supervisor' => 'Post Supervisor',
                'shift_supervisor' => 'Shift Supervisor',
                'relief_supervisor' => 'Relief Supervisor',
                'training_supervisor' => 'Supervisor in Training',
            ];
        }
        
        return [];
    }

    /**
     * Get supervisors that can be assigned by this user
     */
    private function getAssignableSupervisors(User $user)
    {
        if (!$this->isAreaSupervisor($user)) {
            return collect([]);
        }

        // ✅ UPDATED: Using can_be_supervisor flag
        return User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('can_be_supervisor', true)
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', $user->id)
            ->whereDoesntHave('activeSupervisorAssignments', function($query) {
                $query->where('supervisor_type', 'area_supervisor');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'can_be_supervisor']);
    }

    /**
     * Check if user can access a specific assignment
     */
    private function canAccessAssignment(User $user, SecuritySupervisorAssignment $assignment): bool
    {
        // If user is an admin, they have access (but this is security controller)
        // For security controller, check if user is area supervisor with scope
        if (!$this->isAreaSupervisor($user)) {
            return false;
        }

        $areaAssignments = $this->getUserAreaAssignments($user);
        
        // Check if area supervisor has access to the assignment's post
        foreach ($areaAssignments as $area) {
            if (is_null($area->security_post_id)) {
                return true; // Has access to all posts
            }
            if ($area->security_post_id == $assignment->security_post_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can edit a specific assignment
     */
    private function canEditAssignment(User $user, SecuritySupervisorAssignment $assignment): bool
    {
        // Can't edit area supervisor assignments
        if ($assignment->supervisor_type === 'area_supervisor') {
            return false;
        }

        // Must have access to the assignment
        if (!$this->canAccessAssignment($user, $assignment)) {
            return false;
        }

        // Check if assignment is within supervisor's scope
        return true;
    }

    /**
     * Check if user can delete a specific assignment
     */
    private function canDeleteAssignment(User $user, SecuritySupervisorAssignment $assignment): bool
    {
        // Same as edit permissions for now
        return $this->canEditAssignment($user, $assignment);
    }

    /**
     * Apply filters to query
     */
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

    /**
     * Get statistics for supervisor
     */
    private function getSupervisorStats(User $user, $areaAssignments)
    {
        $query = SecuritySupervisorAssignment::query();
        $this->applySupervisorScope($query, $user, $areaAssignments);

        return [
            'total_assignments' => $query->count(),
            'active_assignments' => (clone $query)->active()->count(),
            'expired_assignments' => (clone $query)->expired()->count(),
            'expiring_soon' => (clone $query)->expiringSoon()->count(),
            'by_type' => (clone $query)->selectRaw('supervisor_type, count(*) as count')
                ->groupBy('supervisor_type')
                ->pluck('count', 'supervisor_type')
                ->toArray(),
        ];
    }

    /**
     * Get limited statistics for a specific assignment
     */
    private function getLimitedStats($assignment)
    {
        try {
            $stats = $assignment->getSupervisionHistory();
            return [
                'total_days' => $stats['total_days'] ?? 0,
                'schedules_overseen' => $stats['schedules_overseen'] ?? 0,
                'approvals_given' => $stats['approvals_given'] ?? 0,
                'incidents_reported' => $stats['incidents_reported'] ?? 0,
                'incidents_resolved' => $stats['incidents_resolved'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'total_days' => 0,
                'schedules_overseen' => 0,
                'approvals_given' => 0,
                'incidents_reported' => 0,
                'incidents_resolved' => 0,
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
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    private function getVerificationLogs($assignment)
    {
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
            
            return $query->limit(20)->get();
            
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    private function getRelatedAssignments($assignment)
    {
        // Get related assignments within the same post
        return SecuritySupervisorAssignment::where('security_post_id', $assignment->security_post_id)
            ->where('id', '!=', $assignment->id)
            ->where('is_active', true)
            ->with(['user'])
            ->limit(5)
            ->get();
    }
}