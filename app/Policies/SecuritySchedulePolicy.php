<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SecuritySchedule;
use App\Models\SecuritySupervisorAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;

class SecuritySchedulePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any security schedules.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user)
    {
        return $user->isSecurityPersonnel() || $user->isAdmin();
    }

    /**
     * Determine if the user can view the security schedule.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function view(User $user, SecuritySchedule $schedule)
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Security personnel can view their own schedules
        if ($user->isSecurityPersonnel() && $schedule->user_id === $user->id) {
            return true;
        }

        // Supervisor can view schedules for their assigned posts
        if ($user->isSupervisor()) {
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('security_post_id', $schedule->security_post_id)
                ->active()
                ->exists();
        }

        return false;
    }

    /**
     * Determine if the user can create security schedules.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user)
    {
        // Only admins and supervisors with appropriate permissions can create
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isSupervisor()) {
            // Check if supervisor has permission to edit schedules
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('can_edit_schedules', true)
                ->active()
                ->exists();
        }

        return false;
    }

    /**
     * Determine if the user can update the security schedule.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function update(User $user, SecuritySchedule $schedule)
    {
        // Admin can update all
        if ($user->isAdmin()) {
            return true;
        }

        // Supervisor can update schedules for their posts if they have permission
        if ($user->isSupervisor()) {
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('security_post_id', $schedule->security_post_id)
                ->where('can_edit_schedules', true)
                ->active()
                ->exists();
        }

        // Security personnel can update their own schedule preferences
        if ($user->isSecurityPersonnel() && $schedule->user_id === $user->id) {
            // Only allow updating specific fields (preferences, availability)
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the security schedule.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function delete(User $user, SecuritySchedule $schedule)
    {
        // Only admins can delete
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the security schedule.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function restore(User $user, SecuritySchedule $schedule)
    {
        // Only admins can restore
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the security schedule.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function forceDelete(User $user, SecuritySchedule $schedule)
    {
        // Only admins can force delete
        return $user->isAdmin();
    }

    // ============================================
    // SUPERVISOR SPECIFIC PERMISSIONS
    // ============================================

    /**
     * Determine if the user can approve shift swaps.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function approveSwap(User $user, SecuritySchedule $schedule)
    {
        // Admin can approve all
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with swap approval permission for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_approve_swaps', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can approve overtime.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function approveOvertime(User $user, SecuritySchedule $schedule)
    {
        // Admin can approve all
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with overtime approval permission for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_approve_overtime', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can verify check-ins.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function verifyCheckin(User $user, SecuritySchedule $schedule)
    {
        // Admin can verify all
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with check-in verification permission for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_verify_checkins', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can mark absent.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function markAbsent(User $user, SecuritySchedule $schedule)
    {
        // Admin can mark absent
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can override check-ins.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function overrideCheckin(User $user, SecuritySchedule $schedule)
    {
        // Admin can override all
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with override permission for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_override_checkins', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can review incidents.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function reviewIncident(User $user, SecuritySchedule $schedule)
    {
        // Admin can review all
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with incident review permission for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_review_incidents', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can request backup.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function requestBackup(User $user, SecuritySchedule $schedule)
    {
        // Admin can request backup
        if ($user->isAdmin()) {
            return true;
        }

        // Any supervisor for this post can request backup
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_request_backup', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can approve breaks.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function approveBreak(User $user, SecuritySchedule $schedule)
    {
        // Admin can approve breaks
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with break approval permission for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_approve_breaks', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can escalate issues.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function escalateIssue(User $user, SecuritySchedule $schedule)
    {
        // Admin can escalate
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with escalation permission for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_escalate_issues', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can view all schedules.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function viewAllSchedules(User $user, SecuritySchedule $schedule)
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with view all schedules permission
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('can_view_all_schedules', true)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can edit schedules.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SecuritySchedule  $schedule
     * @return bool
     */
    public function editSchedule(User $user, SecuritySchedule $schedule)
    {
        // Admin can edit all
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user is a supervisor with edit schedules permission for this post
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $schedule->security_post_id)
            ->where('can_edit_schedules', true)
            ->active()
            ->exists();
    }

    // ============================================
    // POST SPECIFIC PERMISSIONS
    // ============================================

    /**
     * Determine if the user can view a post.
     *
     * @param  \App\Models\User  $user
     * @param  int  $postId
     * @return bool
     */
    public function viewPost(User $user, $postId)
    {
        // Admin can view all posts
        if ($user->isAdmin()) {
            return true;
        }

        // Security personnel can view their assigned posts
        if ($user->isSecurityPersonnel()) {
            // Check if user is assigned to this post
            return SecuritySchedule::where('user_id', $user->id)
                ->where('security_post_id', $postId)
                ->exists();
        }

        // Supervisor can view their supervised posts
        if ($user->isSupervisor()) {
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('security_post_id', $postId)
                ->active()
                ->exists();
        }

        return false;
    }

    /**
     * Determine if the user can view post personnel.
     *
     * @param  \App\Models\User  $user
     * @param  int  $postId
     * @return bool
     */
    public function viewPostPersonnel(User $user, $postId)
    {
        // Admin can view all post personnel
        if ($user->isAdmin()) {
            return true;
        }

        // Supervisor can view personnel for their supervised posts
        if ($user->isSupervisor()) {
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('security_post_id', $postId)
                ->active()
                ->exists();
        }

        return false;
    }

    /**
     * Determine if the user can view post statistics.
     *
     * @param  \App\Models\User  $user
     * @param  int  $postId
     * @return bool
     */
    public function viewPostStatistics(User $user, $postId)
    {
        // Admin can view all post statistics
        if ($user->isAdmin()) {
            return true;
        }

        // Supervisor can view statistics for their supervised posts
        if ($user->isSupervisor()) {
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('security_post_id', $postId)
                ->active()
                ->exists();
        }

        return false;
    }

    /**
     * Determine if the user can export post schedule.
     *
     * @param  \App\Models\User  $user
     * @param  int  $postId
     * @return bool
     */
    public function exportPostSchedule(User $user, $postId)
    {
        // Admin can export all
        if ($user->isAdmin()) {
            return true;
        }

        // Supervisor can export for their supervised posts
        if ($user->isSupervisor()) {
            return SecuritySupervisorAssignment::where('user_id', $user->id)
                ->where('security_post_id', $postId)
                ->active()
                ->exists();
        }

        return false;
    }

    // ============================================
    // TEAM MANAGEMENT PERMISSIONS
    // ============================================

    /**
     * Determine if the user can view team today.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewTeamToday(User $user)
    {
        // Admin can view team today
        if ($user->isAdmin()) {
            return true;
        }

        // Any supervisor can view team today
        return $user->isSupervisor() && SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can view team schedule.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewTeamSchedule(User $user)
    {
        // Admin can view team schedule
        if ($user->isAdmin()) {
            return true;
        }

        // Any supervisor can view team schedule
        return $user->isSupervisor() && SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can view team attendance.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewTeamAttendance(User $user)
    {
        // Admin can view team attendance
        if ($user->isAdmin()) {
            return true;
        }

        // Any supervisor can view team attendance
        return $user->isSupervisor() && SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can view team performance.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewTeamPerformance(User $user)
    {
        // Admin can view team performance
        if ($user->isAdmin()) {
            return true;
        }

        // Any supervisor can view team performance
        return $user->isSupervisor() && SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->exists();
    }

    /**
     * Determine if the user can export team data.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function exportTeamData(User $user)
    {
        // Admin can export team data
        if ($user->isAdmin()) {
            return true;
        }

        // Supervisors can export team data
        return $user->isSupervisor() && SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->exists();
    }

    // ============================================
    // SUPERVISOR ASSIGNMENT PERMISSIONS
    // ============================================

    /**
     * Determine if the user can view supervisor assignments.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewSupervisorAssignments(User $user)
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Supervisors can view their own assignments
        return $user->isSupervisor();
    }

    /**
     * Determine if the user can view pending approvals.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewPendingApprovals(User $user)
    {
        // Admin can view all pending approvals
        if ($user->isAdmin()) {
            return true;
        }

        // Supervisors can view pending approvals
        return $user->isSupervisor();
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if user is a supervisor for a specific post.
     *
     * @param  \App\Models\User  $user
     * @param  int  $postId
     * @return bool
     */
    protected function isSupervisorForPost(User $user, $postId)
    {
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $postId)
            ->active()
            ->exists();
    }

    /**
     * Check if user is a supervisor with a specific permission for a post.
     *
     * @param  \App\Models\User  $user
     * @param  int  $postId
     * @param  string  $permission
     * @return bool
     */
    protected function hasSupervisorPermissionForPost(User $user, $postId, $permission)
    {
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('security_post_id', $postId)
            ->where($permission, true)
            ->active()
            ->exists();
    }

    /**
     * Check if user has any active supervisor assignment.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function hasActiveSupervisorAssignment(User $user)
    {
        return SecuritySupervisorAssignment::where('user_id', $user->id)
            ->active()
            ->exists();
    }
}