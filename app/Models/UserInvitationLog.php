<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvitationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_invitation_id',
        'user_id',
        'action',
        'channel',
        'token',
        'status',
        'message',
        'data',
        'ip_address',
        'user_agent',
        'created_by'
    ];

    protected $casts = [
        'data' => 'array'
    ];

    /**
     * Relationship with UserInvitation
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(UserInvitation::class, 'user_invitation_id');
    }

    /**
     * Relationship with User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Static method to log invitation creation
     */
    public static function logInvitationCreated(
        int $invitationId,
        int $userId,
        int $createdBy,
        array $channels,
        string $token
    ): self {
        return self::create([
            'user_invitation_id' => $invitationId,
            'user_id' => $userId,
            'action' => 'invitation_created',
            'channel' => 'system',
            'token' => $token,
            'status' => 'success',
            'message' => 'User invitation created',
            'data' => [
                'channels' => $channels,
                'created_via' => 'service'
            ],
            'created_by' => $createdBy,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Static method to log email sent
     */
    public static function logEmailSent(
        int $invitationId,
        string $email,
        string $messageId,
        string $token,
        bool $tokenVerified = true
    ): self {
        return self::create([
            'user_invitation_id' => $invitationId,
            'action' => 'email_sent',
            'channel' => 'email',
            'token' => $token,
            'status' => 'success',
            'message' => 'Email invitation sent',
            'data' => [
                'to' => $email,
                'message_id' => $messageId,
                'token_verified' => $tokenVerified,
                'verified_at' => now()->toISOString()
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Static method to log SMS sent
     */
    public static function logSmsSent(
        int $invitationId,
        string $phone,
        string $messageId,
        string $token
    ): self {
        return self::create([
            'user_invitation_id' => $invitationId,
            'action' => 'sms_sent',
            'channel' => 'sms',
            'token' => $token,
            'status' => 'success',
            'message' => 'SMS invitation sent',
            'data' => [
                'to' => $phone,
                'message_id' => $messageId,
                'verified_at' => now()->toISOString()
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Static method to log WhatsApp sent
     */
    public static function logWhatsAppSent(
        int $invitationId,
        string $phone,
        string $messageId,
        string $token
    ): self {
        return self::create([
            'user_invitation_id' => $invitationId,
            'action' => 'whatsapp_sent',
            'channel' => 'whatsapp',
            'token' => $token,
            'status' => 'success',
            'message' => 'WhatsApp invitation sent',
            'data' => [
                'to' => $phone,
                'message_id' => $messageId,
                'verified_at' => now()->toISOString()
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Static method to log invitation accepted
     */
    public static function logInvitationAccepted(
        int $invitationId,
        int $userId,
        string $channel,
        string $token
    ): self {
        return self::create([
            'user_invitation_id' => $invitationId,
            'user_id' => $userId,
            'action' => 'invitation_accepted',
            'channel' => $channel,
            'token' => $token,
            'status' => 'success',
            'message' => 'User invitation accepted',
            'data' => [
                'accepted_via' => $channel,
                'accepted_at' => now()->toISOString()
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Static method to log token repair
     */
    public static function logTokenRepaired(
        int $invitationId,
        string $originalToken,
        string $newToken,
        bool $repairSuccessful
    ): self {
        return self::create([
            'user_invitation_id' => $invitationId,
            'action' => 'token_repaired',
            'channel' => 'system',
            'token' => $newToken,
            'status' => $repairSuccessful ? 'success' : 'failed',
            'message' => $repairSuccessful ? 'Token inconsistency repaired' : 'Token repair failed',
            'data' => [
                'original_token' => $originalToken,
                'new_token' => $newToken,
                'repair_successful' => $repairSuccessful,
                'repaired_at' => now()->toISOString()
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Static method to log invitation resent
     */
    public static function logInvitationResent(
        int $invitationId,
        int $userId,
        int $resentBy,
        array $channels,
        string $token
    ): self {
        return self::create([
            'user_invitation_id' => $invitationId,
            'user_id' => $userId,
            'action' => 'invitation_resent',
            'channel' => 'system',
            'token' => $token,
            'status' => 'success',
            'message' => 'User invitation resent',
            'data' => [
                'channels' => $channels,
                'resent_by' => $resentBy,
                'resent_at' => now()->toISOString()
            ],
            'created_by' => $resentBy,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Scope for specific invitation logs
     */
    public function scopeForInvitation($query, $invitationId)
    {
        return $query->where('user_invitation_id', $invitationId);
    }

    /**
     * Scope for specific action
     */
    public function scopeForAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific channel
     */
    public function scopeForChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Get recent logs for a user
     */
    public static function getRecentLogsForUser($userId, $limit = 10)
    {
        return self::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get invitation statistics from logs
     */
    public static function getInvitationStats($invitationId): array
    {
        $logs = self::where('user_invitation_id', $invitationId)->get();
        
        return [
            'total_logs' => $logs->count(),
            'successful_sends' => $logs->where('action', 'like', '%_sent')->where('status', 'success')->count(),
            'failed_sends' => $logs->where('action', 'like', '%_sent')->where('status', 'failed')->count(),
            'accepted' => $logs->where('action', 'invitation_accepted')->count() > 0,
            'channels_used' => $logs->where('action', 'like', '%_sent')->pluck('channel')->unique()->values()->toArray(),
            'last_action' => $logs->sortByDesc('created_at')->first()?->action,
            'last_action_time' => $logs->sortByDesc('created_at')->first()?->created_at
        ];
    }
}