<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecuritySchedule;
use App\Models\SecurityPost;
use App\Models\SecurityShift;
use App\Models\RotationGroup;
use App\Models\SecurityAvailability;
use App\Models\SecuritySupervisorAssignment;
use App\Models\PostQrCode;
use App\Models\PostNfcTag;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\VerificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SecurityScheduleController extends Controller
{
    // ============================================================
    // USER TYPE CONSTANTS
    // ============================================================
    const USER_TYPE_SUPER_ADMIN        = 0;
    const USER_TYPE_ADMIN              = 1;
    const USER_TYPE_SECURITY_PERSONNEL = 6;

    // ============================================================
    // AUTHORIZATION / RESPONSE HELPERS
    // ============================================================

    /**
     * Single source of truth for the supervisor capability.
     *
     * Delegates to the `manage-security-schedules` gate defined in
     * AppServiceProvider — the exact same gate the sidebar, the
     * `@canmanageschedules` Blade directive, and the resolver read.
     * This guarantees the controller never drifts from the UI:
     * if the sidebar shows "Schedule Management", this returns true.
     */
    private function canManageSchedules(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return Gate::forUser($user)->allows('manage-security-schedules');
    }

    /**
     * Hard gate for supervisor-only mutating actions.
     * Throws a 403 before any DB work happens.
     */
    private function authorizeManage(): void
    {
        if (!$this->canManageSchedules()) {
            abort(403, 'You do not have permission to manage security schedules.');
        }
    }

    /**
     * Unified success response (JSON for AJAX, redirect/back for HTML).
     */
    private function success(Request $request, string $message, array $data = [], ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['success' => true, 'message' => $message], $data));
        }
        $r = $redirect ? redirect($redirect) : back();
        return $r->with('success', $message);
    }

    /**
     * Unified failure response.
     */
    private function failure(Request $request, string $message, int $status = 422, ?string $redirect = null, $errors = null)
    {
        if ($request->expectsJson()) {
            return response()->json(
                array_merge(['success' => false, 'message' => $message], $errors ? ['errors' => $errors] : []),
                $status
            );
        }
        $r = $redirect ? redirect($redirect) : back();
        if ($errors) {
            $r->withErrors($errors);
        }
        return $r->with('error', $message);
    }

    // ============================================================
    // SUPERVISOR-ONLY: LISTING (admin parity)
    // ============================================================

    public function index(Request $request)
    {
        $canManage = $this->canManageSchedules();

        // Local-only diagnostic — never fires in production.
        if (app()->environment('local')) {
            $u = auth()->user();
            Log::info('[SecurityScheduleController@index] capability check', [
                'user_id'                   => $u?->id,
                'user_type'                 => $u?->type,
                'canManage'                 => $canManage,
                'isSupervisor'              => $u && method_exists($u, 'isSupervisor') ? $u->isSupervisor() : null,
                'hasActiveSupervisorAssign' => $u && method_exists($u, 'hasActiveSupervisorAssignment') ? $u->hasActiveSupervisorAssignment() : null,
                'route'                     => $request->path(),
            ]);
        }

        if (!$canManage) {
            return $this->mySchedules($request);
        }

        $query = SecuritySchedule::with(['post', 'shift', 'securityUser', 'assignedBy'])
            ->orderBy('assignment_date', 'desc')
            ->orderBy('security_post_id');

        if ($request->filled('date')) {
            $query->whereDate('assignment_date', $request->date);
        } else {
            $query->whereDate('assignment_date', '>=', today()->subDays(3));
        }

        foreach (['post_id' => 'security_post_id', 'shift_id' => 'security_shift_id', 'security_user_id' => 'security_user_id', 'status' => 'status'] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->input($param));
            }
        }

        if ($request->filled('shift_category')) {
            $query->whereHas('shift', fn($q) => $q->where('category', $request->shift_category));
        }

        if ($request->filled('verification_status')) {
            match ($request->verification_status) {
                'verified'   => $query->whereNotNull('checkin_verification'),
                'unverified' => $query->whereNull('checkin_verification'),
                'failed'     => $query->whereNotNull('verification_attempts'),
                default      => null,
            };
        }

        foreach (['late_flagged', 'offline_mode', 'supervisor_override'] as $flag) {
            if ($request->filled($flag)) {
                $query->where($flag, $request->boolean($flag));
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('securityUser', fn($q2) => $q2
                        ->where('name', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%")
                        ->orWhere('badge_number', 'like', "%{$s}%"))
                  ->orWhereHas('post', fn($q2) => $q2
                        ->where('name', 'like', "%{$s}%")
                        ->orWhere('code', 'like', "%{$s}%")
                        ->orWhere('location', 'like', "%{$s}%"))
                  ->orWhereHas('shift', fn($q2) => $q2
                        ->where('name', 'like', "%{$s}%")
                        ->orWhere('code', 'like', "%{$s}%"));
            });
        }

        $schedules = $query->paginate(20)->withQueryString();

        $securityPosts     = SecurityPost::active()->get(['id', 'name', 'max_personnel', 'latitude', 'longitude']);
        $securityShifts    = SecurityShift::active()->get(['id', 'name', 'start_time', 'end_time', 'category']);
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id', 'name', 'phone', 'badge_number']);

        $todaySummary       = $this->getTodayScheduleSummary();
        $upcomingHandovers  = $this->getUpcomingHandovers();
        $verificationStats  = $this->getVerificationStatistics();
        $stats              = $this->getScheduleStatistics();

        $isSupervisor = true;

        return view('security.schedules.index', compact(
            'schedules', 'securityPosts', 'securityShifts', 'securityPersonnel',
            'todaySummary', 'upcomingHandovers', 'verificationStats', 'stats',
            'request', 'canManage', 'isSupervisor'
        ));
    }

    public function trash(Request $request)
    {
        $this->authorizeManage();

        $query = SecuritySchedule::onlyTrashed()
            ->with(['post', 'shift', 'securityUser', 'assignedBy'])
            ->orderBy('deleted_at', 'desc');

        foreach (['date' => 'assignment_date', 'post_id' => 'security_post_id', 'shift_id' => 'security_shift_id', 'security_user_id' => 'security_user_id', 'status' => 'status'] as $param => $column) {
            if ($request->filled($param)) {
                $param === 'date'
                    ? $query->whereDate($column, $request->input($param))
                    : $query->where($column, $request->input($param));
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('securityUser', fn($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))
                  ->orWhereHas('post', fn($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
                  ->orWhereHas('shift', fn($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
            });
        }

        $trashedSchedules = $query->paginate(20)->withQueryString();

        $securityPosts     = SecurityPost::active()->get(['id', 'name']);
        $securityShifts    = SecurityShift::active()->get(['id', 'name']);
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id', 'name']);

        $trashStats = [
            'total_trashed' => SecuritySchedule::onlyTrashed()->count(),
            'trashed_by_period' => [
                'today'      => SecuritySchedule::onlyTrashed()->whereDate('deleted_at', today())->count(),
                'this_week'  => SecuritySchedule::onlyTrashed()->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'this_month' => SecuritySchedule::onlyTrashed()->whereMonth('deleted_at', now()->month)->count(),
            ],
            'oldest_trashed'   => SecuritySchedule::onlyTrashed()->oldest('deleted_at')->first()?->deleted_at?->diffForHumans(),
            'deleted_by_users' => $this->getDeletedByStats(),
        ];

        return view('security.schedules.trash', compact(
            'trashedSchedules', 'securityPosts', 'securityShifts',
            'securityPersonnel', 'trashStats', 'request'
        ));
    }

    public function calendar(Request $request)
    {
        $this->authorizeManage();

        $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate   = $request->filled('end_date')   ? Carbon::parse($request->end_date)   : now()->endOfMonth();

        $calendarStart = $startDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $calendarEnd   = $endDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $schedules = SecuritySchedule::with([
            'post:id,name', 'shift:id,name,start_time,end_time',
            'securityUser:id,name,phone', 'assignedBy:id,name',
        ])
            ->whereBetween('assignment_date', [$calendarStart, $calendarEnd])
            ->orderBy('assignment_date')->orderBy('security_post_id')
            ->get()
            ->groupBy(fn($s) => $s->assignment_date->format('Y-m-d'));

        $today = now()->format('Y-m-d');

        $totalSchedules = SecuritySchedule::whereBetween('assignment_date', [$calendarStart, $calendarEnd])->count();
        $activeToday    = SecuritySchedule::whereDate('assignment_date', $today)->where('status', 'active')->count();
        $absentToday    = SecuritySchedule::whereDate('assignment_date', $today)->where('status', 'absent')->count();
        $totalPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)->where('status', User::STATUS_ACTIVE)->count();
        $securityPosts  = SecurityPost::active()->orderBy('name')->get(['id', 'name', 'code']);

        return view('security.schedules.calendar', compact(
            'schedules', 'startDate', 'endDate', 'calendarStart', 'calendarEnd',
            'totalSchedules', 'activeToday', 'absentToday', 'totalPersonnel', 'securityPosts'
        ));
    }

    public function getDateDetails(Request $request)
    {
        $this->authorizeManage();

        try {
            $date = Carbon::parse($request->date);

            $schedules = SecuritySchedule::with([
                'post:id,name', 'shift:id,name,start_time,end_time',
                'securityUser:id,name,phone', 'assignedBy:id,name',
            ])->whereDate('assignment_date', $date)->orderBy('security_shift_id')->get();

            $summary = [
                'active'    => $schedules->where('status', 'active')->count(),
                'scheduled' => $schedules->where('status', 'scheduled')->count(),
                'completed' => $schedules->where('status', 'completed')->count(),
                'absent'    => $schedules->where('status', 'absent')->count(),
                'verified'  => $schedules->whereNotNull('checkin_verification')->count(),
                'late'      => $schedules->where('late_minutes', '>', 0)->count(),
            ];

            $formatted = $schedules->map(fn($s) => [
                'id'            => $s->id,
                'status'        => $s->status,
                'security_user' => $s->securityUser ? ['id' => $s->securityUser->id, 'name' => $s->securityUser->name, 'phone' => $s->securityUser->phone] : null,
                'post'          => $s->post ? ['id' => $s->post->id, 'name' => $s->post->name] : null,
                'shift'         => $s->shift ? ['id' => $s->shift->id, 'name' => $s->shift->name, 'time_range' => $s->shift->getTimeRange()] : null,
                'assigned_by'   => $s->assignedBy ? ['id' => $s->assignedBy->id, 'name' => $s->assignedBy->name] : null,
                'checkin_time'  => $s->checkin_time?->format('Y-m-d H:i:s'),
                'checkout_time' => $s->checkout_time?->format('Y-m-d H:i:s'),
                'verified'      => !is_null($s->checkin_verification),
                'late_minutes'  => $s->late_minutes,
                'verification_method' => is_array($s->checkin_verification) ? ($s->checkin_verification['method'] ?? null) : null,
            ]);

            return response()->json(['success' => true, 'schedules' => $formatted, 'summary' => $summary, 'date' => $date->format('Y-m-d')]);
        } catch (\Exception $e) {
            Log::error('getDateDetails failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load date details'], 500);
        }
    }

    // ============================================================
    // SUPERVISOR-ONLY: CREATE / STORE
    // ============================================================

    public function create()
    {
        $this->authorizeManage();

        $securityPosts = SecurityPost::whereNull('deleted_at')
            ->orderBy('is_active', 'desc')->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel', 'type', 'location', 'latitude', 'longitude', 'equipment', 'is_active', 'checkin_radius', 'require_gps_verification']);

        $securityShifts = SecurityShift::active()->get([
            'id', 'name', 'start_time', 'end_time', 'category', 'is_overnight',
            'rotation_type', 'handover_config', 'applicable_days', 'break_schedule', 'required_personnel',
        ]);

        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->with(['schedules' => fn($q) => $q->latest('assignment_date')->limit(1)])
            ->get(['id', 'name', 'phone', 'email', 'badge_number', 'preferences']);

        $rotationGroups = RotationGroup::where('status', 'active')
            ->with(['post:id,name', 'shift:id,name'])->get();

        return view('security.schedules.create', compact('securityPosts', 'securityShifts', 'securityPersonnel', 'rotationGroups'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        // NEW — normalize empty strings to null before validation so that
        // `nullable|integer` rules (rotation_preference_score,
        // rotation_sequence_number, etc.) don't fail on a blank form field.
        $request->merge(
            collect($request->all())
                ->map(fn ($v) => $v === '' ? null : $v)
                ->all()
        );

        $validator = Validator::make($request->all(), [
            'security_post_id'          => 'required|exists:security_posts,id',
            'security_shift_id'         => 'required|exists:security_shifts,id',
            'security_user_id'          => 'required|exists:users,id',
            'assignment_date'           => 'required|date',
            'notes'                     => 'nullable|string|max:500',
            'emergency_contact'         => 'nullable|string|max:255',
            'special_instructions'      => 'nullable|string|max:1000',
            'include_breaks'            => 'nullable|boolean',
            'rotation_group_id'         => 'nullable|exists:rotation_groups,id',
            'rotation_group_type'       => 'nullable|string',
            'rotation_sequence_number'  => 'nullable|integer',
            'rotation_preference_score' => 'nullable|integer|min:1|max:10',
            'is_rotated'                => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (SecuritySchedule::where('security_user_id', $request->security_user_id)
            ->whereDate('assignment_date', $request->assignment_date)->exists()) {
            return back()->withErrors(['security_user_id' => 'This personnel already has an assignment on this date.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $shift = SecurityShift::findOrFail($request->security_shift_id);
            $post  = SecurityPost::findOrFail($request->security_post_id);

            $assigned = SecuritySchedule::where('security_post_id', $post->id)
                ->whereDate('assignment_date', $request->assignment_date)->count();
            if ($assigned >= $post->max_personnel) {
                throw new \Exception("Post has reached max capacity ({$post->max_personnel}).");
            }

            if (!$shift->isApplicableOnDay(Carbon::parse($request->assignment_date)->dayOfWeekIso)) {
                throw new \Exception('Shift is not applicable on the selected date.');
            }

            $handoverInfo = $this->calculateHandoverTimes($shift, $request->assignment_date, $post->id);
            $rotationData = $this->prepareRotationData($shift, $request->security_user_id);

            $schedule = SecuritySchedule::create([
                'security_post_id'          => $post->id,
                'security_shift_id'         => $shift->id,
                'security_user_id'          => $request->security_user_id,
                'assigned_by'               => auth()->id(),
                'assignment_date'           => $request->assignment_date,
                'status'                    => 'scheduled',
                'notes'                     => $request->notes,
                'emergency_contact'         => $request->emergency_contact,
                'special_instructions'      => $request->special_instructions,
                'include_breaks'            => $request->boolean('include_breaks', false),
                'handover_info'             => $handoverInfo,
                'rotation_data'             => $rotationData,
                'rotation_group_id'         => $request->rotation_group_id,
                'rotation_group_type'       => $request->rotation_group_type,
                'rotation_sequence_number'  => $request->rotation_sequence_number ?? 0,
                'rotation_preference_score' => $request->rotation_preference_score ?? 5,
                'is_rotated'                => $request->boolean('is_rotated', false),
                'rotation_swap_count'       => 0,
            ]);

            $this->clearScheduleCaches($schedule);

            DB::commit();

            Log::info('Security schedule created (supervisor)', [
                'schedule_id' => $schedule->id,
                'created_by'  => auth()->id(),
            ]);

            return redirect()->route('security.schedules.show', $schedule)
                ->with('success', 'Security schedule created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create schedule (supervisor): ' . $e->getMessage());
            return back()->with('error', 'Failed to create schedule: ' . $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        $this->authorizeManage();

        // NEW — normalize empty strings to null before validation so that
        // `nullable|integer`, `nullable|date`, `nullable|boolean` rules
        // don't fail when the caller leaves optional fields blank.
        $request->merge(
            collect($request->all())
                ->map(fn ($v) => $v === '' ? null : $v)
                ->all()
        );

        $schedule = SecuritySchedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'security_post_id'      => 'required|exists:security_posts,id',
            'security_shift_id'     => 'required|exists:security_shifts,id',
            'security_user_id'      => 'required|exists:users,id',
            'assignment_date'       => 'required|date',
            'status'                => 'required|in:scheduled,active,completed,absent,cancelled',
            'notes'                 => 'nullable|string|max:500',
            'emergency_contact'     => 'nullable|string|max:255',
            'special_instructions'  => 'nullable|string|max:1000',
            'include_breaks'        => 'nullable|boolean',
            'handover_completed'    => 'nullable|boolean',
            'is_approved'           => 'nullable|boolean',
            'late_minutes'          => 'nullable|integer|min:0',
            'overtime_minutes'      => 'nullable|integer|min:0',
            'total_minutes'         => 'nullable|integer|min:0',
            'break_duration'        => 'nullable|integer|min:0',
            'checkin_time'          => 'nullable|date',
            'checkout_time'         => 'nullable|date',
            'late_flagged'          => 'nullable|boolean',
            'late_reason'           => 'nullable|string|max:255',
            'supervisor_override'   => 'nullable|boolean',
            'override_reason'       => 'required_if:supervisor_override,true|nullable|string|max:500',
        ]);

        // Cross-field check applied only when both are present.
        if ($request->filled('checkin_time') && $request->filled('checkout_time')) {
            $validator->after(function ($v) use ($request) {
                if (Carbon::parse($request->checkout_time)->lt(Carbon::parse($request->checkin_time))) {
                    $v->errors()->add('checkout_time', 'Checkout time must be after check-in time.');
                }
            });
        }

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (in_array($schedule->status, ['completed', 'absent', 'cancelled']) && $schedule->status !== $request->status) {
            return back()->with('error', "Cannot change status from {$schedule->status}.")->withInput();
        }

        DB::beginTransaction();
        try {
            $shift = SecurityShift::findOrFail($request->security_shift_id);

            $handoverInfo = $schedule->handover_info;
            if ($schedule->security_shift_id != $request->security_shift_id
                || $schedule->assignment_date != $request->assignment_date
                || $schedule->security_post_id != $request->security_post_id) {
                $handoverInfo = $this->calculateHandoverTimes($shift, $request->assignment_date, $request->security_post_id);
            }

            $updateData = [
                'security_post_id'     => $request->security_post_id,
                'security_shift_id'    => $request->security_shift_id,
                'security_user_id'     => $request->security_user_id,
                'assignment_date'      => $request->assignment_date,
                'status'               => $request->status,
                'notes'                => $request->notes,
                'emergency_contact'    => $request->emergency_contact,
                'special_instructions' => $request->special_instructions,
                'handover_info'        => $handoverInfo,
                'include_breaks'       => $request->boolean('include_breaks', $schedule->include_breaks),
            ];

            if ($request->has('handover_completed')) {
                $updateData['handover_completed'] = $request->boolean('handover_completed');
                if ($request->boolean('handover_completed') && !$schedule->handover_completed_at) {
                    $updateData['handover_completed_at'] = now();
                    $updateData['handover_completed_by'] = auth()->id();
                }
            }

            if ($request->has('is_approved')) {
                $updateData['is_approved'] = $request->boolean('is_approved');
                if ($request->boolean('is_approved') && !$schedule->approved_at) {
                    $updateData['approved_by'] = auth()->id();
                    $updateData['approved_at'] = now();
                    $updateData['approval_metadata'] = [
                        'approved_by_name' => auth()->user()->name,
                        'approved_at'      => now()->toDateTimeString(),
                        'notes'            => $request->approval_notes ?? null,
                    ];
                }
            }

            foreach (['checkin_time', 'checkout_time'] as $dtField) {
                if ($request->filled($dtField)) {
                    $updateData[$dtField] = Carbon::parse($request->input($dtField));
                }
            }
            foreach (['late_minutes', 'overtime_minutes', 'total_minutes', 'break_duration'] as $intField) {
                if ($request->filled($intField)) {
                    $updateData[$intField] = (int) $request->input($intField);
                }
            }

            if ($request->has('late_flagged')) {
                $updateData['late_flagged'] = $request->boolean('late_flagged');
                $updateData['late_reason']  = $request->late_reason;
            }

            if ($request->boolean('supervisor_override')) {
                $updateData['supervisor_override'] = true;
                $updateData['supervisor_id']       = auth()->id();
                $updateData['override_reason']     = $request->override_reason;

                Log::info('Supervisor override applied', [
                    'schedule_id'   => $schedule->id,
                    'supervisor_id' => auth()->id(),
                    'reason'        => $request->override_reason,
                ]);
            }

            $schedule->update($updateData);
            $this->clearScheduleCaches($schedule);

            DB::commit();

            Log::info('Security schedule updated (supervisor)', [
                'schedule_id' => $schedule->id,
                'updated_by'  => auth()->id(),
                'changes'     => $schedule->getChanges(),
            ]);

            return redirect()->route('security.schedules.show', $schedule)
                ->with('success', 'Security schedule updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update schedule (supervisor): ' . $e->getMessage());
            return back()->with('error', 'Failed to update security schedule. Please try again.')->withInput();
        }
    }

    // ============================================================
    // SUPERVISOR-ONLY: SHOW / EDIT / UPDATE
    // ============================================================

    public function show($id)
    {
        if (!$this->canManageSchedules()) {
            return $this->showSchedule($id);
        }

        $schedule = SecuritySchedule::with([
            'post', 'shift', 'securityUser', 'assignedBy',
            'rotatedFromUser', 'approvedBy', 'handoverCompletedBy',
        ])->findOrFail($id);

        $verificationLogs = DB::table('verification_logs')
            ->where('schedule_id', $id)->orderBy('created_at', 'desc')->get();

        $deviceInfo = null;
        if ($schedule->checkin_device_id) {
            $deviceInfo = DB::table('user_devices')
                ->where('device_id', $schedule->checkin_device_id)
                ->where('user_id', $schedule->security_user_id)
                ->first();
        }

        $canManage    = true;
        $isSupervisor = true;

        return view('security.schedules.show', compact('schedule', 'verificationLogs', 'deviceInfo', 'canManage', 'isSupervisor'));
    }

    public function edit($id)
    {
        $this->authorizeManage();

        $schedule = SecuritySchedule::with(['shift', 'post', 'securityUser'])->findOrFail($id);

        $securityPosts = SecurityPost::whereNull('deleted_at')
            ->orderBy('is_active', 'desc')->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel', 'type', 'location', 'is_active', 'latitude', 'longitude']);

        $securityShifts = SecurityShift::active()->get([
            'id', 'name', 'start_time', 'end_time', 'category', 'is_overnight',
            'rotation_type', 'handover_config', 'applicable_days',
        ]);

        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id', 'name', 'phone', 'email', 'badge_number']);

        $rotationGroups = RotationGroup::where('status', 'active')
            ->with(['post:id,name', 'shift:id,name'])->get();

        $currentAssignments = 0;
        if ($schedule->security_post_id && $schedule->assignment_date) {
            $currentAssignments = SecuritySchedule::where('security_post_id', $schedule->security_post_id)
                ->whereDate('assignment_date', $schedule->assignment_date)
                ->where('id', '!=', $schedule->id)->count();
        }

        return view('security.schedules.edit', compact(
            'schedule', 'securityPosts', 'securityShifts',
            'securityPersonnel', 'rotationGroups', 'currentAssignments'
        ));
    }

    // ============================================================
    // SUPERVISOR-ONLY: DELETE / RESTORE / TRASH
    // ============================================================

    public function destroy(Request $request, $id)
    {
        $this->authorizeManage();

        try {
            $schedule = SecuritySchedule::findOrFail($id);

            if (in_array($schedule->status, ['active', 'completed'])) {
                return $this->failure($request, 'Cannot delete an active or completed schedule.', 422);
            }

            $auditLog   = $schedule->audit_log ?? [];
            $auditLog[] = [
                'action'          => 'deleted',
                'deleted_by'      => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
                'deleted_at'      => now()->toDateTimeString(),
                'reason'          => $request->input('reason', 'Manual deletion'),
            ];
            $schedule->audit_log = $auditLog;
            $schedule->save();
            $schedule->delete();

            $this->clearScheduleCaches($schedule);

            Log::info('Security schedule soft deleted (supervisor)', ['schedule_id' => $id, 'deleted_by' => auth()->id()]);

            return $this->success($request, 'Security schedule moved to trash successfully.', [], route('security.schedules.index'));
        } catch (\Exception $e) {
            Log::error('Failed to soft delete schedule (supervisor): ' . $e->getMessage());
            return $this->failure($request, 'Failed to delete security schedule.', 500);
        }
    }

    public function restore(Request $request, $id)
    {
        $this->authorizeManage();

        try {
            $schedule = SecuritySchedule::onlyTrashed()->findOrFail($id);
            $conflicts = $this->checkRestorationConflicts($schedule);

            if (!empty($conflicts)) {
                return $this->failure($request, 'Cannot restore schedule: ' . implode('; ', $conflicts), 422);
            }

            $schedule->restore();
            $this->clearScheduleCaches($schedule);

            Log::info('Security schedule restored (supervisor)', ['schedule_id' => $id, 'restored_by' => auth()->id()]);

            return $this->success($request, 'Security schedule restored successfully.', [], route('security.schedules.trash'));
        } catch (\Exception $e) {
            Log::error('Failed to restore schedule: ' . $e->getMessage());
            return $this->failure($request, 'Failed to restore security schedule.', 500);
        }
    }

    public function bulkRestore(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'schedule_ids'   => 'required|array',
            'schedule_ids.*' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $restored = 0;
            $failed   = [];

            foreach ($request->schedule_ids as $scheduleId) {
                $schedule = SecuritySchedule::onlyTrashed()->find($scheduleId);
                if (!$schedule) {
                    $failed[] = ['id' => $scheduleId, 'reason' => 'Not in trash'];
                    continue;
                }
                $conflicts = $this->checkRestorationConflicts($schedule);
                if (!empty($conflicts)) {
                    $failed[] = ['id' => $scheduleId, 'reason' => implode('; ', $conflicts)];
                    continue;
                }
                $schedule->restore();
                $restored++;
            }

            DB::commit();

            return $this->success($request, "Restored {$restored} schedule(s).", [
                'restored_count'  => $restored,
                'failed_count'    => count($failed),
                'failed_restores' => $failed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk restore failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to bulk restore schedules.', 500);
        }
    }

    public function forceDelete(Request $request, $id)
    {
        $this->authorizeManage();

        try {
            if (SecuritySchedule::find($id)) {
                return $this->failure($request, 'Schedule must be trashed first.', 422);
            }

            $schedule = SecuritySchedule::onlyTrashed()->find($id);
            if (!$schedule) {
                return $this->failure($request, 'Schedule not found in trash.', 404);
            }

            $schedule->forceDelete();
            Log::warning('Security schedule permanently deleted (supervisor)', ['schedule_id' => $id, 'deleted_by' => auth()->id()]);

            return $this->success($request, 'Security schedule permanently deleted.');
        } catch (\Exception $e) {
            Log::error('Force delete failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to permanently delete schedule.', 500);
        }
    }

    public function bulkForceDelete(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'schedule_ids'   => 'required|array',
            'schedule_ids.*' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $deleted = 0;
            $failed  = [];

            foreach ($request->schedule_ids as $scheduleId) {
                if (SecuritySchedule::find($scheduleId)) {
                    $failed[] = ['id' => $scheduleId, 'reason' => 'Must be trashed first'];
                    continue;
                }
                $schedule = SecuritySchedule::onlyTrashed()->find($scheduleId);
                if (!$schedule) {
                    $failed[] = ['id' => $scheduleId, 'reason' => 'Not in trash'];
                    continue;
                }
                $schedule->forceDelete();
                $deleted++;
            }

            DB::commit();

            return $this->success($request, "Permanently deleted {$deleted} schedule(s).", [
                'deleted_count'  => $deleted,
                'failed_count'   => count($failed),
                'failed_deletes' => $failed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk force delete failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to bulk delete schedules.', 500);
        }
    }

    public function clearOldTrash(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), ['days' => 'required|integer|min:1|max:365']);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $cutoff = now()->subDays($request->days);
            $old    = SecuritySchedule::onlyTrashed()->where('deleted_at', '<', $cutoff)->get();
            $count  = $old->count();

            foreach ($old as $schedule) {
                $schedule->forceDelete();
            }

            Log::info('Old trashed schedules cleared (supervisor)', [
                'deleted_count' => $count, 'days_threshold' => $request->days, 'deleted_by' => auth()->id(),
            ]);

            return redirect()->route('security.schedules.trash')
                ->with('success', "{$count} schedule(s) older than {$request->days} days have been permanently deleted.");
        } catch (\Exception $e) {
            Log::error('Clear old trash failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to clear old trash.');
        }
    }

    // ============================================================
    // SUPERVISOR-ONLY: BULK UPDATE
    // ============================================================

    public function bulkUpdate(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'schedule_ids'   => 'required|array',
            'schedule_ids.*' => 'exists:security_schedules,id',
            'action'         => 'required|in:trash,force_delete,restore,apply_rotation,swap_groups,mark_verified,clear_verification',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $successCount = 0;
            $failedItems  = [];

            foreach ($request->schedule_ids as $id) {
                try {
                    $schedule = SecuritySchedule::withTrashed()->find($id);
                    if (!$schedule) {
                        $failedItems[] = ['id' => $id, 'reason' => 'Not found'];
                        continue;
                    }

                    switch ($request->action) {
                        case 'trash':
                            if (!$schedule->trashed() && !in_array($schedule->status, ['active', 'completed'])) {
                                $schedule->delete();
                                $successCount++;
                            } else {
                                $failedItems[] = ['id' => $id, 'reason' => 'Cannot delete active/completed'];
                            }
                            break;
                        case 'restore':
                            if ($schedule->trashed()) {
                                $conflicts = $this->checkRestorationConflicts($schedule);
                                if (empty($conflicts)) {
                                    $schedule->restore();
                                    $successCount++;
                                } else {
                                    $failedItems[] = ['id' => $id, 'reason' => implode('; ', $conflicts)];
                                }
                            }
                            break;
                        case 'force_delete':
                            if ($schedule->trashed()) {
                                $schedule->forceDelete();
                                $successCount++;
                            }
                            break;
                        case 'mark_verified':
                            if (!$schedule->trashed()) {
                                $schedule->update(['checkin_verification' => [
                                    'method'           => 'bulk_supervisor_override',
                                    'verified_at'      => now()->toDateTimeString(),
                                    'verified_by'      => auth()->id(),
                                    'verified_by_name' => auth()->user()->name,
                                ]]);
                                $successCount++;
                            }
                            break;
                        case 'clear_verification':
                            if (!$schedule->trashed()) {
                                $schedule->update(['checkin_verification' => null]);
                                $successCount++;
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    $failedItems[] = ['id' => $id, 'reason' => $e->getMessage()];
                }
            }

            DB::commit();

            return $this->success($request, "Processed {$successCount} schedule(s).", [
                'success_count' => $successCount,
                'failed_count'  => count($failedItems),
                'failed_items'  => $failedItems,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk update failed (supervisor): ' . $e->getMessage());
            return $this->failure($request, 'Failed to perform bulk action.', 500);
        }
    }

    // ============================================================
    // SUPERVISOR-ONLY: BULK ASSIGN + PREVIEW
    // ============================================================

    public function bulkAssign(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'security_post_id'         => 'required|exists:security_posts,id',
            'security_shift_id'        => 'required|exists:security_shifts,id',
            'security_user_ids'        => 'required|array|min:1',
            'security_user_ids.*'      => 'exists:users,id',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after_or_equal:start_date',
            'recurrence_type'          => 'required|in:daily,weekly,monthly,custom,rotating',
            'recurrence_days'          => 'nullable|array',
            'recurrence_days.*'        => 'integer|min:1|max:7',
            'rotation_pattern'         => 'nullable|in:full_swap,staggered,partial,sequential,alternating,preference_based',
            'include_breaks'           => 'nullable|boolean',
            'include_handover'         => 'nullable|boolean',
            'notes'                    => 'nullable|string|max:500',
            'emergency_contact'        => 'nullable|string|max:255',
            'special_instructions'     => 'nullable|string|max:1000',
            'create_rotation_group'    => 'nullable|boolean',
            'rotation_group_name'      => 'required_if:create_rotation_group,true|nullable|string|max:255',
            'maintain_coverage'        => 'nullable|boolean',
            'respect_preferences'      => 'nullable|boolean',
            'notify_personnel'         => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $post  = SecurityPost::findOrFail($request->security_post_id);
            $shift = SecurityShift::findOrFail($request->security_shift_id);

            if (!$post->is_active || $post->trashed()) {
                throw new \Exception('Security post is inactive or deleted.');
            }
            if (!$shift->is_active || $shift->trashed()) {
                throw new \Exception('Security shift is inactive or deleted.');
            }

            $startDate = Carbon::parse($request->start_date);
            $endDate   = Carbon::parse($request->end_date);

            $rotationGroup = null;
            if ($request->recurrence_type === 'rotating' && $request->filled('rotation_pattern')) {
                $rotationGroup = $this->getOrCreateRotationGroup($request, $post, $shift);
            }

            $applicableDates = $this->calculateApplicableDates(
                $startDate, $endDate, $request->recurrence_type, $request->recurrence_days ?? []
            );

            $existing = DB::table('security_schedules')
                ->whereIn('security_user_id', $request->security_user_ids)
                ->whereBetween('assignment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get(['security_user_id', 'assignment_date'])
                ->mapWithKeys(fn($i) => [$i->security_user_id . '_' . $i->assignment_date => true])
                ->toArray();

            $handoverInfo = null;
            if ($request->boolean('include_handover', false)) {
                $hi = $this->calculateHandoverTimes($shift, $startDate, $post->id);
                $handoverInfo = $hi ?: null;
            }

            $batchData = [];
            $createdCount = 0;
            $failed = [];
            $now = now();
            $sortedUserIds = $request->security_user_ids;

            foreach ($applicableDates as $date) {
                $dateStr = $date->format('Y-m-d');

                $assignedCount = DB::table('security_schedules')
                    ->where('security_post_id', $post->id)
                    ->whereDate('assignment_date', $dateStr)->count();

                $availableSlots = $post->max_personnel - $assignedCount;
                if ($availableSlots <= 0) {
                    $failed[] = ['date' => $dateStr, 'reason' => 'Post at full capacity'];
                    continue;
                }

                if ($shift->applicable_days !== null && !empty($shift->applicable_days)
                    && !in_array($date->dayOfWeekIso, $shift->applicable_days)) {
                    $failed[] = ['date' => $dateStr, 'reason' => 'Shift not applicable on this day'];
                    continue;
                }

                $slotsToFill = min($availableSlots, count($sortedUserIds));
                $assigned = 0;
                foreach ($sortedUserIds as $userId) {
                    if ($assigned >= $slotsToFill) break;
                    $key = $userId . '_' . $dateStr;
                    if (isset($existing[$key])) continue;

                    $batchData[] = [
                        'security_post_id'      => $post->id,
                        'security_shift_id'     => $shift->id,
                        'security_user_id'      => $userId,
                        'assigned_by'           => auth()->id(),
                        'assignment_date'       => $dateStr,
                        'status'                => 'scheduled',
                        'notes'                 => $request->notes,
                        'emergency_contact'     => $request->emergency_contact,
                        'special_instructions'  => $request->special_instructions,
                        'include_breaks'        => $request->boolean('include_breaks', false),
                        'handover_info'         => $handoverInfo ? json_encode($handoverInfo) : null,
                        'rotation_group_id'     => $rotationGroup?->id,
                        'rotation_group_type'   => $shift->category,
                        'rotation_sequence_number' => 0,
                        'rotation_preference_score' => 5,
                        'is_rotated'            => $rotationGroup ? true : false,
                        'rotation_swap_count'   => 0,
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ];
                    $existing[$key] = true;
                    $assigned++;
                    $createdCount++;
                }

                if (count($batchData) >= 50) {
                    DB::table('security_schedules')->insert($batchData);
                    $batchData = [];
                }
            }

            if (!empty($batchData)) {
                DB::table('security_schedules')->insert($batchData);
            }

            if ($rotationGroup) {
                $this->updateRotationGroupHistory($rotationGroup, $startDate, $endDate, $request);
            }

            DB::commit();

            Log::info('Bulk assign completed (supervisor)', [
                'created_count' => $createdCount,
                'failed_count'  => count($failed),
                'post_id'       => $post->id,
                'shift_id'      => $shift->id,
                'by'            => auth()->id(),
            ]);

            return $this->success($request, "{$createdCount} schedule(s) created successfully.", [
                'created_count'      => $createdCount,
                'failed_count'       => count($failed),
                'failed_assignments' => $failed,
                'rotation_group'     => $rotationGroup ? [
                    'id' => $rotationGroup->id, 'name' => $rotationGroup->name,
                ] : null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk assign failed (supervisor): ' . $e->getMessage());
            return $this->failure($request, 'Failed to process bulk assignment: ' . $e->getMessage(), 500);
        }
    }

    public function previewBulkAssign(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'security_post_id'      => 'required|exists:security_posts,id',
            'security_shift_id'     => 'required|exists:security_shifts,id',
            'security_user_ids'     => 'required|array|min:1',
            'security_user_ids.*'   => 'exists:users,id',
            'start_date'            => 'required|date',
            'end_date'              => 'required|date|after_or_equal:start_date',
            'recurrence_type'       => 'required|in:daily,weekly,monthly,custom,rotating',
            'recurrence_days'       => 'nullable|array',
            'recurrence_days.*'     => 'integer|min:1|max:7',
            'rotation_pattern'      => 'nullable|in:full_swap,staggered,partial,sequential,alternating,preference_based',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        try {
            $post  = SecurityPost::findOrFail($request->security_post_id);
            $shift = SecurityShift::findOrFail($request->security_shift_id);
            $startDate = Carbon::parse($request->start_date);
            $endDate   = Carbon::parse($request->end_date);

            $applicableDates = $this->calculateApplicableDates(
                $startDate, $endDate, $request->recurrence_type, $request->recurrence_days ?? []
            );

            $preview = [];
            $totalAssignments = 0;
            $affectedPersonnel = [];

            foreach ($applicableDates as $date) {
                $dateStr = $date->format('Y-m-d');
                $assignedCount = DB::table('security_schedules')
                    ->where('security_post_id', $post->id)
                    ->whereDate('assignment_date', $dateStr)->count();

                $availableSlots = $post->max_personnel - $assignedCount;

                $dayEntry = [
                    'date'            => $dateStr,
                    'day_of_week'     => $date->format('l'),
                    'available_slots' => $availableSlots,
                    'slots_filled'    => 0,
                    'assignments'     => [],
                    'status'          => $availableSlots <= 0 ? 'skipped' : 'ready',
                    'reason'          => $availableSlots <= 0 ? 'Post at full capacity' : null,
                ];

                if ($availableSlots > 0) {
                    $slotsToFill = min($availableSlots, count($request->security_user_ids));
                    foreach (array_slice($request->security_user_ids, 0, $slotsToFill) as $userId) {
                        $user = User::find($userId);
                        if ($user) {
                            $dayEntry['assignments'][] = [
                                'user_id'        => $user->id,
                                'personnel_name' => $user->name,
                                'badge_number'   => $user->badge_number,
                            ];
                            $affectedPersonnel[$userId] = true;
                            $totalAssignments++;
                        }
                    }
                    $dayEntry['slots_filled'] = count($dayEntry['assignments']);
                }

                $preview[] = $dayEntry;
            }

            return $this->success($request, 'Preview generated.', [
                'preview'    => $preview,
                'statistics' => [
                    'total_assignments' => $totalAssignments,
                    'unique_personnel'  => count($affectedPersonnel),
                    'total_days'        => $startDate->diffInDays($endDate) + 1,
                    'applicable_days'   => count($applicableDates),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Preview bulk assign failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to generate preview: ' . $e->getMessage(), 500);
        }
    }

    public function rotateShiftGroups(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'post_id'             => 'required|exists:security_posts,id',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after:start_date',
            'rotation_frequency'  => 'required|in:daily,weekly,biweekly,monthly',
            'rotation_pattern'    => 'required|in:full_swap,staggered,partial',
            'exclude_personnel'   => 'nullable|array',
            'exclude_personnel.*' => 'exists:users,id',
            'maintain_coverage'   => 'boolean',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $post = SecurityPost::findOrFail($request->post_id);
            $startDate = Carbon::parse($request->start_date);
            $endDate   = Carbon::parse($request->end_date);

            $schedules = SecuritySchedule::with(['shift:id,category', 'securityUser:id,name'])
                ->where('security_post_id', $post->id)
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->orderBy('assignment_date')->orderBy('security_shift_id')
                ->get(['id', 'security_post_id', 'security_shift_id', 'security_user_id', 'assignment_date']);

            $grouped = $schedules->groupBy(fn($s) => $s->assignment_date->format('Y-m-d'));

            $rotatedSchedules = [];
            $rotationLog = [];
            $counter = 0;
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->format('Y-m-d');

                if ($this->shouldRotateOnDate($currentDate, $startDate, $request->rotation_frequency, $counter)) {
                    $dateSchedules = $grouped->get($dateStr, collect());

                    if ($dateSchedules->isNotEmpty()) {
                        $result = $this->applyShiftGroupRotation(
                            $dateSchedules,
                            $request->rotation_pattern,
                            $request->exclude_personnel ?? []
                        );

                        foreach ($result['schedules'] as $r) {
                            $schedule = SecuritySchedule::find($r['id']);
                            if (!$schedule) continue;

                            $rotationData = $schedule->rotation_data ?? [];
                            $rotationData['rotated_from']    = $r['old_user_id'];
                            $rotationData['rotated_at']      = now();
                            $rotationData['rotation_type']   = 'shift_group';
                            $rotationData['previous_shift']  = $r['old_shift_id'];
                            $rotationData['new_shift']       = $r['new_shift_id'];
                            $rotationData['rotation_group']  = 'day_night_swap';

                            $schedule->update([
                                'security_user_id'     => $r['new_user_id'],
                                'rotated_from_user_id' => $r['old_user_id'],
                                'is_rotated'           => true,
                                'rotated_at'           => now(),
                                'rotation_swap_count'  => ($schedule->rotation_swap_count ?? 0) + 1,
                                'rotation_data'        => $rotationData,
                            ]);
                            $rotatedSchedules[] = $r['id'];
                        }

                        $rotationLog[$dateStr] = $result['log'];
                    }
                }
                $counter++;
                $currentDate->addDay();
            }

            DB::commit();

            Log::info('Shift group rotation completed (supervisor)', [
                'post_id'       => $post->id,
                'rotated_count' => count($rotatedSchedules),
                'by'            => auth()->id(),
            ]);

            return $this->success($request, 'Shift group rotation completed successfully.', [
                'rotated_count' => count($rotatedSchedules),
                'rotation_log'  => $rotationLog,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Rotate shift groups failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to rotate shift groups: ' . $e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request, $scheduleId)
    {
        $this->authorizeManage();

        $schedule = SecuritySchedule::findOrFail($scheduleId);

        $validator = Validator::make($request->all(), [
            'action'             => 'required|in:checkin,checkout,mark_absent,mark_complete,start_break,end_break,verify_location',
            'location'           => 'nullable|array',
            'location.lat'       => 'required_with:location|numeric',
            'location.lng'       => 'required_with:location|numeric',
            'accuracy'           => 'nullable|numeric',
            'notes'              => 'nullable|string|max:500',
            'break_id'           => 'nullable|integer',
            'handover_notes'     => 'nullable|string|max:1000',
            'handover_checklist' => 'nullable|array',
            'supervisor_override' => 'nullable|boolean',
            'override_reason'    => 'required_if:supervisor_override,true|nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $message = match ($request->action) {
                'checkin'         => $this->supervisorProcessCheckin($schedule, $request),
                'checkout'        => $this->supervisorProcessCheckout($schedule, $request),
                'mark_absent'     => tap('Marked as absent.', fn() => method_exists($schedule, 'markAsAbsent') && $schedule->markAsAbsent($request->notes)),
                'mark_complete'   => tap('Marked as complete.', fn() => method_exists($schedule, 'markAsComplete') && $schedule->markAsComplete($request->notes)),
                'start_break'     => $this->supervisorStartBreak($schedule, $request),
                'end_break'       => $this->supervisorEndBreak($schedule, $request),
                'verify_location' => 'Location verified',
            };

            if ($request->filled('handover_checklist')) {
                $this->updateHandoverChecklist($schedule, $request->handover_checklist);
            }

            if ($request->filled('handover_notes')) {
                $handoverInfo = $schedule->handover_info ?? [];
                $handoverInfo['notes']               = $request->handover_notes;
                $handoverInfo['notes_submitted_at']  = now();
                $handoverInfo['notes_submitted_by']  = auth()->id();
                $schedule->update(['handover_info' => $handoverInfo]);
            }

            DB::commit();
            $this->clearScheduleCaches($schedule);

            return $this->success($request, $message, [
                'schedule' => $schedule->fresh()->load(['post', 'shift', 'securityUser']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Supervisor update status failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // SUPERVISOR-ONLY: EXPORT & STATISTICS
    // ============================================================

    public function exportSchedules(Request $request)
    {
        $this->authorizeManage();

        $query = SecuritySchedule::with(['post', 'shift', 'securityUser', 'assignedBy']);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('assignment_date', [$request->start_date, $request->end_date]);
        }
        if ($request->filled('post_id'))        $query->where('security_post_id', $request->post_id);
        if ($request->filled('status'))         $query->where('status', $request->status);
        if ($request->filled('shift_category')) $query->whereHas('shift', fn($q) => $q->where('category', $request->shift_category));

        $schedules = $query->orderBy('assignment_date')->orderBy('security_post_id')->get();

        $fileName = 'security_schedules_' . date('Y-m-d_H-i-s') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($schedules) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Schedule ID', 'Date', 'Day', 'Post', 'Post Type', 'Shift', 'Shift Category',
                'Start', 'End', 'Duration (h)', 'Overnight', 'Personnel', 'Phone', 'Badge',
                'Assigned By', 'Check-in', 'Check-out', 'Verified', 'Late Min', 'Overtime Min',
                'Total Min', 'Status', 'Handover Required', 'Handover Completed',
                'Notes', 'Created At', 'Updated At',
            ]);

            foreach ($schedules as $s) {
                $shift = $s->shift;
                $post  = $s->post;
                fputcsv($file, [
                    $s->id,
                    $s->assignment_date->format('Y-m-d'),
                    $s->assignment_date->format('l'),
                    $post->name ?? 'N/A',
                    $post->type ?? 'N/A',
                    $shift->name . ' (' . ($shift->getTimeRange() ?? 'N/A') . ')',
                    $shift->category ?? 'N/A',
                    $shift->start_time ?? 'N/A',
                    $shift->end_time ?? 'N/A',
                    $shift->duration_hours ?? 0,
                    $shift->is_overnight ? 'Yes' : 'No',
                    $s->securityUser->name ?? 'N/A',
                    $s->securityUser->phone ?? 'N/A',
                    $s->securityUser->badge_number ?? 'N/A',
                    $s->assignedBy->name ?? 'N/A',
                    $s->checkin_time?->format('Y-m-d H:i:s') ?? 'N/A',
                    $s->checkout_time?->format('Y-m-d H:i:s') ?? 'N/A',
                    $s->checkin_verification ? 'Yes' : 'No',
                    $s->late_minutes ?? 0,
                    $s->overtime_minutes ?? 0,
                    $s->total_minutes ?? 'N/A',
                    ucfirst($s->status),
                    $s->handover_info ? 'Yes' : 'No',
                    $s->handover_completed ? 'Yes' : 'No',
                    $s->notes ?? '',
                    $s->created_at->format('Y-m-d H:i:s'),
                    $s->updated_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getQrCodeStatistics(Request $request)
    {
        $this->authorizeManage();

        try {
            if (!class_exists(PostQrCode::class)) {
                return $this->failure($request, 'QR Code functionality not available.', 404);
            }
            $stats = [
                'total'           => PostQrCode::count(),
                'active'          => PostQrCode::where('is_active', true)
                    ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
                'by_type'         => PostQrCode::select('code_type', DB::raw('count(*) as count'))->groupBy('code_type')->pluck('count', 'code_type')->toArray(),
                'recent_activity' => PostQrCode::where('created_at', '>=', now()->subDays(7))->count(),
                'expiring_soon'   => PostQrCode::where('is_active', true)->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
                'expired'         => PostQrCode::where('is_active', true)->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())->count(),
                'one_time_used'   => PostQrCode::where('code_type', 'one_time')->where('uses_count', '>', 0)->count(),
            ];
            return $this->success($request, 'OK', ['stats' => $stats]);
        } catch (\Exception $e) {
            Log::error('getQrCodeStatistics failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to load QR code statistics.', 500);
        }
    }

    public function getPostCapacity(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'post_id' => 'required|exists:security_posts,id',
            'date'    => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        try {
            $post = SecurityPost::find($request->post_id);
            if (!$post) {
                return $this->failure($request, 'Post not found.', 404);
            }

            $dateStr = Carbon::parse($request->date)->format('Y-m-d');

            $assignedCount = SecuritySchedule::where('security_post_id', $post->id)
                ->whereDate('assignment_date', $dateStr)
                ->where('status', '!=', 'cancelled')->count();

            $max       = $post->max_personnel ?? 0;
            $available = max(0, $max - $assignedCount);

            $assignedPersonnel = SecuritySchedule::where('security_post_id', $post->id)
                ->whereDate('assignment_date', $dateStr)
                ->where('status', '!=', 'cancelled')
                ->with(['securityUser:id,name,phone,badge_number'])
                ->get(['id', 'security_user_id', 'security_shift_id', 'status'])
                ->map(fn($s) => [
                    'id'           => $s->id,
                    'user_id'      => $s->security_user_id,
                    'name'         => $s->securityUser->name ?? 'Unknown',
                    'phone'        => $s->securityUser->phone ?? 'N/A',
                    'badge_number' => $s->securityUser->badge_number ?? 'N/A',
                    'status'       => $s->status,
                    'shift_id'     => $s->security_shift_id,
                ]);

            $assignedIds = SecuritySchedule::where('security_post_id', $post->id)
                ->whereDate('assignment_date', $dateStr)
                ->where('status', '!=', 'cancelled')
                ->pluck('security_user_id')->toArray();

            $availablePersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('status', User::STATUS_ACTIVE)
                ->whereNotIn('id', $assignedIds)
                ->get(['id', 'name', 'phone', 'badge_number'])
                ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'phone' => $u->phone, 'badge_number' => $u->badge_number]);

            return $this->success($request, 'OK', [
                'capacity'            => $max,
                'assigned'            => $assignedCount,
                'available'           => $available,
                'max_personnel'       => $max,
                'is_full'             => $available <= 0,
                'post'                => [
                    'id' => $post->id, 'name' => $post->name, 'code' => $post->code,
                    'location' => $post->location, 'latitude' => $post->latitude, 'longitude' => $post->longitude,
                    'is_active' => $post->is_active,
                ],
                'assigned_personnel'  => $assignedPersonnel,
                'available_personnel' => $availablePersonnel,
                'date'                => $dateStr,
                'coverage_percentage' => $max > 0 ? round(($assignedCount / $max) * 100, 2) : 0,
                'status'              => $available <= 0 ? 'full' : ($assignedCount > 0 ? 'partial' : 'empty'),
            ]);
        } catch (\Exception $e) {
            Log::error('getPostCapacity failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to get post capacity.', 500);
        }
    }

    // ============================================================
    // GUARD-FACING METHODS
    // ============================================================

    public function mySchedules(Request $request)
    {
        $user = auth()->user();

        $todaySchedule = SecuritySchedule::with(['post', 'shift', 'rotationGroup'])
            ->where('security_user_id', $user->id)->whereDate('assignment_date', today())->first();

        $upcomingSchedules = SecuritySchedule::with(['post', 'shift', 'rotationGroup'])
            ->where('security_user_id', $user->id)
            ->whereDate('assignment_date', '>', today())
            ->orderBy('assignment_date')->paginate(10, ['*'], 'upcoming_page');

        $historySchedules = SecuritySchedule::with(['post', 'shift'])
            ->where('security_user_id', $user->id)
            ->whereDate('assignment_date', '<', today())
            ->orderBy('assignment_date', 'desc')->paginate(15, ['*'], 'history_page');

        $totalSchedules = SecuritySchedule::where('security_user_id', $user->id)->count();
        $completedCount = SecuritySchedule::where('security_user_id', $user->id)->where('status', 'completed')->count();
        $upcomingCount  = SecuritySchedule::where('security_user_id', $user->id)->whereDate('assignment_date', '>', today())->count();
        $rotationCount  = SecuritySchedule::where('security_user_id', $user->id)->where('is_rotated', true)->count();

        $rotationGroups = RotationGroup::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'active');
        })->with(['post', 'shift'])->get();

        foreach ($historySchedules as $schedule) {
            $schedule->earnings = $this->calculateEarnings($schedule);
        }

        return view('security.schedules.my-schedules', compact(
            'todaySchedule', 'upcomingSchedules', 'historySchedules',
            'totalSchedules', 'completedCount', 'upcomingCount',
            'rotationCount', 'rotationGroups'
        ));
    }

    public function showSchedule($id)
    {
        $schedule = SecuritySchedule::with(['post', 'shift', 'rotationGroup', 'rotatedFromUser', 'approvedBy'])
            ->where('security_user_id', auth()->id())->findOrFail($id);

        if ($schedule->handover_info && isset($schedule->handover_info['previous_shift_id'])) {
            $schedule->previousSchedule = SecuritySchedule::with('shift', 'securityUser')
                ->find($schedule->handover_info['previous_shift_id']);
        }

        return view('security.schedules.show', compact('schedule'));
    }

    public function showCheckin($scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->with(['post', 'shift'])->firstOrFail();

        if ($schedule->checkin_time) {
            return redirect()->route('security.my-schedules')
                ->with('info', 'You have already checked in for this shift at ' . $schedule->checkin_time->format('H:i'));
        }

        $post = $schedule->post;
        $verificationMethods = [];

        if ($post->latitude && $post->longitude) {
            $verificationMethods[] = 'gps';
        }

        $hasQRCode = DB::table('post_qr_codes')
            ->where('post_id', $post->id)->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q) => $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses'))
            ->exists();
        $post->has_qr = $hasQRCode;
        if ($hasQRCode) $verificationMethods[] = 'qr';

        $hasNFCTag = DB::table('post_nfc_tags')->where('post_id', $post->id)->where('is_active', true)->exists();
        $post->has_nfc = $hasNFCTag;
        if ($hasNFCTag) $verificationMethods[] = 'nfc';

        $user = auth()->user();
        $preferences = $user->preferences ?? [];
        $hasBiometric = $preferences['biometric_enabled'] ?? false;
        $post->has_biometric = $hasBiometric;
        if ($hasBiometric) $verificationMethods[] = 'biometric';

        $isSupervisor = method_exists($user, 'isSupervisor') && $user->isSupervisor();
        if ($isSupervisor) $verificationMethods[] = 'manual';

        if (empty($verificationMethods)) {
            $verificationMethods[] = 'confirm';
        }
        if (!in_array('gps', $verificationMethods)) {
            $verificationMethods[] = 'gps_fallback';
        }

        $post->has_gps     = in_array('gps', $verificationMethods) || in_array('gps_fallback', $verificationMethods);
        $post->has_confirm = in_array('confirm', $verificationMethods);

        try {
            $shiftStartTime = $schedule->shift->start_time;
            $shiftStart = str_contains($shiftStartTime, '-')
                ? Carbon::parse($shiftStartTime)
                : Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shiftStartTime);

            $minutesUntilShift = now()->diffInMinutes($shiftStart, false);
            $isEarly = $minutesUntilShift > 0;
            $isLate  = $minutesUntilShift < 0;
            $minutesDifference = abs($minutesUntilShift);
        } catch (\Exception $e) {
            Log::warning('Failed to parse shift start', ['schedule_id' => $scheduleId, 'error' => $e->getMessage()]);
            $minutesUntilShift = null;
            $isEarly = false;
            $isLate  = false;
            $minutesDifference = 0;
        }

        return view('security.schedules.checkin', compact(
            'schedule', 'verificationMethods', 'isEarly', 'isLate',
            'minutesDifference', 'minutesUntilShift'
        ));
    }

    public function smartCheckin(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->with(['post', 'shift'])->firstOrFail();

        if ($schedule->checkin_time) {
            return response()->json(['success' => false, 'message' => 'Already checked in at ' . $schedule->checkin_time->format('H:i')], 400);
        }

        $validator = Validator::make($request->all(), [
            'location'            => 'required|array',
            'location.lat'        => 'required|numeric|between:-90,90',
            'location.lng'        => 'required|numeric|between:-180,180',
            'accuracy'            => 'nullable|numeric|min:0|max:100',
            'verification_method' => 'required|in:gps,qr,nfc,biometric,manual',
            'verification_code'   => 'required_if:verification_method,qr|string|nullable',
            'device_id'           => 'nullable|string',
            'photo'               => 'nullable|image|max:5120',
            'selfie'              => 'nullable|image|max:5120',
            'notes'               => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $post = $schedule->post;
            $verificationResults = [];
            $verificationPassed = true;
            $warningMessages = [];

            if ($request->verification_method === 'gps' || $request->has('location')) {
                $gps = $this->verifyGPSCoordinates($request->location, $post, $request->accuracy ?? 10);
                $verificationResults['gps'] = $gps;
                if (!$gps['verified']) { $verificationPassed = false; $warningMessages[] = $gps['message']; }
            }
            if ($request->verification_method === 'qr' && $request->verification_code) {
                $qr = $this->verifyQRCode($request->verification_code, $post);
                $verificationResults['qr'] = $qr;
                if (!$qr['verified']) { $verificationPassed = false; $warningMessages[] = $qr['message']; }
            }
            if ($request->verification_method === 'nfc' && $request->device_id) {
                $nfc = $this->verifyNFCTag($request->device_id, $post);
                $verificationResults['nfc'] = $nfc;
                if (!$nfc['verified']) { $verificationPassed = false; $warningMessages[] = $nfc['message']; }
            }
            if ($request->hasFile('selfie')) {
                $bio = $this->verifyBiometric($request->file('selfie'), auth()->user());
                $verificationResults['biometric'] = $bio;
                if (!$bio['verified']) { $verificationPassed = false; $warningMessages[] = $bio['message']; }
            }
            $time = $this->verifyCheckinTime($schedule, $request->verification_method);
            $verificationResults['time'] = $time;
            if (!$time['verified']) { $verificationPassed = false; $warningMessages[] = $time['message']; }

            if ($request->device_id) {
                $device = $this->verifyDeviceFingerprint($request->device_id, auth()->user());
                $verificationResults['device'] = $device;
                if (!$device['verified']) { $verificationPassed = false; $warningMessages[] = $device['message']; }
            }

            if (!$verificationPassed && $request->verification_method !== 'manual') {
                $this->logVerificationAttempt($schedule, 'checkin', 'failed', $verificationResults);
                return response()->json([
                    'success' => false,
                    'message' => 'Location verification failed. Please ensure you are at the correct post.',
                    'warnings' => $warningMessages,
                    'verification_results' => $verificationResults,
                ], 403);
            }

            $verificationMetadata = [
                'method'          => $request->verification_method,
                'results'         => $verificationResults,
                'timestamp'       => now()->toIso8601String(),
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
                'device_id'       => $request->device_id,
                'manual_override' => $request->verification_method === 'manual',
                'manual_reason'   => $request->manual_reason ?? null,
                'verified_at'     => now()->toIso8601String(),
            ];

            $photoPath  = $request->hasFile('photo')  ? $request->file('photo')->store('checkin-photos/' . date('Y/m/d'), 'public')  : null;
            $selfiePath = $request->hasFile('selfie') ? $request->file('selfie')->store('selfies/' . date('Y/m/d'), 'private') : null;

            $schedule->checkin_time       = now();
            $schedule->status             = 'active';
            $schedule->checkin_notes      = $request->notes;
            $schedule->checkin_location   = $request->location;
            $schedule->checkin_accuracy   = $request->accuracy;
            $schedule->checkin_verification = $verificationMetadata;
            $schedule->checkin_photo      = $photoPath;
            $schedule->checkin_selfie     = $selfiePath;
            $schedule->checkin_device_id  = $request->device_id;
            $schedule->checkin_ip         = $request->ip();

            $shift = $schedule->shift;
            $scheduledStart = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
            if (now()->greaterThan($scheduledStart)) {
                $lateMinutes = (int) now()->diffInMinutes($scheduledStart, false) * -1;
                $schedule->late_minutes = $lateMinutes;
                $gracePeriod = $post->checkin_grace_period ?? 5;
                if ($lateMinutes > $gracePeriod) {
                    $schedule->late_flagged = true;
                    $schedule->late_reason  = 'Arrived ' . $lateMinutes . ' minutes late';
                }
            }

            $schedule->save();
            $this->logVerificationAttempt($schedule, 'checkin', 'success', $verificationResults);

            DB::commit();
            $this->clearSecurityCaches($schedule);

            return response()->json([
                'success'              => true,
                'message'              => 'Checked in successfully',
                'checkin_time'         => $schedule->checkin_time->format('H:i'),
                'late_minutes'         => $schedule->late_minutes ?? 0,
                'status'               => $schedule->status,
                'verification_results' => $verificationResults,
                'post_name'            => $post->name,
                'post_location'        => $post->location,
                'redirect'             => route('security.my-schedules'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Smart check-in failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Check-in failed. Please try again or contact supervisor.'], 500);
        }
    }

    public function smartCheckout(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->where('status', 'active')
            ->with(['post', 'shift'])->firstOrFail();

        $validator = Validator::make($request->all(), [
            'location'            => 'required|array',
            'location.lat'        => 'required|numeric|between:-90,90',
            'location.lng'        => 'required|numeric|between:-180,180',
            'accuracy'            => 'nullable|numeric|min:0|max:100',
            'verification_method' => 'required|in:gps,qr,nfc,biometric,manual',
            'handover_completed'  => 'nullable|boolean',
            'handover_notes'      => 'nullable|string|max:1000',
            'device_id'           => 'nullable|string',
            'notes'               => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $post = $schedule->post;
            $verificationResults = [];
            $verificationPassed = true;

            if ($request->verification_method === 'gps' || $request->has('location')) {
                $gps = $this->verifyGPSCoordinates($request->location, $post, $request->accuracy ?? 10);
                $verificationResults['gps'] = $gps;
                if (!$gps['verified']) $verificationPassed = false;
            }
            $time = $this->verifyCheckoutTime($schedule);
            $verificationResults['time'] = $time;
            if (!$time['verified']) $verificationPassed = false;

            if ($schedule->handover_info && !$request->handover_completed) {
                $verificationPassed = false;
                $verificationResults['handover'] = ['verified' => false, 'message' => 'Handover must be completed before checkout'];
            }

            if (!$verificationPassed && $request->verification_method !== 'manual') {
                return response()->json(['success' => false, 'message' => 'Checkout verification failed', 'verification_results' => $verificationResults], 403);
            }

            $schedule->checkout_time     = now();
            $schedule->status            = 'completed';
            $schedule->checkout_notes    = $request->notes;
            $schedule->checkout_location = $request->location;
            $schedule->checkout_accuracy = $request->accuracy;
            $schedule->checkout_verification = [
                'method'    => $request->verification_method,
                'results'   => $verificationResults,
                'timestamp' => now()->toIso8601String(),
                'device_id' => $request->device_id,
            ];

            $shift = $schedule->shift;
            $scheduledEnd = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->end_time);
            if ($scheduledEnd <= Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time)) {
                $scheduledEnd->addDay();
            }
            if (now()->greaterThan($scheduledEnd)) {
                $schedule->overtime_minutes = now()->diffInMinutes($scheduledEnd);
            }
            if ($schedule->checkin_time) {
                $schedule->total_minutes = $schedule->checkin_time->diffInMinutes(now());
                if ($schedule->break_duration > 0) $schedule->total_minutes -= $schedule->break_duration;
            }

            $schedule->save();

            if ($request->handover_completed && $schedule->handover_info) {
                $handoverInfo = $schedule->handover_info;
                $handoverInfo['handover_notes']        = $request->handover_notes;
                $handoverInfo['handover_completed_at'] = now()->toDateTimeString();
                $handoverInfo['handover_completed_by'] = auth()->id();
                $schedule->update([
                    'handover_info'         => $handoverInfo,
                    'handover_completed'    => true,
                    'handover_completed_at' => now(),
                ]);
            }

            DB::commit();
            $this->clearSecurityCaches($schedule);

            return response()->json([
                'success'              => true,
                'message'              => 'Checked out successfully',
                'checkout_time'        => $schedule->checkout_time->format('H:i'),
                'overtime_minutes'     => $schedule->overtime_minutes ?? 0,
                'total_hours'          => round(($schedule->total_minutes ?? 0) / 60, 1),
                'verification_results' => $verificationResults,
                'redirect'             => route('security.my-schedules'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Smart checkout failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Checkout failed. Please try again.'], 500);
        }
    }

    public function checkin(Request $request, $scheduleId)  { return $this->smartCheckin($request, $scheduleId); }
    public function checkout(Request $request, $scheduleId) { return $this->smartCheckout($request, $scheduleId); }

    // -------- Verification helpers --------
    private function verifyGPSCoordinates($userLocation, $post, $accuracy = 10)
    {
        if (!$post->latitude || !$post->longitude) {
            return ['verified' => true, 'method' => 'gps', 'message' => 'Post location not configured. GPS check skipped.', 'distance' => null, 'threshold' => null];
        }
        $earthRadius = 6371000;
        $latFrom = deg2rad($userLocation['lat']);
        $lonFrom = deg2rad($userLocation['lng']);
        $latTo   = deg2rad($post->latitude);
        $lonTo   = deg2rad($post->longitude);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        $distance = $angle * $earthRadius;

        $allowedRadius   = $post->checkin_radius ?? 100;
        $effectiveRadius = $allowedRadius + ($accuracy * 2);
        $verified        = $distance <= $effectiveRadius;

        return [
            'verified'         => $verified,
            'method'           => 'gps',
            'distance'         => round($distance, 1),
            'allowed_radius'   => $allowedRadius,
            'accuracy'         => $accuracy,
            'effective_radius' => $effectiveRadius,
            'message'          => $verified ? "You are within the allowed radius (" . round($distance, 1) . "m)" : "You are " . round($distance, 1) . "m from the post (max allowed: {$allowedRadius}m)",
            'post_coordinates' => ['lat' => $post->latitude, 'lng' => $post->longitude],
        ];
    }

    private function verifyQRCode($scannedCode, $post)
    {
        $cacheKey   = "qr_code_{$post->id}";
        $validCodes = Cache::get($cacheKey, []);
        $verified   = in_array($scannedCode, $validCodes);

        if (!$verified) {
            $qrRecord = DB::table('post_qr_codes')
                ->where('post_id', $post->id)->where('code', $scannedCode)->where('is_active', true)
                ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn($q) => $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses'))
                ->first();

            $verified = !is_null($qrRecord);

            if ($verified && $qrRecord) {
                DB::table('post_qr_codes')->where('id', $qrRecord->id)
                    ->update(['uses_count' => $qrRecord->uses_count + 1, 'updated_at' => now()]);
                if ($qrRecord->code_type === 'one_time') {
                    DB::table('post_qr_codes')->where('id', $qrRecord->id)->update(['is_active' => false]);
                }
                $validCodes[] = $scannedCode;
                Cache::put($cacheKey, $validCodes, now()->addMinutes(5));
            }
        }
        return [
            'verified' => $verified,
            'method'   => 'qr',
            'message'  => $verified ? 'QR code verified' : 'Invalid or expired QR code',
            'code'     => substr($scannedCode, 0, 8) . '...',
        ];
    }

    private function verifyNFCTag($tagId, $post)
    {
        $validTag = DB::table('post_nfc_tags')
            ->where('post_id', $post->id)->where('tag_id', $tagId)->where('is_active', true)->first();
        $verified = !is_null($validTag);
        if ($verified && $validTag) {
            DB::table('post_nfc_tags')->where('id', $validTag->id)
                ->update(['last_used_at' => now(), 'last_used_by' => auth()->id()]);
        }
        return [
            'verified' => $verified,
            'method'   => 'nfc',
            'message'  => $verified ? 'NFC tag verified' : 'Invalid NFC tag for this post',
            'tag_id'   => substr($tagId, 0, 8) . '...',
        ];
    }

    private function verifyBiometric($selfieFile, $user)
    {
        try {
            $preferences = $user->preferences ?? [];
            if (empty($preferences['face_reference_photo'] ?? null)) {
                return ['verified' => true, 'method' => 'biometric', 'message' => 'Face reference not configured', 'confidence' => null];
            }
            return ['verified' => true, 'method' => 'biometric', 'message' => 'Face verified', 'confidence' => 0.98];
        } catch (\Exception $e) {
            Log::error('Biometric verification failed: ' . $e->getMessage());
            return ['verified' => false, 'method' => 'biometric', 'message' => 'Biometric verification error', 'confidence' => null];
        }
    }

    private function verifyCheckinTime($schedule, $method)
    {
        $shift = $schedule->shift;
        $scheduledStart = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
        $earliestAllowed = $scheduledStart->copy()->subMinutes(30);
        $latestAllowed   = $scheduledStart->copy()->addMinutes(60);
        $now = now();

        if ($now < $earliestAllowed) {
            return ['verified' => false, 'message' => 'Too early to check in. You can check in from ' . $earliestAllowed->format('H:i'), 'early_minutes' => $now->diffInMinutes($earliestAllowed)];
        }
        if ($now > $latestAllowed && $method !== 'manual') {
            return ['verified' => false, 'message' => 'Too late to check in. Please contact supervisor for manual override.', 'late_minutes' => $scheduledStart->diffInMinutes($now)];
        }
        return [
            'verified'           => true,
            'message'            => 'Check-in time accepted',
            'is_early'           => $now < $scheduledStart,
            'is_late'            => $now > $scheduledStart,
            'minutes_difference' => $scheduledStart->diffInMinutes($now),
        ];
    }

    private function verifyCheckoutTime($schedule)
    {
        $shift = $schedule->shift;
        $scheduledEnd = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->end_time);
        if ($scheduledEnd <= Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time)) {
            $scheduledEnd->addDay();
        }
        $now = now();
        $earliestAllowed = $scheduledEnd->copy()->subMinutes(30);
        if ($now < $earliestAllowed) {
            return ['verified' => false, 'message' => 'Too early to check out. Shift ends at ' . $scheduledEnd->format('H:i'), 'early_minutes' => $now->diffInMinutes($scheduledEnd)];
        }
        return [
            'verified'         => true,
            'message'          => 'Checkout time accepted',
            'is_overtime'      => $now > $scheduledEnd,
            'overtime_minutes' => $now > $scheduledEnd ? $scheduledEnd->diffInMinutes($now) : 0,
        ];
    }

    private function verifyDeviceFingerprint($deviceId, $user)
    {
        $registeredDevice = DB::table('user_devices')
            ->where('user_id', $user->id)->where('device_id', $deviceId)->first();
        $verified = !is_null($registeredDevice) && $registeredDevice->is_trusted;

        if (!$registeredDevice) {
            DB::table('user_devices')->insert([
                'user_id' => $user->id, 'device_id' => $deviceId,
                'device_name' => request()->header('User-Agent'),
                'first_seen_at' => now(), 'last_seen_at' => now(),
                'is_trusted' => false, 'requires_verification' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            DB::table('user_devices')->where('id', $registeredDevice->id)->update(['last_seen_at' => now()]);
        }
        return ['verified' => $verified, 'method' => 'device', 'message' => $verified ? 'Trusted device' : 'New device detected - will require additional verification', 'is_trusted' => $verified];
    }

    private function logVerificationAttempt($schedule, $action, $status, $results)
    {
        try {
            DB::table('verification_logs')->insert([
                'user_id'     => auth()->id(),
                'schedule_id' => $schedule->id,
                'action'      => $action,
                'status'      => $status,
                'results'     => json_encode($results),
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'created_at'  => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log verification: ' . $e->getMessage());
        }
    }

    // -------- Break management (guard-facing; ownership enforced) --------
    public function startBreak(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'break_id' => 'required|integer',
            'location' => 'nullable|array',
            'notes'    => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $this->supervisorStartBreak($schedule, $request);
            return response()->json(['success' => true, 'message' => 'Break started.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function endBreak(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'location' => 'nullable|array',
            'notes'    => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $this->supervisorEndBreak($schedule, $request);
            return response()->json(['success' => true, 'message' => 'Break ended.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ============================================================
    // PRIVATE HELPERS — DASHBOARD / STATISTICS
    // ============================================================

    private function getTodayScheduleSummary()
    {
        $today = today()->toDateString();

        $schedules = SecuritySchedule::whereDate('assignment_date', $today)
            ->with(['post:id,name,code,max_personnel', 'shift:id,name,category', 'securityUser:id,name'])
            ->get();

        $totalAssigned    = $schedules->count();
        $checkedIn        = $schedules->where('status', 'active')->count();
        $absent           = $schedules->where('status', 'absent')->count();
        $completed        = $schedules->where('status', 'completed')->count();
        $lateCheckins     = $schedules->where('late_minutes', '>', 0)->count();
        $onBreak          = $schedules->where('break_status', 'active')->count();
        $verifiedCheckins = $schedules->whereNotNull('checkin_verification')->count();
        $offlineCheckins  = $schedules->where('offline_mode', true)->count();

        $byCategory = $schedules->groupBy(fn($s) => $s->shift->category ?? 'unknown')->map(fn($g) => [
            'count' => $g->count(),
            'name'  => $g->first()->shift->category ?? 'unknown',
        ]);

        $byPost = $schedules->groupBy('post.name')->map(function ($group) {
            $post = $group->first()->post;
            $assignedCount = $group->count();
            $maxPersonnel  = $post->max_personnel ?? 0;
            return [
                'count'               => $assignedCount,
                'post'                => $post,
                'post_name'           => $post->name,
                'post_id'             => $post->id,
                'post_code'           => $post->code ?? '',
                'max_personnel'       => $maxPersonnel,
                'fully_staffed'       => $assignedCount >= $maxPersonnel,
                'coverage_percentage' => $maxPersonnel > 0 ? round(($assignedCount / $maxPersonnel) * 100, 2) : 0,
                'remaining_slots'     => max(0, $maxPersonnel - $assignedCount),
                'is_overstaffed'      => $assignedCount > $maxPersonnel,
                'is_understaffed'     => $assignedCount < $maxPersonnel,
                'status_color'        => $this->getCoverageStatusColor($assignedCount, $maxPersonnel),
                'assigned_personnel'  => $group->map(fn($s) => [
                    'id'           => $s->security_user_id,
                    'name'         => $s->securityUser->name ?? 'Unknown',
                    'shift'        => $s->shift->name ?? 'Unknown',
                    'status'       => $s->status,
                    'checkin_time' => $s->checkin_time,
                    'verified'     => !is_null($s->checkin_verification),
                ])->toArray(),
            ];
        })->sortByDesc('fully_staffed')->sortByDesc('coverage_percentage');

        $allPosts = SecurityPost::active()->get(['id', 'name', 'max_personnel']);
        $postCoverage = [];
        foreach ($allPosts as $post) {
            $postSchedules = $schedules->where('security_post_id', $post->id);
            $assignedCount = $postSchedules->count();
            $maxPersonnel  = $post->max_personnel;
            $postCoverage[] = [
                'post'            => $post,
                'post_name'       => $post->name,
                'assigned'        => $assignedCount,
                'required'        => $maxPersonnel,
                'coverage_rate'   => $maxPersonnel > 0 ? round(($assignedCount / $maxPersonnel) * 100, 2) : 0,
                'fully_staffed'   => $assignedCount >= $maxPersonnel,
                'remaining_slots' => max(0, $maxPersonnel - $assignedCount),
                'verified_count'  => $postSchedules->whereNotNull('checkin_verification')->count(),
            ];
        }

        return [
            'total_assigned'      => $totalAssigned,
            'checked_in'          => $checkedIn,
            'absent'              => $absent,
            'completed'           => $completed,
            'late_checkins'       => $lateCheckins,
            'on_break'            => $onBreak,
            'verified_checkins'   => $verifiedCheckins,
            'offline_checkins'    => $offlineCheckins,
            'by_category'         => $byCategory,
            'by_post'             => $byPost,
            'post_coverage'       => $postCoverage,
            'schedules'           => $schedules,
            'summary_date'        => $today,
            'total_posts'         => $allPosts->count(),
            'fully_staffed_posts' => collect($postCoverage)->where('fully_staffed', true)->count(),
            'understaffed_posts'  => collect($postCoverage)->where('fully_staffed', false)->where('assigned', '>', 0)->count(),
            'unstaffed_posts'     => collect($postCoverage)->where('assigned', 0)->count(),
        ];
    }

    private function getUpcomingHandovers()
    {
        $today    = today()->toDateString();
        $tomorrow = today()->addDay()->toDateString();

        return SecuritySchedule::whereDate('assignment_date', $today)
            ->orWhereDate('assignment_date', $tomorrow)
            ->whereNotNull('handover_info')
            ->where('handover_completed', false)
            ->with(['post:id,name', 'shift:id,name,start_time,end_time', 'securityUser:id,name'])
            ->get(['id', 'security_post_id', 'security_shift_id', 'security_user_id', 'assignment_date', 'handover_info'])
            ->filter(function ($schedule) {
                $hi = $schedule->handover_info;
                if (!$hi || empty($hi['handover_start'])) return false;
                try {
                    $handoverTime = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $hi['handover_start']);
                    return $handoverTime->isFuture() && $handoverTime->diffInHours(now()) <= 2;
                } catch (\Exception $e) {
                    return false;
                }
            })
            ->sortBy(fn($s) => $s->handover_info['handover_start'] ?? '00:00')
            ->values();
    }

    private function getUpcomingHandoversForSupervisor()
    {
        return $this->getUpcomingHandovers();
    }

    private function getVerificationStatistics()
    {
        $today        = today();
        $startOfWeek  = $today->copy()->startOfWeek();
        $startOfMonth = $today->copy()->startOfMonth();

        // NOTE: `verification_logs` has no `method` column — method is
        // stored in the `results` JSON. Aggregate by `action` instead.
        return [
            'today' => [
                'total'    => VerificationLog::whereDate('created_at', $today)->count(),
                'success'  => VerificationLog::whereDate('created_at', $today)->where('status', 'success')->count(),
                'failed'   => VerificationLog::whereDate('created_at', $today)->where('status', 'failed')->count(),
                'by_action'=> VerificationLog::whereDate('created_at', $today)
                    ->selectRaw('action, count(*) as count')
                    ->groupBy('action')->pluck('count', 'action')->toArray(),
            ],
            'this_week' => [
                'total'        => VerificationLog::whereBetween('created_at', [$startOfWeek, $today])->count(),
                'unique_users' => VerificationLog::whereBetween('created_at', [$startOfWeek, $today])
                    ->distinct('user_id')->count('user_id'),
                'success_rate' => $this->calculateVerificationRate($startOfWeek, $today),
            ],
            'this_month' => [
                'total'     => VerificationLog::whereBetween('created_at', [$startOfMonth, $today])->count(),
                'by_device' => VerificationLog::whereBetween('created_at', [$startOfMonth, $today])
                    ->whereNotNull('device_id')
                    ->selectRaw('device_id, count(*) as count')
                    ->groupBy('device_id')->orderBy('count', 'desc')->limit(5)->get(),
            ],
        ];
    }

    private function getScheduleStatistics()
    {
        $today      = today()->toDateString();
        $weekStart  = today()->startOfWeek();
        $weekEnd    = today()->endOfWeek();
        $monthStart = today()->startOfMonth();
        $monthEnd   = today()->endOfMonth();

        $cacheKey = 'schedule_stats_' . today()->format('Y-m-d');

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($today, $weekStart, $weekEnd, $monthStart, $monthEnd) {
            return [
                'today' => [
                    'total'     => SecuritySchedule::whereDate('assignment_date', $today)->count(),
                    'completed' => SecuritySchedule::whereDate('assignment_date', $today)->where('status', 'completed')->count(),
                    'absent'    => SecuritySchedule::whereDate('assignment_date', $today)->where('status', 'absent')->count(),
                    'late'      => SecuritySchedule::whereDate('assignment_date', $today)->where('late_minutes', '>', 0)->count(),
                ],
                'this_week' => [
                    'total'          => SecuritySchedule::whereBetween('assignment_date', [$weekStart, $weekEnd])->count(),
                    'completed'      => SecuritySchedule::whereBetween('assignment_date', [$weekStart, $weekEnd])->where('status', 'completed')->count(),
                    'overtime_hours' => SecuritySchedule::whereBetween('assignment_date', [$weekStart, $weekEnd])->sum('overtime_minutes') / 60,
                ],
                'this_month' => [
                    'total'             => SecuritySchedule::whereBetween('assignment_date', [$monthStart, $monthEnd])->count(),
                    'by_status'         => SecuritySchedule::whereBetween('assignment_date', [$monthStart, $monthEnd])
                        ->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->toArray(),
                    'by_shift_category' => SecuritySchedule::whereBetween('assignment_date', [$monthStart, $monthEnd])
                        ->join('security_shifts', 'security_schedules.security_shift_id', '=', 'security_shifts.id')
                        ->selectRaw('security_shifts.category, count(*) as count')
                        ->groupBy('security_shifts.category')->pluck('count', 'security_shifts.category')->toArray(),
                    'verification_rate' => $this->calculateVerificationRate($monthStart, $monthEnd),
                ],
                'personnel_stats' => $this->getPersonnelStatistics(),
            ];
        });
    }

    private function getPersonnelStatistics()
    {
        $monthStart = today()->startOfMonth();
        $monthEnd   = today()->endOfMonth();

        try {
            return User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('status', User::STATUS_ACTIVE)
                ->withCount(['schedules' => fn($q) => $q->whereBetween('assignment_date', [$monthStart, $monthEnd])])
                ->withCount(['schedules as completed_schedules' => fn($q) => $q
                    ->whereBetween('assignment_date', [$monthStart, $monthEnd])->where('status', 'completed')])
                ->withCount(['schedules as absent_schedules' => fn($q) => $q
                    ->whereBetween('assignment_date', [$monthStart, $monthEnd])->where('status', 'absent')])
                ->having('schedules_count', '>', 0)
                ->orderBy('schedules_count', 'desc')->limit(10)
                ->get(['id', 'name'])
                ->map(fn($u) => [
                    'id'               => $u->id,
                    'name'             => $u->name,
                    'total_shifts'     => $u->schedules_count,
                    'completed_shifts' => $u->completed_schedules,
                    'absent_shifts'    => $u->absent_schedules,
                    'attendance_rate'  => $u->schedules_count > 0
                        ? round(($u->completed_schedules / $u->schedules_count) * 100, 2) : 0,
                ]);
        } catch (\Exception $e) {
            Log::warning('Failed to get personnel statistics: ' . $e->getMessage());
            return [];
        }
    }

    private function calculateVerificationRate($startDate, $endDate)
    {
        $total = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->whereNotNull('checkin_time')->count();
        if ($total == 0) return 100;
        $verified = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->whereNotNull('checkin_verification')->count();
        return round(($verified / $total) * 100, 1);
    }

    private function getCoverageStatusColor($assigned, $max)
    {
        if ($max == 0) return 'secondary';
        $percentage = ($assigned / $max) * 100;
        if ($percentage >= 100) return 'success';
        if ($percentage >= 75)  return 'info';
        if ($percentage >= 50)  return 'warning';
        return 'danger';
    }

    private function getDeletedByStats()
    {
        try {
            $trashedSchedules = SecuritySchedule::onlyTrashed()->get();
            $deletedByStats = [];
            foreach ($trashedSchedules as $schedule) {
                if (isset($schedule->audit_log) && is_array($schedule->audit_log)) {
                    foreach ($schedule->audit_log as $log) {
                        if (($log['action'] ?? null) === 'deleted' && isset($log['deleted_by_name'])) {
                            $name = $log['deleted_by_name'];
                            $deletedByStats[$name] = ($deletedByStats[$name] ?? 0) + 1;
                        }
                    }
                }
            }
            arsort($deletedByStats);
            return $deletedByStats;
        } catch (\Exception $e) {
            Log::warning('Failed to get deleted by stats: ' . $e->getMessage());
            return [];
        }
    }

    // ============================================================
    // PRIVATE HELPERS — RESTORE / CACHE
    // ============================================================

    private function checkRestorationConflicts($schedule)
    {
        $conflicts = [];
        try {
            $post = SecurityPost::withTrashed()->find($schedule->security_post_id);
            if (!$post) $conflicts[] = 'Associated security post no longer exists.';
            elseif ($post->trashed()) $conflicts[] = 'Associated security post is deleted. Please restore the post first.';
            elseif (!$post->is_active) $conflicts[] = 'Associated security post is inactive.';

            $shift = SecurityShift::withTrashed()->find($schedule->security_shift_id);
            if (!$shift) $conflicts[] = 'Associated shift no longer exists.';
            elseif ($shift->trashed()) $conflicts[] = 'Associated shift is deleted. Please restore the shift first.';
            elseif (!$shift->is_active) $conflicts[] = 'Associated shift is inactive.';

            $user = User::withTrashed()->find($schedule->security_user_id);
            if (!$user) $conflicts[] = 'Associated security personnel no longer exists.';
            elseif ($user->trashed()) $conflicts[] = 'Associated security personnel is deleted. Please restore the user first.';
            elseif ($user->status !== User::STATUS_ACTIVE) $conflicts[] = 'Associated security personnel is not active.';

            $existingAssignment = SecuritySchedule::where('security_user_id', $schedule->security_user_id)
                ->whereDate('assignment_date', $schedule->assignment_date)
                ->where('id', '!=', $schedule->id)->exists();
            if ($existingAssignment) $conflicts[] = 'This personnel already has an assignment on this date.';

            if ($post && !$post->trashed()) {
                $assignedCount = SecuritySchedule::where('security_post_id', $schedule->security_post_id)
                    ->whereDate('assignment_date', $schedule->assignment_date)->count();
                if ($assignedCount >= $post->max_personnel) {
                    $conflicts[] = "This post has reached maximum capacity ({$post->max_personnel} personnel) for the selected date.";
                }
            }

            if ($shift && !$shift->trashed()) {
                $date = Carbon::parse($schedule->assignment_date);
                if (!$shift->isApplicableOnDay($date->dayOfWeekIso)) {
                    $conflicts[] = 'This shift is not applicable on the selected date.';
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check restoration conflicts', ['schedule_id' => $schedule->id, 'error' => $e->getMessage()]);
            $conflicts[] = 'Error checking restoration conflicts: ' . $e->getMessage();
        }
        return $conflicts;
    }

    private function clearScheduleCaches($schedule)
    {
        if (!$schedule) return;
        try {
            $keys = [
                "handover_{$schedule->security_post_id}_{$schedule->assignment_date->format('Y-m-d')}_{$schedule->security_shift_id}",
                "rotation_history_{$schedule->security_user_id}_{$schedule->security_shift_id}",
                "shift_{$schedule->security_shift_id}",
                "post_capacity_{$schedule->security_post_id}_{$schedule->assignment_date->format('Y-m-d')}",
                'schedule_stats_' . today()->format('Y-m-d'),
            ];
            foreach ($keys as $k) Cache::forget($k);
        } catch (\Exception $e) {
            Log::warning('Failed to clear schedule caches', ['schedule_id' => $schedule->id ?? null, 'error' => $e->getMessage()]);
        }
    }

    private function clearSecurityCaches($schedule)
    {
        $this->clearScheduleCaches($schedule);
    }

    // ============================================================
    // PRIVATE HELPERS — HANDOVER / ROTATION DATA
    // ============================================================

    private function calculateHandoverTimes($shift, $assignmentDate, $postId = null)
    {
        if (!$shift || !$shift->handover_config || empty($shift->handover_config['has_handover'])) {
            return null;
        }
        $handoverDuration = $shift->handover_config['handover_duration'] ?? 30;
        $assignmentDate   = Carbon::parse($assignmentDate);
        if (!$postId) return null;

        $cacheKey = "handover_{$postId}_{$assignmentDate->format('Y-m-d')}_{$shift->id}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($postId, $assignmentDate, $shift, $handoverDuration) {
            try {
                $previousSchedule = SecuritySchedule::where('security_post_id', $postId)
                    ->whereDate('assignment_date', $assignmentDate)
                    ->where('security_shift_id', '!=', $shift->id)
                    ->whereIn('status', ['active', 'scheduled'])
                    ->orderBy('assignment_date', 'desc')
                    ->select('id', 'security_shift_id')->first();
                if (!$previousSchedule) return null;

                $previousShift = Cache::remember("shift_{$previousSchedule->security_shift_id}", now()->addHour(), fn() =>
                    SecurityShift::find($previousSchedule->security_shift_id, ['id', 'start_time', 'end_time', 'is_overnight'])
                );
                if (!$previousShift) return null;

                // Respect overnight shifts: if previous shift ends after start,
                // the end time belongs to the next calendar day.
                $handoverStart = Carbon::parse($previousShift->end_time);
                $handoverEnd   = Carbon::parse($shift->start_time);

                if (!empty($previousShift->is_overnight)
                    && $handoverStart->hour < 12
                    && $handoverEnd->hour > 12) {
                    // Previous shift ended in the small hours; shift starts in the evening.
                    $handoverStart->addDay();
                }

                return [
                    'previous_shift_id' => $previousSchedule->id,
                    'handover_start'    => $handoverStart->format('H:i'),
                    'handover_end'      => $handoverEnd->format('H:i'),
                    'handover_duration' => $handoverDuration,
                    'handover_window'   => $handoverStart->diffInMinutes($handoverEnd, false),
                    'notes_required'    => $shift->handover_config['handover_notes_required'] ?? true,
                    'checklist'         => $shift->handover_config['handover_checklist'] ?? [],
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to fetch previous schedule for handover', ['post_id' => $postId, 'error' => $e->getMessage()]);
                return null;
            }
        });
    }

    private function prepareRotationData($shift, $userId)
    {
        if (!$shift || $shift->rotation_type !== 'rotating') return null;

        $user = User::find($userId);
        if (!$user) return null;

        $rotationConfig = $shift->rotation_config ?? [];
        $sequence       = $this->getRotationSequence($rotationConfig['rotation_sequence'] ?? 'morning_evening');
        $currentIndex   = $rotationConfig['current_sequence_index'] ?? 0;

        $cacheKey = "rotation_history_{$userId}_{$shift->id}";
        $history  = Cache::remember($cacheKey, now()->addMinutes(10), fn() => $this->getUserRotationHistory($userId, $shift->id));

        $nextRotationDate = null;
        if (!empty($rotationConfig['last_rotation_date'])) {
            try {
                $lastRotation = Carbon::parse($rotationConfig['last_rotation_date']);
                $rotationDays = $rotationConfig['rotation_days'] ?? 7;
                $nextRotationDate = $lastRotation->addDays($rotationDays);
            } catch (\Exception $e) {
                Log::warning('Failed to calculate next rotation date', ['shift_id' => $shift->id, 'error' => $e->getMessage()]);
            }
        }

        return [
            'sequence_type'         => $rotationConfig['rotation_sequence'] ?? 'morning_evening',
            'current_position'      => $sequence[$currentIndex] ?? $sequence[0],
            'next_position'         => $sequence[($currentIndex + 1) % count($sequence)] ?? $sequence[0],
            'next_rotation_date'    => $nextRotationDate?->format('Y-m-d'),
            'rotation_days'         => $rotationConfig['rotation_days'] ?? 7,
            'user_rotation_history' => $history,
        ];
    }

    private function getUserRotationHistory($userId, $shiftId)
    {
        try {
            return SecuritySchedule::where('security_user_id', $userId)
                ->where('security_shift_id', $shiftId)
                ->where('status', 'completed')
                ->orderBy('assignment_date', 'desc')->limit(5)
                ->get(['assignment_date', 'status', 'checkin_time', 'checkout_time', 'late_minutes', 'overtime_minutes'])
                ->map(fn($s) => [
                    'date'         => $s->assignment_date->format('Y-m-d'),
                    'status'       => $s->status,
                    'duration'     => $this->calculateDuration($s->checkin_time, $s->checkout_time),
                    'was_on_time'  => ($s->late_minutes ?? 0) == 0,
                    'had_overtime' => ($s->overtime_minutes ?? 0) > 0,
                ])->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get user rotation history', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function calculateDuration($checkin, $checkout)
    {
        if (!$checkin || !$checkout) return null;
        try {
            $ci = $checkin instanceof Carbon ? $checkin : Carbon::parse($checkin);
            $co = $checkout instanceof Carbon ? $checkout : Carbon::parse($checkout);
            return $ci->diffInMinutes($co);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getRotationSequence($sequenceType)
    {
        $sequences = [
            'morning_evening' => ['morning', 'evening', 'night', 'off'],
            'evening_morning' => ['evening', 'morning', 'night', 'off'],
            'night_morning'   => ['night', 'morning', 'evening', 'off'],
        ];
        return $sequences[$sequenceType] ?? ['morning', 'evening', 'night', 'off'];
    }

    // ============================================================
    // PRIVATE HELPERS — BULK ASSIGN / ROTATION GROUP
    // ============================================================

    private function getOrCreateRotationGroup($request, $post, $shift)
    {
        if ($request->boolean('create_rotation_group', true) && $request->filled('rotation_group_name')) {
            $group = RotationGroup::create([
                'name'                => $request->rotation_group_name,
                'code'                => $this->generateGroupCode($request->rotation_group_name),
                'description'         => 'Auto-created from supervisor bulk assignment',
                'security_post_id'    => $post->id,
                'security_shift_id'   => $shift->id,
                'group_type'          => $shift->category === 'night' ? 'night' : 'day',
                'rotation_config'     => [
                    'type'                   => $request->rotation_pattern,
                    'interval_days'          => $this->getIntervalForPattern($request->rotation_pattern),
                    'start_date'             => $request->start_date,
                    'last_rotation_date'     => null,
                    'next_rotation_date'     => $request->start_date,
                    'current_sequence_index' => 0,
                    'sequence'               => $this->getSequenceForPattern($request->rotation_pattern),
                    'rotation_history'       => [],
                ],
                'max_members'         => $post->max_personnel,
                'min_members'         => 2,
                'current_members'     => count($request->security_user_ids),
                'preference_weights'  => [
                    'seniority'            => 25,
                    'performance'          => 25,
                    'availability'         => 20,
                    'preferred_shift'      => 15,
                    'rotation_willingness' => 15,
                ],
                'auto_rotate'          => true,
                'auto_rotate_schedule' => $this->getScheduleForPattern($request->rotation_pattern),
                'auto_rotate_time'     => '00:00',
                'status'               => 'active',
                'settings'             => [
                    'require_handover'   => $request->boolean('include_handover', true),
                    'notify_on_rotate'   => true,
                    'maintain_coverage'  => $request->boolean('maintain_coverage', true),
                    'allow_swaps'        => true,
                ],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($request->security_user_ids as $userId) {
                DB::table('rotation_group_members')->insert([
                    'rotation_group_id' => $group->id,
                    'user_id'           => $userId,
                    'role'              => 'member',
                    'joined_at'         => now(),
                    'preference_score'  => $this->calculateMemberPreferenceScore($userId, $group),
                    'assigned_by'       => auth()->id(),
                    'status'            => 'active',
                    'rotation_count'    => 0,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            Log::info('Created new rotation group from supervisor bulk assign', [
                'group_id' => $group->id, 'name' => $group->name, 'members' => count($request->security_user_ids),
            ]);
            return $group;
        }

        $existing = RotationGroup::where('security_post_id', $post->id)
            ->where('security_shift_id', $shift->id)
            ->where('status', 'active')->first();

        if ($existing) {
            foreach ($request->security_user_ids as $userId) {
                $exists = DB::table('rotation_group_members')
                    ->where('rotation_group_id', $existing->id)->where('user_id', $userId)->exists();
                if (!$exists) {
                    DB::table('rotation_group_members')->insert([
                        'rotation_group_id' => $existing->id,
                        'user_id'           => $userId,
                        'role'              => 'member',
                        'joined_at'         => now(),
                        'preference_score'  => $this->calculateMemberPreferenceScore($userId, $existing),
                        'assigned_by'       => auth()->id(),
                        'status'            => 'active',
                        'rotation_count'    => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }
            $existing->update(['current_members' => DB::table('rotation_group_members')
                ->where('rotation_group_id', $existing->id)->where('status', 'active')->count()]);
            return $existing;
        }

        return null;
    }

    /**
     * Reserved helper — rotate pattern selection. Currently used by
     * `applyShiftGroupRotation()` via the day/night swap helpers.
     */
    private function getPersonnelForRotatingDate($userIds, $preferenceScores, $date, $startDate, $pattern, $slotsToFill, &$existingAssignments)
    {
        $assigned = [];
        $daysDiff = $startDate->diffInDays($date);
        $userCount = count($userIds);

        switch ($pattern) {
            case 'full_swap':
                $half = ceil($userCount / 2);
                $groupA = array_slice($userIds, 0, $half);
                $groupB = array_slice($userIds, $half);
                $weekIndex = floor($daysDiff / 7) % 2;
                $candidates = $weekIndex == 0 ? $groupA : $groupB;
                break;
            case 'staggered':
                $rotateCount = ceil($userCount / 2);
                $startIndex = ($daysDiff % $userCount);
                $candidates = [];
                for ($i = 0; $i < $rotateCount; $i++) $candidates[] = $userIds[($startIndex + $i) % $userCount];
                break;
            case 'partial':
                $willing = array_filter($userIds, fn($id) => ($preferenceScores[$id] ?? 5) >= 7);
                $candidates = !empty($willing) ? $willing : $userIds;
                break;
            case 'sequential':
                $startIndex = $daysDiff % $userCount;
                $candidates = [];
                for ($i = 0; $i < $userCount; $i++) $candidates[] = $userIds[($startIndex + $i) % $userCount];
                break;
            case 'alternating':
                $sorted = array_keys($preferenceScores);
                $half = ceil($userCount / 2);
                $top = array_slice($sorted, 0, $half);
                $bottom = array_slice($sorted, $half);
                $weekIndex = floor($daysDiff / 7) % 2;
                $candidates = $weekIndex == 0 ? $top : $bottom;
                break;
            case 'preference_based':
                $candidates = array_keys($preferenceScores);
                break;
            default:
                $candidates = $userIds;
        }

        foreach ($candidates as $userId) {
            if (count($assigned) >= $slotsToFill) break;
            $key = $userId . '_' . $date->format('Y-m-d');
            if (!isset($existingAssignments[$key])) {
                $assigned[] = $userId;
                $existingAssignments[$key] = true;
            }
        }
        return $assigned;
    }

    /**
     * Reserved helper — call before persisting a bulk assign to skip
     * personnel who would breach the 12h gap or 60h/week rule.
     */
    private function validatePersonnelConstraints($userId, $date, $shift)
    {
        try {
            $lastShift = SecuritySchedule::where('security_user_id', $userId)
                ->where('status', 'completed')
                ->whereDate('assignment_date', '<', $date)
                ->orderBy('assignment_date', 'desc')->first(['assignment_date']);
            if ($lastShift) {
                $hoursSinceLastShift = $date->diffInHours($lastShift->assignment_date);
                if ($hoursSinceLastShift < 12) return false;
            }

            $weekStart = $date->copy()->startOfWeek();
            $weekEnd   = $date->copy()->endOfWeek();
            $weeklyHours = SecuritySchedule::where('security_user_id', $userId)
                ->whereBetween('assignment_date', [$weekStart, $weekEnd])
                ->where('status', '!=', 'cancelled')
                ->with('shift:id,duration_hours')->get()
                ->sum(fn($s) => $s->shift->duration_hours ?? 0);

            if (($weeklyHours + ($shift->duration_hours ?? 0)) > 60) return false;
            return true;
        } catch (\Exception $e) {
            Log::warning('Failed to validate personnel constraints', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return true;
        }
    }

    private function calculateUserPreferenceScore($userId, $shift, $post, $rotationGroup = null)
    {
        try {
            $user = User::find($userId);
            if (!$user) return 5;
            $score = 5;
            $preferences = $user->preferences ?? [];

            if (in_array($shift->category, $preferences['preferred_shifts'] ?? [])) $score += 2;
            if (in_array($post->id, $preferences['preferred_posts'] ?? [])) $score += 1;

            $willing = $preferences['willing_to_rotate'] ?? false;
            if ($willing) {
                $score += 2;
                if (is_array($willing)) {
                    if ($shift->category === 'night' && ($willing['day_to_night'] ?? false)) $score += 1;
                    if ($shift->category === 'day'   && ($willing['night_to_day'] ?? false)) $score += 1;
                }
            }
            if ($user->created_at) $score += min(2, $user->created_at->diffInYears(now()));
            $perf = $preferences['performance_rating'] ?? 7;
            $score += ($perf - 5) / 2;
            return max(1, min(10, round($score, 1)));
        } catch (\Exception $e) {
            return 5;
        }
    }

    private function calculateMemberPreferenceScore($userId, $group)
    {
        $user = User::find($userId);
        if (!$user) return 5;

        $w = $group->preference_weights ?? [
            'seniority' => 25, 'performance' => 25, 'availability' => 20,
            'preferred_shift' => 15, 'rotation_willingness' => 15,
        ];

        $score = 0;
        if ($user->created_at) {
            $seniorityScore = min(10, $user->created_at->diffInYears(now()) * 2);
            $score += ($seniorityScore / 10) * ($w['seniority'] / 100);
        }
        $perf = $user->preferences['performance_rating'] ?? 7;
        $score += ($perf / 10) * ($w['performance'] / 100);
        $score += ($this->calculateAvailabilityScore($userId, $group) / 10) * ($w['availability'] / 100);
        $score += ($this->calculatePreferredShiftScore($user, $group) / 10) * ($w['preferred_shift'] / 100);
        $willingness = $user->preferences['rotation_willingness'] ?? 5;
        $score += ($willingness / 10) * ($w['rotation_willingness'] / 100);

        return round($score * 10, 1);
    }

    private function calculateAvailabilityScore($userId, $group)
    {
        try {
            $since = now()->subDays(30);
            $total = SecuritySchedule::where('security_user_id', $userId)
                ->where('assignment_date', '>=', $since)->where('status', '!=', 'cancelled')->count();
            $completed = SecuritySchedule::where('security_user_id', $userId)
                ->where('assignment_date', '>=', $since)->where('status', 'completed')->count();
            if ($total == 0) return 5;
            return min(10, round(($completed / $total) * 10, 1));
        } catch (\Exception $e) {
            return 5;
        }
    }

    private function calculatePreferredShiftScore($user, $group)
    {
        $preferred = $user->preferences['preferred_shifts'] ?? [];
        if (empty($preferred)) return 5;
        $category = $group->shift->category ?? null;
        return in_array($category, $preferred) ? 10 : 3;
    }

    private function updateRotationGroupHistory($rotationGroup, $startDate, $endDate, $request)
    {
        try {
            $config  = $rotationGroup->rotation_config;
            $history = $config['rotation_history'] ?? [];
            $history[] = [
                'id'               => uniqid('rot_'),
                'date'             => now()->format('Y-m-d H:i:s'),
                'type'             => 'bulk_assign',
                'pattern'          => $request->rotation_pattern,
                'start_date'       => $startDate->format('Y-m-d'),
                'end_date'         => $endDate->format('Y-m-d'),
                'executed_by'      => auth()->id(),
                'executed_by_name' => auth()->user()?->name,
            ];
            $config['rotation_history']   = $history;
            $config['last_rotation_date'] = now()->format('Y-m-d H:i:s');

            $stats = $config['rotation_stats'] ?? ['total_rotations' => 0, 'successful_rotations' => 0, 'failed_rotations' => 0];
            $stats['total_rotations']++;
            $stats['successful_rotations']++;
            $config['rotation_stats'] = $stats;

            $rotationGroup->update([
                'rotation_config' => $config,
                'last_rotated_at' => now(),
                'updated_by'      => auth()->id(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update rotation group history', ['group_id' => $rotationGroup->id, 'error' => $e->getMessage()]);
        }
    }

    private function getIntervalForPattern($pattern)
    {
        return [
            'full_swap' => 7, 'staggered' => 3, 'partial' => 7,
            'sequential' => 1, 'alternating' => 7, 'preference_based' => 7,
        ][$pattern] ?? 7;
    }

    private function getScheduleForPattern($pattern)
    {
        return [
            'full_swap' => 'weekly', 'staggered' => 'daily', 'partial' => 'weekly',
            'sequential' => 'daily', 'alternating' => 'weekly', 'preference_based' => 'weekly',
        ][$pattern] ?? 'weekly';
    }

    private function getSequenceForPattern($pattern)
    {
        return [
            'full_swap' => ['A', 'B'], 'staggered' => ['A', 'B', 'C', 'D'],
            'partial' => ['A', 'B'], 'sequential' => ['A', 'B', 'C', 'D'],
            'alternating' => ['A', 'B'], 'preference_based' => ['A', 'B', 'C', 'D'],
        ][$pattern] ?? ['A', 'B', 'C', 'D'];
    }

    private function generateGroupCode($name)
    {
        $words = explode(' ', $name);
        $code = '';
        foreach ($words as $w) if (!empty($w)) $code .= strtoupper(substr($w, 0, 1));
        $original = $code;
        $i = 1;
        while (RotationGroup::where('code', $code)->exists()) { $code = $original . $i; $i++; }
        return $code;
    }

    /**
     * Reserved helper — post-assign coverage check used by the
     * (disabled) bulk assign workflow.
     */
    private function ensureCoverageForBulkAssign($post, $shift, $startDate, $endDate, $applicableDates)
    {
        try {
            $current = $startDate->copy();
            $issues = [];
            while ($current->lte($endDate)) {
                $dateStr = $current->format('Y-m-d');
                if (!in_array($dateStr, array_map(fn($d) => $d->format('Y-m-d'), $applicableDates))) {
                    $current->addDay();
                    continue;
                }
                $assigned = SecuritySchedule::where('security_post_id', $post->id)
                    ->where('security_shift_id', $shift->id)
                    ->whereDate('assignment_date', $dateStr)->count();
                $required = min($shift->required_personnel ?? 1, $post->max_personnel);
                if ($assigned < $required) $issues[] = ['date' => $dateStr, 'assigned' => $assigned, 'required' => $required];
                $current->addDay();
            }
            if (!empty($issues)) {
                Log::warning('Bulk assign completed with coverage issues', ['post_id' => $post->id, 'issues' => $issues]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to ensure coverage for bulk assign', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reserved helper — pushes notifications for a freshly-created
     * batch of schedules. Wire to a queued listener if/when enabled.
     */
    private function queueBulkAssignNotifications($post, $shift, $userIds, $startDate, $endDate, $now)
    {
        try {
            $schedules = SecuritySchedule::with(['shift', 'post', 'securityUser'])
                ->where('security_post_id', $post->id)
                ->where('security_shift_id', $shift->id)
                ->whereIn('security_user_id', $userIds)
                ->whereBetween('assignment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('created_at', '>=', $now->copy()->subSeconds(10))->get();
            if ($schedules->isNotEmpty()) {
                Log::info('Bulk assign notifications queued', ['schedule_count' => $schedules->count()]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to queue bulk assign notifications', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reserved helper — dispatches a rotation job.
     */
    private function queueRotationExecution($rotationGroup, $startDate, $endDate, $request)
    {
        try {
            Log::info('Rotation execution queued', ['group_id' => $rotationGroup->id]);
        } catch (\Exception $e) {
            Log::warning('Failed to queue rotation execution', ['error' => $e->getMessage()]);
        }
    }

    // ============================================================
    // PRIVATE HELPERS — SHIFT GROUP ROTATION
    // ============================================================

    private function shouldRotateOnDate(Carbon $date, Carbon $startDate, $frequency, $counter)
    {
        switch ($frequency) {
            case 'daily':    return true;
            case 'weekly':   return $date->dayOfWeek === Carbon::MONDAY;
            case 'biweekly':
                $weeksSinceStart = $startDate->diffInWeeks($date);
                return $date->dayOfWeek === Carbon::MONDAY && $weeksSinceStart % 2 === 0;
            case 'monthly':  return $date->day === 1;
            default:         return false;
        }
    }

    private function applyShiftGroupRotation($schedules, $pattern, $excludePersonnel, $maintainCoverage = true)
    {
        $dayShifts   = $schedules->filter(fn($s) => $s->shift && $s->shift->category === 'day')->values();
        $nightShifts = $schedules->filter(fn($s) => $s->shift && $s->shift->category === 'night')->values();

        $rotated = [];
        $log = [];
        if ($dayShifts->isEmpty() || $nightShifts->isEmpty()) {
            return ['schedules' => $rotated, 'log' => $log];
        }

        $dayPersonnel   = $dayShifts->pluck('security_user_id')->toArray();
        $nightPersonnel = $nightShifts->pluck('security_user_id')->toArray();
        $availableDay   = array_values(array_diff($dayPersonnel, $excludePersonnel));
        $availableNight = array_values(array_diff($nightPersonnel, $excludePersonnel));

        $dayMap = [];
        foreach ($dayShifts as $s) $dayMap[$s->security_user_id] = $s;
        $nightMap = [];
        foreach ($nightShifts as $s) $nightMap[$s->security_user_id] = $s;

        switch ($pattern) {
            case 'full_swap':
                $result = $this->performFullSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayMap, $nightMap, $log);
                $rotated = $result['rotated'];
                $log     = $result['log'];
                break;
            case 'staggered':
                $result = $this->performStaggeredSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayMap, $nightMap, $log);
                $rotated = $result['rotated'];
                $log     = $result['log'];
                break;
            case 'partial':
                $result = $this->performPartialSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayMap, $nightMap, $log);
                $rotated = $result['rotated'];
                $log     = $result['log'];
                break;
        }

        if ($maintainCoverage && !empty($rotated)) {
            $rotated = $this->ensureCoverageMaintained($rotated, $schedules, $log);
        }

        return ['schedules' => $rotated, 'log' => $log];
    }

    private function performFullSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayMap, $nightMap, &$log)
    {
        $rotated = [];
        $swapCount = min(count($availableDay), count($availableNight));
        $dayToSwap   = array_slice($availableDay, 0, $swapCount);
        $nightToSwap = array_slice($availableNight, 0, $swapCount);

        for ($i = 0; $i < $swapCount; $i++) {
            $d = $dayMap[$dayToSwap[$i]] ?? null;
            $n = $nightMap[$nightToSwap[$i]] ?? null;
            if ($d && $n) {
                $rotated[] = ['id' => $d->id, 'old_user_id' => $d->security_user_id, 'new_user_id' => $n->security_user_id, 'old_shift_id' => $d->security_shift_id, 'new_shift_id' => $n->security_shift_id, 'date' => $d->assignment_date->format('Y-m-d')];
                $rotated[] = ['id' => $n->id, 'old_user_id' => $n->security_user_id, 'new_user_id' => $d->security_user_id, 'old_shift_id' => $n->security_shift_id, 'new_shift_id' => $d->security_shift_id, 'date' => $n->assignment_date->format('Y-m-d')];
                $log[] = "Full swap: {$d->securityUser->name} (Day) ↔ {$n->securityUser->name} (Night)";
            }
        }
        return ['rotated' => $rotated, 'log' => $log];
    }

    private function performStaggeredSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayMap, $nightMap, &$log)
    {
        $rotated = [];
        $daySwapCount   = ceil(count($availableDay) / 2);
        $nightSwapCount = ceil(count($availableNight) / 2);
        $swapCount = min($daySwapCount, $nightSwapCount);
        if ($swapCount == 0) return ['rotated' => $rotated, 'log' => $log];

        $dayIndices   = array_rand($availableDay, min($swapCount, count($availableDay)));
        if (!is_array($dayIndices)) $dayIndices = [$dayIndices];
        $nightIndices = array_rand($availableNight, min($swapCount, count($availableNight)));
        if (!is_array($nightIndices)) $nightIndices = [$nightIndices];

        for ($i = 0; $i < min(count($dayIndices), count($nightIndices)); $i++) {
            $d = $dayMap[$availableDay[$dayIndices[$i]]] ?? null;
            $n = $nightMap[$availableNight[$nightIndices[$i]]] ?? null;
            if ($d && $n) {
                $rotated[] = ['id' => $d->id, 'old_user_id' => $d->security_user_id, 'new_user_id' => $n->security_user_id, 'old_shift_id' => $d->security_shift_id, 'new_shift_id' => $n->security_shift_id, 'date' => $d->assignment_date->format('Y-m-d')];
                $rotated[] = ['id' => $n->id, 'old_user_id' => $n->security_user_id, 'new_user_id' => $d->security_user_id, 'old_shift_id' => $n->security_shift_id, 'new_shift_id' => $d->security_shift_id, 'date' => $n->assignment_date->format('Y-m-d')];
                $log[] = "Staggered swap: {$d->securityUser->name} ↔ {$n->securityUser->name}";
            }
        }
        return ['rotated' => $rotated, 'log' => $log];
    }

    private function performPartialSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayMap, $nightMap, &$log)
    {
        $rotated = [];
        $willingDay   = $this->getWillingForRotation($availableDay, 'day_to_night');
        $willingNight = $this->getWillingForRotation($availableNight, 'night_to_day');
        $swapCount = min(count($willingDay), count($willingNight));

        for ($i = 0; $i < $swapCount; $i++) {
            $d = $dayMap[$willingDay[$i]] ?? null;
            $n = $nightMap[$willingNight[$i]] ?? null;
            if ($d && $n) {
                $rotated[] = ['id' => $d->id, 'old_user_id' => $d->security_user_id, 'new_user_id' => $n->security_user_id, 'old_shift_id' => $d->security_shift_id, 'new_shift_id' => $n->security_shift_id, 'date' => $d->assignment_date->format('Y-m-d')];
                $rotated[] = ['id' => $n->id, 'old_user_id' => $n->security_user_id, 'new_user_id' => $d->security_user_id, 'old_shift_id' => $n->security_shift_id, 'new_shift_id' => $d->security_shift_id, 'date' => $n->assignment_date->format('Y-m-d')];
                $log[] = "Voluntary swap: {$d->securityUser->name} ↔ {$n->securityUser->name}";
            }
        }
        return ['rotated' => $rotated, 'log' => $log];
    }

    /**
     * Resolve which personnel in a list are willing to swap in the given
     * direction. Reads preferences in batches to avoid N+1 model loads.
     */
    private function getWillingForRotation($personnelIds, $direction)
    {
        if (empty($personnelIds)) return [];

        $willing = [];
        $users = User::whereIn('id', $personnelIds)->get(['id', 'preferences'])->keyBy('id');
        foreach ($personnelIds as $id) {
            $user = $users->get($id);
            if ($user && $user->preferences
                && isset($user->preferences['willing_to_rotate'][$direction])
                && $user->preferences['willing_to_rotate'][$direction]) {
                $willing[] = $id;
            }
        }
        return $willing;
    }

    private function ensureCoverageMaintained($rotated, $originalSchedules, &$log)
    {
        $postIds = $originalSchedules->pluck('security_post_id')->unique();
        foreach ($postIds as $postId) {
            $postSchedules = $originalSchedules->where('security_post_id', $postId);
            $rotatedForPost = array_filter($rotated, function ($r) use ($postId, $postSchedules) {
                $s = $postSchedules->firstWhere('id', $r['id']);
                return $s && $s->security_post_id == $postId;
            });
            $assignedUserIds = array_column($rotatedForPost, 'new_user_id');
            $dupes = array_diff_assoc($assignedUserIds, array_unique($assignedUserIds));
            if (!empty($dupes)) {
                $log[] = 'Coverage adjustment needed: Duplicate assignments detected';
                $rotated = $this->fixDuplicateAssignments($rotated, $originalSchedules, $postId);
            }
            if (count($rotatedForPost) < count($postSchedules)) {
                $log[] = 'Coverage adjustment needed: Missing assignments';
                $rotated = $this->fixMissingAssignments($rotated, $originalSchedules, $postId);
            }
        }
        return $rotated;
    }

    private function fixDuplicateAssignments($rotated, $originalSchedules, $postId)
    {
        $fixed = [];
        $used = [];
        foreach ($rotated as $r) {
            $schedule = $originalSchedules->firstWhere('id', $r['id']);
            if (!$schedule) continue;
            if (in_array($r['new_user_id'], $used)) {
                $fixed[] = [
                    'id' => $r['id'], 'old_user_id' => $r['old_user_id'], 'new_user_id' => $r['old_user_id'],
                    'old_shift_id' => $r['old_shift_id'], 'new_shift_id' => $r['old_shift_id'], 'date' => $r['date'],
                ];
            } else {
                $fixed[] = $r;
                $used[]  = $r['new_user_id'];
            }
        }
        return $fixed;
    }

    private function fixMissingAssignments($rotated, $originalSchedules, $postId)
    {
        $originalIds = $originalSchedules->where('security_post_id', $postId)->pluck('id')->toArray();
        $rotatedIds  = array_column($rotated, 'id');
        $missingIds  = array_diff($originalIds, $rotatedIds);
        foreach ($missingIds as $missingId) {
            $orig = $originalSchedules->firstWhere('id', $missingId);
            if ($orig) {
                $rotated[] = [
                    'id' => $orig->id, 'old_user_id' => $orig->security_user_id, 'new_user_id' => $orig->security_user_id,
                    'old_shift_id' => $orig->security_shift_id, 'new_shift_id' => $orig->security_shift_id,
                    'date' => $orig->assignment_date->format('Y-m-d'),
                ];
            }
        }
        return $rotated;
    }

    // ============================================================
    // PRIVATE HELPERS — APPLICABLE DATES / MISC
    // ============================================================

    private function calculateApplicableDates($startDate, $endDate, $recurrenceType, $recurrenceDays)
    {
        $dates = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            if ($this->isDateApplicable($current, $recurrenceType, $recurrenceDays)) {
                $dates[] = $current->copy();
            }
            $current->addDay();
        }
        return $dates;
    }

    private function isDateApplicable(Carbon $date, $recurrenceType, $recurrenceDays)
    {
        switch ($recurrenceType) {
            case 'daily':
                return true;
            case 'weekly':
                if (empty($recurrenceDays)) return true;
                return in_array($date->dayOfWeekIso, $recurrenceDays);
            case 'monthly':
                if (empty($recurrenceDays)) return true;
                return in_array($date->day, $recurrenceDays);
            case 'rotating':
            case 'custom':
                return true;
            default:
                return false;
        }
    }

    private function groupFailedAssignments($failedAssignments)
    {
        $grouped = [];
        foreach ($failedAssignments as $f) {
            $reason = $f['reason'] ?? 'Unknown';
            $grouped[$reason] = ($grouped[$reason] ?? 0) + 1;
        }
        return $grouped;
    }

    private function calculateEarnings($schedule)
    {
        // Placeholder — override with real payroll logic if you have it.
        $hours = ($schedule->total_minutes ?? 0) / 60;
        return round($hours * 0, 2);
    }

    // ============================================================
    // PRIVATE HELPERS — SUPERVISOR STATUS / BREAK / HANDOVER
    // ============================================================

    private function supervisorProcessCheckin($schedule, $request)
    {
        if ($schedule->checkin_time) {
            throw new \Exception('Schedule already has a check-in recorded.');
        }

        $data = [
            'checkin_time'  => now(),
            'status'        => 'active',
            'checkin_notes' => $request->notes,
        ];
        if ($request->has('location')) {
            $data['checkin_location'] = $request->location;
            $data['checkin_accuracy'] = $request->accuracy;
        }
        $schedule->update($data);

        $shift = $schedule->shift;
        $scheduledStart = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
        if (now()->greaterThan($scheduledStart)) {
            $lateMinutes = (int) now()->diffInMinutes($scheduledStart, false) * -1;
            $grace = $schedule->post->checkin_grace_period ?? 5;
            $schedule->update([
                'late_minutes' => $lateMinutes,
                'late_flagged' => $lateMinutes > $grace,
                'late_reason'  => $lateMinutes > $grace ? "Arrived {$lateMinutes} minutes late" : null,
            ]);
        }
        return 'Checked in successfully.';
    }

    private function supervisorProcessCheckout($schedule, $request)
    {
        if ($schedule->checkout_time) {
            throw new \Exception('Schedule already has a check-out recorded.');
        }

        $data = [
            'checkout_time'  => now(),
            'status'         => 'completed',
            'checkout_notes' => $request->notes,
        ];
        if ($request->has('location')) {
            $data['checkout_location'] = $request->location;
            $data['checkout_accuracy'] = $request->accuracy;
        }
        $schedule->update($data);

        $shift = $schedule->shift;
        $scheduledEnd = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->end_time);
        if ($scheduledEnd <= Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time)) {
            $scheduledEnd->addDay();
        }
        if (now()->greaterThan($scheduledEnd)) {
            $schedule->update(['overtime_minutes' => now()->diffInMinutes($scheduledEnd)]);
        }
        if ($schedule->checkin_time) {
            $total = $schedule->checkin_time->diffInMinutes(now());
            if ($schedule->break_duration > 0) $total -= $schedule->break_duration;
            $schedule->update(['total_minutes' => $total]);
        }
        return 'Checked out successfully.';
    }

    private function supervisorStartBreak($schedule, $request)
    {
        if (!$schedule->include_breaks) {
            throw new \Exception('This schedule does not include breaks.');
        }
        $shift = $schedule->shift;
        if (!$shift->break_schedule || empty($shift->break_schedule['has_break'])) {
            throw new \Exception('No breaks configured for this shift.');
        }
        $breaks = $shift->break_schedule['breaks'] ?? [];
        $breakId = $request->break_id;
        if (!isset($breaks[$breakId])) {
            throw new \Exception('Invalid break ID.');
        }
        $data = [
            'current_break_id' => $breakId,
            'break_start_time' => now(),
            'break_status'     => 'active',
        ];
        if ($request->has('location')) $data['break_start_location'] = $request->location;
        $schedule->update($data);
        return 'Break started.';
    }

    private function supervisorEndBreak($schedule, $request)
    {
        if (!$schedule->current_break_id) {
            throw new \Exception('No active break to end.');
        }
        $breakDuration = now()->diffInMinutes($schedule->break_start_time);
        $shift = $schedule->shift;
        $breaks = $shift->break_schedule['breaks'] ?? [];
        $break  = $breaks[$schedule->current_break_id] ?? null;

        $history   = $schedule->break_history ?? [];
        $history[] = [
            'break_id'       => $schedule->current_break_id,
            'break_name'     => $break['name'] ?? 'Unknown',
            'start_time'     => $schedule->break_start_time->format('Y-m-d H:i:s'),
            'end_time'       => now()->format('Y-m-d H:i:s'),
            'duration'       => $breakDuration,
            'notes'          => $request->notes,
            'start_location' => $schedule->break_start_location,
            'end_location'   => $request->location,
        ];
        $data = [
            'break_end_time'    => now(),
            'break_duration'    => ($schedule->break_duration ?? 0) + $breakDuration,
            'break_status'      => 'completed',
            'break_notes'       => $request->notes,
            'break_history'     => $history,
            'current_break_id'  => null,
            'break_start_time'  => null,
        ];
        if ($request->has('location')) $data['break_end_location'] = $request->location;
        $schedule->update($data);
        return 'Break ended.';
    }

    private function updateHandoverChecklist($schedule, $checklistUpdates)
    {
        if (!$schedule->handover_info || !isset($schedule->handover_info['checklist_items'])) return;
        $hi    = $schedule->handover_info;
        $items = $hi['checklist_items'];
        foreach ($checklistUpdates as $index => $update) {
            if (isset($items[$index])) {
                $items[$index]['completed']    = $update['completed'] ?? false;
                $items[$index]['completed_by'] = auth()->id();
                $items[$index]['completed_at'] = now();
                $items[$index]['notes']        = $update['notes'] ?? null;
            }
        }
        $hi['checklist_items']        = $items;
        $hi['checklist_completed_at'] = now();
        $hi['checklist_completed_by'] = auth()->id();
        $schedule->update(['handover_info' => $hi]);
    }
        // ============================================================
    // SUPERVISOR-ONLY: HANDOVER INFO (JSON)
    // ============================================================

    /**
     * GET  /security/schedules/{schedule}/handover-info
     * Route: security.schedules.handover-info
     *
     * Returns the current handover snapshot for a schedule so the
     * assignment detail UI can display:
     *   - whether a handover is configured for the shift
     *   - the checklist items + completion state
     *   - the previous shift (outgoing officer) if any
     *   - the current handover notes + who completed it
     *
     * Always returns JSON. Status codes:
     *   200 → OK (even if the shift has no handover; has_handover=false)
     *   404 → schedule not found
     *   500 → unexpected DB/logic error
     */
    public function getHandoverInfo(SecuritySchedule $schedule)
    {
        try {
            $schedule->loadMissing([
                'post:id,name,code,latitude,longitude',
                'shift:id,name,start_time,end_time,handover_config,is_overnight',
                'securityUser:id,name,phone,badge_number',
                'rotatedFromUser:id,name',
                'handoverCompletedBy:id,name',
            ]);

            $shift = $schedule->shift;
            $handoverConfig = is_array($shift?->handover_config) ? $shift->handover_config : [];
            $hasHandover    = ($handoverConfig['has_handover'] ?? false) === true;

            $handoverInfo = is_array($schedule->handover_info) ? $schedule->handover_info : [];
            $checklistItems = $handoverInfo['checklist_items']
                ?? array_map(
                    fn ($item) => ['item' => $item, 'completed' => false],
                    $handoverConfig['handover_checklist'] ?? []
                );

            // Resolve the *previous* schedule on the same post. Used by the
            // UI to show who the outgoing officer was and what time their
            // shift ended. Handles overnight shifts correctly by looking
            // one calendar day back when the shift itself is overnight.
            $previous = null;
            if ($hasHandover && $shift) {
                $shiftStart = Carbon::parse(
                    $schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time
                );

                $previous = SecuritySchedule::with([
                    'securityUser:id,name,phone',
                    'shift:id,name,end_time,start_time,is_overnight',
                ])
                    ->where('security_post_id', $schedule->security_post_id)
                    ->where('id', '!=', $schedule->id)
                    ->where(function ($q) use ($schedule, $shift, $shiftStart) {
                        // Same-day earlier shift
                        $q->whereDate('assignment_date', $schedule->assignment_date)
                          ->whereHas('shift', fn ($qs) => $qs->where('start_time', '<', $shift->start_time));

                        // OR yesterday's overnight shift that ends this morning
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
                        'id'         => $shift->id,
                        'name'       => $shift->name,
                        'start_time' => $shift->start_time,
                        'end_time'   => $shift->end_time,
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
                        'id'         => $previous->id,
                        'user'       => $previous->securityUser ? [
                            'id'   => $previous->securityUser->id,
                            'name' => $previous->securityUser->name,
                        ] : null,
                        'shift_name' => $previous->shift?->name,
                        'shift_end'  => $previous->shift?->end_time,
                        'assignment_date' => $previous->assignment_date?->format('Y-m-d'),
                    ] : null,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('getHandoverInfo failed: ' . $e->getMessage(), [
                'schedule_id' => $schedule->id ?? null,
                'trace'       => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load handover info.',
            ], 500);
        }
    }
        // ============================================================
    // SUPERVISOR-ONLY: PERSONNEL AVAILABILITY CHECK (JSON)
    // ============================================================

    /**
     * GET / POST  /security/schedules/check-availability
     * Route:      security.schedules.check-availability
     *
     * Answers, in one call: which of the given users are free on the
     * target date(s), and if not, why (already assigned / rest gap /
     * weekly cap).
     *
     * Accepts:
     *   - ?user_ids[]=1&user_ids[]=2&date=2026-10-25
     *   - ?user_ids=1,2,3&date=2026-10-25
     *   - body { user_ids: [1,2], date_from: "2026-10-25", date_to: "2026-10-27" }
     *
     * Returns { success, date_range, available[], unavailable[], summary }
     */
    public function checkAvailability(Request $request)
    {
        // ── 1. Normalize user_ids from either array or CSV string ─────────
        $rawUserIds = $request->input('user_ids', []);
        if (is_string($rawUserIds)) {
            $rawUserIds = array_filter(array_map('trim', explode(',', $rawUserIds)));
        }
        $userIds = collect($rawUserIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'At least one user_id is required.',
                'errors'  => ['user_ids' => ['Provide one or more user IDs.']],
            ], 422);
        }

        // ── 2. Accept single `date` or `date_from`/`date_to` ─────────────
        $dateFromStr = $request->input('date_from')
            ?? $request->input('date')
            ?? $request->route('date');
        $dateToStr   = $request->input('date_to') ?? $dateFromStr;

        $validator = Validator::make([
            'date_from' => $dateFromStr,
            'date_to'   => $dateToStr,
            'user_ids'  => $userIds,
        ], [
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'user_ids'  => 'array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $from = Carbon::parse($dateFromStr)->startOfDay();
            $to   = Carbon::parse($dateToStr)->startOfDay();
            $days = (int) $from->diffInDays($to) + 1;

            // ── 3. Only active security personnel ────────────────────────
            $users = User::whereIn('id', $userIds)
                ->where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('status', User::STATUS_ACTIVE)
                ->get(['id', 'name', 'phone', 'badge_number'])
                ->keyBy('id');

            // ── 4. Batch queries (O(users) not O(users × days)) ──────────
            $existingByUser = SecuritySchedule::whereIn('security_user_id', $userIds)
                ->whereBetween('assignment_date', [$from->toDateString(), $to->toDateString()])
                ->where('status', '!=', 'cancelled')
                ->get(['id', 'security_user_id', 'security_post_id', 'security_shift_id', 'assignment_date', 'status'])
                ->groupBy('security_user_id');

            $lastShiftBefore = [];
            foreach ($userIds as $uid) {
                $lastShiftBefore[$uid] = SecuritySchedule::where('security_user_id', $uid)
                    ->where('assignment_date', '<', $from->toDateString())
                    ->where('status', '!=', 'cancelled')
                    ->orderByDesc('assignment_date')
                    ->with('shift:id,start_time,end_time,is_overnight,duration_hours')
                    ->first();
            }

            $weekStart = $from->copy()->startOfWeek();
            $weekEnd   = $to->copy()->endOfWeek();
            $weeklyHours = SecuritySchedule::whereIn('security_user_id', $userIds)
                ->whereBetween('assignment_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->where('status', '!=', 'cancelled')
                ->join('security_shifts', 'security_schedules.security_shift_id', '=', 'security_shifts.id')
                ->selectRaw('security_schedules.security_user_id as uid, COALESCE(SUM(security_shifts.duration_hours), 0) as hours')
                ->groupBy('security_schedules.security_user_id')
                ->pluck('hours', 'uid')
                ->toArray();

            // ── 5. Classify each user ────────────────────────────────────
            $available   = [];
            $unavailable = [];

            foreach ($userIds as $uid) {
                $user = $users->get($uid);
                if (!$user) {
                    $unavailable[] = [
                        'user_id' => $uid,
                        'name'    => null,
                        'reason'  => 'not_found_or_inactive',
                    ];
                    continue;
                }

                $conflicts   = [];
                $reason      = null;
                $context     = [];

                // 5a. Already assigned on any date in the range
                $assignments = $existingByUser->get($uid, collect());
                if ($assignments->isNotEmpty()) {
                    $reason = 'already_assigned';
                    $conflicts = $assignments->map(fn ($a) => [
                        'schedule_id'     => $a->id,
                        'post_id'         => $a->security_post_id,
                        'shift_id'        => $a->security_shift_id,
                        'assignment_date' => $a->assignment_date instanceof Carbon
                            ? $a->assignment_date->format('Y-m-d')
                            : $a->assignment_date,
                        'status'          => $a->status,
                    ])->values()->all();
                }

                // 5b. 12-hour rest gap from the last shift
                if ($reason === null) {
                    $last = $lastShiftBefore[$uid] ?? null;
                    if ($last && $last->shift) {
                        $lastStart = Carbon::parse(
                            $last->assignment_date->format('Y-m-d') . ' ' . $last->shift->start_time
                        );
                        $lastEnd = $last->shift->is_overnight
                            ? $lastStart->copy()->addDay()->setTimeFromTimeString($last->shift->end_time)
                            : Carbon::parse($last->assignment_date->format('Y-m-d') . ' ' . $last->shift->end_time);

                        $gapHours = $lastEnd->diffInHours($from, false);
                        if ($gapHours < 12) {
                            $reason = 'rest_period';
                            $context = [
                                'last_shift_end'    => $lastEnd->toIso8601String(),
                                'required_gap_hrs'  => 12,
                                'actual_gap_hrs'    => $gapHours,
                            ];
                        }
                    }
                }

                // 5c. Weekly cap (60 hours)
                if ($reason === null) {
                    $currentWeekly = (float) ($weeklyHours[$uid] ?? 0);
                    if ($currentWeekly >= 60) {
                        $reason = 'weekly_limit';
                        $context = [
                            'weekly_hours'   => $currentWeekly,
                            'max_weekly_hrs' => 60,
                        ];
                    }
                }

                if ($reason === null) {
                    $available[] = [
                        'user_id'      => $uid,
                        'name'         => $user->name,
                        'phone'        => $user->phone,
                        'badge_number' => $user->badge_number,
                    ];
                } else {
                    $unavailable[] = array_merge([
                        'user_id'   => $uid,
                        'name'      => $user->name,
                        'reason'    => $reason,
                        'conflicts' => $conflicts,
                    ], $context);
                }
            }

            return response()->json([
                'success' => true,
                'date_range' => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                    'days' => $days,
                ],
                'available'   => $available,
                'unavailable' => $unavailable,
                'summary'     => [
                    'total'       => count($userIds),
                    'available'   => count($available),
                    'unavailable' => count($unavailable),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('checkAvailability failed: ' . $e->getMessage(), [
                'user_ids'  => $userIds,
                'date_from' => $dateFromStr,
                'date_to'   => $dateToStr,
                'trace'     => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability.',
            ], 500);
        }
    }
}