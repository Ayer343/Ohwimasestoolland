<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\RotationGroup;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class RotationHistoryController extends Controller
{
    // ============================================================
    // AUTHORIZATION + RESPONSE HELPERS
    // ============================================================

    private function canManage(): bool
    {
        $user = auth()->user();
        return $user && Gate::forUser($user)->allows('manage-security-schedules');
    }

    private function authorizeManage(): void
    {
        if (!$this->canManage()) {
            abort(403, 'You do not have permission to view rotation history.');
        }
    }

    private function success(Request $request, string $message, array $data = [], ?string $redirect = null)
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(array_merge(['success' => true, 'message' => $message], $data));
        }
        return ($redirect ? redirect($redirect) : back())->with('success', $message);
    }

    private function failure(Request $request, string $message, int $status = 422, $errors = null, ?string $redirect = null)
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(
                array_merge(['success' => false, 'message' => $message], $errors ? ['errors' => $errors] : []),
                $status
            );
        }
        $r = $redirect ? redirect($redirect) : back();
        if ($errors) $r->withErrors($errors);
        return $r->with('error', $message);
    }

    // ============================================================
    // INDEX — Dashboard
    // ============================================================

    public function index(Request $request)
    {
        $this->authorizeManage();

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate   = $request->end_date   ? Carbon::parse($request->end_date)   : now()->endOfMonth();

        $statistics      = $this->getDashboardStatistics($startDate, $endDate);
        $recentRotations = $this->getRecentRotations(50);
        $topGroups       = $this->getTopRotatingGroups($startDate, $endDate, 10);
        $topPersonnel    = $this->getTopRotatingPersonnel($startDate, $endDate, 10);
        $trends          = $this->getRotationTrends($startDate, $endDate);

        return view('security.rotation-history.index', compact(
            'statistics',
            'recentRotations',
            'topGroups',
            'topPersonnel',
            'trends',
            'startDate',
            'endDate'
        ));
    }

    // ============================================================
    // FOR SCHEDULE
    // ============================================================

    public function forSchedule(Request $request, $scheduleId)
    {
        $this->authorizeManage();

        try {
            $schedule = SecuritySchedule::with([
                'securityUser', 'rotatedFromUser', 'post', 'shift', 'rotationGroup',
            ])->findOrFail($scheduleId);

            $history = [];

            // Historical entries from rotation_data
            if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                foreach ($schedule->rotation_data['rotation_history'] as $item) {
                    $history[] = array_merge($item, [
                        'type'          => 'historical',
                        'schedule_id'   => $schedule->id,
                        'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                        'post'          => $schedule->post?->name,
                        'shift'         => $schedule->shift?->name,
                        'group'         => $schedule->rotationGroup?->name,
                    ]);
                }
            }

            // Current rotation
            if ($schedule->is_rotated && $schedule->rotated_at) {
                $history[] = [
                    'id'                 => 'current_' . $schedule->id,
                    'type'               => 'current',
                    'date'               => $schedule->rotated_at->format('Y-m-d H:i:s'),
                    'schedule_date'      => $schedule->assignment_date->format('Y-m-d'),
                    'post'               => $schedule->post?->name,
                    'shift'              => $schedule->shift?->name,
                    'group'              => $schedule->rotationGroup?->name,
                    'from_user_id'       => $schedule->rotated_from_user_id,
                    'from_user_name'     => $schedule->rotatedFromUser?->name,
                    'to_user_id'         => $schedule->security_user_id,
                    'to_user_name'       => $schedule->securityUser?->name,
                    'reason'             => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                    'performed_by'       => $schedule->rotation_data['performed_by'] ?? null,
                    'performed_by_name'  => $this->getUserName($schedule->rotation_data['performed_by'] ?? null),
                    'rotation_group'     => $schedule->rotation_group_id,
                    'rotation_group_name'=> $schedule->rotationGroup?->name,
                    'preference_score'   => $schedule->rotation_preference_score ?? null,
                    'swap_count'         => $schedule->rotation_swap_count ?? 0,
                ];
            }

            // Related group history
            if ($schedule->rotation_group_id) {
                $history = array_merge($history, $this->getGroupRotationHistory($schedule->rotation_group_id, $schedule->id));
            }

            // Related swap events
            $history = array_merge($history, $this->getSwapHistory($scheduleId));

            usort($history, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

            $statistics = $this->calculateScheduleHistoryStatistics($history, $schedule);

            $data = [
                'schedule' => [
                    'id'           => $schedule->id,
                    'date'         => $schedule->assignment_date->format('Y-m-d'),
                    'post'         => $schedule->post?->name,
                    'shift'        => $schedule->shift?->name,
                    'current_user' => $schedule->securityUser?->name,
                    'group'        => $schedule->rotationGroup?->name,
                ],
                'history'      => $history,
                'statistics'   => $statistics,
                'total_events' => count($history),
            ];

            if ($request->expectsJson() || $request->wantsJson()) {
                return $this->success($request, 'OK', $data);
            }

            return view('security.rotation-history.schedule', $data);
        } catch (\Throwable $e) {
            Log::error('Failed to get schedule rotation history: ' . $e->getMessage());
            return $this->failure($request, 'Failed to load rotation history.', 500);
        }
    }

    // ============================================================
    // FOR USER
    // ============================================================

    public function forUser(Request $request, $userId)
    {
        $this->authorizeManage();

        try {
            $user = User::findOrFail($userId);

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subMonths(3);
            $endDate   = $request->end_date   ? Carbon::parse($request->end_date)   : now();

            $schedules = SecuritySchedule::where(function ($q) use ($userId) {
                    $q->where('security_user_id', $userId)
                      ->orWhere('rotated_from_user_id', $userId);
                })
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->with(['post', 'shift', 'securityUser', 'rotatedFromUser', 'rotationGroup'])
                ->orderByDesc('assignment_date')
                ->get();

            $history       = [];
            $rotationStats = [
                'rotated_in'  => 0,
                'rotated_out' => 0,
                'by_post'     => [],
                'by_shift'    => [],
                'by_group'    => [],
            ];

            foreach ($schedules as $schedule) {
                // Rotated OUT of this schedule
                if ($schedule->rotated_from_user_id == $userId && $schedule->is_rotated) {
                    $history[] = [
                        'id'            => 'rotated_out_' . $schedule->id,
                        'type'          => 'rotated_out',
                        'date'          => $schedule->rotated_at?->format('Y-m-d H:i:s') ?? $schedule->updated_at->format('Y-m-d H:i:s'),
                        'schedule_id'   => $schedule->id,
                        'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                        'post'          => $schedule->post?->name,
                        'post_id'       => $schedule->security_post_id,
                        'shift'         => $schedule->shift?->name,
                        'shift_id'      => $schedule->security_shift_id,
                        'group'         => $schedule->rotationGroup?->name,
                        'group_id'      => $schedule->rotation_group_id,
                        'from_user_id'  => $userId,
                        'from_user_name'=> $user->name,
                        'to_user_id'    => $schedule->security_user_id,
                        'to_user_name'  => $schedule->securityUser?->name,
                        'reason'        => $schedule->rotation_data['reason'] ?? 'Rotated out',
                        'rotation_group'=> $schedule->rotation_data['rotation_group'] ?? null,
                    ];
                    $rotationStats['rotated_out']++;
                    $this->updateStatsArray($rotationStats, $schedule);
                }

                // Rotated INTO this schedule
                if ($schedule->security_user_id == $userId && $schedule->rotated_from_user_id) {
                    $history[] = [
                        'id'            => 'rotated_in_' . $schedule->id,
                        'type'          => 'rotated_in',
                        'date'          => $schedule->rotated_at?->format('Y-m-d H:i:s') ?? $schedule->created_at->format('Y-m-d H:i:s'),
                        'schedule_id'   => $schedule->id,
                        'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                        'post'          => $schedule->post?->name,
                        'post_id'       => $schedule->security_post_id,
                        'shift'         => $schedule->shift?->name,
                        'shift_id'      => $schedule->security_shift_id,
                        'group'         => $schedule->rotationGroup?->name,
                        'group_id'      => $schedule->rotation_group_id,
                        'from_user_id'  => $schedule->rotated_from_user_id,
                        'from_user_name'=> $schedule->rotatedFromUser?->name,
                        'to_user_id'    => $userId,
                        'to_user_name'  => $user->name,
                        'reason'        => $schedule->rotation_data['reason'] ?? 'Rotated in',
                        'rotation_group'=> $schedule->rotation_data['rotation_group'] ?? null,
                    ];
                    $rotationStats['rotated_in']++;
                    $this->updateStatsArray($rotationStats, $schedule);
                }

                // Rotation data history
                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        $fromId = $item['from_user_id'] ?? null;
                        $toId   = $item['to_user_id'] ?? null;
                        if ($fromId == $userId || $toId == $userId) {
                            $item['schedule_id']   = $schedule->id;
                            $item['schedule_date'] = $schedule->assignment_date->format('Y-m-d');
                            $item['post']          = $schedule->post?->name;
                            $item['shift']         = $schedule->shift?->name;
                            $item['group']         = $schedule->rotationGroup?->name;
                            $history[]             = $item;

                            if ($fromId == $userId) {
                                $rotationStats['rotated_out']++;
                            } else {
                                $rotationStats['rotated_in']++;
                            }
                        }
                    }
                }
            }

            $groupMemberships = RotationGroup::whereHas('members', fn($q) => $q->where('user_id', $userId))
                ->with(['post', 'shift'])
                ->get();

            usort($history, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

            $statistics = $this->calculateUserRotationStatistics($history, $userId, $rotationStats);

            $data = [
                'user' => [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'email'        => $user->email,
                    'phone'        => $user->phone,
                    'badge_number' => $user->badge_number,
                ],
                'history'           => $history,
                'group_memberships' => $groupMemberships,
                'statistics'        => $statistics,
                'period'            => [
                    'start' => $startDate->format('Y-m-d'),
                    'end'   => $endDate->format('Y-m-d'),
                ],
                'total_events' => count($history),
            ];

            if ($request->expectsJson() || $request->wantsJson()) {
                return $this->success($request, 'OK', $data);
            }

            return view('security.rotation-history.user', $data);
        } catch (\Throwable $e) {
            Log::error('Failed to get user rotation history: ' . $e->getMessage());
            return $this->failure($request, 'Failed to load user rotation history.', 500);
        }
    }

    // ============================================================
    // FOR GROUP
    // ============================================================

    public function forGroup(Request $request, $groupId)
    {
        $this->authorizeManage();

        try {
            $group = RotationGroup::with(['post', 'shift'])->findOrFail($groupId);

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subMonths(3);
            $endDate   = $request->end_date   ? Carbon::parse($request->end_date)   : now();

            $groupHistory = ($group->rotation_config['rotation_history'] ?? []);

            $scheduleRotations = SecuritySchedule::where('rotation_group_id', $groupId)
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->where(fn($q) => $q->where('is_rotated', true)->orWhereNotNull('rotated_from_user_id'))
                ->with(['securityUser', 'rotatedFromUser', 'post', 'shift'])
                ->orderByDesc('rotated_at')
                ->get();

            $history = [];

            foreach ($groupHistory as $item) {
                $history[] = array_merge($item, [
                    'type'       => 'group_rotation',
                    'source'     => 'group_config',
                    'group_id'   => $group->id,
                    'group_name' => $group->name,
                ]);
            }

            foreach ($scheduleRotations as $schedule) {
                if ($schedule->rotated_at) {
                    $history[] = [
                        'id'               => 'schedule_' . $schedule->id,
                        'type'             => 'schedule_rotation',
                        'source'           => 'schedule',
                        'date'             => $schedule->rotated_at->format('Y-m-d H:i:s'),
                        'schedule_id'      => $schedule->id,
                        'schedule_date'    => $schedule->assignment_date->format('Y-m-d'),
                        'post'             => $schedule->post?->name,
                        'shift'            => $schedule->shift?->name,
                        'from_user_id'     => $schedule->rotated_from_user_id,
                        'from_user_name'   => $schedule->rotatedFromUser?->name,
                        'to_user_id'       => $schedule->security_user_id,
                        'to_user_name'     => $schedule->securityUser?->name,
                        'reason'           => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                        'group_id'         => $group->id,
                        'group_name'       => $group->name,
                        'preference_score' => $schedule->rotation_preference_score,
                    ];
                }

                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        $item['type']          = 'historical';
                        $item['source']        = 'schedule_history';
                        $item['schedule_id']   = $schedule->id;
                        $item['schedule_date'] = $schedule->assignment_date->format('Y-m-d');
                        $item['post']          = $schedule->post?->name;
                        $item['shift']         = $schedule->shift?->name;
                        $item['group_id']      = $group->id;
                        $item['group_name']    = $group->name;
                        $history[]             = $item;
                    }
                }
            }

            usort($history, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

            $statistics = $this->calculateGroupHistoryStatistics($history, $group);

            $data = [
                'group' => [
                    'id'           => $group->id,
                    'name'         => $group->name,
                    'code'         => $group->code,
                    'type'         => $group->group_type,
                    'post'         => $group->post?->name,
                    'shift'        => $group->shift?->name,
                    'member_count' => $group->members()->count(),
                ],
                'history'      => $history,
                'statistics'   => $statistics,
                'period'       => [
                    'start' => $startDate->format('Y-m-d'),
                    'end'   => $endDate->format('Y-m-d'),
                ],
                'total_events' => count($history),
            ];

            if ($request->expectsJson() || $request->wantsJson()) {
                return $this->success($request, 'OK', $data);
            }

            return view('security.rotation-history.group', $data);
        } catch (\Throwable $e) {
            Log::error('Failed to get group rotation history: ' . $e->getMessage());
            return $this->failure($request, 'Failed to load group rotation history.', 500);
        }
    }

    // ============================================================
    // FOR POST
    // ============================================================

    public function forPost(Request $request, $postId)
    {
        $this->authorizeManage();

        try {
            $post = SecurityPost::findOrFail($postId);

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subMonths(3);
            $endDate   = $request->end_date   ? Carbon::parse($request->end_date)   : now();

            $schedules = SecuritySchedule::where('security_post_id', $postId)
                ->where(fn($q) => $q->where('is_rotated', true)
                                    ->orWhereNotNull('rotated_from_user_id')
                                    ->orWhereNotNull('rotation_data'))
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->with(['shift', 'securityUser', 'rotatedFromUser', 'rotationGroup'])
                ->orderByDesc('assignment_date')
                ->get();

            $history          = [];
            $rotationsByShift = [];
            $rotationsByDate  = [];
            $rotationsByGroup = [];

            foreach ($schedules as $schedule) {
                $shiftName = $schedule->shift?->name ?? 'Unknown';
                $groupName = $schedule->rotationGroup?->name ?? 'None';
                $dateKey   = $schedule->assignment_date->format('Y-m-d');

                if ($schedule->is_rotated && $schedule->rotated_at) {
                    $history[] = [
                        'id'            => 'rotation_' . $schedule->id,
                        'type'          => 'rotation',
                        'date'          => $schedule->rotated_at->format('Y-m-d H:i:s'),
                        'schedule_date' => $dateKey,
                        'shift'         => $shiftName,
                        'shift_id'      => $schedule->security_shift_id,
                        'group'         => $groupName,
                        'group_id'      => $schedule->rotation_group_id,
                        'from_user_id'  => $schedule->rotated_from_user_id,
                        'from_user_name'=> $schedule->rotatedFromUser?->name,
                        'to_user_id'    => $schedule->security_user_id,
                        'to_user_name'  => $schedule->securityUser?->name,
                        'reason'        => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                    ];

                    $rotationsByShift[$shiftName] = ($rotationsByShift[$shiftName] ?? 0) + 1;
                    $rotationsByDate[$dateKey]    = ($rotationsByDate[$dateKey] ?? 0) + 1;
                    $rotationsByGroup[$groupName] = ($rotationsByGroup[$groupName] ?? 0) + 1;
                }

                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        $item['schedule_id']   = $schedule->id;
                        $item['schedule_date'] = $dateKey;
                        $item['shift']         = $shiftName;
                        $item['group']         = $groupName;
                        $history[]             = $item;

                        $rotationsByShift[$shiftName] = ($rotationsByShift[$shiftName] ?? 0) + 1;
                        $rotationsByDate[$dateKey]    = ($rotationsByDate[$dateKey] ?? 0) + 1;
                        $rotationsByGroup[$groupName] = ($rotationsByGroup[$groupName] ?? 0) + 1;
                    }
                }
            }

            $rotationGroups = RotationGroup::where('security_post_id', $postId)
                ->with(['shift'])
                ->get();

            usort($history, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

            $statistics = $this->calculatePostHistoryStatistics($history, $schedules, $post);

            $data = [
                'post' => [
                    'id'             => $post->id,
                    'name'           => $post->name,
                    'code'           => $post->code,
                    'type'           => $post->type,
                    'max_personnel'  => $post->max_personnel,
                ],
                'history'         => $history,
                'rotation_groups' => $rotationGroups,
                'statistics'      => $statistics,
                'breakdown'       => [
                    'by_shift' => $rotationsByShift,
                    'by_date'  => $rotationsByDate,
                    'by_group' => $rotationsByGroup,
                ],
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end'   => $endDate->format('Y-m-d'),
                ],
                'total_events' => count($history),
            ];

            if ($request->expectsJson() || $request->wantsJson()) {
                return $this->success($request, 'OK', $data);
            }

            return view('security.rotation-history.post', $data);
        } catch (\Throwable $e) {
            Log::error('Failed to get post rotation history: ' . $e->getMessage());
            return $this->failure($request, 'Failed to load post rotation history.', 500);
        }
    }

    // ============================================================
    // BY DATE RANGE
    // ============================================================

    public function byDateRange(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'post_id'    => 'nullable|exists:security_posts,id',
            'shift_id'   => 'nullable|exists:security_shifts,id',
            'group_id'   => 'nullable|exists:rotation_groups,id',
            'user_id'    => 'nullable|exists:users,id',
            'per_page'   => 'nullable|integer|min:10|max:100',
        ]);

        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate   = Carbon::parse($request->end_date);
            $perPage   = (int) ($request->per_page ?? 20);

            $query = SecuritySchedule::where(fn($q) => $q->where('is_rotated', true)
                                                    ->orWhereNotNull('rotated_from_user_id')
                                                    ->orWhereNotNull('rotation_data'))
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->with(['post', 'shift', 'securityUser', 'rotatedFromUser', 'rotationGroup']);

            if ($request->filled('post_id'))  $query->where('security_post_id', $request->post_id);
            if ($request->filled('shift_id')) $query->where('security_shift_id', $request->shift_id);
            if ($request->filled('group_id')) $query->where('rotation_group_id', $request->group_id);

            if ($request->filled('user_id')) {
                $query->where(fn($q) => $q->where('security_user_id', $request->user_id)
                                          ->orWhere('rotated_from_user_id', $request->user_id));
            }

            $schedules = $query->orderByDesc('assignment_date')
                ->orderByDesc('rotated_at')
                ->paginate($perPage)
                ->withQueryString();

            $history        = [];
            $rotationsByDay = [];

            foreach ($schedules as $schedule) {
                $dateKey = $schedule->assignment_date->format('Y-m-d');

                if ($schedule->is_rotated && $schedule->rotated_at) {
                    $history[] = [
                        'id'            => 'rotation_' . $schedule->id,
                        'type'          => 'rotation',
                        'date'          => $schedule->rotated_at->format('Y-m-d H:i:s'),
                        'schedule_date' => $dateKey,
                        'post'          => $schedule->post?->name,
                        'shift'         => $schedule->shift?->name,
                        'from_user'     => $schedule->rotatedFromUser?->name,
                        'to_user'       => $schedule->securityUser?->name,
                        'group'         => $schedule->rotationGroup?->name,
                        'reason'        => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                    ];
                    $rotationsByDay[$dateKey] = ($rotationsByDay[$dateKey] ?? 0) + 1;
                }

                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        $item['schedule_date'] = $dateKey;
                        $item['post']          = $schedule->post?->name;
                        $item['shift']         = $schedule->shift?->name;
                        $item['group']         = $schedule->rotationGroup?->name;
                        $history[]             = $item;
                        $rotationsByDay[$dateKey] = ($rotationsByDay[$dateKey] ?? 0) + 1;
                    }
                }
            }

            $summary = [
                'total_rotations' => $schedules->total(),
                'unique_days'     => count($rotationsByDay),
                'avg_per_day'     => count($rotationsByDay) > 0
                    ? round($schedules->total() / count($rotationsByDay), 2) : 0,
                'max_in_day'      => !empty($rotationsByDay) ? max($rotationsByDay) : 0,
                'rotations_by_day'=> $rotationsByDay,
            ];

            $data = [
                'history'        => $schedules,
                'rotations_list' => $history,
                'summary'        => $summary,
                'period'         => [
                    'start'      => $startDate->format('Y-m-d'),
                    'end'        => $endDate->format('Y-m-d'),
                    'total_days' => $startDate->diffInDays($endDate) + 1,
                ],
                'filters'      => $request->only(['post_id', 'shift_id', 'group_id', 'user_id']),
                'total_events' => count($history),
            ];

            if ($request->expectsJson() || $request->wantsJson()) {
                return $this->success($request, 'OK', $data);
            }

            return view('security.rotation-history.date-range', $data);
        } catch (\Throwable $e) {
            Log::error('Failed to get rotation history by date range: ' . $e->getMessage());
            return $this->failure($request, 'Failed to load rotation history.', 500);
        }
    }

    // ============================================================
    // STATISTICS
    // ============================================================

    public function getStatistics(Request $request)
    {
        $this->authorizeManage();

        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
            $endDate   = $request->end_date   ? Carbon::parse($request->end_date)   : now()->endOfMonth();

            $statistics = [
                'overall'       => $this->getOverallRotationStats($startDate, $endDate),
                'by_post'       => $this->getRotationStatsByPost($startDate, $endDate),
                'by_shift'      => $this->getRotationStatsByShift($startDate, $endDate),
                'by_group'      => $this->getRotationStatsByGroup($startDate, $endDate),
                'by_user'       => $this->getRotationStatsByUser($startDate, $endDate),
                'trends'        => $this->getRotationTrends($startDate, $endDate),
                'effectiveness' => $this->getRotationEffectiveness($startDate, $endDate),
                'timing'        => $this->getRotationTimingStats($startDate, $endDate),
            ];

            return $this->success($request, 'OK', [
                'statistics' => $statistics,
                'period'     => [
                    'start' => $startDate->format('Y-m-d'),
                    'end'   => $endDate->format('Y-m-d'),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get rotation statistics: ' . $e->getMessage());
            return $this->failure($request, 'Failed to load rotation statistics.', 500);
        }
    }

    // ============================================================
    // EXPORT
    // ============================================================

    public function export(Request $request)
    {
        $this->authorizeManage();

        $validator = Validator::make($request->all(), [
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'format'          => 'required|in:csv,excel,pdf',
            'post_id'         => 'nullable|exists:security_posts,id',
            'shift_id'        => 'nullable|exists:security_shifts,id',
            'group_id'        => 'nullable|exists:rotation_groups,id',
            'include_details' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate   = Carbon::parse($request->end_date);

            $history  = $this->getRotationDataForExport($startDate, $endDate, $request);
            $filename = 'rotation_history_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d');
            $summary  = $this->getExportSummary($history, $startDate, $endDate);

            // CSV works today; Excel/PDF fall back to CSV until libs are wired
            return match ($request->format) {
                'excel' => $this->exportAsCsv($history, $summary, $filename),
                'pdf'   => $this->exportAsCsv($history, $summary, $filename),
                default => $this->exportAsCsv($history, $summary, $filename),
            };
        } catch (\Throwable $e) {
            Log::error('Failed to export rotation history: ' . $e->getMessage());
            return $this->failure($request, 'Failed to export rotation history.', 500);
        }
    }

    // ============================================================
    // TIMELINE
    // ============================================================

    public function getTimeline(Request $request)
    {
        $this->authorizeManage();

        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDays(30);
            $endDate   = $request->end_date   ? Carbon::parse($request->end_date)   : now();

            $rotations = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
                ->where('is_rotated', true)
                ->with(['post', 'shift', 'securityUser', 'rotatedFromUser'])
                ->orderBy('rotated_at')
                ->get();

            $timeline   = [];
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dayRotations = $rotations->filter(fn($r) => $r->rotated_at
                    && $r->rotated_at->format('Y-m-d') === $currentDate->format('Y-m-d'));

                $timeline[] = [
                    'date'        => $currentDate->format('Y-m-d'),
                    'day_of_week' => $currentDate->format('l'),
                    'rotations'   => $dayRotations->count(),
                    'details'     => $request->boolean('include_details')
                        ? $dayRotations->map(fn($r) => [
                            'time'   => $r->rotated_at->format('H:i'),
                            'post'   => $r->post?->name,
                            'shift'  => $r->shift?->name,
                            'from'   => $r->rotatedFromUser?->name,
                            'to'     => $r->securityUser?->name,
                        ])
                        : [],
                ];

                $currentDate->addDay();
            }

            $totalDays = max($startDate->diffInDays($endDate), 1);

            return $this->success($request, 'OK', [
                'timeline' => $timeline,
                'summary'  => [
                    'total_rotations' => $rotations->count(),
                    'avg_per_day'     => round($rotations->count() / $totalDays, 2),
                    'busiest_day'     => collect($timeline)->sortByDesc('rotations')->first(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get rotation timeline: ' . $e->getMessage());
            return $this->failure($request, 'Failed to load rotation timeline.', 500);
        }
    }

    // ============================================================
    // DASHBOARD STATS
    // ============================================================

    private function getDashboardStatistics($startDate, $endDate): array
    {
        $totalRotations = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->count();

        $uniqueGroups = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->whereNotNull('rotation_group_id')
            ->distinct('rotation_group_id')
            ->count('rotation_group_id');

        $uniquePersonnel = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->distinct('security_user_id')
            ->count('security_user_id');

        $uniquePosts = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->distinct('security_post_id')
            ->count('security_post_id');

        $avgPreference = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->whereNotNull('rotation_preference_score')
            ->avg('rotation_preference_score') ?? 0;

        $successful = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->where('status', 'completed')
            ->count();

        $failed = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->whereIn('status', ['absent', 'cancelled'])
            ->count();

        $totalSchedules = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])->count();

        return [
            'total_rotations'      => $totalRotations,
            'unique_groups'        => $uniqueGroups,
            'unique_personnel'     => $uniquePersonnel,
            'unique_posts'         => $uniquePosts,
            'avg_preference_score' => round($avgPreference, 1),
            'successful_rotations' => $successful,
            'failed_rotations'     => $failed,
            'success_rate'         => $totalRotations > 0 ? round(($successful / $totalRotations) * 100, 2) : 0,
            'total_schedules'      => $totalSchedules,
            'rotation_rate'        => $totalSchedules > 0 ? round(($totalRotations / $totalSchedules) * 100, 2) : 0,
        ];
    }

    private function getRecentRotations($limit = 50)
    {
        return SecuritySchedule::where('is_rotated', true)
            ->whereNotNull('rotated_at')
            ->with(['post', 'shift', 'securityUser', 'rotatedFromUser', 'rotationGroup'])
            ->orderByDesc('rotated_at')
            ->limit($limit)
            ->get()
            ->map(fn($s) => [
                'id'            => $s->id,
                'date'          => $s->rotated_at->format('Y-m-d H:i:s'),
                'schedule_date' => $s->assignment_date->format('Y-m-d'),
                'post'          => $s->post?->name,
                'shift'         => $s->shift?->name,
                'from_user'     => $s->rotatedFromUser?->name,
                'to_user'       => $s->securityUser?->name,
                'group'         => $s->rotationGroup?->name,
                'reason'        => $s->rotation_data['reason'] ?? 'Scheduled rotation',
            ]);
    }

    private function getTopRotatingGroups($startDate, $endDate, $limit = 10)
    {
        return RotationGroup::withCount(['schedules' => fn($q) => $q
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->where('is_rotated', true)])
            ->having('schedules_count', '>', 0)
            ->orderByDesc('schedules_count')
            ->limit($limit)
            ->get()
            ->map(fn($g) => [
                'id'             => $g->id,
                'name'           => $g->name,
                'type'           => $g->group_type,
                'rotation_count' => $g->schedules_count,
                'member_count'   => $g->members()->count(),
            ]);
    }

    private function getTopRotatingPersonnel($startDate, $endDate, $limit = 10)
    {
        return DB::table('security_schedules')
            ->join('users', 'security_schedules.security_user_id', '=', 'users.id')
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->select('users.id', 'users.name', DB::raw('count(*) as rotation_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('rotation_count')
            ->limit($limit)
            ->get();
    }

    private function getRotationTrends($startDate, $endDate): array
    {
        $trends       = [];
        $currentDate  = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $count = SecuritySchedule::whereDate('assignment_date', $currentDate)
                ->where('is_rotated', true)
                ->count();

            $trends[] = [
                'date'        => $currentDate->format('Y-m-d'),
                'rotations'   => $count,
                'day_of_week' => $currentDate->format('l'),
            ];

            $currentDate->addDay();
        }

        // 7-day moving average
        $withAvg = [];
        foreach ($trends as $i => $t) {
            $sum   = $t['rotations'];
            $n     = 1;
            for ($j = max(0, $i - 3); $j <= min(count($trends) - 1, $i + 3); $j++) {
                if ($j !== $i) {
                    $sum += $trends[$j]['rotations'];
                    $n++;
                }
            }
            $t['moving_avg'] = round($sum / $n, 1);
            $withAvg[]       = $t;
        }

        return $withAvg;
    }

    // ============================================================
    // GROUP / SWAP HELPERS
    // ============================================================

    private function getGroupRotationHistory($groupId, $excludeScheduleId = null): array
    {
        $group = RotationGroup::find($groupId);
        if (!$group) return [];

        $history = [];

        if ($group->rotation_config && isset($group->rotation_config['rotation_history'])) {
            foreach ($group->rotation_config['rotation_history'] as $item) {
                $item['type']       = 'group_rotation';
                $item['group_name'] = $group->name;
                $item['group_id']   = $group->id;
                $history[]          = $item;
            }
        }

        $schedules = SecuritySchedule::where('rotation_group_id', $groupId)
            ->when($excludeScheduleId, fn($q) => $q->where('id', '!=', $excludeScheduleId))
            ->where(fn($q) => $q->where('is_rotated', true)->orWhereNotNull('rotated_from_user_id'))
            ->with(['securityUser', 'rotatedFromUser'])
            ->limit(50)
            ->get();

        foreach ($schedules as $schedule) {
            if ($schedule->rotated_at) {
                $history[] = [
                    'id'             => 'group_schedule_' . $schedule->id,
                    'type'           => 'group_schedule_rotation',
                    'date'           => $schedule->rotated_at->format('Y-m-d H:i:s'),
                    'schedule_id'    => $schedule->id,
                    'schedule_date'  => $schedule->assignment_date->format('Y-m-d'),
                    'from_user_id'   => $schedule->rotated_from_user_id,
                    'from_user_name' => $schedule->rotatedFromUser?->name,
                    'to_user_id'     => $schedule->security_user_id,
                    'to_user_name'   => $schedule->securityUser?->name,
                    'group_id'       => $group->id,
                    'group_name'     => $group->name,
                ];
            }
        }

        return $history;
    }

    private function getSwapHistory($scheduleId): array
    {
        $schedule = SecuritySchedule::find($scheduleId);
        if (!$schedule) return [];

        $history = [];

        $swaps = SecuritySchedule::whereDate('assignment_date', $schedule->assignment_date)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('id', '!=', $scheduleId)
            ->where(fn($q) => $q->where('is_rotated', true)->orWhereNotNull('rotated_from_user_id'))
            ->with(['securityUser', 'rotatedFromUser'])
            ->get();

        foreach ($swaps as $swap) {
            if ($swap->rotated_at) {
                $history[] = [
                    'id'             => 'swap_' . $swap->id,
                    'type'           => 'related_swap',
                    'date'           => $swap->rotated_at->format('Y-m-d H:i:s'),
                    'schedule_id'    => $swap->id,
                    'schedule_date'  => $swap->assignment_date->format('Y-m-d'),
                    'from_user_id'   => $swap->rotated_from_user_id,
                    'from_user_name' => $swap->rotatedFromUser?->name,
                    'to_user_id'     => $swap->security_user_id,
                    'to_user_name'   => $swap->securityUser?->name,
                ];
            }
        }

        return $history;
    }

    // ============================================================
    // STATISTICS BUILDERS
    // ============================================================

    private function calculateScheduleHistoryStatistics($history, $schedule): array
    {
        $rotations = array_filter($history, fn($i) => in_array($i['type'] ?? '', [
            'rotation', 'group_rotation', 'group_schedule_rotation', 'historical',
        ], true));

        $swaps = array_filter($history, fn($i) => in_array($i['type'] ?? '', ['swap', 'related_swap'], true));

        $fromUsers = [];
        $toUsers   = [];
        foreach ($history as $item) {
            if (isset($item['from_user_id'])) $fromUsers[$item['from_user_id']] = true;
            if (isset($item['to_user_id']))   $toUsers[$item['to_user_id']]     = true;
        }

        return [
            'total_rotations'           => count($rotations),
            'total_swaps'               => count($swaps),
            'total_events'              => count($history),
            'first_rotation'            => !empty($rotations) ? min(array_column($rotations, 'date')) : null,
            'last_rotation'             => !empty($rotations) ? max(array_column($rotations, 'date')) : null,
            'unique_personnel_involved' => count($fromUsers) + count($toUsers),
            'unique_from_users'         => count($fromUsers),
            'unique_to_users'           => count($toUsers),
            'preference_score_avg'      => $schedule->rotation_preference_score ?? 5,
            'swap_count'                => $schedule->rotation_swap_count ?? 0,
        ];
    }

    private function calculateUserRotationStatistics($history, $userId, $rotationStats): array
    {
        $rotationsIn  = array_filter($history, fn($i) => ($i['type'] ?? '') === 'rotated_in');
        $rotationsOut = array_filter($history, fn($i) => ($i['type'] ?? '') === 'rotated_out');

        $shifts = array_unique(array_column($history, 'shift'));
        $posts  = array_unique(array_column($history, 'post'));
        $groups = array_unique(array_column($history, 'group'));
        $dates  = array_unique(array_column($history, 'schedule_date'));
        sort($dates);

        return [
            'total_rotations_in'       => count($rotationsIn),
            'total_rotations_out'      => count($rotationsOut),
            'net_rotations'            => count($rotationsIn) - count($rotationsOut),
            'unique_shifts'            => count($shifts),
            'unique_posts'             => count($posts),
            'unique_groups'            => count($groups),
            'first_rotation'           => !empty($history) ? min(array_column($history, 'date')) : null,
            'last_rotation'            => !empty($history) ? max(array_column($history, 'date')) : null,
            'rotation_frequency_days'  => $this->calculateAverageFrequency($dates),
            'by_post'                  => $rotationStats['by_post']  ?? [],
            'by_shift'                 => $rotationStats['by_shift'] ?? [],
            'by_group'                 => $rotationStats['by_group'] ?? [],
        ];
    }

    private function calculateGroupHistoryStatistics($history, $group): array
    {
        $totalEvents     = count($history);
        $uniqueDates     = array_unique(array_column($history, 'schedule_date'));
        $uniquePersonnel = [];

        foreach ($history as $item) {
            if (isset($item['from_user_id'])) $uniquePersonnel[$item['from_user_id']] = true;
            if (isset($item['to_user_id']))   $uniquePersonnel[$item['to_user_id']]     = true;
        }

        $rotationsByType = [];
        foreach ($history as $item) {
            $type = $item['type'] ?? 'unknown';
            $rotationsByType[$type] = ($rotationsByType[$type] ?? 0) + 1;
        }

        $rotationConfig   = $group->rotation_config ?? [];
        $rotationStats    = $rotationConfig['rotation_stats'] ?? [];
        $totalRotations   = $rotationStats['total_rotations'] ?? 0;
        $successful       = $rotationStats['successful_rotations'] ?? 0;

        return [
            'total_events'      => $totalEvents,
            'unique_dates'      => count($uniqueDates),
            'unique_personnel'  => count($uniquePersonnel),
            'avg_per_day'       => count($uniqueDates) > 0 ? round($totalEvents / count($uniqueDates), 2) : 0,
            'by_type'           => $rotationsByType,
            'last_rotation'     => $group->last_rotated_at,
            'next_rotation'     => $this->getNextScheduledRotation($group),
            'total_rotations'   => $totalRotations,
            'success_rate'      => $totalRotations > 0 ? round(($successful / $totalRotations) * 100, 2) : 0,
        ];
    }

    private function calculatePostHistoryStatistics($history, $schedules, $post): array
    {
        $totalSchedules   = $schedules->count();
        $rotatedSchedules = $schedules->where('is_rotated', true)->count();

        $uniquePersonnel = [];
        foreach ($history as $item) {
            if (isset($item['from_user_id'])) $uniquePersonnel[$item['from_user_id']] = true;
            if (isset($item['to_user_id']))   $uniquePersonnel[$item['to_user_id']]     = true;
        }

        $dates = array_unique(array_column($history, 'schedule_date'));

        return [
            'total_schedules'           => $totalSchedules,
            'rotated_schedules'         => $rotatedSchedules,
            'rotation_rate'             => $totalSchedules > 0 ? round(($rotatedSchedules / $totalSchedules) * 100, 2) : 0,
            'total_rotation_events'     => count($history),
            'unique_dates'              => count($dates),
            'unique_personnel_involved' => count($uniquePersonnel),
            'avg_preference_score'      => round($schedules->avg('rotation_preference_score') ?? 5, 1),
            'avg_per_day'               => count($dates) > 0 ? round(count($history) / count($dates), 2) : 0,
        ];
    }

    // ============================================================
    // SEGMENTED STATS
    // ============================================================

    private function getOverallRotationStats($startDate, $endDate): array
    {
        $rotated    = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)->get();
        $totalSched = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])->count();

        $completed = $rotated->where('status', 'completed')->count();
        $absent    = $rotated->where('status', 'absent')->count();

        return [
            'total_rotations'      => $rotated->count(),
            'unique_personnel'     => $rotated->unique('security_user_id')->count(),
            'unique_posts'         => $rotated->unique('security_post_id')->count(),
            'unique_groups'        => $rotated->whereNotNull('rotation_group_id')->unique('rotation_group_id')->count(),
            'rotation_rate'        => $totalSched > 0 ? round(($rotated->count() / $totalSched) * 100, 2) : 0,
            'avg_preference_score' => round($rotated->avg('rotation_preference_score') ?? 5, 1),
            'completion_rate'      => $rotated->count() > 0 ? round(($completed / $rotated->count()) * 100, 2) : 0,
            'absent_rate'          => $rotated->count() > 0 ? round(($absent / $rotated->count()) * 100, 2) : 0,
        ];
    }

    private function getRotationStatsByPost($startDate, $endDate)
    {
        return SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->select('security_post_id', DB::raw('count(*) as rotation_count'))
            ->with('post:id,name')
            ->groupBy('security_post_id')
            ->get()
            ->map(fn($i) => [
                'post_id'        => $i->security_post_id,
                'post_name'      => $i->post?->name ?? 'Unknown',
                'rotation_count' => $i->rotation_count,
            ]);
    }

    private function getRotationStatsByShift($startDate, $endDate)
    {
        return SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->select('security_shift_id', DB::raw('count(*) as rotation_count'))
            ->with('shift:id,name,category')
            ->groupBy('security_shift_id')
            ->get()
            ->map(fn($i) => [
                'shift_id'       => $i->security_shift_id,
                'shift_name'     => $i->shift?->name ?? 'Unknown',
                'category'       => $i->shift?->category ?? 'Unknown',
                'rotation_count' => $i->rotation_count,
            ]);
    }

    private function getRotationStatsByGroup($startDate, $endDate)
    {
        return RotationGroup::withCount(['schedules' => fn($q) => $q
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->where('is_rotated', true)])
            ->having('schedules_count', '>', 0)
            ->get()
            ->map(fn($g) => [
                'group_id'       => $g->id,
                'group_name'     => $g->name,
                'rotation_count' => $g->schedules_count,
                'type'           => $g->group_type,
            ]);
    }

    private function getRotationStatsByUser($startDate, $endDate)
    {
        return DB::table('security_schedules')
            ->join('users', 'security_schedules.security_user_id', '=', 'users.id')
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->select('users.id', 'users.name', DB::raw('count(*) as rotation_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('rotation_count')
            ->limit(20)
            ->get();
    }

    private function getRotationEffectiveness($startDate, $endDate): array
    {
        $rotated    = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])->where('is_rotated', true)->get();
        $nonRotated = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])->where('is_rotated', false)->get();

        $rComplete = $rotated->where('status', 'completed')->count();
        $nComplete = $nonRotated->where('status', 'completed')->count();
        $rLate     = $rotated->where('late_minutes', '>', 0)->count();
        $nLate     = $nonRotated->where('late_minutes', '>', 0)->count();

        return [
            'rotated' => [
                'count'           => $rotated->count(),
                'completed'       => $rComplete,
                'completion_rate' => $rotated->count() > 0 ? round(($rComplete / $rotated->count()) * 100, 2) : 0,
                'late_rate'       => $rotated->count() > 0 ? round(($rLate / $rotated->count()) * 100, 2) : 0,
                'avg_late_minutes'=> round($rotated->avg('late_minutes') ?? 0, 1),
                'avg_overtime'    => round($rotated->avg('overtime_minutes') ?? 0, 1),
            ],
            'non_rotated' => [
                'count'           => $nonRotated->count(),
                'completed'       => $nComplete,
                'completion_rate' => $nonRotated->count() > 0 ? round(($nComplete / $nonRotated->count()) * 100, 2) : 0,
                'late_rate'       => $nonRotated->count() > 0 ? round(($nLate / $nonRotated->count()) * 100, 2) : 0,
                'avg_late_minutes'=> round($nonRotated->avg('late_minutes') ?? 0, 1),
                'avg_overtime'    => round($nonRotated->avg('overtime_minutes') ?? 0, 1),
            ],
            'improvement' => [
                'completion_rate' => $this->calculateImprovement(
                    $nonRotated->count() > 0 ? ($nComplete / $nonRotated->count()) * 100 : 0,
                    $rotated->count() > 0 ? ($rComplete / $rotated->count()) * 100 : 0
                ),
                'late_rate' => $this->calculateImprovement(
                    $nonRotated->count() > 0 ? ($nLate / $nonRotated->count()) * 100 : 0,
                    $rotated->count() > 0 ? ($rLate / $rotated->count()) * 100 : 0,
                    true
                ),
            ],
        ];
    }

    private function getRotationTimingStats($startDate, $endDate): array
    {
        $rotations = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->whereNotNull('rotated_at')
            ->get();

        if ($rotations->isEmpty()) {
            return [
                'avg_hour'       => null,
                'peak_hour'      => null,
                'by_hour'        => [],
                'by_day_of_week' => [],
                'peak_day'       => null,
            ];
        }

        $byHour = [];
        $byDay  = [];
        foreach ($rotations as $r) {
            $hour = $r->rotated_at->format('H');
            $day  = $r->rotated_at->format('l');
            $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;
            $byDay[$day]   = ($byDay[$day]   ?? 0) + 1;
        }
        ksort($byHour);

        $sum = 0;
        $total = 0;
        foreach ($byHour as $hour => $count) {
            $sum   += $hour * $count;
            $total += $count;
        }
        $avgHour  = $total > 0 ? round($sum / $total, 1) : null;
        $peakHour = !empty($byHour) ? array_search(max($byHour), $byHour) : null;
        $peakDay  = !empty($byDay)  ? array_search(max($byDay),  $byDay)  : null;

        return [
            'avg_hour'       => $avgHour,
            'peak_hour'      => $peakHour,
            'by_hour'        => $byHour,
            'by_day_of_week' => $byDay,
            'peak_day'       => $peakDay,
        ];
    }

    // ============================================================
    // EXPORT HELPERS
    // ============================================================

    private function getRotationDataForExport($startDate, $endDate, $request)
    {
        $query = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where(fn($q) => $q->where('is_rotated', true)->orWhereNotNull('rotated_from_user_id'))
            ->with(['post', 'shift', 'securityUser', 'rotatedFromUser', 'rotationGroup']);

        if ($request->filled('post_id'))  $query->where('security_post_id', $request->post_id);
        if ($request->filled('shift_id')) $query->where('security_shift_id', $request->shift_id);
        if ($request->filled('group_id')) $query->where('rotation_group_id', $request->group_id);

        return $query->orderBy('assignment_date')->orderBy('rotated_at')->get();
    }

    private function getExportSummary($data, $startDate, $endDate): array
    {
        $total = $data->count();

        return [
            'period'             => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'total_rotations'    => $total,
            'unique_posts'       => $data->unique('security_post_id')->count(),
            'unique_personnel'   => $data->unique('security_user_id')->count(),
            'unique_groups'      => $data->whereNotNull('rotation_group_id')->unique('rotation_group_id')->count(),
            'completed_rotations'=> $data->where('status', 'completed')->count(),
            'absent_rotations'   => $data->where('status', 'absent')->count(),
            'success_rate'       => $total > 0 ? round(($data->where('status', 'completed')->count() / $total) * 100, 2) . '%' : '0%',
            'generated_at'       => now()->format('Y-m-d H:i:s'),
            'generated_by'       => auth()->user()?->name ?? 'System',
        ];
    }

    private function exportAsCsv($data, $summary, $filename)
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ];

        $callback = function () use ($data, $summary) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($fh, ['ROTATION HISTORY EXPORT']);
            fputcsv($fh, ['Generated:', $summary['generated_at']]);
            fputcsv($fh, ['Period:',    $summary['period']]);
            fputcsv($fh, []);
            fputcsv($fh, ['SUMMARY STATISTICS']);
            fputcsv($fh, ['Total Rotations:',    $summary['total_rotations']]);
            fputcsv($fh, ['Unique Posts:',       $summary['unique_posts']]);
            fputcsv($fh, ['Unique Personnel:',   $summary['unique_personnel']]);
            fputcsv($fh, ['Unique Groups:',      $summary['unique_groups']]);
            fputcsv($fh, ['Completed Rotations:',$summary['completed_rotations']]);
            fputcsv($fh, ['Success Rate:',       $summary['success_rate']]);
            fputcsv($fh, []);
            fputcsv($fh, ['DETAILED ROTATION HISTORY']);

            fputcsv($fh, [
                'Date', 'Time', 'Schedule Date', 'Post', 'Shift',
                'From User', 'To User', 'Type', 'Reason', 'Group',
                'Preference Score', 'Status',
            ]);

            foreach ($data as $schedule) {
                fputcsv($fh, [
                    $schedule->rotated_at?->format('Y-m-d') ?? $schedule->updated_at->format('Y-m-d'),
                    $schedule->rotated_at?->format('H:i:s') ?? $schedule->updated_at->format('H:i:s'),
                    $schedule->assignment_date->format('Y-m-d'),
                    $schedule->post?->name ?? 'N/A',
                    $schedule->shift?->name ?? 'N/A',
                    $schedule->rotatedFromUser?->name ?? 'N/A',
                    $schedule->securityUser?->name ?? 'N/A',
                    $schedule->is_rotated ? 'Rotation' : 'Swap',
                    $schedule->rotation_data['reason'] ?? 'N/A',
                    $schedule->rotationGroup?->name ?? 'N/A',
                    $schedule->rotation_preference_score ?? 'N/A',
                    $schedule->status ?? 'N/A',
                ]);

                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        fputcsv($fh, [
                            isset($item['date']) ? Carbon::parse($item['date'])->format('Y-m-d') : 'N/A',
                            isset($item['date']) ? Carbon::parse($item['date'])->format('H:i:s') : 'N/A',
                            $schedule->assignment_date->format('Y-m-d'),
                            $schedule->post?->name ?? 'N/A',
                            $schedule->shift?->name ?? 'N/A',
                            $item['from_user_name'] ?? 'N/A',
                            $item['to_user_name'] ?? 'N/A',
                            'Historical',
                            $item['reason'] ?? 'N/A',
                            $item['rotation_group_name'] ?? 'N/A',
                            $item['preference_score'] ?? 'N/A',
                            'Historical',
                        ]);
                    }
                }
            }

            fclose($fh);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ============================================================
    // SMALL HELPERS
    // ============================================================

    private function calculateAverageFrequency($dates): ?float
    {
        if (count($dates) < 2) return null;
        $intervals = [];
        for ($i = 1; $i < count($dates); $i++) {
            $intervals[] = Carbon::parse($dates[$i - 1])->diffInDays(Carbon::parse($dates[$i]));
        }
        return round(array_sum($intervals) / count($intervals), 1);
    }

    private function calculateImprovement($baseline, $current, bool $lowerIsBetter = false): float
    {
        if ($baseline == 0) return 0;
        return $lowerIsBetter
            ? round((($baseline - $current) / $baseline) * 100, 2)
            : round((($current - $baseline) / $baseline) * 100, 2);
    }

    private function getUserName($userId): ?string
    {
        if (!$userId) return null;
        return User::find($userId)?->name;
    }

    private function updateStatsArray(&$stats, $schedule): void
    {
        $postName  = $schedule->post?->name          ?? 'Unknown';
        $shiftName = $schedule->shift?->name         ?? 'Unknown';
        $groupName = $schedule->rotationGroup?->name ?? 'None';

        $stats['by_post'][$postName]   = ($stats['by_post'][$postName]   ?? 0) + 1;
        $stats['by_shift'][$shiftName] = ($stats['by_shift'][$shiftName] ?? 0) + 1;
        $stats['by_group'][$groupName] = ($stats['by_group'][$groupName] ?? 0) + 1;
    }

    private function getNextScheduledRotation($group): ?Carbon
    {
        if (!$group->auto_rotate) return null;

        $config = $group->rotation_config ?? [];
        if (!empty($config['next_rotation_date'])) {
            try {
                return Carbon::parse($config['next_rotation_date']);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
}