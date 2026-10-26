<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RotationGroup;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\SecurityShift;
use App\Models\User;
use App\Services\RotationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RotationGroupController extends Controller
{
    protected RotationService $rotation;

    public function __construct(RotationService $rotation)
    {
        $this->rotation = $rotation;
    }

    public function index(Request $request)
    {
        $query = RotationGroup::with(['post', 'shift'])->orderBy('name');

        if ($request->filled('post_id')) $query->where('security_post_id', $request->post_id);
        if ($request->filled('shift_id')) $query->where('security_shift_id', $request->shift_id);
        if ($request->filled('type')) $query->where('group_type', $request->type);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$s}%")
                ->orWhere('code', 'LIKE', "%{$s}%"));
        }

        $groups = $query->paginate(20)->withQueryString();

        $posts = SecurityPost::active()->get(['id', 'name', 'code']);
        $shifts = SecurityShift::active()->get(['id', 'name', 'category']);

        $stats = [
            'total_groups' => RotationGroup::count(),
            'active_groups' => RotationGroup::where('status', 'active')->count(),
            'total_members' => DB::table('rotation_group_members')->count(),
            'upcoming_rotations' => SecuritySchedule::where('is_rotated', true)
                ->whereBetween('assignment_date', [now(), now()->addWeek()])->count(),
        ];

        return view('admin.rotation-groups.index', compact('groups', 'posts', 'shifts', 'stats', 'request'));
    }

    public function create()
    {
        $posts = SecurityPost::whereNull('deleted_at')->orderByDesc('is_active')->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel', 'type', 'is_active']);
        $shifts = SecurityShift::active()->get([
            'id', 'name', 'start_time', 'end_time', 'category', 'is_overnight',
            'rotation_type', 'handover_config', 'duration_hours', 'required_personnel',
        ]);
        $availablePersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->whereDoesntHave('rotationGroupMembers', fn ($q) => $q->where('status', 'active'))
            ->get(['id', 'name', 'phone', 'email', 'badge_number', 'preferences']);

        return view('admin.rotation-groups.create', compact('posts', 'shifts', 'availablePersonnel'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:rotation_groups',
            'code' => 'nullable|string|max:50|unique:rotation_groups',
            'description' => 'nullable|string|max:1000',
            'security_post_id' => 'required|exists:security_posts,id',
            'security_shift_id' => 'required|exists:security_shifts,id',
            'group_type' => 'required|in:day,night,evening,rotating,standby',
            'rotation_pattern.type' => 'required_if:group_type,rotating|in:sequential,alternating,preference_based,staggered,full_swap',
            'rotation_pattern.interval_days' => 'required_if:group_type,rotating|integer|min:1|max:365',
            'rotation_pattern.start_date' => 'nullable|date',
            'max_members' => 'nullable|integer|min:1|max:100',
            'min_members' => 'nullable|integer|min:1',
            'auto_rotate' => 'boolean',
            'auto_rotate_schedule' => 'required_if:auto_rotate,true|nullable|in:daily,weekly,biweekly,monthly',
            'auto_rotate_time' => 'nullable|date_format:H:i',
            'status' => 'required|in:active,inactive,draft',
        ]);
        if ($validator->fails()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $code = $request->code ?: $this->generateCode($request->name);

            $rotationConfig = $request->group_type === 'rotating' ? [
                'type' => $request->rotation_pattern['type'] ?? 'sequential',
                'interval_days' => (int) ($request->rotation_pattern['interval_days'] ?? 7),
                'start_date' => $request->rotation_pattern['start_date'] ?? now()->toDateString(),
                'current_sequence_index' => 0,
                'sequence' => ['A', 'B', 'C', 'D'],
                'rotation_history' => [],
            ] : null;

            $group = RotationGroup::create([
                'name' => $request->name,
                'code' => $code,
                'description' => $request->description,
                'security_post_id' => $request->security_post_id,
                'security_shift_id' => $request->security_shift_id,
                'group_type' => $request->group_type,
                'rotation_config' => $rotationConfig,
                'max_members' => $request->max_members,
                'min_members' => $request->min_members ?? 2,
                'current_members' => 0,
                'preference_weights' => $request->preference_weights ?? [
                    'seniority' => 25, 'performance' => 25, 'availability' => 20,
                    'preferred_shift' => 15, 'rotation_willingness' => 15,
                ],
                'auto_rotate' => $request->boolean('auto_rotate'),
                'auto_rotate_schedule' => $request->auto_rotate_schedule,
                'auto_rotate_time' => $request->auto_rotate_time,
                'status' => $request->status,
                'settings' => array_merge([
                    'require_handover' => true, 'notify_on_rotate' => true,
                    'maintain_coverage' => true, 'allow_swaps' => true,
                ], $request->settings ?? []),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($request->filled('initial_members')) {
                $this->assignInitialMembers($group, $request->initial_members);
            }

            DB::commit();
            Cache::forget('rotation_groups_list');

            return $request->expectsJson()
                ? response()->json(['success' => true, 'group' => $group->load(['post', 'shift'])])
                : redirect()->route('admin.rotation-groups.index')->with('success', 'Created.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Rotation group create failed: ' . $e->getMessage());
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage()], 500)
                : back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $group = RotationGroup::with(['members.user', 'post', 'shift'])->findOrFail($id);

            $history = $this->getRotationHistoryArray($group->id);
            $metrics = $this->calculateGroupMetrics($group);
            $upcomingRotations = $this->getUpcomingRotations($group);
            $currentAssignments = $this->getCurrentAssignments($group);
            $readiness = $this->checkRotationReadiness($group);

            return view('admin.rotation-groups.show', compact(
                'group', 'history', 'metrics', 'upcomingRotations', 'currentAssignments', 'readiness'
            ));
        } catch (\Exception $e) {
            Log::error('Rotation group show failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to load.');
        }
    }

    public function edit($id)
    {
        $group = RotationGroup::with(['members.user', 'post', 'shift'])->findOrFail($id);
        $posts = SecurityPost::whereNull('deleted_at')->orderByDesc('is_active')->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel', 'type', 'is_active']);
        $shifts = SecurityShift::active()->get([
            'id', 'name', 'start_time', 'end_time', 'category', 'is_overnight',
            'rotation_type', 'handover_config', 'duration_hours', 'required_personnel',
        ]);
        $availablePersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->whereDoesntHave('rotationGroupMembers', fn ($q) => $q->where('status', 'active')
                ->where('rotation_group_id', '!=', $group->id))
            ->get(['id', 'name', 'phone', 'email', 'badge_number', 'preferences']);

        return view('admin.rotation-groups.edit', compact('group', 'posts', 'shifts', 'availablePersonnel'));
    }

    public function update(Request $request, $id)
    {
        $group = RotationGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:rotation_groups,name,' . $id,
            'code' => 'nullable|string|max:50|unique:rotation_groups,code,' . $id,
            'security_post_id' => 'required|exists:security_posts,id',
            'security_shift_id' => 'required|exists:security_shifts,id',
            'group_type' => 'required|in:day,night,evening,rotating,standby',
            'status' => 'required|in:active,inactive,draft',
            'auto_rotate_schedule' => 'required_if:auto_rotate,true|nullable|in:daily,weekly,biweekly,monthly',
        ]);
        if ($validator->fails()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $config = $group->rotation_config;
            if ($request->filled('rotation_pattern') && $group->group_type === 'rotating') {
                $config = array_merge($config ?? [], [
                    'type' => $request->rotation_pattern['type'] ?? ($config['type'] ?? 'sequential'),
                    'interval_days' => (int) ($request->rotation_pattern['interval_days'] ?? ($config['interval_days'] ?? 7)),
                ]);
            }

            $group->update([
                'name' => $request->name,
                'code' => $request->code ?: $group->code,
                'description' => $request->description,
                'security_post_id' => $request->security_post_id,
                'security_shift_id' => $request->security_shift_id,
                'group_type' => $request->group_type,
                'rotation_config' => $config,
                'max_members' => $request->max_members,
                'min_members' => $request->min_members,
                'auto_rotate' => $request->boolean('auto_rotate', $group->auto_rotate),
                'auto_rotate_schedule' => $request->auto_rotate_schedule,
                'auto_rotate_time' => $request->auto_rotate_time,
                'status' => $request->status,
                'settings' => $request->settings ?? $group->settings,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();
            Cache::forget("rotation_group_{$group->id}");

            return $request->expectsJson()
                ? response()->json(['success' => true, 'group' => $group->fresh(['post', 'shift'])])
                : redirect()->route('admin.rotation-groups.index')->with('success', 'Updated.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage()], 500)
                : back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $group = RotationGroup::findOrFail($id);
            if ($group->members()->count() > 0) {
                return back()->with('error', 'Remove members first.');
            }
            if (SecuritySchedule::where('rotation_group_id', $id)
                    ->whereIn('status', ['scheduled', 'active'])->exists()) {
                return back()->with('error', 'Group has active rotations.');
            }
            $group->delete();
            Cache::forget("rotation_group_{$id}");
            return redirect()->route('admin.rotation-groups.index')->with('success', 'Deleted.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function assignMembers(Request $request, $groupId)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'role' => 'nullable|in:member,leader,deputy',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $group = RotationGroup::findOrFail($groupId);
            $current = $group->members()->count();
            $incoming = count($request->user_ids);

            if ($group->max_members && ($current + $incoming) > $group->max_members) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Capacity {$group->max_members}, would exceed.",
                ], 422);
            }

            $assigned = [];
            $failed = [];
            foreach ($request->user_ids as $uid) {
                if ($group->members()->where('user_id', $uid)->exists()) {
                    $failed[] = ['user_id' => $uid, 'reason' => 'Already member'];
                    continue;
                }
                $group->members()->create([
                    'user_id' => $uid,
                    'role' => $request->role ?? 'member',
                    'joined_at' => now(),
                    'preference_score' => $this->calculateMemberScore($uid, $group),
                    'assigned_by' => auth()->id(),
                    'status' => 'active',
                    'rotation_count' => 0,
                ]);
                $assigned[] = $uid;
            }

            $group->update(['current_members' => $group->members()->count()]);
            DB::commit();

            return response()->json([
                'success' => true,
                'assigned' => $assigned,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function removeMember(Request $request, $groupId, $memberId)
    {
        DB::beginTransaction();
        try {
            $group = RotationGroup::findOrFail($groupId);
            $member = $group->members()->findOrFail($memberId);

            $hasUpcoming = SecuritySchedule::where('rotation_group_id', $groupId)
                ->where('security_user_id', $member->user_id)
                ->where('assignment_date', '>=', now())
                ->whereIn('status', ['scheduled', 'active'])
                ->exists();

            if ($hasUpcoming && !$request->boolean('force')) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Has upcoming assignments.'], 422);
            }

            $member->delete();
            $group->update(['current_members' => $group->members()->count()]);
            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * FIXED: Uses lockForUpdate + RotationService.
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
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $group = RotationGroup::lockForUpdate()->with(['post', 'shift'])->findOrFail($groupId);

            if ($group->group_type !== 'rotating') {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Not a rotating group.'], 422);
            }
            if ($group->members()->count() < ($group->min_members ?? 2)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Not enough members.'], 422);
            }

            $start = $request->start_date ? Carbon::parse($request->start_date) : now();
            $end = $request->end_date ? Carbon::parse($request->end_date) : $start->copy()->addDays(30);

            $built = $this->rotation->buildSequence(
                $group, $start, $end,
                $request->rotation_type ?? 'full',
                $request->boolean('respect_preferences', true)
            );

            $applied = 0;
            if ($request->boolean('apply_to_schedules')) {
                $applied = $this->rotation->applyToSchedules(
                    $group, $built['sequence'],
                    $request->boolean('maintain_coverage', true)
                );
            }

            // Update config + history
            $config = $group->rotation_config ?? [];
            $config['last_rotation_date'] = now()->toIso8601String();
            $config['rotation_history'][] = [
                'id' => uniqid('rot_'),
                'date' => now()->toIso8601String(),
                'type' => $request->rotation_type ?? 'full',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'affected' => $built['affected_count'],
                'applied' => $applied,
                'by' => auth()->id(),
            ];
            $group->update(['rotation_config' => $config, 'last_rotated_at' => now()]);

            DB::commit();
            Cache::forget("rotation_group_{$groupId}");

            return response()->json([
                'success' => true,
                'message' => 'Rotation executed.',
                'rotation_result' => $built,
                'applied_to_schedules' => $applied,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('executeRotation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function previewRotation(Request $request, $groupId)
    {
        try {
            $group = RotationGroup::with(['post', 'shift'])->findOrFail($groupId);
            $start = $request->start_date ? Carbon::parse($request->start_date) : now();
            $end = $request->end_date ? Carbon::parse($request->end_date) : $start->copy()->addDays(7);

            $preview = $this->rotation->buildSequence(
                $group, $start, $end,
                $request->rotation_type ?? 'full',
                $request->boolean('respect_preferences', true)
            );

            return response()->json(['success' => true, 'preview' => $preview]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getRotationHistory($groupId)
    {
        try {
            $group = RotationGroup::findOrFail($groupId);
            $history = $this->getRotationHistoryArray($groupId);

            return response()->json([
                'success' => true,
                'history' => $history,
                'summary' => [
                    'total' => count($history),
                    'last_rotation' => $group->last_rotated_at,
                    'next' => $this->getNextScheduledRotation($group)?->toDateString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getStatistics($groupId)
    {
        try {
            $group = RotationGroup::with('members.user')->findOrFail($groupId);

            $memberCount = $group->members()->count();
            $stats = [
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'member_count' => $memberCount,
                    'last_rotated' => $group->last_rotated_at,
                ],
                'membership' => [
                    'leaders' => $group->members()->where('role', 'leader')->count(),
                    'avg_preference' => round($group->members()->avg('preference_score') ?? 0, 1),
                    'avg_rotation_count' => round($group->members()->avg('rotation_count') ?? 0, 1),
                ],
                'readiness' => $this->checkRotationReadiness($group),
            ];

            return response()->json(['success' => true, 'statistics' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // INTERNAL HELPERS
    // ============================================================

    private function getRotationHistoryArray(int $groupId): array
    {
        $group = RotationGroup::find($groupId);
        if (!$group) return [];
        $history = $group->rotation_config['rotation_history'] ?? [];
        usort($history, fn ($a, $b) => strtotime($b['date']) - strtotime($a['date']));
        return $history;
    }

    private function generateCode(string $name): string
    {
        $code = '';
        foreach (explode(' ', $name) as $w) if ($w !== '') $code .= strtoupper($w[0]);
        $orig = $code ?: 'RG';
        $code = $orig;
        $i = 1;
        while (RotationGroup::where('code', $code)->exists()) {
            $code = $orig . $i++;
        }
        return $code;
    }

    private function calculateMemberScore(int $userId, RotationGroup $group): float
    {
        $user = User::find($userId);
        if (!$user) return 5.0;

        $weights = $group->preference_weights ?? [
            'seniority' => 25, 'performance' => 25, 'availability' => 20,
            'preferred_shift' => 15, 'rotation_willingness' => 15,
        ];

        $score = 0.0;
        if ($user->created_at) {
            $score += (min(10, $user->created_at->diffInYears(now()) * 2) / 10) * ($weights['seniority'] / 100);
        }
        $perf = $user->preferences['performance_rating'] ?? 7;
        $score += ($perf / 10) * ($weights['performance'] / 100);
        $avail = $this->availabilityScore($userId);
        $score += ($avail / 10) * ($weights['availability'] / 100);
        $prefShift = in_array($group->shift?->category, $user->preferences['preferred_shifts'] ?? [], true) ? 10 : 5;
        $score += ($prefShift / 10) * ($weights['preferred_shift'] / 100);
        $willing = $user->preferences['rotation_willingness'] ?? 5;
        $score += ($willing / 10) * ($weights['rotation_willingness'] / 100);

        return round($score * 10, 1);
    }

    private function availabilityScore(int $userId): float
    {
        $since = now()->subDays(30);
        $total = SecuritySchedule::where('security_user_id', $userId)
            ->where('assignment_date', '>=', $since)
            ->where('status', '!=', 'cancelled')->count();
        if ($total === 0) return 5.0;
        $completed = SecuritySchedule::where('security_user_id', $userId)
            ->where('assignment_date', '>=', $since)
            ->where('status', 'completed')->count();
        return min(10.0, round(($completed / $total) * 10, 1));
    }

    private function assignInitialMembers(RotationGroup $group, array $userIds): void
    {
        foreach ($userIds as $uid) {
            $group->members()->create([
                'user_id' => $uid,
                'role' => 'member',
                'joined_at' => now(),
                'preference_score' => $this->calculateMemberScore($uid, $group),
                'assigned_by' => auth()->id(),
                'status' => 'active',
                'rotation_count' => 0,
            ]);
        }
        $group->update(['current_members' => count($userIds)]);
    }

    private function getUpcomingRotations(RotationGroup $group)
    {
        return SecuritySchedule::where('rotation_group_id', $group->id)
            ->whereBetween('assignment_date', [now(), now()->addDays(30)])
            ->whereIn('status', ['scheduled', 'active'])
            ->with(['post', 'shift', 'securityUser'])
            ->orderBy('assignment_date')
            ->get();
    }

    private function getCurrentAssignments(RotationGroup $group)
    {
        $memberIds = $group->members()->pluck('user_id');
        return SecuritySchedule::whereIn('security_user_id', $memberIds)
            ->whereDate('assignment_date', '>=', today())
            ->whereIn('status', ['scheduled', 'active'])
            ->with(['post', 'shift', 'securityUser'])
            ->orderBy('assignment_date')
            ->orderBy('security_shift_id')
            ->limit(20)
            ->get();
    }

    private function checkRotationReadiness(RotationGroup $group): array
    {
        if ($group->group_type !== 'rotating') {
            return ['ready' => false, 'reason' => 'Not rotating group'];
        }
        $count = $group->members()->count();
        if ($count < ($group->min_members ?? 2)) {
            return ['ready' => false, 'reason' => 'Not enough members', 'member_count' => $count];
        }
        return ['ready' => true, 'member_count' => $count];
    }

    private function calculateGroupMetrics(RotationGroup $group): array
    {
        $thirtyAgo = now()->subDays(30);
        $memberIds = $group->members()->pluck('user_id');
        $schedules = SecuritySchedule::whereIn('security_user_id', $memberIds)
            ->where('assignment_date', '>=', $thirtyAgo)->get();
        $total = $schedules->count();
        $completed = $schedules->where('status', 'completed')->count();
        return [
            'total_shifts_30d' => $total,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }

    private function getNextScheduledRotation(RotationGroup $group): ?Carbon
    {
        if (!$group->auto_rotate) return null;
        $config = $group->rotation_config ?? [];
        if (!empty($config['next_rotation_date'])) {
            return Carbon::parse($config['next_rotation_date']);
        }
        $last = $group->last_rotated_at;
        return $last ? $last->copy()->addDays($config['interval_days'] ?? 7) : null;
    }
}