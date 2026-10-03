<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Carbon\Carbon;

class SecurityDashboardController extends Controller
{
    /**
     * Display the security personnel dashboard
     */
    public function index()
    {
        $this->authorizeSecurity();

        $user = Auth::user();
        
        // Get the security personnel ID (could be user_id or a different column)
        $personnelId = $this->getPersonnelId($user);
        
        // Get today's schedule
        $todaySchedule = $this->getTodaySchedule($personnelId);
        
        // Get upcoming shifts
        $upcomingShifts = $this->getUpcomingShifts($personnelId);
        
        // Ensure $upcomingShifts is always an array
        if ($upcomingShifts === null) {
            $upcomingShifts = [];
        }
        
        // Get pending tasks
        $pendingTasks = $this->getPendingTasks($personnelId);
        
        // Ensure $pendingTasks is always an array
        if ($pendingTasks === null) {
            $pendingTasks = [];
        }
        
        // Get recent notifications
        $recentNotifications = $this->getRecentNotifications($user);
        
        // Ensure $recentNotifications is always an array
        if ($recentNotifications === null) {
            $recentNotifications = [];
        }
        
        // Get performance metrics
        $performanceMetrics = $this->getPerformanceMetrics($personnelId);
        
        // Ensure $performanceMetrics is always an array with defaults
        if ($performanceMetrics === null) {
            $performanceMetrics = $this->getEmptyPerformanceMetrics();
        }
        
        // Get assigned post information
        $assignedPost = $this->getAssignedPost($personnelId);
        
        // Get current status
        $currentStatus = $this->getCurrentStatus($personnelId);
        
        // Ensure $currentStatus is always an array with defaults
        if ($currentStatus === null) {
            $currentStatus = $this->getEmptyCurrentStatus();
        }
        
        // Get attendance statistics
        $attendanceStats = $this->getAttendanceStats($personnelId);
        
        // Get user's roles for the dashboard switcher
        $userRoles = $user->roles ?? collect();
        $hasMultipleRoles = $userRoles->count() > 1;
        
        // Check if user has legacy security type OR role-based security role
        $isSecurityByType = ($user->type == 6);
        $hasSecurityRole = $user->hasRole('security-personnel');
        
        return view('security.dashboard', compact(
            'todaySchedule',
            'upcomingShifts',
            'attendanceStats',
            'pendingTasks',
            'recentNotifications',
            'performanceMetrics',
            'assignedPost',
            'currentStatus',
            'userRoles',
            'hasMultipleRoles',
            'isSecurityByType',
            'hasSecurityRole'
        ));
    }

    /**
     * Get the personnel ID from the user
     * This handles different column naming conventions
     */
    protected function getPersonnelId($user)
    {
        // First, try to find if there's a security_personnel record
        $possibleTables = ['security_personnel', 'security_guards', 'security_officers', 'personnel'];
        
        foreach ($possibleTables as $table) {
            if (Schema::hasTable($table)) {
                // Find the column that references the user
                $possibleColumns = ['user_id', 'userid', 'user_uuid', 'auth_user_id', 'personnel_user_id'];
                
                foreach ($possibleColumns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $record = DB::table($table)->where($column, $user->id)->first();
                        if ($record) {
                            return $record->id;
                        }
                    }
                }
            }
        }
        
