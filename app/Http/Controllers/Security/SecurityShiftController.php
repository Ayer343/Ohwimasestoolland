<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\SecurityShift;
use App\Services\HolidayResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SecurityShiftController extends Controller
{
    public function __construct(protected HolidayResolver $holidays) {}

    // ============================================================
    // AUTHORIZATION + RESPONSE HELPERS
    // ============================================================

    private function canManageShifts(): bool
    {
        $user = auth()->user();
        return $user && Gate::forUser($user)->allows('manage-security-schedules');
    }

    private function authorizeManage(): void
    {
        if (!$this->canManageShifts()) {
            abort(403, 'You do not have permission to manage security shifts.');
        }
    }

    private function success(Request $request, string $message, array $data = [], ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['success' => true, 'message' => $message], $data));
        }
        return ($redirect ? redirect($redirect) : back())->with('success', $message);
    }

    private function failure(Request $request, string $message, int $status = 422, ?string $redirect = null, $errors = null)
    {
        if ($request->expectsJson()) {
            return response()->json(
                array_merge(['success' => false, 'message' => $message], $errors ? ['errors' => $errors] : []),
                $status
            );
        }
        $r = $redirect ? redirect($redirect) : back();
        if ($errors) $r->withErrors($errors);
        return $r->with('error', $message)->withInput();
    }

    // ============================================================
    // INDEX
    // ============================================================

    public function index(Request $request)
    {
        $this->authorizeManage();

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

        // Whitelist sort columns
        $allowedSorts = ['name', 'code', 'category', 'start_time', 'end_time', 'duration_hours', 'required_personnel', 'is_active', 'created_at'];
        $sortBy = $request->get('sort_by', 'start_time');
        if (!in_array($sortBy, $allowedSorts, true)) $sortBy = 'start_time';
        $sortOrder = strtolower($request->get('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder)->withCount('schedules');

        $shifts = $query->paginate(20)->withQueryString();

        // View-compatible individual stats
        $totalShifts      = SecurityShift::count();
        $activeShifts     = SecurityShift::where('is_active', true)->count();
        $activeToday      = SecurityShift::active()->forToday()->count();
        $averageDuration  = round(SecurityShift::avg('duration_hours') ?? 0, 2);
        $shortestDuration = SecurityShift::min('duration_hours') ?? 0;
        $totalPersonnel   = SecurityShift::sum('required_personnel') ?? 0;
        $dayShifts        = SecurityShift::where('category', 'day')->count();
        $nightShifts      = SecurityShift::where('category', 'night')->count();
        $rotatingShifts   = SecurityShift::where('rotation_type', 'rotating')->count();
        $trashedCount     = SecurityShift::onlyTrashed()->count();

        $stats = compact(
            'totalShifts', 'activeShifts', 'activeToday', 'averageDuration',
            'shortestDuration', 'totalPersonnel', 'dayShifts', 'nightShifts',
            'rotatingShifts', 'trashedCount'
        );

        $shiftCategories = SecurityShift::CATEGORIES;
        $rotationTypes   = SecurityShift::ROTATION_TYPES;

        return view('security.security-shifts.index', array_merge($stats, compact(
            'shifts', 'shiftCategories', 'rotationTypes'
        )));
    }

    // ============================================================
    // CREATE / STORE
    // ============================================================

    public function create()
    {
        $this->authorizeManage();
        return view('security.security-shifts.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $validator = $this->validateShift($request);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $data = $this->prepareShiftData($request);

            $data['applicable_days'] = $this->resolveApplicableDays($request, $data['day_type']);

            if ($data['rotation_type'] === 'rotating') {
                $data['rotation_config'] = [
                    'rotation_days'          => (int) ($request->rotation_days ?? 7),
                    'rotation_sequence'      => $request->rotation_sequence ?? 'morning_evening',
                    'last_rotation_date'     => null,
                    'current_sequence_index' => 0,
                ];
            } else {
                $data['rotation_config'] = null;
            }

            $data['break_schedule']  = $this->buildBreakSchedule($request);
            $data['handover_config'] = $this->buildHandoverConfig($request);

            $shift = SecurityShift::create($data);
            DB::commit();

            Log::info('Security shift created (supervisor)', [
                'shift_id' => $shift->id, 'by' => auth()->id(),
            ]);

            return $this->success(
                $request,
                'Security shift created successfully.',
                ['shift_id' => $shift->id],
                route('security.security-shifts.index')
            );
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('DB error creating shift: ' . $e->getMessage());
            return $this->failure($request, 'Database error: ' . $this->friendlyDbError($e), 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creating shift: ' . $e->getMessage());
            return $this->failure($request, 'Failed to create shift: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // SHOW / EDIT / UPDATE
    // ============================================================

    public function show($id)
    {
        $this->authorizeManage();

        $securityShift = SecurityShift::findOrFail($id);
        $securityShift->loadCount('schedules');
        $securityShift->load([
            'schedules' => fn ($q) => $q->whereDate('assignment_date', '>=', today())
                ->orderBy('assignment_date')->limit(10)->with(['post', 'securityUser']),
        ]);

        $shiftStats = [
            'total_assignments'    => $securityShift->schedules()->count(),
            'completed_shifts'     => $securityShift->schedules()->where('status', 'completed')->count(),
            'active_assignments'   => $securityShift->schedules()
                ->whereDate('assignment_date', '>=', today())
                ->whereIn('status', ['scheduled', 'active'])->count(),
            'attendance_rate'      => $this->calculateShiftAttendanceRate($securityShift),
            'average_checkin_time' => $this->calculateAverageCheckinTime($securityShift),
        ];

        $upcomingRotations = $securityShift->isRotating()
            ? $this->getUpcomingRotations($securityShift)
            : [];

        return view('security.security-shifts.show', compact('securityShift', 'shiftStats', 'upcomingRotations'));
    }

    public function edit($id)
    {
        $this->authorizeManage();
        $securityShift = SecurityShift::findOrFail($id);
        return view('security.security-shifts.edit', array_merge(
            ['securityShift' => $securityShift],
            $this->formOptions()
        ));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeManage();

        $securityShift = SecurityShift::findOrFail($id);
        $validator = $this->validateShift($request, $securityShift->id);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $data = $this->prepareShiftData($request);
            $data['applicable_days'] = $this->resolveApplicableDays($request, $data['day_type']);

            if ($data['rotation_type'] === 'rotating') {
                $existing = $securityShift->rotation_config ?? [];
                $data['rotation_config'] = [
                    'rotation_days'          => (int) ($request->rotation_days ?? 7),
                    'rotation_sequence'      => $request->rotation_sequence ?? 'morning_evening',
                    'last_rotation_date'     => $existing['last_rotation_date'] ?? null,
                    'current_sequence_index' => $existing['current_sequence_index'] ?? 0,
                ];
            } else {
                $data['rotation_config'] = null;
            }

            $data['break_schedule']  = $this->buildBreakSchedule($request);
            $data['handover_config'] = $this->buildHandoverConfig($request);

            $securityShift->update($data);
            DB::commit();

            Log::info('Security shift updated (supervisor)', [
                'shift_id' => $securityShift->id, 'by' => auth()->id(),
            ]);

            return $this->success(
                $request,
                'Security shift updated successfully.',
                ['shift_id' => $securityShift->id],
                route('security.security-shifts.show', $securityShift->id)
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updating shift: ' . $e->getMessage());
            return $this->failure($request, 'Failed to update shift: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // DESTROY (soft delete)
    // ============================================================

    public function destroy(Request $request, $id)
    {
        $this->authorizeManage();

        try {
            $shift = SecurityShift::findOrFail($id);

            $activeSchedules = SecuritySchedule::where('security_shift_id', $shift->id)
                ->whereIn('status', ['scheduled', 'active'])
                ->whereDate('assignment_date', '>=', today())
                ->count();

            if ($activeSchedules > 0) {
                return $this->failure(
                    $request,
                    "Cannot delete: {$activeSchedules} active/future schedule(s) still use this shift.",
                    422
                );
            }

            $shift->delete();
            Log::info('Security shift soft deleted (supervisor)', ['shift_id' => $id, 'by' => auth()->id()]);

            return $this->success(
                $request,
                'Security shift moved to trash successfully.',
                [],
                route('security.security-shifts.index')
            );
        } catch (\Throwable $e) {
            Log::error('Failed to delete shift: ' . $e->getMessage());
            return $this->failure($request, 'Failed to delete shift: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // TRASH
    // ============================================================

    public function trash(Request $request)
    {
        $this->authorizeManage();

        $query = SecurityShift::onlyTrashed()->withCount('schedules');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$s}%")
                ->orWhere('code', 'LIKE', "%{$s}%")
                ->orWhere('description', 'LIKE', "%{$s}%"));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $allowedSorts = ['name', 'code', 'category', 'start_time', 'end_time', 'deleted_at'];
        $sortBy = $request->get('sort_by', 'deleted_at');
        if (!in_array($sortBy, $allowedSorts, true)) $sortBy = 'deleted_at';
        $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $trashedShifts = $query->paginate(20)->withQueryString();

        $trashStats = [
            'total_trashed'     => SecurityShift::onlyTrashed()->count(),
            'trashed_today'     => SecurityShift::onlyTrashed()->whereDate('deleted_at', today())->count(),
            'trashed_this_week' => SecurityShift::onlyTrashed()
                ->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'oldest_trashed'    => SecurityShift::onlyTrashed()
                ->orderBy('deleted_at', 'asc')->first()?->deleted_at?->format('M j, Y') ?? 'N/A',
        ];

        $shiftCategories = SecurityShift::CATEGORIES;

        return view('security.security-shifts.trash', compact(
            'trashedShifts', 'trashStats', 'shiftCategories'
        ));
    }

    public function restore(Request $request, $id)
    {
        $this->authorizeManage();

        DB::beginTransaction();
        try {
            $shift = SecurityShift::onlyTrashed()->findOrFail($id);

            if (SecurityShift::where('code', $shift->code)->where('id', '!=', $shift->id)->exists()) {
                DB::rollBack();
                return $this->failure(
                    $request,
                    "Cannot restore — another active shift uses code '{$shift->code}'.",
                    422
                );
            }

            $shift->restore();
            DB::commit();

            Log::info('Shift restored (supervisor)', ['shift_id' => $shift->id, 'by' => auth()->id()]);

            return $this->success($request, 'Shift restored successfully.', [
                'shift_id' => $shift->id,
            ], route('security.security-shifts.trash'));
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Restore shift failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to restore shift: ' . $e->getMessage(), 500);
        }
    }

    public function forceDelete(Request $request, $id)
    {
        $this->authorizeManage();

        DB::beginTransaction();
        try {
            $shift = SecurityShift::onlyTrashed()->findOrFail($id);

            $refs = SecuritySchedule::withTrashed()->where('security_shift_id', $shift->id)->count();
            if ($refs > 0) {
                DB::rollBack();
                return $this->failure(
                    $request,
                    "Cannot permanently delete — {$refs} schedule(s) reference this shift.",
                    422
                );
            }

            $shift->forceDelete();
            DB::commit();

            Log::warning('Shift permanently deleted (supervisor)', ['shift_id' => $id, 'by' => auth()->id()]);

            return $this->success($request, 'Shift permanently deleted.', [], route('security.security-shifts.trash'));
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Force delete shift failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to permanently delete shift: ' . $e->getMessage(), 500);
        }
    }

    public function bulkTrashAction(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:restore,force_delete',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $ids = $request->ids;

            if ($request->action === 'restore') {
                $restored = 0; $skipped = [];
                foreach (SecurityShift::onlyTrashed()->whereIn('id', $ids)->get() as $shift) {
                    if (SecurityShift::where('code', $shift->code)->where('id', '!=', $shift->id)->exists()) {
                        $skipped[] = $shift->name;
                        continue;
                    }
                    $shift->restore();
                    $restored++;
                }
                DB::commit();

                $message = "{$restored} shift(s) restored.";
                if ($skipped) $message .= ' Skipped: ' . implode(', ', $skipped);

                return $this->success($request, $message, ['restored' => $restored, 'skipped' => $skipped]);
            }

            // force_delete
            $blocked = SecuritySchedule::withTrashed()
                ->whereIn('security_shift_id', $ids)->distinct()->pluck('security_shift_id')->all();
            $deletable = array_values(array_diff($ids, $blocked));
            $deleted = SecurityShift::onlyTrashed()->whereIn('id', $deletable)->forceDelete();

            DB::commit();

            $message = "{$deleted} shift(s) permanently deleted.";
            if ($blocked) $message .= ' ' . count($blocked) . ' skipped (referenced).';

            return $this->success($request, $message, [
                'deleted' => $deleted, 'blocked' => count($blocked),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Bulk trash action failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // BULK ACTIONS
    // ============================================================

    public function bulkAction(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete,export',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'exists:security_shifts,id',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
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
                    // FIXED: use whereHas + whereIn, not `having` on an alias
                    $inUseIds = SecurityShift::whereIn('id', $request->ids)
                        ->whereHas('schedules', fn ($q) => $q
                            ->whereIn('status', ['scheduled', 'active'])
                            ->whereDate('assignment_date', '>=', today()))
                        ->pluck('id')->all();

                    $deletable = array_values(array_diff($request->ids, $inUseIds));
                    $n = SecurityShift::whereIn('id', $deletable)->delete();

                    $msg = "{$n} shift(s) moved to trash.";
                    if ($inUseIds) $msg .= ' ' . count($inUseIds) . ' blocked (in use).';
                    return $msg;
                })(),
                'export' => null,
            };

            if ($request->action === 'export') {
                DB::commit();
                return $this->exportSelected($request->ids);
            }

            DB::commit();
            return $this->success($request, $message);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Bulk shift action failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed: ' . $e->getMessage(), 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        $this->authorizeManage();
        try {
            $shift = SecurityShift::findOrFail($id);
            $shift->update(['is_active' => !$shift->is_active]);

            Log::info('Shift status toggled (supervisor)', [
                'shift_id' => $shift->id,
                'new_status' => $shift->is_active ? 'active' : 'inactive',
                'by' => auth()->id(),
            ]);

            return $this->success($request, 'Shift status updated.', [
                'is_active' => (bool) $shift->fresh()->is_active,
            ]);
        } catch (\Throwable $e) {
            return $this->failure($request, 'Failed: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // EXPORT
    // ============================================================

    public function exportSelected($idsOrRequest)
    {
        $this->authorizeManage();

        $ids = $idsOrRequest instanceof Request
            ? (array) $idsOrRequest->query('ids', [])
            : (array) $idsOrRequest;

        if (empty($ids)) abort(422, 'At least one shift id is required.');

        $shifts = SecurityShift::whereIn('id', $ids)->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="security_shifts_' . date('Y-m-d_H-i-s') . '.csv"',
        ];

        $callback = function () use ($shifts) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, [
                'Code','Name','Category','Start','End','Duration','Overnight',
                'Day Type','Applicable Days','Rotation','Required','Status','Description',
            ]);
            foreach ($shifts as $s) {
                fputcsv($f, [
                    $s->code, $s->name, $s->category, $s->start_time, $s->end_time,
                    $s->duration_hours, $s->is_overnight ? 'Yes' : 'No',
                    $s->day_type, $s->applicable_days ? implode(',', $s->applicable_days) : '',
                    $s->rotation_type, $s->required_personnel,
                    $s->is_active ? 'Active' : 'Inactive', $s->description,
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ============================================================
    // AJAX
    // ============================================================

    public function getShiftsForSchedule(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'post_id' => 'nullable|exists:security_posts,id',
            'personnel_count' => 'nullable|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        $date = Carbon::parse($request->date);
        $dayOfWeek = $date->dayOfWeekIso;
        $isHoliday = $this->holidays->isHoliday($date);

        // FIXED: use the model scope — single source of truth
        $query = SecurityShift::active()->forDayOfWeek($dayOfWeek);

        if ($isHoliday) {
            $query->where('category', 'holiday');
        }

        if ($request->filled('personnel_count')) {
            $query->where('required_personnel', '<=', $request->personnel_count);
        }

        if ($request->filled('post_id')) {
            $post = SecurityPost::find($request->post_id);
            if ($post && $post->type === 'main_gate') {
                $query->whereIn('category', ['day', 'night']);
            }
        }

        $shifts = $query->get()->map(fn ($shift) => [
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
            'is_applicable' => $shift->isApplicableOnDay($dayOfWeek, $isHoliday),
            'time_range' => $shift->getTimeRange(),
            'breaks_formatted' => $this->formatBreaks($shift->break_schedule),
        ]);

        return $this->success($request, 'OK', [
            'shifts' => $shifts,
            'date' => $date->toDateString(),
            'day_of_week' => $date->format('l'),
            'is_holiday' => $isHoliday,
            'total_shifts' => $shifts->count(),
        ]);
    }

    public function checkShiftsInUse(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:security_shifts,id',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        // FIXED: `having` on alias fails on some MySQL versions — filter in PHP
        $usedShifts = SecurityShift::whereIn('id', $request->ids)
            ->withCount(['schedules as active_schedules_count' => fn ($q) => $q
                ->whereIn('status', ['scheduled', 'active'])
                ->whereDate('assignment_date', '>=', today())])
            ->get(['id', 'name', 'code'])
            ->filter(fn ($s) => $s->active_schedules_count > 0)
            ->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'code' => $s->code,
                'schedules_count' => $s->active_schedules_count,
            ])->values();

        return $this->success($request, 'OK', [
            'in_use_count' => $usedShifts->count(),
            'used_shifts' => $usedShifts,
        ]);
    }

    public function getRecommendedShifts(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'post_id' => 'required|exists:security_posts,id',
            'date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        $post = SecurityPost::findOrFail($request->post_id);

        $commonShifts = SecurityShift::active()
            ->whereHas('schedules', fn ($q) => $q
                ->where('security_post_id', $post->id)
                ->whereDate('assignment_date', '>=', now()->subDays(30)))
            ->withCount(['schedules' => fn ($q) => $q->where('security_post_id', $post->id)])
            ->orderByDesc('schedules_count')
            ->limit(5)->get();

        $matchingShifts = SecurityShift::active()
            ->where('required_personnel', '<=', $post->max_personnel)
            ->get();

        return $this->success($request, 'OK', [
            'common_shifts' => $commonShifts,
            'matching_shifts' => $matchingShifts,
            'post' => [
                'id' => $post->id, 'name' => $post->name,
                'type' => $post->type, 'max_personnel' => $post->max_personnel,
            ],
        ]);
    }

    public function processRotation(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'shift_id' => 'required|exists:security_shifts,id',
            'date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        $shift = SecurityShift::findOrFail($request->shift_id);
        if (!$shift->isRotating()) {
            return $this->failure($request, 'This shift is not configured for rotation.', 400);
        }

        DB::beginTransaction();
        try {
            $date = Carbon::parse($request->date);
            $config = $shift->rotation_config ?? [];
            $sequence = $shift->getRotationSequence();
            if (empty($sequence)) throw new \Exception('Empty rotation sequence.');

            $currentIndex = (int) ($config['current_sequence_index'] ?? 0);
            $newIndex = ($currentIndex + 1) % count($sequence);

            $config['current_sequence_index'] = $newIndex;
            $config['last_rotation_date'] = $date->toDateString();

            $shift->update(['rotation_config' => $config]);
            DB::commit();

            Log::info('Shift rotation processed (supervisor)', [
                'shift_id' => $shift->id, 'new_index' => $newIndex, 'by' => auth()->id(),
            ]);

            return $this->success($request, 'Rotation processed successfully.', [
                'new_shift_type' => $sequence[$newIndex],
                'next_rotation_date' => $date->copy()
                    ->addDays((int) ($config['rotation_days'] ?? 7))->toDateString(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Rotation process failed: ' . $e->getMessage());
            return $this->failure($request, 'Error: ' . $e->getMessage(), 500);
        }
    }

    public function shiftCalendar(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'shift_id' => 'nullable|exists:security_shifts,id',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, null, $validator->errors());
        }

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $shifts = SecurityShift::active()->get();

        $data = [];
        $cur = $start->copy();
        while ($cur->lte($end)) {
            $dow = $cur->dayOfWeekIso;
            $holiday = $this->holidays->isHoliday($cur);
            $applicable = $shifts->filter(fn ($s) => $s->isApplicableOnDay($dow, $holiday))->values();

            $data[] = [
                'date' => $cur->toDateString(),
                'day_of_week' => $cur->format('l'),
                'is_weekend' => $dow >= 6,
                'is_holiday' => $holiday,
                'applicable_shifts' => $applicable,
                'total_applicable' => $applicable->count(),
            ];
            $cur->addDay();
        }

        return $this->success($request, 'OK', [
            'calendar_data' => $data,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'total_days' => $start->diffInDays($end) + 1,
            ],
            'total_shifts' => $shifts->count(),
        ]);
    }

    // ============================================================
    // FORM OPTIONS
    // ============================================================

    private function formOptions(): array
    {
        return [
            'dayTypes' => SecurityShift::DAY_TYPES,
            'daysOfWeek' => SecurityShift::DAYS_OF_WEEK,
            'shiftCategories' => SecurityShift::CATEGORIES,
            'rotationTypes' => SecurityShift::ROTATION_TYPES,
            'rotationSequences' => [
                'morning_evening' => 'Morning → Evening → Night → Off',
                'evening_morning' => 'Evening → Morning → Night → Off',
                'night_morning' => 'Night → Morning → Evening → Off',
            ],
        ];
    }

    // ============================================================
    // VALIDATION / DATA PREP
    // ============================================================

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
            if (!$hasBreak || !$request->has('break_start_times')) return;
            try {
                $shiftStart = Carbon::createFromFormat('H:i', $request->start_time);
                $shiftEnd = Carbon::createFromFormat('H:i', $request->end_time);
                if ($shiftEnd <= $shiftStart) $shiftEnd->addDay();

                foreach ($request->break_start_times as $i => $start) {
                    $end = $request->break_end_times[$i] ?? null;
                    if (empty($start) || empty($end)) continue;
                    try {
                        $bStart = Carbon::createFromFormat('H:i', $start);
                        $bEnd = Carbon::createFromFormat('H:i', $end);
                        if ($bEnd <= $bStart) $bEnd->addDay();
                        if ($bStart < $shiftStart || $bEnd > $shiftEnd) {
                            $v->errors()->add(
                                "break_start_times.{$i}",
                                "Break '{$request->break_names[$i]}' outside shift hours."
                            );
                        }
                    } catch (\Exception $e) {
                        $v->errors()->add("break_start_times.{$i}", 'Invalid break time.');
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
        if ($end <= $start) $end->addDay();

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

    private function resolveApplicableDays(Request $request, string $dayType): ?array
    {
        if ($dayType === 'custom') {
            return (array) $request->input('applicable_days', []);
        }
        return null;
    }

    private function buildBreakSchedule(Request $request): ?array
    {
        $hasBreak = $request->input('has_break') === '1'
            && $request->has('break_names')
            && is_array($request->break_names)
            && count(array_filter($request->break_names)) > 0;
        if (!$hasBreak) return null;

        $breaks = [];
        foreach ($request->break_names as $i => $name) {
            if (empty($name) || empty($request->break_start_times[$i] ?? null) || empty($request->break_end_times[$i] ?? null)) {
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
        if ($request->input('has_handover') !== '1') return null;
        return [
            'has_handover' => true,
            'handover_duration' => (int) ($request->handover_duration ?? 30),
            'handover_notes_required' => $request->input('handover_notes_required') === '1',
            'handover_checklist' => $request->handover_checklist
                ?? ['equipment_check', 'incident_report', 'visitor_logs', 'key_handover'],
        ];
    }

    // ============================================================
    // STATS / HELPERS
    // ============================================================

    private function calculateShiftAttendanceRate(SecurityShift $shift): float
    {
        $total = $shift->schedules()->count();
        if ($total === 0) return 100.0;
        $completed = $shift->schedules()->where('status', 'completed')->count();
        $absent = $shift->schedules()->where('status', 'absent')->count();
        return round((($total - $absent) / $total) * 100, 2);
    }

    private function calculateAverageCheckinTime(SecurityShift $shift): ?float
    {
        $schedules = $shift->schedules()
            ->whereNotNull('checkin_time')->where('status', 'completed')->get();
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
        $sequence = $shift->getRotationSequence();
        if (empty($sequence)) return [];

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

    private function formatBreaks($schedule): string
    {
        if (!$schedule || empty($schedule['has_break']) || empty($schedule['breaks'])) {
            return 'No breaks';
        }
        return collect($schedule['breaks'])
            ->map(fn ($b) => "{$b['name']}: {$b['start_time']} - {$b['end_time']} ({$b['duration_minutes']} min)")
            ->implode(', ');
    }

    private function friendlyDbError(\Illuminate\Database\QueryException $e): string
    {
        $m = $e->getMessage();
        if (str_contains($m, 'Duplicate entry') && str_contains($m, 'code')) {
            return 'A shift with this code already exists.';
        }
        if (str_contains($m, 'foreign key')) {
            return 'Operation violates data integrity.';
        }
        return 'Please try again.';
    }
}