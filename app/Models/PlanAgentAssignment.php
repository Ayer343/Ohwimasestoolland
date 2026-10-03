<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanAgentAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plan_id',
        'agent_id',
        'assigned_by',
        'assigned_at',
        'is_active',
        'removed_at',
        'removal_reason',
        'properties_registered',
        'last_activity_at',
        // ✅ ADDED: New fields from migration
        'assignment_type',
        'performance_score',
        'last_invitation_sent_at',
        'invitation_acceptance_time',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'removed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'last_invitation_sent_at' => 'datetime', // ✅ ADDED
        'is_active' => 'boolean',
        'properties_registered' => 'integer',
        'invitation_acceptance_time' => 'integer', // ✅ ADDED
        'performance_score' => 'decimal:2', // ✅ ADDED
    ];

    protected $appends = [
        'is_currently_active',
        'assignment_duration_days',
        'assignment_duration_hours',
        'productivity_rate',
        'completion_percentage',
        'needs_attention',
        'assignment_type_label',
        'performance_grade',
    ];

    // ✅ ADDED: Assignment type constants
    const TYPE_PRIMARY = 'primary';
    const TYPE_SECONDARY = 'secondary';
    const TYPE_BACKUP = 'backup';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($assignment) {
            // Set defaults if not explicitly provided
            if (is_null($assignment->assigned_at)) {
                $assignment->assigned_at = now();
            }
            if (is_null($assignment->is_active)) {
                $assignment->is_active = true;
            }
            if (is_null($assignment->properties_registered)) {
                $assignment->properties_registered = 0;
            }
            if (is_null($assignment->assignment_type)) {
                $assignment->assignment_type = self::TYPE_PRIMARY;
            }
        });

        // Update last_activity_at when properties_registered changes
        static::updating(function ($assignment) {
            if ($assignment->isDirty('properties_registered')) {
                $assignment->last_activity_at = now();
                
                // Auto-calculate performance score when properties change
                $assignment->calculatePerformanceScore();
            }

            // Auto-update removed_at when is_active changes
            if ($assignment->isDirty('is_active')) {
                if (!$assignment->is_active && is_null($assignment->removed_at)) {
                    $assignment->removed_at = now();
                } elseif ($assignment->is_active && !is_null($assignment->removed_at)) {
                    $assignment->removed_at = null;
                    $assignment->removal_reason = null;
                }
            }
        });

        // Calculate performance score when assignment is saved
        static::saved(function ($assignment) {
            if ($assignment->wasChanged('properties_registered') || $assignment->wasChanged('last_activity_at')) {
                $assignment->calculatePerformanceScore();
            }
        });
    }

    /**
     * ✅ FIXED: Relationship to the registration plan (uses plan_id)
     * This matches the foreign key in your RegistrationPlan model
     */
    public function plan()
    {
        return $this->belongsTo(RegistrationPlan::class, 'plan_id');
    }

    /**
     * ✅ ADDED: Alias method for compatibility with controller
     * This allows $assignment->registrationPlan to work
     */
    public function registrationPlan()
    {
        return $this->belongsTo(RegistrationPlan::class, 'plan_id');
    }

    /**
     * Relationship to the assigned agent
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Relationship to the user who assigned this agent
     */
    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Relationship to invitations for this assignment
     */
    public function invitations()
    {
        return $this->hasMany(AgentInvitation::class, 'agent_id', 'agent_id')
                    ->where('plan_id', $this->plan_id);
    }

    /**
     * Relationship to latest invitation
     */
    public function latestInvitation()
    {
        return $this->hasOne(AgentInvitation::class, 'agent_id', 'agent_id')
                    ->where('plan_id', $this->plan_id)
                    ->latest();
    }

    /**
     * ✅ ADDED: Accessors for computed properties
     */
    public function getIsCurrentlyActiveAttribute(): bool
    {
        return $this->is_active && is_null($this->removed_at);
    }

    public function getAssignmentDurationDaysAttribute(): int
    {
        $endDate = $this->removed_at ?? now();
        return $this->assigned_at->diffInDays($endDate);
    }

    public function getAssignmentDurationHoursAttribute(): int
    {
        $endDate = $this->removed_at ?? now();
        return $this->assigned_at->diffInHours($endDate);
    }

    public function getProductivityRateAttribute(): float
    {
        $durationDays = $this->assignment_duration_days;
        if ($durationDays === 0) {
            return $this->properties_registered > 0 ? 100 : 0;
        }
        
        return round($this->properties_registered / $durationDays, 2);
    }

    public function getCompletionPercentageAttribute(): float
    {
        if (!$this->plan || $this->plan->estimated_houses <= 0) {
            return 0;
        }
        
        $totalAssignedAgents = $this->plan->active_agents_count ?: 1;
        $individualTarget = $this->plan->estimated_houses / $totalAssignedAgents;
        
        return $individualTarget > 0 ? 
            min(100, round(($this->properties_registered / $individualTarget) * 100, 1)) : 0;
    }

    public function getNeedsAttentionAttribute(): bool
    {
        // Needs attention if inactive but had recent activity
        if (!$this->is_active && $this->properties_registered > 0) {
            return $this->last_activity_at && $this->last_activity_at->gte(now()->subDays(7));
        }

        // Needs attention if active but no recent activity for 3 days
        if ($this->is_active && $this->last_activity_at) {
            return $this->last_activity_at->lt(now()->subDays(3));
        }

        // Needs attention if assigned but no activity at all for 5 days
        if ($this->is_active && !$this->last_activity_at) {
            return $this->assigned_at->lt(now()->subDays(5));
        }

        return false;
    }

    public function getAssignmentTypeLabelAttribute(): string
    {
        return match($this->assignment_type) {
            self::TYPE_PRIMARY => 'Primary Agent',
            self::TYPE_SECONDARY => 'Secondary Agent',
            self::TYPE_BACKUP => 'Backup Agent',
            default => 'Unknown',
        };
    }

    public function getPerformanceGradeAttribute(): string
    {
        if (!$this->performance_score) {
            return 'Not Rated';
        }

        return match(true) {
            $this->performance_score >= 90 => 'A+',
            $this->performance_score >= 80 => 'A',
            $this->performance_score >= 70 => 'B+',
            $this->performance_score >= 60 => 'B',
            $this->performance_score >= 50 => 'C',
            $this->performance_score >= 40 => 'D',
            default => 'F',
        };
    }

    /**
     * ✅ ADDED: Scopes for enhanced querying
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopePrimary($query)
    {
        return $query->where('assignment_type', self::TYPE_PRIMARY);
    }

    public function scopeSecondary($query)
    {
        return $query->where('assignment_type', self::TYPE_SECONDARY);
    }

    public function scopeBackup($query)
    {
        return $query->where('assignment_type', self::TYPE_BACKUP);
    }

    public function scopeWithRecentActivity($query, $days = 7)
    {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }

    public function scopeWithoutRecentActivity($query, $days = 3)
    {
        return $query->where(function($q) use ($days) {
            $q->whereNull('last_activity_at')
              ->orWhere('last_activity_at', '<', now()->subDays($days));
        });
    }

    public function scopeHighPerformers($query, $threshold = 80)
    {
        return $query->where('performance_score', '>=', $threshold)
                    ->orderBy('performance_score', 'desc');
    }

    public function scopeNeedsPerformanceReview($query)
    {
        return $query->where('performance_score', '<', 60)
                    ->where('is_active', true)
                    ->where('properties_registered', '>', 0);
    }

    /**
     * Business Logic Methods
     */
    public function isActive(): bool
    {
        return $this->is_active && is_null($this->removed_at);
    }

    public function markAsInactive(?string $reason = null): bool
    {
        return $this->update([
            'is_active' => false,
            'removed_at' => now(),
            'removal_reason' => $reason,
        ]);
    }

    public function reactivate(): bool
    {
        return $this->update([
            'is_active' => true,
            'removed_at' => null,
            'removal_reason' => null,
            'last_activity_at' => now(),
        ]);
    }

    public function updatePropertiesRegistered(int $count): bool
    {
        return $this->update([
            'properties_registered' => max(0, $count),
            'last_activity_at' => now(),
        ]);
    }

    public function incrementPropertiesRegistered(int $increment = 1): bool
    {
        return $this->update([
            'properties_registered' => $this->properties_registered + $increment,
            'last_activity_at' => now(),
        ]);
    }

    public function decrementPropertiesRegistered(int $decrement = 1): bool
    {
        $newCount = max(0, $this->properties_registered - $decrement);
        return $this->update([
            'properties_registered' => $newCount,
            'last_activity_at' => now(),
        ]);
    }

    public function resetPropertiesRegistered(): bool
    {
        return $this->update([
            'properties_registered' => 0,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * ✅ ADDED: New methods for enhanced functionality
     */
    public function calculatePerformanceScore(): void
    {
        $score = 0;

        // Base score from productivity (50 points max)
        $productivityScore = min(50, ($this->productivity_rate / 2));
        $score += $productivityScore;

        // Score from completion percentage (30 points max)
        $completionScore = min(30, ($this->completion_percentage * 0.3));
        $score += $completionScore;

        // Score from recent activity (20 points max)
        $activityScore = 0;
        if ($this->last_activity_at) {
            $daysSinceActivity = $this->last_activity_at->diffInDays(now());
            $activityScore = max(0, 20 - ($daysSinceActivity * 2));
        }
        $score += $activityScore;

        // Bonus for primary assignment (5 points)
        if ($this->assignment_type === self::TYPE_PRIMARY) {
            $score += 5;
        }

        $this->performance_score = min(100, max(0, round($score, 2)));
        $this->saveQuietly(); // Save without triggering events
    }

    public function recordInvitationSent(): bool
    {
        return $this->update([
            'last_invitation_sent_at' => now(),
        ]);
    }

    public function recordInvitationAccepted(): bool
    {
        if ($this->last_invitation_sent_at) {
            $acceptanceTime = $this->last_invitation_sent_at->diffInMinutes(now());
            return $this->update([
                'invitation_acceptance_time' => $acceptanceTime,
            ]);
        }

        return false;
    }

    public function getInvitationResponseTime(): ?int
    {
        return $this->invitation_acceptance_time;
    }

    public function getAssignmentDuration(): int
    {
        return $this->assignment_duration_days;
    }

    public function getAssignmentDurationHours(): int
    {
        return $this->assignment_duration_hours;
    }

    public function wasRecentlyActive(int $hours = 24): bool
    {
        if (!$this->last_activity_at) {
            return false;
        }
        
        return $this->last_activity_at->gt(now()->subHours($hours));
    }

    public function getPerformanceMetrics(): array
    {
        return [
            'properties_registered' => $this->properties_registered,
            'assignment_duration_days' => $this->assignment_duration_days,
            'assignment_duration_hours' => $this->assignment_duration_hours,
            'properties_per_day' => $this->productivity_rate,
            'completion_percentage' => $this->completion_percentage,
            'performance_score' => $this->performance_score,
            'performance_grade' => $this->performance_grade,
            'assignment_start' => $this->assigned_at->format('M j, Y'),
            'assignment_end' => $this->removed_at?->format('M j, Y') ?? 'Active',
            'is_active' => $this->is_active,
            'assignment_type' => $this->assignment_type_label,
            'last_activity' => $this->last_activity_at?->diffForHumans() ?? 'Never',
            'needs_attention' => $this->needs_attention,
            'invitation_response_time' => $this->invitation_acceptance_time ? 
                round($this->invitation_acceptance_time / 60, 1) . ' hours' : 'N/A',
        ];
    }

    public function getAssignmentSummary(): array
    {
        return [
            'assignment_id' => $this->id,
            'plan_id' => $this->plan_id,
            'agent_id' => $this->agent_id,
            'agent_name' => $this->agent->name ?? 'Unknown Agent',
            'agent_phone' => $this->agent->phone ?? 'N/A',
            'plan_zone' => $this->plan->zone ?? 'Unknown Zone',
            'plan_section' => $this->plan->section ?? 'No Section',
            'assigned_by' => $this->assigner->name ?? 'Unknown',
            'assigned_at' => $this->assigned_at->format('Y-m-d H:i:s'),
            'status' => $this->is_active ? 'Active' : 'Inactive',
            'assignment_type' => $this->assignment_type_label,
            'properties_registered' => $this->properties_registered,
            'performance_score' => $this->performance_score,
            'performance_grade' => $this->performance_grade,
            'last_activity' => $this->last_activity_at?->format('Y-m-d H:i:s') ?? 'Never',
            'completion_percentage' => $this->completion_percentage,
            'productivity_rate' => $this->productivity_rate,
            'performance_metrics' => $this->getPerformanceMetrics(),
        ];
    }

    public function canBeDeleted(): bool
    {
        // Prevent deletion if properties are registered
        return $this->properties_registered === 0;
    }

    public function canBeReactivated(): bool
    {
        return !$this->is_active && $this->plan && 
               in_array($this->plan->status, ['draft', 'assigned', 'in_progress']);
    }

    public function shouldReceiveInvitation(): bool
    {
        return $this->is_active && 
               $this->plan && 
               in_array($this->plan->status, ['assigned', 'in_progress']) &&
               (!$this->last_invitation_sent_at || 
                $this->last_invitation_sent_at->lt(now()->subDays(1)));
    }

    /**
     * Static Methods
     */
    public static function needsAttention(): array
    {
        return self::with(['plan', 'agent'])
            ->where(function($query) {
                // Inactive but had recent activity
                $query->where(function($q) {
                    $q->where('is_active', false)
                      ->where('properties_registered', '>', 0)
                      ->where('last_activity_at', '>=', now()->subDays(7));
                })->orWhere(function($q) {
                    // Active but no recent activity for 3 days
                    $q->where('is_active', true)
                      ->where('last_activity_at', '<', now()->subDays(3))
                      ->orWhereNull('last_activity_at');
                })->orWhere(function($q) {
                    // Low performance score
                    $q->where('is_active', true)
                      ->where('performance_score', '<', 60)
                      ->where('properties_registered', '>', 0);
                });
            })
            ->get()
            ->all();
    }

    public static function getPerformanceStatistics(): array
    {
        $totalAssignments = self::count();
        $activeAssignments = self::active()->count();
        $highPerformers = self::highPerformers()->count();
        
        return [
            'total_assignments' => $totalAssignments,
            'active_assignments' => $activeAssignments,
            'inactive_assignments' => $totalAssignments - $activeAssignments,
            'high_performers' => $highPerformers,
            'average_performance_score' => round(self::avg('performance_score') ?? 0, 2),
            'total_properties_registered' => self::sum('properties_registered'),
            'assignments_needing_attention' => count(self::needsAttention()),
            'performance_breakdown' => [
                'excellent' => self::where('performance_score', '>=', 90)->count(),
                'good' => self::whereBetween('performance_score', [75, 89])->count(),
                'average' => self::whereBetween('performance_score', [60, 74])->count(),
                'poor' => self::where('performance_score', '<', 60)->count(),
            ],
        ];
    }
}