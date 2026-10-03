<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RotationGroup;
use App\Models\SecuritySchedule;
use App\Models\User;
use App\Models\SecurityPost;
use App\Models\SecurityShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class RotationGroupController extends Controller
{
    /**
     * Display a listing of rotation groups
     */
    public function index(Request $request)
    {
        $query = RotationGroup::with(['members', 'post', 'shift'])
            ->orderBy('name');

        // Filters
        if ($request->filled('post_id')) {
            $query->where('security_post_id', $request->post_id);
        }

        if ($request->filled('shift_id')) {
            $query->where('security_shift_id', $request->shift_id);
        }

        if ($request->filled('type')) {
            $query->where('group_type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $groups = $query->paginate(20)->withQueryString();

        // Get filter data
        $posts = SecurityPost::active()->get(['id', 'name', 'code']);
        $shifts = SecurityShift::active()->get(['id', 'name', 'category']);

        // Statistics - Enhanced to match schedule controller
        $stats = [
            'total_groups' => RotationGroup::count(),
            'active_groups' => RotationGroup::where('status', 'active')->count(),
            'total_members' => DB::table('rotation_group_members')->count(),
            'avg_group_size' => round(DB::table('rotation_group_members')
                ->select('rotation_group_id', DB::raw('count(*) as count'))
                ->groupBy('rotation_group_id')
                ->get()
                ->avg('count') ?? 0, 1),
            // New stats aligned with schedule controller
            'rotations_today' => $this->getRotationsCountForDate(now()),
            'upcoming_rotations' => $this->getUpcomingRotationsCount(),
            'groups_needing_rotation' => $this->getGroupsNeedingRotation(),
        ];

        return view('admin.rotation-groups.index', compact(
            'groups',
            'posts',
            'shifts',
            'stats',
            'request'
        ));
    }

    /**
     * Show the form for creating a new rotation group
     */
    public function create()
    {
        $posts = SecurityPost::whereNull('deleted_at')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel', 'type', 'is_active']);

        $shifts = SecurityShift::active()->get([
            'id', 'name', 'start_time', 'end_time', 'category', 'is_overnight',
            'rotation_type', 'handover_config', 'duration_hours', 'required_personnel'
        ]);

        // Get available personnel (not in any active rotation group)
        $availablePersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->whereDoesntHave('rotationGroupMembers', function($q) {
                $q->where('status', 'active');
            })
            ->get(['id', 'name', 'phone', 'email', 'badge_number', 'preferences']);

        return view('admin.rotation-groups.create', compact(
            'posts',
            'shifts',
            'availablePersonnel'
        ));
    }

    /**
     * Store a newly created rotation group
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:rotation_groups',
            'code' => 'nullable|string|max:50|unique:rotation_groups',
            'description' => 'nullable|string|max:1000',
            'security_post_id' => 'required|exists:security_posts,id',
            'security_shift_id' => 'required|exists:security_shifts,id',
            'group_type' => 'required|in:day,night,evening,rotating,standby',
            'rotation_pattern' => 'required_if:group_type,rotating|array',
            'rotation_pattern.type' => 'required_if:group_type,rotating|in:sequential,alternating,preference_based,staggered,full_swap',
            'rotation_pattern.interval_days' => 'required_if:group_type,rotating|integer|min:1|max:365',
            'rotation_pattern.start_date' => 'nullable|date',
            'rotation_pattern.sequence' => 'nullable|array',
            'max_members' => 'nullable|integer|min:1|max:100',
            'min_members' => 'nullable|integer|min:1|lte:max_members',
            'preference_weights' => 'nullable|array',
            'preference_weights.seniority' => 'nullable|integer|min:0|max:100',
            'preference_weights.performance' => 'nullable|integer|min:0|max:100',
            'preference_weights.availability' => 'nullable|integer|min:0|max:100',
            'preference_weights.preferred_shift' => 'nullable|integer|min:0|max:100',
            'preference_weights.rotation_willingness' => 'nullable|integer|min:0|max:100',
            'auto_rotate' => 'boolean',
            'auto_rotate_schedule' => 'required_if:auto_rotate,true|nullable|in:daily,weekly,biweekly,monthly',
            'auto_rotate_time' => 'nullable|date_format:H:i',
            'status' => 'required|in:active,inactive,draft',
            'settings' => 'nullable|array',
            'settings.require_handover' => 'boolean',
            'settings.notify_on_rotate' => 'boolean',
            'settings.maintain_coverage' => 'boolean',
            'settings.allow_swaps' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // Generate code if not provided
            if (empty($request->code)) {
                $request->merge(['code' => $this->generateGroupCode($request->name)]);
            }

            // Get shift for rotation config
            $shift = SecurityShift::find($request->security_shift_id);

            // Prepare rotation config - Enhanced to match schedule controller
            $rotationConfig = null;
            if ($request->group_type === 'rotating') {
                $rotationConfig = [
                    'type' => $request->rotation_pattern['type'] ?? 'sequential',
                    'interval_days' => $request->rotation_pattern['interval_days'] ?? 7,
                    'start_date' => $request->rotation_pattern['start_date'] ?? now()->format('Y-m-d'),
                    'last_rotation_date' => null,
                    'next_rotation_date' => $request->rotation_pattern['start_date'] ?? now()->format('Y-m-d'),
                    'next_rotation_time' => $request->auto_rotate_time ?? '00:00',
                    'current_sequence_index' => 0,
                    'sequence' => $request->rotation_pattern['sequence'] ?? $this->getDefaultSequence(),
                    'rotation_history' => [],
                    'rotation_stats' => [
                        'total_rotations' => 0,
                        'successful_rotations' => 0,
                        'failed_rotations' => 0,
                        'avg_rotation_time' => null,
                    ],
                    'compatibility' => [
                        'shift_rotation_type' => $shift->rotation_type ?? null,
                        'handover_required' => $shift->handover_config['has_handover'] ?? false,
                    ]
                ];
            }

            // Prepare preference weights - Enhanced
            $preferenceWeights = $request->preference_weights ?? [
                'seniority' => 25,
                'performance' => 25,
                'availability' => 20,
                'preferred_shift' => 15,
                'rotation_willingness' => 15
            ];

            // Create rotation group
            $group = RotationGroup::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'security_post_id' => $request->security_post_id,
                'security_shift_id' => $request->security_shift_id,
                'group_type' => $request->group_type,
                'rotation_config' => $rotationConfig,
                'max_members' => $request->max_members,
                'min_members' => $request->min_members,
                'current_members' => 0,
                'preference_weights' => $preferenceWeights,
                'auto_rotate' => $request->boolean('auto_rotate', false),
                'auto_rotate_schedule' => $request->auto_rotate_schedule,
                'auto_rotate_time' => $request->auto_rotate_time,
                'status' => $request->status,
                'settings' => array_merge([
                    'require_handover' => true,
                    'notify_on_rotate' => true,
                    'maintain_coverage' => true,
                    'allow_swaps' => true,
                ], $request->settings ?? []),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Assign initial members if provided
            if ($request->filled('initial_members')) {
                $this->assignInitialMembers($group, $request->initial_members);
            }

            DB::commit();

            // Clear cache
            Cache::forget('rotation_groups_list');
            Cache::forget('rotation_groups_stats');

            Log::info('Rotation group created', [
                'group_id' => $group->id,
                'name' => $group->name,
                'created_by' => auth()->id()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Rotation group created successfully.',
                    'group' => $group->load(['post', 'shift'])
                ]);
            }

            return redirect()->route('admin.rotation-groups.index')
                ->with('success', 'Rotation group created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create rotation group: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create rotation group.',
                    'error' => $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to create rotation group.')
                ->withInput();
        }
    }

    /**
     * Display the specified rotation group
     */
   public function show($id)
{
    try {
        $group = RotationGroup::with([
            'members.user',
            'post',
            'shift',
            'createdBy',
            'updatedBy'
        ])->findOrFail($id);

        // Get rotation history - extract data from JsonResponse
        $historyResponse = $this->getRotationHistory($group->id);
        $history = $historyResponse->getData(true); // Convert to array
        
        // If you just need the history array, extract it
        if (isset($history['history'])) {
            $history = $history['history'];
        } elseif (isset($history['group_history'])) {
            $history = $history['group_history'];
        } else {
            $history = []; // Default to empty array
        }

        // Get performance metrics aligned with schedule controller
        $metrics = $this->calculateGroupMetrics($group);

        // Get upcoming rotations - FIX THIS TOO
        // You need to implement this method or use an alternative
        $upcomingRotations = $this->getUpcomingRotations($group); // This also needs fixing

        // Get current assignments for group members
        $currentAssignments = $this->getCurrentAssignments($group);

        // Get rotation readiness status
        $readiness = $this->checkRotationReadiness($group);

        return view('admin.rotation-groups.show', compact(
            'group',
            'history',
            'metrics',
            'upcomingRotations',
            'currentAssignments',
            'readiness'
        ));

    } catch (\Exception $e) {
        Log::error('Failed to show rotation group: ' . $e->getMessage(), [
            'group_id' => $id,
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->back()
            ->with('error', 'Failed to load rotation group.');
    }
}

/**
 * Get rotation history as array for the view
 */
private function getRotationHistoryArray($groupId)
{
    try {
        $group = RotationGroup::findOrFail($groupId);
        
        // Get history from rotation_config
        $history = $group->rotation_config['rotation_history'] ?? [];
        
        // Sort by date descending
        usort($history, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $history;
        
    } catch (\Exception $e) {
        Log::error('Failed to get rotation history array: ' . $e->getMessage());
        return [];
    }
}

    /**
     * Show the form for editing the specified rotation group
     */
    public function edit($id)
    {
        $group = RotationGroup::with(['members.user', 'post', 'shift'])->findOrFail($id);

        $posts = SecurityPost::whereNull('deleted_at')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel', 'type', 'is_active']);

        $shifts = SecurityShift::active()->get([
            'id', 'name', 'start_time', 'end_time', 'category', 'is_overnight',
            'rotation_type', 'handover_config', 'duration_hours', 'required_personnel'
        ]);

        // Get available personnel (not in any active rotation group except current)
        $availablePersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->whereDoesntHave('rotationGroupMembers', function($q) use ($group) {
                $q->where('status', 'active')
                  ->where('rotation_group_id', '!=', $group->id);
            })
            ->get(['id', 'name', 'phone', 'email', 'badge_number', 'preferences']);

        return view('admin.rotation-groups.edit', compact(
            'group',
            'posts',
            'shifts',
            'availablePersonnel'
        ));
    }

    /**
     * Update the specified rotation group
     */
    public function update(Request $request, $id)
    {
        $group = RotationGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:rotation_groups,name,' . $id,
            'code' => 'nullable|string|max:50|unique:rotation_groups,code,' . $id,
            'description' => 'nullable|string|max:1000',
            'security_post_id' => 'required|exists:security_posts,id',
            'security_shift_id' => 'required|exists:security_shifts,id',
            'group_type' => 'required|in:day,night,evening,rotating,standby',
            'rotation_pattern' => 'nullable|array',
            'max_members' => 'nullable|integer|min:1|max:100',
            'min_members' => 'nullable|integer|min:1|lte:max_members',
            'preference_weights' => 'nullable|array',
            'auto_rotate' => 'boolean',
            'auto_rotate_schedule' => 'required_if:auto_rotate,true|nullable|in:daily,weekly,biweekly,monthly',
            'auto_rotate_time' => 'nullable|date_format:H:i',
            'status' => 'required|in:active,inactive,draft',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // Update rotation config if needed
            if ($request->has('rotation_pattern') && $group->group_type === 'rotating') {
                $rotationConfig = $group->rotation_config ?? [];
                $rotationConfig['type'] = $request->rotation_pattern['type'] ?? $rotationConfig['type'] ?? 'sequential';
                $rotationConfig['interval_days'] = $request->rotation_pattern['interval_days'] ?? $rotationConfig['interval_days'] ?? 7;
                $rotationConfig['sequence'] = $request->rotation_pattern['sequence'] ?? $rotationConfig['sequence'] ?? $this->getDefaultSequence();
                
                // Update next rotation date if interval changed
                if (isset($rotationConfig['last_rotation_date'])) {
                    $lastRotation = Carbon::parse($rotationConfig['last_rotation_date']);
                    $rotationConfig['next_rotation_date'] = $lastRotation->addDays($rotationConfig['interval_days'])->format('Y-m-d');
                }
                
                $group->rotation_config = $rotationConfig;
            }

            // Update group
            $group->update([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'security_post_id' => $request->security_post_id,
                'security_shift_id' => $request->security_shift_id,
                'group_type' => $request->group_type,
                'max_members' => $request->max_members,
                'min_members' => $request->min_members,
                'preference_weights' => $request->preference_weights ?? $group->preference_weights,
                'auto_rotate' => $request->boolean('auto_rotate', $group->auto_rotate),
                'auto_rotate_schedule' => $request->auto_rotate_schedule,
                'auto_rotate_time' => $request->auto_rotate_time,
                'status' => $request->status,
                'settings' => $request->settings ?? $group->settings,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            // Clear caches
            Cache::forget("rotation_group_{$group->id}");
            Cache::forget('rotation_groups_list');

            Log::info('Rotation group updated', [
                'group_id' => $group->id,
                'updated_by' => auth()->id(),
                'changes' => $group->getChanges()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Rotation group updated successfully.',
                    'group' => $group->fresh(['post', 'shift'])
                ]);
            }

            return redirect()->route('admin.rotation-groups.index')
                ->with('success', 'Rotation group updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update rotation group: ' . $e->getMessage(), [
                'group_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update rotation group.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to update rotation group.')
                ->withInput();
        }
    }

    /**
     * Remove the specified rotation group
     */
    public function destroy($id)
    {
        try {
            $group = RotationGroup::findOrFail($id);

            // Check if group has members
            if ($group->members()->count() > 0) {
                return redirect()->back()
                    ->with('error', 'Cannot delete group with existing members. Please remove members first.');
            }

            // Check if group has active rotations in schedules
            $hasActiveRotations = SecuritySchedule::where('rotation_group_id', $id)
                ->whereIn('status', ['scheduled', 'active'])
                ->exists();

            if ($hasActiveRotations) {
                return redirect()->back()
                    ->with('error', 'Cannot delete group with active rotations in schedules.');
            }

            $group->delete();

            // Clear caches
            Cache::forget("rotation_group_{$id}");
            Cache::forget('rotation_groups_list');

            Log::info('Rotation group deleted', [
                'group_id' => $id,
                'deleted_by' => auth()->id()
            ]);

            return redirect()->route('admin.rotation-groups.index')
                ->with('success', 'Rotation group deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete rotation group: ' . $e->getMessage(), [
                'group_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete rotation group.');
        }
    }

    /**
     * Assign members to rotation group
     */
    public function assignMembers(Request $request, $groupId)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'role' => 'nullable|in:member,leader,deputy',
            'preference_score' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $group = RotationGroup::findOrFail($groupId);

            // Check capacity
            $currentMembers = $group->members()->count();
            $newMembersCount = count($request->user_ids);

            if ($group->max_members && ($currentMembers + $newMembersCount) > $group->max_members) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot assign {$newMembersCount} members. Group capacity is {$group->max_members}."
                ], 422);
            }

            $assigned = [];
            $failed = [];

            foreach ($request->user_ids as $userId) {
                try {
                    // Check if user already in group
                    $exists = $group->members()
                        ->where('user_id', $userId)
                        ->exists();

                    if ($exists) {
                        $failed[] = [
                            'user_id' => $userId,
                            'reason' => 'Already a member'
                        ];
                        continue;
                    }

                    // Check if user is in another rotation group for same post/shift
                    $conflict = RotationGroup::whereHas('members', function($q) use ($userId) {
                            $q->where('user_id', $userId);
                        })
                        ->where('security_post_id', $group->security_post_id)
                        ->where('security_shift_id', $group->security_shift_id)
                        ->where('id', '!=', $groupId)
                        ->exists();

                    if ($conflict) {
                        $failed[] = [
                            'user_id' => $userId,
                            'reason' => 'Already in another rotation group for this post/shift'
                        ];
                        continue;
                    }

                    // Calculate preference score based on weights (aligned with schedule controller)
                    $score = $this->calculateMemberPreferenceScore($userId, $group);

                    // Check user's rotation willingness from preferences
                    $user = User::find($userId);
                    $willingnessScore = $user->preferences['rotation_willingness'] ?? 5;
                    
                    // Adjust score based on willingness
                    $score = ($score * 0.7) + ($willingnessScore * 0.3);

                    // Assign member
                    $group->members()->create([
                        'user_id' => $userId,
                        'role' => $request->role ?? 'member',
                        'joined_at' => now(),
                        'preference_score' => round($score, 1),
                        'assigned_by' => auth()->id(),
                        'status' => 'active',
                        'rotation_count' => 0,
                        'last_rotation_date' => null,
                    ]);

                    $assigned[] = $userId;

                    // Log in rotation history
                    $this->logMemberAssignment($group, $userId);

                } catch (\Exception $e) {
                    $failed[] = [
                        'user_id' => $userId,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            // Update current members count
            $group->update([
                'current_members' => $group->members()->count()
            ]);

            // Clear cache
            Cache::forget("rotation_group_{$groupId}_members");

            DB::commit();

            Log::info('Members assigned to rotation group', [
                'group_id' => $groupId,
                'assigned_count' => count($assigned),
                'failed_count' => count($failed),
                'assigned_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => count($assigned) . ' member(s) assigned successfully.',
                'assigned' => $assigned,
                'failed' => $failed
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign members: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign members.'
            ], 500);
        }
    }

    /**
     * Remove member from rotation group
     */
    public function removeMember(Request $request, $groupId, $memberId)
    {
        DB::beginTransaction();

        try {
            $group = RotationGroup::findOrFail($groupId);
            $member = $group->members()->findOrFail($memberId);

            // Check if member has upcoming assignments
            $hasUpcoming = SecuritySchedule::where('rotation_group_id', $groupId)
                ->where('security_user_id', $member->user_id)
                ->where('assignment_date', '>=', now())
                ->whereIn('status', ['scheduled', 'active'])
                ->exists();

            if ($hasUpcoming && !$request->boolean('force')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member has upcoming assignments. Use force flag to override.'
                ], 422);
            }

            // Log removal
            $this->logMemberRemoval($group, $member);

            // Delete member
            $member->delete();

            // Update current members count
            $group->update([
                'current_members' => $group->members()->count()
            ]);

            // Clear cache
            Cache::forget("rotation_group_{$groupId}_members");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove member: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'member_id' => $memberId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member.'
            ], 500);
        }
    }

    /**
     * Execute rotation for the group - Aligned with SecurityScheduleController::rotateShiftGroups()
     */
    public function executeRotation(Request $request, $groupId)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'rotation_type' => 'nullable|in:full,partial,staggered,full_swap',
            'apply_to_schedules' => 'boolean',
            'maintain_coverage' => 'boolean',
            'respect_preferences' => 'boolean',
            'notify_members' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $group = RotationGroup::with(['members.user', 'post', 'shift'])
                ->findOrFail($groupId);

            if ($group->group_type !== 'rotating') {
                return response()->json([
                    'success' => false,
                    'message' => 'Group is not configured for rotation.'
                ], 422);
            }

            if ($group->members()->count() < ($group->min_members ?? 2)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient members for rotation.'
                ], 422);
            }

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now();
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate->copy()->addDays(30);
            $rotationType = $request->rotation_type ?? 'full';
            $maintainCoverage = $request->boolean('maintain_coverage', true);
            $respectPreferences = $request->boolean('respect_preferences', true);

            // Calculate rotation sequence (aligned with schedule controller)
            $rotationResult = $this->calculateRotationSequence(
                $group,
                $startDate,
                $endDate,
                $rotationType,
                $respectPreferences
            );

            // Apply to schedules if requested
            if ($request->boolean('apply_to_schedules')) {
                $appliedCount = $this->applyRotationToSchedules(
                    $group, 
                    $rotationResult,
                    $maintainCoverage
                );
                
                $rotationResult['applied_to_schedules'] = $appliedCount;
            }

            // Update rotation config
            $rotationConfig = $group->rotation_config;
            $rotationConfig['last_rotation_date'] = now()->format('Y-m-d H:i:s');
            $rotationConfig['current_sequence_index'] = ($rotationConfig['current_sequence_index'] ?? 0) + 1;
            
            // Calculate next rotation date
            $nextRotationDate = $this->calculateNextRotationDate($group, $rotationConfig);
            $rotationConfig['next_rotation_date'] = $nextRotationDate?->format('Y-m-d');
            
            // Add to rotation history
            $rotationHistory = $rotationConfig['rotation_history'] ?? [];
            $rotationHistory[] = [
                'id' => uniqid('rot_'),
                'date' => now()->format('Y-m-d H:i:s'),
                'type' => $rotationType,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'affected_members' => $rotationResult['affected_count'],
                'sequence_count' => count($rotationResult['sequence']),
                'maintained_coverage' => $maintainCoverage,
                'respected_preferences' => $respectPreferences,
                'executed_by' => auth()->id(),
                'executed_by_name' => auth()->user()?->name,
            ];
            
            $rotationConfig['rotation_history'] = $rotationHistory;
            
            // Update rotation stats
            $rotationStats = $rotationConfig['rotation_stats'] ?? [
                'total_rotations' => 0,
                'successful_rotations' => 0,
                'failed_rotations' => 0,
                'avg_rotation_time' => null,
            ];
            $rotationStats['total_rotations']++;
            $rotationStats['successful_rotations']++;
            $rotationConfig['rotation_stats'] = $rotationStats;

            $group->update([
                'rotation_config' => $rotationConfig,
                'last_rotated_at' => now(),
                'updated_by' => auth()->id()
            ]);

            // Update member rotation counts
            foreach ($rotationResult['sequence'] as $rotation) {
                foreach ($rotation['assigned_members'] as $userId) {
                    $member = $group->members()->where('user_id', $userId)->first();
                    if ($member) {
                        $member->increment('rotation_count');
                        $member->update(['last_rotation_date' => now()]);
                    }
                }
            }

            DB::commit();

            // Clear caches
            Cache::forget("rotation_group_{$groupId}");
            Cache::forget("rotation_group_{$groupId}_members");
            Cache::forget("rotation_group_{$groupId}_history");

            // Send notifications if requested
            if ($request->boolean('notify_members', true)) {
                $this->sendRotationNotifications($group, $rotationResult);
            }

            Log::info('Rotation executed for group', [
                'group_id' => $groupId,
                'rotation_type' => $rotationType,
                'affected_count' => $rotationResult['affected_count'],
                'sequence_count' => count($rotationResult['sequence']),
                'executed_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rotation executed successfully.',
                'rotation_result' => $rotationResult,
                'next_rotation' => $nextRotationDate?->format('Y-m-d'),
                'total_rotations' => $rotationStats['total_rotations']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to execute rotation: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to execute rotation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview rotation before executing
     */
    public function previewRotation(Request $request, $groupId)
    {
        try {
            $group = RotationGroup::with(['members.user', 'post', 'shift'])
                ->findOrFail($groupId);

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now();
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate->copy()->addDays(7);
            $rotationType = $request->rotation_type ?? 'full';

            // Calculate preview
            $preview = $this->calculateRotationSequence(
                $group,
                $startDate,
                $endDate,
                $rotationType,
                $request->boolean('respect_preferences', true)
            );

            // Add member details
            foreach ($preview['sequence'] as &$day) {
                $day['member_details'] = [];
                foreach ($day['assigned_members'] as $userId) {
                    $member = $group->members()->with('user')->where('user_id', $userId)->first();
                    if ($member) {
                        $day['member_details'][] = [
                            'id' => $member->user_id,
                            'name' => $member->user->name,
                            'role' => $member->role,
                            'preference_score' => $member->preference_score,
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'member_count' => $group->members()->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to preview rotation: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to preview rotation.'
            ], 500);
        }
    }

    /**
     * Get rotation history for the group
     */
    public function getRotationHistory($groupId)
    {
        try {
            $group = RotationGroup::findOrFail($groupId);
            
            $history = $group->rotation_config['rotation_history'] ?? [];

            // Get rotation events from schedules
            $scheduleRotations = SecuritySchedule::where('rotation_group_id', $groupId)
                ->whereNotNull('rotated_at')
                ->with(['securityUser:id,name', 'rotatedFromUser:id,name', 'post:id,name', 'shift:id,name'])
                ->orderBy('rotated_at', 'desc')
                ->limit(50)
                ->get(['id', 'security_user_id', 'rotated_from_user_id', 'rotated_at', 'rotation_data', 'assignment_date', 'security_post_id', 'security_shift_id']);

            // Format schedule rotations
            $formattedScheduleRotations = $scheduleRotations->map(function($schedule) {
                return [
                    'id' => 'schedule_' . $schedule->id,
                    'type' => 'schedule_rotation',
                    'date' => $schedule->rotated_at->format('Y-m-d H:i:s'),
                    'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                    'post' => $schedule->post?->name,
                    'shift' => $schedule->shift?->name,
                    'from_user' => $schedule->rotatedFromUser?->name,
                    'to_user' => $schedule->securityUser?->name,
                    'reason' => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                ];
            })->toArray();

            // Merge and sort
            $allHistory = array_merge($history, $formattedScheduleRotations);
            usort($allHistory, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            return response()->json([
                'success' => true,
                'history' => $allHistory,
                'group_history' => $history,
                'schedule_rotations' => $formattedScheduleRotations,
                'summary' => [
                    'total_rotations' => count($history) + $scheduleRotations->count(),
                    'group_rotations' => count($history),
                    'schedule_rotations' => $scheduleRotations->count(),
                    'last_rotation' => $group->last_rotated_at,
                    'next_scheduled' => $this->getNextScheduledRotation($group)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get rotation history: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load rotation history.'
            ], 500);
        }
    }

    /**
     * Get statistics for the group
     */
    public function getStatistics($groupId)
    {
        try {
            $group = RotationGroup::with(['members.user', 'post', 'shift'])
                ->findOrFail($groupId);

            // Get schedule statistics
            $scheduleStats = $this->getGroupScheduleStatistics($group);

            $statistics = [
                'group_info' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'type' => $group->group_type,
                    'status' => $group->status,
                    'created_at' => $group->created_at,
                    'last_rotated' => $group->last_rotated_at,
                    'days_since_last_rotation' => $group->last_rotated_at ? 
                        $group->last_rotated_at->diffInDays(now()) : null,
                ],
                'membership' => [
                    'total' => $group->members()->count(),
                    'leaders' => $group->members()->where('role', 'leader')->count(),
                    'deputies' => $group->members()->where('role', 'deputy')->count(),
                    'members' => $group->members()->where('role', 'member')->count(),
                    'capacity_used' => $group->max_members ? 
                        round(($group->members()->count() / $group->max_members) * 100, 2) : null,
                    'avg_preference_score' => round($group->members()->avg('preference_score') ?? 0, 1),
                    'avg_rotation_count' => round($group->members()->avg('rotation_count') ?? 0, 1),
                ],
                'performance' => $this->calculateGroupPerformance($group),
                'rotation_stats' => $this->calculateRotationStatistics($group),
                'schedule_stats' => $scheduleStats,
                'member_performance' => $this->calculateMemberPerformance($group),
                'coverage' => $this->calculateCoverageMetrics($group),
                'effectiveness' => $this->calculateEffectivenessMetrics($group),
            ];

            return response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get group statistics: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics.'
            ], 500);
        }
    }

    /**
     * Get groups needing rotation
     */
    public function getGroupsNeedingRotation()
    {
        try {
            $groups = RotationGroup::where('group_type', 'rotating')
                ->where('status', 'active')
                ->where('auto_rotate', true)
                ->get();

            $needingRotation = [];

            foreach ($groups as $group) {
                $nextRotation = $this->getNextScheduledRotation($group);
                if ($nextRotation && $nextRotation->lte(now()->addDays(3))) {
                    $needingRotation[] = [
                        'group' => [
                            'id' => $group->id,
                            'name' => $group->name,
                            'post' => $group->post?->name,
                            'shift' => $group->shift?->name,
                        ],
                        'next_rotation' => $nextRotation->format('Y-m-d'),
                        'days_until' => now()->diffInDays($nextRotation),
                        'member_count' => $group->members()->count(),
                        'is_urgent' => $nextRotation->lte(now()),
                    ];
                }
            }

            // Sort by urgency
            usort($needingRotation, function($a, $b) {
                return $a['days_until'] <=> $b['days_until'];
            });

            return $needingRotation;

        } catch (\Exception $e) {
            Log::error('Failed to get groups needing rotation: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Helper Methods
     */

    private function generateGroupCode($name)
    {
        // Generate code from name (e.g., "Morning Shift Group" -> "MSG")
        $words = explode(' ', $name);
        $code = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $code .= strtoupper(substr($word, 0, 1));
            }
        }
        
        // Ensure uniqueness
        $originalCode = $code;
        $counter = 1;
        while (RotationGroup::where('code', $code)->exists()) {
            $code = $originalCode . $counter;
            $counter++;
        }
        
        return $code;
    }

    private function getDefaultSequence()
    {
        return ['A', 'B', 'C', 'D'];
    }

    private function calculateMemberPreferenceScore($userId, $group)
    {
        $user = User::find($userId);
        if (!$user) return 5;

        $weights = $group->preference_weights ?? [
            'seniority' => 25,
            'performance' => 25,
            'availability' => 20,
            'preferred_shift' => 15,
            'rotation_willingness' => 15
        ];

        $score = 0;

        // Seniority score (based on employment duration)
        if ($user->created_at) {
            $yearsEmployed = $user->created_at->diffInYears(now());
            $seniorityScore = min(10, $yearsEmployed * 2); // Max 10 after 5 years
            $score += ($seniorityScore / 10) * ($weights['seniority'] / 100);
        }

        // Performance score (from user preferences or performance reviews)
        $performanceScore = $user->preferences['performance_rating'] ?? 7;
        $score += ($performanceScore / 10) * ($weights['performance'] / 100);

        // Availability score (aligned with schedule controller)
        $availabilityScore = $this->calculateAvailabilityScore($userId, $group);
        $score += ($availabilityScore / 10) * ($weights['availability'] / 100);

        // Preferred shift alignment
        $preferredShiftScore = $this->calculatePreferredShiftScore($user, $group);
        $score += ($preferredShiftScore / 10) * ($weights['preferred_shift'] / 100);

        // Rotation willingness
        $willingnessScore = $user->preferences['rotation_willingness'] ?? 5;
        $score += ($willingnessScore / 10) * ($weights['rotation_willingness'] / 100);

        return round($score * 10, 1); // Convert to 1-10 scale
    }

    private function calculateAvailabilityScore($userId, $group)
    {
        try {
            $thirtyDaysAgo = now()->subDays(30);
            
            // Count completed schedules vs total assigned
            $totalAssigned = SecuritySchedule::where('security_user_id', $userId)
                ->where('assignment_date', '>=', $thirtyDaysAgo)
                ->where('status', '!=', 'cancelled')
                ->count();

            $completed = SecuritySchedule::where('security_user_id', $userId)
                ->where('assignment_date', '>=', $thirtyDaysAgo)
                ->where('status', 'completed')
                ->count();

            if ($totalAssigned == 0) return 5;

            return min(10, round(($completed / $totalAssigned) * 10, 1));
        } catch (\Exception $e) {
            return 5;
        }
    }

    private function calculatePreferredShiftScore($user, $group)
    {
        $preferences = $user->preferences ?? [];
        $preferredShifts = $preferences['preferred_shifts'] ?? [];
        
        if (empty($preferredShifts)) return 5;

        $shiftCategory = $group->shift->category ?? null;
        
        if (in_array($shiftCategory, $preferredShifts)) {
            return 10;
        }

        return 3;
    }

    private function calculateRotationSequence($group, $startDate, $endDate, $rotationType, $respectPreferences)
    {
        $members = $group->members()->with('user')->orderBy('preference_score', 'desc')->get();
        $memberIds = $members->pluck('user_id')->toArray();
        $memberCount = count($memberIds);

        $sequence = [];
        $affectedCount = 0;

        $currentDate = $startDate->copy();
        $rotationConfig = $group->rotation_config;
        $intervalDays = $rotationConfig['interval_days'] ?? 7;
        $sequencePattern = $rotationConfig['sequence'] ?? ['A', 'B', 'C', 'D'];

        // Sort members by preference score if respecting preferences
        if ($respectPreferences) {
            $members = $members->sortByDesc('preference_score');
            $memberIds = $members->pluck('user_id')->toArray();
        }

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Determine which members are assigned based on rotation type
            switch ($rotationType) {
                case 'full':
                    // Full rotation - all members get new assignments
                    $assignedMembers = $memberIds;
                    break;
                    
                case 'partial':
                    // Partial rotation - only 50% of members
                    $assignedCount = ceil($memberCount / 2);
                    $assignedMembers = array_slice($memberIds, 0, $assignedCount);
                    break;
                    
                case 'staggered':
                    // Staggered - rotate based on sequence pattern
                    $patternIndex = floor($currentDate->diffInDays($startDate) / $intervalDays) % count($sequencePattern);
                    $assignedMembers = $this->getMembersForPattern($memberIds, $sequencePattern[$patternIndex] ?? 'A');
                    break;
                    
                case 'full_swap':
                    // Full swap between two groups
                    $half = ceil($memberCount / 2);
                    $groupA = array_slice($memberIds, 0, $half);
                    $groupB = array_slice($memberIds, $half);
                    
                    // Alternate between groups
                    $weekIndex = floor($currentDate->diffInDays($startDate) / 7) % 2;
                    $assignedMembers = $weekIndex == 0 ? $groupA : $groupB;
                    break;
                    
                default:
                    $assignedMembers = $memberIds;
            }

            $sequence[] = [
                'date' => $dateStr,
                'assigned_members' => $assignedMembers,
                'rotation_pattern' => $rotationType,
                'member_count' => count($assignedMembers),
                'preference_scores' => $this->getPreferenceScoresForMembers($members, $assignedMembers),
            ];

            $affectedCount += count($assignedMembers);
            $currentDate->addDays($intervalDays);
        }

        return [
            'sequence' => $sequence,
            'affected_count' => $affectedCount,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'rotation_type' => $rotationType,
            'total_days' => $startDate->diffInDays($endDate) + 1,
            'rotation_days' => $intervalDays,
            'unique_members_involved' => count(array_unique($this->flattenAssignedMembers($sequence))),
        ];
    }

    private function getMembersForPattern($memberIds, $pattern)
    {
        // Pattern-based selection (e.g., 'A' for first half, 'B' for second half)
        $half = ceil(count($memberIds) / 2);
        
        switch ($pattern) {
            case 'A':
                return array_slice($memberIds, 0, $half);
            case 'B':
                return array_slice($memberIds, $half);
            case 'C':
                return array_slice($memberIds, 0, ceil($half / 2));
            case 'D':
                return array_slice($memberIds, ceil($half / 2));
            default:
                return $memberIds;
        }
    }

    private function getPreferenceScoresForMembers($members, $assignedMemberIds)
    {
        $scores = [];
        foreach ($members as $member) {
            if (in_array($member->user_id, $assignedMemberIds)) {
                $scores[] = $member->preference_score;
            }
        }
        return $scores;
    }

    private function flattenAssignedMembers($sequence)
    {
        $members = [];
        foreach ($sequence as $day) {
            $members = array_merge($members, $day['assigned_members']);
        }
        return $members;
    }

    private function applyRotationToSchedules($group, $rotationResult, $maintainCoverage)
    {
        $postId = $group->security_post_id;
        $shiftId = $group->security_shift_id;
        $appliedCount = 0;

        foreach ($rotationResult['sequence'] as $rotation) {
            $date = Carbon::parse($rotation['date']);
            
            // Get existing schedules for this date
            $existingSchedules = SecuritySchedule::where('security_post_id', $postId)
                ->where('security_shift_id', $shiftId)
                ->whereDate('assignment_date', $date)
                ->orderBy('id')
                ->get();

            // Update assignments based on rotation
            foreach ($rotation['assigned_members'] as $index => $userId) {
                if (isset($existingSchedules[$index])) {
                    $schedule = $existingSchedules[$index];
                    $oldUserId = $schedule->security_user_id;
                    
                    if ($oldUserId != $userId) {
                        // Get rotation history from existing data
                        $rotationHistory = $schedule->rotation_data['rotation_history'] ?? [];
                        
                        // Add to rotation history
                        $rotationHistory[] = [
                            'id' => uniqid('rot_'),
                            'date' => now()->format('Y-m-d H:i:s'),
                            'from_user_id' => $oldUserId,
                            'from_user_name' => $schedule->securityUser?->name,
                            'to_user_id' => $userId,
                            'to_user_name' => User::find($userId)?->name,
                            'reason' => 'Group rotation',
                            'rotation_group' => $group->id,
                            'rotation_group_name' => $group->name,
                            'performed_by' => auth()->id(),
                            'performed_by_name' => auth()->user()?->name,
                        ];
                        
                        $rotationData = array_merge($schedule->rotation_data ?? [], [
                            'rotated_from' => $oldUserId,
                            'rotated_at' => now()->format('Y-m-d H:i:s'),
                            'rotation_group' => $group->id,
                            'rotation_group_name' => $group->name,
                            'rotation_history' => $rotationHistory,
                            'rotation_count' => ($schedule->rotation_data['rotation_count'] ?? 0) + 1,
                        ]);
                        
                        $schedule->update([
                            'security_user_id' => $userId,
                            'rotated_from_user_id' => $oldUserId,
                            'is_rotated' => true,
                            'rotated_at' => now(),
                            'rotation_data' => $rotationData,
                            'rotation_swap_count' => ($schedule->rotation_swap_count ?? 0) + 1,
                        ]);
                        
                        $appliedCount++;
                    }
                } else {
                    // Create new schedule if needed and if we haven't exceeded post capacity
                    $post = $group->post;
                    if ($post) {
                        $currentCount = SecuritySchedule::where('security_post_id', $postId)
                            ->whereDate('assignment_date', $date)
                            ->count();
                        
                        if ($currentCount < $post->max_personnel) {
                            $schedule = SecuritySchedule::create([
                                'security_post_id' => $postId,
                                'security_shift_id' => $shiftId,
                                'security_user_id' => $userId,
                                'assignment_date' => $date->format('Y-m-d'),
                                'assigned_by' => auth()->id(),
                                'status' => 'scheduled',
                                'rotation_group_id' => $group->id,
                                'is_rotated' => true,
                                'rotated_at' => now(),
                                'rotation_data' => [
                                    'rotated_at' => now()->format('Y-m-d H:i:s'),
                                    'rotation_group' => $group->id,
                                    'rotation_group_name' => $group->name,
                                    'rotation_count' => 1,
                                    'rotation_history' => [[
                                        'id' => uniqid('rot_'),
                                        'date' => now()->format('Y-m-d H:i:s'),
                                        'to_user_id' => $userId,
                                        'to_user_name' => User::find($userId)?->name,
                                        'reason' => 'Group rotation - new schedule',
                                        'rotation_group' => $group->id,
                                        'performed_by' => auth()->id(),
                                    ]]
                                ],
                                'rotation_swap_count' => 1,
                            ]);
                            
                            $appliedCount++;
                        }
                    }
                }
            }

            // Handle coverage maintenance
            if ($maintainCoverage && isset($existingSchedules)) {
                $this->ensureCoverageForDate($group, $date, $existingSchedules, $rotation['assigned_members']);
            }
        }

        return $appliedCount;
    }

    private function ensureCoverageForDate($group, $date, $existingSchedules, $assignedMembers)
    {
        $post = $group->post;
        if (!$post) return;

        $requiredCount = min($post->max_personnel, $group->shift->required_personnel ?? 1);
        $currentCount = count($existingSchedules);
        
        // If we have fewer schedules than required, create missing ones
        if ($currentCount < $requiredCount) {
            $needed = $requiredCount - $currentCount;
            
            // Get available members not already assigned
            $assignedUserIds = $existingSchedules->pluck('security_user_id')->toArray();
            $availableMembers = $group->members()
                ->whereNotIn('user_id', $assignedUserIds)
                ->orderBy('preference_score', 'desc')
                ->limit($needed)
                ->get();

            foreach ($availableMembers as $member) {
                SecuritySchedule::create([
                    'security_post_id' => $post->id,
                    'security_shift_id' => $group->security_shift_id,
                    'security_user_id' => $member->user_id,
                    'assignment_date' => $date->format('Y-m-d'),
                    'assigned_by' => auth()->id(),
                    'status' => 'scheduled',
                    'rotation_group_id' => $group->id,
                    'notes' => 'Auto-created to maintain coverage',
                    'rotation_data' => [
                        'created_for_coverage' => true,
                        'created_at' => now()->format('Y-m-d H:i:s'),
                        'rotation_group' => $group->id,
                    ]
                ]);
            }
        }
    }

    private function calculateNextRotationDate($group, $rotationConfig)
    {
        if (!$group->auto_rotate) return null;

        $lastRotation = $group->last_rotated_at ?? now();
        $schedule = $group->auto_rotate_schedule;
        $rotationTime = $group->auto_rotate_time ?? '00:00';

        $nextDate = null;
        switch ($schedule) {
            case 'daily':
                $nextDate = $lastRotation->copy()->addDay();
                break;
            case 'weekly':
                $nextDate = $lastRotation->copy()->addWeek();
                break;
            case 'biweekly':
                $nextDate = $lastRotation->copy()->addWeeks(2);
                break;
            case 'monthly':
                $nextDate = $lastRotation->copy()->addMonth();
                break;
            default:
                return null;
        }

        // Set to specific time
        list($hour, $minute) = explode(':', $rotationTime);
        $nextDate->setTime($hour, $minute);

        return $nextDate;
    }

    private function sendRotationNotifications($group, $rotationResult)
    {
        try {
            $affectedUserIds = $this->flattenAssignedMembers($rotationResult['sequence']);
            $affectedUserIds = array_unique($affectedUserIds);

            foreach ($affectedUserIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    // Queue notification (implement based on your notification system)
                    Log::info('Rotation notification queued', [
                        'user_id' => $userId,
                        'group_id' => $group->id,
                        'rotation_date' => $rotationResult['start_date']
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send rotation notifications', [
                'group_id' => $group->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function calculateGroupPerformance($group)
    {
        $thirtyDaysAgo = now()->subDays(30);
        $memberIds = $group->members()->pluck('user_id');
        
        $schedules = SecuritySchedule::whereIn('security_user_id', $memberIds)
            ->where('security_post_id', $group->security_post_id)
            ->where('security_shift_id', $group->security_shift_id)
            ->where('assignment_date', '>=', $thirtyDaysAgo)
            ->get();

        $totalSchedules = $schedules->count();
        $completedSchedules = $schedules->where('status', 'completed')->count();
        $absentSchedules = $schedules->where('status', 'absent')->count();
        $lateSchedules = $schedules->where('late_minutes', '>', 0)->count();

        return [
            'total_schedules' => $totalSchedules,
            'completed' => $completedSchedules,
            'absent' => $absentSchedules,
            'late' => $lateSchedules,
            'completion_rate' => $totalSchedules > 0 ? 
                round(($completedSchedules / $totalSchedules) * 100, 2) : 0,
            'absent_rate' => $totalSchedules > 0 ? 
                round(($absentSchedules / $totalSchedules) * 100, 2) : 0,
            'late_rate' => $totalSchedules > 0 ? 
                round(($lateSchedules / $totalSchedules) * 100, 2) : 0,
            'avg_late_minutes' => round($schedules->avg('late_minutes') ?? 0, 1),
            'avg_overtime_minutes' => round($schedules->avg('overtime_minutes') ?? 0, 1),
        ];
    }

    private function calculateRotationStatistics($group)
    {
        $config = $group->rotation_config;
        $history = $config['rotation_history'] ?? [];
        $stats = $config['rotation_stats'] ?? [];
        
        $last30Days = collect($history)->filter(function($item) {
            return Carbon::parse($item['date'])->gte(now()->subDays(30));
        })->count();

        return [
            'total_rotations' => $stats['total_rotations'] ?? count($history),
            'rotations_30_days' => $last30Days,
            'successful_rotations' => $stats['successful_rotations'] ?? 0,
            'failed_rotations' => $stats['failed_rotations'] ?? 0,
            'success_rate' => ($stats['total_rotations'] ?? 0) > 0 ?
                round((($stats['successful_rotations'] ?? 0) / ($stats['total_rotations'] ?? 1)) * 100, 2) : 0,
            'avg_rotation_interval' => $this->calculateAverageRotationInterval($history),
            'last_rotation' => $group->last_rotated_at,
            'next_rotation' => $this->getNextScheduledRotation($group),
            'rotation_frequency' => $group->auto_rotate_schedule,
        ];
    }

    private function getGroupScheduleStatistics($group)
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $schedules = SecuritySchedule::where('rotation_group_id', $group->id)
            ->whereBetween('assignment_date', [$startOfMonth, $endOfMonth])
            ->get();

        $post = $group->post;
        $expectedCount = $post ? $post->max_personnel * now()->daysInMonth : 0;

        return [
            'total' => $schedules->count(),
            'by_status' => $schedules->groupBy('status')->map->count(),
            'expected_count' => $expectedCount,
            'coverage_rate' => $expectedCount > 0 ?
                round(($schedules->count() / $expectedCount) * 100, 2) : 0,
            'unique_days' => $schedules->groupBy(function($s) {
                return $s->assignment_date->format('Y-m-d');
            })->count(),
            'avg_per_day' => $schedules->count() > 0 ?
                round($schedules->count() / $schedules->groupBy('assignment_date')->count(), 1) : 0,
        ];
    }

    private function calculateMemberPerformance($group)
    {
        $thirtyDaysAgo = now()->subDays(30);
        $members = $group->members()->with('user')->get();
        $performance = [];

        foreach ($members as $member) {
            $schedules = SecuritySchedule::where('security_user_id', $member->user_id)
                ->where('assignment_date', '>=', $thirtyDaysAgo)
                ->get();

            $totalShifts = $schedules->count();
            $completedShifts = $schedules->where('status', 'completed')->count();
            $lateShifts = $schedules->where('late_minutes', '>', 0)->count();

            $performance[] = [
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'role' => $member->role,
                'preference_score' => $member->preference_score,
                'rotation_count' => $member->rotation_count,
                'schedules_count' => $totalShifts,
                'completion_rate' => $totalShifts > 0 ?
                    round(($completedShifts / $totalShifts) * 100, 2) : 0,
                'punctuality_rate' => $totalShifts > 0 ?
                    round((($totalShifts - $lateShifts) / $totalShifts) * 100, 2) : 100,
                'avg_late_minutes' => round($schedules->avg('late_minutes') ?? 0, 1),
                'last_rotation' => $member->last_rotation_date,
            ];
        }

        // Sort by preference score
        usort($performance, function($a, $b) {
            return $b['preference_score'] <=> $a['preference_score'];
        });

        return $performance;
    }

    private function calculateCoverageMetrics($group)
    {
        $post = $group->post;
        if (!$post) return null;

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $schedules = SecuritySchedule::where('rotation_group_id', $group->id)
            ->whereBetween('assignment_date', [$startOfWeek, $endOfWeek])
            ->get();

        $daysInWeek = 7;
        $requiredPerDay = $post->max_personnel;
        $totalRequired = $daysInWeek * $requiredPerDay;
        $totalActual = $schedules->count();

        $coverageByDay = [];
        $currentDate = $startOfWeek->copy();
        while ($currentDate->lte($endOfWeek)) {
            $daySchedules = $schedules->filter(function($s) use ($currentDate) {
                return $s->assignment_date->format('Y-m-d') === $currentDate->format('Y-m-d');
            });
            
            $coverageByDay[$currentDate->format('Y-m-d')] = [
                'required' => $requiredPerDay,
                'actual' => $daySchedules->count(),
                'coverage' => $requiredPerDay > 0 ?
                    round(($daySchedules->count() / $requiredPerDay) * 100, 2) : 0,
                'fully_staffed' => $daySchedules->count() >= $requiredPerDay,
            ];
            
            $currentDate->addDay();
        }

        return [
            'weekly' => [
                'required' => $totalRequired,
                'actual' => $totalActual,
                'coverage_rate' => $totalRequired > 0 ?
                    round(($totalActual / $totalRequired) * 100, 2) : 0,
            ],
            'by_day' => $coverageByDay,
            'fully_staffed_days' => collect($coverageByDay)->where('fully_staffed', true)->count(),
            'understaffed_days' => collect($coverageByDay)->where('fully_staffed', false)->count(),
        ];
    }

    private function calculateEffectivenessMetrics($group)
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        $rotatedSchedules = SecuritySchedule::where('rotation_group_id', $group->id)
            ->where('assignment_date', '>=', $thirtyDaysAgo)
            ->where('is_rotated', true)
            ->get();

        $nonRotatedSchedules = SecuritySchedule::where('rotation_group_id', $group->id)
            ->where('assignment_date', '>=', $thirtyDaysAgo)
            ->where('is_rotated', false)
            ->get();

        $rotatedCompletion = $rotatedSchedules->where('status', 'completed')->count();
        $nonRotatedCompletion = $nonRotatedSchedules->where('status', 'completed')->count();

        return [
            'rotated' => [
                'count' => $rotatedSchedules->count(),
                'completed' => $rotatedCompletion,
                'completion_rate' => $rotatedSchedules->count() > 0 ?
                    round(($rotatedCompletion / $rotatedSchedules->count()) * 100, 2) : 0,
                'avg_late' => round($rotatedSchedules->avg('late_minutes') ?? 0, 1),
            ],
            'non_rotated' => [
                'count' => $nonRotatedSchedules->count(),
                'completed' => $nonRotatedCompletion,
                'completion_rate' => $nonRotatedSchedules->count() > 0 ?
                    round(($nonRotatedCompletion / $nonRotatedSchedules->count()) * 100, 2) : 0,
                'avg_late' => round($nonRotatedSchedules->avg('late_minutes') ?? 0, 1),
            ],
            'improvement' => [
                'completion_rate' => $this->calculateImprovement(
                    $nonRotatedSchedules->count() > 0 ? ($nonRotatedCompletion / $nonRotatedSchedules->count()) * 100 : 0,
                    $rotatedSchedules->count() > 0 ? ($rotatedCompletion / $rotatedSchedules->count()) * 100 : 0
                ),
                'lateness' => $this->calculateImprovement(
                    $nonRotatedSchedules->avg('late_minutes') ?? 0,
                    $rotatedSchedules->avg('late_minutes') ?? 0,
                    true // lower is better
                ),
            ],
        ];
    }

    private function calculateImprovement($baseline, $current, $lowerIsBetter = false)
    {
        if ($baseline == 0) return 0;
        
        if ($lowerIsBetter) {
            return round((($baseline - $current) / $baseline) * 100, 2);
        }
        
        return round((($current - $baseline) / $baseline) * 100, 2);
    }

    private function calculateAverageRotationInterval($history)
    {
        if (count($history) < 2) return null;

        $intervals = [];
        for ($i = 1; $i < count($history); $i++) {
            $prev = Carbon::parse($history[$i - 1]['date']);
            $curr = Carbon::parse($history[$i]['date']);
            $intervals[] = $prev->diffInDays($curr);
        }

        return round(array_sum($intervals) / count($intervals), 1);
    }

    private function getNextScheduledRotation($group)
    {
        if (!$group->auto_rotate) return null;

        $config = $group->rotation_config;
        if (isset($config['next_rotation_date'])) {
            return Carbon::parse($config['next_rotation_date']);
        }

        return $this->calculateNextRotationDate($group, $config);
    }

    private function getCurrentAssignments($group)
    {
        $today = now()->format('Y-m-d');
        $memberIds = $group->members()->pluck('user_id');

        $assignments = SecuritySchedule::whereIn('security_user_id', $memberIds)
            ->whereDate('assignment_date', '>=', $today)
            ->whereIn('status', ['scheduled', 'active'])
            ->with(['post', 'shift', 'securityUser'])
            ->orderBy('assignment_date')
            ->limit(20)
            ->get();

        return $assignments;
    }

    private function checkRotationReadiness($group)
    {
        if ($group->group_type !== 'rotating') {
            return ['ready' => false, 'reason' => 'Not a rotating group'];
        }

        $memberCount = $group->members()->count();
        if ($memberCount < ($group->min_members ?? 2)) {
            return ['ready' => false, 'reason' => 'Insufficient members'];
        }

        $nextRotation = $this->getNextScheduledRotation($group);
        $daysUntil = $nextRotation ? now()->diffInDays($nextRotation) : null;

        // Check if any members have schedule conflicts
        $conflicts = $this->checkRotationConflicts($group);

        return [
            'ready' => empty($conflicts) && $memberCount >= ($group->min_members ?? 2),
            'member_count' => $memberCount,
            'next_rotation' => $nextRotation?->format('Y-m-d'),
            'days_until' => $daysUntil,
            'is_due' => $nextRotation && $nextRotation->lte(now()),
            'conflicts' => $conflicts,
            'can_rotate' => empty($conflicts),
        ];
    }

    private function checkRotationConflicts($group)
    {
        $conflicts = [];
        $nextRotation = $this->getNextScheduledRotation($group);
        
        if (!$nextRotation) return $conflicts;

        $members = $group->members()->with('user')->get();

        foreach ($members as $member) {
            // Check if member has schedule on rotation date
            $existing = SecuritySchedule::where('security_user_id', $member->user_id)
                ->whereDate('assignment_date', $nextRotation)
                ->where('status', '!=', 'cancelled')
                ->exists();

            if ($existing) {
                $conflicts[] = [
                    'user_id' => $member->user_id,
                    'user_name' => $member->user->name,
                    'reason' => 'Already scheduled on rotation date',
                ];
            }

            // Check weekly hour limits
            $weekStart = $nextRotation->copy()->startOfWeek();
            $weekEnd = $nextRotation->copy()->endOfWeek();
            
            $weeklyHours = SecuritySchedule::where('security_user_id', $member->user_id)
                ->whereBetween('assignment_date', [$weekStart, $weekEnd])
                ->where('status', '!=', 'cancelled')
                ->with('shift')
                ->get()
                ->sum(function($s) {
                    return $s->shift->duration_hours ?? 0;
                });

            $shift = $group->shift;
            if ($shift && ($weeklyHours + $shift->duration_hours) > 60) {
                $conflicts[] = [
                    'user_id' => $member->user_id,
                    'user_name' => $member->user->name,
                    'reason' => 'Would exceed weekly hour limit',
                ];
            }
        }

        return $conflicts;
    }

    private function assignInitialMembers($group, $memberIds)
    {
        foreach ($memberIds as $userId) {
            $score = $this->calculateMemberPreferenceScore($userId, $group);
            
            $group->members()->create([
                'user_id' => $userId,
                'role' => 'member',
                'joined_at' => now(),
                'preference_score' => $score,
                'assigned_by' => auth()->id(),
                'status' => 'active',
                'rotation_count' => 0,
            ]);
        }

        $group->update(['current_members' => count($memberIds)]);
    }

    private function logMemberAssignment($group, $userId)
    {
        $user = User::find($userId);
        
        Log::info('Member assigned to rotation group', [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'user_id' => $userId,
            'user_name' => $user?->name,
            'assigned_by' => auth()->id(),
            'assigned_at' => now()->toDateTimeString(),
        ]);
    }

    private function logMemberRemoval($group, $member)
    {
        Log::info('Member removed from rotation group', [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'user_id' => $member->user_id,
            'user_name' => $member->user?->name,
            'removed_by' => auth()->id(),
            'removed_at' => now()->toDateTimeString(),
            'membership_duration' => $member->joined_at?->diffInDays(now()),
            'rotation_count' => $member->rotation_count,
        ]);
    }

    private function getRotationsCountForDate($date)
    {
        return SecuritySchedule::whereDate('assignment_date', $date)
            ->where('is_rotated', true)
            ->count();
    }

    private function getUpcomingRotationsCount()
    {
        $nextWeek = now()->addWeek();
        
        return SecuritySchedule::whereBetween('assignment_date', [now(), $nextWeek])
            ->where('is_rotated', true)
            ->count();
    }

    private function calculateGroupMetrics($group)
    {
        return [
            'stability' => $this->calculateGroupStability($group),
            'efficiency' => $this->calculateGroupEfficiency($group),
            'satisfaction' => $this->calculateGroupSatisfaction($group),
            'coverage' => $this->calculateCoverageScore($group),
            'readiness' => $this->calculateReadinessScore($group),
        ];
    }

    private function calculateGroupStability($group)
    {
        $memberCount = $group->members()->count();
        $rotationCount = count($group->rotation_config['rotation_history'] ?? []);
        $memberRetention = $this->calculateMemberRetention($group);
        
        // Stability score based on member retention and rotation frequency
        $score = 70; // Base score
        
        if ($memberCount > 5) $score += 5;
        if ($memberRetention > 80) $score += 10;
        if ($rotationCount > 10) $score -= 5; // Too many rotations reduce stability
        if ($rotationCount < 3) $score -= 5; // Too few rotations might indicate stagnation
        
        return min(100, max(0, $score));
    }

    private function calculateMemberRetention($group)
    {
        // This would need a `left_at` field to track departures
        // Placeholder implementation
        return 90;
    }

    private function calculateGroupEfficiency($group)
    {
        $performance = $this->calculateGroupPerformance($group);
        return $performance['completion_rate'];
    }

    private function calculateGroupSatisfaction($group)
    {
        $avgPreferenceScore = $group->members()->avg('preference_score') ?? 5;
        $willingnessScore = $group->members()->with('user')->get()->avg(function($member) {
            return $member->user->preferences['rotation_willingness'] ?? 5;
        }) ?? 5;
        
        return round(($avgPreferenceScore * 0.6 + $willingnessScore * 0.4) * 10, 2);
    }

    private function calculateCoverageScore($group)
    {
        $coverage = $this->calculateCoverageMetrics($group);
        return $coverage['weekly']['coverage_rate'] ?? 0;
    }

    private function calculateReadinessScore($group)
    {
        $readiness = $this->checkRotationReadiness($group);
        
        if (!$readiness['ready']) return 0;
        
        $score = 70; // Base score
        
        if ($readiness['member_count'] >= ($group->min_members ?? 2)) {
            $score += 15;
        }
        
        if (empty($readiness['conflicts'])) {
            $score += 15;
        }
        
        return min(100, $score);
    }

    /**
 * Get upcoming rotations for a specific group
 */
private function getUpcomingRotations($group)
{
    try {
        // Get the next 30 days of rotations for this group
        $startDate = now();
        $endDate = now()->addDays(30);
        
        $upcomingRotations = SecuritySchedule::where('rotation_group_id', $group->id)
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->whereIn('status', ['scheduled', 'active'])
            ->with(['post', 'shift', 'securityUser'])
            ->orderBy('assignment_date')
            ->get();
            
        return $upcomingRotations;
        
    } catch (\Exception $e) {
        Log::error('Failed to get upcoming rotations: ' . $e->getMessage(), [
            'group_id' => $group->id
        ]);
        
        return collect(); // Return empty collection on error
    }
}

}