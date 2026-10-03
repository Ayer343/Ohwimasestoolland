<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RotationHistory;
use App\Models\SecuritySchedule;
use App\Models\RotationGroup;
use App\Models\User;
use App\Models\SecurityPost;
use App\Models\SecurityShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class RotationHistoryController extends Controller
{
    /**
     * Display rotation history dashboard
     */
    public function index(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

        // Get statistics
        $statistics = $this->getDashboardStatistics($startDate, $endDate);

        // Get recent rotations
        $recentRotations = $this->getRecentRotations(50);

        // Get groups with most rotations
        $topGroups = $this->getTopRotatingGroups($startDate, $endDate, 10);

        // Get personnel with most rotations
        $topPersonnel = $this->getTopRotatingPersonnel($startDate, $endDate, 10);

        // Get rotation trends
        $trends = $this->getRotationTrends($startDate, $endDate);

        return view('admin.rotation-history.index', compact(
            'statistics',
            'recentRotations',
            'topGroups',
            'topPersonnel',
            'trends',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Get rotation history for a specific schedule
     */
    public function forSchedule($scheduleId)
    {
        try {
            $schedule = SecuritySchedule::with([
                'securityUser', 
                'rotatedFromUser', 
                'post', 
                'shift',
                'rotationGroup'
            ])->findOrFail($scheduleId);

            // Get rotation history from rotation_data and related records
            $history = [];

            // Current rotation data
            if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                foreach ($schedule->rotation_data['rotation_history'] as $item) {
                    $history[] = array_merge($item, [
                        'type' => 'historical',
                        'schedule_id' => $schedule->id,
                        'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                        'post' => $schedule->post?->name,
                        'shift' => $schedule->shift?->name,
                        'group' => $schedule->rotationGroup?->name,
                    ]);
                }
            }

            // Add current rotation if exists
            if ($schedule->is_rotated && $schedule->rotated_at) {
                $history[] = [
                    'id' => 'current_' . $schedule->id,
                    'type' => 'current',
                    'date' => $schedule->rotated_at->format('Y-m-d H:i:s'),
                    'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                    'post' => $schedule->post?->name,
                    'shift' => $schedule->shift?->name,
                    'group' => $schedule->rotationGroup?->name,
                    'from_user_id' => $schedule->rotated_from_user_id,
                    'from_user_name' => $schedule->rotatedFromUser?->name,
                    'to_user_id' => $schedule->security_user_id,
                    'to_user_name' => $schedule->securityUser?->name,
                    'reason' => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                    'performed_by' => $schedule->rotation_data['performed_by'] ?? null,
                    'performed_by_name' => $this->getUserName($schedule->rotation_data['performed_by'] ?? null),
                    'rotation_group' => $schedule->rotation_group_id,
                    'rotation_group_name' => $schedule->rotationGroup?->name,
                    'preference_score' => $schedule->rotation_preference_score ?? null,
                    'swap_count' => $schedule->rotation_swap_count ?? 0,
                ];
            }

            // Get related rotation events from the group
            if ($schedule->rotation_group_id) {
                $groupHistory = $this->getGroupRotationHistory($schedule->rotation_group_id, $schedule->id);
                $history = array_merge($history, $groupHistory);
            }

            // Get swap history
            $swapHistory = $this->getSwapHistory($scheduleId);
            $history = array_merge($history, $swapHistory);

            // Sort by date (newest first)
            usort($history, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Calculate statistics
            $statistics = $this->calculateScheduleHistoryStatistics($history, $schedule);

            // Prepare view data
            $data = [
                'schedule' => [
                    'id' => $schedule->id,
                    'date' => $schedule->assignment_date->format('Y-m-d'),
                    'post' => $schedule->post?->name,
                    'shift' => $schedule->shift?->name,
                    'current_user' => $schedule->securityUser?->name,
                    'group' => $schedule->rotationGroup?->name,
                ],
                'history' => $history,
                'statistics' => $statistics,
                'total_events' => count($history)
            ];

            if ($request->wantsJson()) {
                return response()->json(array_merge(['success' => true], $data));
            }

            return view('admin.rotation-history.schedule', $data);

        } catch (\Exception $e) {
            Log::error('Failed to get schedule rotation history: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load rotation history.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to load rotation history.');
        }
    }

    /**
     * Get rotation history for a specific user
     */
    public function forUser(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subMonths(3);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

            // Get schedules where user was involved in rotations
            $schedules = SecuritySchedule::where(function($query) use ($userId) {
                    $query->where('security_user_id', $userId)
                        ->orWhere('rotated_from_user_id', $userId);
                })
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->with(['post', 'shift', 'securityUser', 'rotatedFromUser', 'rotationGroup'])
                ->orderBy('assignment_date', 'desc')
                ->get();

            $history = [];
            $rotationStats = [
                'rotated_in' => 0,
                'rotated_out' => 0,
                'by_post' => [],
                'by_shift' => [],
                'by_group' => [],
            ];

            foreach ($schedules as $schedule) {
                // If user was rotated from this schedule
                if ($schedule->rotated_from_user_id == $userId && $schedule->is_rotated) {
                    $event = [
                        'id' => 'rotated_out_' . $schedule->id,
                        'type' => 'rotated_out',
                        'date' => $schedule->rotated_at?->format('Y-m-d H:i:s') ?? $schedule->updated_at->format('Y-m-d H:i:s'),
                        'schedule_id' => $schedule->id,
                        'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                        'post' => $schedule->post?->name,
                        'post_id' => $schedule->security_post_id,
                        'shift' => $schedule->shift?->name,
                        'shift_id' => $schedule->security_shift_id,
                        'group' => $schedule->rotationGroup?->name,
                        'group_id' => $schedule->rotation_group_id,
                        'from_user_id' => $userId,
                        'from_user_name' => $user->name,
                        'to_user_id' => $schedule->security_user_id,
                        'to_user_name' => $schedule->securityUser?->name,
                        'reason' => $schedule->rotation_data['reason'] ?? 'Rotated out',
                        'rotation_group' => $schedule->rotation_data['rotation_group'] ?? null,
                    ];
                    
                    $history[] = $event;
                    $rotationStats['rotated_out']++;
                    $this->updateStatsArray($rotationStats, $schedule);
                }

                // If user was rotated into this schedule
                if ($schedule->security_user_id == $userId && $schedule->rotated_from_user_id) {
                    $event = [
                        'id' => 'rotated_in_' . $schedule->id,
                        'type' => 'rotated_in',
                        'date' => $schedule->rotated_at?->format('Y-m-d H:i:s') ?? $schedule->created_at->format('Y-m-d H:i:s'),
                        'schedule_id' => $schedule->id,
                        'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                        'post' => $schedule->post?->name,
                        'post_id' => $schedule->security_post_id,
                        'shift' => $schedule->shift?->name,
                        'shift_id' => $schedule->security_shift_id,
                        'group' => $schedule->rotationGroup?->name,
                        'group_id' => $schedule->rotation_group_id,
                        'from_user_id' => $schedule->rotated_from_user_id,
                        'from_user_name' => $schedule->rotatedFromUser?->name,
                        'to_user_id' => $userId,
                        'to_user_name' => $user->name,
                        'reason' => $schedule->rotation_data['reason'] ?? 'Rotated in',
                        'rotation_group' => $schedule->rotation_data['rotation_group'] ?? null,
                    ];
                    
                    $history[] = $event;
                    $rotationStats['rotated_in']++;
                    $this->updateStatsArray($rotationStats, $schedule);
                }

                // Rotation data history
                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        if (($item['from_user_id'] ?? null) == $userId || ($item['to_user_id'] ?? null) == $userId) {
                            $item['schedule_id'] = $schedule->id;
                            $item['schedule_date'] = $schedule->assignment_date->format('Y-m-d');
                            $item['post'] = $schedule->post?->name;
                            $item['shift'] = $schedule->shift?->name;
                            $item['group'] = $schedule->rotationGroup?->name;
                            $history[] = $item;
                            
                            if (($item['from_user_id'] ?? null) == $userId) {
                                $rotationStats['rotated_out']++;
                            } else {
                                $rotationStats['rotated_in']++;
                            }
                        }
                    }
                }
            }

            // Get rotation group memberships
            $groupMemberships = RotationGroup::whereHas('members', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->with(['post', 'shift'])
                ->get();

            // Sort history by date
            usort($history, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Calculate statistics
            $statistics = $this->calculateUserRotationStatistics($history, $userId, $rotationStats);

            $data = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'badge_number' => $user->badge_number,
                ],
                'history' => $history,
                'group_memberships' => $groupMemberships,
                'statistics' => $statistics,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'total_events' => count($history)
            ];

            if ($request->wantsJson()) {
                return response()->json(array_merge(['success' => true], $data));
            }

            return view('admin.rotation-history.user', $data);

        } catch (\Exception $e) {
            Log::error('Failed to get user rotation history: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load user rotation history.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to load user rotation history.');
        }
    }

    /**
     * Get rotation history for a specific group
     */
    public function forGroup(Request $request, $groupId)
    {
        try {
            $group = RotationGroup::with(['post', 'shift'])->findOrFail($groupId);

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subMonths(3);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

            // Get group rotation history from config
            $groupHistory = $group->rotation_config['rotation_history'] ?? [];

            // Get schedule rotations for this group
            $scheduleRotations = SecuritySchedule::where('rotation_group_id', $groupId)
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->where(function($q) {
                    $q->where('is_rotated', true)
                      ->orWhereNotNull('rotated_from_user_id');
                })
                ->with(['securityUser', 'rotatedFromUser', 'post', 'shift'])
                ->orderBy('rotated_at', 'desc')
                ->get();

            $history = [];

            // Add group rotations
            foreach ($groupHistory as $item) {
                $history[] = array_merge($item, [
                    'type' => 'group_rotation',
                    'source' => 'group_config',
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                ]);
            }

            // Add schedule rotations
            foreach ($scheduleRotations as $schedule) {
                if ($schedule->rotated_at) {
                    $history[] = [
                        'id' => 'schedule_' . $schedule->id,
                        'type' => 'schedule_rotation',
                        'source' => 'schedule',
                        'date' => $schedule->rotated_at->format('Y-m-d H:i:s'),
                        'schedule_id' => $schedule->id,
                        'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                        'post' => $schedule->post?->name,
                        'shift' => $schedule->shift?->name,
                        'from_user_id' => $schedule->rotated_from_user_id,
                        'from_user_name' => $schedule->rotatedFromUser?->name,
                        'to_user_id' => $schedule->security_user_id,
                        'to_user_name' => $schedule->securityUser?->name,
                        'reason' => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'preference_score' => $schedule->rotation_preference_score,
                    ];
                }

                // Add historical data from schedule
                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        $item['type'] = 'historical';
                        $item['source'] = 'schedule_history';
                        $item['schedule_id'] = $schedule->id;
                        $item['schedule_date'] = $schedule->assignment_date->format('Y-m-d');
                        $item['post'] = $schedule->post?->name;
                        $item['shift'] = $schedule->shift?->name;
                        $item['group_id'] = $group->id;
                        $item['group_name'] = $group->name;
                        $history[] = $item;
                    }
                }
            }

            // Sort by date
            usort($history, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Calculate statistics
            $statistics = $this->calculateGroupHistoryStatistics($history, $group);

            $data = [
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                    'type' => $group->group_type,
                    'post' => $group->post?->name,
                    'shift' => $group->shift?->name,
                    'member_count' => $group->members()->count(),
                ],
                'history' => $history,
                'statistics' => $statistics,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'total_events' => count($history)
            ];

            if ($request->wantsJson()) {
                return response()->json(array_merge(['success' => true], $data));
            }

            return view('admin.rotation-history.group', $data);

        } catch (\Exception $e) {
            Log::error('Failed to get group rotation history: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load group rotation history.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to load group rotation history.');
        }
    }

    /**
     * Get rotation history for a specific post
     */
    public function forPost(Request $request, $postId)
    {
        try {
            $post = SecurityPost::findOrFail($postId);

            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subMonths(3);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

            // Get schedules for this post with rotation data
            $schedules = SecuritySchedule::where('security_post_id', $postId)
                ->where(function($query) {
                    $query->where('is_rotated', true)
                        ->orWhereNotNull('rotated_from_user_id')
                        ->orWhereNotNull('rotation_data');
                })
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->with(['shift', 'securityUser', 'rotatedFromUser', 'rotationGroup'])
                ->orderBy('assignment_date', 'desc')
                ->get();

            $history = [];
            $rotationsByShift = [];
            $rotationsByDate = [];
            $rotationsByGroup = [];

            foreach ($schedules as $schedule) {
                $shiftName = $schedule->shift?->name ?? 'Unknown';
                $groupName = $schedule->rotationGroup?->name ?? 'None';
                $dateKey = $schedule->assignment_date->format('Y-m-d');
                
                // Current rotation
                if ($schedule->is_rotated && $schedule->rotated_at) {
                    $event = [
                        'id' => 'rotation_' . $schedule->id,
                        'type' => 'rotation',
                        'date' => $schedule->rotated_at->format('Y-m-d H:i:s'),
                        'schedule_date' => $dateKey,
                        'shift' => $shiftName,
                        'shift_id' => $schedule->security_shift_id,
                        'group' => $groupName,
                        'group_id' => $schedule->rotation_group_id,
                        'from_user_id' => $schedule->rotated_from_user_id,
                        'from_user_name' => $schedule->rotatedFromUser?->name,
                        'to_user_id' => $schedule->security_user_id,
                        'to_user_name' => $schedule->securityUser?->name,
                        'reason' => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                    ];
                    
                    $history[] = $event;
                    
                    // Track statistics
                    $rotationsByShift[$shiftName] = ($rotationsByShift[$shiftName] ?? 0) + 1;
                    $rotationsByDate[$dateKey] = ($rotationsByDate[$dateKey] ?? 0) + 1;
                    $rotationsByGroup[$groupName] = ($rotationsByGroup[$groupName] ?? 0) + 1;
                }

                // History from rotation_data
                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        $item['schedule_id'] = $schedule->id;
                        $item['schedule_date'] = $dateKey;
                        $item['shift'] = $shiftName;
                        $item['group'] = $groupName;
                        $history[] = $item;
                        
                        $rotationsByShift[$shiftName] = ($rotationsByShift[$shiftName] ?? 0) + 1;
                        $rotationsByDate[$dateKey] = ($rotationsByDate[$dateKey] ?? 0) + 1;
                        $rotationsByGroup[$groupName] = ($rotationsByGroup[$groupName] ?? 0) + 1;
                    }
                }
            }

            // Get rotation groups for this post
            $rotationGroups = RotationGroup::where('security_post_id', $postId)
                ->with(['shift'])
                ->get();

            // Sort history by date
            usort($history, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Calculate statistics
            $statistics = $this->calculatePostHistoryStatistics($history, $schedules, $post);

            $data = [
                'post' => [
                    'id' => $post->id,
                    'name' => $post->name,
                    'code' => $post->code,
                    'type' => $post->type,
                    'max_personnel' => $post->max_personnel,
                ],
                'history' => $history,
                'rotation_groups' => $rotationGroups,
                'statistics' => $statistics,
                'breakdown' => [
                    'by_shift' => $rotationsByShift,
                    'by_date' => $rotationsByDate,
                    'by_group' => $rotationsByGroup,
                ],
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'total_events' => count($history)
            ];

            if ($request->wantsJson()) {
                return response()->json(array_merge(['success' => true], $data));
            }

            return view('admin.rotation-history.post', $data);

        } catch (\Exception $e) {
            Log::error('Failed to get post rotation history: ' . $e->getMessage(), [
                'post_id' => $postId,
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load post rotation history.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to load post rotation history.');
        }
    }

    /**
     * Get rotation history by date range
     */
    public function byDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'post_id' => 'nullable|exists:security_posts,id',
            'shift_id' => 'nullable|exists:security_shifts,id',
            'group_id' => 'nullable|exists:rotation_groups,id',
            'user_id' => 'nullable|exists:users,id',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $perPage = $request->per_page ?? 20;

            // Build query for schedules with rotation data
            $query = SecuritySchedule::where(function($q) {
                    $q->where('is_rotated', true)
                      ->orWhereNotNull('rotated_from_user_id')
                      ->orWhereNotNull('rotation_data');
                })
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->with(['post', 'shift', 'securityUser', 'rotatedFromUser', 'rotationGroup']);

            // Apply filters
            if ($request->filled('post_id')) {
                $query->where('security_post_id', $request->post_id);
            }

            if ($request->filled('shift_id')) {
                $query->where('security_shift_id', $request->shift_id);
            }

            if ($request->filled('group_id')) {
                $query->where('rotation_group_id', $request->group_id);
            }

            if ($request->filled('user_id')) {
                $query->where(function($q) use ($request) {
                    $q->where('security_user_id', $request->user_id)
                      ->orWhere('rotated_from_user_id', $request->user_id);
                });
            }

            $schedules = $query->orderBy('assignment_date', 'desc')
                ->orderBy('rotated_at', 'desc')
                ->paginate($perPage)
                ->withQueryString();

            // Compile history for display
            $history = [];
            $rotationsByDay = [];

            foreach ($schedules as $schedule) {
                $dateKey = $schedule->assignment_date->format('Y-m-d');
                
                // Current rotation
                if ($schedule->is_rotated && $schedule->rotated_at) {
                    $history[] = [
                        'id' => 'rotation_' . $schedule->id,
                        'type' => 'rotation',
                        'date' => $schedule->rotated_at->format('Y-m-d H:i:s'),
                        'schedule_date' => $dateKey,
                        'post' => $schedule->post?->name,
                        'shift' => $schedule->shift?->name,
                        'from_user' => $schedule->rotatedFromUser?->name,
                        'to_user' => $schedule->securityUser?->name,
                        'group' => $schedule->rotationGroup?->name,
                        'reason' => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                    ];
                    
                    $rotationsByDay[$dateKey] = ($rotationsByDay[$dateKey] ?? 0) + 1;
                }

                // History from rotation_data
                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        $item['schedule_date'] = $dateKey;
                        $item['post'] = $schedule->post?->name;
                        $item['shift'] = $schedule->shift?->name;
                        $item['group'] = $schedule->rotationGroup?->name;
                        $history[] = $item;
                        
                        $rotationsByDay[$dateKey] = ($rotationsByDay[$dateKey] ?? 0) + 1;
                    }
                }
            }

            // Calculate summary statistics
            $summary = [
                'total_rotations' => $schedules->total(),
                'unique_days' => count($rotationsByDay),
                'avg_per_day' => count($rotationsByDay) > 0 ? 
                    round($schedules->total() / count($rotationsByDay), 2) : 0,
                'max_in_day' => max($rotationsByDay) ?: 0,
                'rotations_by_day' => $rotationsByDay,
            ];

            $data = [
                'history' => $schedules,
                'rotations_list' => $history,
                'summary' => $summary,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'total_days' => $startDate->diffInDays($endDate) + 1,
                ],
                'filters' => $request->only(['post_id', 'shift_id', 'group_id', 'user_id']),
                'total_events' => count($history)
            ];

            if ($request->wantsJson()) {
                return response()->json(array_merge(['success' => true], $data));
            }

            return view('admin.rotation-history.date-range', $data);

        } catch (\Exception $e) {
            Log::error('Failed to get rotation history by date range: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load rotation history.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to load rotation history.');
        }
    }

    /**
     * Get rotation statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

            // Overall statistics
            $statistics = [
                'overall' => $this->getOverallRotationStats($startDate, $endDate),
                'by_post' => $this->getRotationStatsByPost($startDate, $endDate),
                'by_shift' => $this->getRotationStatsByShift($startDate, $endDate),
                'by_group' => $this->getRotationStatsByGroup($startDate, $endDate),
                'by_user' => $this->getRotationStatsByUser($startDate, $endDate),
                'trends' => $this->getRotationTrends($startDate, $endDate),
                'effectiveness' => $this->getRotationEffectiveness($startDate, $endDate),
                'timing' => $this->getRotationTimingStats($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'statistics' => $statistics,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get rotation statistics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load rotation statistics.'
            ], 500);
        }
    }

    /**
     * Export rotation history
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:csv,excel,pdf',
            'post_id' => 'nullable|exists:security_posts,id',
            'shift_id' => 'nullable|exists:security_shifts,id',
            'group_id' => 'nullable|exists:rotation_groups,id',
            'include_details' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            // Get rotation data
            $history = $this->getRotationDataForExport($startDate, $endDate, $request);

            // Generate filename
            $filename = 'rotation_history_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d');

            // Add summary sheet
            $summary = $this->getExportSummary($history, $startDate, $endDate);

            switch ($request->format) {
                case 'csv':
                    return $this->exportAsCsv($history, $summary, $filename);
                case 'excel':
                    return $this->exportAsExcel($history, $summary, $filename);
                case 'pdf':
                    return $this->exportAsPdf($history, $summary, $filename);
                default:
                    return $this->exportAsCsv($history, $summary, $filename);
            }

        } catch (\Exception $e) {
            Log::error('Failed to export rotation history: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export rotation history.'
            ], 500);
        }
    }

    /**
     * Get rotation timeline
     */
    public function getTimeline(Request $request)
    {
        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDays(30);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

            $rotations = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
                ->where('is_rotated', true)
                ->with(['post', 'shift', 'securityUser', 'rotatedFromUser'])
                ->orderBy('rotated_at')
                ->get();

            $timeline = [];
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dayRotations = $rotations->filter(function($r) use ($currentDate) {
                    return $r->rotated_at && $r->rotated_at->format('Y-m-d') === $currentDate->format('Y-m-d');
                });

                $timeline[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day_of_week' => $currentDate->format('l'),
                    'rotations' => $dayRotations->count(),
                    'details' => $request->boolean('include_details') ? $dayRotations->map(function($r) {
                        return [
                            'time' => $r->rotated_at->format('H:i'),
                            'post' => $r->post?->name,
                            'shift' => $r->shift?->name,
                            'from' => $r->rotatedFromUser?->name,
                            'to' => $r->securityUser?->name,
                        ];
                    }) : [],
                ];

                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'timeline' => $timeline,
                'summary' => [
                    'total_rotations' => $rotations->count(),
                    'avg_per_day' => round($rotations->count() / $startDate->diffInDays($endDate), 2),
                    'busiest_day' => collect($timeline)->sortByDesc('rotations')->first(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get rotation timeline: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load rotation timeline.'
            ], 500);
        }
    }

    /**
     * Helper Methods
     */

    private function getDashboardStatistics($startDate, $endDate)
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

        $avgPreferenceScore = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->whereNotNull('rotation_preference_score')
            ->avg('rotation_preference_score') ?? 0;

        $successfulRotations = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->where('status', 'completed')
            ->count();

        $failedRotations = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->whereIn('status', ['absent', 'cancelled'])
            ->count();

        return [
            'total_rotations' => $totalRotations,
            'unique_groups' => $uniqueGroups,
            'unique_personnel' => $uniquePersonnel,
            'unique_posts' => $uniquePosts,
            'avg_preference_score' => round($avgPreferenceScore, 1),
            'successful_rotations' => $successfulRotations,
            'failed_rotations' => $failedRotations,
            'success_rate' => $totalRotations > 0 ? 
                round(($successfulRotations / $totalRotations) * 100, 2) : 0,
            'total_schedules' => SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])->count(),
            'rotation_rate' => $totalRotations > 0 ? 
                round(($totalRotations / SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])->count()) * 100, 2) : 0,
        ];
    }

    private function getRecentRotations($limit = 50)
    {
        return SecuritySchedule::where('is_rotated', true)
            ->whereNotNull('rotated_at')
            ->with(['post', 'shift', 'securityUser', 'rotatedFromUser', 'rotationGroup'])
            ->orderBy('rotated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'date' => $schedule->rotated_at->format('Y-m-d H:i:s'),
                    'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                    'post' => $schedule->post?->name,
                    'shift' => $schedule->shift?->name,
                    'from_user' => $schedule->rotatedFromUser?->name,
                    'to_user' => $schedule->securityUser?->name,
                    'group' => $schedule->rotationGroup?->name,
                    'reason' => $schedule->rotation_data['reason'] ?? 'Scheduled rotation',
                ];
            });
    }

    private function getTopRotatingGroups($startDate, $endDate, $limit = 10)
    {
        return RotationGroup::withCount(['schedules' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('assignment_date', [$startDate, $endDate])
                  ->where('is_rotated', true);
            }])
            ->having('schedules_count', '>', 0)
            ->orderBy('schedules_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'type' => $group->group_type,
                    'rotation_count' => $group->schedules_count,
                    'member_count' => $group->members()->count(),
                ];
            });
    }

    private function getTopRotatingPersonnel($startDate, $endDate, $limit = 10)
    {
        return DB::table('security_schedules')
            ->join('users', 'security_schedules.security_user_id', '=', 'users.id')
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->select('users.id', 'users.name', DB::raw('count(*) as rotation_count'))
            ->groupBy('users.id', 'users.name')
            ->orderBy('rotation_count', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getRotationTrends($startDate, $endDate)
    {
        $trends = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $count = SecuritySchedule::whereDate('assignment_date', $currentDate)
                ->where('is_rotated', true)
                ->count();

            $trends[] = [
                'date' => $currentDate->format('Y-m-d'),
                'rotations' => $count,
                'day_of_week' => $currentDate->format('l'),
            ];

            $currentDate->addDay();
        }

        // Calculate moving average (7-day)
        $trendsWithAvg = [];
        foreach ($trends as $i => $trend) {
            $avg = $trend['rotations'];
            $count = 1;
            
            for ($j = max(0, $i - 3); $j <= min(count($trends) - 1, $i + 3); $j++) {
                if ($j != $i) {
                    $avg += $trends[$j]['rotations'];
                    $count++;
                }
            }
            
            $trend['moving_avg'] = round($avg / $count, 1);
            $trendsWithAvg[] = $trend;
        }

        return $trendsWithAvg;
    }

    private function getGroupRotationHistory($groupId, $excludeScheduleId = null)
    {
        $group = RotationGroup::find($groupId);
        if (!$group) return [];

        $history = [];

        // Get group's rotation config history
        if ($group->rotation_config && isset($group->rotation_config['rotation_history'])) {
            foreach ($group->rotation_config['rotation_history'] as $item) {
                $item['type'] = 'group_rotation';
                $item['group_name'] = $group->name;
                $item['group_id'] = $group->id;
                $history[] = $item;
            }
        }

        // Get schedules from this group
        $schedules = SecuritySchedule::where('rotation_group_id', $groupId)
            ->where('id', '!=', $excludeScheduleId)
            ->where(function($q) {
                $q->where('is_rotated', true)
                  ->orWhereNotNull('rotated_from_user_id');
            })
            ->with(['securityUser', 'rotatedFromUser'])
            ->limit(50)
            ->get();

        foreach ($schedules as $schedule) {
            if ($schedule->rotated_at) {
                $history[] = [
                    'id' => 'group_schedule_' . $schedule->id,
                    'type' => 'group_schedule_rotation',
                    'date' => $schedule->rotated_at->format('Y-m-d H:i:s'),
                    'schedule_id' => $schedule->id,
                    'schedule_date' => $schedule->assignment_date->format('Y-m-d'),
                    'from_user_id' => $schedule->rotated_from_user_id,
                    'from_user_name' => $schedule->rotatedFromUser?->name,
                    'to_user_id' => $schedule->security_user_id,
                    'to_user_name' => $schedule->securityUser?->name,
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                ];
            }
        }

        return $history;
    }

    private function getSwapHistory($scheduleId)
    {
        $schedule = SecuritySchedule::find($scheduleId);
        if (!$schedule) return [];

        $history = [];

        // Look for related swaps (same date, different personnel)
        $swaps = SecuritySchedule::whereDate('assignment_date', $schedule->assignment_date)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('id', '!=', $scheduleId)
            ->where(function($q) {
                $q->where('is_rotated', true)
                  ->orWhereNotNull('rotated_from_user_id');
            })
            ->with(['securityUser', 'rotatedFromUser'])
            ->get();

        foreach ($swaps as $swap) {
            if ($swap->rotated_at) {
                $history[] = [
                    'id' => 'swap_' . $swap->id,
                    'type' => 'related_swap',
                    'date' => $swap->rotated_at->format('Y-m-d H:i:s'),
                    'schedule_id' => $swap->id,
                    'schedule_date' => $swap->assignment_date->format('Y-m-d'),
                    'from_user_id' => $swap->rotated_from_user_id,
                    'from_user_name' => $swap->rotatedFromUser?->name,
                    'to_user_id' => $swap->security_user_id,
                    'to_user_name' => $swap->securityUser?->name,
                ];
            }
        }

        return $history;
    }

    private function calculateScheduleHistoryStatistics($history, $schedule)
    {
        $rotations = array_filter($history, function($item) {
            return in_array($item['type'] ?? '', ['rotation', 'group_rotation', 'group_schedule_rotation', 'historical']);
        });

        $swaps = array_filter($history, function($item) {
            return ($item['type'] ?? '') === 'swap' || ($item['type'] ?? '') === 'related_swap';
        });

        $uniqueFromUsers = [];
        $uniqueToUsers = [];
        foreach ($history as $item) {
            if (isset($item['from_user_id'])) $uniqueFromUsers[$item['from_user_id']] = true;
            if (isset($item['to_user_id'])) $uniqueToUsers[$item['to_user_id']] = true;
        }

        return [
            'total_rotations' => count($rotations),
            'total_swaps' => count($swaps),
            'total_events' => count($history),
            'first_rotation' => !empty($rotations) ? min(array_column($rotations, 'date')) : null,
            'last_rotation' => !empty($rotations) ? max(array_column($rotations, 'date')) : null,
            'unique_personnel_involved' => count($uniqueFromUsers) + count($uniqueToUsers),
            'unique_from_users' => count($uniqueFromUsers),
            'unique_to_users' => count($uniqueToUsers),
            'preference_score_avg' => $schedule->rotation_preference_score ?? 5,
            'swap_count' => $schedule->rotation_swap_count ?? 0,
        ];
    }

    private function calculateUserRotationStatistics($history, $userId, $rotationStats)
    {
        $rotationsIn = array_filter($history, function($item) use ($userId) {
            return ($item['type'] ?? '') === 'rotated_in';
        });

        $rotationsOut = array_filter($history, function($item) use ($userId) {
            return ($item['type'] ?? '') === 'rotated_out';
        });

        $shifts = array_unique(array_column($history, 'shift'));
        $posts = array_unique(array_column($history, 'post'));
        $groups = array_unique(array_column($history, 'group'));

        $dates = array_unique(array_column($history, 'schedule_date'));
        sort($dates);

        return [
            'total_rotations_in' => count($rotationsIn),
            'total_rotations_out' => count($rotationsOut),
            'net_rotations' => count($rotationsIn) - count($rotationsOut),
            'unique_shifts' => count($shifts),
            'unique_posts' => count($posts),
            'unique_groups' => count($groups),
            'first_rotation' => !empty($history) ? min(array_column($history, 'date')) : null,
            'last_rotation' => !empty($history) ? max(array_column($history, 'date')) : null,
            'rotation_frequency_days' => $this->calculateAverageFrequency($dates),
            'by_post' => $rotationStats['by_post'] ?? [],
            'by_shift' => $rotationStats['by_shift'] ?? [],
            'by_group' => $rotationStats['by_group'] ?? [],
        ];
    }

    private function calculateGroupHistoryStatistics($history, $group)
{
    $totalEvents = count($history);
    $uniqueDates = array_unique(array_column($history, 'schedule_date'));
    $uniquePersonnel = [];

    foreach ($history as $item) {
        if (isset($item['from_user_id'])) $uniquePersonnel[$item['from_user_id']] = true;
        if (isset($item['to_user_id'])) $uniquePersonnel[$item['to_user_id']] = true;
    }

    $rotationsByType = [];
    foreach ($history as $item) {
        $type = $item['type'] ?? 'unknown';
        $rotationsByType[$type] = ($rotationsByType[$type] ?? 0) + 1;
    }

    // SAFELY ACCESS ROTATION CONFIG
    $rotationConfig = $group->rotation_config ?? []; // Default to empty array if null
    $rotationStats = $rotationConfig['rotation_stats'] ?? []; // Safely access stats
    $totalRotations = $rotationStats['total_rotations'] ?? 0;
    $successfulRotations = $rotationStats['successful_rotations'] ?? 0;

    return [
        'total_events' => $totalEvents,
        'unique_dates' => count($uniqueDates),
        'unique_personnel' => count($uniquePersonnel),
        'avg_per_day' => count($uniqueDates) > 0 ? 
            round($totalEvents / count($uniqueDates), 2) : 0,
        'by_type' => $rotationsByType,
        'last_rotation' => $group->last_rotated_at,
        'next_rotation' => $this->getNextScheduledRotation($group),
        'total_rotations' => $totalRotations,
        'success_rate' => $totalRotations > 0 ?
            round(($successfulRotations / $totalRotations) * 100, 2) : 0,
    ];
}

    private function calculatePostHistoryStatistics($history, $schedules, $post)
    {
        $totalSchedules = $schedules->count();
        $rotatedSchedules = $schedules->where('is_rotated', true)->count();

        $uniquePersonnel = [];
        foreach ($history as $item) {
            if (isset($item['from_user_id'])) $uniquePersonnel[$item['from_user_id']] = true;
            if (isset($item['to_user_id'])) $uniquePersonnel[$item['to_user_id']] = true;
        }

        $dates = array_unique(array_column($history, 'schedule_date'));

        return [
            'total_schedules' => $totalSchedules,
            'rotated_schedules' => $rotatedSchedules,
            'rotation_rate' => $totalSchedules > 0 ? 
                round(($rotatedSchedules / $totalSchedules) * 100, 2) : 0,
            'total_rotation_events' => count($history),
            'unique_dates' => count($dates),
            'unique_personnel_involved' => count($uniquePersonnel),
            'avg_preference_score' => round($schedules->avg('rotation_preference_score') ?? 5, 1),
            'avg_per_day' => count($dates) > 0 ?
                round(count($history) / count($dates), 2) : 0,
        ];
    }

    private function getOverallRotationStats($startDate, $endDate)
    {
        $schedules = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->get();

        $totalSchedules = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])->count();

        $completedAfterRotation = $schedules->where('status', 'completed')->count();
        $absentAfterRotation = $schedules->where('status', 'absent')->count();

        return [
            'total_rotations' => $schedules->count(),
            'unique_personnel' => $schedules->unique('security_user_id')->count(),
            'unique_posts' => $schedules->unique('security_post_id')->count(),
            'unique_groups' => $schedules->whereNotNull('rotation_group_id')->unique('rotation_group_id')->count(),
            'rotation_rate' => $totalSchedules > 0 ? 
                round(($schedules->count() / $totalSchedules) * 100, 2) : 0,
            'avg_preference_score' => round($schedules->avg('rotation_preference_score') ?? 5, 1),
            'completion_rate' => $schedules->count() > 0 ?
                round(($completedAfterRotation / $schedules->count()) * 100, 2) : 0,
            'absent_rate' => $schedules->count() > 0 ?
                round(($absentAfterRotation / $schedules->count()) * 100, 2) : 0,
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
            ->map(function($item) {
                return [
                    'post_id' => $item->security_post_id,
                    'post_name' => $item->post?->name ?? 'Unknown',
                    'rotation_count' => $item->rotation_count,
                ];
            });
    }

    private function getRotationStatsByShift($startDate, $endDate)
    {
        return SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->select('security_shift_id', DB::raw('count(*) as rotation_count'))
            ->with('shift:id,name,category')
            ->groupBy('security_shift_id')
            ->get()
            ->map(function($item) {
                return [
                    'shift_id' => $item->security_shift_id,
                    'shift_name' => $item->shift?->name ?? 'Unknown',
                    'category' => $item->shift?->category ?? 'Unknown',
                    'rotation_count' => $item->rotation_count,
                ];
            });
    }

    private function getRotationStatsByGroup($startDate, $endDate)
    {
        return RotationGroup::withCount(['schedules' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('assignment_date', [$startDate, $endDate])
                  ->where('is_rotated', true);
            }])
            ->having('schedules_count', '>', 0)
            ->get()
            ->map(function($group) {
                return [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'rotation_count' => $group->schedules_count,
                    'type' => $group->group_type,
                ];
            });
    }

    private function getRotationStatsByUser($startDate, $endDate)
    {
        return DB::table('security_schedules')
            ->join('users', 'security_schedules.security_user_id', '=', 'users.id')
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->select('users.id', 'users.name', DB::raw('count(*) as rotation_count'))
            ->groupBy('users.id', 'users.name')
            ->orderBy('rotation_count', 'desc')
            ->limit(20)
            ->get();
    }

    private function getRotationEffectiveness($startDate, $endDate)
    {
        $rotatedSchedules = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->get();

        $nonRotatedSchedules = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', false)
            ->get();

        $rotatedCompletion = $rotatedSchedules->where('status', 'completed')->count();
        $nonRotatedCompletion = $nonRotatedSchedules->where('status', 'completed')->count();

        $rotatedLate = $rotatedSchedules->where('late_minutes', '>', 0)->count();
        $nonRotatedLate = $nonRotatedSchedules->where('late_minutes', '>', 0)->count();

        return [
            'rotated' => [
                'count' => $rotatedSchedules->count(),
                'completed' => $rotatedCompletion,
                'completion_rate' => $rotatedSchedules->count() > 0 ?
                    round(($rotatedCompletion / $rotatedSchedules->count()) * 100, 2) : 0,
                'late_rate' => $rotatedSchedules->count() > 0 ?
                    round(($rotatedLate / $rotatedSchedules->count()) * 100, 2) : 0,
                'avg_late_minutes' => round($rotatedSchedules->avg('late_minutes') ?? 0, 1),
                'avg_overtime' => round($rotatedSchedules->avg('overtime_minutes') ?? 0, 1),
            ],
            'non_rotated' => [
                'count' => $nonRotatedSchedules->count(),
                'completed' => $nonRotatedCompletion,
                'completion_rate' => $nonRotatedSchedules->count() > 0 ?
                    round(($nonRotatedCompletion / $nonRotatedSchedules->count()) * 100, 2) : 0,
                'late_rate' => $nonRotatedSchedules->count() > 0 ?
                    round(($nonRotatedLate / $nonRotatedSchedules->count()) * 100, 2) : 0,
                'avg_late_minutes' => round($nonRotatedSchedules->avg('late_minutes') ?? 0, 1),
                'avg_overtime' => round($nonRotatedSchedules->avg('overtime_minutes') ?? 0, 1),
            ],
            'improvement' => [
                'completion_rate' => $this->calculateImprovement(
                    $nonRotatedSchedules->count() > 0 ? ($nonRotatedCompletion / $nonRotatedSchedules->count()) * 100 : 0,
                    $rotatedSchedules->count() > 0 ? ($rotatedCompletion / $rotatedSchedules->count()) * 100 : 0
                ),
                'late_rate' => $this->calculateImprovement(
                    $nonRotatedSchedules->count() > 0 ? ($nonRotatedLate / $nonRotatedSchedules->count()) * 100 : 0,
                    $rotatedSchedules->count() > 0 ? ($rotatedLate / $rotatedSchedules->count()) * 100 : 0,
                    true // lower is better
                ),
            ],
        ];
    }

    private function getRotationTimingStats($startDate, $endDate)
    {
        $rotations = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where('is_rotated', true)
            ->whereNotNull('rotated_at')
            ->get();

        if ($rotations->isEmpty()) {
            return [
                'avg_hour' => null,
                'peak_hour' => null,
                'by_hour' => [],
                'by_day_of_week' => [],
            ];
        }

        $byHour = [];
        $byDayOfWeek = [];

        foreach ($rotations as $rotation) {
            $hour = $rotation->rotated_at->format('H');
            $dayOfWeek = $rotation->rotated_at->format('l');
            
            $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;
            $byDayOfWeek[$dayOfWeek] = ($byDayOfWeek[$dayOfWeek] ?? 0) + 1;
        }

        ksort($byHour);
        
        // Calculate weighted average hour
        $total = 0;
        $sum = 0;
        foreach ($byHour as $hour => $count) {
            $sum += $hour * $count;
            $total += $count;
        }
        $avgHour = $total > 0 ? round($sum / $total, 1) : null;

        // Find peak hour
        $peakHour = array_search(max($byHour), $byHour);

        return [
            'avg_hour' => $avgHour,
            'peak_hour' => $peakHour,
            'by_hour' => $byHour,
            'by_day_of_week' => $byDayOfWeek,
            'peak_day' => array_search(max($byDayOfWeek), $byDayOfWeek),
        ];
    }

    private function getRotationDataForExport($startDate, $endDate, $request)
    {
        $query = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->where(function($q) {
                $q->where('is_rotated', true)
                  ->orWhereNotNull('rotated_from_user_id');
            })
            ->with(['post', 'shift', 'securityUser', 'rotatedFromUser', 'rotationGroup']);

        if ($request->filled('post_id')) {
            $query->where('security_post_id', $request->post_id);
        }

        if ($request->filled('shift_id')) {
            $query->where('security_shift_id', $request->shift_id);
        }

        if ($request->filled('group_id')) {
            $query->where('rotation_group_id', $request->group_id);
        }

        return $query->orderBy('assignment_date')->orderBy('rotated_at')->get();
    }

    private function getExportSummary($data, $startDate, $endDate)
    {
        $totalRotations = $data->count();
        $uniquePosts = $data->unique('security_post_id')->count();
        $uniqueUsers = $data->unique('security_user_id')->count();
        $uniqueGroups = $data->whereNotNull('rotation_group_id')->unique('rotation_group_id')->count();

        $completed = $data->where('status', 'completed')->count();
        $absent = $data->where('status', 'absent')->count();

        return [
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'total_rotations' => $totalRotations,
            'unique_posts' => $uniquePosts,
            'unique_personnel' => $uniqueUsers,
            'unique_groups' => $uniqueGroups,
            'completed_rotations' => $completed,
            'absent_rotations' => $absent,
            'success_rate' => $totalRotations > 0 ? 
                round(($completed / $totalRotations) * 100, 2) . '%' : '0%',
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'generated_by' => auth()->user()?->name ?? 'System',
        ];
    }

    private function exportAsCsv($data, $summary, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ];

        $callback = function() use ($data, $summary) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Add summary section
            fputcsv($file, ['ROTATION HISTORY EXPORT']);
            fputcsv($file, ['Generated:', $summary['generated_at']]);
            fputcsv($file, ['Period:', $summary['period']]);
            fputcsv($file, ['']);
            fputcsv($file, ['SUMMARY STATISTICS']);
            fputcsv($file, ['Total Rotations:', $summary['total_rotations']]);
            fputcsv($file, ['Unique Posts:', $summary['unique_posts']]);
            fputcsv($file, ['Unique Personnel:', $summary['unique_personnel']]);
            fputcsv($file, ['Unique Groups:', $summary['unique_groups']]);
            fputcsv($file, ['Completed Rotations:', $summary['completed_rotations']]);
            fputcsv($file, ['Success Rate:', $summary['success_rate']]);
            fputcsv($file, ['']);
            fputcsv($file, ['DETAILED ROTATION HISTORY']);
            
            // Headers
            fputcsv($file, [
                'Date',
                'Time',
                'Schedule Date',
                'Post',
                'Shift',
                'From User',
                'To User',
                'Type',
                'Reason',
                'Group',
                'Preference Score',
                'Status'
            ]);

            // Data
            foreach ($data as $schedule) {
                fputcsv($file, [
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

                // Add historical data if present
                if ($schedule->rotation_data && isset($schedule->rotation_data['rotation_history'])) {
                    foreach ($schedule->rotation_data['rotation_history'] as $item) {
                        fputcsv($file, [
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

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportAsExcel($data, $summary, $filename)
    {
        // For Excel export, you'd typically use a package like Laravel Excel
        // This is a placeholder that returns CSV instead
        return $this->exportAsCsv($data, $summary, $filename);
    }

    private function exportAsPdf($data, $summary, $filename)
    {
        // For PDF export, you'd typically use a package like DomPDF
        // This is a placeholder that returns CSV instead
        return $this->exportAsCsv($data, $summary, $filename);
    }

    private function calculateAverageFrequency($dates)
    {
        if (count($dates) < 2) return null;

        $intervals = [];
        for ($i = 1; $i < count($dates); $i++) {
            $prev = Carbon::parse($dates[$i - 1]);
            $curr = Carbon::parse($dates[$i]);
            $intervals[] = $prev->diffInDays($curr);
        }

        return round(array_sum($intervals) / count($intervals), 1);
    }

    private function calculateImprovement($baseline, $current, $lowerIsBetter = false)
    {
        if ($baseline == 0) return 0;
        
        if ($lowerIsBetter) {
            return round((($baseline - $current) / $baseline) * 100, 2);
        }
        
        return round((($current - $baseline) / $baseline) * 100, 2);
    }

    private function getUserName($userId)
    {
        if (!$userId) return null;
        
        $user = User::find($userId);
        return $user?->name;
    }

    private function updateStatsArray(&$stats, $schedule)
    {
        $postName = $schedule->post?->name ?? 'Unknown';
        $shiftName = $schedule->shift?->name ?? 'Unknown';
        $groupName = $schedule->rotationGroup?->name ?? 'None';
        
        $stats['by_post'][$postName] = ($stats['by_post'][$postName] ?? 0) + 1;
        $stats['by_shift'][$shiftName] = ($stats['by_shift'][$shiftName] ?? 0) + 1;
        $stats['by_group'][$groupName] = ($stats['by_group'][$groupName] ?? 0) + 1;
    }

    private function getNextScheduledRotation($group)
{
    if (!$group->auto_rotate) return null;

    // Safely access rotation_config
    $config = $group->rotation_config ?? [];
    
    if (isset($config['next_rotation_date']) && $config['next_rotation_date']) {
        try {
            return Carbon::parse($config['next_rotation_date']);
        } catch (\Exception $e) {
            return null;
        }
    }

    return null;
}

}