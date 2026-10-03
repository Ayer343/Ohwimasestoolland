<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrationPlan extends Model
{
    use HasFactory, SoftDeletes;

    // Plan status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Sequence type constants
    const SEQUENTIAL = 'sequential';
    const EVEN_ONLY = 'even_only';
    const ODD_ONLY = 'odd_only';

    // Agent assignment type constants
    const ASSIGNMENT_SINGLE = 'single';
    const ASSIGNMENT_MULTIPLE = 'multiple';

    // Invitation status constants
    const INVITATION_PENDING = 'pending';
    const INVITATION_SENT = 'sent';
    const INVITATION_FAILED = 'failed';
    const INVITATION_NOT_REQUIRED = 'not_required';

    // Invitation channel constants
    const CHANNEL_SMS = 'sms';
    const CHANNEL_WHATSAPP = 'whatsapp';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_ALL = 'all_channels';

    /**
     * Runtime flag the controller can set to indicate it has already
     * resolved the correct status and the model should NOT auto-adjust it.
     *
     * Usage in controller:
     *   $plan->skipStatusAutoCorrection = true;
     *   $plan->save();
     */
    public bool $skipStatusAutoCorrection = false;

    protected $fillable = [
        'created_by',
        'zone',
        'section',
        'naming_pattern',
        'starting_point',
        'next_available_name',
        'estimated_houses',
        'sequence_type',
        'agent_assignment_type',
        'houses_registered',
        'properties_count',
        'status',
        'registration_start_date',
        'registration_end_date',
        'started_at',
        'completed_at',
        'cancelled_at',
        'instructions',
        'boundaries_description',
        'completion_notes',
        'continues_from_plan_id',
        'is_global_sequence',
        'deleted_by',
        // Multi-channel invitation fields
        'invitation_status',
        'invitation_sent_at',
        'invitation_provider',
        'invitation_channels',
        'sms_attempts',
        'whatsapp_attempts',
        'email_attempts',
        'last_sms_attempt_at',
        'last_whatsapp_attempt_at',
        'last_email_attempt_at',
        'preferred_channel',
        'invitation_expires_at',
        // Global sequence tracking
        'last_used_pattern',
        'sequence_continuation_count',
        'total_sequence_properties',
    ];

    protected $casts = [
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'estimated_houses' => 'integer',
        'houses_registered' => 'integer',
        'properties_count' => 'integer',
        'is_global_sequence' => 'boolean',
        'sms_attempts' => 'integer',
        'whatsapp_attempts' => 'integer',
        'email_attempts' => 'integer',
        'invitation_sent_at' => 'datetime',
        'last_sms_attempt_at' => 'datetime',
        'last_whatsapp_attempt_at' => 'datetime',
        'last_email_attempt_at' => 'datetime',
        'invitation_expires_at' => 'datetime',
        'invitation_channels' => 'array',
        'sequence_continuation_count' => 'integer',
        'total_sequence_properties' => 'integer',
    ];

    protected $appends = [
        'is_overdue',
        'progress_percentage',
        'remaining_houses',
        'is_active',
        'can_be_edited',
        'can_be_deleted',
        'area_description',
        'days_remaining',
        'duration',
        'has_active_invitation',
        'can_send_invitation',
        'invitation_status_info',
        'assigned_agents_count',
        'active_agents_count',
        'has_assigned_agents',
        'primary_agent',
        'registered_properties_count',
        'multiple_agents_summary',
        'assignment_type_label',
        'sequence_type_label',
        'status_label',
        'can_assign_more_agents',
        'max_agents_reached',
        'available_channels',
        'channel_status',
        'has_pending_invitations',
        'invitation_statistics',
        'multi_channel_summary',
        'sequence_continuation_info',
        'global_sequence_chain',
        'can_continue_sequence',
        'next_global_sequence_start',
        'global_sequence_conflicts',
        'pattern_usage_across_plans',
        'sequence_continuation_warnings',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->status)) {
                $model->status = self::STATUS_DRAFT;
            }

            if (empty($model->next_available_name)) {
                $model->next_available_name = $model->starting_point;
            }

            if (empty($model->houses_registered)) {
                $model->houses_registered = 0;
            }

            if (empty($model->properties_count)) {
                $model->properties_count = 0;
            }

            if (empty($model->agent_assignment_type)) {
                $model->agent_assignment_type = self::ASSIGNMENT_SINGLE;
            }

            if (empty($model->invitation_status)) {
                $model->invitation_status = self::INVITATION_NOT_REQUIRED;
            }

            if (empty($model->sms_attempts)) {
                $model->sms_attempts = 0;
            }

            if (empty($model->whatsapp_attempts)) {
                $model->whatsapp_attempts = 0;
            }

            if (empty($model->email_attempts)) {
                $model->email_attempts = 0;
            }

            if (empty($model->invitation_channels)) {
                $model->invitation_channels = [self::CHANNEL_SMS];
            }

            if (empty($model->preferred_channel)) {
                $model->preferred_channel = self::CHANNEL_SMS;
            }

            if ($model->is_global_sequence) {
                if (empty($model->sequence_continuation_count)) {
                    $model->sequence_continuation_count = 0;
                }
                if (empty($model->total_sequence_properties)) {
                    $model->total_sequence_properties = 0;
                }
                $model->last_used_pattern = $model->starting_point;
            }
        });

        static::saving(function ($model) {
            // Validate global sequence pattern uniqueness
            if ($model->is_global_sequence) {
                $existingNonGlobal = self::where('naming_pattern', $model->naming_pattern)
                    ->where('is_global_sequence', false)
                    ->when($model->id, function ($query) use ($model) {
                        $query->where('id', '!=', $model->id);
                    })
                    ->exists();

                if ($existingNonGlobal) {
                    throw new \Exception(
                        "Cannot set pattern '{$model->naming_pattern}' as global sequence. " .
                        "It is already used in non-global plans."
                    );
                }
            }
        });

        static::updating(function ($model) {
            // ============================================================
            // TIMESTAMPS + STATUS TRANSITION SIDE EFFECTS
            // ============================================================
            if ($model->isDirty('status')) {
                if ($model->status === self::STATUS_COMPLETED) {
                    if (!$model->completed_at) {
                        $model->completed_at = now();
                    }
                } elseif ($model->status === self::STATUS_CANCELLED) {
                    if (!$model->cancelled_at) {
                        $model->cancelled_at = now();
                    }
                } elseif ($model->status === self::STATUS_IN_PROGRESS && !$model->started_at) {
                    $model->started_at = now();
                } elseif ($model->status !== self::STATUS_COMPLETED) {
                    $model->completed_at = null;
                }

                if (!in_array($model->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
                    $model->cancelled_at = null;
                }
            }

            // ============================================================
            // ✅ FIXED: AUTO-STATUS SYNC — SAFE GUARDS
            // ============================================================
            // Only auto-adjust status if the controller did NOT explicitly
            // tell us it already resolved the status correctly.
            if (empty($model->skipStatusAutoCorrection)) {

                $hasActiveAgents = $model->assignedAgents()
                    ->where('is_active', true)
                    ->exists();

                if ($hasActiveAgents && $model->status === self::STATUS_DRAFT) {
                    // Draft + agents -> Assigned
                    $model->status = self::STATUS_ASSIGNED;
                } elseif (
                    !$hasActiveAgents
                    && in_array($model->status, [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS], true)
                    && $model->isDirty('status')          // ← only fire if admin is EXPLICITLY changing status
                ) {
                    // No agents + admin tried to set assigned/in_progress -> Draft
                    //
                    // Only null the dates/instructions here if the admin did
                    // NOT submit them. Otherwise the admin loses data.
                    if (!$model->isDirty('registration_start_date')) {
                        $model->registration_start_date = null;
                    }
                    if (!$model->isDirty('registration_end_date')) {
                        $model->registration_end_date = null;
                    }
                    if (!$model->isDirty('instructions')) {
                        $model->instructions = null;
                    }

                    $model->status = self::STATUS_DRAFT;
                }
            }

            // ============================================================
            // NAMING PATTERN RESET
            // ============================================================
            if ($model->isDirty(['starting_point', 'naming_pattern'])) {
                $model->next_available_name = $model->starting_point;

                if ($model->is_global_sequence) {
                    $model->last_used_pattern = $model->starting_point;
                }
            }

            // ============================================================
            // ASSIGNMENT TYPE CONSTRAINT
            // ============================================================
            if ($model->isDirty('agent_assignment_type')) {
                $activeAgentsCount = $model->assignedAgents()->where('is_active', true)->count();
                if ($model->agent_assignment_type === self::ASSIGNMENT_SINGLE && $activeAgentsCount > 1) {
                    throw new \Exception('Cannot switch to single assignment type when multiple agents are assigned.');
                }
            }

            // ============================================================
            // GLOBAL SEQUENCE CONFLICT CHECK
            // ============================================================
            if ($model->isDirty('is_global_sequence') && $model->is_global_sequence) {
                $conflicts = $model->checkGlobalSequenceConflicts();
                if ($conflicts['has_conflict']) {
                    throw new \Exception($conflicts['message']);
                }
            }
        });

        // Handle side effects AFTER the update has been persisted.
        static::updated(function ($model) {
            if ($model->is_global_sequence && $model->properties()->count() > 0) {
                $model->updateNextAvailableNameQuietly();
            }
        });

        static::deleting(function ($model) {
            if ($model->isForceDeleting()) {
                $model->properties()->delete();
                $model->invitations()->delete();
                $model->assignedAgents()->delete();
            } else {
                if (auth()->check()) {
                    $model->deleted_by = auth()->id();
                    $model->saveQuietly();
                }
            }
        });

        static::restoring(function ($model) {
            $model->deleted_by = null;
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function plan()
    {
        // Compatibility shim — some code expects $plan->plan().
        return $this->belongsTo(RegistrationPlan::class, 'id');
    }

    public function planAssignments()
    {
        return $this->hasMany(PlanAgentAssignment::class, 'plan_id');
    }

    public function assignedAgents()
    {
        return $this->hasMany(PlanAgentAssignment::class, 'plan_id');
    }

    public function activeAgents()
    {
        return $this->hasMany(PlanAgentAssignment::class, 'plan_id')->where('is_active', true);
    }

    public function agents()
    {
        return $this->belongsToMany(User::class, 'plan_agent_assignments', 'plan_id', 'agent_id')
            ->withPivot('assigned_by', 'assigned_at', 'is_active', 'removed_at', 'properties_registered', 'last_activity_at')
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    public function continuedFromPlan()
    {
        return $this->belongsTo(RegistrationPlan::class, 'continues_from_plan_id');
    }

    public function continuedByPlans()
    {
        return $this->hasMany(RegistrationPlan::class, 'continues_from_plan_id');
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'registration_plan_id');
    }

    public function registeredProperties()
    {
        return $this->hasMany(Property::class, 'registration_plan_id')->whereNotNull('registered_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function invitations()
    {
        return $this->hasMany(AgentInvitation::class, 'plan_id');
    }

    public function latestInvitation()
    {
        return $this->hasOne(AgentInvitation::class, 'plan_id')->latest();
    }

    public function acceptedInvitation()
    {
        return $this->hasOne(AgentInvitation::class, 'plan_id')
            ->where('status', AgentInvitation::STATUS_ACCEPTED);
    }

    public function pendingInvitations()
    {
        return $this->hasMany(AgentInvitation::class, 'plan_id')
            ->where('status', AgentInvitation::STATUS_SENT)
            ->where('expires_at', '>', now());
    }

    public function smsInvitations()
    {
        return $this->hasMany(AgentInvitation::class, 'plan_id')
            ->where('sent_via', self::CHANNEL_SMS);
    }

    public function whatsappInvitations()
    {
        return $this->hasMany(AgentInvitation::class, 'plan_id')
            ->where('sent_via', self::CHANNEL_WHATSAPP);
    }

    public function emailInvitations()
    {
        return $this->hasMany(AgentInvitation::class, 'plan_id')
            ->where('sent_via', self::CHANNEL_EMAIL);
    }

    public function registeredPropertiesByAgent($agentId)
    {
        return $this->hasMany(Property::class, 'registration_plan_id')
            ->where('registered_by', $agentId);
    }

    // ==================== ACCESSORS ====================

    public function getIsOverdueAttribute(): bool
    {
        return $this->registration_end_date &&
            $this->registration_end_date->isPast() &&
            !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->estimated_houses <= 0) {
            return 0;
        }
        $registeredCount = $this->properties()->count();
        return min(100, round(($registeredCount / $this->estimated_houses) * 100, 2));
    }

    public function getRemainingHousesAttribute(): int
    {
        return max(0, $this->estimated_houses - $this->properties()->count());
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS]);
    }

    public function getCanBeEditedAttribute(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]) &&
            !$this->trashed();
    }

    public function getCanBeDeletedAttribute(): bool
    {
        return !in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS]) &&
            $this->properties()->count() === 0 &&
            !$this->trashed();
    }

    public function getAreaDescriptionAttribute(): string
    {
        $description = $this->zone;
        if ($this->section) {
            $description .= ", Section {$this->section}";
        }
        return $description;
    }

    public function getRegisteredPropertiesCountAttribute(): int
    {
        return $this->properties()->count();
    }

    public function getHousesRegisteredAttribute($value)
    {
        return $this->properties()->count();
    }

    public function getPropertiesCountAttribute($value)
    {
        return $this->properties()->count();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->registration_end_date) {
            return null;
        }
        return max(0, now()->diffInDays($this->registration_end_date, false));
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->registration_start_date || !$this->registration_end_date) {
            return null;
        }
        return $this->registration_start_date->diffInDays($this->registration_end_date);
    }

    public function getHasActiveInvitationAttribute(): bool
    {
        return $this->pendingInvitations()->exists();
    }

    public function getCanSendInvitationAttribute(): bool
    {
        if (!$this->has_assigned_agents) {
            return false;
        }
        if ($this->has_active_invitation) {
            return false;
        }
        return in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS]);
    }

    // ==================== GLOBAL SEQUENCE ACCESSORS ====================

    public function getGlobalSequenceChainAttribute(): array
    {
        if (!$this->is_global_sequence) {
            return [];
        }

        $chain = [];
        $currentPlan = $this;

        while ($currentPlan->continuedFromPlan) {
            $chain[] = [
                'id' => $currentPlan->continuedFromPlan->id,
                'zone' => $currentPlan->continuedFromPlan->zone,
                'section' => $currentPlan->continuedFromPlan->section,
                'starting_point' => $currentPlan->continuedFromPlan->starting_point,
                'next_available_name' => $currentPlan->continuedFromPlan->next_available_name,
                'status' => $currentPlan->continuedFromPlan->status,
            ];
            $currentPlan = $currentPlan->continuedFromPlan;
        }

        return array_reverse($chain);
    }

    public function getCanContinueSequenceAttribute(): bool
    {
        return $this->is_global_sequence &&
            $this->status === self::STATUS_COMPLETED &&
            !empty($this->next_available_name);
    }

    public function getNextGlobalSequenceStartAttribute(): ?string
    {
        return $this->can_continue_sequence ? $this->next_available_name : null;
    }

    public function getGlobalSequenceConflictsAttribute(): array
    {
        return $this->checkGlobalSequenceConflicts();
    }

    public function getPatternUsageAcrossPlansAttribute(): array
    {
        $usage = self::where('naming_pattern', $this->naming_pattern)
            ->select('zone', 'section', 'starting_point', 'next_available_name', 'status')
            ->get()
            ->toArray();

        return [
            'total_plans' => count($usage),
            'plans' => $usage,
            'is_global_sequence_exclusive' => !self::where('naming_pattern', $this->naming_pattern)
                ->where('is_global_sequence', false)
                ->exists(),
        ];
    }

    public function getSequenceContinuationWarningsAttribute(): array
    {
        $warnings = [];

        if ($this->is_global_sequence) {
            $nonGlobalUsage = self::where('naming_pattern', $this->naming_pattern)
                ->where('is_global_sequence', false)
                ->exists();

            if ($nonGlobalUsage) {
                $warnings[] = "Pattern '{$this->naming_pattern}' is also used in non-global plans, which may cause conflicts.";
            }

            if ($this->continuedFromPlan) {
                $expectedStart = $this->continuedFromPlan->next_available_name;
                if ($this->starting_point !== $expectedStart) {
                    $warnings[] = "Sequence continuation gap detected. Expected: {$expectedStart}, Actual: {$this->starting_point}";
                }
            }
        }

        return $warnings;
    }

    public function getSequenceContinuationInfoAttribute(): array
    {
        return [
            'is_global_sequence' => $this->is_global_sequence,
            'naming_pattern' => $this->naming_pattern,
            'next_available_name' => $this->next_available_name,
            'continues_from_plan_id' => $this->continues_from_plan_id,
            'continued_from_plan' => $this->continuedFromPlan ? [
                'id' => $this->continuedFromPlan->id,
                'zone' => $this->continuedFromPlan->zone,
                'section' => $this->continuedFromPlan->section,
                'naming_pattern' => $this->continuedFromPlan->naming_pattern,
            ] : null,
            'total_properties_in_sequence' => $this->is_global_sequence ?
                Property::whereHas('registrationPlan', function ($query) {
                    $query->where('is_global_sequence', true)
                        ->where('naming_pattern', $this->naming_pattern);
                })->count() : 0,
        ];
    }

    public function getInvitationStatusInfoAttribute(): array
    {
        $latestInvitation = $this->latestInvitation;

        return [
            'has_agents' => $this->has_assigned_agents,
            'has_active_invitation' => $this->has_active_invitation,
            'has_accepted_invitation' => $this->acceptedInvitation()->exists(),
            'latest_invitation' => $latestInvitation ? [
                'id' => $latestInvitation->id,
                'status' => $latestInvitation->status,
                'sent_via' => $latestInvitation->sent_via,
                'sent_at' => $latestInvitation->created_at->format('M j, Y g:i A'),
                'expires_at' => $latestInvitation->expires_at->format('M j, Y g:i A'),
                'accepted_at' => $latestInvitation->accepted_at?->format('M j, Y g:i A'),
                'can_resend' => $latestInvitation->status === AgentInvitation::STATUS_SENT && $latestInvitation->expires_at > now(),
                'is_expired' => $latestInvitation->expires_at <= now(),
                'days_until_expiry' => $latestInvitation->expires_at->diffInDays(now()),
            ] : null,
            'total_invitations' => $this->invitations()->count(),
            'accepted_invitations' => $this->invitations()->where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
            'pending_invitations' => $this->pendingInvitations()->count(),
            'database_invitation_status' => $this->invitation_status,
            'invitation_sent_at' => $this->invitation_sent_at?->format('M j, Y g:i A'),
            'invitation_provider' => $this->invitation_provider,
            'invitation_channels' => $this->invitation_channels ?? [],
            'preferred_channel' => $this->preferred_channel,
            'sms_attempts' => $this->sms_attempts,
            'whatsapp_attempts' => $this->whatsapp_attempts,
            'email_attempts' => $this->email_attempts,
            'last_sms_attempt_at' => $this->last_sms_attempt_at?->format('M j, Y g:i A'),
            'last_whatsapp_attempt_at' => $this->last_whatsapp_attempt_at?->format('M j, Y g:i A'),
            'last_email_attempt_at' => $this->last_email_attempt_at?->format('M j, Y g:i A'),
            'invitation_expires_at' => $this->invitation_expires_at?->format('M j, Y g:i A'),
            'channel_status' => $this->getChannelStatusAttribute(),
        ];
    }

    public function getAvailableChannelsAttribute(): array
    {
        return [
            self::CHANNEL_SMS => [
                'name' => 'SMS',
                'enabled' => true,
                'attempts' => $this->sms_attempts,
                'last_attempt' => $this->last_sms_attempt_at,
                'can_retry' => $this->sms_attempts < 3,
            ],
            self::CHANNEL_WHATSAPP => [
                'name' => 'WhatsApp',
                'enabled' => true,
                'attempts' => $this->whatsapp_attempts,
                'last_attempt' => $this->last_whatsapp_attempt_at,
                'can_retry' => $this->whatsapp_attempts < 3,
            ],
            self::CHANNEL_EMAIL => [
                'name' => 'Email',
                'enabled' => true,
                'attempts' => $this->email_attempts,
                'last_attempt' => $this->last_email_attempt_at,
                'can_retry' => $this->email_attempts < 3,
            ],
        ];
    }

    public function getChannelStatusAttribute(): array
    {
        return [
            'sms' => [
                'enabled' => true,
                'attempts' => $this->sms_attempts,
                'last_attempt' => $this->last_sms_attempt_at?->diffForHumans(),
                'status' => $this->sms_attempts > 0 ? 'attempted' : 'ready',
                'can_send' => $this->sms_attempts < 3,
            ],
            'whatsapp' => [
                'enabled' => true,
                'attempts' => $this->whatsapp_attempts,
                'last_attempt' => $this->last_whatsapp_attempt_at?->diffForHumans(),
                'status' => $this->whatsapp_attempts > 0 ? 'attempted' : 'ready',
                'can_send' => $this->whatsapp_attempts < 3,
            ],
            'email' => [
                'enabled' => true,
                'attempts' => $this->email_attempts,
                'last_attempt' => $this->last_email_attempt_at?->diffForHumans(),
                'status' => $this->email_attempts > 0 ? 'attempted' : 'ready',
                'can_send' => $this->email_attempts < 3,
            ],
        ];
    }

    public function getHasPendingInvitationsAttribute(): bool
    {
        return $this->pendingInvitations()->exists() ||
            $this->invitation_status === self::INVITATION_PENDING;
    }

    public function getInvitationStatisticsAttribute(): array
    {
        return $this->getInvitationStatistics();
    }

    public function getMultiChannelSummaryAttribute(): array
    {
        return [
            'total_invitations' => $this->invitations()->count(),
            'channels_used' => $this->invitations()->distinct()->pluck('sent_via')->toArray(),
            'success_by_channel' => [
                'sms' => $this->smsInvitations()->where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
                'whatsapp' => $this->whatsappInvitations()->where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
                'email' => $this->emailInvitations()->where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
            ],
            'attempts_by_channel' => [
                'sms' => $this->sms_attempts,
                'whatsapp' => $this->whatsapp_attempts,
                'email' => $this->email_attempts,
            ],
            'preferred_channel' => $this->preferred_channel,
            'available_channels' => $this->available_channels,
        ];
    }

    public function getAssignedAgentsCountAttribute(): int
    {
        return $this->assignedAgents()->where('is_active', true)->count();
    }

    public function getActiveAgentsCountAttribute(): int
    {
        return $this->activeAgents()->count();
    }

    public function getHasAssignedAgentsAttribute(): bool
    {
        return $this->assignedAgents()->where('is_active', true)->exists();
    }

    public function getPrimaryAgentAttribute(): ?User
    {
        return $this->activeAgents()->first()?->agent;
    }

    public function getMultipleAgentsSummaryAttribute(): array
    {
        $agents = $this->activeAgents()->with('agent')->get();

        return [
            'total_agents' => $agents->count(),
            'agents' => $agents->map(function ($assignment) {
                return [
                    'id' => $assignment->agent->id,
                    'name' => $assignment->agent->name,
                    'phone' => $assignment->agent->phone,
                    'email' => $assignment->agent->email,
                    'assignment_id' => $assignment->id,
                    'properties_registered' => $assignment->properties_registered,
                    'assigned_at' => $assignment->assigned_at->format('M j, Y'),
                    'last_activity' => $assignment->last_activity_at?->format('M j, Y g:i A'),
                    'is_active' => $assignment->is_active,
                ];
            })->toArray(),
            'total_properties_registered' => $agents->sum('properties_registered'),
            'average_properties_per_agent' => $agents->count() > 0 ? round($agents->sum('properties_registered') / $agents->count(), 2) : 0,
        ];
    }

    public function getAssignmentTypeLabelAttribute(): string
    {
        return match ($this->agent_assignment_type) {
            self::ASSIGNMENT_SINGLE => 'Single Agent',
            self::ASSIGNMENT_MULTIPLE => 'Multiple Agents',
            default => 'Unknown',
        };
    }

    public function getSequenceTypeLabelAttribute(): string
    {
        return match ($this->sequence_type) {
            self::SEQUENTIAL => 'Sequential (1,2,3...)',
            self::EVEN_ONLY => 'Even Numbers Only (2,4,6...)',
            self::ODD_ONLY => 'Odd Numbers Only (1,3,5...)',
            default => 'Unknown',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ASSIGNED => 'Assigned',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown',
        };
    }

    public function getCanAssignMoreAgentsAttribute(): bool
    {
        if ($this->agent_assignment_type === self::ASSIGNMENT_SINGLE) {
            return $this->active_agents_count === 0;
        }
        return $this->active_agents_count < 10;
    }

    public function getMaxAgentsReachedAttribute(): bool
    {
        if ($this->agent_assignment_type === self::ASSIGNMENT_SINGLE) {
            return $this->active_agents_count >= 1;
        }
        return $this->active_agents_count >= 10;
    }

    public function getAgentPerformanceAttribute(): array
    {
        $performance = [];

        foreach ($this->activeAgents as $assignment) {
            $performance[] = [
                'agent' => $assignment->agent,
                'assignment' => $assignment,
                'properties_registered' => $assignment->properties_registered,
                'last_activity' => $assignment->last_activity_at,
                'assignment_duration' => $assignment->assigned_at->diffInDays(now()),
                'productivity_rate' => $assignment->assigned_at->diffInDays(now()) > 0
                    ? round($assignment->properties_registered / $assignment->assigned_at->diffInDays(now()), 2)
                    : 0,
            ];
        }

        return $performance;
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('registration_end_date', '<', now())
            ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeWithAssignedAgents($query)
    {
        return $query->whereHas('assignedAgents', function ($q) {
            $q->where('is_active', true);
        });
    }

    public function scopeUnassigned($query)
    {
        return $query->whereDoesntHave('assignedAgents', function ($q) {
            $q->where('is_active', true);
        });
    }

    public function scopeSingleAssignment($query)
    {
        return $query->where('agent_assignment_type', self::ASSIGNMENT_SINGLE);
    }

    public function scopeMultipleAssignment($query)
    {
        return $query->where('agent_assignment_type', self::ASSIGNMENT_MULTIPLE);
    }

    public function scopeWithAgent($query, $agentId)
    {
        return $query->whereHas('assignedAgents', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId)->where('is_active', true);
        });
    }

    public function scopeGlobalSequence($query)
    {
        return $query->where('is_global_sequence', true);
    }

    public function scopeWithPendingInvitations($query)
    {
        return $query->whereHas('invitations', function ($q) {
            $q->where('status', AgentInvitation::STATUS_SENT)
                ->where('expires_at', '>', now());
        });
    }

    public function scopeWithAcceptedInvitations($query)
    {
        return $query->whereHas('invitations', function ($q) {
            $q->where('status', AgentInvitation::STATUS_ACCEPTED);
        });
    }

    public function scopeAccessibleByFieldAgent($query, $agentId)
    {
        return $query->whereHas('assignedAgents', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId)->where('is_active', true);
        });
    }

    public function scopeWithChannel($query, $channel)
    {
        return $query->whereJsonContains('invitation_channels', $channel);
    }

    public function scopeWithPreferredChannel($query, $channel)
    {
        return $query->where('preferred_channel', $channel);
    }

    public function scopeNeedsChannelRetry($query, $channel)
    {
        $attemptField = $channel . '_attempts';
        $lastAttemptField = 'last_' . $channel . '_attempt_at';

        return $query->where(function ($q) use ($attemptField, $lastAttemptField) {
            $q->where($attemptField, '<', 3)
                ->where(function ($q2) use ($lastAttemptField) {
                    $q2->whereNull($lastAttemptField)
                        ->orWhere($lastAttemptField, '<', now()->subHours(1));
                });
        });
    }

    // ==================== BUSINESS LOGIC ====================

    /**
     * Flip the plan to 'in_progress' the first time an active field agent
     * registers a property under it.
     *
     * Safe to call repeatedly — it only mutates when the transition is
     * meaningful (assigned → in_progress).
     *
     * @param  int|null  $agentId  The agent who triggered the transition.
     *                             If null, we don't attribute the change.
     * @return bool  True if the plan was actually transitioned.
     */
    public function markInProgressIfAssigned(?int $agentId = null): bool
    {
        // Only transition from 'assigned' → 'in_progress'.
        if ($this->status !== self::STATUS_ASSIGNED) {
            return false;
        }

        // Guard: the agent must still be actively assigned to this plan.
        if ($agentId !== null) {
            $stillAssigned = $this->assignedAgents()
                ->where('agent_id', $agentId)
                ->where('is_active', true)
                ->exists();

            if (!$stillAssigned) {
                return false;
            }
        }

        // Use updateQuietly to avoid re-firing the model's auto-status hook
        // and any notifications tied to `updated`.
        $this->updateQuietly([
            'status'     => self::STATUS_IN_PROGRESS,
            'started_at' => $this->started_at ?? now(),
        ]);

        // Sync the in-memory instance so callers see the new values.
        $this->setAttribute('status', self::STATUS_IN_PROGRESS);
        if (empty($this->getOriginal('started_at')) && empty($this->started_at)) {
            $this->setAttribute('started_at', now());
        }
        $this->syncOriginal();

        Log::info('Registration plan auto-advanced to in_progress', [
            'plan_id'  => $this->id,
            'zone'     => $this->zone,
            'section'  => $this->section,
            'agent_id' => $agentId,
            'occurred' => 'first property registered by field agent',
        ]);

        return true;
    }

    public function canBeMarkedAsCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS]) &&
            $this->properties()->count() >= $this->estimated_houses;
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function canBeReactivated(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeAccessedByFieldAgent($agentId): bool
    {
        return $this->assignedAgents()
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->exists();
    }

    public function getPropertiesRegisteredByAgent($agentId)
    {
        return $this->properties()->where('registered_by', $agentId)->get();
    }

    public function countPropertiesRegisteredByAgent($agentId): int
    {
        return $this->properties()->where('registered_by', $agentId)->count();
    }

    public function checkGlobalSequenceConflicts(): array
    {
        if (!$this->is_global_sequence) {
            return ['has_conflict' => false];
        }

        $conflictingPlans = self::where('naming_pattern', $this->naming_pattern)
            ->where('is_global_sequence', false)
            ->when($this->id, function ($query) {
                $query->where('id', '!=', $this->id);
            })
            ->exists();

        if ($conflictingPlans) {
            return [
                'has_conflict' => true,
                'message' => "Cannot use global sequence with pattern '{$this->naming_pattern}'. " .
                    "Non-global plans already use this pattern.",
            ];
        }

        return ['has_conflict' => false];
    }

    public function updateGlobalSequenceStats(): bool
    {
        if (!$this->is_global_sequence) {
            return false;
        }

        $totalProperties = Property::whereHas('registrationPlan', function ($query) {
            $query->where('is_global_sequence', true)
                ->where('naming_pattern', $this->naming_pattern);
        })->count();

        return $this->update([
            'total_sequence_properties' => $totalProperties,
            'last_used_pattern' => $this->next_available_name,
        ]);
    }

    public function updateNextAvailableName(): bool
    {
        if (!$this->is_global_sequence) {
            return false;
        }

        $lastProperty = $this->properties()->latest()->first();
        if ($lastProperty && $lastProperty->registration_pattern) {
            $nextName = $this->generateNextName(
                $lastProperty->registration_pattern,
                $this->naming_pattern,
                $this->sequence_type
            );

            return $this->update(['next_available_name' => $nextName]);
        }

        return false;
    }

    /**
     * Same as updateNextAvailableName but bypasses model events —
     * safe to call from the `updated` event without recursion.
     */
    protected function updateNextAvailableNameQuietly(): void
    {
        if (!$this->is_global_sequence) {
            return;
        }

        $lastProperty = $this->properties()->latest()->first();
        if ($lastProperty && $lastProperty->registration_pattern) {
            $nextName = $this->generateNextName(
                $lastProperty->registration_pattern,
                $this->naming_pattern,
                $this->sequence_type
            );

            $this->updateQuietly(['next_available_name' => $nextName]);
        }
    }

    // ==================== INVITATION METHODS ====================

    public function markInvitationAsSent($channels = [], $provider = null): bool
    {
        return $this->update([
            'invitation_status' => self::INVITATION_SENT,
            'invitation_sent_at' => now(),
            'invitation_provider' => $provider,
            'invitation_channels' => $channels,
            'invitation_expires_at' => now()->addDays(7),
            'sms_attempts' => in_array(self::CHANNEL_SMS, $channels) ? $this->sms_attempts + 1 : $this->sms_attempts,
            'whatsapp_attempts' => in_array(self::CHANNEL_WHATSAPP, $channels) ? $this->whatsapp_attempts + 1 : $this->whatsapp_attempts,
            'email_attempts' => in_array(self::CHANNEL_EMAIL, $channels) ? $this->email_attempts + 1 : $this->email_attempts,
            'last_sms_attempt_at' => in_array(self::CHANNEL_SMS, $channels) ? now() : $this->last_sms_attempt_at,
            'last_whatsapp_attempt_at' => in_array(self::CHANNEL_WHATSAPP, $channels) ? now() : $this->last_whatsapp_attempt_at,
            'last_email_attempt_at' => in_array(self::CHANNEL_EMAIL, $channels) ? now() : $this->last_email_attempt_at,
        ]);
    }

    public function markChannelAttempt($channel, $success = true): bool
    {
        $updateData = [];

        switch ($channel) {
            case self::CHANNEL_SMS:
                $updateData['sms_attempts'] = $this->sms_attempts + 1;
                $updateData['last_sms_attempt_at'] = now();
                break;
            case self::CHANNEL_WHATSAPP:
                $updateData['whatsapp_attempts'] = $this->whatsapp_attempts + 1;
                $updateData['last_whatsapp_attempt_at'] = now();
                break;
            case self::CHANNEL_EMAIL:
                $updateData['email_attempts'] = $this->email_attempts + 1;
                $updateData['last_email_attempt_at'] = now();
                break;
        }

        if (!$success) {
            $updateData['invitation_status'] = self::INVITATION_FAILED;
        }

        return $this->update($updateData);
    }

    public function canSendChannelInvitation($channel): bool
    {
        $attemptField = $channel . '_attempts';
        $maxAttempts = 3;

        return $this->has_assigned_agents &&
            $this->$attemptField < $maxAttempts;
    }

    public function getRecommendedChannels(): array
    {
        $channels = [];

        if ($this->canSendChannelInvitation(self::CHANNEL_SMS)) {
            $channels[] = self::CHANNEL_SMS;
        }
        if ($this->canSendChannelInvitation(self::CHANNEL_WHATSAPP)) {
            $channels[] = self::CHANNEL_WHATSAPP;
        }
        if ($this->canSendChannelInvitation(self::CHANNEL_EMAIL)) {
            $channels[] = self::CHANNEL_EMAIL;
        }

        return $channels;
    }

    // ==================== AGENT MANAGEMENT ====================

    public function assignAgent($agentId, $assignedBy = null): PlanAgentAssignment
    {
        if ($this->agent_assignment_type === self::ASSIGNMENT_SINGLE && $this->active_agents_count >= 1) {
            throw new \Exception('Cannot assign multiple agents to a single-assignment plan.');
        }

        $existingAssignment = $this->assignedAgents()
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->first();

        if ($existingAssignment) {
            throw new \Exception('Agent is already assigned to this plan.');
        }

        return PlanAgentAssignment::create([
            'plan_id' => $this->id,
            'agent_id' => $agentId,
            'assigned_by' => $assignedBy ?: auth()->id(),
            'is_active' => true,
        ]);
    }

    public function removeAgent($agentId, $reason = null): bool
    {
        $assignment = $this->assignedAgents()
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->first();

        if (!$assignment) {
            return false;
        }

        return $assignment->markAsInactive($reason);
    }

    public function isAgentAssigned($agentId): bool
    {
        return $this->assignedAgents()
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->exists();
    }

    public function getAgentAssignment($agentId): ?PlanAgentAssignment
    {
        return $this->assignedAgents()
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->first();
    }

    public function updateAgentPerformance($agentId, $propertiesRegistered = null): bool
    {
        $assignment = $this->getAgentAssignment($agentId);

        if (!$assignment) {
            return false;
        }

        if ($propertiesRegistered !== null) {
            return $assignment->updatePropertiesRegistered($propertiesRegistered);
        } else {
            return $assignment->update(['last_activity_at' => now()]);
        }
    }

    public function assignMultipleAgents(array $agentIds, $assignedBy = null): array
    {
        if ($this->agent_assignment_type === self::ASSIGNMENT_SINGLE) {
            throw new \Exception('Cannot assign multiple agents to a single-assignment plan.');
        }

        $assignments = [];
        foreach ($agentIds as $agentId) {
            if (!$this->isAgentAssigned($agentId)) {
                $assignments[] = $this->assignAgent($agentId, $assignedBy);
            }
        }

        return $assignments;
    }

    public function bulkRemoveAgents(array $agentIds, $reason = null): array
    {
        $results = [];
        foreach ($agentIds as $agentId) {
            $results[$agentId] = $this->removeAgent($agentId, $reason);
        }

        return $results;
    }

    public function getAgentAssignmentsSummary(): array
    {
        $assignments = $this->activeAgents()->with('agent')->get();

        return [
            'total_agents' => $assignments->count(),
            'total_properties_registered' => $assignments->sum('properties_registered'),
            'agents' => $assignments->map(function ($assignment) {
                return [
                    'agent_id' => $assignment->agent_id,
                    'agent_name' => $assignment->agent->name,
                    'agent_phone' => $assignment->agent->phone,
                    'agent_email' => $assignment->agent->email,
                    'properties_registered' => $assignment->properties_registered,
                    'assigned_at' => $assignment->assigned_at->format('Y-m-d H:i:s'),
                    'last_activity' => $assignment->last_activity_at?->format('Y-m-d H:i:s'),
                    'assignment_id' => $assignment->id,
                ];
            })->toArray(),
            'performance_metrics' => $this->getAgentPerformanceAttribute(),
        ];
    }

    public function syncPropertiesCount(): bool
    {
        $actualCount = $this->properties()->count();

        return $this->update([
            'houses_registered' => $actualCount,
            'properties_count' => $actualCount,
        ]);
    }

    // ==================== INVITATION CREATION ====================

    public function createInvitation($agentId, array $data = []): AgentInvitation
    {
        if (!$this->isAgentAssigned($agentId)) {
            throw new \Exception('Agent is not assigned to this plan.');
        }

        $channel = $data['channel'] ?? self::CHANNEL_SMS;

        return AgentInvitation::create([
            'plan_id' => $this->id,
            'agent_id' => $agentId,
            'token' => \Illuminate\Support\Str::random(64),
            'sent_via' => $channel,
            'expires_at' => now()->addDays(7),
            'status' => AgentInvitation::STATUS_SENT,
            'message' => $data['message'] ?? null,
            'provider' => $data['provider'] ?? null,
            'resend_count' => 0,
            'channel_data' => $data['channel_data'] ?? null,
        ]);
    }

    public function createInvitationWithToken($agentId, array $data = []): array
    {
        if (!$this->isAgentAssigned($agentId)) {
            throw new \Exception('Agent is not assigned to this plan.');
        }

        $channel = $data['channel'] ?? self::CHANNEL_SMS;
        $token = \Illuminate\Support\Str::random(64);

        try {
            $invitation = AgentInvitation::create([
                'plan_id' => $this->id,
                'agent_id' => $agentId,
                'token' => $token,
                'sent_via' => $channel,
                'expires_at' => now()->addDays(7),
                'status' => AgentInvitation::STATUS_SENT,
                'message' => $data['message'] ?? null,
                'provider' => $data['provider'] ?? null,
                'resend_count' => 0,
                'channel_data' => $data['channel_data'] ?? null,
            ]);

            return [
                'success' => true,
                'invitation' => $invitation,
                'token' => $token,
                'message' => 'Invitation created successfully',
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to create invitation for agent {$agentId}: " . $e->getMessage());

            return [
                'success' => false,
                'invitation' => null,
                'token' => null,
                'message' => 'Failed to create invitation: ' . $e->getMessage(),
            ];
        }
    }

    public function createMultiChannelInvitationWithMasterToken($agentId, array $channels, array $data = []): array
    {
        if (!$this->isAgentAssigned($agentId)) {
            return [
                'success' => false,
                'message' => 'Agent is not assigned to this plan.',
                'master_token' => null,
                'invitations' => [],
            ];
        }

        $masterToken = \Illuminate\Support\Str::random(64);
        $invitations = [];
        $errors = [];

        foreach ($channels as $channel) {
            $invitationData = array_merge($data, [
                'channel' => $channel,
                'master_token' => $masterToken,
                'channel_data' => array_merge($data['channel_data'] ?? [], ['master_token' => $masterToken]),
            ]);

            $result = $this->createInvitationWithToken($agentId, $invitationData);

            if ($result['success']) {
                $invitations[] = $result['invitation'];
            } else {
                $errors[] = "Failed to create {$channel} invitation: " . $result['message'];
            }
        }

        return [
            'success' => count($invitations) > 0,
            'master_token' => $masterToken,
            'invitations' => $invitations,
            'errors' => $errors,
            'message' => count($invitations) > 0 ?
                'Multi-channel invitations created successfully' :
                'Failed to create invitations: ' . implode(', ', $errors),
        ];
    }

    public function createMultiChannelInvitations(array $agentIds, array $channels, array $data = []): array
    {
        $invitations = [];

        foreach ($agentIds as $agentId) {
            if ($this->isAgentAssigned($agentId)) {
                foreach ($channels as $channel) {
                    $invitationData = array_merge($data, ['channel' => $channel]);
                    $invitations[] = $this->createInvitation($agentId, $invitationData);
                }
            }
        }

        return $invitations;
    }

    public function createMultiChannelInvitationsWithTokens(array $agentIds, array $channels, array $data = []): array
    {
        $results = [];

        foreach ($agentIds as $agentId) {
            if ($this->isAgentAssigned($agentId)) {
                foreach ($channels as $channel) {
                    $invitationData = array_merge($data, ['channel' => $channel]);
                    $result = $this->createInvitationWithToken($agentId, $invitationData);
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    public function getInvitationStatistics(): array
    {
        $totalInvitations = $this->invitations()->count();
        $sentInvitations = $this->invitations()->where('status', AgentInvitation::STATUS_SENT)->count();
        $acceptedInvitations = $this->invitations()->where('status', AgentInvitation::STATUS_ACCEPTED)->count();
        $failedInvitations = $this->invitations()->where('status', AgentInvitation::STATUS_FAILED)->count();
        $expiredInvitations = $this->invitations()->where('status', AgentInvitation::STATUS_EXPIRED)->count();
        $revokedInvitations = $this->invitations()->where('status', AgentInvitation::STATUS_REVOKED)->count();

        $channelStats = [];
        foreach ([self::CHANNEL_SMS, self::CHANNEL_WHATSAPP, self::CHANNEL_EMAIL] as $channel) {
            $channelInvitations = $this->invitations()->where('sent_via', $channel);
            $channelStats[$channel] = [
                'total' => $channelInvitations->count(),
                'accepted' => $channelInvitations->where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
                'success_rate' => $channelInvitations->count() > 0
                    ? round(($channelInvitations->where('status', AgentInvitation::STATUS_ACCEPTED)->count() / $channelInvitations->count()) * 100, 2)
                    : 0,
            ];
        }

        return [
            'total_invitations' => $totalInvitations,
            'sent_invitations' => $sentInvitations,
            'accepted_invitations' => $acceptedInvitations,
            'failed_invitations' => $failedInvitations,
            'expired_invitations' => $expiredInvitations,
            'revoked_invitations' => $revokedInvitations,
            'success_rate' => $totalInvitations > 0 ? round(($acceptedInvitations / $totalInvitations) * 100, 2) : 0,
            'methods_used' => $this->invitations()->distinct()->pluck('sent_via')->toArray(),
            'agents_with_invitations' => $this->invitations()->distinct('agent_id')->count('agent_id'),
            'channel_statistics' => $channelStats,
            'database_invitation_status' => $this->invitation_status,
            'sms_attempts' => $this->sms_attempts,
            'whatsapp_attempts' => $this->whatsapp_attempts,
            'email_attempts' => $this->email_attempts,
            'last_sms_attempt' => $this->last_sms_attempt_at?->diffForHumans(),
            'last_whatsapp_attempt' => $this->last_whatsapp_attempt_at?->diffForHumans(),
            'last_email_attempt' => $this->last_email_attempt_at?->diffForHumans(),
            'invitation_provider' => $this->invitation_provider,
            'preferred_channel' => $this->preferred_channel,
            'available_channels' => $this->invitation_channels ?? [],
        ];
    }

    public function getActiveInvitationsWithTokens(): array
    {
        return $this->invitations()
            ->where('status', AgentInvitation::STATUS_SENT)
            ->where('expires_at', '>', now())
            ->select(['id', 'agent_id', 'token', 'sent_via', 'created_at', 'expires_at'])
            ->get()
            ->toArray();
    }

    public function findInvitationByToken($token): ?AgentInvitation
    {
        return $this->invitations()
            ->where('token', $token)
            ->first();
    }

    public function validateInvitationToken($token): array
    {
        $invitation = $this->findInvitationByToken($token);

        if (!$invitation) {
            return [
                'valid' => false,
                'message' => 'Invalid invitation token',
                'invitation' => null,
            ];
        }

        if ($invitation->expires_at < now()) {
            return [
                'valid' => false,
                'message' => 'Invitation has expired',
                'invitation' => $invitation,
            ];
        }

        if ($invitation->status !== AgentInvitation::STATUS_SENT) {
            return [
                'valid' => false,
                'message' => 'Invitation is no longer valid',
                'invitation' => $invitation,
            ];
        }

        return [
            'valid' => true,
            'message' => 'Invitation token is valid',
            'invitation' => $invitation,
        ];
    }

    // ==================== PROPERTY REGISTRATION ====================

    public function registerPropertyWithPattern(array $propertyData, $registeredBy = null): array
    {
        DB::beginTransaction();

        try {
            $nextPattern = $this->getNextPattern();
            $registrationPattern = $nextPattern['next_available_name'];

            $property = $this->properties()->create(array_merge($propertyData, [
                'registration_pattern' => $registrationPattern,
                'registered_by' => $registeredBy,
                'registration_date' => now(),
            ]));

            $this->update([
                'next_available_name' => $nextPattern['next_next_name'],
                'houses_registered' => $this->houses_registered + 1,
            ]);

            if ($registeredBy) {
                $this->updateAgentPerformance($registeredBy);
            }

            if ($this->is_global_sequence) {
                $this->updateGlobalSequenceStats();
            }

            DB::commit();

            // If this was the first property registered by an active agent
            // on an 'assigned' plan, flip the plan to 'in_progress'.
            $this->markInProgressIfAssigned($registeredBy ? (int) $registeredBy : null);

            return [
                'success' => true,
                'property' => $property,
                'registration_pattern' => $registrationPattern,
                'next_available_name' => $this->next_available_name,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to register property: ' . $e->getMessage(),
                'property' => null,
            ];
        }
    }

    // ==================== PATTERN GENERATION ====================

    public function getNextPattern(): array
    {
        $nextName = $this->next_available_name ?? $this->starting_point;
        $pattern = $this->generatePatternFromName($this->naming_pattern, $nextName);

        if (!$pattern) {
            throw new \Exception('Failed to generate pattern from naming template.');
        }

        $nextNextName = $this->generateNextName($nextName, $this->naming_pattern, $this->sequence_type);

        return [
            'next_pattern' => $pattern,
            'next_available_name' => $nextName,
            'next_next_name' => $nextNextName,
            'plan_progress' => [
                'registered' => $this->properties()->count(),
                'estimated' => $this->estimated_houses,
                'percentage' => $this->progress_percentage,
            ],
            'plan_status' => $this->status,
            'agent_assignment_type' => $this->agent_assignment_type,
            'assigned_agents_count' => $this->assigned_agents_count,
            'multiple_agents_summary' => $this->multiple_agents_summary,
            'can_assign_more_agents' => $this->can_assign_more_agents,
            'sequence_continuation_info' => $this->sequence_continuation_info,
        ];
    }

    private function generatePatternFromName($pattern, $name): string
    {
        $generated = $pattern;

        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            if (preg_match('/([A-Za-z]+)(\d+)/', $name, $matches)) {
                $letter = $matches[1];
                $number = $matches[2];
                $generated = str_replace(['{letter}', '{number}'], [$letter, $number], $pattern);
            } else {
                return $name;
            }
        } elseif (strpos($pattern, '{letter}') !== false) {
            if (preg_match('/^[a-zA-Z]+$/', $name)) {
                $generated = str_replace('{letter}', $name, $pattern);
            } else {
                return $name;
            }
        } elseif (strpos($pattern, '{number}') !== false) {
            if (preg_match('/^\d+$/', $name)) {
                $generated = str_replace('{number}', $name, $pattern);
            } else {
                return $name;
            }
        }

        return $generated;
    }

    private function generateNextName($currentName, $pattern = null, $sequenceType = null): string
    {
        $pattern = $pattern ?: $this->naming_pattern;
        $sequenceType = $sequenceType ?: $this->sequence_type;

        if (empty($currentName)) {
            return $this->getStartingName($pattern);
        }

        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return $this->generateCombinedNextName($currentName, $sequenceType);
        } elseif (strpos($pattern, '{letter}') !== false) {
            return $this->generateLetterNextName($currentName);
        } elseif (strpos($pattern, '{number}') !== false) {
            return $this->generateNumberNextName($currentName, $sequenceType);
        }

        return $this->generateSimpleNextName($currentName);
    }

    private function getStartingName($pattern): string
    {
        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return 'A1';
        } elseif (strpos($pattern, '{letter}') !== false) {
            return 'A';
        } elseif (strpos($pattern, '{number}') !== false) {
            return '1';
        }

        return '001';
    }

    private function generateCombinedNextName($currentName, $sequenceType): string
    {
        if (preg_match('/([A-Za-z]+)(\d+)/', $currentName, $matches)) {
            $letter = $matches[1];
            $number = (int) $matches[2];

            $number += 1;

            switch ($sequenceType) {
                case self::EVEN_ONLY:
                    if ($number % 2 !== 0) $number += 1;
                    break;
                case self::ODD_ONLY:
                    if ($number % 2 === 0) $number += 1;
                    break;
            }

            if ($number > 99) {
                $letter = $this->incrementLetters($letter);
                $number = $sequenceType === self::EVEN_ONLY ? 2 : ($sequenceType === self::ODD_ONLY ? 1 : 1);
            }

            return $letter . $number;
        }

        return $this->generateSimpleNextName($currentName);
    }

    private function incrementLetters($letters): string
    {
        $length = strlen($letters);
        for ($i = $length - 1; $i >= 0; $i--) {
            if ($letters[$i] !== 'Z') {
                $letters[$i] = chr(ord($letters[$i]) + 1);
                return $letters;
            }
            $letters[$i] = 'A';
        }
        return 'A' . $letters;
    }

    private function generateLetterNextName($currentName): string
    {
        return $this->incrementLetters($currentName);
    }

    private function generateNumberNextName($currentName, $sequenceType): string
    {
        $number = (int) $currentName;

        switch ($sequenceType) {
            case self::EVEN_ONLY:
                return $number % 2 === 0 ? $number + 2 : $number + 1;
            case self::ODD_ONLY:
                return $number % 2 === 1 ? $number + 2 : $number + 1;
            default:
                return $number + 1;
        }
    }

    private function generateSimpleNextName($currentName): string
    {
        if (preg_match('/(.*?)(\d+)$/', $currentName, $matches)) {
            $prefix = $matches[1];
            $number = (int) $matches[2];
            return $prefix . ($number + 1);
        }

        return $currentName . '-1';
    }

    // ==================== STATISTICS ====================

    public static function getDashboardStatistics(): array
    {
        return [
            'total_plans' => self::count(),
            'active_plans' => self::active()->count(),
            'completed_plans' => self::where('status', self::STATUS_COMPLETED)->count(),
            'cancelled_plans' => self::where('status', self::STATUS_CANCELLED)->count(),
            'trashed_plans' => self::onlyTrashed()->count(),
            'overdue_plans' => self::overdue()->count(),
            'unassigned_plans' => self::unassigned()->count(),
            'total_houses_target' => self::sum('estimated_houses'),
            'total_houses_registered' => self::withCount('properties')->get()->sum('properties_count'),
            'total_properties_count' => self::withCount('properties')->get()->sum('properties_count'),
            'global_sequence_plans' => self::globalSequence()->count(),
            'single_assignment_plans' => self::singleAssignment()->count(),
            'multiple_assignment_plans' => self::multipleAssignment()->count(),
            'total_agent_assignments' => PlanAgentAssignment::where('is_active', true)->count(),
            'sms_invitations_sent' => self::where('sms_attempts', '>', 0)->count(),
            'whatsapp_invitations_sent' => self::where('whatsapp_attempts', '>', 0)->count(),
            'email_invitations_sent' => self::where('email_attempts', '>', 0)->count(),
            'plans_with_multi_channel' => self::whereJsonLength('invitation_channels', '>', 1)->count(),
            'plans_with_multiple_agents' => self::multipleAssignment()->withAssignedAgents()->count(),
            'total_multiple_agent_assignments' => self::multipleAssignment()->withAssignedAgents()->get()->sum('assigned_agents_count'),
            'total_invitations_with_tokens' => AgentInvitation::count(),
            'active_invitations_with_tokens' => AgentInvitation::where('status', AgentInvitation::STATUS_SENT)
                ->where('expires_at', '>', now())
                ->count(),
        ];
    }

    public static function getGlobalSequenceStats(): array
    {
        $globalSequencePlans = self::globalSequence()->get();

        $totalProperties = 0;
        $completedPlans = 0;
        $activePlans = 0;
        $sequencePatterns = [];

        foreach ($globalSequencePlans as $plan) {
            $propertyCount = $plan->properties()->count();
            $totalProperties += $propertyCount;

            if ($plan->status === self::STATUS_COMPLETED) {
                $completedPlans++;
            } elseif (in_array($plan->status, [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS])) {
                $activePlans++;
            }

            $pattern = $plan->naming_pattern;
            if (!isset($sequencePatterns[$pattern])) {
                $sequencePatterns[$pattern] = 0;
            }
            $sequencePatterns[$pattern] += $propertyCount;
        }

        return [
            'total_global_sequence_plans' => $globalSequencePlans->count(),
            'total_properties_in_global_sequences' => $totalProperties,
            'completed_global_sequence_plans' => $completedPlans,
            'active_global_sequence_plans' => $activePlans,
            'sequence_patterns_distribution' => $sequencePatterns,
            'average_properties_per_global_sequence' => $globalSequencePlans->count() > 0 ?
                round($totalProperties / $globalSequencePlans->count(), 2) : 0,
        ];
    }

    public static function getValidationRules($planId = null): array
    {
        return [
            'assigned_agent_ids' => 'nullable|array',
            'assigned_agent_ids.*' => 'exists:users,id',
            'agent_phones' => 'nullable|array',
            'agent_phones.*' => 'string|max:20',
            'agent_names' => 'required_with:agent_phones|array',
            'agent_names.*' => 'string|max:255',
            'agent_emails' => 'nullable|array',
            'agent_emails.*' => 'nullable|email',
            'invitation_methods' => 'nullable|array',
            'invitation_methods.*' => 'in:sms,whatsapp,email,all_channels',
            'invitation_channels' => 'nullable|array',
            'invitation_channels.*' => 'in:sms,whatsapp,email',
            'preferred_channel' => 'nullable|in:sms,whatsapp,email',
            'zone' => 'required|string|max:100',
            'section' => 'nullable|string|max:100',
            'naming_pattern' => 'required|string|max:100',
            'custom_pattern' => 'nullable|string|max:100',
            'starting_point' => 'required|string|max:50',
            'estimated_houses' => 'required|integer|min:1',
            'sequence_type' => 'required|in:sequential,even_only,odd_only',
            'agent_assignment_type' => 'required|in:single,multiple',
            'registration_start_date' => 'nullable|date',
            'registration_end_date' => 'nullable|date|after_or_equal:registration_start_date',
            'instructions' => 'nullable|string',
            'boundaries_description' => 'nullable|string',
            'continue_global_sequence' => 'sometimes|boolean',
        ];
    }

    public static function getLastUsedGlobalPattern($namingPattern = null)
    {
        $query = self::whereNotNull('next_available_name')
            ->where('is_global_sequence', true)
            ->orderBy('created_at', 'desc');

        if ($namingPattern) {
            $query->where('naming_pattern', $namingPattern);
        }

        return $query->first();
    }

    public static function validateNamingPattern($pattern, $startingPoint, $sequenceType = null)
    {
        if (!preg_match('/\{([a-zA-Z_]+)\}/', $pattern)) {
            return [
                'valid' => false,
                'message' => 'Naming pattern must contain at least one placeholder like {letter}, {number}, etc.',
            ];
        }

        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            if (!preg_match('/^[A-Za-z]+\d+$/', $startingPoint)) {
                return [
                    'valid' => false,
                    'message' => 'Starting point must be in format like A1, B2, etc. for letter+number patterns.',
                ];
            }

            if ($sequenceType) {
                $number = intval(preg_replace('/[^0-9]/', '', $startingPoint));
                if ($sequenceType === self::EVEN_ONLY && $number % 2 !== 0) {
                    return [
                        'valid' => false,
                        'message' => 'For even-only sequences, starting point must have an even number (A2, B4, etc.).',
                    ];
                }
                if ($sequenceType === self::ODD_ONLY && $number % 2 === 0) {
                    return [
                        'valid' => false,
                        'message' => 'For odd-only sequences, starting point must have an odd number (A1, B3, etc.).',
                    ];
                }
            }
        } elseif (strpos($pattern, '{letter}') !== false) {
            if (!preg_match('/^[A-Za-z]+$/', $startingPoint)) {
                return [
                    'valid' => false,
                    'message' => 'Starting point must contain only letters for letter-only patterns.',
                ];
            }
        } elseif (strpos($pattern, '{number}') !== false) {
            if (!is_numeric($startingPoint)) {
                return [
                    'valid' => false,
                    'message' => 'Starting point must be a number for number-only patterns.',
                ];
            }

            if ($sequenceType) {
                $number = intval($startingPoint);
                if ($sequenceType === self::EVEN_ONLY && $number % 2 !== 0) {
                    return [
                        'valid' => false,
                        'message' => 'For even-only sequences, starting point must be an even number (2, 4, 6, etc.).',
                    ];
                }
                if ($sequenceType === self::ODD_ONLY && $number % 2 === 0) {
                    return [
                        'valid' => false,
                        'message' => 'For odd-only sequences, starting point must be an odd number (1, 3, 5, etc.).',
                    ];
                }
            }
        }

        return ['valid' => true, 'message' => 'Pattern is valid'];
    }

    public function generateSequencePreview($count = 5): array
    {
        $preview = [];
        $currentName = $this->next_available_name ?? $this->starting_point;

        for ($i = 0; $i < $count; $i++) {
            $preview[] = $currentName;
            $currentName = $this->generateNextName($currentName, $this->naming_pattern, $this->sequence_type);
        }

        return $preview;
    }
}