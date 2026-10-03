<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AgentInvitationLog extends Model
{
    use HasFactory;

    // ✅ ADDED: Action constants for consistent logging
    const ACTION_CREATED = 'created';
    const ACTION_SENT = 'sent';
    const ACTION_RESENT = 'resent';
    const ACTION_DELIVERED = 'delivered';
    const ACTION_VIEWED = 'viewed';
    const ACTION_ACCEPTED = 'accepted';
    const ACTION_EXPIRED = 'expired';
    const ACTION_REVOKED = 'revoked';
    const ACTION_FAILED = 'failed';
    const ACTION_VERIFIED = 'verified';
    const ACTION_SECURITY_CODE_SENT = 'security_code_sent';
    const ACTION_BULK_SENT = 'bulk_sent';
    const ACTION_EXTENDED = 'extended';
    const ACTION_FIXED = 'fixed';
    const ACTION_EMAIL_SENT = 'email_sent'; // ✅ ADDED: Specific email sent action

    protected $fillable = [
        'invitation_id',
        'user_id',
        'agent_id',
        'plan_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'metadata' // ✅ ADDED: For enhanced logging data
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array' // ✅ ADDED: For JSON metadata storage
    ];

    // ✅ ADDED: Appends for enhanced functionality
    protected $appends = [
        'action_label',
        'is_system_action',
        'log_summary'
    ];

    /**
     * ✅ ADDED: Boot method for automatic logging enhancements
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Set created_at timestamp if not provided
            if (empty($model->created_at)) {
                $model->created_at = now();
            }

            // Auto-populate metadata if not provided
            if (empty($model->metadata)) {
                $model->metadata = [
                    'logged_at' => now()->toISOString(),
                    'system_version' => config('app.version', '1.0.0'),
                    'log_type' => 'invitation_activity'
                ];
            }

            // Set default details if empty
            if (empty($model->details) && $model->invitation) {
                $model->details = "Invitation {$model->invitation->id} - {$model->action}";
            }
        });
    }

    // Relationships
    public function invitation()
    {
        return $this->belongsTo(AgentInvitation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function plan()
    {
        return $this->belongsTo(RegistrationPlan::class);
    }

    /**
     * ✅ ADDED: Accessors for enhanced functionality
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_SENT => 'Sent',
            self::ACTION_RESENT => 'Resent',
            self::ACTION_DELIVERED => 'Delivered',
            self::ACTION_VIEWED => 'Viewed',
            self::ACTION_ACCEPTED => 'Accepted',
            self::ACTION_EXPIRED => 'Expired',
            self::ACTION_REVOKED => 'Revoked',
            self::ACTION_FAILED => 'Failed',
            self::ACTION_VERIFIED => 'Verified',
            self::ACTION_SECURITY_CODE_SENT => 'Security Code Sent',
            self::ACTION_BULK_SENT => 'Bulk Sent',
            self::ACTION_EXTENDED => 'Extended',
            self::ACTION_FIXED => 'Fixed',
            self::ACTION_EMAIL_SENT => 'Email Sent', // ✅ ADDED
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }

    public function getIsSystemActionAttribute(): bool
    {
        return in_array($this->action, [
            self::ACTION_EXPIRED,
            self::ACTION_EXTENDED,
            self::ACTION_FIXED,
            self::ACTION_CREATED,
            self::ACTION_EMAIL_SENT // ✅ ADDED
        ]) || empty($this->user_id);
    }

    public function getLogSummaryAttribute(): string
    {
        $summary = "{$this->action_label}";

        if ($this->user) {
            $summary .= " by {$this->user->name}";
        } elseif ($this->is_system_action) {
            $summary .= " by System";
        }

        if ($this->created_at) {
            $summary .= " at {$this->created_at->format('M j, Y g:i A')}";
        }

        return $summary;
    }

    /**
     * ✅ FIXED: Add the missing method that caused the original error
     */
    public static function logEmailSent(
        int $invitationId, 
        string $agentEmail, 
        string $masterToken, 
        ?string $provider = null,
        ?string $template = null,
        ?string $subject = null
    ): ?self {
        $metadata = [
            'email_sent' => true,
            'provider' => $provider ?? 'gmail_smtp',
            'template' => $template,
            'subject' => $subject,
            'sent_at' => now()->toISOString(),
            'master_token' => $masterToken,
            'agent_email' => $agentEmail,
            'delivery_channel' => 'email'
        ];

        return self::logInvitationAction(
            $invitationId,
            self::ACTION_EMAIL_SENT,
            "Email invitation sent to {$agentEmail} via " . ($provider ?? 'gmail_smtp'),
            null, // system action
            null, // agent_id
            null, // plan_id
            null, // ip_address
            null, // user_agent
            $metadata
        );
    }

    /**
     * ✅ ADDED: Method to log email failures specifically
     */
    public static function logEmailFailure(
        int $invitationId,
        string $agentEmail,
        string $masterToken,
        string $errorMessage,
        ?string $provider = null
    ): ?self {
        $metadata = [
            'email_failed' => true,
            'provider' => $provider ?? 'gmail_smtp',
            'failed_at' => now()->toISOString(),
            'master_token' => $masterToken,
            'agent_email' => $agentEmail,
            'error_message' => $errorMessage,
            'delivery_channel' => 'email'
        ];

        return self::logInvitationAction(
            $invitationId,
            self::ACTION_FAILED,
            "Email sending failed to {$agentEmail}: {$errorMessage}",
            null, // system action
            null, // agent_id
            null, // plan_id
            null, // ip_address
            null, // user_agent
            $metadata
        );
    }

    /**
     * ✅ ADDED: Method to log multi-channel invitation failures
     */
    public static function logMultiChannelFailure(
        int $planId,
        int $agentId,
        string $errorMessage,
        ?string $errorCode = null,
        ?array $failedChannels = null
    ): ?self {
        $metadata = [
            'multi_channel_failure' => true,
            'failed_channels' => $failedChannels ?? ['email'],
            'error_code' => $errorCode ?? 'unknown',
            'failed_at' => now()->toISOString()
        ];

        $details = "Multi-channel invitation failed for agent {$agentId}, plan {$planId}: {$errorMessage}";
        if ($failedChannels) {
            $details .= " (Failed channels: " . implode(', ', $failedChannels) . ")";
        }

        return self::logInvitationAction(
            null, // No specific invitation_id for multi-channel failure
            self::ACTION_FAILED,
            $details,
            null, // system action
            $agentId,
            $planId,
            null, // ip_address
            null, // user_agent
            $metadata
        );
    }

    /**
     * ✅ ADDED: Scope methods for enhanced querying
     */
    public function scopeForInvitation($query, $invitationId)
    {
        return $query->where('invitation_id', $invitationId);
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeSystemActions($query)
    {
        return $query->whereNull('user_id')
                    ->orWhereIn('action', [
                        self::ACTION_EXPIRED,
                        self::ACTION_EXTENDED,
                        self::ACTION_FIXED,
                        self::ACTION_EMAIL_SENT
                    ]);
    }

    public function scopeUserActions($query)
    {
        return $query->whereNotNull('user_id')
                    ->whereNotIn('action', [
                        self::ACTION_EXPIRED,
                        self::ACTION_EXTENDED,
                        self::ACTION_FIXED,
                        self::ACTION_EMAIL_SENT
                    ]);
    }

    public function scopeEmailActions($query)
    {
        return $query->where('action', self::ACTION_EMAIL_SENT)
                    ->orWhere(function($q) {
                        $q->where('action', self::ACTION_FAILED)
                          ->where('metadata->delivery_channel', 'email');
                    });
    }

    public function scopeMultiChannel($query, $channel = null)
    {
        if ($channel && $this->hasColumn('metadata')) {
            return $query->where('metadata->channel', $channel);
        }
        
        return $query->whereNotNull('metadata->channel');
    }

    /**
     * ✅ ADDED: Analytics and reporting methods
     */
    public function getAnalyticsData(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'action_label' => $this->action_label,
            'invitation_id' => $this->invitation_id,
            'agent_id' => $this->agent_id,
            'plan_id' => $this->plan_id,
            'user_id' => $this->user_id,
            'is_system_action' => $this->is_system_action,
            'details' => $this->details,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toISOString(),
            'metadata' => $this->metadata,
            'log_summary' => $this->log_summary
        ];
    }

    public function getChannelFromMetadata(): ?string
    {
        return $this->metadata['channel'] ?? null;
    }

    public function getMethodFromMetadata(): ?string
    {
        return $this->metadata['method'] ?? null;
    }

    public function getProviderFromMetadata(): ?string
    {
        return $this->metadata['provider'] ?? null;
    }

    /**
     * ✅ FIXED: Static methods for common logging operations - Handle null invitation_id
     */
    public static function logInvitationAction(
        $invitationId, // ✅ CHANGED: Removed int type hint to allow null
        string $action,
        ?string $details = null,
        ?int $userId = null,
        ?int $agentId = null,
        ?int $planId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): ?self {
        try {
            // ✅ FIX: Convert 0 to null and validate invitation_id
            $invitationId = $invitationId ?: null;
            
            // If invitation_id is provided, verify it exists (unless it's a system action)
            if ($invitationId && !self::isSystemAction($action)) {
                $invitationExists = AgentInvitation::where('id', $invitationId)->exists();
                if (!$invitationExists) {
                    Log::warning("Invalid invitation_id provided for log: {$invitationId}", [
                        'action' => $action,
                        'agent_id' => $agentId
                    ]);
                    $invitationId = null; // Don't log with invalid invitation_id
                }
            }

            $logData = [
                'invitation_id' => $invitationId,
                'action' => $action,
                'details' => $details,
                'user_id' => $userId,
                'agent_id' => $agentId,
                'plan_id' => $planId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ];

            if ($metadata) {
                $logData['metadata'] = array_merge($metadata, [
                    'logged_via' => 'static_method',
                    'timestamp' => now()->toISOString()
                ]);
            }

            return self::create($logData);

        } catch (\Exception $e) {
            Log::error('Failed to log invitation action: ' . $e->getMessage(), [
                'invitation_id' => $invitationId,
                'action' => $action,
                'agent_id' => $agentId
            ]);
            return null;
        }
    }

    /**
     * ✅ ADDED: Helper to determine if action is a system action
     */
    private static function isSystemAction(string $action): bool
    {
        return in_array($action, [
            self::ACTION_CREATED,
            self::ACTION_EXPIRED,
            self::ACTION_EXTENDED,
            self::ACTION_FIXED,
            self::ACTION_EMAIL_SENT
        ]);
    }

    public static function logMultiChannelSent(
        int $invitationId,
        string $channel,
        string $method,
        ?string $provider = null,
        ?int $userId = null,
        ?string $ipAddress = null
    ): ?self {
        $metadata = [
            'channel' => $channel,
            'method' => $method,
            'provider' => $provider,
            'multi_channel' => true,
            'delivery_attempted_at' => now()->toISOString()
        ];

        return self::logInvitationAction(
            $invitationId,
            self::ACTION_SENT,
            "Invitation sent via {$channel} using {$method}" . ($provider ? " ({$provider})" : ""),
            $userId,
            null, // agent_id will be auto-populated from invitation
            null, // plan_id will be auto-populated from invitation
            $ipAddress,
            null, // user_agent
            $metadata
        );
    }

    public static function logSecurityCodeSent(
        int $invitationId,
        string $channel,
        ?int $userId = null,
        ?string $ipAddress = null
    ): ?self {
        $metadata = [
            'channel' => $channel,
            'security_code_sent_at' => now()->toISOString(),
            'verification_type' => 'multi_channel'
        ];

        return self::logInvitationAction(
            $invitationId,
            self::ACTION_SECURITY_CODE_SENT,
            "Security code sent via {$channel}",
            $userId,
            null,
            null,
            $ipAddress,
            null,
            $metadata
        );
    }

    public static function logExpirationExtended(
        int $invitationId,
        int $additionalDays,
        ?string $reason = null,
        ?int $userId = null
    ): ?self {
        $details = "Expiration extended by {$additionalDays} days";
        if ($reason) {
            $details .= " - {$reason}";
        }

        $metadata = [
            'extended_days' => $additionalDays,
            'reason' => $reason,
            'extended_at' => now()->toISOString()
        ];

        return self::logInvitationAction(
            $invitationId,
            self::ACTION_EXTENDED,
            $details,
            $userId,
            null,
            null,
            null,
            null,
            $metadata
        );
    }

    public static function logCorruptionFixed(
        int $invitationId,
        string $issue,
        ?string $previousValue = null,
        ?string $newValue = null
    ): ?self {
        $details = "Fixed corruption issue: {$issue}";
        
        $metadata = [
            'corruption_issue' => $issue,
            'fixed_at' => now()->toISOString(),
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'auto_fixed' => true
        ];

        return self::logInvitationAction(
            $invitationId,
            self::ACTION_FIXED,
            $details,
            null, // system action
            null,
            null,
            null,
            null,
            $metadata
        );
    }

    /**
     * ✅ ADDED: Method specifically for agent creation logs (no invitation_id)
     */
    public static function logAgentCreation(
        string $agentName,
        ?int $userId = null,
        ?int $agentId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $contactMethods = null
    ): ?self {
        $details = "Field agent created: {$agentName}";
        
        $metadata = [
            'agent_creation' => true,
            'contact_methods' => $contactMethods ?? [],
            'log_type' => 'agent_management'
        ];

        return self::logInvitationAction(
            null, // ✅ No invitation_id for agent creation
            self::ACTION_CREATED,
            $details,
            $userId,
            $agentId,
            null, // No plan_id for agent creation
            $ipAddress,
            $userAgent,
            $metadata
        );
    }

    /**
     * ✅ ADDED: Bulk logging for multiple invitations
     */
    public static function logBulkAction(
        array $invitationIds,
        string $action,
        ?string $details = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?array $metadata = null
    ): array {
        $logs = [];
        $timestamp = now();

        foreach ($invitationIds as $invitationId) {
            $logData = [
                'invitation_id' => $invitationId,
                'action' => $action,
                'details' => $details ?? "Bulk action: {$action}",
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'created_at' => $timestamp,
            ];

            if ($metadata) {
                $bulkMetadata = array_merge($metadata, [
                    'bulk_operation' => true,
                    'total_invitations' => count($invitationIds),
                    'bulk_timestamp' => $timestamp->toISOString()
                ]);
                $logData['metadata'] = $bulkMetadata;
            }

            try {
                $logs[] = self::create($logData);
            } catch (\Exception $e) {
                Log::error('Failed to create bulk log entry: ' . $e->getMessage(), [
                    'invitation_id' => $invitationId,
                    'action' => $action
                ]);
            }
        }

        return $logs;
    }

    /**
     * ✅ ADDED: Analytics and reporting scopes
     */
    public function scopeGetChannelStats($query, ?int $planId = null, ?string $period = '30 days')
    {
        $query = $query->where('action', self::ACTION_SENT)
                      ->whereNotNull('metadata->channel');

        if ($planId) {
            $query->where('plan_id', $planId);
        }

        if ($period) {
            $query->where('created_at', '>=', now()->sub($period));
        }

        return $query->selectRaw('
            metadata->>"$.channel" as channel,
            COUNT(*) as total_sent,
            COUNT(CASE WHEN EXISTS(SELECT 1 FROM agent_invitations WHERE agent_invitations.id = agent_invitation_logs.invitation_id AND agent_invitations.status = "accepted") THEN 1 END) as accepted_count,
            AVG(CASE WHEN EXISTS(SELECT 1 FROM agent_invitations WHERE agent_invitations.id = agent_invitation_logs.invitation_id AND agent_invitations.response_time_minutes IS NOT NULL) THEN (SELECT response_time_minutes FROM agent_invitations WHERE agent_invitations.id = agent_invitation_logs.invitation_id) END) as avg_response_time
        ')->groupBy('channel');
    }

    public function scopeGetActivityTimeline($query, ?int $planId = null, int $limit = 50)
    {
        $query = $query->with(['invitation', 'user', 'agent'])
                      ->orderBy('created_at', 'desc')
                      ->limit($limit);

        if ($planId) {
            $query->where('plan_id', $planId);
        }

        return $query;
    }

    /**
     * ✅ ADDED: Helper methods
     */
    public function hasMetadata(): bool
    {
        return !empty($this->metadata) && is_array($this->metadata);
    }

    public function isSuccessfulAction(): bool
    {
        return in_array($this->action, [
            self::ACTION_SENT,
            self::ACTION_DELIVERED,
            self::ACTION_ACCEPTED,
            self::ACTION_VERIFIED,
            self::ACTION_EXTENDED,
            self::ACTION_FIXED
        ]);
    }

    public function isFailureAction(): bool
    {
        return in_array($this->action, [
            self::ACTION_FAILED,
            self::ACTION_EXPIRED,
            self::ACTION_REVOKED
        ]);
    }

    public function getTimeSinceCreated(): string
    {
        if (!$this->created_at) {
            return 'Unknown';
        }

        return $this->created_at->diffForHumans();
    }

     /**
     * ✅ ADDED: Database helper method
     */
    private function hasColumn(string $column): bool
    {
        static $columnCache = [];

        if (!isset($columnCache[$column])) {
            try {
                $columnCache[$column] = Schema::hasColumn($this->getTable(), $column);
            } catch (\Exception $e) {
                $columnCache[$column] = false;
            }
        }

        return $columnCache[$column];
    }

    /**
     * ✅ ADDED: Safe creation method that handles foreign key constraints
     */
    public static function safeCreate(array $attributes): ?self
    {
        try {
            // Ensure invitation_id is valid if provided
            if (isset($attributes['invitation_id']) && $attributes['invitation_id']) {
                $invitationExists = AgentInvitation::where('id', $attributes['invitation_id'])->exists();
                if (!$invitationExists) {
                    Log::warning('Attempted to create log with invalid invitation_id', [
                        'invitation_id' => $attributes['invitation_id'],
                        'action' => $attributes['action'] ?? 'unknown'
                    ]);
                    $attributes['invitation_id'] = null;
                }
            }

            return self::create($attributes);
        } catch (\Exception $e) {
            Log::error('Failed to safely create invitation log: ' . $e->getMessage(), [
                'attributes' => $attributes
            ]);
            return null;
        }
    }
}