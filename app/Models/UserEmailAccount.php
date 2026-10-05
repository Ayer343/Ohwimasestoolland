<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class UserEmailAccount extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_email_accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'display_name',
        'reply_to_email',
        'reply_to_name',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_validate_cert',
        'imap_timeout',
        'imap_folder',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_validate_cert',
        'smtp_timeout',
        'encrypted_password',
        'auth_method',
        'oauth_tokens',
        'provider',
        'gmail_user_id',
        'gmail_refresh_token',
        'outlook_tenant_id',
        'sync_frequency',
        'max_emails_per_sync',
        'sync_days_back',
        'last_sync_at',
        'next_sync_at',
        'folder_mappings',
        'sync_folders',
        'exclude_folders',
        'status',
        'verified_at',
        'verification_error',
        'verification_attempts',
        'last_verification_attempt_at',
        'daily_send_limit',
        'daily_receive_limit',
        'emails_sent_today',
        'emails_received_today',
        'daily_limit_reset_at',
        'is_primary',
        'enable_auto_reply',
        'auto_reply_message',
        'auto_reply_conditions',
        'enable_signature',
        'signature',
        'notify_on_new_email',
        'notify_on_send_failure',
        'notification_preferences',
        'metadata',
        'settings',
        'is_connected',
        'last_connected_at',
        'last_connection_error',
        'password_changed_at',
        'settings_changed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // ✅ Password handled by Laravel's built-in encrypted cast
        'encrypted_password' => 'encrypted',

        'imap_validate_cert' => 'boolean',
        'smtp_validate_cert' => 'boolean',
        'is_primary' => 'boolean',
        'enable_auto_reply' => 'boolean',
        'enable_signature' => 'boolean',
        'notify_on_new_email' => 'boolean',
        'notify_on_send_failure' => 'boolean',
        'is_connected' => 'boolean',

        'imap_port' => 'integer',
        'smtp_port' => 'integer',
        'imap_timeout' => 'integer',
        'smtp_timeout' => 'integer',
        'max_emails_per_sync' => 'integer',
        'sync_days_back' => 'integer',
        'verification_attempts' => 'integer',
        'daily_send_limit' => 'integer',
        'daily_receive_limit' => 'integer',
        'emails_sent_today' => 'integer',
        'emails_received_today' => 'integer',

        'oauth_tokens' => 'array',
        'folder_mappings' => 'array',
        'sync_folders' => 'array',
        'exclude_folders' => 'array',
        'auto_reply_conditions' => 'array',
        'notification_preferences' => 'array',
        'metadata' => 'array',
        'settings' => 'array',

        'verified_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'next_sync_at' => 'datetime',
        'last_verification_attempt_at' => 'datetime',
        'daily_limit_reset_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'settings_changed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'encrypted_password',
    ];

    /**
     * ✅ FIX: No appends.
     *
     * The previous list (`has_password`, `is_verified`, `formatted_status`,
     * `provider_name`, `connection_status`) was forcing every accessor to run
     * during JSON serialization. One of them was hitting a deprecation warning
     * ("Using null as an array offset") when the account was loaded with a
     * restricted select — Laravel silently dropped the whole account from the
     * JSON response.
     *
     * The accessors still exist as methods you can call explicitly:
     *   $account->getFormattedStatus();
     *   $account->getProviderName();
     *   $account->getConnectionStatus();
     *   $account->has_password;      // accessor still works via property
     *   $account->is_verified;       // accessor still works via property
     *
     * They just aren't auto-appended to every JSON payload.
     *
     * @var array<int, string>
     */
    protected $appends = [];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user that owns the email account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the emails associated with this account.
     */
    public function emails()
{
    return $this->hasMany(Email::class, 'user_email_account_id');
}

    /**
     * Get the email folders for this account.
     */
    public function folders(): HasMany
    {
        return $this->hasMany(EmailFolder::class);
    }

    /**
     * Get the email attachments.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    // ==================== ACCESSORS & MUTATORS ====================

    /**
     * ✅ SAFE ACCESSOR: Get the decrypted password without throwing on legacy/plain values.
     */
    public function getDecryptedPassword(): ?string
    {
        $value = $this->attributes['encrypted_password'] ?? null;

        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // Legacy plain-text value — return as-is
            Log::warning('Password is stored in plain text (legacy). Consider re-saving to encrypt.', [
                'account_id' => $this->id,
                'email'      => $this->attributes['email'] ?? null,
            ]);
            return $value;
        }
    }

    /**
     * ✅ SAFE MUTATOR: Encrypt password when assigning, idempotent for already-encrypted values.
     */
    public function setPassword(?string $plainPassword): self
    {
        if (empty($plainPassword)) {
            $this->attributes['encrypted_password'] = null;
            return $this;
        }

        // If already encrypted, store as-is
        try {
            Crypt::decryptString($plainPassword);
            $this->attributes['encrypted_password'] = $plainPassword;
            return $this;
        } catch (\Exception $e) {
            // Not encrypted — encrypt it
        }

        $this->attributes['encrypted_password'] = Crypt::encryptString($plainPassword);
        return $this;
    }

    /**
     * Get the raw (encrypted) password value directly from the database.
     */
    public function getRawPasswordAttribute(): ?string
    {
        return $this->attributes['encrypted_password'] ?? null;
    }

    /**
     * ✅ Accessor: Check if the account has a password set.
     * Reads from raw attributes so it doesn't trigger decryption.
     *
     * Usage:  $account->has_password
     */
    public function getHasPasswordAttribute(): bool
    {
        return !empty($this->attributes['encrypted_password']);
    }

    /**
     * ✅ Accessor: Check if the account is verified.
     *
     * Usage:  $account->is_verified
     */
    public function getIsVerifiedAttribute(): bool
    {
        $status = $this->attributes['status'] ?? null;
        return $status === 'verified';
    }

    /**
     * ✅ Accessor: Get formatted status with badge color.
     *
     * Usage:  $account->formatted_status
     */
    public function getFormattedStatusAttribute(): array
    {
        $status = $this->attributes['status'] ?? 'pending';

        $statuses = [
            'pending'   => ['label' => 'Pending',   'color' => 'warning'],
            'verified'  => ['label' => 'Verified',  'color' => 'success'],
            'failed'    => ['label' => 'Failed',    'color' => 'danger'],
            'suspended' => ['label' => 'Suspended', 'color' => 'danger'],
            'expired'   => ['label' => 'Expired',   'color' => 'secondary'],
        ];

        return $statuses[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary'];
    }

    /**
     * ✅ Accessor: Get the provider name.
     * Null-safe — works even if `provider` wasn't selected.
     *
     * Usage:  $account->provider_name
     */
    public function getProviderNameAttribute(): string
    {
        $key = $this->attributes['provider'] ?? '';

        $providers = [
            'gmail'   => 'Gmail',
            'outlook' => 'Outlook/Hotmail',
            'yahoo'   => 'Yahoo Mail',
            'custom'  => 'Custom',
        ];

        return $providers[$key] ?? 'Unknown';
    }

    /**
     * ✅ Accessor: Get the connection status.
     *
     * Usage:  $account->connection_status
     */
    public function getConnectionStatusAttribute(): array
    {
        $isConnected = (bool) ($this->attributes['is_connected'] ?? false);

        return [
            'is_connected'          => $isConnected,
            'last_connected_at'     => $this->attributes['last_connected_at'] ?? null,
            'last_connection_error' => $this->attributes['last_connection_error'] ?? null,
            'status'                => $isConnected ? 'Connected' : 'Disconnected',
        ];
    }

    /**
     * ✅ Accessor: Get the sync frequency label.
     *
     * Usage:  $account->sync_frequency_label
     */
    public function getSyncFrequencyLabelAttribute(): string
    {
        $freq = $this->attributes['sync_frequency'] ?? '';

        $frequencies = [
            'realtime'               => 'Real-time',
            'every_minute'           => 'Every Minute',
            'every_five_minutes'     => 'Every 5 Minutes',
            'every_fifteen_minutes'  => 'Every 15 Minutes',
            'every_thirty_minutes'   => 'Every 30 Minutes',
            'hourly'                 => 'Hourly',
            'manual'                 => 'Manual',
        ];

        return $frequencies[$freq] ?? 'Unknown';
    }

    /**
     * ✅ Accessor: Check if IMAP settings are configured.
     *
     * Usage:  $account->has_imap_settings
     */
    public function getHasImapSettingsAttribute(): bool
    {
        return !empty($this->attributes['imap_host'])
            && !empty($this->attributes['imap_port']);
    }

    /**
     * ✅ Accessor: Check if SMTP settings are configured.
     *
     * Usage:  $account->has_smtp_settings
     */
    public function getHasSmtpSettingsAttribute(): bool
    {
        return !empty($this->attributes['smtp_host'])
            && !empty($this->attributes['smtp_port']);
    }

    // ==================== SCOPES ====================

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeConnected($query)
    {
        return $query->where('is_connected', true);
    }

    public function scopeNeedsSync($query)
    {
        return $query->where('status', 'verified')
                     ->where('is_connected', true)
                     ->where(function ($q) {
                         $q->whereNull('next_sync_at')
                           ->orWhere('next_sync_at', '<=', now());
                     });
    }

    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeSyncFrequency($query, string $frequency)
    {
        return $query->where('sync_frequency', $frequency);
    }

    // ==================== METHODS ====================

    /**
     * Check if the account can send emails.
     */
    public function canSendEmails(): bool
    {
        return $this->status === 'verified'
               && $this->is_connected
               && $this->has_smtp_settings
               && $this->emails_sent_today < $this->daily_send_limit;
    }

    /**
     * Check if the account can receive emails.
     */
    public function canReceiveEmails(): bool
    {
        return $this->status === 'verified'
               && $this->is_connected
               && $this->has_imap_settings
               && $this->emails_received_today < $this->daily_receive_limit;
    }

    /**
     * Increment the emails sent counter.
     */
    public function incrementEmailsSent(int $count = 1): bool
    {
        $this->emails_sent_today += $count;

        if ($this->daily_limit_reset_at && $this->daily_limit_reset_at->isPast()) {
            $this->emails_sent_today = $count;
            $this->daily_limit_reset_at = now()->addDay();
        }

        return $this->save();
    }

    /**
     * Increment the emails received counter.
     */
    public function incrementEmailsReceived(int $count = 1): bool
    {
        $this->emails_received_today += $count;

        if ($this->daily_limit_reset_at && $this->daily_limit_reset_at->isPast()) {
            $this->emails_received_today = $count;
            $this->daily_limit_reset_at = now()->addDay();
        }

        return $this->save();
    }

    /**
     * Update connection status.
     */
    public function updateConnectionStatus(bool $connected, ?string $error = null): bool
    {
        $this->is_connected = $connected;
        $this->last_connected_at = $connected ? now() : $this->last_connected_at;
        $this->last_connection_error = $error;

        return $this->save();
    }

    /**
     * Update sync timestamp.
     */
    public function updateSyncTimestamp(): bool
    {
        $this->last_sync_at = now();

        $interval = match($this->sync_frequency) {
            'realtime'               => 1,
            'every_minute'           => 1,
            'every_five_minutes'     => 5,
            'every_fifteen_minutes'  => 15,
            'every_thirty_minutes'   => 30,
            'hourly'                 => 60,
            'manual'                 => null,
            default                  => 5,
        };

        if ($interval) {
            $this->next_sync_at = now()->addMinutes($interval);
        }

        return $this->save();
    }

    /**
     * Verify or re-verify the account.
     */
    public function verify(?bool $success = null, ?string $error = null): bool
    {
        $this->verification_attempts += 1;
        $this->last_verification_attempt_at = now();

        if ($success === true) {
            $this->status = 'verified';
            $this->verified_at = now();
            $this->verification_error = null;
            $this->is_connected = true;
            $this->last_connected_at = now();
        } elseif ($success === false) {
            $this->status = 'failed';
            $this->verification_error = $error;
            $this->is_connected = false;
            $this->last_connection_error = $error;
        }

        return $this->save();
    }

    /**
     * Check if the account is at daily send limit.
     */
    public function isAtSendLimit(): bool
    {
        return $this->emails_sent_today >= $this->daily_send_limit;
    }

    /**
     * Check if the account is at daily receive limit.
     */
    public function isAtReceiveLimit(): bool
    {
        return $this->emails_received_today >= $this->daily_receive_limit;
    }

    /**
     * Get remaining daily send quota.
     */
    public function getRemainingSendQuota(): int
    {
        return max(0, $this->daily_send_limit - $this->emails_sent_today);
    }

    /**
     * Get remaining daily receive quota.
     */
    public function getRemainingReceiveQuota(): int
    {
        return max(0, $this->daily_receive_limit - $this->emails_received_today);
    }

    /**
     * Reset daily counters.
     */
    public function resetDailyCounters(): bool
    {
        $this->emails_sent_today = 0;
        $this->emails_received_today = 0;
        $this->daily_limit_reset_at = now()->addDay();

        return $this->save();
    }

    /**
     * Check if daily counters should be reset.
     */
    public function shouldResetDailyCounters(): bool
    {
        if (!$this->daily_limit_reset_at) {
            return true;
        }

        return $this->daily_limit_reset_at->isPast();
    }

    /**
     * Get the encryption status.
     */
    public function getEncryptionStatus(): array
    {
        return [
            'imap_encryption' => $this->imap_encryption,
            'imap_secure'     => in_array($this->imap_encryption, ['ssl', 'tls']),
            'smtp_encryption' => $this->smtp_encryption,
            'smtp_secure'     => in_array($this->smtp_encryption, ['ssl', 'tls']),
        ];
    }

    /**
     * Get account health status.
     */
    public function getHealthStatus(): array
    {
        $issues = [];
        $warnings = [];

        if (!$this->is_connected) {
            $issues[] = 'Account is disconnected';
        }

        if ($this->status !== 'verified') {
            $issues[] = 'Account is not verified';
        }

        if ($this->isAtSendLimit()) {
            $warnings[] = 'Daily send limit reached';
        }

        if ($this->isAtReceiveLimit()) {
            $warnings[] = 'Daily receive limit reached';
        }

        if ($this->last_sync_at && $this->last_sync_at->diffInHours(now()) > 24) {
            $warnings[] = 'Last sync was over 24 hours ago';
        }

        if ($this->password_changed_at && $this->password_changed_at->diffInDays(now()) > 90) {
            $warnings[] = 'Password is over 90 days old';
        }

        return [
            'status'       => empty($issues) ? 'healthy' : 'unhealthy',
            'issues'       => $issues,
            'warnings'     => $warnings,
            'is_healthy'   => empty($issues),
            'has_warnings' => !empty($warnings),
        ];
    }

    /**
     * Get default sync folders.
     */
    public function getDefaultSyncFolders(): array
    {
        return $this->sync_folders ?? ['INBOX', 'SENT', 'DRAFTS'];
    }

    /**
     * Get IMAP configuration array.
     */
    public function getImapConfig(): array
    {
        return [
            'host'          => $this->imap_host,
            'port'          => $this->imap_port,
            'encryption'    => $this->imap_encryption,
            'validate_cert' => $this->imap_validate_cert,
            'username'      => $this->email,
            'password'      => $this->getDecryptedPassword(),
            'protocol'      => 'imap',
            'timeout'       => $this->imap_timeout,
        ];
    }

    /**
     * Get SMTP configuration array.
     */
    public function getSmtpConfig(): array
    {
        return [
            'host'          => $this->smtp_host,
            'port'          => $this->smtp_port,
            'encryption'    => $this->smtp_encryption,
            'validate_cert' => $this->smtp_validate_cert,
            'username'      => $this->email,
            'password'      => $this->getDecryptedPassword(),
            'timeout'       => $this->smtp_timeout,
        ];
    }

    // ==================== EVENT HANDLING ====================

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::retrieved(function ($model) {
            if ($model->shouldResetDailyCounters()) {
                $model->resetDailyCounters();
            }
        });
    }
}