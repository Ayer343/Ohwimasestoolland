<?php

namespace App\Models;

use App\Traits\HasArchival;
use App\Traits\HasFieldAgentStats;
use App\Traits\HasInvitations;
use App\Traits\HasPhoneNumbers;
use App\Traits\HasRoles;
use App\Traits\HasSupervisorAssignments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use HasSupervisorAssignments;
    use HasPhoneNumbers;
    use HasRoles;
    use HasArchival;
    use HasInvitations;
    use HasFieldAgentStats;

    /* ============================================================
     | CONSTANTS
     * ============================================================ */

    public const TYPE_SUPER_ADMIN          = 0;
    public const TYPE_ADMIN                = 1;
    public const TYPE_LANDLORD             = 2;
    public const TYPE_TENANT               = 3;
    public const TYPE_FIELD_AGENT          = 4;
    public const TYPE_DEVELOPER            = 5;
    public const TYPE_SECURITY_PERSONNEL   = 6;
    public const TYPE_FORMER_LANDLORD      = 7;
    public const TYPE_CONTRACTOR           = 8;
    public const TYPE_SANITATION_PERSONNEL = 9;

    public const STATUS_PENDING               = 'pending';
    public const STATUS_ACTIVE                = 'active';
    public const STATUS_SUSPENDED             = 'suspended';
    public const STATUS_INACTIVE              = 'inactive';
    public const STATUS_VERIFICATION_REQUIRED = 'verification_required';
    public const STATUS_ARCHIVED              = 'archived';

    public const PHONE_VERIFIED             = 'verified';
    public const PHONE_PENDING_VERIFICATION = 'pending';
    public const PHONE_UNVERIFIED           = 'unverified';

    public const PHONE_COUNTRY_CODE  = '+233';
    public const PHONE_LOCAL_PREFIX  = '0';
    public const PHONE_DIGITS_LENGTH = 9;

    public const PHOTO_DISK             = 'public';
    public const PHOTO_DIRECTORY        = 'users/photos';
    public const PHOTO_THUMBNAIL_WIDTH  = 150;
    public const PHOTO_THUMBNAIL_HEIGHT = 150;
    public const PHOTO_MAX_SIZE         = 5120;

    public const TYPE_SLUG_MAP = [
        self::TYPE_SUPER_ADMIN          => 'super-admin',
        self::TYPE_ADMIN                => 'admin',
        self::TYPE_LANDLORD             => 'landlord',
        self::TYPE_TENANT               => 'tenant',
        self::TYPE_FIELD_AGENT          => 'field-agent',
        self::TYPE_DEVELOPER            => 'developer',
        self::TYPE_SECURITY_PERSONNEL   => 'security-personnel',
        self::TYPE_FORMER_LANDLORD      => 'former-landlord',
        self::TYPE_CONTRACTOR           => 'contractor',
        self::TYPE_SANITATION_PERSONNEL => 'sanitation-personnel',
    ];

    /* ============================================================
     | ELOQUENT CONFIG
     * ============================================================ */

    protected $fillable = [
        'name', 'email', 'email_verified_at', 'password', 'phone',
        'digital_address', 'region', 'location', 'photo', 'gender', 'dob',
        'type', 'created_by', 'username', 'last_activity_at',
        'status', 'invitation_accepted_at', 'phone_verified_at',
        'last_invitation_sent_at', 'invitation_method', 'temp_password',
        'verification_code', 'verification_code_sent_at',
        'phone_verification_code', 'phone_verification_sent_at',
        'last_login_at', 'last_login_ip', 'user_agent', 'metadata',
        'can_be_supervisor', 'security_supervisor_certifications',
        'archived_at', 'last_property_ownership',
        'deletion_scheduled_at', 'can_login', 'api_access', 'registration_ip',

        // 2FA fields — required for ->update() calls to persist them
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_backup_codes',
        'two_factor_method',
        'two_factor_enabled_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'temp_password', 'verification_code',
        'phone_verification_code', 'metadata',

        // 2FA secrets must never be serialized
        'two_factor_secret',
        'two_factor_backup_codes',
    ];

    protected $casts = [
        'email_verified_at'          => 'datetime',
        'password'                   => 'hashed',
        'type'                       => 'integer',
        'last_activity_at'           => 'datetime',
        'invitation_accepted_at'     => 'datetime',
        'phone_verified_at'          => 'datetime',
        'last_invitation_sent_at'    => 'datetime',
        'verification_code_sent_at'  => 'datetime',
        'phone_verification_sent_at' => 'datetime',
        'last_login_at'              => 'datetime',
        'status'                     => 'string',
        'metadata'                   => 'array',
        'dob'                        => 'date',
        'can_be_supervisor'          => 'boolean',
        'archived_at'                => 'datetime',
        'last_property_ownership'    => 'datetime',
        'deletion_scheduled_at'      => 'datetime',
        'can_login'                  => 'boolean',
        'api_access'                 => 'boolean',

        // 2FA casts
        'two_factor_enabled'      => 'boolean',
        'two_factor_backup_codes' => 'array',
        'two_factor_enabled_at'   => 'datetime',
        'temp_password'           => 'boolean',
        'password_changed_at'     => 'datetime',
    ];

    protected $attributes = [
        'type'               => self::TYPE_TENANT,
        'status'             => self::STATUS_ACTIVE,
        'can_be_supervisor'  => false,
        'can_login'          => true,
        'api_access'         => true,
        'two_factor_enabled' => false,
    ];

    protected $appends = [
        'role_name', 'type_name', 'status_with_color',
        'photo_url', 'photo_thumbnail_url', 'has_photo', 'avatar_url',
        'initials', 'local_phone', 'is_phone_verified',
        'is_invitation_pending', 'invitation_status',
        'has_accepted_invitation',
        'is_security_supervisor', 'is_eligible_security_supervisor',
        'is_archived', 'can_be_restored',
        'primary_role_name',
    ];

    /* ============================================================
     | RUNTIME MEMO — avoids re-querying the active appointment
     | many times per request (sidebar + composer + gate + controller
     | all call isSupervisor() on the same user object).
     * ============================================================ */

    private ?bool $isSupervisorMemo        = null;
    private ?bool $hasActiveSupervisorMemo = null;

    /* ============================================================
     | BOOT HOOKS
     * ============================================================ */

    protected static function boot(): void
    {
        parent::boot();

        static::creating(fn (self $user) => $user->handleCreating());
        static::updating(fn (self $user) => $user->handleUpdating());
        static::deleting(fn (self $user) => $user->handleDeleting());
        static::restored(fn (self $user) => $user->handleRestored());
        static::forceDeleted(fn (self $user) => $user->deletePhoto());
    }

    protected function handleCreating(): void
    {
        if ($this->type === self::TYPE_FIELD_AGENT && empty($this->status)) {
            $this->status = self::STATUS_PENDING;
        }

        if ($this->type === self::TYPE_FIELD_AGENT && empty($this->username)) {
            $this->username = $this->generateAgentUsername();
        }

        if (!empty($this->phone)) {
            $this->phone = $this->standardizePhoneNumber($this->phone);
        }

        if (empty($this->metadata)) {
            $this->metadata = [
                'created_via'                  => 'manual',
                'registration_source'          => 'web',
                'initial_status'               => $this->status,
                'password_history'             => [],
                'change_log'                   => [],
                'total_logins'                 => 0,
                'phone_standardized'           => true,
                'invitation_method'            => $this->invitation_method ?? null,
                'supervisor_assigned_at'       => null,
                'supervisor_promotion_history' => [],
            ];
        }
    }

    protected function handleUpdating(): void
    {
        if ($this->isDirty('phone') && !empty($this->phone)) {
            $this->phone = $this->standardizePhoneNumber($this->phone);

            $this->metadata = array_merge($this->metadata ?? [], [
                'phone_last_updated'     => now()->toISOString(),
                'previous_phone_formats' => $this->metadata['previous_phone_formats'] ?? [],
            ]);
        }

        if ($this->isDirty('status') && $this->status === self::STATUS_ACTIVE) {
            $this->invitations()
                ->whereIn('status', [UserInvitation::STATUS_PENDING, UserInvitation::STATUS_SENT])
                ->where('expires_at', '>', now())
                ->update([
                    'accepted_at' => now(),
                    'status'      => UserInvitation::STATUS_ACCEPTED,
                ]);
        }

        if (
            $this->isDirty('invitation_accepted_at') &&
            !is_null($this->invitation_accepted_at) &&
            $this->type === self::TYPE_FIELD_AGENT
        ) {
            $this->status            = self::STATUS_ACTIVE;
            $this->email_verified_at = $this->email_verified_at ?? now();
        }

        if ($this->isDirty('last_login_at')) {
            $this->metadata = array_merge($this->metadata ?? [], [
                'last_login_tracked_at' => now()->toISOString(),
                'total_logins'          => ($this->metadata['total_logins'] ?? 0) + 1,
            ]);
        }

        // Track supervisor eligibility changes in metadata (audit trail).
        if ($this->isDirty('can_be_supervisor')) {
            $history   = $this->metadata['supervisor_eligibility_history'] ?? [];
            $history[] = [
                'old_value'  => (bool) $this->getOriginal('can_be_supervisor'),
                'new_value'  => (bool) $this->can_be_supervisor,
                'changed_at' => now()->toISOString(),
                'changed_by' => auth()->id(),
                'changed_by_name' => auth()->user()?->name ?? 'System',
            ];

            $this->metadata = array_merge($this->metadata ?? [], [
                'supervisor_eligibility_history' => $history,
            ]);
        }

        // If eligibility changed, invalidate the capability memo.
        if ($this->isDirty('can_be_supervisor') || $this->isDirty('type') || $this->isDirty('status')) {
            $this->isSupervisorMemo        = null;
            $this->hasActiveSupervisorMemo = null;
        }
    }

    protected function handleDeleting(): void
    {
        try {
            $userName = auth()->user()?->name ?? 'System';
            $userId   = auth()->id() ?? 0;
            $now      = now()->toISOString();

            $pending = $this->invitations()
                ->whereNull('accepted_at')
                ->whereIn('status', ['pending', 'sent'])
                ->get();

            foreach ($pending as $invitation) {
                $metadata = $invitation->metadata ?? [];
                $metadata['cancelled_by']        = $userId;
                $metadata['cancelled_by_name']   = $userName;
                $metadata['cancelled_at']        = $now;
                $metadata['cancellation_reason'] = 'User account deleted';

                $invitation->update([
                    'status'              => 'cancelled',
                    'cancelled_at'        => now(),
                    'cancellation_reason' => 'User account deleted',
                    'metadata'            => $metadata,
                ]);
            }

            if ($pending->isNotEmpty()) {
                Log::info('Cancelled pending invitations during user deletion', [
                    'user_id'          => $this->id,
                    'user_name'        => $this->name,
                    'invitation_count' => $pending->count(),
                    'deleted_by'       => $userId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to cancel invitations during user deletion', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
        }

        if ($this->isForceDeleting()) {
            $this->deletePhoto();
            $this->roleAssignments()->delete();
        }
    }

    protected function handleRestored(): void
    {
        try {
            $this->invitations()
                ->where('status', 'cancelled')
                ->where('cancelled_at', '<', now()->subDays(30))
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to clean up old cancelled invitations', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /* ============================================================
     | CORE RELATIONSHIPS
     * ============================================================ */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function recentActivities($limit = 10)
    {
        return $this->activities()->limit($limit)->get();
    }

    public function properties()
    {
        return $this->landlordProperties();
    }

    public function landlordProperties()
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    public function ownedProperties()
    {
        return $this->landlordProperties();
    }

    public function createdProperties()
    {
        return $this->hasMany(Property::class, 'created_by');
    }

    public function registeredProperties()
    {
        return $this->hasMany(Property::class, 'registered_by');
    }

    /* ============================================================
     | FAMILY LINK RELATIONSHIPS
     * ============================================================ */

    public function familyLinks()
    {
        return $this->hasMany(PropertyFamilyLink::class, 'linked_user_id');
    }

    public function approvedFamilyLinks()
    {
        return $this->hasMany(PropertyFamilyLink::class, 'linked_user_id')
            ->where('status', PropertyFamilyLink::STATUS_APPROVED);
    }

    public function linkedProperties()
    {
        return $this->belongsToMany(
            Property::class,
            'property_family_links',
            'linked_user_id',
            'property_id'
        )
            ->where('property_family_links.status', PropertyFamilyLink::STATUS_APPROVED)
            ->withPivot([
                'landlord_id',
                'relationship',
                'permissions',
                'status',
                'linked_at',
            ])
            ->withTimestamps();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'landlord_id');
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class, 'tenant_id');
    }

    public function rentalAgreements()
    {
        return $this->rentals();
    }

    public function landlordRentalAgreements()
    {
        return $this->hasMany(RentalAgreement::class, 'landlord_id');
    }

    public function createdRentalAgreements()
    {
        return $this->hasMany(RentalAgreement::class, 'created_by');
    }

    public function units()
    {
        return $this->hasMany(PropertyUnit::class, 'tenant_id');
    }

    public function propertyUnits()
    {
        return $this->hasMany(PropertyUnit::class, 'tenant_id');
    }

    public function tenantInvoices()
    {
        return $this->hasMany(TenantInvoice::class, 'tenant_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'tenant_id');
    }

    public function phones()
    {
        return $this->hasMany(LandlordPhone::class, 'landlord_id');
    }

    public function emailAccounts()
    {
        return $this->hasMany(UserEmailAccount::class);
    }

    public function primaryEmailAccount()
    {
        return $this->hasOne(UserEmailAccount::class)->where('is_primary', true);
    }

    public function verifiedEmailAccounts()
    {
        return $this->hasMany(UserEmailAccount::class)->where('status', 'verified');
    }

    public function tenantProperties()
    {
        return $this->belongsToMany(Property::class, 'property_tenant', 'user_id', 'property_id')
            ->withPivot('added_by', 'added_at', 'notes', 'status', 'start_date', 'end_date')
            ->withTimestamps();
    }

    public function testimonials()
    {
        return $this->hasMany(Testimonial::class, 'user_id');
    }

    public function approvedTestimonials()
    {
        return $this->hasMany(Testimonial::class, 'user_id')->where('is_approved', true);
    }

    public function featuredTestimonials()
    {
        return $this->hasMany(Testimonial::class, 'user_id')->where('is_featured', true);
    }

    public function contracts()
    {
        return $this->hasMany(ConstructionContract::class, 'contractor_user_id');
    }

    public function landlordContracts()
    {
        return $this->hasMany(ConstructionContract::class, 'landlord_id');
    }

    public function constructionWorkers()
    {
        return $this->hasMany(ConstructionWorker::class, 'added_by');
    }

    public function assignedWorkers()
    {
        return $this->hasMany(ConstructionWorker::class, 'contractor_id');
    }

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

    public function sanitationPersonnel()
    {
        return $this->hasOne(SanitationPersonnel::class);
    }

    public function wasteCollectionRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class, 'requested_by');
    }

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

    public function rotationGroupMembers()
    {
        return $this->hasMany(RotationGroupMember::class, 'user_id');
    }

    public function activeRotationGroupMembers()
    {
        return $this->hasMany(RotationGroupMember::class, 'user_id')->where('status', 'active');
    }

    public function currentRotationGroup()
    {
        return $this->hasOne(RotationGroupMember::class, 'user_id')
            ->where('status', 'active')
            ->with('rotationGroup');
    }

    public function trustedDevices()
    {
        return $this->hasMany(\App\Models\TrustedDevice::class, 'user_id');
    }

    /* ============================================================
     | SECURITY HEAD-OF-SECURITY RELATIONSHIP
     * ============================================================ */

    public function activeSecurityHeadAssignment()
    {
        return $this->hasOne(SecuritySupervisorAssignment::class, 'user_id')
            ->where('is_active', true)
            ->latestOfMany();
    }

    /**
     * All appointments (active and inactive) for this user.
     *
     * Present on User so the model is self-sufficient even if the
     * HasSupervisorAssignments trait forgets to declare it.
     */
    public function supervisorAssignments()
    {
        return $this->hasMany(SecuritySupervisorAssignment::class, 'user_id');
    }

    /* ============================================================
     | ACCESSORS — IDENTITY / TYPE
     * ============================================================ */

    public function getRoleName(): string
    {
        return self::TYPE_SLUG_MAP[$this->attributes['type'] ?? self::TYPE_TENANT] ?? 'unknown';
    }

    public function getRoleNameAttribute(): string
    {
        return $this->getRoleName();
    }

    public function getTypeNameAttribute(): string
    {
        return $this->getTypeName();
    }

    public function getTypeName(): string
    {
        return self::getUserTypes()[$this->type] ?? 'User';
    }

    public function getStatusWithColorAttribute(): array
    {
        $map = [
            self::STATUS_PENDING               => ['warning',   'Pending Invitation'],
            self::STATUS_ACTIVE                => ['success',   'Active'],
            self::STATUS_SUSPENDED             => ['danger',    'Suspended'],
            self::STATUS_INACTIVE              => ['secondary', 'Inactive'],
            self::STATUS_VERIFICATION_REQUIRED => ['info',      'Verification Required'],
            self::STATUS_ARCHIVED              => ['dark',      'Archived'],
        ];

        $current = $this->attributes['status'] ?? self::STATUS_ACTIVE;
        [$color, $label] = $map[$current] ?? ['secondary', ucfirst((string) $current)];

        return [
            'status'      => $current,
            'label'       => $label,
            'color'       => $color,
            'badge_class' => 'badge bg-' . $color,
        ];
    }

    /* ============================================================
     | ACCESSORS — PROPERTY ACCESS
     * ============================================================ */

    public function getPropertyCountAttribute(): int
    {
        if (array_key_exists('properties_count', $this->attributes)) {
            return (int) $this->attributes['properties_count'];
        }

        return $this->landlordProperties()->count();
    }

    public function getLinkedPropertyCountAttribute(): int
    {
        if (array_key_exists('linked_properties_count', $this->attributes)) {
            return (int) $this->attributes['linked_properties_count'];
        }

        return $this->approvedFamilyLinks()->count();
    }

    public function getAccessiblePropertyCountAttribute(): int
    {
        return $this->getPropertyCountAttribute() + $this->getLinkedPropertyCountAttribute();
    }

    public function getHasPropertyAccessAttribute(): bool
    {
        return $this->getAccessiblePropertyCountAttribute() > 0;
    }

    public function getIsPropertyOwnerAttribute(): bool
    {
        return $this->getPropertyCountAttribute() > 0;
    }

    public function accessiblePropertyIds(): \Illuminate\Support\Collection
    {
        $ownedIds = $this->landlordProperties()->pluck('id');

        $linkedIds = PropertyFamilyLink::query()
            ->where('linked_user_id', $this->id)
            ->where('status', PropertyFamilyLink::STATUS_APPROVED)
            ->pluck('property_id');

        return $ownedIds->merge($linkedIds)->unique()->values();
    }

    /* ============================================================
     | ACCESSORS — PHOTO
     * ============================================================ */

    private ?bool $hasPhotoCache = null;

    public function getPhotoUrl(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        if (!Storage::disk(self::PHOTO_DISK)->exists(self::PHOTO_DIRECTORY . '/' . $this->photo)) {
            return null;
        }

        $url = Storage::disk(self::PHOTO_DISK)->url(self::PHOTO_DIRECTORY . '/' . $this->photo);

        $version = $this->updated_at?->timestamp ?? time();
        $sep     = str_contains($url, '?') ? '&' : '?';

        return $url . $sep . 'v=' . $version;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->getPhotoUrl();
    }

    public function getPhotoThumbnailUrlAttribute(): ?string
    {
        return $this->getPhotoUrl();
    }

    public function getHasPhotoAttribute(): bool
    {
        if ($this->hasPhotoCache !== null) {
            return $this->hasPhotoCache;
        }

        return $this->hasPhotoCache = !empty($this->photo)
            && Storage::disk(self::PHOTO_DISK)->exists(self::PHOTO_DIRECTORY . '/' . $this->photo);
    }

    public function getInitials(): string
    {
        $names = preg_split('/\s+/', trim((string) $this->name));

        if (count($names) >= 2) {
            return strtoupper(substr($names[0], 0, 1) . substr(end($names), 0, 1));
        }

        if (count($names) === 1 && $names[0] !== '') {
            return strtoupper(substr($names[0], 0, 2));
        }

        return strtoupper(substr((string) ($this->email ?? 'US'), 0, 2));
    }

    public function getInitialsAttribute(): string
    {
        return $this->getInitials();
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->has_photo && $this->photo_url) {
            return $this->photo_url;
        }

        $initials = urlencode($this->initials);

        return "https://ui-avatars.com/api/?name={$initials}&background=4f46e5&color=ffffff&size=150&bold=true&font-size=0.8";
    }

    public function deletePhoto(): bool
    {
        if (!$this->photo) {
            return true;
        }

        try {
            $path = self::PHOTO_DIRECTORY . '/' . $this->photo;

            if (Storage::disk(self::PHOTO_DISK)->exists($path)) {
                Storage::disk(self::PHOTO_DISK)->delete($path);
            }

            return $this->update(['photo' => null]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete user photo: ' . $e->getMessage());
            return false;
        }
    }

    /* ============================================================
     | USER TYPE CHECKS
     * ============================================================ */

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin') || $this->type === self::TYPE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->type === self::TYPE_ADMIN;
    }

    /**
     * ------------------------------------------------------------------
     * FIXED: isSupervisor()
     * ------------------------------------------------------------------
     * Previously returned only `isSuperAdmin() || isAdmin()`, which is
     * why a real Head of Security (type=6) with an active
     * `security_supervisor_assignments` row was still reported as a
     * non-supervisor. That single `false` propagated to:
     *   - AppServiceProvider::resolveSecurityCapabilities()['canManage']
     *   - the `manage-security-schedules` gate
     *   - SecurityScheduleController::canManageSchedules()
     *   - the sidebar's @if($canManage) blocks
     *
     * Resolution order:
     *   1. Super-admin / admin (global scope)
     *   2. Explicit spatie role `head_of_security`
     *   3. An active row in security_supervisor_assignments
     *      (this is what AdminSupervisorAssignmentController writes)
     *
     * Memoized so repeated calls in one request don't re-query.
     */
    public function isSupervisor(): bool
    {
        if ($this->isSupervisorMemo !== null) {
            return $this->isSupervisorMemo;
        }

        // 1. Global admins are always supervisors.
        if ($this->isSuperAdmin() || $this->isAdmin()) {
            return $this->isSupervisorMemo = true;
        }

        // 2. Explicit role flag, if spatie/permission is in use.
        if (method_exists($this, 'hasRole') && $this->hasRole('head_of_security')) {
            return $this->isSupervisorMemo = true;
        }

        // 3. Source of truth: an active appointment row.
        if ($this->hasActiveSupervisorAssignment()) {
            return $this->isSupervisorMemo = true;
        }

        return $this->isSupervisorMemo = false;
    }

    /**
     * True if this user currently holds an active Head-of-Security
     * appointment. Delegates to the trait if it defines the method
     * (so a single implementation is used), otherwise falls back to
     * an inline query — always guarded by Schema::hasTable so the
     * method is safe before migrations run.
     */
    public function hasActiveSupervisorAssignment(): bool
    {
        if ($this->hasActiveSupervisorMemo !== null) {
            return $this->hasActiveSupervisorMemo;
        }

        // Defer to the trait implementation if present.
        if (method_exists($this, 'hasActiveSupervisorAssignmentFromTrait')) {
            return $this->hasActiveSupervisorMemo =
                $this->hasActiveSupervisorAssignmentFromTrait();
        }

        try {
            if (!Schema::hasTable('security_supervisor_assignments')) {
                return $this->hasActiveSupervisorMemo = false;
            }

            return $this->hasActiveSupervisorMemo = $this->supervisorAssignments()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhereDate('end_date', '>=', now()->toDateString());
                })
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('hasActiveSupervisorAssignment failed', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
            return $this->hasActiveSupervisorMemo = false;
        }
    }

    public function isLandlord(): bool
    {
        return $this->hasRole('landlord') || $this->type === self::TYPE_LANDLORD;
    }

    public function isFormerLandlord(): bool
    {
        return $this->type === self::TYPE_FORMER_LANDLORD;
    }

    public function isTenant(): bool
    {
        return $this->hasRole('tenant') || $this->type === self::TYPE_TENANT;
    }

    public function isFieldAgent(): bool
    {
        return $this->hasRole('field-agent') || $this->type === self::TYPE_FIELD_AGENT;
    }

    public function isDeveloper(): bool
    {
        return $this->hasRole('developer') || $this->type === self::TYPE_DEVELOPER;
    }

    public function isSecurityPersonnel(): bool
    {
        return $this->hasRole('security-personnel') || $this->type === self::TYPE_SECURITY_PERSONNEL;
    }

    public function isContractor(): bool
    {
        return $this->hasRole('contractor') || $this->type === self::TYPE_CONTRACTOR;
    }

    public function isSanitationPersonnel(): bool
    {
        return $this->hasRole('sanitation-personnel') || $this->type === self::TYPE_SANITATION_PERSONNEL;
    }

    public function isPropertyOwner(): bool
    {
        return $this->getPropertyCountAttribute() > 0;
    }

    public function isLinkedFamilyMember(): bool
    {
        return $this->getLinkedPropertyCountAttribute() > 0;
    }

    /* ============================================================
     | SECURITY SUPERVISOR — SIMPLIFIED MODEL
     * ============================================================ */

    public function isEligibleSecuritySupervisor(): bool
    {
        return $this->isSecurityPersonnel()
            && (bool) $this->can_be_supervisor
            && $this->status === self::STATUS_ACTIVE;
    }

    public function getIsEligibleSecuritySupervisorAttribute(): bool
    {
        return $this->isEligibleSecuritySupervisor();
    }

    public function isCurrentSecurityHead(): bool
    {
        $assignment = $this->relationLoaded('activeSecurityHeadAssignment')
            ? $this->activeSecurityHeadAssignment
            : $this->activeSecurityHeadAssignment()->first();

        return $assignment !== null;
    }

    public function getIsSecuritySupervisorAttribute(): bool
    {
        return $this->isSupervisor();
    }

    /* ============================================================
     | LOGIN / PASSWORD STATE
     * ============================================================ */

    public function canLogin(): bool
    {
        if ($this->isArchived() || !$this->can_login || $this->has_valid_invitation) {
            return false;
        }

        if ($this->isFieldAgent()) {
            return $this->status === self::STATUS_ACTIVE && !is_null($this->invitation_accepted_at);
        }

        return $this->status === self::STATUS_ACTIVE;
    }

    public function needsPasswordSetup(): bool
    {
        if ($this->has_valid_invitation && $this->status === self::STATUS_PENDING) {
            return true;
        }

        if (empty($this->password)) {
            return true;
        }

        if ($this->temp_password === true) {
            return true;
        }

        if (($this->metadata['requires_password_setup'] ?? false) === true) {
            return true;
        }

        if ($this->isFieldAgent() && is_null($this->invitation_accepted_at)) {
            return true;
        }

        if ($this->last_login_at === null) {
            return true;
        }

        return false;
    }

    /* ============================================================
     | DASHBOARD / UI
     * ============================================================ */

    public function getDashboardRoute(): string
    {
        // Head of Security → supervisor landing.
        if ($this->isSupervisor() && !$this->isSuperAdmin() && !$this->isAdmin()) {
            return $this->safeRoute('security.schedules.index');
        }

        $primaryRole = $this->getPrimaryRoleAttribute();

        if ($primaryRole) {
            return $this->routeForRole($primaryRole->slug);
        }

        return $this->routeForRole(self::TYPE_SLUG_MAP[$this->type] ?? null);
    }

    public function getContractorDashboardRoute(): string
    {
        return $this->isContractor()
            ? $this->safeRoute('contractor.dashboard')
            : $this->getDashboardRoute();
    }

    public function getRoleBadgesHtml(): string
    {
        $badges = [];

        foreach ($this->roles as $role) {
            $class = match ($role->slug) {
                'super-admin'        => 'danger',
                'admin'              => 'primary',
                'developer'          => 'dark',
                'landlord'           => 'success',
                'field-agent'        => 'info',
                'security-personnel' => 'warning',
                'tenant'             => 'secondary',
                default              => 'light',
            };

            $badges[] = '<span class="badge bg-' . $class . ' me-1">' . e($role->display_name) . '</span>';
        }

        if (empty($badges)) {
            $badges[] = '<span class="badge bg-secondary me-1">' . e($this->getTypeName()) . ' (Legacy)</span>';
        }

        return implode(' ', $badges);
    }

    /* ============================================================
     | ONLINE STATUS / ACTIVITY
     * ============================================================ */

    public function getOnlineStatus(): array
    {
        if (!$this->last_activity_at) {
            return ['status' => 'offline', 'last_seen' => null, 'is_online' => false, 'status_color' => 'gray'];
        }

        $minutesAgo = $this->last_activity_at->diffInMinutes(now());

        if ($minutesAgo < 5) {
            return ['status' => 'online', 'last_seen' => 'Just now', 'is_online' => true, 'status_color' => 'green'];
        }

        if ($minutesAgo < 60) {
            return ['status' => 'recent', 'last_seen' => "{$minutesAgo} minutes ago", 'is_online' => false, 'status_color' => 'blue'];
        }

        return [
            'status'       => 'offline',
            'last_seen'    => $this->last_activity_at->diffForHumans(),
            'is_online'    => false,
            'status_color' => 'gray',
        ];
    }

    public function updateLastActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }

    public function trackLogin($ipAddress = null, $userAgent = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'user_agent'    => $userAgent,
        ]);
    }

    /* ============================================================
     | ROLE-BASED NOTIFICATIONS
     * ============================================================ */

    public function getRoleBasedNotifications(?string $roleSlug = null)
    {
        $roleSlug = $roleSlug
            ?? (app()->bound('session') && session()->isStarted()
                ? session('selected_role')
                : null);

        if (!$roleSlug) {
            return $this->notifications();
        }

        return $this->notifications()->where(function ($q) use ($roleSlug) {
            $q->whereNull('data->roles')
              ->orWhereJsonContains('data->roles', 'all')
              ->orWhereJsonContains('data->roles', $roleSlug);
        });
    }

    public function getRoleBasedUnreadCount(?string $roleSlug = null): int
    {
        return $this->getRoleBasedNotifications($roleSlug)
            ->whereNull('read_at')
            ->count();
    }

    public function getRoleBasedNotificationStats(?string $roleSlug = null): array
    {
        $base = $this->getRoleBasedNotifications($roleSlug);

        return [
            'total'       => (clone $base)->count(),
            'unread'      => (clone $base)->whereNull('read_at')->count(),
            'read'        => (clone $base)->whereNotNull('read_at')->count(),
            'today'       => (clone $base)->whereDate('created_at', today())->count(),
            'by_category' => $this->getRoleBasedCategoryStats($roleSlug),
            'by_priority' => $this->getRoleBasedPriorityStats($roleSlug),
        ];
    }

    protected function getRoleBasedCategoryStats(?string $roleSlug = null): array
    {
        $query = $this->getRoleBasedNotifications($roleSlug);

        try {
            if (Schema::hasColumn('notifications', 'category')) {
                return (clone $query)
                    ->select('category', DB::raw('COUNT(*) as count'))
                    ->whereNotNull('category')
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray();
            }

            return (clone $query)
                ->select(DB::raw("data->>'$.category' as category"), DB::raw('COUNT(*) as count'))
                ->whereNotNull(DB::raw("data->>'$.category'"))
                ->groupBy(DB::raw("data->>'$.category'"))
                ->pluck('count', 'category')
                ->filter(fn ($count, $category) => $category !== null && $category !== '')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('getRoleBasedCategoryStats failed', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function getRoleBasedPriorityStats(?string $roleSlug = null): array
    {
        $query = $this->getRoleBasedNotifications($roleSlug);

        try {
            if (Schema::hasColumn('notifications', 'priority')) {
                return (clone $query)
                    ->select('priority', DB::raw('COUNT(*) as count'))
                    ->groupBy('priority')
                    ->pluck('count', 'priority')
                    ->mapWithKeys(fn ($count, $priority) => ["priority_{$priority}" => (int) $count])
                    ->toArray();
            }

            $raw = (clone $query)
                ->select(DB::raw("data->>'$.priority' as priority"), DB::raw('COUNT(*) as count'))
                ->whereNotNull(DB::raw("data->>'$.priority'"))
                ->groupBy(DB::raw("data->>'$.priority'"))
                ->get();

            $priorities = [];
            foreach ($raw as $item) {
                if ($item->priority !== null) {
                    $priorities["priority_{$item->priority}"] = (int) $item->count;
                }
            }
            return $priorities;
        } catch (\Throwable $e) {
            Log::warning('getRoleBasedPriorityStats failed', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function markRoleBasedNotificationsAsRead(?string $roleSlug = null): int
    {
        return $this->getRoleBasedNotifications($roleSlug)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function clearRoleBasedNotifications(?string $roleSlug = null): int
    {
        return $this->getRoleBasedNotifications($roleSlug)->delete();
    }

    /* ============================================================
     | SCOPES
     * ============================================================ */

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeSecurityPersonnel($query)
    {
        return $query->where('type', self::TYPE_SECURITY_PERSONNEL);
    }

    public function scopeEligibleSecuritySupervisors($query)
    {
        return $query
            ->where('type', self::TYPE_SECURITY_PERSONNEL)
            ->where('can_be_supervisor', true)
            ->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWithPhotos($query)
    {
        return $query->whereNotNull('photo')->where('photo', '!=', '');
    }

    public function scopeWithPropertyAccess($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('ownedProperties')
              ->orWhereHas('approvedFamilyLinks');
        });
    }

    public function scopePropertyOwners($query)
    {
        return $query->whereHas('ownedProperties');
    }

    public function scopeWithTwoFactorEnabled($query)
    {
        return $query->where('two_factor_enabled', true);
    }

    /* ============================================================
     | STATIC LOOKUPS
     * ============================================================ */

    public static function getRoleMapping(): array
    {
        return self::TYPE_SLUG_MAP;
    }

    public static function getUserTypes(): array
    {
        return [
            self::TYPE_SUPER_ADMIN          => 'Super Admin',
            self::TYPE_ADMIN                => 'Admin',
            self::TYPE_LANDLORD             => 'Landlord',
            self::TYPE_TENANT               => 'Tenant',
            self::TYPE_FIELD_AGENT          => 'Field Agent',
            self::TYPE_DEVELOPER            => 'Developer',
            self::TYPE_SECURITY_PERSONNEL   => 'Security Personnel',
            self::TYPE_FORMER_LANDLORD      => 'Former Landlord',
            self::TYPE_CONTRACTOR           => 'Contractor',
            self::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',
        ];
    }

    public static function getUserStatuses(): array
    {
        return [
            self::STATUS_PENDING               => 'Pending',
            self::STATUS_ACTIVE                => 'Active',
            self::STATUS_SUSPENDED             => 'Suspended',
            self::STATUS_INACTIVE              => 'Inactive',
            self::STATUS_VERIFICATION_REQUIRED => 'Verification Required',
            self::STATUS_ARCHIVED              => 'Archived',
        ];
    }

    public static function getEmailStatuses(): array
    {
        return [
            'verified'   => 'Verified',
            'unverified' => 'Unverified',
            'pending'    => 'Pending Verification',
            'bounced'    => 'Bounced',
            'complained' => 'Complained',
        ];
    }

    public static function getPropertyOwners()
    {
        return self::withPropertyAccess()->get();
    }

    /* ============================================================
     | PRIVATE HELPERS
     * ============================================================ */

    private function generateAgentUsername(): string
    {
        $base     = 'agent_' . strtolower(Str::random(6));
        $username = $base;
        $counter  = 1;

        while (self::where('username', $username)->exists()) {
            $username = $base . '_' . $counter++;
        }

        return $username;
    }

    private function routeForRole(?string $slug): string
    {
        return match ($slug) {
            'super-admin'        => $this->safeRoute('super-admin.dashboard'),
            'admin'              => $this->safeRoute('admin.dashboard'),
            'developer'          => $this->safeRoute('developer.dashboard'),
            'landlord'           => $this->safeRoute('landlord.dashboard'),
            'field-agent'        => $this->safeRoute('field-agent.dashboard'),
            'security-personnel' => $this->safeRoute('security.dashboard'),
            'tenant'             => $this->safeRoute('tenant.dashboard'),
            'contractor'         => $this->safeRoute('contractor.dashboard'),
            default              => $this->safeRoute('dashboard'),
        };
    }

    private function safeRoute(string $name): string
    {
        return Route::has($name) ? route($name) : url('/');
    }
}