<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class UserActivity extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Activity types/events
     */
    public const TYPE_LOGIN = 'login';
    public const TYPE_LOGOUT = 'logout';
    public const TYPE_PROFILE_UPDATE = 'profile_update';
    public const TYPE_PASSWORD_CHANGE = 'password_change';
    public const TYPE_INVITATION_SENT = 'invitation_sent';
    public const TYPE_INVITATION_ACCEPTED = 'invitation_accepted';
    public const TYPE_PROPERTY_REGISTERED = 'property_registered';
    public const TYPE_PLAN_ASSIGNED = 'plan_assigned';
    public const TYPE_PLAN_COMPLETED = 'plan_completed';
    public const TYPE_PAYMENT_MADE = 'payment_made';
    public const TYPE_PAYMENT_RECEIVED = 'payment_received';
    public const TYPE_LEASE_CREATED = 'lease_created';
    public const TYPE_LEASE_UPDATED = 'lease_updated';
    public const TYPE_MAINTENANCE_REQUEST = 'maintenance_request';
    public const TYPE_SECURITY_CHECK = 'security_check';
    public const TYPE_SYSTEM_ACTION = 'system_action';
    public const TYPE_USER_CREATED = 'user_created';
    public const TYPE_USER_UPDATED = 'user_updated';
    public const TYPE_USER_DELETED = 'user_deleted';
    public const TYPE_PHOTO_UPLOADED = 'photo_uploaded';
    public const TYPE_PHONE_VERIFIED = 'phone_verified';
    public const TYPE_EMAIL_VERIFIED = 'email_verified';

    /**
     * Device-tracking type.
     */
    public const TYPE_DEVICE_TRACKED = 'device_tracked';

    /**
     * Fallback used when no specific type is supplied.
     */
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Activity levels
     */
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_SUCCESS = 'success';
    public const LEVEL_CRITICAL = 'critical';

    /**
     * Activity scopes (who can see it)
     */
    public const SCOPE_SYSTEM = 'system'; // System admins only
    public const SCOPE_USER = 'user'; // User themselves
    public const SCOPE_ADMIN = 'admin'; // All admins
    public const SCOPE_PUBLIC = 'public'; // Everyone with access

    /**
     * The table associated with the model.
     */
    protected $table = 'user_activities';

    /**
     * The attributes that are mass assignable.
     *
     * ✅ FIX: 'action' added so the legacy column can be populated through
     *         mass-assignment. The table has both `action` and `activity_type`
     *         and both are NOT NULL without a default — so the model must
     *         write both to satisfy strict mode.
     */
    protected $fillable = [
        'user_id',
        'activity_type',
        'action',           // ✅ FIX — legacy column, mirrored from activity_type
        'description',
        'ip_address',
        'user_agent',
        'device_info',
        'location',
        'metadata',
        'level',
        'scope',
        'performed_by', // Who performed the action (if different from user_id)
        'related_model',
        'related_id',
        'is_system',
        'read_at',
        'archived_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'metadata',
    ];

    /**
     * Default attribute values for new records.
     *
     * ✅ FIX: 'action' added. It mirrors activity_type and satisfies the
     *         legacy NOT NULL constraint on the column without a default.
     */
    protected $attributes = [
        'activity_type' => self::TYPE_UNKNOWN,
        'action'        => self::TYPE_UNKNOWN,   // ✅ FIX
        'level'         => self::LEVEL_INFO,
        'scope'         => self::SCOPE_USER,
        'is_system'     => false,
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * ✅ FIX: Intentionally EMPTY.
     *
     * Every accessor on this model depends on columns that aggregate queries
     * do NOT select. `getActivityStatistics()` returns rows like:
     *
     *     SELECT level, COUNT(*) as count FROM user_activities GROUP BY level
     *     SELECT activity_type, COUNT(*) as count ... GROUP BY activity_type
     *     SELECT DATE(created_at) as date, COUNT(*) as count ... GROUP BY date
     *
     * Those rows have no `level`, no `activity_type`, no `created_at`. When
     * Laravel serialized them, every appended accessor ran and produced:
     *
     *   - PHP 8.5 deprecation warnings ("Using null as an array offset")
     *     from `$icons[$this->activity_type]`, `$colors[$this->level]`, etc.
     *   - Misleading defaults injected into the result set:
     *       type_display: "Unknown Activity"
     *       level_display: "Unknown"
     *       activity_icon: "info"
     *       activity_color: "gray"
     *       is_read: false
     *       is_archived: false
     *
     * The clean fix is to not force-append anything. Every accessor is still
     * available on fully-loaded model instances:
     *
     *     $activity = UserActivity::find($id);
     *     $activity->type_display;      // works
     *     $activity->formatted_date;    // works
     *     $activity->activity_icon;     // works
     *
     * If a specific response needs them on JSON output, append them at the
     * call site:
     *
     *     UserActivity::query()
     *         ->latest()
     *         ->paginate(20)
     *         ->through(fn ($a) => $a->append([
     *             'type_display', 'level_display', 'activity_icon',
     *             'activity_color', 'formatted_date', 'time_ago',
     *         ]));
     *
     * That keeps the aggregate endpoints clean and the detail endpoints rich.
     */
    protected $appends = [];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activity) {
            // ------------------------------------------------------------
            // activity_type — NOT NULL, no default in the DB.
            // ------------------------------------------------------------
            if (empty($activity->activity_type)) {
                $activity->activity_type = self::TYPE_UNKNOWN;
            }

            // ------------------------------------------------------------
            // ✅ FIX: action — NOT NULL, no default in the DB.
            //
            // The table has drifted: it carries both `action` (legacy) and
            // `activity_type` (current). Both are NOT NULL with no default.
            // We mirror activity_type into action so:
            //   - strict mode is satisfied on insert
            //   - old queries filtering on `action` keep working
            //   - no schema migration is required
            // ------------------------------------------------------------
            if (empty($activity->action)) {
                $activity->action = $activity->activity_type;
            }

            // Set default values
            if (empty($activity->level)) {
                $activity->level = self::LEVEL_INFO;
            }

            if (empty($activity->scope)) {
                $activity->scope = self::SCOPE_USER;
            }

            // is_system is boolean — must explicitly handle null vs false
            if (is_null($activity->is_system)) {
                $activity->is_system = false;
            }

            // Set performed_by to current user if not set
            if (empty($activity->performed_by) && Auth::check()) {
                $activity->performed_by = Auth::id();
            }

            // Generate description if not provided
            if (empty($activity->description)) {
                $activity->description = $activity->generateDescription();
            }

            // Add timestamp to metadata
            $metadata = $activity->metadata ?? [];
            if (!is_array($metadata)) {
                $metadata = [];
            }
            $metadata['recorded_at'] = now()->toISOString();
            $metadata['user_agent_parsed'] = $activity->parseUserAgent();
            $activity->metadata = $metadata;
        });
    }

    /**
     * Relationship to the user who performed the activity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship to the user who triggered the activity
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Relationship to related model (polymorphic)
     */
    public function related()
    {
        if ($this->related_model && $this->related_id) {
            return $this->morphTo();
        }
        return null;
    }

    /**
     * Scope for activities by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope for activities by level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for activities by scope
     */
    public function scopeByScope($query, $scope)
    {
        return $query->where('scope', $scope);
    }

    /**
     * Scope for system activities
     */
    public function scopeSystemActivities($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope for user activities (non-system)
     */
    public function scopeUserActivities($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope for unread activities
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for read activities
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope for archived activities
     */
    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Scope for unarchived activities
     */
    public function scopeUnarchived($query)
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope for recent activities (last 30 days)
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for today's activities
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for this week's activities
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope for this month's activities
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    /**
     * Scope for activities for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for activities performed by a specific user
     */
    public function scopePerformedBy($query, $userId)
    {
        return $query->where('performed_by', $userId);
    }

    /**
     * Scope for activities related to a specific model
     */
    public function scopeRelatedTo($query, $model, $id)
    {
        return $query->where('related_model', $model)
                    ->where('related_id', $id);
    }

    /**
     * Mark activity as read
     */
    public function markAsRead(): bool
    {
        if (!$this->read_at) {
            return $this->update(['read_at' => now()]);
        }
        return true;
    }

    /**
     * Mark activity as unread
     */
    public function markAsUnread(): bool
    {
        return $this->update(['read_at' => null]);
    }

    /**
     * Archive activity
     */
    public function archive(): bool
    {
        if (!$this->archived_at) {
            return $this->update(['archived_at' => now()]);
        }
        return true;
    }

    /**
     * Unarchive activity
     */
    public function unarchive(): bool
    {
        return $this->update(['archived_at' => null]);
    }

    /**
     * Check if activity is read
     */
    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if activity is archived
     */
    public function getIsArchivedAttribute(): bool
    {
        return !is_null($this->archived_at);
    }

    /**
     * Get formatted date
     *
     * Null-guarded — safe to call on a partially-loaded model. Returns an
     * empty string if `created_at` wasn't selected.
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->created_at
            ? $this->created_at->format('M j, Y \a\t g:i A')
            : '';
    }

    /**
     * Get time ago
     *
     * Null-guarded, same reasoning as getFormattedDateAttribute.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at
            ? $this->created_at->diffForHumans()
            : '';
    }

    /**
     * Get activity icon based on type
     *
     * Null-guarded: returns 'info' if activity_type wasn't selected.
     */
    public function getActivityIconAttribute(): string
    {
        if ($this->activity_type === null) {
            return 'info';
        }

        $icons = [
            self::TYPE_LOGIN => 'login',
            self::TYPE_LOGOUT => 'logout',
            self::TYPE_PROFILE_UPDATE => 'user-circle',
            self::TYPE_PASSWORD_CHANGE => 'key',
            self::TYPE_INVITATION_SENT => 'mail',
            self::TYPE_INVITATION_ACCEPTED => 'check-circle',
            self::TYPE_PROPERTY_REGISTERED => 'home',
            self::TYPE_PLAN_ASSIGNED => 'assignment',
            self::TYPE_PLAN_COMPLETED => 'check-circle',
            self::TYPE_PAYMENT_MADE => 'payment',
            self::TYPE_PAYMENT_RECEIVED => 'attach-money',
            self::TYPE_LEASE_CREATED => 'description',
            self::TYPE_LEASE_UPDATED => 'edit',
            self::TYPE_MAINTENANCE_REQUEST => 'build',
            self::TYPE_SECURITY_CHECK => 'security',
            self::TYPE_SYSTEM_ACTION => 'settings',
            self::TYPE_USER_CREATED => 'person-add',
            self::TYPE_USER_UPDATED => 'edit',
            self::TYPE_USER_DELETED => 'delete',
            self::TYPE_PHOTO_UPLOADED => 'photo',
            self::TYPE_PHONE_VERIFIED => 'phone',
            self::TYPE_EMAIL_VERIFIED => 'email',
            self::TYPE_DEVICE_TRACKED => 'devices',
            self::TYPE_UNKNOWN => 'info',
        ];

        return $icons[$this->activity_type] ?? 'info';
    }

    /**
     * Get activity color based on level
     *
     * Null-guarded: returns 'gray' if level wasn't selected.
     */
    public function getActivityColorAttribute(): string
    {
        if ($this->level === null) {
            return 'gray';
        }

        $colors = [
            self::LEVEL_INFO => 'blue',
            self::LEVEL_WARNING => 'yellow',
            self::LEVEL_ERROR => 'red',
            self::LEVEL_SUCCESS => 'green',
            self::LEVEL_CRITICAL => 'purple',
        ];

        return $colors[$this->level] ?? 'gray';
    }

    /**
     * Get performer name
     *
     * Null-guarded — the `performer` relation may not be loaded.
     */
    public function getPerformerNameAttribute(): string
    {
        if ($this->performer) {
            return $this->performer->name;
        }

        if ($this->is_system) {
            return 'System';
        }

        return 'Unknown';
    }

    /**
     * Get type display name
     *
     * Null-guarded: returns 'Unknown Activity' if activity_type wasn't selected.
     */
    public function getTypeDisplayAttribute(): string
    {
        if ($this->activity_type === null) {
            return 'Unknown Activity';
        }

        $types = [
            self::TYPE_LOGIN => 'Login',
            self::TYPE_LOGOUT => 'Logout',
            self::TYPE_PROFILE_UPDATE => 'Profile Update',
            self::TYPE_PASSWORD_CHANGE => 'Password Change',
            self::TYPE_INVITATION_SENT => 'Invitation Sent',
            self::TYPE_INVITATION_ACCEPTED => 'Invitation Accepted',
            self::TYPE_PROPERTY_REGISTERED => 'Property Registered',
            self::TYPE_PLAN_ASSIGNED => 'Plan Assigned',
            self::TYPE_PLAN_COMPLETED => 'Plan Completed',
            self::TYPE_PAYMENT_MADE => 'Payment Made',
            self::TYPE_PAYMENT_RECEIVED => 'Payment Received',
            self::TYPE_LEASE_CREATED => 'Lease Created',
            self::TYPE_LEASE_UPDATED => 'Lease Updated',
            self::TYPE_MAINTENANCE_REQUEST => 'Maintenance Request',
            self::TYPE_SECURITY_CHECK => 'Security Check',
            self::TYPE_SYSTEM_ACTION => 'System Action',
            self::TYPE_USER_CREATED => 'User Created',
            self::TYPE_USER_UPDATED => 'User Updated',
            self::TYPE_USER_DELETED => 'User Deleted',
            self::TYPE_PHOTO_UPLOADED => 'Photo Uploaded',
            self::TYPE_PHONE_VERIFIED => 'Phone Verified',
            self::TYPE_EMAIL_VERIFIED => 'Email Verified',
            self::TYPE_DEVICE_TRACKED => 'Device Tracked',
            self::TYPE_UNKNOWN => 'Unknown Activity',
        ];

        return $types[$this->activity_type] ?? 'Unknown Activity';
    }

    /**
     * Get level display name
     *
     * Null-guarded: returns 'Unknown' if level wasn't selected.
     */
    public function getLevelDisplayAttribute(): string
    {
        if ($this->level === null) {
            return 'Unknown';
        }

        $levels = [
            self::LEVEL_INFO => 'Info',
            self::LEVEL_WARNING => 'Warning',
            self::LEVEL_ERROR => 'Error',
            self::LEVEL_SUCCESS => 'Success',
            self::LEVEL_CRITICAL => 'Critical',
        ];

        return $levels[$this->level] ?? 'Unknown';
    }

    /**
     * Generate activity description based on type
     */
    private function generateDescription(): string
    {
        $descriptions = [
            self::TYPE_LOGIN => 'User logged into the system',
            self::TYPE_LOGOUT => 'User logged out of the system',
            self::TYPE_PROFILE_UPDATE => 'User updated their profile',
            self::TYPE_PASSWORD_CHANGE => 'User changed their password',
            self::TYPE_INVITATION_SENT => 'Invitation sent to user',
            self::TYPE_INVITATION_ACCEPTED => 'User accepted invitation and set up account',
            self::TYPE_PROPERTY_REGISTERED => 'User registered a property',
            self::TYPE_PLAN_ASSIGNED => 'Registration plan assigned to user',
            self::TYPE_PLAN_COMPLETED => 'User completed a registration plan',
            self::TYPE_PAYMENT_MADE => 'User made a payment',
            self::TYPE_PAYMENT_RECEIVED => 'User received a payment',
            self::TYPE_LEASE_CREATED => 'User created a lease agreement',
            self::TYPE_LEASE_UPDATED => 'User updated a lease agreement',
            self::TYPE_MAINTENANCE_REQUEST => 'User submitted a maintenance request',
            self::TYPE_SECURITY_CHECK => 'Security check performed',
            self::TYPE_SYSTEM_ACTION => 'System action performed',
            self::TYPE_USER_CREATED => 'User account created',
            self::TYPE_USER_UPDATED => 'User account updated',
            self::TYPE_USER_DELETED => 'User account deleted',
            self::TYPE_PHOTO_UPLOADED => 'User uploaded profile photo',
            self::TYPE_PHONE_VERIFIED => 'User verified phone number',
            self::TYPE_EMAIL_VERIFIED => 'User verified email address',
            self::TYPE_DEVICE_TRACKED => 'Device tracked for user',
            self::TYPE_UNKNOWN => 'Activity recorded',
        ];

        $description = $descriptions[$this->activity_type] ?? 'Activity recorded';

        // Add related model info if available
        if ($this->related_model && $this->related_id) {
            $modelName = class_basename($this->related_model);
            $description .= " ({$modelName} ID: {$this->related_id})";
        }

        return $description;
    }

    /**
     * Parse user agent for device info
     */
    private function parseUserAgent(): array
    {
        if (!$this->user_agent) {
            return [];
        }

        $parsed = [];

        // Simple parsing - in production you might use a library like jenssegers/agent
        $ua = strtolower($this->user_agent);

        // Check for mobile devices
        if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
            $parsed['device_type'] = 'mobile';
        } elseif (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
            $parsed['device_type'] = 'tablet';
        } else {
            $parsed['device_type'] = 'desktop';
        }

        // Check for browser
        if (strpos($ua, 'chrome') !== false) {
            $parsed['browser'] = 'Chrome';
        } elseif (strpos($ua, 'firefox') !== false) {
            $parsed['browser'] = 'Firefox';
        } elseif (strpos($ua, 'safari') !== false && strpos($ua, 'chrome') === false) {
            $parsed['browser'] = 'Safari';
        } elseif (strpos($ua, 'edge') !== false) {
            $parsed['browser'] = 'Edge';
        } elseif (strpos($ua, 'opera') !== false) {
            $parsed['browser'] = 'Opera';
        } else {
            $parsed['browser'] = 'Unknown';
        }

        // Check for OS
        if (strpos($ua, 'windows') !== false) {
            $parsed['os'] = 'Windows';
        } elseif (strpos($ua, 'mac') !== false) {
            $parsed['os'] = 'Mac OS';
        } elseif (strpos($ua, 'linux') !== false) {
            $parsed['os'] = 'Linux';
        } elseif (strpos($ua, 'android') !== false) {
            $parsed['os'] = 'Android';
        } elseif (strpos($ua, 'ios') !== false || strpos($ua, 'iphone') !== false) {
            $parsed['os'] = 'iOS';
        } else {
            $parsed['os'] = 'Unknown';
        }

        return $parsed;
    }

    /**
     * Get all activity types
     */
    public static function getActivityTypes(): array
    {
        return [
            self::TYPE_LOGIN => 'Login',
            self::TYPE_LOGOUT => 'Logout',
            self::TYPE_PROFILE_UPDATE => 'Profile Update',
            self::TYPE_PASSWORD_CHANGE => 'Password Change',
            self::TYPE_INVITATION_SENT => 'Invitation Sent',
            self::TYPE_INVITATION_ACCEPTED => 'Invitation Accepted',
            self::TYPE_PROPERTY_REGISTERED => 'Property Registered',
            self::TYPE_PLAN_ASSIGNED => 'Plan Assigned',
            self::TYPE_PLAN_COMPLETED => 'Plan Completed',
            self::TYPE_PAYMENT_MADE => 'Payment Made',
            self::TYPE_PAYMENT_RECEIVED => 'Payment Received',
            self::TYPE_LEASE_CREATED => 'Lease Created',
            self::TYPE_LEASE_UPDATED => 'Lease Updated',
            self::TYPE_MAINTENANCE_REQUEST => 'Maintenance Request',
            self::TYPE_SECURITY_CHECK => 'Security Check',
            self::TYPE_SYSTEM_ACTION => 'System Action',
            self::TYPE_USER_CREATED => 'User Created',
            self::TYPE_USER_UPDATED => 'User Updated',
            self::TYPE_USER_DELETED => 'User Deleted',
            self::TYPE_PHOTO_UPLOADED => 'Photo Uploaded',
            self::TYPE_PHONE_VERIFIED => 'Phone Verified',
            self::TYPE_EMAIL_VERIFIED => 'Email Verified',
            self::TYPE_DEVICE_TRACKED => 'Device Tracked',
            self::TYPE_UNKNOWN => 'Unknown Activity',
        ];
    }

    /**
     * Get all activity levels
     */
    public static function getActivityLevels(): array
    {
        return [
            self::LEVEL_INFO => 'Info',
            self::LEVEL_WARNING => 'Warning',
            self::LEVEL_ERROR => 'Error',
            self::LEVEL_SUCCESS => 'Success',
            self::LEVEL_CRITICAL => 'Critical',
        ];
    }

    /**
     * Get all activity scopes
     */
    public static function getActivityScopes(): array
    {
        return [
            self::SCOPE_SYSTEM => 'System',
            self::SCOPE_USER => 'User',
            self::SCOPE_ADMIN => 'Admin',
            self::SCOPE_PUBLIC => 'Public',
        ];
    }

    /**
     * Create a new activity record
     */
    public static function record(array $data): self
    {
        return self::create($data);
    }

    /**
     * Record user login activity
     */
    public static function recordLogin(User $user, ?string $ipAddress = null, ?string $userAgent = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_LOGIN,
            'description' => 'User logged into the system',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'level' => self::LEVEL_INFO,
            'scope' => self::SCOPE_SYSTEM,
            'is_system' => true,
            'metadata' => [
                'login_method' => 'password',
                'successful' => true,
            ],
        ]);
    }

    /**
     * Record user logout activity
     */
    public static function recordLogout(User $user, ?string $ipAddress = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_LOGOUT,
            'description' => 'User logged out of the system',
            'ip_address' => $ipAddress,
            'level' => self::LEVEL_INFO,
            'scope' => self::SCOPE_SYSTEM,
            'is_system' => true,
        ]);
    }

    /**
     * Record profile update activity
     */
    public static function recordProfileUpdate(User $user, array $changes, ?User $performer = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_PROFILE_UPDATE,
            'description' => 'User profile updated',
            'level' => self::LEVEL_INFO,
            'scope' => self::SCOPE_USER,
            'performed_by' => $performer ? $performer->id : $user->id,
            'metadata' => [
                'changes' => $changes,
                'fields_updated' => array_keys($changes),
            ],
        ]);
    }

    /**
     * Record password change activity
     */
    public static function recordPasswordChange(User $user, ?User $performer = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_PASSWORD_CHANGE,
            'description' => 'User password changed',
            'level' => self::LEVEL_WARNING,
            'scope' => self::SCOPE_SYSTEM,
            'performed_by' => $performer ? $performer->id : $user->id,
            'metadata' => [
                'changed_by_user' => is_null($performer) || $performer->id === $user->id,
                'forced_change' => $performer && $performer->id !== $user->id,
            ],
        ]);
    }

    /**
     * Record invitation sent activity
     */
    public static function recordInvitationSent(User $user, User $invitedBy, array $channels): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_INVITATION_SENT,
            'description' => 'Invitation sent to user via ' . implode(', ', $channels),
            'level' => self::LEVEL_INFO,
            'scope' => self::SCOPE_ADMIN,
            'performed_by' => $invitedBy->id,
            'metadata' => [
                'channels' => $channels,
                'invited_by' => $invitedBy->name,
                'invited_by_id' => $invitedBy->id,
            ],
        ]);
    }

    /**
     * Record invitation accepted activity
     */
    public static function recordInvitationAccepted(User $user): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_INVITATION_ACCEPTED,
            'description' => 'User accepted invitation and set up account',
            'level' => self::LEVEL_SUCCESS,
            'scope' => self::SCOPE_SYSTEM,
            'is_system' => true,
            'metadata' => [
                'setup_completed' => true,
                'account_activated' => true,
            ],
        ]);
    }

    /**
     * Record property registration activity
     */
    public static function recordPropertyRegistered(User $user, $propertyId, $propertyName): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_PROPERTY_REGISTERED,
            'description' => "User registered property: {$propertyName}",
            'level' => self::LEVEL_SUCCESS,
            'scope' => self::SCOPE_USER,
            'related_model' => Property::class,
            'related_id' => $propertyId,
            'metadata' => [
                'property_id' => $propertyId,
                'property_name' => $propertyName,
            ],
        ]);
    }

    /**
     * Record plan assignment activity
     */
    public static function recordPlanAssigned(User $user, $planId, $planName, User $assignedBy): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_PLAN_ASSIGNED,
            'description' => "Registration plan assigned: {$planName}",
            'level' => self::LEVEL_INFO,
            'scope' => self::SCOPE_USER,
            'performed_by' => $assignedBy->id,
            'related_model' => RegistrationPlan::class,
            'related_id' => $planId,
            'metadata' => [
                'plan_id' => $planId,
                'plan_name' => $planName,
                'assigned_by' => $assignedBy->name,
            ],
        ]);
    }

    /**
     * Record user creation activity
     */
    public static function recordUserCreated(User $user, ?User $createdBy = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_USER_CREATED,
            'description' => "User account created for {$user->name}",
            'level' => self::LEVEL_SUCCESS,
            'scope' => self::SCOPE_SYSTEM,
            'performed_by' => $createdBy ? $createdBy->id : $user->id,
            'metadata' => [
                'user_type' => $user->type_name,
                'created_via' => $user->metadata['created_via'] ?? 'manual',
            ],
        ]);
    }

    /**
     * Record system action activity
     */
    public static function recordSystemAction(string $description, array $metadata = []): self
    {
        return self::create([
            'activity_type' => self::TYPE_SYSTEM_ACTION,
            'description' => $description,
            'level' => self::LEVEL_INFO,
            'scope' => self::SCOPE_SYSTEM,
            'is_system' => true,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record device-tracking activity.
     *
     * Intended for the code path that was previously inserting rows without
     * an activity_type. Callers should prefer this over a raw `create([...])`
     * so the type is always set.
     */
    public static function recordDeviceTracked(
        User $user,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $metadata = []
    ): self {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => self::TYPE_DEVICE_TRACKED,
            'description' => 'Device tracked for user',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'level' => self::LEVEL_INFO,
            'scope' => self::SCOPE_SYSTEM,
            'is_system' => false,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get user's recent activities
     */
    public static function getUserRecentActivities($userId, $limit = 10)
    {
        return self::where('user_id', $userId)
                    ->orWhere('performed_by', $userId)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Get activities for dashboard
     */
    public static function getDashboardActivities($limit = 20)
    {
        return self::with(['user', 'performer'])
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Clean up old activities (older than specified days)
     */
    public static function cleanupOldActivities($days = 90): int
    {
        $date = now()->subDays($days);
        return self::where('created_at', '<', $date)
                    ->where('level', '!=', self::LEVEL_CRITICAL) // Don't delete critical logs
                    ->delete();
    }

    /**
     * Get activity statistics
     *
     * ✅ FIX: The `by_level`, `by_type`, and `daily_activities` sub-queries
     *         return raw rows that lack `activity_type`, `level`, and
     *         `created_at` respectively. Now that `$appends` is empty, those
     *         rows serialize as `{level: count}`, `{activity_type: count}`,
     *         and `{date, count}` — no accessors fire, no warnings, no
     *         misleading "Unknown" defaults.
     */
    public static function getActivityStatistics($days = 30): array
    {
        $startDate = now()->subDays($days);

        return [
            'total_activities' => self::where('created_at', '>=', $startDate)->count(),
            'logins' => self::where('activity_type', self::TYPE_LOGIN)
                          ->where('created_at', '>=', $startDate)
                          ->count(),
            'property_registrations' => self::where('activity_type', self::TYPE_PROPERTY_REGISTERED)
                                          ->where('created_at', '>=', $startDate)
                                          ->count(),
            'payments' => self::whereIn('activity_type', [self::TYPE_PAYMENT_MADE, self::TYPE_PAYMENT_RECEIVED])
                            ->where('created_at', '>=', $startDate)
                            ->count(),
            'invitations_sent' => self::where('activity_type', self::TYPE_INVITATION_SENT)
                                    ->where('created_at', '>=', $startDate)
                                    ->count(),
            'invitations_accepted' => self::where('activity_type', self::TYPE_INVITATION_ACCEPTED)
                                        ->where('created_at', '>=', $startDate)
                                        ->count(),
            'by_level' => self::selectRaw('level, COUNT(*) as count')
                            ->where('created_at', '>=', $startDate)
                            ->groupBy('level')
                            ->pluck('count', 'level')
                            ->toArray(),
            'by_type' => self::selectRaw('activity_type, COUNT(*) as count')
                           ->where('created_at', '>=', $startDate)
                           ->groupBy('activity_type')
                           ->pluck('count', 'activity_type')
                           ->toArray(),
            'daily_activities' => self::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                                    ->where('created_at', '>=', $startDate)
                                    ->groupBy('date')
                                    ->orderBy('date')
                                    ->get()
                                    ->toArray(),
        ];
    }
}