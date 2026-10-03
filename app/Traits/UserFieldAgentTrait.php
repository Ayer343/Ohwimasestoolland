<?php

namespace App\Traits;

use App\Models\RegistrationPlan;
use Illuminate\Support\Str;

trait UserFieldAgentTrait
{
    /**
     * Generate agent username
     */
    public function generateAgentUsername(): string
    {
        $base = 'agent_' . strtolower(Str::random(6));
        $username = $base;
        $counter = 1;

        while (self::where('username', $username)->exists()) {
            $username = $base . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Mark invitation as accepted
     */
    public function markInvitationAccepted(): bool
    {
        if (!$this->isFieldAgent()) {
            return false;
        }

        return $this->update([
            'invitation_accepted_at' => now(),
            'status' => self::STATUS_ACTIVE,
            'email_verified_at' => $this->email_verified_at ?? now(),
        ]);
    }

    /**
     * Get field agent statistics for multiple assignments
     */
    public function getFieldAgentStatsAttribute(): array
    {
        if (!$this->isFieldAgent()) {
            return ['is_field_agent' => false];
        }

        $totalAssignments = $this->planAssignments()->count();
        $activeAssignments = $this->activePlanAssignments()->count();

        $totalPropertiesRegistered = $this->planAssignments()->sum('properties_registered');

        return [
            'is_field_agent' => true,
            'total_assignments_count' => $totalAssignments,
            'active_assignments_count' => $activeAssignments,
            'completed_assignments_count' => $this->completedPlans()->count(),
            'active_plans_count' => $this->activePlans()->count(),
            'completed_plans_count' => $this->completedPlans()->count(),
            'overdue_plans_count' => $this->overduePlans()->count(),
            'total_properties_registered' => $totalPropertiesRegistered,
            'invitation_status' => $this->invitation_status,
            'performance_score' => $this->calculatePerformanceScore(),
            'acceptance_rate' => $this->calculateAcceptanceRate(),
            'average_completion_time' => $this->calculateAverageCompletionTime(),
            'assignment_breakdown' => $this->getAssignmentBreakdown(),
            'performance_metrics' => $this->getPerformanceMetricsFromAssignments(),
        ];
    }

    /**
     * Check if agent is verified
     */
    public function getIsVerifiedAgentAttribute(): bool
    {
        return $this->isFieldAgent() && 
               $this->status === self::STATUS_ACTIVE && 
               !is_null($this->invitation_accepted_at) &&
               !is_null($this->phone_verified_at);
    }

    /**
     * Get performance metrics attribute
     */
    public function getPerformanceMetricsAttribute(): array
    {
        if (!$this->isFieldAgent()) {
            return [];
        }

        $totalAssignments = $this->planAssignments()->count();
        $activeAssignments = $this->activePlanAssignments()->count();
        $completedAssignments = $this->completedPlans()->count();

        return [
            'total_assignments' => $totalAssignments,
            'active_assignments' => $activeAssignments,
            'completed_assignments' => $completedAssignments,
            'completion_rate' => $this->calculatePerformanceScore(),
            'properties_registered' => $this->planAssignments()->sum('properties_registered'),
            'average_properties_per_plan' => $this->calculateAveragePropertiesPerPlan(),
            'last_activity' => $this->last_activity_at?->diffForHumans(),
            'member_since' => $this->invitation_accepted_at?->diffForHumans(),
            'assignment_types' => $this->getAssignmentTypeBreakdown(),
            'total_assignment_duration' => $this->calculateTotalAssignmentDuration(),
            'average_productivity_rate' => $this->calculateAverageProductivityRate(),
        ];
    }

    /**
     * Check if agent is currently assigned to a specific plan
     */
    public function isAssignedToPlan($planId): bool
    {
        return $this->activePlanAssignments()
                    ->where('plan_id', $planId)
                    ->exists();
    }

    /**
     * Get agent's current assignments with plan details
     */
    public function getCurrentAssignments()
    {
        return $this->activePlans()
                    ->with(['zone', 'section', 'properties'])
                    ->orderBy('registration_end_date', 'asc')
                    ->get();
    }

    /**
     * Get agent's assignment history
     */
    public function getAssignmentHistory()
    {
        return $this->planAssignments()
                    ->with(['plan.zone', 'plan.section', 'assigner'])
                    ->orderBy('assigned_at', 'desc')
                    ->get();
    }

    /**
     * Get agent's performance for a specific plan
     */
    public function getPlanPerformance($planId): array
    {
        $assignment = $this->planAssignments()
                          ->where('plan_id', $planId)
                          ->first();

        if (!$assignment) {
            return [
                'plan_id' => $planId,
                'properties_registered' => 0,
                'plan_total_estimated' => 0,
                'completion_percentage' => 0,
                'assignment_date' => null,
                'is_active_assignment' => false,
            ];
        }

        $plan = $assignment->plan;
        $propertiesRegistered = $assignment->properties_registered;

        return [
            'plan_id' => $planId,
            'properties_registered' => $propertiesRegistered,
            'plan_total_estimated' => $plan->estimated_houses ?? 0,
            'completion_percentage' => $plan ? round(($propertiesRegistered / $plan->estimated_houses) * 100, 2) : 0,
            'assignment_date' => $assignment->assigned_at?->format('M j, Y'),
            'is_active_assignment' => $assignment->is_active,
            'assignment_metrics' => method_exists($assignment, 'getPerformanceMetrics') ? $assignment->getPerformanceMetrics() : [],
        ];
    }

    /**
     * Calculate performance score based on completed assignments
     */
    private function calculatePerformanceScore(): float
    {
        $totalAssignments = $this->planAssignments()->count();
        $completedAssignments = $this->completedPlans()->count();

        if ($totalAssignments === 0) {
            return 0.0;
        }

        return round(($completedAssignments / $totalAssignments) * 100, 2);
    }

    /**
     * Calculate acceptance rate
     */
    private function calculateAcceptanceRate(): float
    {
        $totalInvitations = $this->invitations()->count();
        $acceptedInvitations = $this->invitations()->where('status', 'accepted')->count();

        if ($totalInvitations === 0) {
            return 0.0;
        }

        return round(($acceptedInvitations / $totalInvitations) * 100, 2);
    }

    /**
     * Calculate average completion time for completed plans
     */
    private function calculateAverageCompletionTime(): ?string
    {
        $completedAssignments = $this->planAssignments()
            ->whereHas('plan', function($query) {
                $query->where('status', RegistrationPlan::STATUS_COMPLETED);
            })
            ->get();

        if ($completedAssignments->isEmpty()) {
            return null;
        }

        $totalDays = 0;
        $count = 0;

        foreach ($completedAssignments as $assignment) {
            if ($assignment->plan && $assignment->plan->started_at && $assignment->plan->completed_at) {
                $totalDays += $assignment->plan->started_at->diffInDays($assignment->plan->completed_at);
                $count++;
            }
        }

        if ($count === 0) {
            return null;
        }

        $averageDays = round($totalDays / $count, 1);
        return $averageDays . ' days';
    }

    /**
     * Calculate average properties registered per plan
     */
    private function calculateAveragePropertiesPerPlan(): float
    {
        $totalAssignments = $this->planAssignments()->count();
        $totalProperties = $this->planAssignments()->sum('properties_registered');

        if ($totalAssignments === 0) {
            return 0.0;
        }

        return round($totalProperties / $totalAssignments, 1);
    }

    /**
     * Get assignment breakdown by status
     */
    private function getAssignmentBreakdown(): array
    {
        return [
            'draft' => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_DRAFT)->count(),
            'assigned' => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_ASSIGNED)->count(),
            'in_progress' => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_IN_PROGRESS)->count(),
            'completed' => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_COMPLETED)->count(),
            'cancelled' => $this->assignedPlans()->where('status', RegistrationPlan::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * Get assignment type breakdown (single vs multiple agent plans)
     */
    private function getAssignmentTypeBreakdown(): array
    {
        $singleAgentPlans = $this->assignedPlans()
            ->where('agent_assignment_type', RegistrationPlan::ASSIGNMENT_SINGLE)
            ->count();
            
        $multipleAgentPlans = $this->assignedPlans()
            ->where('agent_assignment_type', RegistrationPlan::ASSIGNMENT_MULTIPLE)
            ->count();

        return [
            'single_agent_plans' => $singleAgentPlans,
            'multiple_agent_plans' => $multipleAgentPlans,
            'total_plans' => $singleAgentPlans + $multipleAgentPlans,
        ];
    }

    /**
     * Get performance metrics from PlanAgentAssignment model
     */
    private function getPerformanceMetricsFromAssignments(): array
    {
        $assignments = $this->planAssignments()->get();
        $metrics = [];

        foreach ($assignments as $assignment) {
            $metrics[] = [
                'assignment_id' => $assignment->id,
                'plan_id' => $assignment->plan_id,
                'plan_zone' => $assignment->plan->zone ?? 'Unknown',
                'performance_metrics' => method_exists($assignment, 'getPerformanceMetrics') ? $assignment->getPerformanceMetrics() : [],
                'assignment_summary' => method_exists($assignment, 'getAssignmentSummary') ? $assignment->getAssignmentSummary() : [],
            ];
        }

        return $metrics;
    }

    /**
     * Calculate total assignment duration across all assignments
     */
    private function calculateTotalAssignmentDuration(): int
    {
        $assignments = $this->planAssignments()->get();
        $totalDuration = 0;

        foreach ($assignments as $assignment) {
            $duration = method_exists($assignment, 'getAssignmentDuration') ? $assignment->getAssignmentDuration() : 0;
            $totalDuration += $duration;
        }

        return $totalDuration;
    }

    /**
     * Calculate average productivity rate (properties per day)
     */
    private function calculateAverageProductivityRate(): float
    {
        $totalProperties = $this->planAssignments()->sum('properties_registered');
        $totalDuration = $this->calculateTotalAssignmentDuration();

        if ($totalDuration === 0) {
            return 0.0;
        }

        return round($totalProperties / $totalDuration, 2);
    }
}