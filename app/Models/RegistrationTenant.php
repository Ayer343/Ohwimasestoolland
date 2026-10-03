<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegistrationTenant extends Model
{
    use SoftDeletes;

    protected $table = 'registration_tenants';

    protected $fillable = [
        'registration_id',
        'property_id',
        'user_id',
        'name',
        'phone',
        'email',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the registration that this tenant belongs to
     */
    public function registration()
    {
        return $this->belongsTo(LandlordConstructionRegistration::class, 'registration_id');
    }

    /**
     * Get the property that this tenant is associated with
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get the user account associated with this tenant
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who approved this tenant
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the invitations for this tenant
     */
    public function invitations()
    {
        return $this->hasMany(TenantInvitation::class, 'tenant_id');
    }

    // ========== SCOPES ==========

    /**
     * Scope for pending tenants
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved tenants
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected tenants
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for tenants with user accounts
     */
    public function scopeWithAccounts($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope for tenants without user accounts
     */
    public function scopeWithoutAccounts($query)
    {
        return $query->whereNull('user_id');
    }

    // ========== ACCESSORS ==========

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'pending' => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get formatted phone number
     */
    public function getFormattedPhoneAttribute(): string
    {
        if (empty($this->phone)) {
            return 'N/A';
        }
        
        // Format: 0555 555 555 or +233 555 555 555
        $phone = preg_replace('/\D/', '', $this->phone);
        
        if (strlen($phone) === 10) {
            return substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7, 3);
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '233') {
            return '+233 ' . substr($phone, 3, 3) . ' ' . substr($phone, 6, 3) . ' ' . substr($phone, 9, 3);
        }
        
        return $this->phone;
    }

    /**
     * Check if tenant has an invitation
     */
    public function hasInvitation(): bool
    {
        return $this->invitations()->exists();
    }

    /**
     * Get the latest invitation
     */
    public function latestInvitation()
    {
        return $this->invitations()->latest()->first();
    }

    /**
     * Check if tenant has an active invitation
     */
    public function hasActiveInvitation(): bool
    {
        $latest = $this->latestInvitation();
        return $latest && $latest->is_active;
    }

    /**
     * Check if tenant has accepted invitation
     */
    public function hasAcceptedInvitation(): bool
    {
        return $this->invitations()
            ->where('status', TenantInvitation::STATUS_COMPLETED)
            ->exists();
    }

    /**
     * Approve this tenant
     */
    public function approve(int $userId): bool
    {
        $this->status = 'approved';
        $this->approved_by = $userId;
        $this->approved_at = now();
        
        return $this->save();
    }

    /**
     * Reject this tenant
     */
    public function reject(int $userId, ?string $reason = null): bool
    {
        $this->status = 'rejected';
        $this->approved_by = $userId;
        $this->approved_at = now();
        
        if ($reason) {
            $this->notes = $this->notes ? $this->notes . "\n\nRejection reason: " . $reason : "Rejection reason: " . $reason;
        }
        
        return $this->save();
    }

    /**
     * Link to an existing user account
     */
    public function linkToUser(User $user): bool
    {
        $this->user_id = $user->id;
        return $this->save();
    }

    /**
     * Create a user account for this tenant
     */
    public function createUserAccount(): ?User
    {
        if ($this->user_id) {
            return $this->user;
        }
        
        // Check if user already exists with this phone or email
        $existingUser = null;
        
        if ($this->phone) {
            $existingUser = User::where('phone', $this->phone)->first();
        }
        
        if (!$existingUser && $this->email) {
            $existingUser = User::where('email', $this->email)->first();
        }
        
        if ($existingUser) {
            $this->linkToUser($existingUser);
            return $existingUser;
        }
        
        // Create new user
        $user = User::create([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'type' => User::TYPE_TENANT,
            'status' => User::STATUS_ACTIVE,
            'password' => bcrypt(Str::random(12)),
        ]);
        
        $this->linkToUser($user);
        
        return $user;
    }

    // ========== BOOT METHOD - COMPLETELY REMOVED ACTIVITY LOGGING ==========

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // ALL ACTIVITY LOGGING HAS BEEN REMOVED
        // Activity logging is now handled manually in the controller after commit
        
        // You can add other boot logic here if needed, but no logging
        static::creating(function ($tenant) {
            // Set default status if not provided
            if (empty($tenant->status)) {
                $tenant->status = 'pending';
            }
        });

        // Optional: Add any other non-logging logic here
        static::saving(function ($tenant) {
            // You can add validation or data transformation here
            // But no logging!
        });
    }
}