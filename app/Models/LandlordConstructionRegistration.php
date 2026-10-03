<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class LandlordConstructionRegistration extends Model
{
    use SoftDeletes;

    // Registration Types
    const TYPE_CONSTRUCTION = 'construction';
    const TYPE_PROPERTY_CAPTURE = 'property_capture';
    
    // Registration Purposes
    const PURPOSE_CONSTRUCTION = 'construction';
    const PURPOSE_PERMANENT_REGISTRATION = 'permanent_registration';
    const PURPOSE_BOTH = 'both';

    // Registration Status Constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_REVIEW = 'in_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_NEEDS_INFO = 'needs_info';
    const STATUS_CANCELLED = 'cancelled';

    // Property Status Constants for Construction
    const PROPERTY_STATUS_UNDER_CONSTRUCTION = 'under_construction';
    const PROPERTY_STATUS_COMPLETED = 'completed';
    const PROPERTY_STATUS_ACTIVE = 'active';
    const PROPERTY_STATUS_VACANT = 'vacant';
    const PROPERTY_STATUS_INACTIVE = 'inactive';
    
    // Existing Property Status Constants
    const EXISTING_STATUS_ACTIVE = 'active';
    const EXISTING_STATUS_OCCUPIED = 'occupied';
    const EXISTING_STATUS_VACANT = 'vacant';
    const EXISTING_STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    const EXISTING_STATUS_INACTIVE = 'inactive';

    // Mapped Property Status Constants (for the created property)
    const MAPPED_STATUS_ACTIVE = 'active';
    const MAPPED_STATUS_INACTIVE = 'inactive';
    const MAPPED_STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    const MAPPED_STATUS_VACANT = 'vacant';
    const MAPPED_STATUS_UNDER_CONSTRUCTION = 'under_construction';

    protected $table = 'landlord_construction_registrations';

    protected $fillable = [
        // Duplicate Prevention Fields
        'submission_hash',
        'duplicate_check_at',
        
        // Registration Type & Purpose
        'registration_type',
        'purpose',
        
        // Landlord Information
        'name',
        'email',
        'primary_phone',
        'additional_phones',
        'landlord_id',
        
        // Land/Plot Information (Common Fields)
        'property_name',
        'plot_number',
        'street_name',
        'digital_address',
        'land_description',
        'land_ownership_document',
        
        // Zone and Section (Admin Only - Filled upon approval)
        'zone',
        'section',
        
        // Construction-Specific Fields
        'property_type',
        'custom_property_type',
        'property_status',
        'estimated_bedrooms',
        'has_plans',
        'estimated_completion',
        'construction_documents',
        
        // Property Capture-Specific Fields
        'existing_property_type',
        'existing_custom_property_type',
        'existing_property_status',
        'existing_bedrooms',
        'existing_bathrooms',
        'year_built',
        'property_photos',
        'property_documents',
        
        // Tenant Information
        'has_tenants',
        'tenant_count',
        'tenant_data',
        
        // Status & Tracking
        'status',
        'access_token',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'approved_property_id',
        'cancelled_at',
        'rejection_reason',
        'admin_notes',
        'info_requested',
        'assigned_to',
        'assigned_at',
        
        // Archive Columns
        'is_archived',
        'archived_at',
        'archive_year',
        'archive_reason',
        'archived_by',
        
        // Payment Tracking
        'payment_status',
        'payment_reference',
        'paid_at',
        
        // Audit Fields
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'additional_phones' => 'array',
        'construction_documents' => 'array',
        'property_photos' => 'array',
        'property_documents' => 'array',
        'tenant_data' => 'array',
        'metadata' => 'array',
        'has_plans' => 'boolean',
        'has_tenants' => 'boolean',
        'is_archived' => 'boolean',
        'estimated_completion' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'assigned_at' => 'datetime',
        'archived_at' => 'datetime',
        'paid_at' => 'datetime',
        'duplicate_check_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the landlord user associated with this registration
     */
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the admin who is assigned to review this registration
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the admin who reviewed this registration
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the approved property created from this registration
     */
    public function approvedProperty()
    {
        return $this->belongsTo(Property::class, 'approved_property_id');
    }

    /**
     * Get the admin who archived this registration
     */
    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Get the documents for this registration
     */
    public function documents()
    {
        return $this->hasMany(RegistrationDocument::class, 'registration_id');
    }

    /**
     * Get the notes for this registration
     */
    public function notes()
    {
        return $this->hasMany(RegistrationNote::class, 'registration_id');
    }

    /**
     * Get the tenants for this registration
     */
    public function tenants()
    {
        return $this->hasMany(RegistrationTenant::class, 'registration_id');
    }

    /**
     * Get the activity log for this registration
     */
    public function activityLog()
    {
        return $this->hasMany(RegistrationActivityLog::class, 'registration_id');
    }

    // ========== DUPLICATE DETECTION METHODS ==========

    /**
     * Check if this registration is a duplicate
     * Returns true if there's another registration with the same hash or key fields
     */
    public function isDuplicate(): bool
    {
        // If there's a submission_hash, check if another registration has the same hash
        if ($this->submission_hash) {
            $exists = self::where('submission_hash', $this->submission_hash)
                ->where('id', '!=', $this->id)
                ->whereIn('status', [
                    self::STATUS_PENDING,
                    self::STATUS_APPROVED,
                    self::STATUS_IN_REVIEW,
                    self::STATUS_NEEDS_INFO
                ])
                ->exists();
            
            if ($exists) {
                return true;
            }
        }
        
        // Check by key fields (primary_phone, plot_number, property_name, registration_type)
        $exists = self::where('primary_phone', $this->primary_phone)
            ->where('plot_number', $this->plot_number)
            ->where('property_name', $this->property_name)
            ->where('registration_type', $this->registration_type)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_IN_REVIEW,
                self::STATUS_NEEDS_INFO
            ])
            ->exists();
        
        if ($exists) {
            return true;
        }
        
        // Check for recent rejected/cancelled (within 24 hours)
        $exists = self::where('primary_phone', $this->primary_phone)
            ->where('plot_number', $this->plot_number)
            ->where('property_name', $this->property_name)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_REJECTED,
                self::STATUS_CANCELLED
            ])
            ->where('updated_at', '>=', now()->subHours(24))
            ->exists();
        
        return $exists;
    }

    /**
     * Get the duplicate match method for this registration
     * Returns the method used to detect the duplicate
     */
    public function getDuplicateMatchMethodAttribute(): ?string
    {
        if (!$this->submission_hash) {
            return null;
        }
        
        // Check by submission hash
        $exists = self::where('submission_hash', $this->submission_hash)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_IN_REVIEW,
                self::STATUS_NEEDS_INFO
            ])
            ->first();
        
        if ($exists) {
            return 'submission_hash';
        }
        
        // Check by key fields
        $exists = self::where('primary_phone', $this->primary_phone)
            ->where('plot_number', $this->plot_number)
            ->where('property_name', $this->property_name)
            ->where('registration_type', $this->registration_type)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_IN_REVIEW,
                self::STATUS_NEEDS_INFO
            ])
            ->first();
        
        if ($exists) {
            return 'key_fields';
        }
        
        // Check for recent rejected/cancelled
        $exists = self::where('primary_phone', $this->primary_phone)
            ->where('plot_number', $this->plot_number)
            ->where('property_name', $this->property_name)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_REJECTED,
                self::STATUS_CANCELLED
            ])
            ->where('updated_at', '>=', now()->subHours(24))
            ->first();
        
        if ($exists) {
            return 'recent_rejected';
        }
        
        return null;
    }

    /**
     * Get the duplicate registration ID if this is a duplicate
     */
    public function getDuplicateRegistrationIdAttribute(): ?int
    {
        if (!$this->submission_hash) {
            return null;
        }
        
        // Check by submission hash
        $duplicate = self::where('submission_hash', $this->submission_hash)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_IN_REVIEW,
                self::STATUS_NEEDS_INFO
            ])
            ->first();
        
        if ($duplicate) {
            return $duplicate->id;
        }
        
        // Check by key fields
        $duplicate = self::where('primary_phone', $this->primary_phone)
            ->where('plot_number', $this->plot_number)
            ->where('property_name', $this->property_name)
            ->where('registration_type', $this->registration_type)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_IN_REVIEW,
                self::STATUS_NEEDS_INFO
            ])
            ->first();
        
        if ($duplicate) {
            return $duplicate->id;
        }
        
        return null;
    }

    /**
     * Get the duplicate registration if this is a duplicate
     */
    public function getDuplicateRegistrationAttribute(): ?self
    {
        if (!$this->submission_hash) {
            return null;
        }
        
        // Check by submission hash
        $duplicate = self::where('submission_hash', $this->submission_hash)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_IN_REVIEW,
                self::STATUS_NEEDS_INFO
            ])
            ->first();
        
        if ($duplicate) {
            return $duplicate;
        }
        
        // Check by key fields
        $duplicate = self::where('primary_phone', $this->primary_phone)
            ->where('plot_number', $this->plot_number)
            ->where('property_name', $this->property_name)
            ->where('registration_type', $this->registration_type)
            ->where('id', '!=', $this->id)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_IN_REVIEW,
                self::STATUS_NEEDS_INFO
            ])
            ->first();
        
        if ($duplicate) {
            return $duplicate;
        }
        
        return null;
    }

    /**
     * Get all potential duplicates for this registration
     */
    public function getPotentialDuplicates(): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::where('id', '!=', $this->id);
        
        // Check by submission hash
        if ($this->submission_hash) {
            $query->orWhere('submission_hash', $this->submission_hash);
        }
        
        // Check by key fields
        $query->orWhere(function($q) {
            $q->where('primary_phone', $this->primary_phone)
                ->where('plot_number', $this->plot_number)
                ->where('property_name', $this->property_name)
                ->where('registration_type', $this->registration_type);
        });
        
        // Check by similar land description (if exists)
        if ($this->land_description) {
            $query->orWhere('land_description', 'LIKE', '%' . substr($this->land_description, 0, 50) . '%');
        }
        
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_IN_REVIEW,
            self::STATUS_NEEDS_INFO,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED
        ])->get();
    }

    /**
     * Check if this registration has any duplicate registration
     */
    public function hasDuplicateRegistration(): bool
    {
        return $this->getDuplicateRegistration() !== null;
    }

    /**
     * Get duplicate status label for display
     */
    public function getDuplicateStatusLabelAttribute(): string
    {
        if ($this->isDuplicate()) {
            $method = $this->duplicate_match_method;
            $labels = [
                'submission_hash' => 'Exact Match (Hash)',
                'key_fields' => 'Exact Match (Fields)',
                'recent_rejected' => 'Recent Rejected/Cancelled',
            ];
            return $labels[$method] ?? 'Potential Duplicate';
        }
        
        return 'Not a duplicate';
    }

    /**
     * Get duplicate status badge class for display
     */
    public function getDuplicateStatusBadgeClassAttribute(): string
    {
        if ($this->isDuplicate()) {
            return 'badge-warning';
        }
        return 'badge-success';
    }

    // ========== SCOPES ==========

    /**
     * Scope for construction type registrations
     */
    public function scopeConstruction($query)
    {
        return $query->where('registration_type', self::TYPE_CONSTRUCTION);
    }

    /**
     * Scope for property capture type registrations
     */
    public function scopePropertyCapture($query)
    {
        return $query->where('registration_type', self::TYPE_PROPERTY_CAPTURE);
    }

    /**
     * Scope for both purposes registrations
     */
    public function scopeBothPurposes($query)
    {
        return $query->where('purpose', self::PURPOSE_BOTH);
    }

    /**
     * Scope for pending registrations
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for in review registrations
     */
    public function scopeInReview($query)
    {
        return $query->where('status', self::STATUS_IN_REVIEW);
    }

    /**
     * Scope for approved registrations
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for rejected registrations
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope for needs info registrations
     */
    public function scopeNeedsInfo($query)
    {
        return $query->where('status', self::STATUS_NEEDS_INFO);
    }

    /**
     * Scope for cancelled registrations
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope for registrations assigned to a specific admin
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope for unassigned registrations
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Scope for registrations with tenants
     */
    public function scopeHasTenants($query)
    {
        return $query->where('has_tenants', true);
    }

    /**
     * Scope for registrations without tenants
     */
    public function scopeNoTenants($query)
    {
        return $query->where('has_tenants', false);
    }

    /**
     * Scope for registrations in a specific zone
     */
    public function scopeInZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }

    /**
     * Scope for registrations in a specific section
     */
    public function scopeInSection($query, $section)
    {
        return $query->where('section', $section);
    }

    /**
     * Scope for archived registrations
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Scope for non-archived (active) registrations
     */
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope for duplicate registrations
     */
    public function scopeDuplicates($query)
    {
        return $query->whereNotNull('submission_hash')
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_IN_REVIEW,
                self::STATUS_NEEDS_INFO
            ])
            ->whereExists(function($q) {
                $q->select(\DB::raw(1))
                    ->from('landlord_construction_registrations as dup')
                    ->whereRaw('dup.submission_hash = landlord_construction_registrations.submission_hash')
                    ->whereRaw('dup.id != landlord_construction_registrations.id');
            });
    }

    /**
     * Scope for registrations with under construction status
     */
    public function scopeUnderConstruction($query)
    {
        return $query->where('property_status', self::PROPERTY_STATUS_UNDER_CONSTRUCTION);
    }

    /**
     * Scope for registrations with completed construction
     */
    public function scopeCompletedConstruction($query)
    {
        return $query->where('property_status', self::PROPERTY_STATUS_COMPLETED);
    }

    /**
     * Scope for registrations with vacant land
     */
    public function scopeVacantLand($query)
    {
        return $query->where('property_status', self::PROPERTY_STATUS_VACANT);
    }

    /**
     * Scope for registrations with active existing property
     */
    public function scopeActiveExistingProperty($query)
    {
        return $query->whereIn('existing_property_status', [
            self::EXISTING_STATUS_ACTIVE, 
            self::EXISTING_STATUS_OCCUPIED
        ]);
    }

    // ========== ACCESSORS & MUTATORS ==========

    /**
     * Get all phones as array
     */
    public function getAllPhones(): array
    {
        $phones = [$this->primary_phone];
        
        if ($this->additional_phones && is_array($this->additional_phones)) {
            $phones = array_merge($phones, $this->additional_phones);
        }
        
        return array_values(array_filter($phones)); // Remove any empty values and reindex
    }

    /**
     * Get formatted property type for construction
     */
    public function getFormattedPropertyTypeAttribute(): string
    {
        if ($this->property_type === 'other' && $this->custom_property_type) {
            return $this->custom_property_type;
        }
        
        $types = [
            'residential' => 'Residential House',
            'apartment' => 'Apartment Building',
            'commercial' => 'Commercial Building',
            'mixed' => 'Mixed Use',
            'other' => 'Other',
        ];
        
        return $types[$this->property_type] ?? ucfirst(str_replace('_', ' ', $this->property_type));
    }

    /**
     * Get formatted property type for existing property
     */
    public function getFormattedExistingPropertyTypeAttribute(): string
    {
        if ($this->existing_property_type === 'other' && $this->existing_custom_property_type) {
            return $this->existing_custom_property_type;
        }
        
        $types = [
            'residential' => 'Residential House',
            'apartment' => 'Apartment Building',
            'commercial' => 'Commercial Building',
            'mixed' => 'Mixed Use',
            'other' => 'Other',
        ];
        
        return $types[$this->existing_property_type] ?? ucfirst(str_replace('_', ' ', $this->existing_property_type));
    }

    /**
     * Get formatted property status for display
     */
    public function getFormattedPropertyStatusAttribute(): string
    {
        $statusMap = [
            self::PROPERTY_STATUS_UNDER_CONSTRUCTION => 'Under Construction',
            self::PROPERTY_STATUS_COMPLETED => 'Completed',
            self::PROPERTY_STATUS_ACTIVE => 'Active',
            self::PROPERTY_STATUS_VACANT => 'Vacant',
            self::PROPERTY_STATUS_INACTIVE => 'Inactive',
        ];
        
        return $statusMap[$this->property_status] ?? ucfirst(str_replace('_', ' ', $this->property_status));
    }

    /**
     * Get formatted existing property status for display
     */
    public function getFormattedExistingPropertyStatusAttribute(): string
    {
        $statusMap = [
            self::EXISTING_STATUS_ACTIVE => 'Active',
            self::EXISTING_STATUS_OCCUPIED => 'Occupied',
            self::EXISTING_STATUS_VACANT => 'Vacant',
            self::EXISTING_STATUS_UNDER_MAINTENANCE => 'Under Maintenance',
            self::EXISTING_STATUS_INACTIVE => 'Inactive',
        ];
        
        return $statusMap[$this->existing_property_status] ?? ucfirst(str_replace('_', ' ', $this->existing_property_status));
    }

    /**
     * Get registration type label
     */
    public function getRegistrationTypeLabelAttribute(): string
    {
        return match($this->registration_type) {
            self::TYPE_CONSTRUCTION => 'Vacant Land / Construction',
            self::TYPE_PROPERTY_CAPTURE => 'Existing Property',
            default => ucfirst($this->registration_type),
        };
    }

    /**
     * Get purpose label
     */
    public function getPurposeLabelAttribute(): string
    {
        return match($this->purpose) {
            self::PURPOSE_CONSTRUCTION => 'Construction Only',
            self::PURPOSE_PERMANENT_REGISTRATION => 'Permanent Registration',
            self::PURPOSE_BOTH => 'Both Construction & Registration',
            default => ucfirst($this->purpose),
        };
    }

    /**
     * Get registration status badge class
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_IN_REVIEW => 'badge-info',
            self::STATUS_APPROVED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            self::STATUS_NEEDS_INFO => 'badge-secondary',
            self::STATUS_CANCELLED => 'badge-dark',
            default => 'badge-secondary',
        };
    }

    /**
     * Get registration status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_IN_REVIEW => 'In Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_NEEDS_INFO => 'Needs Information',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get property status badge class
     */
    public function getPropertyStatusBadgeClassAttribute(): string
    {
        return match($this->property_status) {
            self::PROPERTY_STATUS_UNDER_CONSTRUCTION => 'badge-warning',
            self::PROPERTY_STATUS_COMPLETED => 'badge-success',
            self::PROPERTY_STATUS_ACTIVE => 'badge-success',
            self::PROPERTY_STATUS_VACANT => 'badge-info',
            self::PROPERTY_STATUS_INACTIVE => 'badge-secondary',
            default => 'badge-secondary',
        };
    }

    /**
     * Get existing property status badge class
     */
    public function getExistingPropertyStatusBadgeClassAttribute(): string
    {
        return match($this->existing_property_status) {
            self::EXISTING_STATUS_ACTIVE => 'badge-success',
            self::EXISTING_STATUS_OCCUPIED => 'badge-success',
            self::EXISTING_STATUS_VACANT => 'badge-info',
            self::EXISTING_STATUS_UNDER_MAINTENANCE => 'badge-warning',
            self::EXISTING_STATUS_INACTIVE => 'badge-secondary',
            default => 'badge-secondary',
        };
    }

    /**
     * Get mapped property status badge class
     */
    public function getMappedStatusBadgeClassAttribute(): string
    {
        $mappedStatus = $this->mapped_property_status;
        
        return match($mappedStatus) {
            self::MAPPED_STATUS_ACTIVE => 'badge-success',
            self::MAPPED_STATUS_UNDER_CONSTRUCTION => 'badge-warning',
            self::MAPPED_STATUS_UNDER_MAINTENANCE => 'badge-warning',
            self::MAPPED_STATUS_VACANT => 'badge-info',
            self::MAPPED_STATUS_INACTIVE => 'badge-secondary',
            default => 'badge-secondary',
        };
    }

    /**
     * Get tenant count summary
     */
    public function getTenantSummaryAttribute(): string
    {
        if (!$this->has_tenants || !$this->tenant_count) {
            return 'No tenants';
        }
        
        return $this->tenant_count . ' tenant' . ($this->tenant_count > 1 ? 's' : '');
    }

    /**
     * Get detailed tenant summary with status counts
     */
    public function getDetailedTenantSummaryAttribute(): array
    {
        $tenants = $this->tenants;
        
        return [
            'total' => $tenants->count(),
            'pending' => $tenants->where('status', 'pending')->count(),
            'approved' => $tenants->where('status', 'approved')->count(),
            'rejected' => $tenants->where('status', 'rejected')->count(),
            'with_accounts' => $tenants->whereNotNull('user_id')->count(),
        ];
    }

    /**
     * Get days since submission
     */
    public function getDaysSinceSubmissionAttribute(): int
    {
        return $this->submitted_at ? $this->submitted_at->diffInDays(now()) : 0;
    }

    /**
     * Get days since assignment
     */
    public function getDaysSinceAssignmentAttribute(): ?int
    {
        return $this->assigned_at ? $this->assigned_at->diffInDays(now()) : null;
    }

    /**
     * Check if registration is overdue for review
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               $this->created_at->diffInDays(now()) > 7;
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [];
        
        if ($this->street_name) {
            $parts[] = $this->street_name;
        }
        
        if ($this->plot_number) {
            $parts[] = 'Plot ' . $this->plot_number;
        }
        
        if ($this->zone || $this->section) {
            $zoneSection = [];
            if ($this->zone) $zoneSection[] = 'Zone ' . $this->zone;
            if ($this->section) $zoneSection[] = 'Section ' . $this->section;
            $parts[] = implode(', ', $zoneSection);
        }
        
        if ($this->digital_address) {
            $parts[] = 'GPS: ' . $this->digital_address;
        }
        
        return implode(', ', $parts);
    }

    /**
     * Determine the correct property status for the created property
     * This maps registration statuses to the actual property statuses
     */
    public function getMappedPropertyStatusAttribute(): string
    {
        // If it's an existing property registration (property_capture)
        if ($this->isPropertyCapture()) {
            // Map the existing property status to the correct property status
            return match($this->existing_property_status) {
                self::EXISTING_STATUS_ACTIVE, 
                self::EXISTING_STATUS_OCCUPIED => self::MAPPED_STATUS_ACTIVE,
                self::EXISTING_STATUS_UNDER_MAINTENANCE => self::MAPPED_STATUS_UNDER_MAINTENANCE,
                self::EXISTING_STATUS_INACTIVE => self::MAPPED_STATUS_INACTIVE,
                self::EXISTING_STATUS_VACANT => self::MAPPED_STATUS_VACANT,
                default => self::MAPPED_STATUS_ACTIVE // Default for existing property
            };
        }
        
        // If it's a construction registration
        if ($this->isConstruction()) {
            return match($this->property_status) {
                self::PROPERTY_STATUS_UNDER_CONSTRUCTION => self::MAPPED_STATUS_UNDER_CONSTRUCTION,
                self::PROPERTY_STATUS_COMPLETED,
                self::PROPERTY_STATUS_ACTIVE => self::MAPPED_STATUS_ACTIVE,
                self::PROPERTY_STATUS_INACTIVE => self::MAPPED_STATUS_INACTIVE,
                self::PROPERTY_STATUS_VACANT => self::MAPPED_STATUS_VACANT,
                default => self::MAPPED_STATUS_VACANT
            };
        }
        
        // If it's a registration with both purposes
        if ($this->purpose === self::PURPOSE_BOTH) {
            // If they're doing both, check if they've provided construction details
            if ($this->property_status === self::PROPERTY_STATUS_UNDER_CONSTRUCTION) {
                return self::MAPPED_STATUS_UNDER_CONSTRUCTION;
            } elseif (in_array($this->property_status, [self::PROPERTY_STATUS_COMPLETED, self::PROPERTY_STATUS_ACTIVE])) {
                return self::MAPPED_STATUS_ACTIVE;
            } else {
                // They're registering vacant land that will be built later
                return self::MAPPED_STATUS_VACANT;
            }
        }
        
        // Default fallback
        return self::MAPPED_STATUS_VACANT;
    }

    /**
     * Check if this registration should create a property with "under_construction" status
     */
    public function getShouldBeUnderConstructionAttribute(): bool
    {
        return $this->mapped_property_status === self::MAPPED_STATUS_UNDER_CONSTRUCTION;
    }

    /**
     * Check if this registration should create a property with "active" status
     */
    public function getShouldBeActiveAttribute(): bool
    {
        return $this->mapped_property_status === self::MAPPED_STATUS_ACTIVE;
    }

    /**
     * Check if this registration should create a property with "vacant" status
     */
    public function getShouldBeVacantAttribute(): bool
    {
        return $this->mapped_property_status === self::MAPPED_STATUS_VACANT;
    }

    // ========== BUSINESS LOGIC METHODS ==========

    /**
     * Assign this registration to an admin
     */
    public function assignTo(int $adminId): bool
    {
        $this->assigned_to = $adminId;
        $this->assigned_at = now();
        
        if ($this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_IN_REVIEW;
        }
        
        $this->logActivity('assigned', "Registration assigned to admin ID: {$adminId}");
        
        return $this->save();
    }

    /**
     * Unassign this registration
     */
    public function unassign(): bool
    {
        $oldAssignee = $this->assigned_to;
        $this->assigned_to = null;
        $this->assigned_at = null;
        
        if ($this->status === self::STATUS_IN_REVIEW) {
            $this->status = self::STATUS_PENDING;
        }
        
        $this->logActivity('unassigned', "Unassigned from admin ID: {$oldAssignee}");
        
        return $this->save();
    }

    /**
     * Approve this registration
     */
    public function approve(int $reviewerId, ?int $propertyId = null): bool
    {
        $this->status = self::STATUS_APPROVED;
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = now();
        
        if ($propertyId) {
            $this->approved_property_id = $propertyId;
        }
        
        $mappedStatus = $this->mapped_property_status;
        $this->logActivity('approved', "Registration approved by admin ID: {$reviewerId}" . 
            ($propertyId ? " with property ID: {$propertyId}" : '') . 
            " (Property status will be: {$mappedStatus})");
        
        return $this->save();
    }

    /**
     * Reject this registration
     */
    public function reject(int $reviewerId, string $reason): bool
    {
        $this->status = self::STATUS_REJECTED;
        $this->reviewed_by = $reviewerId;
        $this->reviewed_at = now();
        $this->rejection_reason = $reason;
        
        $this->logActivity('rejected', "Registration rejected by admin ID: {$reviewerId}. Reason: {$reason}");
        
        return $this->save();
    }

    /**
     * Request more information
     */
    public function requestInfo(int $reviewerId, string $message): bool
    {
        $this->status = self::STATUS_NEEDS_INFO;
        $this->info_requested = $message;
        
        $this->logActivity('info_requested', "Additional information requested by admin ID: {$reviewerId}. Message: {$message}");
        
        return $this->save();
    }

    /**
     * Archive this registration
     */
    public function archive(?int $archivedBy = null, ?string $reason = null): bool
    {
        $this->is_archived = true;
        $this->archived_at = now();
        $this->archive_year = now()->year;
        $this->archived_by = $archivedBy ?? auth()->id();
        $this->archive_reason = $reason ?? 'Manual archive operation';
        
        $this->logActivity('archived', "Registration archived by " . ($this->archived_by ? User::find($this->archived_by)?->name ?? 'System' : 'System'));
        
        return $this->save();
    }

    /**
     * Restore this registration from archive
     */
    public function restoreFromArchive(): bool
    {
        $this->is_archived = false;
        $this->archived_at = null;
        $this->archive_year = null;
        $this->archived_by = null;
        $this->archive_reason = null;
        
        $this->logActivity('restored', "Registration restored from archive by " . auth()->user()?->name ?? 'System');
        
        return $this->save();
    }

    /**
     * Add an internal note
     */
    public function addInternalNote(string $note, int $userId)
    {
        $noteModel = $this->notes()->create([
            'content' => $note,
            'user_id' => $userId,
            'is_internal' => true,
        ]);
        
        $this->logActivity('note_added', "Internal note added by user ID: {$userId}");
        
        return $noteModel;
    }

    /**
     * Add a public note
     */
    public function addPublicNote(string $note, int $userId)
    {
        $noteModel = $this->notes()->create([
            'content' => $note,
            'user_id' => $userId,
            'is_internal' => false,
        ]);
        
        $this->logActivity('note_added', "Public note added by user ID: {$userId}");
        
        return $noteModel;
    }

    /**
     * Add tenant data from various sources
     */
    public function addTenants(array $tenants): bool
    {
        if (empty($tenants)) {
            return false;
        }

        $this->has_tenants = true;
        $this->tenant_count = count($tenants);
        $this->tenant_data = $tenants;
        
        // Create individual tenant records
        foreach ($tenants as $tenant) {
            // Check if tenant already exists to avoid duplicates
            $existingTenant = $this->tenants()
                ->where('phone', $tenant['phone'])
                ->where('name', $tenant['name'])
                ->first();
            
            if (!$existingTenant) {
                $this->tenants()->create([
                    'name' => $tenant['name'],
                    'phone' => $tenant['phone'],
                    'email' => $tenant['email'] ?? null,
                    'notes' => $tenant['notes'] ?? null,
                    'status' => 'pending',
                ]);
            }
        }
        
        $this->logActivity('tenants_added', count($tenants) . ' tenant(s) added');
        
        return $this->save();
    }

    /**
     * Add a single tenant
     */
    public function addTenant(array $tenant): bool
    {
        return $this->addTenants([$tenant]);
    }

    /**
     * Get pending tenants
     */
    public function getPendingTenants()
    {
        return $this->tenants()->where('status', 'pending')->get();
    }

    /**
     * Get approved tenants
     */
    public function getApprovedTenants()
    {
        return $this->tenants()->where('status', 'approved')->get();
    }

    /**
     * Get rejected tenants
     */
    public function getRejectedTenants()
    {
        return $this->tenants()->where('status', 'rejected')->get();
    }

    /**
     * Check if all tenants are approved
     */
    public function allTenantsApproved(): bool
    {
        return $this->tenants()->where('status', '!=', 'approved')->count() === 0;
    }

    /**
     * Check if any tenants are pending
     */
    public function hasPendingTenants(): bool
    {
        return $this->tenants()->where('status', 'pending')->exists();
    }

    /**
     * Get tenant statistics
     */
    public function getTenantStats(): array
    {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'with_accounts' => 0,
        ];

        foreach ($this->tenants as $tenant) {
            $stats['total']++;
            $stats[$tenant->status]++;
            if ($tenant->user_id) {
                $stats['with_accounts']++;
            }
        }

        return $stats;
    }

    /**
     * Migrate legacy tenant data from JSON to individual records
     */
    public function migrateLegacyTenants(): array
    {
        $migrated = [];
        
        if ($this->tenant_data && is_array($this->tenant_data) && $this->tenants()->count() === 0) {
            foreach ($this->tenant_data as $tenantData) {
                if (isset($tenantData['name']) && isset($tenantData['phone'])) {
                    $tenant = $this->tenants()->create([
                        'name' => $tenantData['name'],
                        'phone' => $tenantData['phone'],
                        'email' => $tenantData['email'] ?? null,
                        'notes' => $tenantData['notes'] ?? null,
                        'status' => 'pending',
                    ]);
                    $migrated[] = $tenant;
                }
            }
            
            $this->logActivity('tenants_migrated', count($migrated) . ' tenant(s) migrated from legacy data');
        }
        
        return $migrated;
    }

    /**
     * Log activity with safety checks
     * Only creates activity log if the model exists in database
     */
    public function logActivity(string $action, string $description): ?RegistrationActivityLog
    {
        // Only try to log if this model exists in database (has an ID)
        if (!$this->exists) {
            Log::info('Skipping activity log for unsaved registration', [
                'action' => $action,
                'description' => $description
            ]);
            return null;
        }
        
        try {
            return $this->activityLog()->create([
                'action' => $action,
                'description' => $description,
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Log the error but don't throw - we don't want activity logging
            // to break the main operation
            Log::error('Failed to log activity: ' . $e->getMessage(), [
                'registration_id' => $this->id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if this is a construction type registration
     */
    public function isConstruction(): bool
    {
        return $this->registration_type === self::TYPE_CONSTRUCTION || 
               $this->purpose === self::PURPOSE_CONSTRUCTION ||
               $this->purpose === self::PURPOSE_BOTH;
    }

    /**
     * Check if this is a property capture type registration
     */
    public function isPropertyCapture(): bool
    {
        return $this->registration_type === self::TYPE_PROPERTY_CAPTURE || 
               $this->purpose === self::PURPOSE_PERMANENT_REGISTRATION ||
               $this->purpose === self::PURPOSE_BOTH;
    }

    /**
     * Check if registration has tenants
     */
    public function hasTenants(): bool
    {
        return $this->has_tenants && $this->tenant_count > 0;
    }

    /**
     * Check if zone and section are assigned
     */
    public function hasZoneAndSection(): bool
    {
        return !empty($this->zone) && !empty($this->section);
    }

    /**
     * Check if this is an under construction registration
     */
    public function isUnderConstruction(): bool
    {
        return $this->isConstruction() && 
               $this->property_status === self::PROPERTY_STATUS_UNDER_CONSTRUCTION;
    }

    /**
     * Check if construction is completed
     */
    public function isConstructionCompleted(): bool
    {
        return $this->isConstruction() && 
               in_array($this->property_status, [self::PROPERTY_STATUS_COMPLETED, self::PROPERTY_STATUS_ACTIVE]);
    }

    /**
     * Check if property is vacant land
     */
    public function isVacantLand(): bool
    {
        return $this->isConstruction() && 
               $this->property_status === self::PROPERTY_STATUS_VACANT;
    }

    /**
     * Get construction progress summary
     */
    public function getConstructionProgressAttribute(): string
    {
        if (!$this->isConstruction()) {
            return 'Not applicable';
        }

        return match($this->property_status) {
            self::PROPERTY_STATUS_UNDER_CONSTRUCTION => '🏗️ Under Construction',
            self::PROPERTY_STATUS_COMPLETED => '✅ Construction Completed',
            self::PROPERTY_STATUS_ACTIVE => '✅ Construction Completed',
            self::PROPERTY_STATUS_VACANT => '🌱 Vacant Land',
            self::PROPERTY_STATUS_INACTIVE => '⏸️ Inactive',
            default => ucfirst(str_replace('_', ' ', $this->property_status)),
        };
    }

    /**
     * Get the appropriate icon for the registration type
     */
    public function getTypeIconAttribute(): string
    {
        if ($this->isPropertyCapture()) {
            return 'fa-building';
        }
        
        if ($this->isUnderConstruction()) {
            return 'fa-hard-hat';
        }
        
        if ($this->isVacantLand()) {
            return 'fa-map-marked-alt';
        }
        
        return 'fa-file-alt';
    }

    // ========== BOOT METHOD ==========

    /**
     * Boot the model with improved event handling
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($registration) {
            // Set default status if not provided
            if (empty($registration->status)) {
                $registration->status = self::STATUS_PENDING;
            }
            
            // Set submitted_at if not provided
            if (empty($registration->submitted_at)) {
                $registration->submitted_at = now();
            }
        });

        static::created(function ($registration) {
            // Let the controller handle activity logging after commit
            Log::info('Registration created with ID: ' . $registration->id);
        });

        static::updating(function ($registration) {
            // Log status changes - but only if the model already exists
            if ($registration->exists && $registration->isDirty('status')) {
                $oldStatus = $registration->getOriginal('status');
                $newStatus = $registration->status;
                $registration->logActivity('status_changed', "Status changed from {$oldStatus} to {$newStatus}");
            }
            
            // Log property status changes for construction
            if ($registration->exists && $registration->isDirty('property_status')) {
                $oldStatus = $registration->getOriginal('property_status');
                $newStatus = $registration->property_status;
                $registration->logActivity('construction_status_changed', 
                    "Construction status changed from {$oldStatus} to {$newStatus}");
            }
            
            // Log existing property status changes
            if ($registration->exists && $registration->isDirty('existing_property_status')) {
                $oldStatus = $registration->getOriginal('existing_property_status');
                $newStatus = $registration->existing_property_status;
                $registration->logActivity('property_status_changed', 
                    "Property status changed from {$oldStatus} to {$newStatus}");
            }
        });

        static::deleting(function ($registration) {
            // When soft deleting, also soft delete related records
            if (!$registration->isForceDeleting()) {
                $registration->documents()->delete();
                $registration->notes()->delete();
                $registration->tenants()->delete();
                $registration->activityLog()->delete();
            }
        });

        static::restoring(function ($registration) {
            // When restoring, also restore related records
            $registration->documents()->withTrashed()->restore();
            $registration->notes()->withTrashed()->restore();
            $registration->tenants()->withTrashed()->restore();
            $registration->activityLog()->withTrashed()->restore();
        });

        static::forceDeleting(function ($registration) {
            // When force deleting, permanently delete related records and files
            foreach ($registration->documents as $document) {
                // Delete file from storage
                if ($document->file_path && \Storage::disk('public')->exists($document->file_path)) {
                    \Storage::disk('public')->delete($document->file_path);
                }
                $document->forceDelete();
            }
            
            // Delete construction documents files
            if ($registration->construction_documents && is_array($registration->construction_documents)) {
                foreach ($registration->construction_documents as $filePath) {
                    if (\Storage::disk('public')->exists($filePath)) {
                        \Storage::disk('public')->delete($filePath);
                    }
                }
            }
            
            // Delete property photos files
            if ($registration->property_photos && is_array($registration->property_photos)) {
                foreach ($registration->property_photos as $filePath) {
                    if (\Storage::disk('public')->exists($filePath)) {
                        \Storage::disk('public')->delete($filePath);
                    }
                }
            }
            
            // Delete property documents files
            if ($registration->property_documents && is_array($registration->property_documents)) {
                foreach ($registration->property_documents as $filePath) {
                    if (\Storage::disk('public')->exists($filePath)) {
                        \Storage::disk('public')->delete($filePath);
                    }
                }
            }
            
            // Delete land ownership document
            if ($registration->land_ownership_document && \Storage::disk('public')->exists($registration->land_ownership_document)) {
                \Storage::disk('public')->delete($registration->land_ownership_document);
            }
            
            $registration->notes()->forceDelete();
            $registration->tenants()->forceDelete();
            $registration->activityLog()->forceDelete();
        });
    }
}