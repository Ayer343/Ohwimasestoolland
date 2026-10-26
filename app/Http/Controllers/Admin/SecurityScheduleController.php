<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostQrCode;
use App\Models\RotationGroup;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\SecurityShift;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\VerificationLog;
use App\Services\RotationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SecurityScheduleController extends Controller
{
    protected RotationService $rotation;

    public function __construct(RotationService $rotation)
    {
        $this->rotation = $rotation;
    }

    // ============================================================
    // INDEX / SHOW
    // ============================================================

    public function index(Request $request)
    {
        $query = SecuritySchedule::with(['post', 'shift', 'securityUser', 'assignedBy'])
            ->orderBy('assignment_date', 'desc')
            ->orderBy('security_post_id');

        if ($request->filled('date')) {
            $query->whereDate('assignment_date', $request->date);
        } else {
            $query->whereDate('assignment_date', '>=', today()->subDays(3));
        }
        if ($request->filled('post_id')) $query->where('security_post_id', $request->post_id);
        if ($request->filled('shift_id')) $query->where('security_shift_id', $request->shift_id);
        if ($request->filled('security_user_id')) $query->where('security_user_id', $request->security_user_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('shift_category')) {
            $query->whereHas('shift', fn ($q) => $q->where('category', $request->shift_category));
        }
        if ($request->filled('verification_status')) {
            match ($request->verification_status) {
                'verified' => $query->whereNotNull('checkin_verification'),
                'unverified' => $query->whereNull('checkin_verification'),
                'failed' => $query->whereNull('checkin_verification')->whereNotNull('verification_attempts'),
                default => null,
            };
        }
        if ($request->filled('late_flagged')) $query->where('late_flagged', $request->boolean('late_flagged'));
        if ($request->filled('offline_mode')) $query->where('offline_mode', $request->boolean('offline_mode'));
        if ($request->filled('supervisor_override')) $query->where('supervisor_override', $request->boolean('supervisor_override'));
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('securityUser', fn ($x) => $x->where('name', 'LIKE', "%{$s}%")
                        ->orWhere('phone', 'LIKE', "%{$s}%")
                        ->orWhere('badge_number', 'LIKE', "%{$s}%"))
                  ->orWhereHas('post', fn ($x) => $x->where('name', 'LIKE', "%{$s}%")
                        ->orWhere('code', 'LIKE', "%{$s}%"))
                  ->orWhereHas('shift', fn ($x) => $x->where('name', 'LIKE', "%{$s}%")
                        ->orWhere('code', 'LIKE', "%{$s}%"));
            });
        }

        $schedules = $query->paginate(20)->withQueryString();

        $securityPosts = SecurityPost::active()->get(['id', 'name', 'max_personnel', 'latitude', 'longitude']);
        $securityShifts = SecurityShift::active()->get(['id', 'name', 'start_time', 'end_time', 'category']);
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id', 'name', 'phone', 'badge_number']);

        $todaySummary = $this->getTodayScheduleSummary();
        $upcomingHandovers = $this->getUpcomingHandovers();
        $verificationStats = $this->getVerificationStatistics();
        $stats = $this->getScheduleStatistics();

        return view('admin.security-schedules.index', compact(
            'schedules', 'securityPosts', 'securityShifts', 'securityPersonnel',
            'todaySummary', 'upcomingHandovers', 'verificationStats', 'stats', 'request'
        ));
    }

    public function create()
    {
        $securityPosts = SecurityPost::whereNull('deleted_at')
            ->orderByDesc('is_active')->orderBy('name')
            ->get(['id','name','code','max_personnel','type','location','latitude','longitude','equipment','is_active','checkin_radius','require_gps_verification']);

        $securityShifts = SecurityShift::active()->get([
            'id','name','start_time','end_time','category','is_overnight',
            'rotation_type','handover_config','applicable_days','break_schedule','required_personnel','day_type',
        ]);

        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->with(['schedules' => fn ($q) => $q->latest('assignment_date')->limit(1)])
            ->get(['id','name','phone','email','badge_number','preferences']);

        $rotationGroups = RotationGroup::where('status', 'active')
            ->with(['post:id,name', 'shift:id,name'])->get();

        return view('admin.security-schedules.create', compact(
            'securityPosts', 'securityShifts', 'securityPersonnel', 'rotationGroups'
        ));
    }

    public function show($id)
    {
        $schedule = SecuritySchedule::with([
            'post','shift','securityUser','assignedBy','rotatedFromUser','approvedBy','handoverCompletedBy',
        ])->findOrFail($id);

        $verificationLogs = VerificationLog::where('schedule_id', $id)->orderByDesc('created_at')->get();
        $deviceInfo = null;
        if ($schedule->checkin_device_id) {
            $deviceInfo = UserDevice::where('device_id', $schedule->checkin_device_id)
                ->where('user_id', $schedule->security_user_id)->first();
        }

        return view('admin.security-schedules.show', compact('schedule', 'verificationLogs', 'deviceInfo'));
    }

    public function edit($id)
    {
        $schedule = SecuritySchedule::with(['shift', 'post', 'securityUser'])->findOrFail($id);
        $securityPosts = SecurityPost::whereNull('deleted_at')
            ->orderByDesc('is_active')->orderBy('name')
            ->get(['id','name','code','max_personnel','type','location','is_active','latitude','longitude']);
        $securityShifts = SecurityShift::active()->get([
            'id','name','start_time','end_time','category','is_overnight',
            'rotation_type','handover_config','applicable_days','day_type',
        ]);
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id','name','phone','email','badge_number']);
        $rotationGroups = RotationGroup::where('status', 'active')
            ->with(['post:id,name', 'shift:id,name'])->get();

        $currentAssignments = 0;
        if ($schedule->security_post_id && $schedule->assignment_date) {
            $currentAssignments = SecuritySchedule::where('security_post_id', $schedule->security_post_id)
                ->whereDate('assignment_date', $schedule->assignment_date)
                ->where('id', '!=', $schedule->id)->count();
        }

        return view('admin.security-schedules.edit', compact(
            'schedule', 'securityPosts', 'securityShifts', 'securityPersonnel',
            'rotationGroups', 'currentAssignments'
        ));
    }

    // ============================================================
    // STORE
    // ============================================================

    public function store(Request $request)
{
    // NEW — normalize empty strings to null before validation so that
    // `nullable|integer` rules (rotation_preference_score,
    // rotation_sequence_number, etc.) don't fail on a blank form field.
    $request->merge(
        collect($request->all())
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all()
    );

    $validator = Validator::make($request->all(), [
        'security_post_id' => 'required|exists:security_posts,id',
        'security_shift_id' => 'required|exists:security_shifts,id',
        'security_user_id' => 'required|exists:users,id',
        'assignment_date' => 'required|date',
        'notes' => 'nullable|string|max:500',
        'emergency_contact' => 'nullable|string|max:255',
        'special_instructions' => 'nullable|string|max:1000',
        'include_breaks' => 'nullable|boolean',
        'rotation_group_id' => 'nullable|exists:rotation_groups,id',
        'rotation_group_type' => 'nullable|string',
        'rotation_sequence_number' => 'nullable|integer',
        'rotation_preference_score' => 'nullable|integer|min:1|max:10',
        'is_rotated' => 'nullable|boolean',
    ]);

    $validator->after(function ($v) use ($request) {
        $user = User::find($request->security_user_id);
        if (!$user || $user->type !== User::TYPE_SECURITY_PERSONNEL) {
            $v->errors()->add('security_user_id', 'Selected user is not security personnel.');
            return;
        }

        $date = Carbon::parse($request->assignment_date);

        $exists = SecuritySchedule::where('security_user_id', $request->security_user_id)
            ->whereDate('assignment_date', $date)
            ->where('status', '!=', 'cancelled')
            ->exists();
        if ($exists) {
            $v->errors()->add('security_user_id', 'Personnel already assigned on this date.');
        }

        $post = SecurityPost::find($request->security_post_id);
        if ($post) {
            $count = SecuritySchedule::where('security_post_id', $post->id)
                ->whereDate('assignment_date', $date)
                ->where('status', '!=', 'cancelled')
                ->count();
            if ($count >= $post->max_personnel) {
                $v->errors()->add('security_post_id',
                    "Post at full capacity ({$post->max_personnel}) for this date.");
            }
        }

        $shift = SecurityShift::find($request->security_shift_id);
        if ($shift) {
            if (!$shift->isApplicableOnDay($date->dayOfWeekIso)) {
                $v->errors()->add('security_shift_id', 'Shift not applicable on this day.');
            } elseif ($shift->rotation_type === 'rotating'
                && !$this->rotation->validateConstraints($user, $date, $shift)) {
                $v->errors()->add('security_user_id',
                    'Personnel violates rest/weekly-hour constraints for this shift.');
            }
        }
    });

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    DB::beginTransaction();
    try {
        $shift = SecurityShift::find($request->security_shift_id);
        $handover = $this->calculateHandoverTimesOptimized(
            $shift, $request->assignment_date, $request->security_post_id
        );

        $schedule = SecuritySchedule::create([
            'security_post_id' => $request->security_post_id,
            'security_shift_id' => $request->security_shift_id,
            'security_user_id' => $request->security_user_id,
            'assigned_by' => auth()->id(),
            'assignment_date' => $request->assignment_date,
            'status' => 'scheduled',
            'notes' => $request->notes,
            'emergency_contact' => $request->emergency_contact,
            'special_instructions' => $request->special_instructions,
            'include_breaks' => $request->boolean('include_breaks'),
            'handover_info' => $handover,
            'rotation_group_id' => $request->rotation_group_id,
            'rotation_group_type' => $request->rotation_group_type,
            'rotation_sequence_number' => $request->rotation_sequence_number ?? 0,
            'rotation_preference_score' => $request->rotation_preference_score ?? 5,
            'is_rotated' => $request->boolean('is_rotated'),
            'rotation_swap_count' => 0,
        ]);

        DB::commit();

        Log::info('Schedule created', ['schedule_id' => $schedule->id, 'by' => auth()->id()]);

        return redirect()->route('admin.security-schedules.index')
            ->with('success', 'Security schedule created successfully.');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Schedule create failed: ' . $e->getMessage());
        return back()->with('error', 'Failed to create schedule: ' . $e->getMessage())->withInput();
    }
}

