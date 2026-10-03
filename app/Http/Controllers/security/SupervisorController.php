<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecuritySupervisorAssignment;
use App\Models\User;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\SwapRequest;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;

class SupervisorController extends Controller
{
    use AuthorizesRequests;

    // ✅ USER TYPE CONSTANTS (match AuthServiceProvider)
    const USER_TYPE_SUPER_ADMIN = 0;
    const USER_TYPE_ADMIN = 1;
    const USER_TYPE_LANDLORD = 2;
    const USER_TYPE_TENANT = 3;
    const USER_TYPE_FIELD_AGENT = 4;
    const USER_TYPE_DEVELOPER = 5;
    const USER_TYPE_SECURITY_PERSONNEL = 6;

    /**
     * Supervisor Dashboard - Main landing page for supervisors
     */
    public function dashboard()
    {
        $user = Auth::user();
        
        // Get all active assignments for this supervisor
        $assignments = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->with(['post'])
            ->get();

        if ($assignments->isEmpty()) {
            return redirect()->route('security.dashboard')
                ->with('info', 'You are not currently assigned as a supervisor for any post.');
        }

        $dashboardData = [
            'assignments' => $assignments,
            'total_posts' => $assignments->count(),
            'post_ids' => $assignments->pluck('security_post_id'),
        ];

        // Get today's team overview
        $today = now()->toDateString();
        $postIds = $assignments->pluck('security_post_id');

        $dashboardData['team_today'] = SecuritySchedule::whereIn('security_post_id', $postIds)
            ->whereDate('assignment_date', $today)
            ->with(['securityUser', 'post', 'shift'])
            ->get()
            ->groupBy('post.name');

        // Get pending approvals
        $dashboardData['pending_approvals'] = [];
        $dashboardData['pending_counts'] = [
            'total' => 0,
            'swaps' => 0,
            'overtime' => 0,
            'verifications' => 0,
            'incidents' => 0,
        ];

        foreach ($assignments as $assignment) {
            $pending = $assignment->getPendingApprovals();
            
            $dashboardData['pending_counts']['swaps'] += $pending['swap_requests'] ?? 0;
            $dashboardData['pending_counts']['overtime'] += $pending['overtime_requests'] ?? 0;
            $dashboardData['pending_counts']['verifications'] += $pending['pending_verifications'] ?? 0;
            
            foreach ($pending as $key => $count) {
                if ($count > 0) {
                    $dashboardData['pending_approvals'][$assignment->post->name][$key] = $count;
                }
            }
        }

        $dashboardData['pending_counts']['total'] = array_sum($dashboardData['pending_counts']);

        // Get staffing alerts
        $dashboardData['staffing_alerts'] = SecurityPost::whereIn('id', $postIds)
            ->withCount(['currentSchedules as current_personnel'])
            ->get()
            ->filter(function($post) {
                return $post->current_personnel < $post->max_personnel;
            });

        return view('security.supervisor.dashboard', $dashboardData);
    }