        // If no specific personnel record, use the user ID
        return $user->id;
    }

    /**
     * Find the correct column name for personnel reference in a table
     */
    protected function findPersonnelColumn($tableName)
    {
        if (!Schema::hasTable($tableName)) {
            return null;
        }
        
        $possibleColumns = [
            'personnel_id', 'security_personnel_id', 'guard_id', 'officer_id',
            'staff_id', 'security_guard_id', 'user_id', 'security_id',
            'personnel_user_id', 'assigned_to', 'security_officer_id'
        ];
        
        foreach ($possibleColumns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                return $column;
            }
        }
        
        return null;
    }

    /**
     * Get today's schedule for the security personnel
     */
    protected function getTodaySchedule($personnelId)
    {
        try {
            $today = Carbon::today()->format('Y-m-d');
            $tableName = 'security_schedules';
            
            if (!Schema::hasTable($tableName)) {
                return null;
            }
            
            $personnelColumn = $this->findPersonnelColumn($tableName);
            
            if (!$personnelColumn) {
                return null;
            }
            
            $schedule = DB::table($tableName)
                ->where($personnelColumn, $personnelId)
                ->whereDate('scheduled_date', $today)
                ->first();
            
            if (!$schedule) {
                return null;
            }
            
            // Get post information if available
            $post = null;
            if (isset($schedule->post_id) && Schema::hasTable('security_posts')) {
                $post = DB::table('security_posts')->where('id', $schedule->post_id)->first();
            }
            
            return [
                'id' => $schedule->id,
                'post_name' => $post->name ?? $schedule->post_name ?? $schedule->post ?? 'N/A',
                'post_location' => $post->location ?? $schedule->location ?? $schedule->post_location ?? 'N/A',
                'shift_name' => $schedule->shift_name ?? $schedule->shift ?? 'N/A',
                'start_time' => $schedule->start_time ?? null,
                'end_time' => $schedule->end_time ?? null,
                'status' => $schedule->status ?? 'pending',
                'checked_in_at' => isset($schedule->checked_in_at) ? Carbon::parse($schedule->checked_in_at)->format('h:i A') : null,
                'checked_out_at' => isset($schedule->checked_out_at) ? Carbon::parse($schedule->checked_out_at)->format('h:i A') : null,
                'is_late' => $this->checkIfLateFromSchedule($schedule),
                'remaining_time' => $this->calculateRemainingTimeFromSchedule($schedule),
                'can_checkin' => $this->canCheckInFromSchedule($schedule),
                'can_checkout' => $this->canCheckOutFromSchedule($schedule)
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting today schedule: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get upcoming shifts for the next 7 days
     */
    protected function getUpcomingShifts($personnelId)
    {
        try {
            $today = Carbon::today();
            $tableName = 'security_schedules';
            
            if (!Schema::hasTable($tableName)) {
                return [];
            }
            
            $personnelColumn = $this->findPersonnelColumn($tableName);
            
            if (!$personnelColumn) {
                return [];
            }
            
            $schedules = DB::table($tableName)
                ->where($personnelColumn, $personnelId)
                ->whereDate('scheduled_date', '>', $today)
                ->whereDate('scheduled_date', '<=', $today->copy()->addDays(7))
                ->orderBy('scheduled_date', 'asc')
                ->get();
            
            if ($schedules->isEmpty()) {
                return [];
            }
            
            return $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'date' => $schedule->scheduled_date,
                    'day_name' => Carbon::parse($schedule->scheduled_date)->format('l'),
                    'post_name' => $schedule->post_name ?? $schedule->post ?? 'N/A',
                    'shift_name' => $schedule->shift_name ?? $schedule->shift ?? 'N/A',
                    'start_time' => $schedule->start_time ? Carbon::parse($schedule->start_time)->format('h:i A') : null,
                    'end_time' => $schedule->end_time ? Carbon::parse($schedule->end_time)->format('h:i A') : null,
                    'status' => $schedule->status ?? 'pending'
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Error getting upcoming shifts: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get attendance statistics
     */
    protected function getAttendanceStats($personnelId)
    {
        try {
            $tableName = 'security_attendances';
            
            if (!Schema::hasTable($tableName)) {
                return $this->getEmptyAttendanceStats();
            }
            
            $personnelColumn = $this->findPersonnelColumn($tableName);
            
            if (!$personnelColumn) {
                return $this->getEmptyAttendanceStats();
            }
            
            $currentMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            
            // Current month stats
            $currentMonthStats = DB::table($tableName)
                ->where($personnelColumn, $personnelId)
                ->whereBetween('date', [$currentMonth, Carbon::now()])
                ->get();
            
            // Last month stats
            $lastMonthStats = DB::table($tableName)
                ->where($personnelColumn, $personnelId)
                ->whereBetween('date', [$lastMonth, $lastMonth->copy()->endOfMonth()])
                ->get();
            
            return [
                'current_month' => [
                    'total_shifts' => $currentMonthStats->count(),
                    'present_days' => $currentMonthStats->where('status', 'present')->count(),
                    'absent_days' => $currentMonthStats->where('status', 'absent')->count(),
                    'late_days' => $currentMonthStats->where('status', 'late')->count(),
                    'on_time_rate' => $this->calculateOnTimeRateFromCollection($currentMonthStats),
                    'total_hours_worked' => $currentMonthStats->sum('hours_worked'),
                    'overtime_hours' => $currentMonthStats->sum('overtime_hours')
                ],
                'last_month' => [
                    'total_shifts' => $lastMonthStats->count(),
                    'present_days' => $lastMonthStats->where('status', 'present')->count(),
                    'on_time_rate' => $this->calculateOnTimeRateFromCollection($lastMonthStats)
                ]
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting attendance stats: ' . $e->getMessage());
            return $this->getEmptyAttendanceStats();
        }
    }

    /**
     * Get empty attendance stats array
     */
    protected function getEmptyAttendanceStats()
    {
        return [
            'current_month' => [
                'total_shifts' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'late_days' => 0,
                'on_time_rate' => 0,
                'total_hours_worked' => 0,
                'overtime_hours' => 0
            ],
            'last_month' => [
                'total_shifts' => 0,
                'present_days' => 0,
                'on_time_rate' => 0
            ]
        ];
    }

    /**
     * Get pending tasks for the security personnel
     */
    protected function getPendingTasks($personnelId)
    {
        try {
            $tasks = [];
            
            // Check for pending reports
            if (Schema::hasTable('security_reports')) {
                $reportColumn = $this->findPersonnelColumn('security_reports');
                if ($reportColumn) {
                    $pendingReports = DB::table('security_reports')
                        ->where($reportColumn, $personnelId)
                        ->where('status', 'draft')
                        ->count();
                    
                    if ($pendingReports > 0) {
                        $tasks[] = [
                            'type' => 'reports',
                            'title' => 'Pending Reports',
                            'description' => "You have {$pendingReports} draft report(s) to complete",
                            'count' => $pendingReports,
                            'icon' => 'file-alt',
                            'color' => 'warning',
                            'route' => route('security.reports.pending', [], false) ?: '#'
                        ];
                    }
                }
            }
            
            // Check for unacknowledged schedules
            if (Schema::hasTable('security_schedules')) {
                $scheduleColumn = $this->findPersonnelColumn('security_schedules');
                if ($scheduleColumn) {
                    $unacknowledgedSchedules = DB::table('security_schedules')
                        ->where($scheduleColumn, $personnelId)
                        ->where('acknowledged', false)
                        ->whereDate('scheduled_date', '>=', Carbon::today())
                        ->count();
                    
                    if ($unacknowledgedSchedules > 0) {
                        $tasks[] = [
                            'type' => 'schedules',
                            'title' => 'Unacknowledged Shifts',
                            'description' => "You have {$unacknowledgedSchedules} upcoming shift(s) to acknowledge",
                            'count' => $unacknowledgedSchedules,
                            'icon' => 'calendar-check',
                            'color' => 'info',
                            'route' => route('security.schedules.index', [], false) ?: '#'
                        ];
                    }
                }
            }
            
            // Check for pending incidents
            if (Schema::hasTable('security_incidents')) {
                $incidentColumn = $this->findPersonnelColumn('security_incidents');
                if ($incidentColumn) {
                    $pendingIncidents = DB::table('security_incidents')
                        ->where($incidentColumn, $personnelId)
                        ->where('status', 'pending_review')
                        ->count();
                    
                    if ($pendingIncidents > 0) {
                        $tasks[] = [
                            'type' => 'incidents',
                            'title' => 'Incidents to Review',
                            'description' => "You have {$pendingIncidents} incident report(s) pending review",
                            'count' => $pendingIncidents,
                            'icon' => 'exclamation-triangle',
                            'color' => 'danger',
                            'route' => route('security.incidents.pending', [], false) ?: '#'
                        ];
                    }
                }
            }
            
            // Check for upcoming handovers
            if (Schema::hasTable('security_schedules')) {
                $scheduleColumn = $this->findPersonnelColumn('security_schedules');
                if ($scheduleColumn) {
                    $upcomingHandovers = DB::table('security_schedules')
                        ->where($scheduleColumn, $personnelId)
                        ->where('status', 'in_progress')
                        ->whereNotNull('checked_in_at')
                        ->whereNull('checked_out_at')
                        ->whereDate('scheduled_date', Carbon::today())
                        ->count();
                    
                    if ($upcomingHandovers > 0) {
                        $tasks[] = [
                            'type' => 'handover',
                            'title' => 'Upcoming Handover',
                            'description' => 'Complete your shift handover before leaving',
                            'count' => $upcomingHandovers,
                            'icon' => 'exchange-alt',
                            'color' => 'primary',
                            'route' => route('security.schedules.handover', [], false) ?: '#'
                        ];
                    }
                }
            }
            
            return $tasks;
        } catch (\Exception $e) {
            \Log::error('Error getting pending tasks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent notifications
     */
    protected function getRecentNotifications($user)
    {
        try {
            if (!Schema::hasTable('notifications')) {
                return $this->getSampleNotifications();
            }
            
            // Find the correct user column
            $userColumns = ['user_id', 'userid', 'user_uuid', 'recipient_id', 'notifiable_id', 'user'];
            $userColumn = null;
            
            foreach ($userColumns as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    $userColumn = $column;
                    break;
                }
            }
            
            if ($userColumn) {
                $notifications = DB::table('notifications')
                    ->where($userColumn, $user->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
                
                if ($notifications->isNotEmpty()) {
                    return $notifications->map(function ($notification) {
                        return [
                            'id' => $notification->id,
                            'title' => $notification->title ?? $notification->subject ?? 'Notification',
                            'message' => $notification->message ?? $notification->body ?? '',
                            'type' => $notification->type ?? $notification->notification_type ?? 'info',
                            'is_read' => isset($notification->read_at) && $notification->read_at !== null,
                            'created_at' => isset($notification->created_at) ? Carbon::parse($notification->created_at)->diffForHumans() : 'recent',
                            'formatted_time' => isset($notification->created_at) ? Carbon::parse($notification->created_at)->diffForHumans() : 'Just now',
                            'created_at_raw' => isset($notification->created_at) ? Carbon::parse($notification->created_at)->toISOString() : now()->toISOString(),
                            'action_url' => $notification->action_url ?? null,
                            'icon' => 'bell',
                            'color' => $this->getNotificationColor($notification->type ?? 'info')
                        ];
                    })->toArray();
                }
            }
            
            return $this->getSampleNotifications();
            
        } catch (\Exception $e) {
            \Log::error('Error getting notifications: ' . $e->getMessage());
            return $this->getSampleNotifications();
        }
    }

    /**
     * Get sample notifications for testing
     */
    protected function getSampleNotifications()
    {
        return [
            [
                'id' => 1,
                'title' => 'Welcome to Security Dashboard',
                'message' => 'Your security dashboard is ready. You can manage your shifts and reports here.',
                'type' => 'info',
                'is_read' => false,
                'created_at' => 'Just now',
                'formatted_time' => 'Just now',
                'created_at_raw' => now()->toISOString(),
                'action_url' => null,
                'icon' => 'bell',
                'color' => 'primary'
            ]
        ];
    }

    /**
     * Get notification color based on type
     */
    protected function getNotificationColor($type)
    {
        $colors = [
            'info' => 'primary',
            'warning' => 'warning',
            'danger' => 'danger',
            'success' => 'success',
            'security_broadcast' => 'primary'
        ];
        
        return $colors[$type] ?? 'primary';
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics($personnelId)
    {
        try {
            $last30Days = Carbon::now()->subDays(30);
            $tableName = 'security_schedules';
            
            if (!Schema::hasTable($tableName)) {
                return $this->getEmptyPerformanceMetrics();
            }
            
            $personnelColumn = $this->findPersonnelColumn($tableName);
            
            if (!$personnelColumn) {
                return $this->getEmptyPerformanceMetrics();
            }
            
            $schedules = DB::table($tableName)
                ->where($personnelColumn, $personnelId)
                ->where('scheduled_date', '>=', $last30Days)
                ->get();
            
            $totalSchedules = $schedules->count();
            $completedSchedules = $schedules->where('status', 'completed')->count();
            
            // Calculate completion rate
            $completionRate = $totalSchedules > 0 ? round(($completedSchedules / $totalSchedules) * 100) : 0;
            
            // Get reports submitted
            $reportsSubmitted = 0;
            if (Schema::hasTable('security_reports')) {
                $reportColumn = $this->findPersonnelColumn('security_reports');
                if ($reportColumn) {
                    $reportsSubmitted = DB::table('security_reports')
                        ->where($reportColumn, $personnelId)
                        ->where('created_at', '>=', $last30Days)
                        ->where('status', 'submitted')
                        ->count();
                }
            }
            
            // Get incidents reported
            $incidentsReported = 0;
            if (Schema::hasTable('security_incidents')) {
                $incidentColumn = $this->findPersonnelColumn('security_incidents');
                if ($incidentColumn) {
                    $incidentsReported = DB::table('security_incidents')
                        ->where($incidentColumn, $personnelId)
                        ->where('created_at', '>=', $last30Days)
                        ->count();
                }
            }
            
            return [
                'completion_rate' => $completionRate,
                'punctuality_rate' => 85, // Default value
                'incidents_reported' => $incidentsReported,
                'reports_submitted' => $reportsSubmitted,
                'total_shifts' => $totalSchedules,
                'completed_shifts' => $completedSchedules
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting performance metrics: ' . $e->getMessage());
            return $this->getEmptyPerformanceMetrics();
        }
    }

    /**
     * Get empty performance metrics array
     */
    protected function getEmptyPerformanceMetrics()
    {
        return [
            'completion_rate' => 0,
            'punctuality_rate' => 0,
            'incidents_reported' => 0,
            'reports_submitted' => 0,
            'total_shifts' => 0,
            'completed_shifts' => 0
        ];
    }

    /**
     * Get assigned post information
     */
    protected function getAssignedPost($personnelId)
    {
        try {
            // Check if security_posts table exists
            if (!Schema::hasTable('security_posts')) {
                return null;
            }
            
            // Try to find assigned post from schedule
            $today = Carbon::today()->format('Y-m-d');
            $scheduleTable = 'security_schedules';
            
            if (Schema::hasTable($scheduleTable)) {
                $personnelColumn = $this->findPersonnelColumn($scheduleTable);
                
                if ($personnelColumn) {
                    $schedule = DB::table($scheduleTable)
                        ->where($personnelColumn, $personnelId)
                        ->whereDate('scheduled_date', $today)
                        ->first();
                    
                    if ($schedule && isset($schedule->post_id)) {
                        $post = DB::table('security_posts')->where('id', $schedule->post_id)->first();
                        if ($post) {
                            return [
                                'id' => $post->id,
                                'name' => $post->name ?? $post->post_name ?? 'N/A',
                                'location' => $post->location ?? $post->address ?? 'N/A',
                                'coordinates' => $post->coordinates ?? null,
                                'checkpoint_code' => $post->checkpoint_code ?? null,
                                'qr_code' => null,
                                'qr_code_url' => null
                            ];
                        }
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::error('Error getting assigned post: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current status
     */
    protected function getCurrentStatus($personnelId)
    {
        try {
            $todaySchedule = $this->getTodaySchedule($personnelId);
            
            if (!$todaySchedule) {
                return [
                    'status' => 'off_duty',
                    'message' => 'You have no shift scheduled for today',
                    'icon' => 'fa-clock',
                    'checked_in_at' => null
                ];
            }
            
            if ($todaySchedule['checked_in_at'] && !$todaySchedule['checked_out_at']) {
                return [
                    'status' => 'on_duty',
                    'message' => 'You are currently on duty',
                    'icon' => 'fa-user-check',
                    'checked_in_at' => $todaySchedule['checked_in_at']
                ];
            }
            
            if ($todaySchedule['checked_out_at']) {
                return [
                    'status' => 'completed',
                    'message' => 'Your shift has been completed',
                    'icon' => 'fa-check-circle',
                    'checked_in_at' => $todaySchedule['checked_in_at']
                ];
            }
            
            return [
                'status' => 'pending_checkin',
                'message' => 'Ready to check in',
                'icon' => 'fa-sign-in-alt',
                'is_late' => false,
                'shift_start_time' => $todaySchedule['start_time'],
                'checked_in_at' => null
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting current status: ' . $e->getMessage());
            return $this->getEmptyCurrentStatus();
        }
    }

    /**
     * Get empty current status array
     */
    protected function getEmptyCurrentStatus()
    {
        return [
            'status' => 'off_duty',
            'message' => 'Unable to determine status',
            'icon' => 'fa-exclamation-triangle',
            'checked_in_at' => null
        ];
    }

    /**
     * Check if security personnel can check in from schedule object
     */
    protected function canCheckInFromSchedule($schedule)
    {
        if (!$schedule) return false;
        if (isset($schedule->checked_in_at) && $schedule->checked_in_at) return false;
        if (isset($schedule->status) && $schedule->status === 'cancelled') return false;
        
        $shiftStartTime = $schedule->start_time ?? null;
        if (!$shiftStartTime) return true;
        
        $shiftStart = Carbon::parse($shiftStartTime);
        $now = Carbon::now();
        
        return $now->between($shiftStart->copy()->subMinutes(30), $shiftStart->copy()->addMinutes(60));
    }

    /**
     * Check if security personnel can check out from schedule object
     */
    protected function canCheckOutFromSchedule($schedule)
    {
        if (!$schedule) return false;
        if (!isset($schedule->checked_in_at) || !$schedule->checked_in_at) return false;
        if (isset($schedule->checked_out_at) && $schedule->checked_out_at) return false;
        if (isset($schedule->status) && $schedule->status === 'cancelled') return false;
        
        $shiftEndTime = $schedule->end_time ?? null;
        if (!$shiftEndTime) return true;
        
        $shiftEnd = Carbon::parse($shiftEndTime);
        $now = Carbon::now();
        
        return $now >= $shiftEnd;
    }

    /**
     * Check if security personnel is late from schedule object
     */
    protected function checkIfLateFromSchedule($schedule)
    {
        if (!$schedule || !isset($schedule->checked_in_at) || !$schedule->checked_in_at) return false;
        
        $shiftStartTime = $schedule->start_time ?? null;
        if (!$shiftStartTime) return false;
        
        $gracePeriod = 15;
        $shiftStart = Carbon::parse($shiftStartTime);
        $checkedIn = Carbon::parse($schedule->checked_in_at);
        
        return $checkedIn->gt($shiftStart->copy()->addMinutes($gracePeriod));
    }

    /**
     * Calculate remaining time from schedule object
     */
    protected function calculateRemainingTimeFromSchedule($schedule)
    {
        if (!$schedule || !isset($schedule->checked_in_at) || !$schedule->checked_in_at) {
            return null;
        }
        if (isset($schedule->checked_out_at) && $schedule->checked_out_at) {
            return null;
        }
        
        $shiftEndTime = $schedule->end_time ?? null;
        if (!$shiftEndTime) return null;
        
        $shiftEnd = Carbon::parse($shiftEndTime);
        $now = Carbon::now();
        
        if ($now >= $shiftEnd) {
            return '00:00:00';
        }
        
        $remaining = $shiftEnd->diff($now);
        return $remaining->format('%H:%I:%S');
    }

    /**
     * Calculate on-time rate from collection
     */
    protected function calculateOnTimeRateFromCollection($collection)
    {
        $total = $collection->count();
        if ($total === 0) return 0;
        
        $onTime = $collection->where('status', 'present')->count();
        return round(($onTime / $total) * 100);
    }

    /**
     * Authorize security personnel access
     */
    protected function authorizeSecurity()
    {
        if (!Auth::check()) {
            abort(401, 'Unauthenticated.');
        }

        $user = Auth::user();
        
        $isSecurityByType = ($user->type == 6);
        $hasSecurityRole = $user->hasRole('security-personnel');
        
        if (!$isSecurityByType && !$hasSecurityRole) {
            abort(403, 'Unauthorized access. Security access required.');
        }
    }

    /**
     * Get dashboard data via AJAX
     */
    public function getDashboardData(Request $request)
    {
        try {
            $this->authorizeSecurity();
            
            $user = Auth::user();
            $personnelId = $this->getPersonnelId($user);
            $type = $request->get('type', 'all');
            
            $data = [];
            
            switch ($type) {
                case 'schedule':
                    $data['today_schedule'] = $this->getTodaySchedule($personnelId);
                    $data['upcoming_shifts'] = $this->getUpcomingShifts($personnelId);
                    break;
                case 'attendance':
                    $data['attendance_stats'] = $this->getAttendanceStats($personnelId);
                    $data['current_status'] = $this->getCurrentStatus($personnelId);
                    break;
                case 'tasks':
                    $data['pending_tasks'] = $this->getPendingTasks($personnelId) ?? [];
                    break;
                case 'notifications':
                    $data['notifications'] = $this->getRecentNotifications($user) ?? [];
                    break;
                case 'performance':
                    $data['performance_metrics'] = $this->getPerformanceMetrics($personnelId) ?? [];
                    break;
                default:
                    $data = [
                        'today_schedule' => $this->getTodaySchedule($personnelId),
                        'upcoming_shifts' => $this->getUpcomingShifts($personnelId),
                        'attendance_stats' => $this->getAttendanceStats($personnelId),
                        'pending_tasks' => $this->getPendingTasks($personnelId) ?? [],
                        'notifications' => $this->getRecentNotifications($user) ?? [],
                        'performance_metrics' => $this->getPerformanceMetrics($personnelId) ?? [],
                        'assigned_post' => $this->getAssignedPost($personnelId),
                        'current_status' => $this->getCurrentStatus($personnelId)
                    ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Security Dashboard API Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh dashboard data
     */
    public function refreshDashboard(Request $request)
    {
        try {
            $this->authorizeSecurity();
            
            $user = Auth::user();
            
            cache()->forget("security_dashboard_{$user->id}");
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data refreshed successfully',
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Security Dashboard Refresh Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh dashboard data'
            ], 500);
        }
    }

    /**
     * Export dashboard data
     */
    public function exportDashboard(Request $request)
    {
        try {
            $this->authorizeSecurity();
            
            $user = Auth::user();
            $personnelId = $this->getPersonnelId($user);
            $format = $request->get('format', 'csv');
            
            $data = [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id ?? 'N/A'
                ],
                'attendance_stats' => $this->getAttendanceStats($personnelId),
                'performance_metrics' => $this->getPerformanceMetrics($personnelId),
                'exported_at' => now()->toDateTimeString()
            ];
            
            if ($format === 'csv') {
                return $this->exportAsCsv($data);
            }
            
            return response()->json($data);
            
        } catch (\Exception $e) {
            \Log::error('Export dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export dashboard data'
            ], 500);
        }
    }

    /**
     * Export data as CSV
     */
    protected function exportAsCsv($data)
    {
        $filename = "security_dashboard_" . now()->format('Y-m-d_His') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\""
        ];
        
        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['Security Dashboard Export']);
            fputcsv($file, ['Exported At', $data['exported_at']]);
            fputcsv($file, []);
            
            fputcsv($file, ['User Information']);
            fputcsv($file, ['Name', $data['user']['name']]);
            fputcsv($file, ['Email', $data['user']['email']]);
            fputcsv($file, ['Employee ID', $data['user']['employee_id']]);
            fputcsv($file, []);
            
            fputcsv($file, ['Attendance Statistics - Current Month']);
            fputcsv($file, ['Metric', 'Value']);
            foreach ($data['attendance_stats']['current_month'] as $key => $value) {
                fputcsv($file, [str_replace('_', ' ', ucfirst($key)), $value]);
            }
            fputcsv($file, []);
            
            fputcsv($file, ['Performance Metrics']);
            fputcsv($file, ['Metric', 'Value']);
            foreach ($data['performance_metrics'] as $key => $value) {
                fputcsv($file, [str_replace('_', ' ', ucfirst($key)), $value]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}