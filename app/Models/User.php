<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\HasSupervisorAssignments;
use Illuminate\Support\Facades\Log; 

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasSupervisorAssignments;

    // ==================== CONSTANTS ====================
    
    // User Type Constants (Legacy - kept for backward compatibility)
    public const TYPE_SUPER_ADMIN = 0;
    public const TYPE_ADMIN = 1;
    public const TYPE_LANDLORD = 2;
    public const TYPE_TENANT = 3;
    public const TYPE_FIELD_AGENT = 4;
    public const TYPE_DEVELOPER = 5;
    public const TYPE_SECURITY_PERSONNEL = 6;
    public const TYPE_FORMER_LANDLORD = 7;
    public const TYPE_CONTRACTOR = 8;
    public const TYPE_SANITATION_PERSONNEL = 9;

    // Security Supervisor Level Constants
    public const SUPERVISOR_LEVEL_NONE = 0;
    public const SUPERVISOR_LEVEL_TEAM_LEAD = 1;
    public const SUPERVISOR_LEVEL_SECTION_LEAD = 2;
    public const SUPERVISOR_LEVEL_POST_COMMANDER = 3;

    // User status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_VERIFICATION_REQUIRED = 'verification_required';
    public const STATUS_ARCHIVED = 'archived';

    // Phone verification status
    public const PHONE_VERIFIED = 'verified';
    public const PHONE_PENDING_VERIFICATION = 'pending';
    public const PHONE_UNVERIFIED = 'unverified';

    // Phone number constants
    public const PHONE_COUNTRY_CODE = '+233';
    public const PHONE_LOCAL_PREFIX = '0';
    public const PHONE_DIGITS_LENGTH = 9;

    // Photo constants
    public const PHOTO_DISK = 'public';
    public const PHOTO_DIRECTORY = 'users/photos';
    public const PHOTO_THUMBNAIL_WIDTH = 150;
    public const PHOTO_THUMBNAIL_HEIGHT = 150;
    public const PHOTO_MAX_SIZE = 5120;

    // ==================== FILLABLE ATTRIBUTES ====================

    protected $fillable = [
        'name', 'email', 'email_verified_at', 'password', 'phone',
        'digital_address', 'region', 'location', 'photo', 'gender', 'dob',
        'type', 'created_by', 'username', 'last_activity_at',
        'status', 'invitation_accepted_at', 'phone_verified_at',
        'last_invitation_sent_at', 'invitation_method', 'temp_password',
        'verification_code', 'verification_code_sent_at',
        'phone_verification_code', 'phone_verification_sent_at',
        'last_login_at', 'last_login_ip', 'user_agent', 'metadata',
        'supervisor_level', 'supervisor_score', 'can_be_supervisor',
        'supervisor_certifications', 'archived_at', 'last_property_ownership',
        'deletion_scheduled_at', 'can_login', 'api_access', 'registration_ip',
    ];

    // ==================== HIDDEN ATTRIBUTES ====================

    protected $hidden = [
        'password', 'remember_token', 'temp_password', 'verification_code',
        'phone_verification_code', 'metadata',
    ];

    // ==================== CASTS ====================

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'type' => 'integer',
        'last_activity_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_invitation_sent_at' => 'datetime',
        'verification_code_sent_at' => 'datetime',
        'phone_verification_sent_at' => 'datetime',
        'last_login_at' => 'datetime',
        'status' => 'string',
        'metadata' => 'array',
        'dob' => 'date',
        'supervisor_level' => 'integer',
        'supervisor_score' => 'integer',
        'can_be_supervisor' => 'boolean',
        'supervisor_certifications' => 'array',
        'archived_at' => 'datetime',
        'last_property_ownership' => 'datetime',
        'deletion_scheduled_at' => 'datetime',
        'can_login' => 'boolean',
        'api_access' => 'boolean',
    ];

    // ==================== ROLE MAPPING (Legacy) ====================

    protected static array $roleMapping = [
        self::TYPE_SUPER_ADMIN => "super-admin",
        self::TYPE_ADMIN => "admin",
        self::TYPE_LANDLORD => "landlord",
        self::TYPE_TENANT => "tenant",
        self::TYPE_FIELD_AGENT => "field-agent",
        self::TYPE_DEVELOPER => "developer",
        self::TYPE_SECURITY_PERSONNEL => "security-personnel",
        self::TYPE_FORMER_LANDLORD => "former-landlord",
        self::TYPE_CONTRACTOR => "contractor",
        self::TYPE_SANITATION_PERSONNEL => "sanitation-personnel",
    ];

    // ==================== DEFAULT ATTRIBUTES ====================

    protected $attributes = [
        'type' => self::TYPE_TENANT,
        'status' => self::STATUS_ACTIVE,
        'supervisor_level' => self::SUPERVISOR_LEVEL_NONE,
        'supervisor_score' => 0,
        'can_be_supervisor' => false,
        'can_login' => true,
        'api_access' => true,
    ];

    // ==================== APPENDED ATTRIBUTES ====================

    protected $appends = [
        'role_name', 'is_invitation_pending', 'can_accept_invitation',
        'phone_verification_status', 'field_agent_stats', 'is_verified_agent',
        'status_with_color', 'type_name', 'is_phone_verified',
        'has_accepted_invitation', 'invitation_status', 'performance_metrics',
        'photo_url', 'photo_thumbnail_url', 'has_photo', 'avatar_url',
        'initials', 'local_phone', 'phone_formats', 'all_phones',
        'has_valid_invitation', 'latest_invitation_status', 'can_set_password',
        'invitation_url', 'supervisor_level_name', 'is_security_supervisor',
        'supervisor_permissions', 'supervised_posts', 'is_archived',
        'archival_info', 'can_be_restored', 'all_roles', 'primary_role',
        'role_names', 'role_slugs', 'primary_role_name',
    ];

    // ==================== BOOT METHOD ====================

protected static function boot()
{
    parent::boot();

    static::creating(function ($user) {
        // Set default status based on user type
        if ($user->type === self::TYPE_FIELD_AGENT && empty($user->status)) {
            $user->status = self::STATUS_PENDING;
        }
        
        // Generate username for field agents if not provided
        if ($user->type === self::TYPE_FIELD_AGENT && empty($user->username)) {
            $user->username = $user->generateAgentUsername();
        }

        // Standardize phone number before saving
        if (!empty($user->phone)) {
            $user->phone = $user->standardizePhoneNumber($user->phone);
        }

        // Set default metadata
        if (empty($user->metadata)) {
            $user->metadata = [
                'created_via' => 'manual',
                'registration_source' => 'web',
                'initial_status' => $user->status,
                'password_history' => [],
                'change_log' => [],
                'total_logins' => 0,
                'phone_standardized' => true,
                'invitation_method' => $user->invitation_method ?? null,
                'supervisor_assigned_at' => null,
                'supervisor_promotion_history' => [],
            ];
        }
    });

    static::updating(function ($user) {
        // Standardize phone number if being updated
        if ($user->isDirty('phone') && !empty($user->phone)) {
            $user->phone = $user->standardizePhoneNumber($user->phone);
            
            $user->metadata = array_merge($user->metadata ?? [], [
                'phone_last_updated' => now()->toISOString(),
                'previous_phone_formats' => $user->metadata['previous_phone_formats'] ?? []
            ]);
        }

        // Handle user activation
        if ($user->isDirty('status') && $user->status === self::STATUS_ACTIVE) {
            $user->invitations()
                ->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])
                ->where('expires_at', '>', now())
                ->update([
                    'accepted_at' => now(),
                    'status' => UserInvitation::STATUS_ACCEPTED
                ]);
        }

        // Auto-activate field agent when they accept invitation
        if ($user->isDirty('invitation_accepted_at') && 
            !is_null($user->invitation_accepted_at) &&
            $user->type === self::TYPE_FIELD_AGENT) {
            $user->status = self::STATUS_ACTIVE;
            $user->email_verified_at = $user->email_verified_at ?? now();
        }

        // Track login activity
        if ($user->isDirty('last_login_at')) {
            $user->metadata = array_merge($user->metadata ?? [], [
                'last_login_tracked_at' => now()->toISOString(),
                'total_logins' => ($user->metadata['total_logins'] ?? 0) + 1
            ]);
        }

        // Track supervisor level changes
        if ($user->isDirty('supervisor_level') && $user->supervisor_level > $user->getOriginal('supervisor_level')) {
            $history = $user->metadata['supervisor_promotion_history'] ?? [];
            $history[] = [
                'old_level' => $user->getOriginal('supervisor_level'),
                'new_level' => $user->supervisor_level,
                'promoted_at' => now()->toISOString(),
                'promoted_by' => auth()->id(),
                'promoted_by_name' => auth()->user()->name ?? 'System',
            ];
            $user->metadata = array_merge($user->metadata ?? [], [
                'supervisor_promotion_history' => $history,
                'supervisor_promoted_at' => now()->toISOString(),
            ]);
        }
    });

    static::deleting(function ($user) {
        // ✅ Cancel all pending invitations when user is being deleted
        try {
            $invitationCount = $user->invitations()
                ->whereNull('accepted_at')
                ->whereIn('status', ['pending', 'sent'])
                ->count();
            
            if ($invitationCount > 0) {
                $user->invitations()
                    ->whereNull('accepted_at')
                    ->whereIn('status', ['pending', 'sent'])
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancellation_reason' => 'User account deleted',
                        'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.cancelled_by', " . (auth()->id() ?? 0) . ", '$.cancelled_by_name', '" . addslashes(auth()->user()->name ?? 'System') . "', '$.cancelled_at', '" . now()->toISOString() . "', '$.cancellation_reason', 'User account deleted')")
                    ]);
                
                \Log::info('Cancelled pending invitations during user deletion', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'invitation_count' => $invitationCount,
                    'deleted_by' => auth()->id() ?? null
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to cancel invitations during user deletion', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        // Handle force delete specific cleanup
        if ($user->isForceDeleting()) {
            $user->deletePhoto();
            // Remove all role assignments
            if (method_exists($user, 'roleAssignments')) {
                $user->roleAssignments()->delete();
            }
        }
    });

    static::forceDeleted(function ($user) {
        $user->deletePhoto();
    });

    // ✅ Add restored event to handle invitation cleanup on restore
    static::restored(function ($user) {
        // Clean up any cancelled invitations that are older than 30 days
        try {
            $user->invitations()
                ->where('status', 'cancelled')
                ->where('cancelled_at', '<', now()->subDays(30))
                ->delete();
        } catch (\Exception $e) {
            \Log::warning('Failed to clean up old cancelled invitations', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    });
}

    // ==================== ROLE RELATIONSHIPS ====================

    /**
     * ✅ Get the contracts associated with this user (as a contractor).
     */
    public function contracts()
    {
        return $this->hasMany(ConstructionContract::class, 'contractor_user_id');
    }

    /**
     * Get the sanitation personnel record associated with this user.
     */
    public function sanitationPersonnel()
    {
        return $this->hasOne(SanitationPersonnel::class);
    }

    /**
     * Get waste collection requests made by this user (as landlord/tenant).
     */
    public function wasteCollectionRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class, 'requested_by');
    }

    /**
     * Get waste collection requests assigned to this user's sanitation personnel.
     */
    public function assignedWasteRequests()
    {
        return $this->hasManyThrough(
            WasteCollectionRequest::class,
            SanitationPersonnel::class,
            'user_id',
            'assigned_to',
            'id',
            'id'
        );
    }

    /**
     * Get properties that have requested waste collection.
     */
    public function propertiesWithWasteRequests()
    {
        return $this->hasManyThrough(
            Property::class,
            WasteCollectionRequest::class,
            'requested_by',
            'id',
            'id',
            'property_id'
        );
    }

    /**
     * ✅ Get the contracts this user has as a landlord.
     */
    public function landlordContracts()
    {
        return $this->hasMany(ConstructionContract::class, 'landlord_id');
    }

    /**
     * ✅ Get the construction workers added by this user.
     */
    public function constructionWorkers()
    {
        return $this->hasMany(ConstructionWorker::class, 'added_by');
    }

    /**
     * ✅ Get the construction workers assigned to this user.
     */
    public function assignedWorkers()
    {
        return $this->hasMany(ConstructionWorker::class, 'contractor_id');
    }

    /**
     * ✅ Get the worker badges associated with this user.
     */
    public function workerBadges()
    {
        return $this->hasManyThrough(
            WorkerBadge::class,
            ConstructionWorker::class,
            'contractor_id',
            'construction_worker_id',
            'id',
            'id'
        );
    }
    
    /**
     * Get all role assignments for this user
     */
    public function roleAssignments()
    {
        return $this->hasMany(RoleUser::class)->with('role');
    }

    /**
     * Get all email accounts for this user.
     */
    public function emailAccounts()
    {
        return $this->hasMany(UserEmailAccount::class);
    }

    /**
     * Get the user's primary email account.
     */
    public function primaryEmailAccount()
    {
        return $this->hasOne(UserEmailAccount::class)->where('is_primary', true);
    }

    /**
     * Get the user's verified email accounts.
     */
    public function verifiedEmailAccounts()
    {
        return $this->hasMany(UserEmailAccount::class)->where('status', 'verified');
    }

    /**
     * Check if user has any email account linked.
     */
    public function hasEmailAccount(): bool
    {
        return $this->emailAccounts()->exists();
    }

    /**
     * Check if user has a verified email account.
     */
    public function hasVerifiedEmailAccount(): bool
    {
        return $this->emailAccounts()->where('status', 'verified')->exists();
    }

    /**
 * Get active role assignments (not expired, not revoked)
 */