    /**
     * Display supervisor's own assignments
     */
    public function myAssignments()
    {
        $assignments = SecuritySupervisorAssignment::where('user_id', Auth::id())
            ->with(['post'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return view('security.supervisor.my-assignments', compact('assignments'));
    }

    /**
     * Display supervisor's assignment history (past assignments)
     */
    public function myAssignmentHistory(Request $request)
    {
        $user = Auth::user();
        
        $assignments = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where(function($q) {
                $q->where('end_date', '<', now())
                  ->orWhere('is_active', false);
            })
            ->with(['post', 'assignedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        $stats = [
            'total' => $assignments->total(),
            'expired' => SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('end_date', '<', now())
                ->count(),
            'inactive' => SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('is_active', false)
                ->whereNull('end_date')
                ->count(),
            'by_type' => SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where(function($q) {
                    $q->where('end_date', '<', now())
                      ->orWhere('is_active', false);
                })
                ->selectRaw('supervisor_type, count(*) as count')
                ->groupBy('supervisor_type')
                ->pluck('count', 'supervisor_type')
                ->toArray(),
        ];
        
        return view('security.supervisor.assignment-history', compact('assignments', 'stats'));
    }

    /**
     * Show current active assignments for supervisor
     */
    public function myCurrentAssignments()
    {
        $assignments = SecuritySupervisorAssignment::where('user_id', Auth::id())
            ->active()
            ->with(['post'])
            ->get();
        
        return view('security.supervisor.current-assignments', compact('assignments'));
    }

    /**
     * Display pending approvals for supervisor
     */
    public function myPendingApprovals()
    {
        $user = Auth::user();
        $pending = [
            'swap_requests' => SwapRequest::whereHas('schedule', function($q) use ($user) {
                $q->whereIn('security_post_id', $user->supervisedPosts());
            })->where('status', 'pending')->count(),
            
            'overtime_requests' => OvertimeRequest::whereHas('schedule', function($q) use ($user) {
                $q->whereIn('security_post_id', $user->supervisedPosts());
            })->where('status', 'pending')->count(),
            
            'pending_verifications' => SecuritySchedule::whereIn('security_post_id', $user->supervisedPosts())
                ->whereDate('assignment_date', today())
                ->where('check_in_status', 'pending_verification')
                ->count(),
        ];
        
        return view('security.supervisor.pending-approvals', compact('pending'));
    }

    /**
     * Approve shift swap request
     */
    public function approveSwap(Request $request, $scheduleId)
    {
        try {
            DB::transaction(function() use ($scheduleId, $request) {
                $schedule = SecuritySchedule::findOrFail($scheduleId);
                
                // Verify supervisor has authority using Gate
                if (!Gate::allows('approve-swap', $schedule)) {
                    abort(403, 'You do not have permission to approve swap requests.');
                }
                
                $schedule->update([
                    'swap_status' => 'approved',
                    'swap_approved_by' => Auth::id(),
                    'swap_approved_at' => now(),
                    'swap_notes' => $request->notes
                ]);
                
                // Notify affected personnel
                // event(new SwapApproved($schedule));
            });
            
            return redirect()->back()->with('success', 'Swap request approved successfully.');
            
        } catch (\Exception $e) {
            Log::error('Swap approval failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to approve swap request.');
        }
    }

    /**
     * Get today's team overview
     */
    public function teamToday()
    {
        $user = Auth::user();
        
        $assignments = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->get();

        if ($assignments->isEmpty()) {
            return redirect()->route('security.supervisor.dashboard')
                ->with('info', 'You are not currently assigned as a supervisor for any post.');
        }

        $postIds = $assignments->pluck('security_post_id');
        $today = now()->toDateString();
        
        $team = SecuritySchedule::whereIn('security_post_id', $postIds)
            ->whereDate('assignment_date', $today)
            ->with(['securityUser', 'post', 'shift'])
            ->orderBy('security_post_id', 'asc')
            ->orderBy('security_shift_id', 'asc')
            ->get()
            ->groupBy('post.name');
        
        $stats = [
            'total' => $team->flatten()->count(),
            'checked_in' => $team->flatten()->where('check_in_status', 'verified')->count(),
            'pending' => $team->flatten()->where('check_in_status', 'pending')->count(),
            'absent' => $team->flatten()->where('status', 'absent')->count(),
        ];
        
        $postIdsArray = $postIds->toArray();
        
        return view('security.supervisor.team-today', compact('team', 'stats', 'postIdsArray', 'assignments'));
    }

    /**
     * Display details of a specific assignment for the logged-in supervisor
     */
    public function myAssignmentDetails($id)
    {
        $assignment = SecuritySupervisorAssignment::where('user_id', Auth::id())
            ->with(['post', 'assignedBy'])
            ->findOrFail($id);
        
        return view('security.supervisor.assignment-details', compact('assignment'));
    }

    /**
     * View post schedule
     */
    public function postSchedule(Request $request, $postId)
    {
        $user = Auth::user();
        
        // ✅ ADD DEBUGGING
        \Log::info('postSchedule called', [
            'user_id' => $user->id,
            'user_type' => $user->type,
            'post_id' => $postId,
        ]);
        
        // ✅ USE CONSTANT INSTEAD OF DIRECT VALUE
        if ($user->type !== self::USER_TYPE_SECURITY_PERSONNEL) {
            abort(403, 'Only security personnel can access this page.');
        }
        
        // Handle 'current' as a special value
        if ($postId === 'current') {
            $assignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                ->active()
                ->first();
            
            if (!$assignment) {
                abort(403, 'You are not assigned as a supervisor for any post.');
            }
            
            $postId = $assignment->security_post_id;
        }
        
        // Check if user has access to this post
        $hasAccess = SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $postId)
            ->active()
            ->exists();
        
        if (!$hasAccess) {
            // Check if user has any supervisor assignment at all
            $anyAssignment = SecuritySupervisorAssignment::where('user_id', $user->id)
                ->active()
                ->exists();
            
            if (!$anyAssignment) {
                abort(403, 'You are not assigned as a supervisor for any post.');
            }
            
            abort(403, 'You are not assigned as a supervisor for this post.');
        }
        
        $date = $request->get('date', now()->toDateString());
        
        $schedule = SecuritySchedule::where('security_post_id', $postId)
            ->whereDate('assignment_date', $date)
            ->with(['securityUser', 'shift'])
            ->orderBy('security_shift_id')
            ->get();
        
        $post = SecurityPost::findOrFail($postId);
        
        return view('security.supervisor.post-schedule', compact('schedule', 'post', 'date'));
    }

    /**
     * Display team performance metrics
     */
    public function teamPerformance(Request $request)
    {
        $user = Auth::user();
        
        $postIds = $user->supervisedPosts();
        
        if (empty($postIds)) {
            return redirect()->route('security.supervisor.dashboard')
                ->with('info', 'You are not currently assigned as a supervisor for any post.');
        }
        
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        
        $schedules = SecuritySchedule::whereIn('security_post_id', $postIds)
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->with(['securityUser', 'post', 'shift'])
            ->get();
        
        $totalShifts = $schedules->count();
        $completedShifts = $schedules->where('status', 'completed')->count();
        $absentShifts = $schedules->where('status', 'absent')->count();
        $lateShifts = $schedules->where('late_minutes', '>', 0)->count();
        $overtimeShifts = $schedules->where('overtime_minutes', '>', 0)->count();
        
        $attendanceRate = $totalShifts > 0 
            ? round(($completedShifts / $totalShifts) * 100, 1) 
            : 0;
        
        $punctualityRate = $completedShifts > 0 
            ? round((($completedShifts - $lateShifts) / $completedShifts) * 100, 1) 
            : 0;
        
        $userPerformance = $schedules->groupBy('security_user_id')
            ->map(function($userSchedules, $userId) {
                $user = $userSchedules->first()->securityUser;
                $total = $userSchedules->count();
                $completed = $userSchedules->where('status', 'completed')->count();
                $late = $userSchedules->where('late_minutes', '>', 0)->count();
                $overtime = $userSchedules->where('overtime_minutes', '>', 0)->count();
                
                return [
                    'user_id' => $userId,
                    'user_name' => $user->name ?? 'Unknown',
                    'badge_number' => $user->badge_number ?? 'N/A',
                    'total_shifts' => $total,
                    'completed_shifts' => $completed,
                    'attendance_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                    'late_shifts' => $late,
                    'punctuality_rate' => $completed > 0 ? round((($completed - $late) / $completed) * 100, 1) : 0,
                    'overtime_shifts' => $overtime,
                    'total_late_minutes' => $userSchedules->sum('late_minutes'),
                    'total_overtime_minutes' => $userSchedules->sum('overtime_minutes'),
                ];
            })->sortByDesc('attendance_rate')->values();
        
        $postPerformance = $schedules->groupBy('security_post_id')
            ->map(function($postSchedules, $postId) {
                $post = $postSchedules->first()->post;
                $total = $postSchedules->count();
                $completed = $postSchedules->where('status', 'completed')->count();
                
                return [
                    'post_id' => $postId,
                    'post_name' => $post->name ?? 'Unknown',
                    'total_shifts' => $total,
                    'completed_shifts' => $completed,
                    'attendance_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                    'late_count' => $postSchedules->where('late_minutes', '>', 0)->count(),
                    'overtime_count' => $postSchedules->where('overtime_minutes', '>', 0)->count(),
                ];
            })->sortByDesc('attendance_rate')->values();
        
        $trends = $schedules->groupBy(function($schedule) {
                return $schedule->assignment_date->format('Y-m-d');
            })
            ->map(function($daySchedules, $date) {
                $total = $daySchedules->count();
                $completed = $daySchedules->where('status', 'completed')->count();
                
                return [
                    'date' => $date,
                    'total_shifts' => $total,
                    'completed_shifts' => $completed,
                    'attendance_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                    'late_count' => $daySchedules->where('late_minutes', '>', 0)->count(),
                    'overtime_count' => $daySchedules->where('overtime_minutes', '>', 0)->count(),
                ];
            })->sortBy('date')->values();
        
        $performance = [
            'total_shifts' => $totalShifts,
            'completed_shifts' => $completedShifts,
            'absent_shifts' => $absentShifts,
            'late_shifts' => $lateShifts,
            'overtime_shifts' => $overtimeShifts,
            'attendance_rate' => $attendanceRate,
            'punctuality_rate' => $punctualityRate,
            'total_late_minutes' => $schedules->sum('late_minutes'),
            'total_overtime_minutes' => $schedules->sum('overtime_minutes'),
            'average_late_minutes' => $lateShifts > 0 ? round($schedules->sum('late_minutes') / $lateShifts, 1) : 0,
            'average_overtime_minutes' => $overtimeShifts > 0 ? round($schedules->sum('overtime_minutes') / $overtimeShifts, 1) : 0,
        ];
        
        return view('security.supervisor.team-performance', compact(
            'performance',
            'userPerformance',
            'postPerformance',
            'trends',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Display team schedule
     */
    public function teamSchedule(Request $request)
    {
        $user = Auth::user();
        
        $postIds = $user->supervisedPosts();
        
        if (empty($postIds)) {
            return redirect()->route('security.supervisor.dashboard')
                ->with('info', 'You are not currently assigned as a supervisor for any post.');
        }
        
        $date = $request->get('date', now()->format('Y-m-d'));
        $postId = $request->get('post_id');
        
        $query = SecuritySchedule::whereIn('security_post_id', $postIds)
            ->whereDate('assignment_date', $date)
            ->with(['securityUser', 'post', 'shift', 'rotationGroup']);
        
        if ($postId) {
            $query->where('security_post_id', $postId);
        }
        
        $schedules = $query->orderBy('security_shift_id')->get();
        
        $posts = SecurityPost::whereIn('id', $postIds)->get();
        
        return view('security.supervisor.team-schedule', compact('schedules', 'posts', 'date', 'postId'));
    }

    /**
     * Display team attendance
     */
    public function teamAttendance(Request $request)
    {
        $user = Auth::user();
        
        $postIds = $user->supervisedPosts();
        
        if (empty($postIds)) {
            return redirect()->route('security.supervisor.dashboard')
                ->with('info', 'You are not currently assigned as a supervisor for any post.');
        }
        
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $currentDate = Carbon::create($year, $month, 1);
        $prevMonth = $currentDate->copy()->subMonth();
        $nextMonth = $currentDate->copy()->addMonth();
        
        $schedules = SecuritySchedule::whereIn('security_post_id', $postIds)
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->with(['securityUser', 'post', 'shift'])
            ->get();
        
        $attendance = $schedules->groupBy('security_user_id')
            ->map(function($userSchedules) use ($startDate) {
                $user = $userSchedules->first()->securityUser;
                $daysInMonth = $startDate->daysInMonth;
                $attendanceMatrix = [];
                
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = $startDate->copy()->day($day)->format('Y-m-d');
                    $daySchedule = $userSchedules->firstWhere('assignment_date', $date);
                    
                    $attendanceMatrix[$day] = $daySchedule ? [
                        'status' => $daySchedule->status,
                        'checkin_time' => $daySchedule->checkin_time,
                        'late_minutes' => $daySchedule->late_minutes,
                    ] : null;
                }
                
                $totalShifts = $userSchedules->count();
                $completedShifts = $userSchedules->where('status', 'completed')->count();
                $lateShifts = $userSchedules->where('late_minutes', '>', 0)->count();
                
                return [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'badge_number' => $user->badge_number,
                    'total_shifts' => $totalShifts,
                    'completed_shifts' => $completedShifts,
                    'late_shifts' => $lateShifts,
                    'attendance_rate' => $totalShifts > 0 ? round(($completedShifts / $totalShifts) * 100, 1) : 0,
                    'matrix' => $attendanceMatrix,
                ];
            })->values();
        
        $summary = [
            'total_shifts' => $schedules->count(),
            'completed_shifts' => $schedules->where('status', 'completed')->count(),
            'absent_shifts' => $schedules->where('status', 'absent')->count(),
            'late_shifts' => $schedules->where('late_minutes', '>', 0)->count(),
            'attendance_rate' => $schedules->count() > 0 
                ? round(($schedules->where('status', 'completed')->count() / $schedules->count()) * 100, 1) 
                : 0,
        ];
        
        return view('security.supervisor.team-attendance', compact(
            'attendance',
            'summary',
            'month',
            'year',
            'startDate',
            'endDate',
            'prevMonth', 
            'nextMonth'   
        ));
    }

    // ============================================
    // ADDITIONAL HELPER METHODS
    // ============================================

    /**
     * Get supervised posts for the current user
     */
    public function supervisedPosts()
    {
        $user = Auth::user();
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->pluck('security_post_id')
            ->toArray();
    }

    /**
     * Mark a schedule as absent
     */
    public function markAbsent(Request $request, $scheduleId)
    {
        try {
            $schedule = SecuritySchedule::findOrFail($scheduleId);
            
            // Verify supervisor has authority
            if (!Gate::allows('mark-absent', $schedule)) {
                abort(403, 'You do not have permission to mark absent.');
            }
            
            $schedule->update([
                'status' => 'absent',
                'check_in_status' => 'absent',
                'absent_reason' => $request->reason,
                'absent_notes' => $request->notes,
                'marked_absent_by' => Auth::id(),
                'marked_absent_at' => now(),
            ]);
            
            Log::info('Schedule marked as absent', [
                'schedule_id' => $scheduleId,
                'marked_by' => Auth::id(),
                'reason' => $request->reason
            ]);
            
            return redirect()->back()->with('success', 'Personnel marked as absent successfully.');
            
        } catch (\Exception $e) {
            Log::error('Failed to mark absent: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to mark as absent.');
        }
    }

    /**
     * Verify check-in
     */
    public function verifyCheckin(Request $request, $scheduleId)
    {
        try {
            $schedule = SecuritySchedule::findOrFail($scheduleId);
            
            // Verify supervisor has authority
            if (!Gate::allows('verify-checkin', $schedule)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to verify check-ins.'
                ], 403);
            }
            
            $schedule->update([
                'check_in_status' => 'verified',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'verification_notes' => $request->notes,
            ]);
            
            Log::info('Check-in verified', [
                'schedule_id' => $scheduleId,
                'verified_by' => Auth::id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Check-in verified successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to verify check-in: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify check-in.'
            ], 500);
        }
    }
}