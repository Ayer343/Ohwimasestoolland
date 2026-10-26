<?php

namespace App\Traits;

use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\SecuritySupervisorAssignment;
use Illuminate\Support\Facades\DB;

trait HasSupervisorAssignments
{
    /* ============================================================
     | RELATIONSHIPS
     * ============================================================ */

    public function supervisorAssignments()
    {
        return $this->hasMany(SecuritySupervisorAssignment::class, 'user_id');
    }

    public function activeSupervisorAssignments()
    {
        return $this->supervisorAssignments()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    public function schedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'security_user_id');
    }

    public function assignedSchedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'assigned_by');
    }

    public function approvedSchedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'approved_by');
    }

    /* ============================================================
     | POST QUERIES
     * ============================================================ */

    public function supervisedPosts()
    {
        return $this->activeSupervisorAssignments()
            ->pluck('security_post_id')
            ->filter()
            ->values()
            ->toArray();
    }

    public function supervisedPostsCollection()
    {
        $postIds = $this->supervisedPosts();

        return SecurityPost::whereIn('id', $postIds)->get();
    }

    public function currentSupervisorAssignmentForPost($postId)
    {
        return $this->activeSupervisorAssignments()
            ->where('security_post_id', $postId)
            ->first();
    }

    /* ============================================================
     | CHECKS
     * ============================================================ */

    public function isSecuritySupervisor(): bool
    {
        if (!$this->isSecurityPersonnel()) {
            return false;
        }

        return $this->activeSupervisorAssignments()->exists();
    }

    public function isSupervisorForSecurity(): bool
    {
        return $this->isSecuritySupervisor();
    }

    public function isSupervisorCapable(): bool
    {
        return $this->isSecurityPersonnel()
            && $this->can_be_supervisor
            && $this->supervisor_level > self::SUPERVISOR_LEVEL_NONE;
    }

    /**
     * ✅ ADDED — dedupe from User model
     */
    public function hasActiveSupervisorAssignment(): bool
    {
        return $this->activeSupervisorAssignments()->exists();
    }

    /**
     * ✅ ADDED — dedupe from User model (plural alias)
     */
    public function hasActiveSupervisorAssignments(): bool
    {
        return $this->hasActiveSupervisorAssignment();
    }

    /* ============================================================
     | PERMISSIONS
     * ============================================================ */

    public function hasSupervisorPermission($permission, $postId = null): bool
    {
        if (!$this->isSecurityPersonnel()) {
            return false;
        }

        $query = $this->activeSupervisorAssignments();

        if ($postId) {
            $query->where('security_post_id', $postId);
        }

        foreach ($query->get() as $assignment) {
            if (in_array($permission, $assignment->permissions_list ?? [])) {
                return true;
            }
        }

        return false;
    }

    public function getSupervisorPermissionsAttribute(): array
    {
        if (!$this->isSecurityPersonnel()) {
            return [];
        }

        $permissions = [];

        foreach ($this->activeSupervisorAssignments()->get() as $assignment) {
            $permissions = array_merge($permissions, $assignment->permissions_list ?? []);
        }

        return array_values(array_unique($permissions));
    }

    /* ============================================================
     | ACCESSORS
     * ============================================================ */

    public function getSupervisedPostsAttribute()
    {
        if (!$this->isSecurityPersonnel()) {
            return collect();
        }

        $postIds = $this->activeSupervisorAssignments()
            ->pluck('security_post_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        return SecurityPost::whereIn('id', $postIds)->get();
    }

    public function getSupervisorLevelNameAttribute(): string
    {
        if (!$this->isSecurityPersonnel()) {
            return 'Not Applicable';
        }

        $levels = [
            self::SUPERVISOR_LEVEL_NONE           => 'Security Personnel',
            self::SUPERVISOR_LEVEL_TEAM_LEAD      => 'Team Lead',
            self::SUPERVISOR_LEVEL_SECTION_LEAD   => 'Section Lead',
            self::SUPERVISOR_LEVEL_POST_COMMANDER => 'Post Commander',
        ];

        return $levels[$this->supervisor_level ?? self::SUPERVISOR_LEVEL_NONE] ?? 'Unknown';
    }

    /* ============================================================
     | POST-LEVEL SUPERVISION
     * ============================================================ */

    public function isPrimarySupervisorForPost($postId = null): bool
    {
        $query = $this->activeSupervisorAssignments()->where('is_primary_supervisor', true);

        if ($postId) {
            $query->where('security_post_id', $postId);
        }

        return $query->exists();
    }

    public function primarySupervisorAssignments()
    {
        return $this->activeSupervisorAssignments()->where('is_primary_supervisor', true);
    }

    public function getSupervisorTypeForPost($postId)
    {
        $assignment = $this->activeSupervisorAssignments()
            ->where('security_post_id', $postId)
            ->first();

        return $assignment ? $assignment->supervisor_type : null;
    }

    public function getSupervisorTypeNameForPost($postId): string
    {
        $type = $this->getSupervisorTypeForPost($postId);

        if (!$type) {
            return 'Not Assigned';
        }

        $typeNames = [
            'post_supervisor'     => 'Post Supervisor',
            'shift_supervisor'    => 'Shift Supervisor',
            'area_supervisor'     => 'Area Supervisor',
            'relief_supervisor'   => 'Relief Supervisor',
            'training_supervisor' => 'Supervisor in Training',
        ];

        return $typeNames[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public function getSupervisedPostsWithTypes(): array
    {
        $assignments = $this->activeSupervisorAssignments()->with('post')->get();

        $result = [];

        foreach ($assignments as $assignment) {
            if ($assignment->security_post_id) {
                $result[] = [
                    'post_id'              => $assignment->security_post_id,
                    'post_name'            => $assignment->post->name ?? 'Unknown',
                    'supervisor_type'      => $assignment->supervisor_type,
                    'supervisor_type_name' => $assignment->supervisor_type_name,
                    'is_primary'           => $assignment->is_primary_supervisor,
                ];
            }
        }

        return $result;
    }

    public function supervisesPost($postId): bool
    {
        return $this->activeSupervisorAssignments()
            ->where('security_post_id', $postId)
            ->exists();
    }

    public function supervisesAnyPost(array $postIds): bool
    {
        return $this->activeSupervisorAssignments()
            ->whereIn('security_post_id', $postIds)
            ->exists();
    }

    /* ============================================================
     | SCHEDULES
     * ============================================================ */

    public function getSupervisedSchedules($date = null)
    {
        $postIds = $this->supervisedPosts();

        if (empty($postIds)) {
            return collect();
        }

        $query = SecuritySchedule::whereIn('security_post_id', $postIds);

        if ($date) {
            $query->whereDate('assignment_date', $date);
        }

        return $query->with(['securityUser', 'shift', 'post'])->get();
    }

    public function getTodayTeam()
    {
        return $this->getSupervisedSchedules(today());
    }

    /* ============================================================
     | PROMOTION / DEMOTION
     * ============================================================ */

    public function promoteToSupervisor($level, $promotedBy = null): bool
    {
        if (!$this->isSecurityPersonnel()) {
            return false;
        }

        if (!in_array($level, [
            self::SUPERVISOR_LEVEL_TEAM_LEAD,
            self::SUPERVISOR_LEVEL_SECTION_LEAD,
            self::SUPERVISOR_LEVEL_POST_COMMANDER,
        ])) {
            return false;
        }

        $oldLevel = $this->supervisor_level;

        $result = $this->update([
            'supervisor_level'  => $level,
            'can_be_supervisor' => true,
            'supervisor_score'  => max($this->supervisor_score ?? 0, 50),
        ]);

        if ($result) {
            $history   = $this->metadata['supervisor_promotion_history'] ?? [];
            $history[] = [
                'old_level'        => $oldLevel,
                'new_level'        => $level,
                'promoted_at'      => now()->toISOString(),
                'promoted_by'      => $promotedBy ?? auth()->id(),
                'promoted_by_name' => auth()->user()?->name ?? 'System',
            ];

            $this->update([
                'metadata' => array_merge($this->metadata ?? [], [
                    'supervisor_promotion_history' => $history,
                    'supervisor_promoted_at'       => now()->toISOString(),
                ]),
            ]);
        }

        return $result;
    }

    public function demoteFromSupervisor(): bool
    {
        if (!$this->isSecurityPersonnel()) {
            return false;
        }

        $this->activeSupervisorAssignments()->update(['is_active' => false]);

        return $this->update([
            'supervisor_level'  => self::SUPERVISOR_LEVEL_NONE,
            'can_be_supervisor' => false,
        ]);
    }

    /* ============================================================
     | SCORING
     * ============================================================ */

    public function updateSupervisorScore($score): bool
    {
        if (!$this->isSecurityPersonnel()) {
            return false;
        }

        $score = max(0, min(100, $score));

        return $this->update(['supervisor_score' => $score]);
    }

    public function incrementSupervisorScore($points = 1): bool
    {
        if (!$this->isSecurityPersonnel()) {
            return false;
        }

        $newScore = min(100, ($this->supervisor_score ?? 0) + $points);

        return $this->update(['supervisor_score' => $newScore]);
    }

    public function decrementSupervisorScore($points = 1): bool
    {
        if (!$this->isSecurityPersonnel()) {
            return false;
        }

        $newScore = max(0, ($this->supervisor_score ?? 0) - $points);

        return $this->update(['supervisor_score' => $newScore]);
    }

    /* ============================================================
     | STATS / DASHBOARD
     * ============================================================ */

    public function getSupervisorStats(): array
    {
        if (!$this->isSecurityPersonnel()) {
            return [
                'is_supervisor' => false,
                'message'       => 'User is not security personnel',
            ];
        }

        $activeAssignments = $this->activeSupervisorAssignments()->get();
        $totalAssignments  = $this->supervisorAssignments()->count();

        $postIds = $activeAssignments->pluck('security_post_id')->filter()->unique()->values()->toArray();

        $todaySchedules = $this->getTodayTeam();

        return [
            'is_supervisor'          => $this->isSecuritySupervisor(),
            'is_supervisor_capable'  => $this->isSupervisorCapable(),
            'supervisor_level'       => $this->supervisor_level,
            'supervisor_level_name'  => $this->supervisor_level_name,
            'supervisor_score'       => $this->supervisor_score ?? 0,
            'can_be_supervisor'      => $this->can_be_supervisor ?? false,

            'active_assignments_count' => $activeAssignments->count(),
            'total_assignments_count'  => $totalAssignments,
            'posts_supervised_count'   => count($postIds),
            'posts_supervised'         => $postIds,

            'primary_assignments_count' => $activeAssignments->where('is_primary_supervisor', true)->count(),

            'team_today_count'               => $todaySchedules->count(),
            'pending_verifications_today'    => $todaySchedules->where('check_in_status', 'pending_verification')->count(),

            'by_type' => $activeAssignments->groupBy('supervisor_type')
                ->map(fn ($group) => $group->count())
                ->toArray(),
        ];
    }

    public function getSupervisorDashboardData(): array
    {
        $stats = $this->getSupervisorStats();

        if (!$stats['is_supervisor']) {
            return $stats;
        }

        $postIds = $stats['posts_supervised'];

        $pendingSwaps = DB::table('swap_requests')
            ->whereIn('post_id', $postIds)
            ->where('status', 'pending')
            ->count();

        $pendingOvertime = DB::table('overtime_requests')
            ->whereIn('post_id', $postIds)
            ->where('status', 'pending')
            ->count();

        return array_merge($stats, [
            'pending_approvals' => [
                'swap_requests'     => $pendingSwaps,
                'overtime_requests' => $pendingOvertime,
                'total'             => $pendingSwaps + $pendingOvertime,
            ],
            'recent_activities' => $this->getRecentSupervisorActivities(),
        ]);
    }

    protected function getRecentSupervisorActivities($limit = 10)
    {
        return [];
    }

    /* ============================================================
     | PENDING APPROVALS (moved here from User)
     * ============================================================ */

    /**
     * ✅ ADDED — dedupe from User model.
     * Counts pending swap / overtime / verification items across all
     * supervised posts.
     */
    public function getPendingApprovalsCount(): int
    {
        $postIds = $this->supervisedPosts();

        if (empty($postIds)) {
            return 0;
        }

        $count = 0;

        $count += \App\Models\SwapRequest::whereHas('schedule', fn ($q) => $q->whereIn('security_post_id', $postIds))
            ->where('status', 'pending')
            ->count();

        $count += \App\Models\OvertimeRequest::whereHas('schedule', fn ($q) => $q->whereIn('security_post_id', $postIds))
            ->where('status', 'pending')
            ->count();

        $count += SecuritySchedule::whereIn('security_post_id', $postIds)
            ->whereDate('assignment_date', now()->toDateString())
            ->where('check_in_status', 'pending_verification')
            ->count();

        return $count;
    }
}