public function activeRoleAssignments()
{
    return $this->hasMany(RoleUser::class)
        ->whereNull('role_user.deleted_at')
        ->where('role_user.is_active', true)
        // Remove this line: ->where('role_user.status', 'active')
        ->where(function($q) {
            $q->whereNull('role_user.expires_at')
              ->orWhere('role_user.expires_at', '>', now());
        })
        ->with('role');
}

    /**
     * Get all roles (through active assignments)
     */
    /**
 * Get all roles (through active assignments)
 */
public function roles()
{
    return $this->belongsToMany(Role::class, 'role_user')
        ->using(RoleUser::class)
        ->withPivot([
            'id', 'assigned_by', 'assigned_at', 'expires_at',
            'is_active', 'metadata', 'permissions_override'
        ])
        ->wherePivotNull('role_user.deleted_at')
        ->wherePivot('is_active', true)
        // Remove this line: ->wherePivot('status', 'active')
        ->where(function($q) {
            $q->whereNull('role_user.expires_at')
              ->orWhere('role_user.expires_at', '>', now());
        });
}

    /**
     * Get all roles (including inactive/expired)
     */
    public function allRolesWithTrashed()
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->using(RoleUser::class)
            ->withPivot(['status', 'expires_at', 'is_active', 'deleted_at'])
            ->withTrashed();
    }

    // ==================== ROLE HELPER METHODS ====================

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(array $roleSlugs): bool
    {
        $userRoles = $this->roles()->pluck('slug')->toArray();
        return !array_diff($roleSlugs, $userRoles);
    }

    /**
     * Get user's primary role (highest priority)
     */
    public function getPrimaryRoleAttribute(): ?Role
    {
        return $this->roles()->orderBy('priority', 'desc')->first();
    }

    /**
     * Get user's primary role name attribute
     */
    public function getPrimaryRoleNameAttribute(): string
    {
        $primaryRole = $this->getPrimaryRoleAttribute();
        return $primaryRole ? $primaryRole->display_name : ($this->getTypeName() ?? 'User');
    }

    /**
     * Get all role slugs as array
     */
    public function getRoleSlugsAttribute(): array
    {
        return $this->roles()->pluck('slug')->toArray();
    }

    /**
     * Get all role names as string
     */
    public function getRoleNamesAttribute(): string
    {
        return $this->roles()->pluck('name')->implode(', ');
    }

    /**
     * Get all roles as collection for API
     */
    public function getAllRolesAttribute(): array
    {
        return $this->roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'priority' => $role->priority,
                'assigned_at' => $role->pivot->assigned_at,
                'expires_at' => $role->pivot->expires_at,
            ];
        })->toArray();
    }

    /**
     * ✅ NEW: Ensure user has landlord role (for multi-role users)
     * This method checks if user has landlord role and assigns if missing
     */
    public function ensureLandlordRole(?int $assignedBy = null, string $reason = 'Property ownership'): bool
    {
        // Already has landlord role
        if ($this->hasRole('landlord')) {
            \Log::info('User already has landlord role', [
                'user_id' => $this->id,
                'user_name' => $this->name,
                'existing_roles' => $this->getRoleSlugsAttribute()
            ]);
            return true;
        }
        
        // Check legacy type
        if ($this->type === self::TYPE_LANDLORD) {
            \Log::info('User has legacy landlord type - adding role', [
                'user_id' => $this->id,
                'user_name' => $this->name
            ]);
        }
        
        try {
            // Assign landlord role
            $this->assignRole('landlord', [
                'assigned_by' => $assignedBy ?? auth()->id(),
                'assigned_at' => now(),
                'assignment_reason' => $reason,
                'notes' => 'Landlord role automatically assigned due to property ownership',
                'metadata' => [
                    'assigned_via' => 'ensure_landlord_role_method',
                    'user_type' => $this->type,
                    'user_roles_before' => $this->getRoleSlugsAttribute()
                ]
            ]);
            
            \Log::info('Landlord role assigned to multi-role user', [
                'user_id' => $this->id,
                'user_name' => $this->name,
                'user_type' => $this->type,
                'assigned_by' => $assignedBy ?? auth()->id(),
                'new_roles' => $this->getRoleSlugsAttribute()
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error('Failed to assign landlord role', [
                'user_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * ✅ NEW: Check if user is a property owner (has any properties)
     */
    public function isPropertyOwner(): bool
    {
        return $this->properties()->exists() || $this->landlordProperties()->exists();
    }

    /**
     * ✅ NEW: Get all properties owned by this user (regardless of role)
     */
    public function ownedProperties()
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    /**
     * ✅ NEW: Check if user already owns a property with similar details
     */
    public function ownsSimilarProperty(array $propertyData): bool
    {
        $query = $this->ownedProperties();
        
        if (!empty($propertyData['digital_address'])) {
            return $query->where('digital_address', $propertyData['digital_address'])->exists();
        }
        
        if (!empty($propertyData['street_name']) && !empty($propertyData['house_number'])) {
            return $query->where('street_name', $propertyData['street_name'])
                ->where('house_number', $propertyData['house_number'])
                ->exists();
        }
        
        if (!empty($propertyData['property_name'])) {
            return $query->where('property_name', 'LIKE', '%' . $propertyData['property_name'] . '%')
                ->exists();
        }
        
        return false;
    }

   /**
 * Override the assignRole method to handle dynamic role assignment
 */
public function assignRole($role, array $options = []): RoleUser
{
    // Find role by ID or slug
    $roleModel = is_numeric($role) 
        ? Role::findOrFail($role) 
        : Role::where('slug', $role)->firstOrFail();
    
    // Check if already has this role (active)
    $existing = $this->roleAssignments()
        ->where('role_id', $roleModel->id)
        ->whereNull('deleted_at')
        ->first();
        
    if ($existing && $existing->isCurrentlyActive()) {
        \Log::info('User already has active role', [
            'user_id' => $this->id,
            'role' => $roleModel->name
        ]);
        return $existing;
    }
    
    if ($existing) {
        // Reactivate existing assignment
        $existing->update([
            'is_active' => true,
            'status' => 'active',
            'expires_at' => $options['expires_at'] ?? null,
            'updated_at' => now(),
        ]);
        $assignment = $existing;
    } else {
        // Create new assignment
        $assignment = $this->roleAssignments()->create([
            'role_id' => $roleModel->id,
            'assigned_by' => $options['assigned_by'] ?? auth()->id(),
            'assigned_at' => $options['assigned_at'] ?? now(),
            'expires_at' => $options['expires_at'] ?? null,
            'is_active' => true,
            'status' => 'active',
            'metadata' => $options['metadata'] ?? [],
            'permissions_override' => $options['permissions_override'] ?? [],
            'notes' => $options['notes'] ?? null,
        ]);
    }
    
    // After assigning role, sync legacy type if needed
    $this->syncLegacyTypeFromRoles();
    
    // Clear permission cache
    $this->clearPermissionCache();
    
    return $assignment;
}

    /**
 * Override the removeRole method to handle dynamic role removal
 */
public function removeRole($role): bool
{
    $roleModel = is_numeric($role) 
        ? Role::find($role) 
        : Role::where('slug', $role)->first();
        
    if (!$roleModel) return false;
    
    // Prevent removing landlord role if user still owns properties
    if ($roleModel->slug === 'landlord' && $this->isPropertyOwner()) {
        \Log::warning('Cannot remove landlord role - user still owns properties', [
            'user_id' => $this->id,
            'property_count' => $this->ownedProperties()->count()
        ]);
        throw new \Exception('Cannot remove landlord role. User still owns properties. Please transfer ownership first.');
    }
    
    $assignment = $this->roleAssignments()
        ->where('role_id', $roleModel->id)
        ->first();
        
    if ($assignment) {
        $result = $assignment->revoke('Removed by user/admin');
        
        // After removing role, sync legacy type if needed
        $this->syncLegacyTypeFromRoles();
        
        // Clear permission cache
        $this->clearPermissionCache();
        
        return $result;
    }
    
    return false;
}

    /**
     * Sync user roles (remove old, add new)
     */
    public function syncRoles(array $roleSlugs): array
    {
        $results = [
            'added' => [],
            'removed' => [],
            'kept' => [],
        ];
        
        // Get current role slugs
        $currentRoles = $this->getRoleSlugsAttribute();
        
        // Roles to remove
        $toRemove = array_diff($currentRoles, $roleSlugs);
        foreach ($toRemove as $slug) {
            try {
                $this->removeRole($slug);
                $results['removed'][] = $slug;
            } catch (\Exception $e) {
                \Log::warning('Could not remove role during sync', [
                    'user_id' => $this->id,
                    'role' => $slug,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Roles to add
        $toAdd = array_diff($roleSlugs, $currentRoles);
        foreach ($toAdd as $slug) {
            $this->assignRole($slug);
            $results['added'][] = $slug;
        }
        
        // Roles kept
        $results['kept'] = array_intersect($currentRoles, $roleSlugs);
        
        // Clear permission cache
        $this->clearPermissionCache();
        
        return $results;
    }

    /**
     * Clear user's permission cache
     */
    public function clearPermissionCache(): void
{
    $indexKey = "user_permissions_index_{$this->id}";
    $index = cache()->get($indexKey, []);

    foreach ($index as $cacheKey) {
        cache()->forget($cacheKey);
    }

    cache()->forget($indexKey);
}

    // ==================== PERMISSION METHODS ====================

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
{
    $cacheKey = "user_permission_{$this->id}_{$permission}";
    $indexKey = "user_permissions_index_{$this->id}";

    return cache()->remember($cacheKey, 3600, function () use ($permission, $indexKey, $cacheKey) {
        // Track this key so clearPermissionCache() can find it later.
        $index = cache()->get($indexKey, []);
        if (!in_array($cacheKey, $index, true)) {
            $index[] = $cacheKey;
            cache()->put($indexKey, $index, 3600);
        }

        foreach ($this->roles as $role) {
            if (in_array($permission, $role->permissions ?? [])) {
                return true;
            }
            $assignment = $this->roleAssignments()
                ->where('role_id', $role->id)
                ->first();
            if ($assignment && in_array($permission, $assignment->permissions_override ?? [])) {
                return true;
            }
        }
        return false;
    });
}


    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Relationship to the user who created this one.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

     /**
     * Get all activities for this user.
     */
    public function activities()
    {
        return $this->hasMany(Activity::class)->latest();
    }
    
    /**
     * Get recent activities for this user.
     */
    public function recentActivities($limit = 10)
    {
        return $this->activities()->limit($limit)->get();
    }

    
    /**
     * Alias for properties() - maintains backward compatibility
     */
    public function properties()
{
    return $this->landlordProperties();
}

    /**
     * Relationship to payments (for landlords)
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'landlord_id');
    }

    /**
     * Relationship to rentals (for tenants)
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class, 'tenant_id');
    }

    /**
     * Relationship to rental agreements (alias for rentals)
     */
    public function rentalAgreements()
    {
        return $this->rentals();
    }

    /**
     * Relationship to rental agreements as landlord
     */
    public function landlordRentalAgreements()
    {
        return $this->hasMany(RentalAgreement::class, 'landlord_id');
    }

    /**
     * Relationship to created rental agreements
     */
    public function createdRentalAgreements()
    {
        return $this->hasMany(RentalAgreement::class, 'created_by');
    }

    /**
     * Relationship to created properties (for super admin)
     */
    public function createdProperties()
    {
        return $this->hasMany(Property::class, 'created_by');
    }

    /**
     * Relationship with UserInvitation model
     */
    public function invitations()
{
    // This is a HasMany relationship - DO NOT add ->get() here
    return $this->hasMany(UserInvitation::class, 'user_id');
}

    /**
     * Get latest invitation
     */
    public function latestInvitation()
    {
        return $this->hasOne(UserInvitation::class)->latest();
    }
    
    /**
     * Relationship to tenant invoices
     */
    public function tenantInvoices()
    {
        return $this->hasMany(TenantInvoice::class, 'tenant_id');
    }

    /**
     * Get pending invitation
     */
    public function pendingInvitation()
    {
        return $this->hasOne(UserInvitation::class)
                    ->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])
                    ->where('expires_at', '>', now())
                    ->latest();
    }

    /**
     * Relationship to security schedules (for security personnel)
     */
    public function schedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'security_user_id');
    }

    /**
     * Relationship to assigned security schedules
     */
    public function assignedSchedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'assigned_by');
    }

    /**
     * Relationship to approved security schedules
     */
    public function approvedSchedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'approved_by');
    }

    /**
     * Relationship to supervisor assignments
     */
    public function supervisorAssignments()
    {
        return $this->hasMany(SecuritySupervisorAssignment::class, 'user_id');
    }

    /**
     * Get active supervisor assignments
     */
    public function activeSupervisorAssignments()
    {
        return $this->supervisorAssignments()
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }
    
    /**
 * Check if user has any active supervisor assignments
 */
public function hasActiveSupervisorAssignment(): bool
{
    return $this->activeSupervisorAssignments()->exists();
}

    /**
     * Relationship with landlord phones
     */
    public function phones()
    {
        return $this->hasMany(LandlordPhone::class, 'landlord_id');
    }

    /**
     * Relationship to assigned registration plans through pivot table
     */
    public function assignedPlans()
{
    // This is a BelongsToMany relationship - DO NOT add ->get() here
    return $this->belongsToMany(RegistrationPlan::class, 'plan_agent_assignments', 'agent_id', 'plan_id')
                ->withPivot([
                    'id', 'assigned_by', 'assigned_at', 'is_active', 'removed_at', 
                    'removal_reason', 'properties_registered', 'last_activity_at',
                    'created_at', 'updated_at'
                ])
                ->withTimestamps()
                ->wherePivot('is_active', true);
}

/**
 * Reports relationship — DISABLED until the `reports` table + model exist.
 *
 * The original code speculatively referenced App\Models\Report, which
 * doesn't exist in this project (Schema::hasTable('reports') === false).
 * We return an empty relation so existing callers keep working without
 * throwing "Class App\Models\Report not found".
 *
 * To re-enable: create the reports migration + App\Models\Report,
 * then restore:
 *
 *     return $this->hasMany(Report::class, 'agent_id');
 */
public function reports()
{
    // Point at an existing model (PlanAgentAssignment) so Laravel doesn't
    // try to instantiate the missing App\Models\Report. The whereRaw
    // clause guarantees the relation always returns zero rows.
    return $this->hasMany(PlanAgentAssignment::class, 'agent_id')
                ->whereRaw('0 = 1');
}

/**
 * Get pending reports count
 *
 * Reports aren't built yet — always returns 0. When the Report model
 * is created, replace with:
 *
 *     return $this->reports()->where('status', 'pending')->count();
 */
public function getPendingReportsCountAttribute()
{
    return 0;
}

    /**
     * Relationship to all plan assignments (including inactive)
     */
    public function planAssignments()
    {
        return $this->hasMany(PlanAgentAssignment::class, 'agent_id');
    }

    /**
     * Relationship to active plan assignments
     */
    public function activePlanAssignments()
{
    return $this->hasMany(PlanAgentAssignment::class, 'agent_id')
        ->where('is_active', true);
}

    /**
     * Relationship to completed plans
     */
    public function completedPlans()
    {
        return $this->belongsToMany(RegistrationPlan::class, 'plan_agent_assignments', 'agent_id', 'plan_id')
                    ->withPivot('properties_registered', 'assigned_at')
                    ->where('registration_plans.status', RegistrationPlan::STATUS_COMPLETED);
    }

    /**
     * Relationship to active plans
     */
    public function activePlans()
    {
        return $this->belongsToMany(RegistrationPlan::class, 'plan_agent_assignments', 'agent_id', 'plan_id')
                    ->withPivot('properties_registered', 'last_activity_at')
                    ->where('plan_agent_assignments.is_active', true)
                    ->whereIn('registration_plans.status', [
                        RegistrationPlan::STATUS_ASSIGNED, 
                        RegistrationPlan::STATUS_IN_PROGRESS
                    ]);
    }

    /**
     * Relationship to overdue plans
     */
    public function overduePlans()
    {
        return $this->belongsToMany(RegistrationPlan::class, 'plan_agent_assignments', 'agent_id', 'plan_id')
                    ->withPivot('properties_registered', 'assigned_at')
                    ->where('plan_agent_assignments.is_active', true)
                    ->where('registration_plans.registration_end_date', '<', now())
                    ->whereNotIn('registration_plans.status', [
                        RegistrationPlan::STATUS_COMPLETED, 
                        RegistrationPlan::STATUS_CANCELLED
                    ]);
    }

    /**
     * Relationship to registered properties (for field agents)
     */
    public function registeredProperties()
{
    // This is a HasMany relationship - DO NOT add ->get() here
    return $this->hasMany(Property::class, 'registered_by');
}

    /**
     * Relationship to tenant properties through pivot table
     */
    public function tenantProperties()
    {
        return $this->belongsToMany(Property::class, 'property_tenant', 'user_id', 'property_id')
                    ->withPivot('added_by', 'added_at', 'notes', 'status', 'start_date', 'end_date')
                    ->withTimestamps();
    }

    /**
     * Relationship with property units (for tenants)
     */
    public function propertyUnits()
    {
        return $this->hasMany(PropertyUnit::class, 'tenant_id');
    }

    /**
     * Relationship to invoices (for tenants)
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'tenant_id');
    }

    /**
     * Relationship to testimonials submitted by this user
     */
    public function testimonials()
    {
        return $this->hasMany(Testimonial::class, 'user_id');
    }

    /**
     * Relationship to approved testimonials
     */
    public function approvedTestimonials()
    {
        return $this->hasMany(Testimonial::class, 'user_id')->where('is_approved', true);
    }

    /**
     * Relationship to featured testimonials
     */
    public function featuredTestimonials()
    {
        return $this->hasMany(Testimonial::class, 'user_id')->where('is_featured', true);
    }

    // ==================== ACCESSOR METHODS ====================

    /**
     * Get role name from legacy type
     */
    public function getRoleName(): string
    {
        return self::$roleMapping[$this->attributes['type'] ?? self::TYPE_TENANT] ?? "unknown";
    }

    /**
     * Get role name attribute (legacy)
     */
    public function getRoleNameAttribute(): string
    {
        return $this->getRoleName();
    }

    /**
     * Get type name for display (legacy)
     */
    public function getTypeNameAttribute(): string
{
    $typeNames = [
        self::TYPE_SUPER_ADMIN => 'Super Admin',
        self::TYPE_ADMIN => 'Admin',
        self::TYPE_LANDLORD => 'Landlord',
        self::TYPE_TENANT => 'Tenant',
        self::TYPE_FIELD_AGENT => 'Field Agent',
        self::TYPE_DEVELOPER => 'Developer',
        self::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
        self::TYPE_FORMER_LANDLORD => 'Former Landlord',
        self::TYPE_CONTRACTOR => 'Contractor',
        self::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',   // ✅ NEW
    ];

    return $typeNames[$this->attributes['type'] ?? self::TYPE_TENANT] ?? 'Unknown';
}

    /**
     * Get the user's type name (method version for compatibility with TestimonialController)
     * 
     * @return string
     */
    public function getTypeName(): string
{
    $typeNames = [
        self::TYPE_SUPER_ADMIN => 'Super Administrator',
        self::TYPE_ADMIN => 'Administrator',
        self::TYPE_LANDLORD => 'Landlord',
        self::TYPE_TENANT => 'Tenant',
        self::TYPE_FIELD_AGENT => 'Field Agent',
        self::TYPE_DEVELOPER => 'Developer',
        self::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
        self::TYPE_FORMER_LANDLORD => 'Former Landlord',
        self::TYPE_CONTRACTOR => 'Contractor',
        self::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',   // ✅ NEW
    ];

    return $typeNames[$this->type] ?? 'User';
}

    /**
     * Check if user needs to set up their password.
     * This method is used to determine if a user should be redirected to password setup page.
     * 
     * @return bool
     */
    public function needsPasswordSetup(): bool
    {
        // Check if user has a pending invitation that requires password setup
        if ($this->has_valid_invitation && $this->status === self::STATUS_PENDING) {
            return true;
        }

        // Check if user has no password set (e.g., social login or invited users)
        if (empty($this->password)) {
            return true;
        }

        // Check if user is using a temporary password
        if ($this->temp_password === true) {
            return true;
        }

        // Check if user has a flag requiring password setup
        $metadata = $this->metadata ?? [];
        if (isset($metadata['requires_password_setup']) && $metadata['requires_password_setup'] === true) {
            return true;
        }

        // For field agents who haven't completed invitation
        if ($this->isFieldAgent() && is_null($this->invitation_accepted_at)) {
            return true;
        }

        // Force password change for first login
        if ($this->last_login_at === null && $this->login_count === 0) {
            return true;
        }

        // Default to false - user doesn't need password setup
        return false;
    }

    /**
     * Get status with color for UI
     */
    public function getStatusWithColorAttribute(): array
    {
        $statusColors = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_ACTIVE => 'success', 
            self::STATUS_SUSPENDED => 'danger',
            self::STATUS_INACTIVE => 'secondary',
            self::STATUS_VERIFICATION_REQUIRED => 'info',
            self::STATUS_ARCHIVED => 'dark',
        ];

        $statusLabels = [
            self::STATUS_PENDING => 'Pending Invitation',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_VERIFICATION_REQUIRED => 'Verification Required',
            self::STATUS_ARCHIVED => 'Archived',
        ];

        $currentStatus = $this->attributes['status'] ?? self::STATUS_ACTIVE;
        
        if (is_null($currentStatus) || !array_key_exists($currentStatus, $statusLabels)) {
            $currentStatus = self::STATUS_ACTIVE;
        }

        $color = $statusColors[$currentStatus] ?? 'secondary';
        
        return [
            'status' => $currentStatus,
            'label' => $statusLabels[$currentStatus] ?? ucfirst($currentStatus),
            'color' => $color,
            'badge_class' => 'badge bg-' . $color,
        ];
    }

    /**
     * Check if user has valid invitation
     */
    public function getHasValidInvitationAttribute(): bool
    {
        return $this->pendingInvitation()->exists();
    }

    /**
     * Get latest invitation status
     */
    public function getLatestInvitationStatusAttribute(): array
    {
        $invitation = $this->latestInvitation;
        
        if (!$invitation) {
            return ['status' => 'none', 'message' => 'No invitations sent'];
        }

        return [
            'status' => $invitation->status,
            'message' => $this->getInvitationStatusMessage($invitation->status),
            'invitation_type' => $invitation->invitation_type,
            'sent_at' => $invitation->sent_at?->toISOString(),
            'expires_at' => $invitation->expires_at?->toISOString(),
            'accepted_at' => $invitation->accepted_at?->toISOString(),
            'channels' => $invitation->channels,
        ];
    }

    /**
     * Get invitation status message
     */
    private function getInvitationStatusMessage($status): string
    {
        $messages = [
            UserInvitation::STATUS_PENDING => 'Invitation is pending',
            UserInvitation::STATUS_SENT => 'Invitation has been sent',
            UserInvitation::STATUS_ACCEPTED => 'Invitation was accepted',
            UserInvitation::STATUS_EXPIRED => 'Invitation has expired',
            UserInvitation::STATUS_FAILED => 'Invitation failed to send',
            UserInvitation::STATUS_CANCELLED => 'Invitation was cancelled',
        ];

        return $messages[$status] ?? 'Unknown invitation status';
    }

    /**
     * Check if user can set password (has valid invitation)
     */
    public function getCanSetPasswordAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               $this->has_valid_invitation;
    }

    /**
     * Get invitation URL for backward compatibility
     */
    public function getInvitationUrl(): ?string
    {
        $invitation = $this->pendingInvitation;
        return $invitation ? $invitation->getInvitationUrl() : null;
    }

    /**
     * Get invitation URL attribute
     */
    public function getInvitationUrlAttribute(): ?string
    {
        return $this->getInvitationUrl();
    }

    /**
     * Check if user is a security supervisor for any post
     */
    public function isSecuritySupervisor(): bool
    {
        if (!$this->isSecurityPersonnel()) {
            return false;
        }
        
        return $this->activeSupervisorAssignments()->exists();
    }

    /**
     * Get is_security_supervisor attribute
     */
    public function getIsSecuritySupervisorAttribute(): bool
    {
        return $this->isSecuritySupervisor();
    }

    /**
     * Get supervisor level name
     */
    public function getSupervisorLevelNameAttribute(): string
    {
        if (!$this->isSecurityPersonnel()) {
            return 'Not Applicable';
        }
        
        $levels = [
            self::SUPERVISOR_LEVEL_NONE => 'Security Personnel',
            self::SUPERVISOR_LEVEL_TEAM_LEAD => 'Team Lead',
            self::SUPERVISOR_LEVEL_SECTION_LEAD => 'Section Lead',
            self::SUPERVISOR_LEVEL_POST_COMMANDER => 'Post Commander',
        ];
        
        return $levels[$this->supervisor_level] ?? 'Unknown';
    }

    /**
     * Get all supervisor permissions for this user
     */
    public function getSupervisorPermissionsAttribute(): array
    {
        if (!$this->isSecurityPersonnel()) {
            return [];
        }
        
        $permissions = [];
        $assignments = $this->activeSupervisorAssignments()->get();
        
        foreach ($assignments as $assignment) {
            $permissions = array_merge($permissions, $assignment->permissions ?? []);
        }
        
        return array_unique($permissions);
    }

    /**
     * Get posts this user supervises
     */
    public function getSupervisedPostsAttribute()
    {
        if (!$this->isSecurityPersonnel()) {
            return collect();
        }
        
        $postIds = $this->activeSupervisorAssignments()
            ->pluck('security_post_id')
            ->unique();
        
        return SecurityPost::whereIn('id', $postIds)->get();
    }

    /**
     * Check if invitation is pending
     */
    public function getIsInvitationPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->has_valid_invitation;
    }

    /**
     * Check if user can accept invitation
     */
    public function getCanAcceptInvitationAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->has_valid_invitation;
    }

    /**
     * Check if user has accepted invitation
     */
    public function getHasAcceptedInvitationAttribute(): bool
    {
        return !is_null($this->invitation_accepted_at);
    }

    /**
     * Get invitation status
     */
    public function getInvitationStatusAttribute(): string
    {
        if (!is_null($this->invitation_accepted_at)) {
            return 'accepted';
        }

        if ($this->status === self::STATUS_PENDING && $this->has_valid_invitation) {
            return 'pending';
        }

        return 'not_applicable';
    }

    /**
     * Get phone number in local format (without country code)
     */
    public function getLocalPhoneAttribute(): string
    {
        if (empty($this->phone)) {
            return 'Not set';
        }
        
        if (preg_match('/^\+233(\d{9})$/', $this->phone, $matches)) {
            return '0' . $matches[1];
        }
        
        return $this->phone;
    }

    /**
     * Get all possible phone formats for login compatibility
     */
    public function getPhoneFormatsAttribute(): array
    {
        if (empty($this->phone)) {
            return [];
        }

        $formats = [];
        
        if (preg_match('/^\+233(\d{9})$/', $this->phone, $matches)) {
            $nineDigits = $matches[1];
            
            $formats = [
                '+233' . $nineDigits,
                '233' . $nineDigits,
                '0' . $nineDigits,
                $nineDigits,
            ];
        } else {
            $normalized = $this->standardizePhoneNumber($this->phone);
            if ($normalized !== $this->phone) {
                return $this->getPhoneFormatsAttribute();
            }
            $formats = [$this->phone];
        }

        return array_unique($formats);
    }

    /**
     * Get all phone numbers including additional ones
     */
    public function getAllPhonesAttribute(): array
    {
        $phones = [];
        
        if (!empty($this->phone)) {
            $phones[] = [
                'phone_number' => $this->phone,
                'local_format' => $this->local_phone,
                'is_primary' => true,
                'type' => 'primary'
            ];
        }
        
        if (method_exists($this, 'phones')) {
            $additionalPhones = $this->phones()->get();
            foreach ($additionalPhones as $phone) {
                $phones[] = [
                    'phone_number' => $phone->phone_number,
                    'local_format' => $this->convertToLocalFormat($phone->phone_number),
                    'is_primary' => false,
                    'type' => 'additional',
                    'id' => $phone->id
                ];
            }
        }
        
        return $phones;
    }

    /**
     * Get phone verification status
     */
    public function getPhoneVerificationStatusAttribute(): array
    {
        $verified = !is_null($this->phone_verified_at);
        
        return [
            'verified' => $verified,
            'status' => $verified ? self::PHONE_VERIFIED : self::PHONE_UNVERIFIED,
            'verified_at' => $this->phone_verified_at?->format('M j, Y g:i A'),
            'phone' => $this->phone,
            'local_phone' => $this->local_phone,
            'needs_verification' => $this->isFieldAgent() && !$verified,
            'can_send_code' => $this->canSendVerificationCode(),
            'wait_time' => $this->getVerificationWaitTime(),
        ];
    }

    /**
 * Update additional phone numbers for the user
 * This method syncs the additional phones stored in the phones relationship
 * 
 * @param array $phones Array of phone numbers (including primary)
 * @return $this
 */
