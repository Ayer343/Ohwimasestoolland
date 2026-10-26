<?php

namespace App\Traits;

use App\Models\PlanAgentAssignment;
use App\Models\RegistrationPlan;
use App\Models\UserInvitation;

/**
 * Field agent statistics, performance metrics, and assignment calculations.
 *
 * Behaviour identical to the in-model implementation it replaces.
 * All methods are safe to call for non-field-agents (they return empty/false).
 */
trait HasFieldAgentStats
{
    /* ============================================================
     | RELATIONSHIPS
     * ============================================================ */

    public function assignedPlans()
    {
        return $this->belongsToMany(RegistrationPlan::class, 'plan_agent_assignments', 'agent_id', 'plan_id')
            ->withPivot([
                'id', 'assigned_by', 'assigned_at', 'is_active', 'removed_at',
                'removal_reason', 'properties_registered', 'last_activity_at',
                'created_at', 'updated_at',
            ])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    public function planAssignments()
    {
        return $this->hasMany(PlanAgentAssignment::class, 'agent_id');
    }

    public function activePlanAssignments()
    {
        return $this->hasMany(PlanAgentAssignment::class, 'agent_id')->where('is_active', true);
    }

    public function completedPlans()
    {
        return $this->belongsToMany(RegistrationPlan::class, 'plan_agent_assignments', 'agent_id', 'plan_id')
            ->withPivot('properties_registered', 'assigned_at')
            ->where('registration_plans.status', RegistrationPlan::STATUS_COMPLETED);
    }

    public function activePlans()
    {
        return $this->belongsToMany(RegistrationPlan::class, 'plan_agent_assignments', 'agent_id', 'plan_id')
            ->withPivot('properties_registered', 'last_activity_at')
            ->where('plan_agent_assignments.is_active', true)
            ->whereIn('registration_plans.status', [
                RegistrationPlan::STATUS_ASSIGNED,
                RegistrationPlan::STATUS_IN_PROGRESS,
            ]);
    }

    public function overduePlans()
    {
        return $this->belongsToMany(RegistrationPlan::class, 'plan_agent_assignments', 'agent_id', 'plan_id')
            ->withPivot('properties_registered', 'assigned_at')
            ->where('plan_agent_assignments.is_active', true)
            ->where('registration_plans.registration_end_date', '<', now())
            ->whereNotIn('registration_plans.status', [
                RegistrationPlan::STATUS_COMPLETED,
                RegistrationPlan::STATUS_CANCELLED,
            ]);
    }

    /**
     * Placeholder reports relation — returns empty until Report model exists.
     */
    public function reports()
    {
        return $this->hasMany(\App\Models\Activity::class, 'user_id')->whereKey(null);
    }

    public function getPendingReportsCountAttribute(): int
    {
        return 0;
    }

    /* ============================================================
     | ACCESSORS
     * ============================================================ */

    public function getFieldAgentStatsAttribute(): array
    {
        if (!$this->isFieldAgent()) {
            return ['is_field_agent' => false];
        }

        $totalAssignments     = $this->planAssignments()->count();
        $activeAssignments    = $this->activePlanAssignments()->count();
        $completedAssignments = $this->completedPlans()->count();

        return [
            'is_field_agent'              => true,
            'total_assignments_count'     => $totalAssignments,
            'active_assignments_count'    => $activeAssignments,
            'completed_assignments_count' => $completedAssignments,
            'active_plans_count'          => $this->activePlans()->count(),
            'completed_plans_count'       => $completedAssignments,
            'overdue_plans_count'         => $this->overduePlans()->count(),
            'total_properties_registered' => (int) $this->planAssignments()->sum('properties_registered'),
            'invitation_status'           => $this->invitation_status,
            'performance_score'           => $this->calculatePerformanceScore(),
            'acceptance_rate'             => $this->calculateAcceptanceRate(),
            'average_completion_time'     => $this->calculateAverageCompletionTime(),
            'assignment_breakdown'        => $this->getAssignmentBreakdown(),
            'performance_metrics'         => $this->getPerformanceMetricsFromAssignments(),
        ];
    }

    public function getIsVerifiedAgentAttribute(): bool
    {
        return $this->isFieldAgent()
            && $this->status === static::STATUS_ACTIVE
            && !is_null($this->invitation_accepted_at)
            && !is_null($this->phone_verified_at);
    }

    public function getPerformanceMetricsAttribute(): array
    {
        if (!$this->isFieldAgent()) {
            return [];
        }

        return [
            'total_assignments'           => $this->planAssignments()->count(),
            'active_assignments'          => $this->activePlanAssignments()->count(),
            'completed_assignments'       => $this->completedPlans()->count(),
            'completion_rate'             => $this->calculatePerformanceScore(),
            'properties_registered'       => (int) $this->planAssignments()->sum('properties_registered'),
            'average_properties_per_plan' => $this->calculateAveragePropertiesPerPlan(),
            'last_activity'               => $this->last_activity_at?->diffForHumans(),
            'member_since'                => $this->invitation_accepted_at?->diffForHumans(),
            'assignment_types'            => $this->getAssignmentTypeBreakdown(),
            'total_assignment_duration'   => $this->calculateTotalAssignmentDuration(),
            'average_productivity_rate'   => $this->calculateAverageProductivityRate(),
        ];
    }

    /* ============================================================
     | CALCULATIONS
     * ============================================================ */

    private function calculatePerformanceScore(): float
    {
        $total = $this->planAssignments()->count();

        if ($total === 0) {
            return 0.0;
        }

        return round(($this->completedPlans()->count() / $total) * 100, 2);
    }

    private function calculateAcceptanceRate(): float
    {
        $total = $this->invitations()->count();

        if ($total === 0) {
            return 0.0;
        }

        return round(($this->invitations()->where('status', UserInvitation::STATUS_ACCEPTED)->count() / $total) * 100, 2);
    }

    private function calculateAverageCompletionTime(): ?string
    {
        $assignments = $this->planAssignments()
            ->whereHas('plan', fn ($q) => $q->where('status', RegistrationPlan::STATUS_COMPLETED))
            ->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        $total = 0;
        $count = 0;

        foreach ($assignments as $assignment) {
            if ($assignment->plan?->started_at && $assignment->plan?->completed_at) {
                $total += $assignment->plan->started_at->diffInDays($assignment->plan->completed_at);
                $count++;
            }
        }

        return $count === 0 ? null : round($total / $count, 1) . ' days';
    }

    private function calculateAveragePropertiesPerPlan(): float
    {
        $total = $this->planAssignments()->count();

        if ($total === 0) {
            return 0.0;
        }

        return round($this->planAssignments()->sum('properties_registered') / $total, 1);
    }

    private function getAssignmentBreakdown(): array
    {
        return [
            'draft'       => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_DRAFT)->count(),
            'assigned'    => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_ASSIGNED)->count(),
            'in_progress' => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_IN_PROGRESS)->count(),
            'completed'   => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_COMPLETED)->count(),
            'cancelled'   => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_CANCELLED)->count(),
        ];
    }

    private function getAssignmentTypeBreakdown(): array
    {
        $single = $this->assignedPlans()->where('agent_assignment_type', RegistrationPlan::ASSIGNMENT_SINGLE)->count();
        $multi  = $this->assignedPlans()->where('agent_assignment_type', RegistrationPlan::ASSIGNMENT_MULTIPLE)->count();

        return [
            'single_agent_plans'   => $single,
            'multiple_agent_plans' => $multi,
            'total_plans'          => $single + $multi,
        ];
    }

    private function getPerformanceMetricsFromAssignments(): array
    {
        $metrics = [];

        foreach ($this->planAssignments()->get() as $assignment) {
            $metrics[] = [
                'assignment_id'       => $assignment->id,
                'plan_id'             => $assignment->plan_id,
                'plan_zone'           => $assignment->plan->zone ?? 'Unknown',
                'performance_metrics' => method_exists($assignment, 'getPerformanceMetrics') ? $assignment->getPerformanceMetrics() : [],
                'assignment_summary'  => method_exists($assignment, 'getAssignmentSummary') ? $assignment->getAssignmentSummary() : [],
            ];
        }

        return $metrics;
    }

    private function calculateTotalAssignmentDuration(): int
    {
        $total = 0;

        foreach ($this->planAssignments()->get() as $assignment) {
            $total += method_exists($assignment, 'getAssignmentDuration') ? $assignment->getAssignmentDuration() : 0;
        }

        return $total;
    }

    private function calculateAverageProductivityRate(): float
    {
        $duration = $this->calculateTotalAssignmentDuration();

        if ($duration === 0) {
            return 0.0;
        }

        return round($this->planAssignments()->sum('properties_registered') / $duration, 2);
    }

    /* ============================================================
     | SCOPES
     * ============================================================ */

    public function scopeFieldAgents($query)
    {
        return $query->where('type', static::TYPE_FIELD_AGENT);
    }

    public function scopeActiveFieldAgents($query)
    {
        return $query->where('type', static::TYPE_FIELD_AGENT)
            ->where('status', static::STATUS_ACTIVE)
            ->whereNotNull('invitation_accepted_at');
    }

    public function scopePendingFieldAgents($query)
    {
        return $query->where('type', static::TYPE_FIELD_AGENT)
            ->where('status', static::STATUS_PENDING)
            ->whereNull('invitation_accepted_at');
    }
}