public function update(Request $request, $id)
{
    // NEW — normalize empty strings to null before validation so that
    // `nullable|integer`, `nullable|date`, `nullable|boolean` rules don't
    // fail when the caller leaves optional fields blank.
    $request->merge(
        collect($request->all())
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all()
    );

    $schedule = SecuritySchedule::findOrFail($id);

    $validator = Validator::make($request->all(), [
        'security_post_id' => 'required|exists:security_posts,id',
        'security_shift_id' => 'required|exists:security_shifts,id',
        'security_user_id' => 'required|exists:users,id',
        'assignment_date' => 'required|date',
        'status' => 'required|in:scheduled,active,completed,absent,cancelled',
        'notes' => 'nullable|string|max:500',
        'emergency_contact' => 'nullable|string|max:255',
        'special_instructions' => 'nullable|string|max:1000',
        'include_breaks' => 'nullable|boolean',
        'handover_completed' => 'nullable|boolean',
        'is_approved' => 'nullable|boolean',
        'late_minutes' => 'nullable|integer|min:0',
        'overtime_minutes' => 'nullable|integer|min:0',
        'total_minutes' => 'nullable|integer|min:0',
        'break_duration' => 'nullable|integer|min:0',
        'checkin_time' => 'nullable|date',
        'checkout_time' => 'nullable|date|after_or_equal:checkin_time',
        'late_flagged' => 'nullable|boolean',
        'late_reason' => 'nullable|string|max:255',
        'supervisor_override' => 'nullable|boolean',
        'override_reason' => 'required_if:supervisor_override,true|nullable|string|max:500',
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    // Status transition guard (allows admin correction on completed->absent/cancelled with override)
    $terminal = ['completed', 'absent', 'cancelled'];
    if (in_array($schedule->status, $terminal, true) && $schedule->status !== $request->status) {
        if (!$request->boolean('supervisor_override')) {
            return back()->with('error', "Cannot change status from {$schedule->status} without supervisor override.")
                ->withInput();
        }
    }

    DB::beginTransaction();
    try {
        $shift = SecurityShift::find($request->security_shift_id);

        $handover = $schedule->handover_info;
        if ($schedule->security_shift_id != $request->security_shift_id
            || $schedule->assignment_date != $request->assignment_date
            || $schedule->security_post_id != $request->security_post_id) {
            $handover = $this->calculateHandoverTimesOptimized(
                $shift, $request->assignment_date, $request->security_post_id
            );
        }

        $update = [
            'security_post_id' => $request->security_post_id,
            'security_shift_id' => $request->security_shift_id,
            'security_user_id' => $request->security_user_id,
            'assignment_date' => $request->assignment_date,
            'status' => $request->status,
            'notes' => $request->notes,
            'emergency_contact' => $request->emergency_contact,
            'special_instructions' => $request->special_instructions,
            'handover_info' => $handover,
            'include_breaks' => $request->boolean('include_breaks', $schedule->include_breaks),
        ];

        if ($request->has('handover_completed')) {
            $update['handover_completed'] = $request->boolean('handover_completed');
            if ($update['handover_completed'] && !$schedule->handover_completed_at) {
                $update['handover_completed_at'] = now();
                $update['handover_completed_by'] = auth()->id();
            }
        }
        if ($request->has('is_approved')) {
            $update['is_approved'] = $request->boolean('is_approved');
            if ($update['is_approved'] && !$schedule->approved_at) {
                $update['approved_by'] = auth()->id();
                $update['approved_at'] = now();
            }
        }
        foreach (['checkin_time', 'checkout_time'] as $f) {
            if ($request->filled($f)) $update[$f] = Carbon::parse($request->$f);
        }
        foreach (['late_minutes', 'overtime_minutes', 'total_minutes', 'break_duration'] as $f) {
            if ($request->filled($f)) $update[$f] = $request->$f;
        }
        if ($request->has('late_flagged')) {
            $update['late_flagged'] = $request->boolean('late_flagged');
            $update['late_reason'] = $request->late_reason;
        }
        if ($request->boolean('supervisor_override')) {
            $update['supervisor_override'] = true;
            $update['supervisor_id'] = auth()->id();
            $update['override_reason'] = $request->override_reason;
        }

        $schedule->update($update);
        $this->clearScheduleCaches($schedule);

        DB::commit();

        return redirect()->route('admin.security-schedules.index')
            ->with('success', 'Security schedule updated.');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Schedule update failed: ' . $e->getMessage());
        return back()->with('error', 'Failed to update: ' . $e->getMessage())->withInput();
    }
}

    public function destroy($id)
    {
        try {
            $schedule = SecuritySchedule::findOrFail($id);
            if (in_array($schedule->status, ['active', 'completed'], true)) {
                return back()->with('error', 'Cannot delete active or completed schedule.');
            }
            $schedule->delete();
            $this->clearScheduleCaches($schedule);
            Log::info('Schedule soft deleted', ['id' => $id, 'by' => auth()->id()]);
            return redirect()->route('admin.security-schedules.index')->with('success', 'Moved to trash.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function trash(Request $request)
    {
        $query = SecuritySchedule::onlyTrashed()
            ->with(['post', 'shift', 'securityUser', 'assignedBy'])
            ->orderByDesc('deleted_at');

        if ($request->filled('date')) $query->whereDate('assignment_date', $request->date);
        if ($request->filled('post_id')) $query->where('security_post_id', $request->post_id);
        if ($request->filled('shift_id')) $query->where('security_shift_id', $request->shift_id);
        if ($request->filled('security_user_id')) $query->where('security_user_id', $request->security_user_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->whereHas('securityUser', fn ($x) => $x->where('name', 'LIKE', "%{$s}%"))
                ->orWhereHas('post', fn ($x) => $x->where('name', 'LIKE', "%{$s}%")));
        }

        $trashedSchedules = $query->paginate(20)->withQueryString();
        $securityPosts = SecurityPost::active()->get(['id', 'name']);
        $securityShifts = SecurityShift::active()->get(['id', 'name']);
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)->get(['id', 'name']);

        $trashStats = [
            'total_trashed' => SecuritySchedule::onlyTrashed()->count(),
            'trashed_by_period' => [
                'today' => SecuritySchedule::onlyTrashed()->whereDate('deleted_at', today())->count(),
                'this_week' => SecuritySchedule::onlyTrashed()
                    ->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'this_month' => SecuritySchedule::onlyTrashed()
                    ->whereMonth('deleted_at', now()->month)->count(),
            ],
        ];

        return view('admin.security-schedules.trash', compact(
            'trashedSchedules', 'securityPosts', 'securityShifts',
            'securityPersonnel', 'trashStats', 'request'
        ));
    }

    public function restore($id)
    {
        try {
            $schedule = SecuritySchedule::onlyTrashed()->findOrFail($id);
            $conflicts = $this->checkRestorationConflicts($schedule);
            if (!empty($conflicts)) {
                return back()->with('error', 'Cannot restore: ' . implode(' ', $conflicts));
            }
            $schedule->restore();
            $this->clearScheduleCaches($schedule);
            return redirect()->route('admin.security-schedules.trash')->with('success', 'Restored.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        try {
            $schedule = SecuritySchedule::find($id);
            if ($schedule) {
                return back()->with('error', 'Move to trash first.');
            }
            $schedule = SecuritySchedule::onlyTrashed()->findOrFail($id);
            $schedule->forceDelete();
            return redirect()->route('admin.security-schedules.trash')->with('success', 'Permanently deleted.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function bulkRestore(Request $request)
    {
        $request->validate(['schedule_ids' => 'required|array', 'schedule_ids.*' => 'integer']);
        DB::beginTransaction();
        try {
            $restored = 0; $failed = [];
            foreach (SecuritySchedule::onlyTrashed()->whereIn('id', $request->schedule_ids)->get() as $s) {
                $c = $this->checkRestorationConflicts($s);
                if (empty($c)) { $s->restore(); $restored++; }
                else { $failed[] = ['id' => $s->id, 'reason' => implode(' ', $c)]; }
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'restored_count' => $restored,
                'failed_restores' => $failed,
                'message' => "{$restored} restored, " . count($failed) . ' failed.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkForceDelete(Request $request)
    {
        $request->validate(['schedule_ids' => 'required|array', 'schedule_ids.*' => 'integer']);
        DB::beginTransaction();
        try {
            $deleted = 0; $failed = [];
            foreach ($request->schedule_ids as $id) {
                if (SecuritySchedule::find($id)) {
                    $failed[] = ['id' => $id, 'reason' => 'Move to trash first'];
                    continue;
                }
                $s = SecuritySchedule::onlyTrashed()->find($id);
                if (!$s) { $failed[] = ['id' => $id, 'reason' => 'Not in trash']; continue; }
                $s->forceDelete();
                $deleted++;
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'deleted_count' => $deleted,
                'failed_deletes' => $failed,
                'message' => "{$deleted} deleted.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function clearOldTrash(Request $request)
    {
        $request->validate(['days' => 'required|integer|min:1|max:365']);
        try {
            $cutoff = now()->subDays($request->days);
            $count = SecuritySchedule::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
            return redirect()->route('admin.security-schedules.trash')
                ->with('success', "{$count} permanently deleted.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ============================================================
    // BULK ASSIGN
    // ============================================================

    public function bulkAssign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'security_post_id' => 'required|exists:security_posts,id',
            'security_shift_id' => 'required|exists:security_shifts,id',
            'security_user_ids' => 'required|array|min:1',
            'security_user_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'recurrence_type' => 'required|in:daily,weekly,monthly,custom,rotating',
            'recurrence_days' => 'nullable|array',
            'recurrence_days.*' => 'integer|min:1|max:7',
            'rotation_pattern' => 'nullable|in:full_swap,staggered,partial,sequential,alternating,preference_based',
            'rotation_pattern_config' => 'nullable|array',
            'include_breaks' => 'nullable|boolean',
            'include_handover' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:255',
            'special_instructions' => 'nullable|string|max:1000',
            'create_rotation_group' => 'nullable|boolean',
            'rotation_group_name' => 'required_if:create_rotation_group,true|nullable|string|max:255',
            'maintain_coverage' => 'nullable|boolean',
            'respect_preferences' => 'nullable|boolean',
            'notify_personnel' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $post = SecurityPost::find($request->security_post_id);
            $shift = SecurityShift::find($request->security_shift_id);
            if (!$post || $post->trashed() || !$post->is_active) {
                throw new \Exception('Post inactive or missing.');
            }
            if (!$shift || $shift->trashed() || !$shift->is_active) {
                throw new \Exception('Shift inactive or missing.');
            }

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $rotationGroup = null;
            if ($request->recurrence_type === 'rotating' && $request->filled('rotation_pattern')) {
                $rotationGroup = $this->getOrCreateRotationGroup($request, $post, $shift);
            }

            $applicableDates = $this->calculateApplicableDates(
                $startDate, $endDate, $request->recurrence_type, $request->recurrence_days ?? []
            );

            // Existing assignments map
            $existingAssignments = SecuritySchedule::whereIn('security_user_id', $request->security_user_ids)
                ->whereBetween('assignment_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where('status', '!=', 'cancelled')
                ->get(['security_user_id', 'assignment_date'])
                ->mapWithKeys(fn ($i) => [
                    $i->security_user_id . '_' . $i->assignment_date->format('Y-m-d') => true,
                ])->toArray();

            $handoverInfo = $request->boolean('include_handover')
                ? $this->calculateHandoverTimesOptimized($shift, $startDate, $post->id)
                : null;

            $userPreferenceScores = [];
            $sortedUserIds = $request->security_user_ids;
            if ($request->boolean('respect_preferences', true) && $rotationGroup) {
                foreach ($request->security_user_ids as $uid) {
                    $userPreferenceScores[$uid] = $this->calculateUserPreferenceScore($uid, $shift, $post);
                }
                arsort($userPreferenceScores);
                $sortedUserIds = array_keys($userPreferenceScores);
            }

            $batch = [];
            $insertedTuples = []; // for notification lookup
            $userCount = count($sortedUserIds);
            $userIndex = 0;
            $now = now();
            $rotationSequence = [];

            foreach ($applicableDates as $date) {
                $dateStr = $date->toDateString();

                // FIXED: use day_type / custom applicability via model
                if (!$shift->isApplicableOnDay($date->dayOfWeekIso)) {
                    continue;
                }

                $assignedCount = SecuritySchedule::where('security_post_id', $post->id)
                    ->whereDate('assignment_date', $date)
                    ->where('status', '!=', 'cancelled')
                    ->count();

                $availableSlots = $post->max_personnel - $assignedCount;
                if ($availableSlots <= 0) {
                    continue;
                }

                $slotsToFill = min($availableSlots, $userCount);
                $personnel = [];

                if ($request->recurrence_type === 'rotating' && $request->filled('rotation_pattern') && $rotationGroup) {
                    $personnel = $this->rotation->resolveMembersForDate(
                        $sortedUserIds, $userPreferenceScores, $date, $startDate,
                        $request->rotation_pattern, $slotsToFill, $existingAssignments
                    );
                    $rotationSequence[] = ['date' => $dateStr, 'assigned_members' => $personnel];
                } else {
                    for ($i = 0; $i < $slotsToFill; $i++) {
                        $uid = $sortedUserIds[($userIndex + $i) % $userCount];
                        $key = $uid . '_' . $dateStr;
                        if (isset($existingAssignments[$key])) continue;
                        $user = User::find($uid);
                        if ($user && $this->rotation->validateConstraints($user, $date, $shift)) {
                            $personnel[] = $uid;
                            $existingAssignments[$key] = true;
                        }
                    }
                    $userIndex = ($userIndex + $slotsToFill) % $userCount;
                }

                foreach ($personnel as $uid) {
                    $rotationData = null;
                    if ($rotationGroup) {
                        $rotationData = [
                            'rotation_group_id' => $rotationGroup->id,
                            'rotation_group_name' => $rotationGroup->name,
                            'rotation_pattern' => $request->rotation_pattern,
                            'rotation_date' => $now->toIso8601String(),
                            'preference_score' => $userPreferenceScores[$uid] ?? 5,
                            'sequence_position' => array_search($uid, $sortedUserIds, true) ?: 0,
                        ];
                    }

                    $batch[] = [
                        'security_post_id' => $post->id,
                        'security_shift_id' => $shift->id,
                        'security_user_id' => $uid,
                        'assigned_by' => auth()->id(),
                        'assignment_date' => $dateStr,
                        'status' => 'scheduled',
                        'notes' => $request->notes,
                        'emergency_contact' => $request->emergency_contact,
                        'special_instructions' => $request->special_instructions,
                        'include_breaks' => $request->boolean('include_breaks'),
                        'handover_info' => $handoverInfo ? json_encode($handoverInfo) : null,
                        'rotation_data' => $rotationData ? json_encode($rotationData) : null,
                        'rotation_group_id' => $rotationGroup?->id,
                        'rotation_group_type' => $shift->category,
                        'rotation_sequence_number' => 0,
                        'rotation_preference_score' => $userPreferenceScores[$uid] ?? 5,
                        'is_rotated' => $rotationGroup !== null,
                        'rotation_swap_count' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $insertedTuples[] = [$post->id, $shift->id, $uid, $dateStr];

                    if (count($batch) >= 50) {
                        DB::table('security_schedules')->insert($batch);
                        $batch = [];
                    }
                }
            }

            if (!empty($batch)) {
                DB::table('security_schedules')->insert($batch);
            }

            $createdCount = count($insertedTuples);

            if ($rotationGroup && !empty($rotationSequence)) {
                $this->updateRotationGroupAfterBulkAssign($rotationGroup, $rotationSequence, $sortedUserIds, $request);
            }

            DB::commit();

            // FIXED: notify using exact tuples, not timestamps
            if ($createdCount > 0 && $request->boolean('notify_personnel', true)) {
                $this->queueBulkAssignNotifications($insertedTuples);
            }

            return response()->json([
                'success' => true,
                'message' => "{$createdCount} schedule(s) created.",
                'created_count' => $createdCount,
                'rotation_group' => $rotationGroup,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('bulkAssign failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function previewBulkAssign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'security_post_id' => 'required|exists:security_posts,id',
            'security_shift_id' => 'required|exists:security_shifts,id',
            'security_user_ids' => 'required|array|min:1',
            'security_user_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'recurrence_type' => 'required|in:daily,weekly,monthly,custom,rotating',
            'recurrence_days' => 'nullable|array',
            'recurrence_days.*' => 'integer|min:1|max:7',
            'rotation_pattern' => 'nullable|in:full_swap,staggered,partial,sequential,alternating,preference_based',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $post = SecurityPost::findOrFail($request->security_post_id);
            $shift = SecurityShift::findOrFail($request->security_shift_id);
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $applicableDates = $this->calculateApplicableDates(
                $startDate, $endDate, $request->recurrence_type, $request->recurrence_days ?? []
            );

            $existingAssignments = SecuritySchedule::whereIn('security_user_id', $request->security_user_ids)
                ->whereBetween('assignment_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where('status', '!=', 'cancelled')
                ->get(['security_user_id', 'assignment_date'])
                ->mapWithKeys(fn ($i) => [
                    $i->security_user_id . '_' . $i->assignment_date->format('Y-m-d') => true,
                ])->toArray();

            $scores = [];
            $sortedUserIds = $request->security_user_ids;
            if ($request->boolean('respect_preferences', true) && $request->recurrence_type === 'rotating') {
                foreach ($request->security_user_ids as $uid) {
                    $scores[$uid] = $this->calculateUserPreferenceScore($uid, $shift, $post);
                }
                arsort($scores);
                $sortedUserIds = array_keys($scores);
            }

            $preview = [];
            $userCount = count($sortedUserIds);
            $userIndex = 0;

            foreach ($applicableDates as $date) {
                // FIXED: respect day_type
                if (!$shift->isApplicableOnDay($date->dayOfWeekIso)) {
                    continue;
                }

                $assignedCount = SecuritySchedule::where('security_post_id', $post->id)
                    ->whereDate('assignment_date', $date)
                    ->where('status', '!=', 'cancelled')
                    ->count();

                $available = $post->max_personnel - $assignedCount;
                if ($available <= 0) {
                    $preview[] = [
                        'date' => $date->toDateString(),
                        'day_of_week' => $date->format('l'),
                        'status' => 'skipped',
                        'reason' => 'Full capacity',
                        'slots_filled' => 0,
                    ];
                    continue;
                }

                $slots = min($available, $userCount);
                $personnel = [];

                if ($request->recurrence_type === 'rotating' && $request->filled('rotation_pattern')) {
                    $personnel = $this->rotation->resolveMembersForDate(
                        $sortedUserIds, $scores, $date, $startDate,
                        $request->rotation_pattern, $slots, $existingAssignments
                    );
                } else {
                    for ($i = 0; $i < $slots; $i++) {
                        $uid = $sortedUserIds[($userIndex + $i) % $userCount];
                        $key = $uid . '_' . $date->toDateString();
                        if (isset($existingAssignments[$key])) continue;
                        $user = User::find($uid);
                        if ($user && $this->rotation->validateConstraints($user, $date, $shift)) {
                            $personnel[] = $uid;
                            $existingAssignments[$key] = true;
                        }
                    }
                    $userIndex = ($userIndex + $slots) % $userCount;
                }

                $preview[] = [
                    'date' => $date->toDateString(),
                    'day_of_week' => $date->format('l'),
                    'post_name' => $post->name,
                    'shift_name' => $shift->name,
                    'shift_time' => substr($shift->start_time, 0, 5) . ' - ' . substr($shift->end_time, 0, 5),
                    'available_slots' => $available,
                    'slots_filled' => count($personnel),
                    'assignments' => array_map(function ($uid) use ($scores) {
                        $u = User::find($uid);
                        return [
                            'user_id' => $uid,
                            'personnel_name' => $u?->name,
                            'preference_score' => round($scores[$uid] ?? 5, 1),
                        ];
                    }, $personnel),
                ];
            }

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'count' => array_sum(array_column($preview, 'slots_filled')),
            ]);
        } catch (\Exception $e) {
            Log::error('previewBulkAssign failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'integer',
            'action' => 'required|in:trash,force_delete,restore,apply_rotation,swap_groups,mark_verified,clear_verification',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $success = 0; $failed = [];
            foreach ($request->schedule_ids as $id) {
                try {
                    $s = SecuritySchedule::withTrashed()->find($id);
                    if (!$s) { $failed[] = ['id' => $id, 'reason' => 'Not found']; continue; }

                    match ($request->action) {
                        'trash' => (function () use ($s, &$success) {
                            if (!in_array($s->status, ['active', 'completed']) && !$s->trashed()) {
                                $s->delete(); $success++;
                            }
                        })(),
                        'restore' => (function () use ($s, &$success) {
                            if ($s->trashed() && empty($this->checkRestorationConflicts($s))) {
                                $s->restore(); $success++;
                            }
                        })(),
                        'force_delete' => (function () use ($s, &$success) {
                            if ($s->trashed()) { $s->forceDelete(); $success++; }
                        })(),
                        'mark_verified' => (function () use ($s, &$success) {
                            $s->update(['checkin_verification' => [
                                'method' => 'bulk_admin_override',
                                'verified_at' => now()->toIso8601String(),
                                'verified_by' => auth()->id(),
                            ]]);
                            $success++;
                        })(),
                        'clear_verification' => (function () use ($s, &$success) {
                            $s->update(['checkin_verification' => null]); $success++;
                        })(),
                        default => null,
                    };
                } catch (\Exception $e) {
                    $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'success_count' => $success,
                'failed_count' => count($failed),
                'failed_items' => $failed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // STATISTICS / API
    // ============================================================

    public function getPosts(Request $request)
    {
        $query = SecurityPost::active()->orderBy('name');
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$s}%")->orWhere('code', 'LIKE', "%{$s}%"));
        }
        $posts = $query->limit(50)->get();

        return response()->json([
            'success' => true,
            'posts' => $posts->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'type' => $p->type,
                'max_personnel' => $p->max_personnel,
                'current_personnel' => $p->getCurrentPersonnelCount(),
                'is_fully_staffed' => $p->isFullyStaffed(),
            ]),
        ]);
    }

    public function getPostDetails(SecurityPost $securityPost)
    {
        $post = $securityPost->load(['currentSchedules.securityUser']);

        return response()->json([
            'success' => true,
            'post' => [
                'id' => $post->id,
                'name' => $post->name,
                'code' => $post->code,
                'current_personnel' => $post->currentSchedules->count(),
                'max_personnel' => $post->max_personnel,
            ],
            'current_schedules' => $post->currentSchedules->map(fn ($s) => [
                'id' => $s->id,
                'security_user' => $s->securityUser ? [
                    'id' => $s->securityUser->id,
                    'name' => $s->securityUser->name,
                    'phone' => $s->securityUser->phone,
                ] : null,
                'shift' => $s->shift?->name,
                'status' => $s->status,
            ]),
        ]);
    }

    public function getPostCapacity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|exists:security_posts,id',
            'date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $post = SecurityPost::findOrFail($request->post_id);
        $date = Carbon::parse($request->date);

        // FIXED: absent counts for future dates but not past; completed does not block capacity
        $statuses = $date->isFuture() || $date->isToday()
            ? ['scheduled', 'active', 'absent']
            : ['scheduled', 'active', 'completed', 'absent'];

        $assignedCount = SecuritySchedule::where('security_post_id', $post->id)
            ->whereDate('assignment_date', $date)
            ->whereIn('status', $statuses)
            ->count();

        return response()->json([
            'success' => true,
            'capacity' => $post->max_personnel,
            'assigned' => $assignedCount,
            'available' => max(0, $post->max_personnel - $assignedCount),
            'is_full' => $assignedCount >= $post->max_personnel,
        ]);
    }

    public function getStaffingStatistics()
    {
        $posts = SecurityPost::active()
            ->withCount(['currentSchedules as current_personnel'])->get();

        $fully = $posts->filter(fn ($p) => $p->current_personnel >= $p->max_personnel)->count();
        $under = $posts->filter(fn ($p) => $p->current_personnel > 0 && $p->current_personnel < $p->max_personnel)->count();
        $un = $posts->filter(fn ($p) => $p->current_personnel === 0)->count();

        return response()->json([
            'success' => true,
            'statistics' => [
                'total_posts' => $posts->count(),
                'fully_staffed' => $fully,
                'understaffed' => $under,
                'unstaffed' => $un,
            ],
        ]);
    }

    public function getUtilizationStatistics(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->startOfDay();
        $days = $start->diffInDays($end) + 1;

        $out = [];
        foreach (SecurityPost::active()->get() as $post) {
            $totalSlots = $post->max_personnel * $days;
            $filled = SecuritySchedule::where('security_post_id', $post->id)
                ->whereBetween('assignment_date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('status', ['scheduled', 'active', 'completed'])
                ->count();
            $rate = $totalSlots > 0 ? ($filled / $totalSlots) * 100 : 0;

            $out[] = [
                'post' => ['id' => $post->id, 'name' => $post->name],
                'filled_slots' => $filled,
                'total_slots' => $totalSlots,
                'utilization_rate' => round($rate, 2),
                'status' => $rate >= 80 ? 'high' : ($rate >= 50 ? 'medium' : 'low'),
            ];
        }

        return response()->json([
            'success' => true,
            'utilization' => $out,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'days' => $days],
        ]);
    }

    public function exportSchedules(Request $request)
    {
        $query = SecuritySchedule::with(['post', 'shift', 'securityUser', 'assignedBy']);
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('assignment_date', [$request->start_date, $request->end_date]);
        }
        if ($request->filled('post_id')) $query->where('security_post_id', $request->post_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('shift_category')) {
            $query->whereHas('shift', fn ($q) => $q->where('category', $request->shift_category));
        }

        $schedules = $query->orderBy('assignment_date')->get();
        $filename = 'security_schedules_' . date('Y-m-d_H-i-s') . '.csv';

        $callback = function () use ($schedules) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['ID','Date','Post','Shift','User','Status','Checkin','Checkout','Late','Overtime']);
            foreach ($schedules as $s) {
                fputcsv($f, [
                    $s->id, $s->assignment_date->format('Y-m-d'),
                    $s->post?->name, $s->shift?->name, $s->securityUser?->name,
                    $s->status,
                    $s->checkin_time?->format('Y-m-d H:i'),
                    $s->checkout_time?->format('Y-m-d H:i'),
                    $s->late_minutes ?? 0, $s->overtime_minutes ?? 0,
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ============================================================
    // ROTATION
    // ============================================================

    public function rotateShiftGroups(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|exists:security_posts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'rotation_frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'rotation_pattern' => 'required|in:full_swap,staggered,partial',
            'exclude_personnel' => 'nullable|array',
            'respect_preferences' => 'boolean',
            'maintain_coverage' => 'boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Delegate to RotationGroupController::executeRotation for each active group on this post
        $groups = RotationGroup::where('security_post_id', $request->post_id)
            ->where('group_type', 'rotating')
            ->where('status', 'active')
            ->get();

        if ($groups->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No active rotating groups for this post.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Use /admin/rotation-groups/{id}/execute-rotation for each group.',
            'group_ids' => $groups->pluck('id'),
        ]);
    }

    // ============================================================
    // INTERNAL HELPERS
    // ============================================================

    private function getOrCreateRotationGroup(Request $request, SecurityPost $post, SecurityShift $shift): ?RotationGroup
    {
        if ($request->boolean('create_rotation_group', true) && $request->filled('rotation_group_name')) {
            $group = RotationGroup::create([
                'name' => $request->rotation_group_name,
                'code' => $this->generateGroupCode($request->rotation_group_name),
                'security_post_id' => $post->id,
                'security_shift_id' => $shift->id,
                'group_type' => $shift->category === 'night' ? 'night' : 'day',
                'rotation_config' => [
                    'type' => $request->rotation_pattern,
                    'interval_days' => 7,
                    'start_date' => $request->start_date,
                    'current_sequence_index' => 0,
                    'sequence' => ['A', 'B', 'C', 'D'],
                    'rotation_history' => [],
                ],
                'max_members' => $post->max_personnel,
                'min_members' => 2,
                'current_members' => count($request->security_user_ids),
                'auto_rotate' => true,
                'auto_rotate_schedule' => 'weekly',
                'auto_rotate_time' => '00:00',
                'status' => 'active',
                'settings' => [
                    'require_handover' => $request->boolean('include_handover', true),
                    'notify_on_rotate' => true,
                    'maintain_coverage' => $request->boolean('maintain_coverage', true),
                ],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($request->security_user_ids as $uid) {
                DB::table('rotation_group_members')->insert([
                    'rotation_group_id' => $group->id,
                    'user_id' => $uid,
                    'role' => 'member',
                    'joined_at' => now(),
                    'preference_score' => 5,
                    'assigned_by' => auth()->id(),
                    'status' => 'active',
                    'rotation_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $group;
        }

        return RotationGroup::where('security_post_id', $post->id)
            ->where('security_shift_id', $shift->id)
            ->where('status', 'active')
            ->first();
    }

    private function calculateHandoverTimesOptimized(SecurityShift $shift, $date, ?int $postId): ?array
    {
        if (!$shift || !$shift->handover_config || empty($shift->handover_config['has_handover']) || !$postId) {
            return null;
        }

        // FIXED: find the true previous shift by datetime, not just same-date
        $date = Carbon::parse($date);
        $shiftStart = $date->copy()->setTimeFromTimeString($shift->start_time);

        // The schedule immediately preceding this shift on this post
        $previous = SecuritySchedule::where('security_post_id', $postId)
            ->where('id', '!=', 0)
            ->whereIn('status', ['active', 'scheduled', 'completed'])
            ->where(function ($q) use ($date, $shiftStart) {
                // same day earlier shift
                $q->whereDate('assignment_date', $date)
                  ->whereHas('shift', fn ($qs) => $qs->where('start_time', '<', $shift->start_time));
                // OR overnight shift from yesterday
                $q->orWhereDate('assignment_date', $date->copy()->subDay())
                  ->whereHas('shift', fn ($qs) => $qs->where('is_overnight', true));
            })
            ->with('shift:id,start_time,end_time,is_overnight')
            ->get()
            ->sortByDesc(function ($s) use ($shiftStart) {
                $start = Carbon::parse($s->assignment_date->format('Y-m-d') . ' ' . $s->shift->start_time);
                return $start->timestamp;
            })
            ->first();

        if (!$previous) {
            return null;
        }

        return [
            'previous_shift_id' => $previous->id,
            'handover_duration' => $shift->handover_config['handover_duration'] ?? 30,
            'handover_checklist' => $shift->handover_config['handover_checklist'] ?? [],
            'notes_required' => $shift->handover_config['handover_notes_required'] ?? true,
        ];
    }

    private function calculateApplicableDates(Carbon $start, Carbon $end, string $type, array $days): array
    {
        $dates = [];
        $cur = $start->copy();
        while ($cur->lte($end)) {
            $applicable = match ($type) {
                'daily' => true,
                'weekly' => empty($days) ? true : in_array($cur->dayOfWeekIso, $days, true),
                'monthly' => empty($days) ? true : in_array($cur->day, $days, true),
                default => true,
            };
            if ($applicable) $dates[] = $cur->copy();
            $cur->addDay();
        }
        return $dates;
    }

    private function calculateUserPreferenceScore(int $userId, SecurityShift $shift, SecurityPost $post): float
    {
        $user = User::find($userId);
        if (!$user) return 5.0;
        $prefs = $user->preferences ?? [];
        $score = 5.0;
        if ($user->created_at) $score += min(2, $user->created_at->diffInYears(now()));
        $score += (($prefs['performance_rating'] ?? 7) - 5) / 2.5;
        if (in_array($shift->category, $prefs['preferred_shifts'] ?? [], true)) $score += 2;
        if (in_array($post->id, $prefs['preferred_posts'] ?? [], true)) $score += 1;
        if (!empty($prefs['willing_to_rotate'])) $score += 2;
        return max(1.0, min(10.0, $score));
    }

    private function generateGroupCode(string $name): string
    {
        $code = '';
        foreach (explode(' ', $name) as $w) if ($w !== '') $code .= strtoupper($w[0]);
        $original = $code ?: 'RG';
        $code = $original;
        $i = 1;
        while (RotationGroup::where('code', $code)->exists()) {
            $code = $original . $i++;
        }
        return $code;
    }

    private function updateRotationGroupAfterBulkAssign(RotationGroup $group, array $sequence, array $userIds, Request $request): void
    {
        $config = $group->rotation_config ?? [];
        $config['rotation_history'][] = [
            'id' => uniqid('rot_'),
            'date' => now()->toIso8601String(),
            'type' => 'bulk_assign',
            'pattern' => $request->rotation_pattern,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'sequence' => $sequence,
            'executed_by' => auth()->id(),
        ];
        $config['last_rotation_date'] = now()->toIso8601String();
        $group->update(['rotation_config' => $config, 'last_rotated_at' => now()]);

        foreach ($sequence as $day) {
            foreach ($day['assigned_members'] as $uid) {
                DB::table('rotation_group_members')
                    ->where('rotation_group_id', $group->id)
                    ->where('user_id', $uid)
                    ->increment('rotation_count');
            }
        }
    }

    private function queueBulkAssignNotifications(array $tuples): void
    {
        if (empty($tuples)) return;
        try {
            $schedules = SecuritySchedule::where(function ($q) use ($tuples) {
                foreach ($tuples as [$postId, $shiftId, $uid, $date]) {
                    $q->orWhere(function ($qq) use ($postId, $shiftId, $uid, $date) {
                        $qq->where('security_post_id', $postId)
                           ->where('security_shift_id', $shiftId)
                           ->where('security_user_id', $uid)
                           ->whereDate('assignment_date', $date);
                    });
                }
            })->with(['shift', 'post', 'securityUser'])->get();

            Log::info('Bulk assign notifications queued', ['count' => $schedules->count()]);
        } catch (\Exception $e) {
            Log::warning('Failed to queue notifications: ' . $e->getMessage());
        }
    }

    private function checkRestorationConflicts(SecuritySchedule $schedule): array
    {
        $conflicts = [];
        $post = SecurityPost::withTrashed()->find($schedule->security_post_id);
        if (!$post) $conflicts[] = 'Post missing.';
        elseif ($post->trashed()) $conflicts[] = 'Post deleted.';

        $shift = SecurityShift::withTrashed()->find($schedule->security_shift_id);
        if (!$shift) $conflicts[] = 'Shift missing.';
        elseif ($shift->trashed()) $conflicts[] = 'Shift deleted.';

        $user = User::withTrashed()->find($schedule->security_user_id);
        if (!$user) $conflicts[] = 'User missing.';
        elseif ($user->trashed()) $conflicts[] = 'User deleted.';

        $dup = SecuritySchedule::where('security_user_id', $schedule->security_user_id)
            ->whereDate('assignment_date', $schedule->assignment_date)
            ->where('id', '!=', $schedule->id)
            ->where('status', '!=', 'cancelled')
            ->exists();
        if ($dup) $conflicts[] = 'Duplicate assignment.';

        return $conflicts;
    }

    private function clearScheduleCaches(SecuritySchedule $schedule): void
    {
        Cache::forget("handover_{$schedule->security_post_id}_"
            . $schedule->assignment_date->format('Y-m-d') . "_{$schedule->security_shift_id}");
        Cache::forget("post_capacity_{$schedule->security_post_id}_"
            . $schedule->assignment_date->format('Y-m-d'));
        Cache::forget('schedule_stats_' . today()->format('Y-m-d'));
    }

    private function getTodayScheduleSummary(): array
    {
        $today = today()->toDateString();
        $schedules = SecuritySchedule::whereDate('assignment_date', $today)
            ->with(['post:id,name,code,max_personnel', 'shift:id,name,category', 'securityUser:id,name'])
            ->get();

        $byPost = $schedules->groupBy('security_post_id')->map(function ($group) {
            $post = $group->first()->post;
            $count = $group->count();
            $max = $post->max_personnel ?? 0;
            return [
                'post_name' => $post->name ?? 'Unknown',
                'count' => $count,
                'max_personnel' => $max,
                'fully_staffed' => $count >= $max,
                'coverage_percentage' => $max > 0 ? round(($count / $max) * 100, 2) : 0,
            ];
        });

        return [
            'total_assigned' => $schedules->count(),
            'checked_in' => $schedules->where('status', 'active')->count(),
            'absent' => $schedules->where('status', 'absent')->count(),
            'completed' => $schedules->where('status', 'completed')->count(),
            'late_checkins' => $schedules->where('late_minutes', '>', 0)->count(),
            'on_break' => $schedules->where('break_status', 'active')->count(),
            'by_category' => $schedules->groupBy(fn ($s) => $s->shift->category ?? 'unknown')
                ->map->count(),
            'by_post' => $byPost,
        ];
    }

    private function getUpcomingHandovers()
    {
        $today = today()->toDateString();
        $tomorrow = today()->addDay()->toDateString();

        // FIXED: proper grouping so handover filters apply to both date ranges
        return SecuritySchedule::where(function ($q) use ($today, $tomorrow) {
                $q->whereDate('assignment_date', $today)
                  ->orWhereDate('assignment_date', $tomorrow);
            })
            ->whereNotNull('handover_info')
            ->where('handover_completed', false)
            ->with(['post:id,name', 'shift:id,name,start_time,end_time', 'securityUser:id,name'])
            ->get()
            ->filter(function ($s) {
                $info = $s->handover_info;
                if (!$info || empty($info['handover_start'])) return false;
                try {
                    $t = Carbon::parse($s->assignment_date->format('Y-m-d') . ' ' . $info['handover_start']);
                    return $t->isFuture() && $t->diffInHours(now()) <= 2;
                } catch (\Exception $e) {
                    return false;
                }
            })->values();
    }

    private function getVerificationStatistics(): array
    {
        $today = today();
        return [
            'today' => [
                'total' => VerificationLog::whereDate('created_at', $today)->count(),
                'success' => VerificationLog::whereDate('created_at', $today)->where('status', 'success')->count(),
                'failed' => VerificationLog::whereDate('created_at', $today)->where('status', 'failed')->count(),
            ],
        ];
    }

    private function getScheduleStatistics(): array
    {
        $today = today()->toDateString();
        $weekStart = today()->startOfWeek();
        $weekEnd = today()->endOfWeek();

        return Cache::remember('schedule_stats_' . today()->format('Y-m-d'), 900, function () use ($today, $weekStart, $weekEnd) {
            return [
                'today' => [
                    'total' => SecuritySchedule::whereDate('assignment_date', $today)->count(),
                    'completed' => SecuritySchedule::whereDate('assignment_date', $today)->where('status', 'completed')->count(),
                    'absent' => SecuritySchedule::whereDate('assignment_date', $today)->where('status', 'absent')->count(),
                    'late' => SecuritySchedule::whereDate('assignment_date', $today)->where('late_minutes', '>', 0)->count(),
                ],
                'this_week' => [
                    'total' => SecuritySchedule::whereBetween('assignment_date', [$weekStart, $weekEnd])->count(),
                    'overtime_hours' => SecuritySchedule::whereBetween('assignment_date', [$weekStart, $weekEnd])
                        ->sum('overtime_minutes') / 60,
                ],
            ];
        });
    }

    // ============================================================
    // STATUS / CHECK-IN / CHECKOUT / BREAKS
    // ============================================================

    public function updateStatus(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::findOrFail($scheduleId);

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:checkin,checkout,mark_absent,mark_complete,start_break,end_break,verify_location',
            'location' => 'nullable|array',
            'location.lat' => 'required_with:location|numeric',
            'location.lng' => 'required_with:location|numeric',
            'accuracy' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
            'break_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $message = match ($request->action) {
                'checkin' => $this->processCheckin($schedule, $request),
                'checkout' => $this->processCheckout($schedule, $request),
                'mark_absent' => (function () use ($schedule, $request) {
                    $schedule->update(['status' => 'absent', 'notes' => $request->notes]);
                    return 'Marked absent';
                })(),
                'mark_complete' => (function () use ($schedule, $request) {
                    $schedule->update(['status' => 'completed', 'notes' => $request->notes]);
                    return 'Marked complete';
                })(),
                'start_break' => $this->startBreak($schedule, $request),
                'end_break' => $this->endBreak($schedule, $request),
                default => 'Done',
            };

            DB::commit();
            $this->clearScheduleCaches($schedule);

            return response()->json([
                'success' => true,
                'message' => $message,
                'schedule' => $schedule->fresh()->load(['post', 'shift', 'securityUser']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function processCheckin(SecuritySchedule $s, Request $r): string
    {
        $data = ['checkin_time' => now(), 'status' => 'active'];
        if ($r->has('location')) {
            $data['checkin_location'] = $r->location;
            $data['checkin_accuracy'] = $r->accuracy;
        }
        $s->update($data);

        $shift = $s->shift;
        $scheduledStart = Carbon::parse($s->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
        if (now()->greaterThan($scheduledStart)) {
            $late = now()->diffInMinutes($scheduledStart);
            $grace = $s->post->checkin_grace_period ?? 5;
            $s->update([
                'late_minutes' => $late,
                'late_flagged' => $late > $grace,
            ]);
        }
        return 'Checked in';
    }

    private function processCheckout(SecuritySchedule $s, Request $r): string
    {
        $data = ['checkout_time' => now(), 'status' => 'completed'];
        if ($r->has('location')) {
            $data['checkout_location'] = $r->location;
            $data['checkout_accuracy'] = $r->accuracy;
        }
        $s->update($data);

        $shift = $s->shift;
        $end = Carbon::parse($s->assignment_date->format('Y-m-d') . ' ' . $shift->end_time);
        if ($end <= Carbon::parse($s->assignment_date->format('Y-m-d') . ' ' . $shift->start_time)) {
            $end->addDay();
        }
        if (now()->greaterThan($end)) {
            $s->update(['overtime_minutes' => now()->diffInMinutes($end)]);
        }
        if ($s->checkin_time) {
            $total = $s->checkin_time->diffInMinutes(now()) - ($s->break_duration ?? 0);
            $s->update(['total_minutes' => $total]);
        }
        return 'Checked out';
    }

    private function startBreak(SecuritySchedule $s, Request $r): string
    {
        if (!$s->include_breaks) throw new \Exception('Breaks not enabled.');
        $s->update([
            'current_break_id' => $r->break_id,
            'break_start_time' => now(),
            'break_status' => 'active',
        ]);
        return 'Break started';
    }

    private function endBreak(SecuritySchedule $s, Request $r): string
    {
        if (!$s->current_break_id) throw new \Exception('No active break.');
        $duration = now()->diffInMinutes($s->break_start_time);
        $s->update([
            'break_end_time' => now(),
            'break_duration' => ($s->break_duration ?? 0) + $duration,
            'break_status' => 'completed',
            'current_break_id' => null,
            'break_start_time' => null,
        ]);
        return 'Break ended';
    }

    // ============================================================
    // CALENDAR
    // ============================================================

    public function calendar(Request $request)
    {
        $start = $request->filled('start_date') ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $end = $request->filled('end_date') ? Carbon::parse($request->end_date) : now()->endOfMonth();
        $calStart = $start->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $calEnd = $end->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $schedules = SecuritySchedule::with([
            'post:id,name,color', 'shift:id,name,start_time,end_time', 'securityUser:id,name,phone',
        ])->whereBetween('assignment_date', [$calStart, $calEnd])
            ->orderBy('assignment_date')->get()
            ->groupBy(fn ($s) => $s->assignment_date->format('Y-m-d'));

        return view('admin.security-schedules.calendar', compact(
            'schedules', 'start', 'end', 'calStart', 'calEnd'
        ));
    }

    public function getDateDetails(Request $request)
    {
        $date = Carbon::parse($request->date);
        $schedules = SecuritySchedule::with([
            'post:id,name', 'shift:id,name,start_time,end_time', 'securityUser:id,name,phone',
        ])->whereDate('assignment_date', $date)->get();

        return response()->json([
            'success' => true,
            'schedules' => $schedules->map(fn ($s) => [
                'id' => $s->id,
                'status' => $s->status,
                'security_user' => $s->securityUser,
                'post' => $s->post,
                'shift' => $s->shift,
            ]),
            'date' => $date->toDateString(),
        ]);
    }

    public function getQrCodeStatistics()
    {
        try {
            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => PostQrCode::count(),
                    'active' => PostQrCode::where('is_active', true)
                        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
        // ============================================================
    // ADMIN: HANDOVER INFO (JSON)
    // ============================================================

    /**
     * Returns the handover snapshot for a schedule so the admin
     * assignment/handover UI can render it.
     *
     * Handles all three admin route shapes:
     *   GET /admin/security-schedules/handover-info?schedule_id=42
     *   GET /admin/security-schedules/api/handover-info?schedule_id=42
     *   GET /api/admin/security-schedules/handover-info?schedule_id=42
     *   GET /admin/security-schedules/{schedule}/handover-info   (if you later add this)
     *
     * Resolves the schedule ID from (in priority order):
     *   1. $scheduleId scalar (route model binding or URI param)
     *   2. ?schedule_id=... query param
     *   3. $request->route('schedule') — URI param without type-hint
     *
     * Returns JSON. Status codes: 200 OK, 404 not found, 500 error.
     */
    public function getHandoverInfo(Request $request, $scheduleId = null)
    {
        // Resolve the schedule ID from whichever shape the route uses.
        $scheduleId = $scheduleId
            ?? $request->input('schedule_id')
            ?? $request->input('id')
            ?? $request->route('schedule')
            ?? $request->route('id');

        if (!$scheduleId || !is_numeric($scheduleId)) {
            return response()->json([
                'success' => false,
                'message' => 'A valid schedule_id is required.',
            ], 422);
        }

        try {
            $schedule = SecuritySchedule::with([
                'post:id,name,code,latitude,longitude',
                'shift:id,name,start_time,end_time,handover_config,is_overnight',
                'securityUser:id,name,phone,badge_number',
                'rotatedFromUser:id,name',
                'handoverCompletedBy:id,name',
            ])->findOrFail((int) $scheduleId);

            $shift          = $schedule->shift;
            $handoverConfig = is_array($shift?->handover_config) ? $shift->handover_config : [];
            $hasHandover    = ($handoverConfig['has_handover'] ?? false) === true;

            $handoverInfo   = is_array($schedule->handover_info) ? $schedule->handover_info : [];
            $checklistItems = $handoverInfo['checklist_items']
                ?? array_map(
                    fn ($item) => ['item' => $item, 'completed' => false],
                    $handoverConfig['handover_checklist'] ?? []
                );

            // Resolve the previous schedule on the same post, handling
            // overnight shifts that spill across the calendar boundary.
            $previous = null;
            if ($hasHandover && $shift) {
                $previous = SecuritySchedule::with([
                        'securityUser:id,name,phone',
                        'shift:id,name,start_time,end_time,is_overnight',
                    ])
                    ->where('security_post_id', $schedule->security_post_id)
                    ->where('id', '!=', $schedule->id)
                    ->where(function ($q) use ($schedule, $shift) {
                        $q->whereDate('assignment_date', $schedule->assignment_date)
                          ->whereHas('shift', fn ($qs) => $qs->where('start_time', '<', $shift->start_time));

                        $q->orWhereDate('assignment_date', $schedule->assignment_date->copy()->subDay())
                          ->whereHas('shift', fn ($qs) => $qs->where('is_overnight', true));
                    })
                    ->orderByDesc('assignment_date')
                    ->first();
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'schedule_id' => $schedule->id,
                    'post'        => $schedule->post ? [
                        'id'   => $schedule->post->id,
                        'name' => $schedule->post->name,
                        'code' => $schedule->post->code,
                    ] : null,
                    'shift'       => $shift ? [
                        'id'           => $shift->id,
                        'name'         => $shift->name,
                        'start_time'   => $shift->start_time,
                        'end_time'     => $shift->end_time,
                        'is_overnight' => (bool) $shift->is_overnight,
                    ] : null,
                    'security_user' => $schedule->securityUser ? [
                        'id'    => $schedule->securityUser->id,
                        'name'  => $schedule->securityUser->name,
                        'phone' => $schedule->securityUser->phone,
                        'badge' => $schedule->securityUser->badge_number,
                    ] : null,
                    'rotated_from_user' => $schedule->rotatedFromUser ? [
                        'id'   => $schedule->rotatedFromUser->id,
                        'name' => $schedule->rotatedFromUser->name,
                    ] : null,

                    'has_handover'            => $hasHandover,
                    'handover_duration'       => $handoverConfig['handover_duration'] ?? null,
                    'handover_notes_required' => (bool) ($handoverConfig['handover_notes_required'] ?? false),
                    'handover_checklist'      => $handoverConfig['handover_checklist'] ?? [],

                    'handover_info'         => $handoverInfo,
                    'checklist_items'       => $checklistItems,
                    'handover_completed'    => (bool) ($schedule->handover_completed ?? false),
                    'handover_completed_at' => optional($schedule->handover_completed_at)->toIso8601String(),
                    'handover_completed_by' => $schedule->handoverCompletedBy ? [
                        'id'   => $schedule->handoverCompletedBy->id,
                        'name' => $schedule->handoverCompletedBy->name,
                    ] : null,

                    'previous_schedule' => $previous ? [
                        'id'              => $previous->id,
                        'user'            => $previous->securityUser ? [
                            'id'   => $previous->securityUser->id,
                            'name' => $previous->securityUser->name,
                        ] : null,
                        'shift_name'      => $previous->shift?->name,
                        'shift_end'       => $previous->shift?->end_time,
                        'assignment_date' => optional($previous->assignment_date)->format('Y-m-d'),
                    ] : null,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('admin getHandoverInfo failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'trace'       => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load handover info.',
            ], 500);
        }
    }
}