public function updatePhones(array $phones): self
{
    // Remove empty values and standardize
    $phones = array_filter($phones, function($phone) {
        return !empty(trim($phone));
    });
    
    $phones = array_map(function($phone) {
        return $this->standardizePhoneNumber($phone);
    }, $phones);
    
    // Primary phone is already set on the user record
    $primaryPhone = $this->phone;
    
    // Filter out the primary phone from additional phones
    $additionalPhones = array_filter($phones, function($phone) use ($primaryPhone) {
        return $phone !== $primaryPhone;
    });
    
    // Get existing additional phones
    $existingPhones = $this->phones()->get();
    $existingPhoneNumbers = $existingPhones->pluck('phone_number')->toArray();
    
    // Phones to add (in additionalPhones but not in existing)
    $phonesToAdd = array_diff($additionalPhones, $existingPhoneNumbers);
    
    // Phones to remove (in existing but not in additionalPhones)
    $phonesToRemove = array_diff($existingPhoneNumbers, $additionalPhones);
    
    // Add new phones
    foreach ($phonesToAdd as $phone) {
        $this->phones()->create([
            'phone_number' => $phone,
            'is_verified' => false,
            'is_primary' => false,
            'created_by' => auth()->id(),
        ]);
        
        Log::info('Additional phone added for user', [
            'user_id' => $this->id,
            'phone' => $phone
        ]);
    }
    
    // Remove phones that are no longer needed
    if (!empty($phonesToRemove)) {
        $this->phones()->whereIn('phone_number', $phonesToRemove)->delete();
        
        Log::info('Additional phones removed for user', [
            'user_id' => $this->id,
            'removed_phones' => $phonesToRemove
        ]);
    }
    
    // Update metadata to track phone changes
    $metadata = $this->metadata ?? [];
    $metadata['phone_history'] = $metadata['phone_history'] ?? [];
    $metadata['phone_history'][] = [
        'action' => 'update_phones',
        'primary_phone' => $primaryPhone,
        'additional_phones' => array_values($additionalPhones),
        'timestamp' => now()->toISOString(),
        'updated_by' => auth()->id(),
    ];
    
    // Keep only last 10 phone history entries
    if (count($metadata['phone_history']) > 10) {
        $metadata['phone_history'] = array_slice($metadata['phone_history'], -10);
    }
    
    $this->metadata = $metadata;
    $this->saveQuietly();
    
    Log::info('User phones updated successfully', [
        'user_id' => $this->id,
        'name' => $this->name,
        'primary_phone' => $primaryPhone,
        'additional_phones_count' => count($additionalPhones),
        'total_phones' => count($phones)
    ]);
    
    return $this;
}

