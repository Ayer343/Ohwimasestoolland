<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityShift;
use App\Models\SecurityPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SecurityShiftController extends Controller
{
    public function index(Request $request)
{
    $query = SecurityShift::query();

    if ($request->filled('search')) {
        $s = $request->search;
        $query->where(function ($q) use ($s) {
            $q->where('name', 'LIKE', "%{$s}%")
              ->orWhere('code', 'LIKE', "%{$s}%")
              ->orWhere('description', 'LIKE', "%{$s}%");
        });
    }
    if ($request->filled('status')) {
        $query->where('is_active', $request->status === 'active');
    }
    if ($request->filled('rotation_type')) {
        $query->where('rotation_type', $request->rotation_type);
    }
    if ($request->filled('personnel')) {
        match ($request->personnel) {
            '1' => $query->where('required_personnel', 1),
            '2' => $query->where('required_personnel', 2),
            '3' => $query->where('required_personnel', '>=', 3),
            default => null,
        };
    }
    if ($request->filled('category')) {
        $query->where('category', $request->category);
    }

    $sortBy = $request->get('sort_by', 'start_time');
    $sortOrder = $request->get('sort_order', 'asc');
    $query->orderBy($sortBy, $sortOrder)->withCount('schedules');

    $shifts = $query->paginate(20)->withQueryString();

    // --- Individual stats (view compatibility) ---
    $totalShifts       = SecurityShift::count();
    $activeShifts      = SecurityShift::where('is_active', true)->count();
    $activeToday       = SecurityShift::active()->forToday()->count();
    $averageDuration   = SecurityShift::avg('duration_hours') ?? 0;
    $shortestDuration  = SecurityShift::min('duration_hours') ?? 0;
    $totalPersonnel    = SecurityShift::sum('required_personnel') ?? 0;
    $dayShifts         = SecurityShift::where('category', 'day')->count();
    $nightShifts       = SecurityShift::where('category', 'night')->count();
    $rotatingShifts    = SecurityShift::where('rotation_type', 'rotating')->count();

    // --- Also pass a stats array for any newer view code that uses $stats ---
    $stats = [
        'total'            => $totalShifts,
        'active'           => $activeShifts,
        'active_today'     => $activeToday,
        'average_duration' => $averageDuration,
        'shortest_duration'=> $shortestDuration,
        'total_personnel'  => $totalPersonnel,
        'day_shifts'       => $dayShifts,
        'night_shifts'     => $nightShifts,
        'rotating_shifts'  => $rotatingShifts,
    ];

    $shiftCategories = [
        'day' => 'Day Shifts',
        'night' => 'Night Shifts',
        'evening' => 'Evening Shifts',
        'special' => 'Special Shifts',
        'holiday' => 'Holiday Shifts',
    ];
    $rotationTypes = ['fixed' => 'Fixed Shift', 'rotating' => 'Rotating Shift'];

    return view('admin.security-shifts.index', compact(
        'shifts',
        'stats',
        'shiftCategories',
        'rotationTypes',
        'totalShifts',
        'activeShifts',
        'activeToday',
        'averageDuration',
        'shortestDuration',
        'totalPersonnel',
        'dayShifts',
        'nightShifts',
        'rotatingShifts'
    ));
}

    public function create()
    {
        $dayTypes = [
            'all_days' => 'All Days',
            'weekday' => 'Weekdays',
            'weekend' => 'Weekends',
            'custom' => 'Custom Days',
        ];
        $daysOfWeek = [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
        ];
        $shiftCategories = [
            'day' => 'Day Shift (06:00 - 18:00)',
            'night' => 'Night Shift (18:00 - 06:00)',
            'evening' => 'Evening Shift (14:00 - 22:00)',
            'special' => 'Special Shift',
            'holiday' => 'Holiday Shift',
        ];
        $rotationTypes = [
            'fixed' => 'Fixed (Same shift always)',
            'rotating' => 'Rotating (Changes periodically)',
        ];
        $rotationSequences = [
            'morning_evening' => 'Morning → Evening → Night → Off',
            'evening_morning' => 'Evening → Morning → Night → Off',
            'night_morning' => 'Night → Morning → Evening → Off',
        ];

        return view('admin.security-shifts.create', compact(
            'dayTypes', 'daysOfWeek', 'shiftCategories', 'rotationTypes', 'rotationSequences'
        ));
    }

    public function store(Request $request)
    {
        $validator = $this->validateShift($request);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $data = $this->prepareShiftData($request);

            if (in_array($data['day_type'], ['all_days', 'weekday', 'weekend'], true)) {
                $data['applicable_days'] = null;
            } elseif ($data['day_type'] === 'custom') {
                $data['applicable_days'] = $request->applicable_days;
            }

            if ($data['rotation_type'] === 'rotating') {
                $data['rotation_config'] = [
                    'rotation_days' => (int) ($request->rotation_days ?? 7),
                    'rotation_sequence' => $request->rotation_sequence ?? 'morning_evening',
                    'last_rotation_date' => null,
                    'current_sequence_index' => 0,
                ];
            } else {
                $data['rotation_config'] = null;
            }

            $data['break_schedule'] = $this->buildBreakSchedule($request);
            $data['handover_config'] = $this->buildHandoverConfig($request);

            $shift = SecurityShift::create($data);

            Log::info('Security shift created', [
                'shift_id' => $shift->id, 'name' => $shift->name, 'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('admin.security-shifts.index')
                ->with('success', 'Security shift created successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('DB error creating shift: ' . $e->getMessage());
            return back()->with('error', $this->friendlyDbError($e))->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating shift: ' . $e->getMessage());
            return back()->with('error', 'Failed to create shift: ' . $e->getMessage())->withInput();
        }
    }

    public function show(SecurityShift $securityShift)
    {
        $securityShift->loadCount('schedules');
        $securityShift->load([
            'schedules' => fn ($q) => $q->whereDate('assignment_date', '>=', today())
                ->orderBy('assignment_date')->limit(10)->with(['post', 'securityUser']),
        ]);

        $shiftStats = [
            'total_assignments' => $securityShift->schedules()->count(),
            'completed_shifts' => $securityShift->schedules()->where('status', 'completed')->count(),
            'active_assignments' => $securityShift->schedules()
                ->whereDate('assignment_date', '>=', today())
                ->whereIn('status', ['scheduled', 'active'])->count(),
            'attendance_rate' => $this->calculateShiftAttendanceRate($securityShift),
            'average_checkin_time' => $this->calculateAverageCheckinTime($securityShift),
        ];

        $upcomingRotations = $securityShift->rotation_type === 'rotating'
            ? $this->getUpcomingRotations($securityShift)
            : [];

        return view('admin.security-shifts.show', compact('securityShift', 'shiftStats', 'upcomingRotations'));
    }

    public function edit(SecurityShift $securityShift)
    {
        $dayTypes = ['all_days' => 'All Days', 'weekday' => 'Weekdays', 'weekend' => 'Weekends', 'custom' => 'Custom Days'];
        $daysOfWeek = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $shiftCategories = [
            'day' => 'Day Shift', 'night' => 'Night Shift', 'evening' => 'Evening Shift',
            'special' => 'Special Shift', 'holiday' => 'Holiday Shift',
        ];
        $rotationTypes = ['fixed' => 'Fixed', 'rotating' => 'Rotating'];
        $rotationSequences = [
            'morning_evening' => 'Morning → Evening → Night → Off',
            'evening_morning' => 'Evening → Morning → Night → Off',
            'night_morning' => 'Night → Morning → Evening → Off',
        ];

        return view('admin.security-shifts.edit', compact(
            'securityShift', 'dayTypes', 'daysOfWeek', 'shiftCategories', 'rotationTypes', 'rotationSequences'
        ));
    }

    public function update(Request $request, SecurityShift $securityShift)
    {
        $validator = $this->validateShift($request, $securityShift->id);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $data = $this->prepareShiftData($request);

            if (in_array($data['day_type'], ['all_days', 'weekday', 'weekend'], true)) {
                $data['applicable_days'] = null;
            } elseif ($data['day_type'] === 'custom') {
                $data['applicable_days'] = $request->applicable_days;
            }

            if ($data['rotation_type'] === 'rotating') {
                $data['rotation_config'] = array_merge(
                    $securityShift->rotation_config ?? [],
                    [
                        'rotation_days' => (int) ($request->rotation_days ?? 7),
                        'rotation_sequence' => $request->rotation_sequence ?? 'morning_evening',
                    ]
                );
            } else {
                $data['rotation_config'] = null;
            }

            $data['break_schedule'] = $this->buildBreakSchedule($request);
            $data['handover_config'] = $this->buildHandoverConfig($request);

            $securityShift->update($data);

            Log::info('Security shift updated', [
                'shift_id' => $securityShift->id, 'updated_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('admin.security-shifts.show', $securityShift)
                ->with('success', 'Security shift updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating shift: ' . $e->getMessage());
            return back()->with('error', 'Failed to update shift: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(SecurityShift $securityShift)
    {
        try {
            $securityShift->delete();
            Log::info('Security shift soft deleted', [
                'shift_id' => $securityShift->id, 'deleted_by' => auth()->id(),
            ]);
            return redirect()->route('admin.security-shifts.index')
                ->with('success', 'Security shift moved to trash successfully.');
        } catch (\Exception $e) {
            Log::error('Error soft deleting shift: ' . $e->getMessage());
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
 * Shift-specific trash (NOT schedules).
 */
public function trash(Request $request)
{
    $query = SecurityShift::onlyTrashed()->orderBy('deleted_at', 'desc');

    if ($request->filled('search')) {
        $s = $request->search;
        $query->where(function ($q) use ($s) {
            $q->where('name', 'LIKE', "%{$s}%")
              ->orWhere('code', 'LIKE', "%{$s}%")
              ->orWhere('description', 'LIKE', "%{$s}%");
        });
    }
    if ($request->filled('category')) {
        $query->where('category', $request->category);
    }
    if ($request->filled('rotation_type')) {
        $query->where('rotation_type', $request->rotation_type);
    }

    $trashedShifts = $query->paginate(20)->withQueryString();

    // ---- Shift-specific stats ----
    $trashStats = [
        'total_trashed' => SecurityShift::onlyTrashed()->count(),
        'recently_deleted' => SecurityShift::onlyTrashed()
            ->where('deleted_at', '>=', now()->subDays(7))->count(),
        'trashed_by_period' => [
            'today' => SecurityShift::onlyTrashed()->whereDate('deleted_at', today())->count(),
            'this_week' => SecurityShift::onlyTrashed()
                ->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => SecurityShift::onlyTrashed()
                ->whereMonth('deleted_at', now()->month)->count(),
        ],
        'by_category' => SecurityShift::onlyTrashed()
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray(),
        'oldest_trashed' => SecurityShift::onlyTrashed()
            ->orderBy('deleted_at', 'asc')
            ->first()?->deleted_at?->format('M j, Y') ?? 'N/A',
    ];

    // ---- Filters required by the view (even if blank for this page) ----
    // The blade view was originally shared with the schedule trash page,
    // so it expects these. Populate with real data so filters work.
    $securityPosts = \App\Models\SecurityPost::orderBy('name')->get(['id', 'name', 'code']);
    $securityShifts = SecurityShift::withTrashed()->orderBy('name')->get(['id', 'name', 'code']);
    $securityPersonnel = \App\Models\User::where('type', \App\Models\User::TYPE_SECURITY_PERSONNEL)
        ->orderBy('name')
        ->get(['id', 'name']);

    // The view was written against `$trashedSchedules`. Provide an alias
    // so the same blade can render without modification.
    $trashedSchedules = $trashedShifts;

    return view('admin.security-shifts.trash', compact(
        'trashedShifts',
        'trashedSchedules',       // alias for the existing view
        'trashStats',
        'securityPosts',
        'securityShifts',
        'securityPersonnel',
        'request'
    ));
}

    public function restore($id)
    {
        DB::beginTransaction();
        try {
            $shift = SecurityShift::onlyTrashed()->findOrFail($id);

            // Prevent duplicate code conflict
            if (SecurityShift::where('code', $shift->code)->where('id', '!=', $shift->id)->exists()) {
                DB::rollBack();
                return back()->with('error', 'Cannot restore: a shift with the same code already exists.');
            }

            $shift->restore();
            DB::commit();

            Log::info('Security shift restored', ['shift_id' => $id, 'restored_by' => auth()->id()]);
            return redirect()->route('admin.security-shifts.trash')
                ->with('success', 'Shift restored successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Restore shift failed: ' . $e->getMessage());
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        DB::beginTransaction();
        try {
            $shift = SecurityShift::onlyTrashed()->findOrFail($id);

            // Block if schedules still reference this shift
            if (\App\Models\SecuritySchedule::withTrashed()->where('security_shift_id', $shift->id)->exists()) {
                DB::rollBack();
                return back()->with('error', 'Cannot permanently delete: schedules still reference this shift.');
            }

            $shift->forceDelete();
            DB::commit();

            Log::warning('Security shift permanently deleted', ['shift_id' => $id, 'by' => auth()->id()]);
            return redirect()->route('admin.security-shifts.trash')
                ->with('success', 'Shift permanently deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Force delete shift failed: ' . $e->getMessage());
            return back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    /**
     * FIXED: bulkAction now correctly targets shifts for restore/force_delete.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete,export,restore,force_delete',
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        if ($request->action === 'export') {
            return $this->exportSelected($request->ids);
        }

        DB::beginTransaction();
        try {
            $message = match ($request->action) {
                'activate' => (function () use ($request) {
                    $n = SecurityShift::whereIn('id', $request->ids)->update(['is_active' => true]);
                    return "{$n} shift(s) activated.";
                })(),
                'deactivate' => (function () use ($request) {
                    $n = SecurityShift::whereIn('id', $request->ids)->update(['is_active' => false]);
                    return "{$n} shift(s) deactivated.";
                })(),
                'delete' => (function () use ($request) {
                    $n = SecurityShift::whereIn('id', $request->ids)->delete();
                    return "{$n} shift(s) moved to trash.";
                })(),
                'restore' => (function () use ($request) {
                    $restored = 0;
                    foreach (SecurityShift::onlyTrashed()->whereIn('id', $request->ids)->get() as $shift) {
                        if (!SecurityShift::where('code', $shift->code)->where('id', '!=', $shift->id)->exists()) {
                            $shift->restore();
                            $restored++;
                        }
                    }
                    return "{$restored} shift(s) restored.";
                })(),
                'force_delete' => (function () use ($request) {
                    $deleted = 0;
                    foreach (SecurityShift::onlyTrashed()->whereIn('id', $request->ids)->get() as $shift) {
                        if (!\App\Models\SecuritySchedule::withTrashed()
                                ->where('security_shift_id', $shift->id)->exists()) {
                            $shift->forceDelete();
                            $deleted++;
                        }
                    }
                    return "{$deleted} shift(s) permanently deleted.";
                })(),
            };

            DB::commit();
            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Shift bulkAction failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function toggleStatus(SecurityShift $securityShift)
    {
        try {
            $securityShift->update(['is_active' => !$securityShift->is_active]);
            return response()->json([
                'success' => true,
                'message' => 'Shift status updated.',
                'is_active' => $securityShift->fresh()->is_active,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getShiftsForSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'post_id' => 'nullable|exists:security_posts,id',
            'personnel_count' => 'nullable|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $date = Carbon::parse($request->date);
        $dayOfWeek = $date->dayOfWeekIso;

        $query = SecurityShift::active();
        if ($request->filled('personnel_count')) {
            $query->where('required_personnel', '<=', $request->personnel_count);
        }

        $shifts = $query->get()->map(function (SecurityShift $shift) use ($date, $dayOfWeek) {
            return [
                'id' => $shift->id,
                'name' => $shift->name,
                'code' => $shift->code,
                'category' => $shift->category,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'duration_hours' => $shift->duration_hours,
                'required_personnel' => $shift->required_personnel,
                'is_overnight' => $shift->is_overnight,
                'rotation_type' => $shift->rotation_type,
                'break_schedule' => $shift->break_schedule,
                'handover_config' => $shift->handover_config,
                'is_applicable' => $shift->isApplicableOnDay($dayOfWeek),
                'time_range' => $shift->getTimeRange(),
                'breaks_formatted' => $this->formatBreaks($shift->break_schedule),
            ];
        });

        return response()->json([
            'success' => true,
            'shifts' => $shifts,
            'date' => $date->toDateString(),
            'day_of_week' => $date->format('l'),
            'total_shifts' => $shifts->count(),
        ]);
    }

    public function checkShiftsInUse(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:security_shifts,id']);
        $used = SecurityShift::whereIn('id', $request->ids)->has('schedules')->get(['id', 'name', 'code']);
        return response()->json(['in_use_count' => $used->count(), 'used_shifts' => $used]);
    }

    public function getRecommendedShifts(Request $request)
    {
        $request->validate(['post_id' => 'required|exists:security_posts,id', 'date' => 'required|date']);
        $post = SecurityPost::find($request->post_id);

        $common = SecurityShift::active()
            ->whereHas('schedules', fn ($q) => $q->where('security_post_id', $post->id)
                ->whereDate('assignment_date', '>=', now()->subDays(30)))
            ->withCount(['schedules' => fn ($q) => $q->where('security_post_id', $post->id)])
            ->orderByDesc('schedules_count')
            ->limit(5)
            ->get();

        $matching = SecurityShift::active()
            ->where('required_personnel', '<=', $post->max_personnel)
            ->get();

        return response()->json([
            'success' => true,
            'common_shifts' => $common,
            'matching_shifts' => $matching,
            'post' => ['id' => $post->id, 'name' => $post->name, 'type' => $post->type, 'max_personnel' => $post->max_personnel],
        ]);
    }

    public function processRotation(Request $request)
    {
        $request->validate(['shift_id' => 'required|exists:security_shifts,id', 'date' => 'required|date']);
        $shift = SecurityShift::findOrFail($request->shift_id);

        if ($shift->rotation_type !== 'rotating') {
            return response()->json(['success' => false, 'message' => 'Shift is not rotating.'], 400);
        }

        DB::beginTransaction();
        try {
            $date = Carbon::parse($request->date);
            $config = $shift->rotation_config ?? [];
            $sequence = $this->getRotationSequence($config['rotation_sequence'] ?? 'morning_evening');
            $currentIndex = (int) ($config['current_sequence_index'] ?? 0);
            $newIndex = ($currentIndex + 1) % count($sequence);

            $config['current_sequence_index'] = $newIndex;
            $config['last_rotation_date'] = $date->toDateString();

            $shift->update(['rotation_config' => $config]);

            DB::commit();

            Log::info('Shift rotation processed', [
                'shift_id' => $shift->id, 'new_index' => $newIndex, 'by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rotation processed.',
                'new_shift_type' => $sequence[$newIndex],
                'next_rotation_date' => $date->copy()->addDays($config['rotation_days'] ?? 7)->toDateString(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function shiftCalendar(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $shifts = SecurityShift::active()->get();

        $data = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dow = $current->dayOfWeekIso;
            $applicable = $shifts->filter(fn ($s) => $s->isApplicableOnDay($dow))->values();
            $data[] = [
                'date' => $current->toDateString(),
                'day_of_week' => $current->format('l'),
                'is_weekend' => $dow >= 6,
                'applicable_shifts' => $applicable,
                'total_applicable' => $applicable->count(),
            ];
            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'calendar_data' => $data,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'total_days' => $start->diffInDays($end) + 1,
            ],
            'total_shifts' => $shifts->count(),
        ]);
    }

    // ---------------- private helpers ----------------

    private function validateShift(Request $request, ?int $shiftId = null)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:security_shifts,code' . ($shiftId ? ",{$shiftId}" : ''),
            'category' => 'required|in:day,night,evening,special,holiday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'day_type' => 'required|in:all_days,weekday,weekend,custom',
            'rotation_type' => 'required|in:fixed,rotating',
            'is_active' => 'sometimes|boolean',
            'required_personnel' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string|max:1000',
        ];

        if ($request->day_type === 'custom') {
            $rules['applicable_days'] = 'required|array|min:1';
            $rules['applicable_days.*'] = 'integer|between:1,7';
        }
        if ($request->rotation_type === 'rotating') {
            $rules['rotation_days'] = 'required|integer|min:1|max:30';
            $rules['rotation_sequence'] = 'required|in:morning_evening,evening_morning,night_morning';
        }

        $hasBreak = $request->input('has_break') === '1'
            && $request->has('break_names')
            && is_array($request->break_names)
            && count(array_filter($request->break_names)) > 0;

        if ($hasBreak) {
            $rules['break_names'] = 'required|array|min:1';
            $rules['break_names.*'] = 'required|string|max:100';
            $rules['break_start_times'] = 'required|array|min:1';
            $rules['break_start_times.*'] = 'required|date_format:H:i';
            $rules['break_end_times'] = 'required|array|min:1';
            $rules['break_end_times.*'] = 'required|date_format:H:i';
            $rules['break_durations'] = 'required|array|min:1';
            $rules['break_durations.*'] = 'required|integer|min:1|max:240';
        }
        if ($request->input('has_handover') === '1') {
            $rules['handover_duration'] = 'nullable|integer|min:5|max:120';
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($v) use ($request, $hasBreak) {
            if (!$hasBreak || !$request->has('break_start_times')) {
                return;
            }
            try {
                $shiftStart = Carbon::createFromFormat('H:i', $request->start_time);
                $shiftEnd = Carbon::createFromFormat('H:i', $request->end_time);
                if ($shiftEnd <= $shiftStart) {
                    $shiftEnd->addDay();
                }

                foreach ($request->break_start_times as $i => $start) {
                    $end = $request->break_end_times[$i] ?? null;
                    if (empty($start) || empty($end)) {
                        continue;
                    }
                    try {
                        $bStart = Carbon::createFromFormat('H:i', $start);
                        $bEnd = Carbon::createFromFormat('H:i', $end);
                        if ($bEnd <= $bStart) {
                            $bEnd->addDay();
                        }
                        if ($bStart < $shiftStart || $bEnd > $shiftEnd) {
                            $v->errors()->add("break_start_times.{$i}",
                                "Break '{$request->break_names[$i]}' must be within shift hours.");
                        }
                    } catch (\Exception $e) {
                        $v->errors()->add("break_start_times.{$i}", "Invalid break time.");
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Break time validation skipped: invalid shift times');
            }
        });

        return $validator;
    }

    private function prepareShiftData(Request $request): array
    {
        $start = Carbon::createFromFormat('H:i', $request->start_time);
        $end = Carbon::createFromFormat('H:i', $request->end_time);
        if ($end <= $start) {
            $end->addDay();
        }

        return [
            'name' => $request->name,
            'code' => $request->code,
            'category' => $request->category,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'duration_hours' => round($start->diffInMinutes($end) / 60, 2),
            'is_overnight' => $request->end_time <= $request->start_time,
            'day_type' => $request->day_type,
            'rotation_type' => $request->rotation_type,
            'is_active' => $request->input('is_active') === '1',
            'required_personnel' => (int) $request->required_personnel,
            'description' => $request->description,
        ];
    }

    private function buildBreakSchedule(Request $request): ?array
    {
        $hasBreak = $request->input('has_break') === '1'
            && $request->has('break_names')
            && is_array($request->break_names)
            && count(array_filter($request->break_names)) > 0;

        if (!$hasBreak) {
            return null;
        }

        $breaks = [];
        foreach ($request->break_names as $i => $name) {
            if (empty($name) || empty($request->break_start_times[$i] ?? null)
                || empty($request->break_end_times[$i] ?? null)) {
                continue;
            }
            $breaks[] = [
                'name' => $name,
                'start_time' => $request->break_start_times[$i],
                'end_time' => $request->break_end_times[$i],
                'duration_minutes' => (int) ($request->break_durations[$i] ?? 0),
                'is_paid' => ($request->break_paid[$i] ?? null) === '1',
                'description' => $request->break_descriptions[$i] ?? null,
            ];
        }

        return [
            'has_break' => true,
            'breaks' => $breaks,
            'total_break_minutes' => array_sum(array_column($breaks, 'duration_minutes')),
            'total_breaks' => count($breaks),
        ];
    }

    private function buildHandoverConfig(Request $request): ?array
    {
        if ($request->input('has_handover') !== '1') {
            return null;
        }
        return [
            'has_handover' => true,
            'handover_duration' => (int) ($request->handover_duration ?? 30),
            'handover_notes_required' => $request->input('handover_notes_required') === '1',
            'handover_checklist' => $request->handover_checklist
                ?? ['equipment_check', 'incident_report', 'visitor_logs', 'key_handover'],
        ];
    }

    private function calculateShiftAttendanceRate(SecurityShift $shift): float
    {
        $total = $shift->schedules()->count();
        if ($total === 0) return 100.0;
        $absent = $shift->schedules()->where('status', 'absent')->count();
        return round((($total - $absent) / $total) * 100, 2);
    }

    private function calculateAverageCheckinTime(SecurityShift $shift): ?float
    {
        $schedules = $shift->schedules()
            ->whereNotNull('checkin_time')
            ->where('status', 'completed')
            ->get(['assignment_date', 'checkin_time']);
        if ($schedules->isEmpty()) return null;

        $total = 0; $count = 0;
        foreach ($schedules as $s) {
            $expected = Carbon::parse($s->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
            $actual = Carbon::parse($s->checkin_time);
            if ($actual->greaterThan($expected)) {
                $total += $actual->diffInMinutes($expected);
                $count++;
            }
        }
        return $count > 0 ? round($total / $count, 2) : 0.0;
    }

    private function getUpcomingRotations(SecurityShift $shift, int $days = 30): array
    {
        $sequence = $this->getRotationSequence($shift->rotation_config['rotation_sequence'] ?? 'morning_evening');
        $index = (int) ($shift->rotation_config['current_sequence_index'] ?? 0) % count($sequence);
        $rotationDays = (int) ($shift->rotation_config['rotation_days'] ?? 7);

        $out = [];
        $date = today();
        for ($i = 0; $i < $days; $i++) {
            $out[] = ['date' => $date->copy(), 'shift_type' => $sequence[$index]];
            $date->addDay();
            if ($i > 0 && ($i + 1) % $rotationDays === 0) {
                $index = ($index + 1) % count($sequence);
            }
        }
        return $out;
    }

    private function getRotationSequence(string $type): array
    {
        return match ($type) {
            'evening_morning' => ['evening', 'morning', 'night', 'off'],
            'night_morning' => ['night', 'morning', 'evening', 'off'],
            default => ['morning', 'evening', 'night', 'off'],
        };
    }

    private function formatBreaks(?array $schedule): string
    {
        if (!$schedule || empty($schedule['breaks'])) {
            return 'No breaks';
        }
        return collect($schedule['breaks'])
            ->map(fn ($b) => "{$b['name']}: {$b['start_time']}-{$b['end_time']} ({$b['duration_minutes']}min)")
            ->implode(', ');
    }

    private function exportSelected(array $ids)
    {
        $shifts = SecurityShift::whereIn('id', $ids)->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="security_shifts_' . date('Y-m-d_H-i-s') . '.csv"',
        ];
        $callback = function () use ($shifts) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Code','Name','Category','Start','End','Duration','Overnight','Day Type','Applicable Days','Rotation','Required','Status']);
            foreach ($shifts as $s) {
                fputcsv($f, [
                    $s->code, $s->name, $s->category, $s->start_time, $s->end_time,
                    $s->duration_hours, $s->is_overnight ? 'Yes' : 'No',
                    $s->day_type, $s->applicable_days ? implode(',', $s->applicable_days) : '',
                    $s->rotation_type, $s->required_personnel, $s->is_active ? 'Active' : 'Inactive',
                ]);
            }
            fclose($f);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function friendlyDbError(\Illuminate\Database\QueryException $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'code')) {
            return 'A shift with this code already exists.';
        }
        return 'Database error. Please try again.';
    }
}