/**
 * Get all phone numbers (primary + additional) as array
 * 
 * @return array
 */
public function getAllPhoneNumbers(): array
{
    $phones = [];
    
    if ($this->phone) {
        $phones[] = [
            'phone_number' => $this->phone,
            'local_format' => $this->local_phone,
            'is_primary' => true,
            'is_verified' => !is_null($this->phone_verified_at),
        ];
    }
    
    foreach ($this->phones as $phone) {
        $phones[] = [
            'phone_number' => $phone->phone_number,
            'local_format' => $this->convertToLocalFormat($phone->phone_number),
            'is_primary' => $phone->is_primary ?? false,
            'is_verified' => $phone->is_verified ?? false,
            'id' => $phone->id,
        ];
    }
    
    return $phones;
}

/**
 * Add an additional phone number
 * 
 * @param string $phone
 * @param bool $isVerified
 * @return \App\Models\LandlordPhone|null
 */
public function addPhone(string $phone, bool $isVerified = false)
{
    $phone = $this->standardizePhoneNumber($phone);
    
    // Check if phone already exists (as primary or additional)
    if ($this->phone === $phone) {
        Log::warning('Cannot add phone that is already the primary phone', [
            'user_id' => $this->id,
            'phone' => $phone
        ]);
        return null;
    }
    
    if ($this->phones()->where('phone_number', $phone)->exists()) {
        Log::warning('Phone number already exists for user', [
            'user_id' => $this->id,
            'phone' => $phone
        ]);
        return null;
    }
    
    return $this->phones()->create([
        'phone_number' => $phone,
        'is_verified' => $isVerified,
        'is_primary' => false,
        'created_by' => auth()->id(),
    ]);
}

/**
 * Remove an additional phone number
 * 
 * @param string $phone
 * @return bool
 */
public function removePhone(string $phone): bool
{
    $phone = $this->standardizePhoneNumber($phone);
    
    // Cannot remove primary phone this way
    if ($this->phone === $phone) {
        Log::warning('Cannot remove primary phone using removePhone method', [
            'user_id' => $this->id,
            'phone' => $phone
        ]);
        return false;
    }
    
    $deleted = $this->phones()->where('phone_number', $phone)->delete();
    
    if ($deleted) {
        Log::info('Additional phone removed', [
            'user_id' => $this->id,
            'phone' => $phone
        ]);
    }
    
    return $deleted;
}

/**
 * Verify an additional phone number
 * 
 * @param string $phone
 * @return bool
 */
public function verifyPhoneNumber(string $phone): bool
{
    $phone = $this->standardizePhoneNumber($phone);
    
    // Check primary phone
    if ($this->phone === $phone) {
        return $this->markPhoneVerified();
    }
    
    // Check additional phones
    $phoneRecord = $this->phones()->where('phone_number', $phone)->first();
    
    if ($phoneRecord) {
        return $phoneRecord->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }
    
    return false;
}


    /**
     * Check if phone is verified
     */
    public function getIsPhoneVerifiedAttribute(): bool
    {
        return !is_null($this->phone_verified_at);
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
        $completedAssignments = $this->completedPlans()->count();

        $totalPropertiesRegistered = $this->planAssignments()->sum('properties_registered');

        return [
            'is_field_agent' => true,
            'total_assignments_count' => $totalAssignments,
            'active_assignments_count' => $activeAssignments,
            'completed_assignments_count' => $completedAssignments,
            'active_plans_count' => $this->activePlans()->count(),
            'completed_plans_count' => $completedAssignments,
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
     * Get photo URL attribute
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->getPhotoUrl();
    }

    /**
     * Get photo thumbnail URL attribute
     */
    public function getPhotoThumbnailUrlAttribute(): ?string
    {
        return $this->getPhotoUrl();
    }

    /**
     * Check if user has photo
     */
    public function getHasPhotoAttribute(): bool
    {
        return !empty($this->photo) && Storage::disk(self::PHOTO_DISK)->exists(self::PHOTO_DIRECTORY . '/' . $this->photo);
    }

    /**
     * Get user's initials for avatar
     */
    public function getInitialsAttribute(): string
    {
        return $this->getInitials();
    }

    /**
     * Get user's avatar URL (photo or initials-based)
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->has_photo && $this->photo_url) {
            return $this->photo_url;
        }

        $initials = urlencode($this->initials);
        $backgroundColor = '4f46e5';
        $textColor = 'ffffff';
        
        return "https://ui-avatars.com/api/?name={$initials}&background={$backgroundColor}&color={$textColor}&size=150&bold=true&font-size=0.8";
    }

    /**
     * Check if user is archived
     */
    public function getIsArchivedAttribute(): bool
    {
        return $this->isArchived();
    }

    /**
     * Get archival information
     */
    public function getArchivalInfoAttribute(): ?array
    {
        if (!$this->isArchived()) {
            return null;
        }
        
        $archivedData = $this->metadata['archived'] ?? [];
        
        return [
            'is_archived' => true,
            'archived_at' => $this->archived_at?->toISOString(),
            'archived_reason' => $archivedData['archived_reason'] ?? 'unknown',
            'archived_by' => $archivedData['archived_by'] ?? null,
            'original_email' => $archivedData['original_data']['email'] ?? null,
            'original_type' => $archivedData['original_user_type'] ?? null,
            'deletion_scheduled_at' => $this->deletion_scheduled_at?->toISOString(),
            'days_since_archival' => $this->archived_at ? $this->archived_at->diffInDays(now()) : null,
        ];
    }

    /**
     * Check if user can be restored
     */
    public function getCanBeRestoredAttribute(): bool
    {
        return $this->isArchived();
    }

    /**
     * Get user's testimonial statistics
     */
    public function getTestimonialStatsAttribute(): array
    {
        return [
            'total' => $this->testimonials()->count(),
            'approved' => $this->testimonials()->where('is_approved', true)->count(),
            'pending' => $this->testimonials()->where('is_approved', false)->count(),
            'featured' => $this->testimonials()->where('is_featured', true)->count(),
            'average_rating' => $this->testimonials()->where('is_approved', true)->avg('rating') ?? 0,
            'latest' => $this->testimonials()->latest()->first(),
        ];
    }

    // ==================== PHONE NUMBER METHODS ====================

    /**
     * Standardize phone number to +233 format
     */
    public function standardizePhoneNumber($phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            return self::PHONE_COUNTRY_CODE . $matches[1];
        }
        
        if (preg_match('/^(\d{9})$/', $phone, $matches)) {
            return self::PHONE_COUNTRY_CODE . $matches[1];
        }
        
        if (preg_match('/^233(\d{9})$/', $phone, $matches)) {
            return self::PHONE_COUNTRY_CODE . $matches[1];
        }
        
        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return $phone;
        }
        
        return $phone;
    }

    /**
     * Set phone attribute with automatic standardization
     */
    public function setPhoneAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['phone'] = $this->standardizePhoneNumber($value);
        } else {
            $this->attributes['phone'] = $value;
        }
    }

    /**
     * Validate phone number format
     */
    public static function isValidPhoneNumber($phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        $phone = preg_replace('/[^\d+]/', '', $phone);

        if (preg_match('/^\+233(\d{9})$/', $phone)) {
            return true;
        }

        if (preg_match('/^233(\d{9})$/', $phone)) {
            return true;
        }

        if (preg_match('/^0(\d{9})$/', $phone)) {
            return true;
        }

        if (preg_match('/^(\d{9})$/', $phone)) {
            return true;
        }

        return false;
    }

    /**
     * Find user by any phone format
     */
    public static function findByAnyPhoneFormat($phone)
    {
        if (empty($phone)) {
            return null;
        }

        $user = new static();
        $standardizedPhone = $user->standardizePhoneNumber($phone);

        $foundUser = static::where('phone', $standardizedPhone)->first();
        if ($foundUser) {
            return $foundUser;
        }

        $phoneDigits = $user->extractPhoneDigitsFromInput($phone);
        if ($phoneDigits) {
            $possiblePhones = [
                '+233' . $phoneDigits,
                '233' . $phoneDigits,
                '0' . $phoneDigits,
                $phoneDigits
            ];

            return static::whereIn('phone', $possiblePhones)->first();
        }

        return null;
    }

    /**
     * Extract 9-digit number from any input format
     */
    private function extractPhoneDigitsFromInput($phone): ?string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);

        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^233(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(\d{9})$/', $phone, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Find user by phone number (alias for findByAnyPhoneFormat)
     */
    public static function findByPhone($phone)
    {
        return static::findByAnyPhoneFormat($phone);
    }

    /**
     * Check if user can send verification code
     */
    public function canSendVerificationCode(): bool
    {
        if (!$this->phone_verification_sent_at) {
            return true;
        }

        return $this->phone_verification_sent_at->addMinutes(5)->isPast();
    }

    /**
     * Get verification wait time in seconds
     */
    public function getVerificationWaitTime(): int
    {
        if (!$this->phone_verification_sent_at) {
            return 0;
        }

        $nextAvailable = $this->phone_verification_sent_at->addMinutes(5);
        $now = now();

        if ($nextAvailable->isPast()) {
            return 0;
        }

        return $nextAvailable->diffInSeconds($now);
    }

    /**
     * Send phone verification code
     */
    public function sendPhoneVerificationCode(): bool
    {
        if (!$this->phone) {
            return false;
        }

        if (!$this->canSendVerificationCode()) {
            return false;
        }

        try {
            $code = sprintf('%06d', random_int(1, 999999));
            
            $this->update([
                'phone_verification_code' => $code,
                'phone_verification_sent_at' => now(),
            ]);

            \Log::info("Phone verification code generated", [
                'user_id' => $this->id,
                'phone' => $this->phone,
                'local_phone' => $this->local_phone,
                'code_sent_at' => now()->toDateTimeString()
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to send phone verification code: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify phone with code
     */
    public function verifyPhone($code): bool
    {
        if (!$this->phone_verification_code || 
            !$this->phone_verification_sent_at ||
            $this->phone_verification_code !== $code) {
            return false;
        }

        if ($this->phone_verification_sent_at->addMinutes(15)->isPast()) {
            return false;
        }

        return $this->update([
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_sent_at' => null,
        ]);
    }

    /**
     * Mark phone as verified (admin manual verification)
     */
    public function markPhoneVerified(): bool
    {
        return $this->update([
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_sent_at' => null,
        ]);
    }

    // ==================== FIELD AGENT METHODS ====================

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
     * Generate agent username
     */
    private function generateAgentUsername(): string
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
     * Complete invitation process by setting password
     */
    public function completeInvitation($password): bool
    {
        if (!$this->has_valid_invitation) {
            return false;
        }

        DB::transaction(function () use ($password) {
            $this->update([
                'password' => Hash::make($password),
                'invitation_accepted_at' => now(),
                'status' => self::STATUS_ACTIVE,
                'email_verified_at' => $this->email_verified_at ?? now(),
            ]);

            $this->pendingInvitation->update([
                'accepted_at' => now(),
                'status' => UserInvitation::STATUS_ACCEPTED
            ]);
        });

        return true;
    }

    /**
     * Check if user can receive invitations
     */
    public function canReceiveInvitation(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               !$this->has_valid_invitation &&
               (!empty($this->phone) || !empty($this->email));
    }

    /**
     * Create new invitation for user
     */
    public function createInvitation(array $data = []): ?UserInvitation
    {
        if (!$this->canReceiveInvitation()) {
            return null;
        }

        return UserInvitation::create(array_merge([
            'user_id' => $this->id,
            'invited_by' => auth()->id(),
            'token' => Str::random(UserInvitation::TOKEN_LENGTH),
            'channels' => [UserInvitation::CHANNEL_EMAIL],
            'invitation_type' => UserInvitation::TYPE_WELCOME,
            'expires_at' => now()->addDays(UserInvitation::DEFAULT_EXPIRY_DAYS),
            'sent_at' => now(),
            'status' => UserInvitation::STATUS_SENT,
            'metadata' => [
                'created_via' => 'system',
                'user_status' => $this->status,
                'user_type' => $this->type_name,
            ]
        ], $data));
    }

    /**
     * Get invitation statistics for user
     */
    public function getInvitationStats(): array
    {
        return [
            'total_invitations' => $this->invitations()->count(),
            'successful_invitations' => $this->invitations()->where('status', UserInvitation::STATUS_ACCEPTED)->count(),
            'pending_invitations' => $this->invitations()->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])->count(),
            'expired_invitations' => $this->invitations()->where('status', UserInvitation::STATUS_EXPIRED)->count(),
            'failed_invitations' => $this->invitations()->where('status', UserInvitation::STATUS_FAILED)->count(),
            'latest_invitation' => $this->latest_invitation_status,
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
        $acceptedInvitations = $this->invitations()->where('status', UserInvitation::STATUS_ACCEPTED)->count();

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

     // ==================== USER TYPE CHECK METHODS (UPDATED) ====================

    /**
     * Check if user can login
     */
    public function canLogin(): bool
    {
        if ($this->isArchived()) {
            return false;
        }
        
        if (!$this->can_login) {
            return false;
        }
        
        if ($this->has_valid_invitation) {
            return false;
        }

        if ($this->isFieldAgent()) {
            return $this->status === self::STATUS_ACTIVE && 
                   !is_null($this->invitation_accepted_at);
        }
        
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if user is super admin (via role OR legacy type)
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin') || (isset($this->attributes['type']) && (int) $this->attributes['type'] === self::TYPE_SUPER_ADMIN);
    }

    public function isSanitationPersonnel(): bool
    {
        return $this->hasRole('sanitation-personnel') || 
               (isset($this->attributes['type']) && 
                (int) $this->attributes['type'] === self::TYPE_SANITATION_PERSONNEL);
    }

    /**
     * Check if user is admin (via role OR legacy type)
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || (isset($this->attributes['type']) && (int) $this->attributes['type'] === self::TYPE_ADMIN);
    }

    /**
     * Check if user is a contractor (via role OR legacy type)
     */
    public function isContractor(): bool
    {
        return $this->hasRole('contractor') || 
               (isset($this->attributes['type']) && 
                (int) $this->attributes['type'] === self::TYPE_CONTRACTOR);
    }

    /**
     * Check if user is a supervisor (Super Admin or Admin)
     */
    public function isSupervisor(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin();
    }

 /**
 * Get all email statuses for dropdowns
 */
public static function getEmailStatuses(): array
{
    return [
        'verified' => 'Verified',
        'unverified' => 'Unverified',
        'pending' => 'Pending Verification',
        'bounced' => 'Bounced',
        'complained' => 'Complained',
    ];
}

    /**
 * ✅ CHANGED: Make role the primary check, type as fallback
 */
public function isLandlord(): bool
{
    // PRIMARY: Check by role first (for multi-role users)
    if ($this->hasRole('landlord')) {
        return true;
    }
    
    // FALLBACK: Legacy type for backward compatibility
    return isset($this->attributes['type']) && 
           (int) $this->attributes['type'] === self::TYPE_LANDLORD;
}

    /**
     * Check if user is former landlord (archived landlord)
     */
    public function isFormerLandlord(): bool
    {
        return (isset($this->attributes['type']) && (int) $this->attributes['type'] === self::TYPE_FORMER_LANDLORD);
    }

    /**
     * Check if user is tenant (via role OR legacy type)
     */
    public function isTenant(): bool
    {
        return $this->hasRole('tenant') || (isset($this->attributes['type']) && (int) $this->attributes['type'] === self::TYPE_TENANT);
    }

    /**
     * Check if user is field agent (via role OR legacy type)
     */
    public function isFieldAgent(): bool
    {
        return $this->hasRole('field-agent') || (isset($this->attributes['type']) && (int) $this->attributes['type'] === self::TYPE_FIELD_AGENT);
    }

    /**
     * Check if user is developer (via role OR legacy type)
     */
    public function isDeveloper(): bool
    {
        return $this->hasRole('developer') || (isset($this->attributes['type']) && (int) $this->attributes['type'] === self::TYPE_DEVELOPER);
    }

    /**
     * Check if user is security personnel (via role OR legacy type)
     */
    public function isSecurityPersonnel(): bool
    {
        $type = $this->getAttribute('type');
        return $this->hasRole('security-personnel') || ($type !== null && (int) $type === self::TYPE_SECURITY_PERSONNEL);
    }

     /**
     * ✅ NEW: Get user's dashboard route based on primary role
     * Useful for redirecting multi-role users to appropriate dashboard
     */
    public function getDashboardRoute(): string
    {
        $primaryRole = $this->getPrimaryRoleAttribute();
        
        if ($primaryRole) {
            return match($primaryRole->slug) {
                'super-admin' => route('super-admin.dashboard'),
                'admin' => route('admin.dashboard'),
                'developer' => route('developer.dashboard'),
                'landlord' => route('landlord.dashboard'),
                'field-agent' => route('field-agent.dashboard'),
                'security-personnel' => route('security.dashboard'),
                'tenant' => route('tenant.dashboard'),
                'contractor' => route('contractor.dashboard'),
                default => route('dashboard'),
            };
        }
        
        // Fallback to legacy type
        return match($this->type) {
            self::TYPE_SUPER_ADMIN => route('super-admin.dashboard'),
            self::TYPE_ADMIN => route('admin.dashboard'),
            self::TYPE_DEVELOPER => route('developer.dashboard'),
            self::TYPE_LANDLORD => route('landlord.dashboard'),
            self::TYPE_FIELD_AGENT => route('field-agent.dashboard'),
            self::TYPE_SECURITY_PERSONNEL => route('security.dashboard'),
            self::TYPE_TENANT => route('tenant.dashboard'),
            self::TYPE_CONTRACTOR => route('contractor.dashboard'),
            default => route('dashboard'),
        };
    }

    /**
     * ✅ NEW: Get user's role badge HTML for UI display
     */
    public function getRoleBadgesHtml(): string
    {
        $badges = [];
        
        foreach ($this->roles as $role) {
            $badgeClass = match($role->slug) {
                'super-admin' => 'danger',
                'admin' => 'primary',
                'developer' => 'dark',
                'landlord' => 'success',
                'field-agent' => 'info',
                'security-personnel' => 'warning',
                'tenant' => 'secondary',
                default => 'light',
            };
            
            $badges[] = '<span class="badge bg-' . $badgeClass . ' me-1">' 
                        . e($role->display_name) . '</span>';
        }
        
        // Also show legacy type if no roles
        if (empty($badges) && isset($this->attributes['type'])) {
            $legacyName = $this->getTypeName();
            $badges[] = '<span class="badge bg-secondary me-1">' . e($legacyName) . ' (Legacy)</span>';
        }
        
        return implode(' ', $badges);
    }

     // ==================== SCOPE METHODS (UPDATED) ====================

    /**
     * ✅ UPDATED: Scope to get users who are landlords (by role OR legacy type)
     */
    public function scopeLandlords($query)
    {
        return $query->where(function($q) {
            $q->where('type', self::TYPE_LANDLORD)
              ->orWhereHas('roles', function($roleQ) {
                  $roleQ->where('slug', 'landlord');
              });
        });
    }

     /**
     * ✅ UPDATED: Scope to get users with specific role
     */
    public function scopeWithRole($query, string $roleSlug)
    {
        return $query->whereHas('roles', function($q) use ($roleSlug) {
            $q->where('slug', $roleSlug);
        });
    }

    /**
     * Scope to get contractor users
     */
    public function scopeContractors($query)
    {
        return $query->where('type', self::TYPE_CONTRACTOR)
                     ->orWhereHas('roles', function($q) {
                         $q->where('slug', 'contractor');
                     });
    }

    /**
     * ✅ UPDATED: Scope to get users with any of the given roles
     */
    public function scopeWithAnyRole($query, array $roleSlugs)
    {
        return $query->whereHas('roles', function($q) use ($roleSlugs) {
            $q->whereIn('slug', $roleSlugs);
        });
    }
    
    // ==================== CONVENIENCE METHODS FOR LANDLORD PROPERTIES ====================

    /**
     * Get all properties where user is landlord (through landlord role)
     * Automatically checks if user has landlord role
     */
     public function landlordProperties()
{
    // Return relationship, not collection
    return $this->hasMany(Property::class, 'landlord_id');
}

    /**
     * Check if user is archived
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED || !is_null($this->archived_at);
    }

     /**
     * Get the user who deleted/archived this user
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
 * Get the original email before archival (for display purposes)
 */
public function getOriginalEmailForDisplay(): string
{
    if (!$this->isArchived()) {
        return $this->email;
    }

    $metadata = $this->metadata;
    if (is_string($metadata)) {
        $metadata = json_decode($metadata, true) ?? [];
    }

    return $metadata['archival_info']['original_email']
        ?? $metadata['archived']['original_data']['email']     // ← THE FIX
        ?? $metadata['archived']['original_email']              // legacy fallback
        ?? $this->email;
}

    /**
     * Activate user account
     */
    public function activate(): bool
    {
        if ($this->isArchived()) {
            return false;
        }
        return $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Suspend user account
     */
    public function suspend(): bool
    {
        if ($this->isArchived()) {
            return false;
        }
        return $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    /**
     * Deactivate user account
     */
    public function deactivate(): bool
    {
        if ($this->isArchived()) {
            return false;
        }
        return $this->update(['status' => self::STATUS_INACTIVE]);
    }

    /**
     * Get user's online status for dashboard
     */
    public function getOnlineStatus(): array
    {
        if (!$this->last_activity_at) {
            return [
                'status' => 'offline',
                'last_seen' => null,
                'is_online' => false,
                'status_color' => 'gray',
            ];
        }

        $minutesAgo = $this->last_activity_at->diffInMinutes(now());
        
        if ($minutesAgo < 5) {
            return [
                'status' => 'online',
                'last_seen' => 'Just now',
                'is_online' => true,
                'status_color' => 'green',
            ];
        } elseif ($minutesAgo < 60) {
            return [
                'status' => 'recent',
                'last_seen' => $minutesAgo . ' minutes ago',
                'is_online' => false,
                'status_color' => 'blue',
            ];
        } else {
            return [
                'status' => 'offline',
                'last_seen' => $this->last_activity_at->diffForHumans(),
                'is_online' => false,
                'status_color' => 'gray',
            ];
        }
    }

    /**
     * Update last activity timestamp.
     */
    public function updateLastActivity()
    {
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Track user login
     */
    public function trackLogin($ipAddress = null, $userAgent = null)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    // ==================== PHOTO/AVATAR METHODS ====================

    /**
     * Get photo URL - RETURNS NULL INSTEAD OF DEFAULT AVATAR
     */
    public function getPhotoUrl(): ?string
    {
        if ($this->photo && Storage::disk(self::PHOTO_DISK)->exists(self::PHOTO_DIRECTORY . '/' . $this->photo)) {
            return Storage::disk(self::PHOTO_DISK)->url(self::PHOTO_DIRECTORY . '/' . $this->photo);
        }

        return null;
    }

    /**
     * Get user's initials
     */
    public function getInitials(): string
    {
        $names = explode(' ', trim($this->name));
        $initials = '';
        
        if (count($names) >= 2) {
            $initials = strtoupper(substr($names[0], 0, 1) . substr($names[count($names) - 1], 0, 1));
        } elseif (count($names) === 1) {
            $initials = strtoupper(substr($names[0], 0, 2));
        } else {
            $initials = strtoupper(substr($this->email ?? 'US', 0, 2));
        }
        
        return $initials;
    }

    /**
     * Delete user photo
     */
    public function deletePhoto(): bool
    {
        if (!$this->photo) {
            return true;
        }

        try {
            $photoPath = self::PHOTO_DIRECTORY . '/' . $this->photo;
            
            if (Storage::disk(self::PHOTO_DISK)->exists($photoPath)) {
                Storage::disk(self::PHOTO_DISK)->delete($photoPath);
            }
            
            return $this->update(['photo' => null]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to delete user photo: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert any phone to local format
     */
    private function convertToLocalFormat($phone): string
    {
        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return self::PHONE_LOCAL_PREFIX . $matches[1];
        }
        return $phone;
    }

    // ==================== ACCOUNT ARCHIVAL METHODS ====================

    /**
     * Check if user can be archived
     */
    public function canBeArchived(): bool
    {
        if ($this->isAdmin() || $this->isSuperAdmin()) {
            return false;
        }
        
        if ($this->properties()->exists()) {
            return false;
        }
        
        if ($this->hasPendingFinancialObligations()) {
            return false;
        }
        
        if ($this->isArchived()) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if user has pending financial obligations
     */
    public function hasPendingFinancialObligations(): bool
    {
        if ($this->invoices()->where('status', '!=', 'paid')->exists()) {
            return true;
        }
        
        if ($this->payments()->where('status', 'pending')->exists()) {
            return true;
        }
        
        if ($this->tenantInvoices()->where('status', '!=', 'paid')->exists()) {
            return true;
        }
        
        return false;
    }

    /**
     * Archive the user account
     */
    public function archive(string $reason = 'no_properties', ?int $archivedBy = null): bool
    {
        if (!$this->canBeArchived()) {
            \Log::warning('Archive failed: User cannot be archived', [
                'user_id' => $this->id,
                'user_name' => $this->name
            ]);
            return false;
        }
        
        DB::beginTransaction();
        
        try {
            $originalData = [
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->location,
                'status' => $this->status,
                'type' => $this->type
            ];
            
            $metadata = $this->metadata ?? [];
            $metadata['archived'] = [
                'archived_at' => now()->toISOString(),
                'archived_by' => $archivedBy ?? auth()->id(),
                'archived_by_name' => $archivedBy ? (User::find($archivedBy)?->name ?? 'System') : (auth()->user()?->name ?? 'System'),
                'archived_reason' => $reason,
                'original_data' => $originalData,
                'original_user_type' => $this->getOriginal('type'),
                'properties_count' => $this->properties()->count(),
                'last_property_ownership' => $this->last_property_ownership
            ];
            
            $updated = $this->update([
                'status' => self::STATUS_ARCHIVED,
                'type' => self::TYPE_FORMER_LANDLORD,
                'archived_at' => now(),
                'email' => 'archived_' . $this->id . '_' . time() . '@deleted.local',
                'phone' => null,
                'location' => null,
                'digital_address' => null,
                'can_login' => false,
                'api_access' => false,
                'metadata' => $metadata
            ]);
            
            if (!$updated) {
                throw new \Exception('Failed to update user record');
            }
            
            $this->tokens()->delete();
            DB::table('sessions')->where('user_id', $this->id)->delete();
            
            DB::commit();
            
            \Log::info('User account archived successfully', [
                'user_id' => $this->id,
                'user_name' => $this->name,
                'reason' => $reason,
                'archived_by' => $archivedBy ?? auth()->id()
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Failed to archive user account', [
                'user_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Restore an archived account
     */
    public function restoreArchived(): bool
    {
        if (!$this->isArchived()) {
            return false;
        }
        
        DB::beginTransaction();
        
        try {
            $archivedData = $this->metadata['archived'] ?? [];
            $originalData = $archivedData['original_data'] ?? [];
            
            $metadata = $this->metadata ?? [];
            
            $metadata['restored'] = [
                'restored_at' => now()->toISOString(),
                'restored_by' => auth()->id(),
                'restored_by_name' => auth()->user()?->name ?? 'System',
                'previous_archive_data' => $archivedData
            ];
            
            if (isset($metadata['archived'])) {
                unset($metadata['archived']);
            }
            
            $updated = $this->update([
    'status' => $originalData['status'] ?? self::STATUS_INACTIVE,
    'type'   => $archivedData['original_user_type']           // ← correct (top-level in archived)
             ?? $originalData['type']                         // ← fallback (nested)
             ?? self::TYPE_LANDLORD,                          // ← last resort
    'archived_at' => null,
    'email' => $originalData['email'] ?? $this->email,
    'phone' => $originalData['phone'] ?? null,
    'location' => $originalData['address'] ?? null,
    'can_login' => true,
    'api_access' => true,
    'metadata' => $metadata
]);
            
            if (!$updated) {
                throw new \Exception('Failed to restore user');
            }
            
            DB::commit();
            
            \Log::info('User account restored from archive', [
                'user_id' => $this->id,
                'user_name' => $this->name,
                'restored_by' => auth()->id()
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Failed to restore archived user', [
                'user_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Schedule account for archival (not deletion)
     */
    public function scheduleArchival(int $daysUntilArchival = 30): bool
    {
        if ($this->isArchived()) {
            return false;
        }
        
        if (!$this->canBeArchived()) {
            return false;
        }
        
        $scheduledDate = now()->addDays($daysUntilArchival);
        
        $this->update([
            'deletion_scheduled_at' => $scheduledDate,
            'metadata' => array_merge($this->metadata ?? [], [
                'archival_scheduled' => [
                    'scheduled_at' => now()->toISOString(),
                    'scheduled_for' => $scheduledDate->toISOString(),
                    'days_until_archival' => $daysUntilArchival,
                    'notifications_sent' => false
                ]
            ])
        ]);
        
        \Log::info('User scheduled for archival', [
            'user_id' => $this->id,
            'user_name' => $this->name,
            'scheduled_date' => $scheduledDate->toISOString()
        ]);
        
        return true;
    }

    // ==================== STATIC METHODS ====================

    /**
     * Public method to access role mapping.
     */
    public static function getRoleMapping(): array
    {
        return self::$roleMapping;
    }

    /**
     * Get all user types for dropdowns or validation
     */
    public static function getUserTypes(): array
    {
        return [
            self::TYPE_SUPER_ADMIN => 'Super Admin',
            self::TYPE_ADMIN => 'Admin',
            self::TYPE_LANDLORD => 'Landlord',
            self::TYPE_TENANT => 'Tenant',
            self::TYPE_FIELD_AGENT => 'Field Agent',
            self::TYPE_DEVELOPER => 'Developer',
            self::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
            self::TYPE_FORMER_LANDLORD => 'Former Landlord',
            self::TYPE_CONTRACTOR => 'Contractor',
            self::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',
        ];
    }

    /**
     * Get all user statuses for dropdowns
     */
    public static function getUserStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_VERIFICATION_REQUIRED => 'Verification Required',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    /**
     * ✅ NEW: Get users who are property owners (have landlord role OR own properties)
     */
    public static function getPropertyOwners()
    {
        return self::whereHas('roles', function($q) {
            $q->where('slug', 'landlord');
        })->orWhereHas('ownedProperties')->get();
    }

    // ==================== SCOPES ====================

    /**
     * Scope for field agents
     */
    public function scopeFieldAgents($query)
    {
        return $query->where('type', self::TYPE_FIELD_AGENT);
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for active field agents
     */
    public function scopeActiveFieldAgents($query)
    {
        return $query->where('type', self::TYPE_FIELD_AGENT)
                    ->where('status', self::STATUS_ACTIVE)
                    ->whereNotNull('invitation_accepted_at');
    }

    /**
     * Scope for pending field agents
     */
    public function scopePendingFieldAgents($query)
    {
        return $query->where('type', self::TYPE_FIELD_AGENT)
                    ->where('status', self::STATUS_PENDING)
                    ->whereNull('invitation_accepted_at');
    }

    /**
     * Scope for security personnel
     */
    public function scopeSecurityPersonnel($query)
    {
        return $query->where('type', self::TYPE_SECURITY_PERSONNEL);
    }

    /**
     * Scope for users with valid invitations
     */
    public function scopeWithValidInvitations($query)
    {
        return $query->whereHas('invitations', function($q) {
            $q->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])
              ->where('expires_at', '>', now());
        })->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for users with photos
     */
    public function scopeWithPhotos($query)
    {
        return $query->whereNotNull('photo')
                    ->where('photo', '!=', '');
    }

    /**
 * Sync roles based on user type (legacy compatibility)
 * This ensures legacy type users automatically get corresponding roles
 */
public function syncRolesFromLegacyType(): void
{
    $roleMapping = [
        self::TYPE_SUPER_ADMIN => 'super-admin',
        self::TYPE_ADMIN => 'admin',
        self::TYPE_LANDLORD => 'landlord',
        self::TYPE_TENANT => 'tenant',
        self::TYPE_FIELD_AGENT => 'field-agent',
        self::TYPE_SECURITY_PERSONNEL => 'security-personnel',
        self::TYPE_CONTRACTOR => 'contractor',
    ];
    
    $legacyType = $this->type;
    
    if (isset($roleMapping[$legacyType])) {
        $roleSlug = $roleMapping[$legacyType];
        
        // Check if role exists and user doesn't have it
        $role = Role::where('slug', $roleSlug)->first();
        if ($role && !$this->hasRole($roleSlug)) {
            $this->roles()->attach($role->id, [
                'assigned_by' => auth()->id() ?? 1,
                'assigned_at' => now(),
                'is_active' => true,
                'status' => 'active',
                'assignment_reason' => 'Auto-synced from legacy user type'
            ]);
            
            \Log::info("Auto-assigned role '{$roleSlug}' from legacy type", [
                'user_id' => $this->id,
                'user_name' => $this->name,
                'legacy_type' => $legacyType
            ]);
        }
    }
}

/**
 * Scope a query to only include users who are capable of being supervisors.
 * 
 * This method checks if users have the necessary attributes to be a supervisor:
 * - Must be security personnel (type 6) OR have security-related roles
 * - Must be active
 * - Must have can_be_supervisor flag set to true
 * - Should have supervisor_level > 0
 * 
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeSupervisorCapable($query)
{
    return $query->where(function($q) {
        // Check by type (security personnel)
        $q->where('type', self::TYPE_SECURITY_PERSONNEL)
          // OR by role (security personnel or supervisor)
          ->orWhereHas('roles', function($roleQuery) {
              $roleQuery->whereIn('slug', ['security-personnel', 'supervisor', 'admin']);
          });
    })
    ->where('status', self::STATUS_ACTIVE) // Only active users
    ->where('can_be_supervisor', true) // Must be eligible
    ->where('supervisor_level', '>', self::SUPERVISOR_LEVEL_NONE); // Must have supervisor level
}

/**
 * Scope a query to only include users who can be assigned as supervisors.
 * 
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeEligibleSupervisors($query)
{
    return $query->supervisorCapable()
                 ->whereDoesntHave('activeSupervisorAssignments', function($q) {
                     // Exclude users who already have active supervisor assignments
                     // at maximum capacity (if you have a limit)
                 });
}

/**
 * Scope a query to only include users who are active supervisors.
 * 
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeActiveSupervisors($query)
{
    return $query->whereHas('activeSupervisorAssignments');
}

/**
 * Scope a query to only include users who are NOT supervisors.
 * 
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeNonSupervisors($query)
{
    return $query->whereDoesntHave('activeSupervisorAssignments');
}

/**
 * Ensure user has all roles based on their type and assignments
 * This keeps roles and legacy type in sync
 */
public function ensureRoleConsistency(): void
{
    // Sync roles from legacy type if needed
    $this->syncRolesFromLegacyType();
    
    // Also update legacy type based on roles if needed (optional)
    $this->syncLegacyTypeFromRoles();
}

/**
 * Update legacy type based on user's highest priority role
 */
public function syncLegacyTypeFromRoles(): void
{
    $primaryRole = $this->getPrimaryRoleAttribute();
    
    if ($primaryRole) {
        $roleToTypeMapping = [
            'super-admin' => self::TYPE_SUPER_ADMIN,
            'admin' => self::TYPE_ADMIN,
            'landlord' => self::TYPE_LANDLORD,
            'field-agent' => self::TYPE_FIELD_AGENT,
            'security-personnel' => self::TYPE_SECURITY_PERSONNEL,
            'tenant' => self::TYPE_TENANT,
            'contractor' => self::TYPE_CONTRACTOR,
        ];
        
        if (isset($roleToTypeMapping[$primaryRole->slug])) {
            $newType = $roleToTypeMapping[$primaryRole->slug];
            if ($this->type != $newType) {
                $this->type = $newType;
                $this->saveQuietly(); // Save without triggering events
                
                \Log::info("Updated legacy type from roles", [
                    'user_id' => $this->id,
                    'old_type' => $this->getOriginal('type'),
                    'new_type' => $newType,
                    'primary_role' => $primaryRole->slug
                ]);
            }
        }
    }
}

public function getRoleBasedNotifications(?string $roleSlug = null)
{
    $roleSlug = $roleSlug ?? session('selected_role');
    
    if (!$roleSlug) {
        // If no role selected, get all notifications
        return $this->notifications();
    }
    
    // Get notifications where:
    // 1. roles array contains the current role, OR
    // 2. roles is null (backward compatibility), OR
    // 3. roles contains 'all'
    return $this->notifications()
        ->where(function($query) use ($roleSlug) {
            $query->whereNull('data->roles')
                  ->orWhereJsonContains('data->roles', 'all')
                  ->orWhereJsonContains('data->roles', $roleSlug);
        });
}

/**
 * Get unread notifications for current role
 */
public function getRoleBasedUnreadCount(?string $roleSlug = null): int
{
    return $this->getRoleBasedNotifications($roleSlug)
                ->whereNull('read_at')
                ->count();
}

/**
 * Get role-specific notification statistics
 */
public function getRoleBasedNotificationStats(?string $roleSlug = null): array
{
    $notifications = $this->getRoleBasedNotifications($roleSlug);
    
    return [
        'total' => $notifications->count(),
        'unread' => $notifications->whereNull('read_at')->count(),
        'read' => $notifications->whereNotNull('read_at')->count(),
        'today' => $notifications->whereDate('created_at', today())->count(),
        'by_category' => $this->getRoleBasedCategoryStats($roleSlug),
        'by_priority' => $this->getRoleBasedPriorityStats($roleSlug),
    ];
}

protected function getRoleBasedCategoryStats(?string $roleSlug = null): array
{
    return $this->getRoleBasedNotifications($roleSlug)
        ->selectRaw("data->>'$.category' as category, COUNT(*) as count")
        ->whereNotNull("data->>'$.category'")
        ->groupBy("data->>'$.category'")
        ->pluck('count', 'category')
        ->toArray();
}

protected function getRoleBasedPriorityStats(?string $roleSlug = null): array
{
    return $this->getRoleBasedNotifications($roleSlug)
        ->selectRaw("data->>'$.priority' as priority, COUNT(*) as count")
        ->whereNotNull("data->>'$.priority'")
        ->groupBy("data->>'$.priority'")
        ->pluck('count', 'priority')
        ->toArray();
}

/**
 * Mark role-based notifications as read
 */
public function markRoleBasedNotificationsAsRead(?string $roleSlug = null): int
{
    return $this->getRoleBasedNotifications($roleSlug)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
}

/**
 * Clear (delete) role-based notifications
 */
public function clearRoleBasedNotifications(?string $roleSlug = null): int
{
    return $this->getRoleBasedNotifications($roleSlug)->delete();
}


/**
 * Get the rotation group memberships for this user
 */
public function rotationGroupMembers()
{
    return $this->hasMany(RotationGroupMember::class, 'user_id');
}

/**
 * Get the active rotation group memberships for this user
 */
public function activeRotationGroupMembers()
{
    return $this->hasMany(RotationGroupMember::class, 'user_id')
                ->where('status', 'active');
}

/**
 * Get the rotation group this user is currently assigned to (if any)
 */
public function currentRotationGroup()
{
    return $this->hasOne(RotationGroupMember::class, 'user_id')
                ->where('status', 'active')
                ->with('rotationGroup');
}

/**
 * Get the dashboard route for contractor
 */
public function getContractorDashboardRoute(): string
{
    if ($this->isContractor()) {
        return route('contractor.dashboard');
    }
    return $this->getDashboardRoute();
}

 /**
     * Check if the user has any active supervisor assignments.
     *
     * @return bool
     */
    public function hasActiveSupervisorAssignments()
    {
        return $this->activeSupervisorAssignments()->exists();
    }

    

    /**
     * Get the post IDs that this user supervises.
     *
     * @return array
     */
    public function supervisedPosts()
    {
        return $this->activeSupervisorAssignments()
            ->whereNotNull('security_post_id')
            ->pluck('security_post_id')
            ->toArray();
    }

    /**
     * Get the count of pending approvals for this supervisor.
     *
     * @return int
     */
    public function getPendingApprovalsCount()
    {
        $postIds = $this->supervisedPosts();
        
        if (empty($postIds)) {
            return 0;
        }
        
        $count = 0;
        
        // Pending swap requests
        $count += SwapRequest::whereHas('schedule', function($q) use ($postIds) {
            $q->whereIn('security_post_id', $postIds);
        })->where('status', 'pending')->count();
        
        // Pending overtime requests
        $count += OvertimeRequest::whereHas('schedule', function($q) use ($postIds) {
            $q->whereIn('security_post_id', $postIds);
        })->where('status', 'pending')->count();
        
        // Pending verifications
        $count += SecuritySchedule::whereIn('security_post_id', $postIds)
            ->whereDate('assignment_date', now()->toDateString())
            ->where('check_in_status', 'pending_verification')
            ->count();
        
        return $count;
    }
}