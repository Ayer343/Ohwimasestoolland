<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Property relationship
        'property_id',

        // Unit identification
        'unit_number',
        'unit_name',
        'unit_type',

        // Unit details
        'description',
        'floor_area',
        'bedrooms',
        'bathrooms',
        'living_rooms',
        'kitchens',
        'amenities',

        // Rental information
        'monthly_rent',
        'security_deposit',
        'current_rent_amount',
        'is_furnished',

        // Tenant assignment with approval workflow
        'tenant_id',
        'tenant_approved_by',
        'tenant_status',
        'tenant_type',
        'tenant_move_in_date',
        'tenant_move_out_date',
        'tenant_approved_at',
        'tenant_approval_notes',
        'tenant_requested_at',
        'tenant_requested_by',
        'tenant_documents',
        'tenant_details',
        'proposed_rent',
        'tenant_resubmission_allowed',
        'tenant_resubmission_notes',
        'invitation_channels',
        'property_condition',
        'cleaning_required',
        'damages_noted',
        'tenant_vacate_reason',
        'termination_details',

        // Lease information
        'current_lease_id',
        'lease_start_date',
        'lease_end_date',

        // Status and tracking
        'status',
        'is_available',
        'available_from',
        'last_maintenance_date',
        'maintenance_history',

        // Archive and trash
        'is_archived',
        'archived_at',
        'archived_by',

        // Security deposit refund
        'security_deposit_refunded',
        'security_deposit_deduction_reason',

        // Creator
        'created_by',
    ];

    protected $casts = [
        // Financial
        'monthly_rent'                  => 'decimal:2',
        'security_deposit'              => 'decimal:2',
        'current_rent_amount'           => 'decimal:2',
        'proposed_rent'                 => 'decimal:2',
        'security_deposit_refunded'     => 'decimal:2',
        'floor_area'                    => 'decimal:2',

        // Counts
        'bedrooms'                      => 'integer',
        'bathrooms'                     => 'integer',
        'living_rooms'                  => 'integer',
        'kitchens'                      => 'integer',

        // Booleans
        'is_available'                  => 'boolean',
        'is_furnished'                  => 'boolean',
        'is_archived'                   => 'boolean',
        'cleaning_required'             => 'boolean',
        'tenant_resubmission_allowed'   => 'boolean',

        // Arrays/JSON
        'amenities'                     => 'array',
        'tenant_documents'              => 'array',
        'tenant_details'                => 'array',
        'invitation_channels'           => 'array',
        'termination_details'           => 'array',
        'maintenance_history'           => 'array',

        // Dates
        'tenant_move_in_date'           => 'date',
        'tenant_move_out_date'          => 'date',
        'lease_start_date'              => 'date',
        'lease_end_date'                => 'date',
        'available_from'                => 'date',
        'last_maintenance_date'         => 'datetime',
        'tenant_approved_at'            => 'datetime',
        'tenant_requested_at'           => 'datetime',
        'archived_at'                   => 'datetime',
        'created_at'                    => 'datetime',
        'updated_at'                    => 'datetime',
        'deleted_at'                    => 'datetime',
    ];

    protected $appends = [
        'full_unit_identifier',
        'rent_formatted',
        'security_deposit_formatted',
        'status_badge',
        'tenant_status_badge',
        'is_ready_for_occupancy',
        'current_tenant_name',
        'floor_area_formatted',
        'amenities_list',
        'tenant_email',
        'tenant_phone',
        'tenant_move_in_date_formatted',
        'tenant_move_out_date_formatted',
        'has_pending_invitation',
        'is_occupied',
        'is_reserved',
        'is_under_maintenance',
        'lease_duration_months',

        // ✅ INVOICE: New appended attributes
        'outstanding_balance',
        'outstanding_balance_formatted',
        'total_invoiced',
        'total_paid_amount',
        'overdue_invoice_count',
        'has_outstanding_balance',
        'invoice_summary',

        // ✅ GHANA: Advance-rent phase info from current lease
        'current_phase',
        'has_advance_rent',
        'advance_rent_remaining_months',
    ];

    // Unit status constants
    const STATUS_AVAILABLE          = 'available';
    const STATUS_RESERVED           = 'reserved';
    const STATUS_OCCUPIED           = 'occupied';
    const STATUS_UNDER_MAINTENANCE  = 'under_maintenance';
    const STATUS_UNAVAILABLE        = 'unavailable';

    // Unit type constants
    const TYPE_APARTMENT = 'apartment';
    const TYPE_ROOM      = 'room';
    const TYPE_STUDIO    = 'studio';
    const TYPE_OFFICE    = 'office';
    const TYPE_SHOP      = 'shop';
    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_OTHER     = 'other';

    // Tenant status constants
    const TENANT_STATUS_PENDING_APPROVAL = 'pending_approval';
    const TENANT_STATUS_APPROVED         = 'approved';
    const TENANT_STATUS_REJECTED         = 'rejected';
    const TENANT_STATUS_VACATED          = 'vacated';
    const TENANT_STATUS_TERMINATED       = 'terminated';

    // Tenant type constants
    const TENANT_TYPE_NEW      = 'new';
    const TENANT_TYPE_EXISTING = 'existing';

    // ========== RELATIONSHIPS ==========

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_approved_by');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_requested_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function currentLease(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class, 'current_lease_id');
    }

    public function rentalAgreements(): HasMany
    {
        return $this->hasMany(RentalAgreement::class, 'unit_id');
    }

    public function leases(): HasMany
    {
        return $this->rentalAgreements();
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'unit_id');
    }

    /**
     * ✅ INVOICE: Property unit invoices (was App\Models\Invoice).
     * This is the canonical relationship for all invoice data on this unit.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(PropertyUnitInvoice::class, 'unit_id');
    }

    /**
     * ✅ INVOICE: Invoices tied specifically to the current lease.
     */
    public function currentLeaseInvoices(): HasMany
    {
        return $this->invoices()
            ->where('lease_id', $this->current_lease_id);
    }

    /**
     * ✅ INVOICE: Outstanding invoices (pending, partial, overdue).
     */
    public function outstandingInvoices(): HasMany
    {
        return $this->invoices()->whereIn('status', [
            PropertyUnitInvoice::STATUS_PENDING,
            PropertyUnitInvoice::STATUS_PARTIAL,
            PropertyUnitInvoice::STATUS_OVERDUE,
        ]);
    }

    /**
     * ✅ INVOICE: Overdue invoices only.
     */
    public function overdueInvoices(): HasMany
    {
        return $this->invoices()->where('status', PropertyUnitInvoice::STATUS_OVERDUE);
    }

    /**
     * ✅ INVOICE: Paid invoices only.
     */
    public function paidInvoices(): HasMany
    {
        return $this->invoices()->where('status', PropertyUnitInvoice::STATUS_PAID);
    }

    /**
     * ✅ INVOICE: Advance-rent invoices.
     */
    public function advanceRentInvoices(): HasMany
    {
        return $this->invoices()->where('invoice_type', PropertyUnitInvoice::TYPE_ADVANCE_RENT);
    }

    /**
     * ✅ INVOICE: Monthly rent invoices.
     */
    public function monthlyRentInvoices(): HasMany
    {
        return $this->invoices()->where('invoice_type', PropertyUnitInvoice::TYPE_MONTHLY_RENT);
    }

    /**
     * ✅ INVOICE: Security deposit invoices.
     */
    public function securityDepositInvoices(): HasMany
    {
        return $this->invoices()->where('invoice_type', PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT);
    }

    public function tenantInvitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class, 'unit_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'unit_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'unit_id');
    }

    // ========== EXISTING ACCESSORS ==========

    public function getFullUnitIdentifierAttribute(): string
    {
        $propertyName = $this->property->property_name ?? 'Unknown Property';
        $unitIdentifier = $this->unit_name
            ? "{$this->unit_name} ({$this->unit_number})"
            : "Unit {$this->unit_number}";

        return "{$propertyName} - {$unitIdentifier}";
    }

    public function getRentFormattedAttribute(): string
    {
        $amount = $this->current_rent_amount ?? $this->monthly_rent;
        return '₵' . number_format($amount, 2);
    }

    public function getSecurityDepositFormattedAttribute(): ?string
    {
        if (!$this->security_deposit) {
            return null;
        }
        return '₵' . number_format($this->security_deposit, 2);
    }

    public function getFloorAreaFormattedAttribute(): ?string
    {
        if (!$this->floor_area) {
            return null;
        }
        return number_format($this->floor_area, 2) . ' sqm';
    }

    public function getTenantMoveInDateFormattedAttribute(): ?string
    {
        return $this->tenant_move_in_date ? $this->tenant_move_in_date->format('M d, Y') : null;
    }

    public function getTenantMoveOutDateFormattedAttribute(): ?string
    {
        return $this->tenant_move_out_date ? $this->tenant_move_out_date->format('M d, Y') : null;
    }

    public function getTenantEmailAttribute(): ?string
    {
        if ($this->tenant_type === self::TENANT_TYPE_NEW && !empty($this->tenant_details)) {
            return $this->tenant_details['email'] ?? null;
        }
        return $this->tenant->email ?? null;
    }

    public function getTenantPhoneAttribute(): ?string
    {
        if ($this->tenant_type === self::TENANT_TYPE_NEW && !empty($this->tenant_details)) {
            return $this->tenant_details['phone'] ?? null;
        }
        return $this->tenant->phone ?? null;
    }

    public function getCurrentTenantNameAttribute(): ?string
    {
        if ($this->tenant_type === self::TENANT_TYPE_NEW && !empty($this->tenant_details)) {
            return $this->tenant_details['name'] ?? null;
        }
        return $this->tenant->name ?? null;
    }

    public function getStatusBadgeAttribute(): array
    {
        $statusInfo = [
            'class' => '',
            'text'  => ucfirst(str_replace('_', ' ', $this->status)),
            'icon'  => 'fas fa-circle',
            'color' => '#6c757d',
        ];

        switch ($this->status) {
            case self::STATUS_AVAILABLE:
                $statusInfo['class'] = 'success';
                $statusInfo['icon']  = 'fas fa-check-circle';
                $statusInfo['color'] = '#28a745';
                $statusInfo['text']  = 'Available';
                break;
            case self::STATUS_RESERVED:
                $statusInfo['class'] = 'info';
                $statusInfo['icon']  = 'fas fa-clock';
                $statusInfo['color'] = '#17a2b8';
                $statusInfo['text']  = 'Reserved';
                break;
            case self::STATUS_OCCUPIED:
                $statusInfo['class'] = 'primary';
                $statusInfo['icon']  = 'fas fa-user';
                $statusInfo['color'] = '#007bff';
                $statusInfo['text']  = 'Occupied';
                break;
            case self::STATUS_UNDER_MAINTENANCE:
                $statusInfo['class'] = 'warning';
                $statusInfo['icon']  = 'fas fa-tools';
                $statusInfo['color'] = '#ffc107';
                $statusInfo['text']  = 'Under Maintenance';
                break;
            case self::STATUS_UNAVAILABLE:
                $statusInfo['class'] = 'secondary';
                $statusInfo['icon']  = 'fas fa-ban';
                $statusInfo['color'] = '#6c757d';
                $statusInfo['text']  = 'Unavailable';
                break;
        }

        return $statusInfo;
    }

    public function getTenantStatusBadgeAttribute(): ?array
    {
        if (!$this->tenant_status) {
            return null;
        }

        $statusInfo = [
            'class' => '',
            'text'  => ucfirst(str_replace('_', ' ', $this->tenant_status)),
            'icon'  => 'fas fa-circle',
            'color' => '#6c757d',
        ];

        switch ($this->tenant_status) {
            case self::TENANT_STATUS_PENDING_APPROVAL:
                $statusInfo['class'] = 'warning';
                $statusInfo['icon']  = 'fas fa-clock';
                $statusInfo['color'] = '#ffc107';
                $statusInfo['text']  = 'Pending Approval';
                break;
            case self::TENANT_STATUS_APPROVED:
                $statusInfo['class'] = 'success';
                $statusInfo['icon']  = 'fas fa-check-circle';
                $statusInfo['color'] = '#28a745';
                $statusInfo['text']  = 'Tenant Approved';
                break;
            case self::TENANT_STATUS_REJECTED:
                $statusInfo['class'] = 'danger';
                $statusInfo['icon']  = 'fas fa-times-circle';
                $statusInfo['color'] = '#dc3545';
                $statusInfo['text']  = 'Tenant Rejected';
                break;
            case self::TENANT_STATUS_VACATED:
                $statusInfo['class'] = 'info';
                $statusInfo['icon']  = 'fas fa-sign-out-alt';
                $statusInfo['color'] = '#17a2b8';
                $statusInfo['text']  = 'Tenant Vacated';
                break;
            case self::TENANT_STATUS_TERMINATED:
                $statusInfo['class'] = 'danger';
                $statusInfo['icon']  = 'fas fa-user-slash';
                $statusInfo['color'] = '#dc3545';
                $statusInfo['text']  = 'Tenant Terminated';
                break;
        }

        return $statusInfo;
    }

    public function getAmenitiesListAttribute(): array
    {
        if (empty($this->amenities)) {
            return [];
        }

        $amenityLabels = [
            'parking'            => 'Parking Space',
            'balcony'            => 'Balcony',
            'air_conditioning'   => 'Air Conditioning',
            'furnished'          => 'Fully Furnished',
            'wifi'               => 'Wi-Fi',
            'security'           => '24/7 Security',
            'gym'                => 'Gym Access',
            'pool'               => 'Swimming Pool',
            'laundry'            => 'Laundry Facility',
            'elevator'           => 'Elevator',
            'generator'          => 'Backup Generator',
            'cctv'               => 'CCTV Surveillance',
            'fire_safety'        => 'Fire Safety System',
            'water_heater'       => 'Water Heater',
            'kitchen_appliances' => 'Kitchen Appliances',
        ];

        return array_map(function ($amenity) use ($amenityLabels) {
            return $amenityLabels[$amenity] ?? ucfirst(str_replace('_', ' ', $amenity));
        }, $this->amenities);
    }

    public function getHasPendingInvitationAttribute(): bool
    {
        return $this->tenantInvitations()
            ->where('status', TenantInvitation::STATUS_PENDING)
            ->exists();
    }

    public function getIsOccupiedAttribute(): bool
    {
        return $this->status === self::STATUS_OCCUPIED
            && $this->tenant_status === self::TENANT_STATUS_APPROVED;
    }

    public function getIsReservedAttribute(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    public function getIsUnderMaintenanceAttribute(): bool
    {
        return $this->status === self::STATUS_UNDER_MAINTENANCE;
    }

    public function getLeaseDurationMonthsAttribute(): ?int
    {
        if (!$this->lease_start_date || !$this->lease_end_date) {
            return null;
        }
        return $this->lease_start_date->diffInMonths($this->lease_end_date);
    }

    public function getIsReadyForOccupancyAttribute(): bool
    {
        return $this->status === self::STATUS_AVAILABLE
            && $this->is_available
            && ($this->tenant_status === null
                || $this->tenant_status === self::TENANT_STATUS_VACATED
                || $this->tenant_status === self::TENANT_STATUS_TERMINATED);
    }

    // ========== ✅ INVOICE: NEW ACCESSORS ==========

    /**
     * ✅ INVOICE: Total amount invoiced to this unit (all time).
     */
    public function getTotalInvoicedAttribute(): float
    {
        if (!$this->relationLoaded('invoices')) {
            return (float) $this->invoices()->sum('amount');
        }
        return (float) $this->invoices->sum('amount');
    }

    /**
     * ✅ INVOICE: Total amount paid to this unit (all time).
     */
    public function getTotalPaidAmountAttribute(): float
    {
        if (!$this->relationLoaded('invoices')) {
            return (float) $this->invoices()->sum('amount_paid');
        }
        return (float) $this->invoices->sum('amount_paid');
    }

    /**
     * ✅ INVOICE: Outstanding balance across all invoices.
     */
    public function getOutstandingBalanceAttribute(): float
    {
        if ($this->relationLoaded('invoices')) {
            return (float) $this->invoices
                ->whereIn('status', [
                    PropertyUnitInvoice::STATUS_PENDING,
                    PropertyUnitInvoice::STATUS_PARTIAL,
                    PropertyUnitInvoice::STATUS_OVERDUE,
                ])
                ->sum(fn ($inv) => $inv->amount - $inv->amount_paid);
        }

        return (float) $this->invoices()
            ->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
                PropertyUnitInvoice::STATUS_OVERDUE,
            ])
            ->sum(DB::raw('amount - amount_paid'));
    }

    /**
     * ✅ INVOICE: Formatted outstanding balance.
     */
    public function getOutstandingBalanceFormattedAttribute(): string
    {
        return '₵' . number_format($this->outstanding_balance, 2);
    }

    /**
     * ✅ INVOICE: Count of overdue invoices.
     */
    public function getOverdueInvoiceCountAttribute(): int
    {
        if ($this->relationLoaded('invoices')) {
            return $this->invoices
                ->where('status', PropertyUnitInvoice::STATUS_OVERDUE)
                ->count();
        }
        return $this->invoices()
            ->where('status', PropertyUnitInvoice::STATUS_OVERDUE)
            ->count();
    }

    /**
     * ✅ INVOICE: Does this unit have any outstanding balance?
     */
    public function getHasOutstandingBalanceAttribute(): bool
    {
        return $this->outstanding_balance > 0;
    }

    /**
     * ✅ INVOICE: Full invoice summary for dashboards.
     * Returns counts + totals per invoice type + status breakdown.
     */
    public function getInvoiceSummaryAttribute(): array
    {
        $invoices = $this->relationLoaded('invoices')
            ? $this->invoices
            : $this->invoices()->get();

        return [
            'total_invoiced'    => (float) $invoices->sum('amount'),
            'total_paid'        => (float) $invoices->sum('amount_paid'),
            'outstanding'       => (float) $invoices
                ->whereIn('status', [
                    PropertyUnitInvoice::STATUS_PENDING,
                    PropertyUnitInvoice::STATUS_PARTIAL,
                    PropertyUnitInvoice::STATUS_OVERDUE,
                ])
                ->sum(fn ($inv) => $inv->amount - $inv->amount_paid),
            'counts' => [
                'total'     => $invoices->count(),
                'paid'      => $invoices->where('status', PropertyUnitInvoice::STATUS_PAID)->count(),
                'pending'   => $invoices->where('status', PropertyUnitInvoice::STATUS_PENDING)->count(),
                'partial'   => $invoices->where('status', PropertyUnitInvoice::STATUS_PARTIAL)->count(),
                'overdue'   => $invoices->where('status', PropertyUnitInvoice::STATUS_OVERDUE)->count(),
                'void'      => $invoices->where('status', PropertyUnitInvoice::STATUS_VOID)->count(),
            ],
            'by_type' => [
                'advance_rent'     => (float) $invoices
                    ->where('invoice_type', PropertyUnitInvoice::TYPE_ADVANCE_RENT)
                    ->sum('amount'),
                'monthly_rent'     => (float) $invoices
                    ->where('invoice_type', PropertyUnitInvoice::TYPE_MONTHLY_RENT)
                    ->sum('amount'),
                'security_deposit' => (float) $invoices
                    ->where('invoice_type', PropertyUnitInvoice::TYPE_SECURITY_DEPOSIT)
                    ->sum('amount'),
                'utility_deposit'  => (float) $invoices
                    ->where('invoice_type', PropertyUnitInvoice::TYPE_UTILITY_DEPOSIT)
                    ->sum('amount'),
                'late_fee'         => (float) $invoices
                    ->where('invoice_type', PropertyUnitInvoice::TYPE_LATE_FEE)
                    ->sum('amount'),
            ],
            'overdue_count' => $invoices
                ->where('status', PropertyUnitInvoice::STATUS_OVERDUE)
                ->count(),
        ];
    }

    // ========== ✅ GHANA: ADVANCE-RENT PHASE ACCESSORS ==========

    /**
     * ✅ GHANA: The current lease's payment phase ('advance' or 'monthly').
     * Returns null if no active lease.
     */
    public function getCurrentPhaseAttribute(): ?string
    {
        if (!$this->currentLease) {
            return null;
        }
        return $this->currentLease->current_phase;
    }

    /**
     * ✅ GHANA: Does the current lease use an advance-rent structure?
     */
    public function getHasAdvanceRentAttribute(): bool
    {
        return $this->currentLease
            && $this->currentLease->advance_rent_months > 0
            && $this->currentLease->advance_rent_amount > 0;
    }

    /**
     * ✅ GHANA: Months remaining in the current advance period.
     */
    public function getAdvanceRentRemainingMonthsAttribute(): int
    {
        if (!$this->currentLease) {
            return 0;
        }
        return $this->currentLease->advance_months_remaining;
    }

    // ========== STATIC OPTION LISTS ==========

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE         => 'Available',
            self::STATUS_RESERVED          => 'Reserved',
            self::STATUS_OCCUPIED          => 'Occupied',
            self::STATUS_UNDER_MAINTENANCE => 'Under Maintenance',
            self::STATUS_UNAVAILABLE       => 'Unavailable',
        ];
    }

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_APARTMENT => 'Apartment',
            self::TYPE_ROOM      => 'Room',
            self::TYPE_STUDIO    => 'Studio',
            self::TYPE_OFFICE    => 'Office',
            self::TYPE_SHOP      => 'Shop',
            self::TYPE_WAREHOUSE => 'Warehouse',
            self::TYPE_OTHER     => 'Other',
        ];
    }

    public static function getTenantStatusOptions(): array
    {
        return [
            self::TENANT_STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::TENANT_STATUS_APPROVED         => 'Approved',
            self::TENANT_STATUS_REJECTED         => 'Rejected',
            self::TENANT_STATUS_VACATED          => 'Vacated',
            self::TENANT_STATUS_TERMINATED       => 'Terminated',
        ];
    }

    public static function getTenantTypeOptions(): array
    {
        return [
            self::TENANT_TYPE_NEW      => 'New Tenant',
            self::TENANT_TYPE_EXISTING => 'Existing Tenant',
        ];
    }

    // ========== SCOPES ==========

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)
                    ->where('is_available', true);
    }

    public function scopeReserved($query)
    {
        return $query->where('status', self::STATUS_RESERVED);
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', self::STATUS_OCCUPIED)
                    ->where('tenant_status', self::TENANT_STATUS_APPROVED);
    }

    public function scopeUnderMaintenance($query)
    {
        return $query->where('status', self::STATUS_UNDER_MAINTENANCE);
    }

    public function scopeByProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('unit_type', $type);
    }

    public function scopePendingTenantApproval($query)
    {
        return $query->where('tenant_status', self::TENANT_STATUS_PENDING_APPROVAL);
    }

    public function scopeWithApprovedTenants($query)
    {
        return $query->where('tenant_status', self::TENANT_STATUS_APPROVED);
    }

    public function scopeWithVacatedTenants($query)
    {
        return $query->where('tenant_status', self::TENANT_STATUS_VACATED);
    }

    public function scopeWithTerminatedTenants($query)
    {
        return $query->where('tenant_status', self::TENANT_STATUS_TERMINATED);
    }

    public function scopeByTenantType($query, $type)
    {
        return $query->where('tenant_type', $type);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeWithPendingInvitations($query)
    {
        return $query->whereHas('tenantInvitations', function ($q) {
            $q->where('status', TenantInvitation::STATUS_PENDING);
        });
    }

    public function scopeWithoutTenant($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('tenant_id')
              ->orWhereIn('tenant_status', [
                  self::TENANT_STATUS_VACATED,
                  self::TENANT_STATUS_TERMINATED,
              ]);
        });
    }

    public function scopeByLandlord($query, $landlordId)
    {
        return $query->whereHas('property', function ($q) use ($landlordId) {
            $q->where('landlord_id', $landlordId);
        });
    }

    // ========== ✅ INVOICE: NEW SCOPES ==========

    /**
     * ✅ INVOICE: Units that have at least one outstanding invoice.
     */
    public function scopeWithOutstandingBalance($query)
    {
        return $query->whereHas('invoices', function ($q) {
            $q->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
                PropertyUnitInvoice::STATUS_OVERDUE,
            ])->whereRaw('amount > amount_paid');
        });
    }

    /**
     * ✅ INVOICE: Units with no outstanding balance.
     */
    public function scopeWithoutOutstandingBalance($query)
    {
        return $query->whereDoesntHave('invoices', function ($q) {
            $q->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
                PropertyUnitInvoice::STATUS_OVERDUE,
            ])->whereRaw('amount > amount_paid');
        });
    }

    /**
     * ✅ INVOICE: Units with at least one overdue invoice.
     */
    public function scopeWithOverdueInvoices($query)
    {
        return $query->whereHas('invoices', function ($q) {
            $q->where('status', PropertyUnitInvoice::STATUS_OVERDUE);
        });
    }

    /**
     * ✅ INVOICE: Units with invoices of a specific type.
     */
    public function scopeWithInvoiceType($query, string $invoiceType)
    {
        return $query->whereHas('invoices', function ($q) use ($invoiceType) {
            $q->where('invoice_type', $invoiceType);
        });
    }

    /**
     * ✅ GHANA: Units whose current lease is in the advance phase.
     */
    public function scopeInAdvancePhase($query)
    {
        return $query->whereHas('currentLease', function ($q) {
            $q->whereNotNull('advance_rent_period_end')
              ->where('advance_rent_period_end', '>=', now());
        });
    }

    /**
     * ✅ GHANA: Units whose current lease is in the monthly phase.
     */
    public function scopeInMonthlyPhase($query)
    {
        return $query->whereHas('currentLease', function ($q) {
            $q->where('payment_frequency', RentalAgreement::PAYMENT_FREQUENCY_MONTHLY)
              ->where(function ($sub) {
                  $sub->whereNull('advance_rent_period_end')
                      ->orWhere('advance_rent_period_end', '<', now());
              });
        });
    }

    // ========== BUSINESS LOGIC METHODS ==========

    public function requestTenantAssignment(User $tenant, User $requestedBy, array $documents = [], string $tenantType = self::TENANT_TYPE_EXISTING): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        if ($this->tenant_id && $this->tenant_status === self::TENANT_STATUS_APPROVED) {
            return false;
        }

        return $this->update([
            'tenant_id'           => $tenant->id,
            'tenant_status'       => self::TENANT_STATUS_PENDING_APPROVAL,
            'tenant_type'         => $tenantType,
            'tenant_requested_by' => $requestedBy->id,
            'tenant_requested_at' => now(),
            'tenant_documents'    => $documents,
            'status'              => self::STATUS_RESERVED,
            'is_available'        => false,
        ]);
    }

    public function requestNewTenant(array $tenantData, User $requestedBy, array $documents = []): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        return $this->update([
            'tenant_status'       => self::TENANT_STATUS_PENDING_APPROVAL,
            'tenant_type'         => self::TENANT_TYPE_NEW,
            'tenant_requested_by' => $requestedBy->id,
            'tenant_requested_at' => now(),
            'tenant_details'      => $tenantData,
            'tenant_documents'    => $documents,
            'proposed_rent'       => $tenantData['proposed_rent'] ?? $this->monthly_rent,
            'status'              => self::STATUS_RESERVED,
            'is_available'        => false,
        ]);
    }

    public function reserveUnit(): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        return $this->update([
            'status'       => self::STATUS_RESERVED,
            'is_available' => false,
        ]);
    }

    public function releaseReservedUnit(): bool
    {
        if ($this->status !== self::STATUS_RESERVED) {
            return false;
        }

        return $this->update([
            'status'          => self::STATUS_AVAILABLE,
            'is_available'    => true,
            'tenant_status'   => null,
            'tenant_id'       => null,
            'tenant_details'  => null,
            'tenant_documents'=> null,
            'proposed_rent'   => null,
        ]);
    }

    public function approveTenantAssignment(User $approvedBy, ?string $notes = null, ?\DateTime $moveInDate = null, ?float $finalRent = null): bool
    {
        if ($this->tenant_status !== self::TENANT_STATUS_PENDING_APPROVAL) {
            return false;
        }

        $updates = [
            'tenant_status'          => self::TENANT_STATUS_APPROVED,
            'tenant_approved_by'     => $approvedBy->id,
            'tenant_approved_at'     => now(),
            'tenant_approval_notes'  => $notes,
            'tenant_move_in_date'    => $moveInDate ?? now(),
            'status'                 => self::STATUS_OCCUPIED,
            'current_rent_amount'    => $finalRent ?? $this->proposed_rent ?? $this->monthly_rent,
        ];

        if ($this->tenant_type === self::TENANT_TYPE_NEW && !empty($this->tenant_details)) {
            $tenant = User::create([
                'name'     => $this->tenant_details['name'],
                'email'    => $this->tenant_details['email'],
                'phone'    => $this->tenant_details['phone'],
                'type'     => User::TYPE_TENANT,
                'status'   => User::STATUS_PENDING,
                'password' => bcrypt(Str::random(12)),
            ]);

            $updates['tenant_id'] = $tenant->id;
        }

        return $this->update($updates);
    }

    public function rejectTenantAssignment(User $rejectedBy, string $reason, bool $allowResubmission = false, ?string $resubmissionNotes = null): bool
    {
        if ($this->tenant_status !== self::TENANT_STATUS_PENDING_APPROVAL) {
            return false;
        }

        $updates = [
            'tenant_status'                => self::TENANT_STATUS_REJECTED,
            'tenant_approved_by'           => $rejectedBy->id,
            'tenant_approved_at'           => now(),
            'tenant_approval_notes'        => $reason,
            'tenant_resubmission_allowed'  => $allowResubmission,
            'tenant_resubmission_notes'    => $resubmissionNotes,
            'status'                       => self::STATUS_AVAILABLE,
            'is_available'                 => true,
        ];

        if (!$allowResubmission || $this->tenant_type === self::TENANT_TYPE_EXISTING) {
            $updates['tenant_id']       = null;
            $updates['tenant_documents']= null;
            $updates['proposed_rent']   = null;
        }

        return $this->update($updates);
    }

    public function markTenantVacated(string $reason, string $propertyCondition, bool $cleaningRequired = false, ?string $damagesNoted = null): bool
    {
        if ($this->tenant_status !== self::TENANT_STATUS_APPROVED) {
            return false;
        }

        return $this->update([
            'tenant_status'        => self::TENANT_STATUS_VACATED,
            'tenant_move_out_date' => now(),
            'tenant_vacate_reason' => $reason,
            'property_condition'   => $propertyCondition,
            'cleaning_required'    => $cleaningRequired,
            'damages_noted'        => $damagesNoted,
            'status'               => self::STATUS_UNDER_MAINTENANCE,
            'is_available'         => false,
            'current_lease_id'     => null,
            'current_rent_amount'  => null,
            'lease_start_date'     => null,
            'lease_end_date'       => null,
        ]);
    }

    public function terminateTenant(string $reason, array $terminationDetails = []): bool
    {
        if (!in_array($this->tenant_status, [
            self::TENANT_STATUS_APPROVED,
            self::TENANT_STATUS_PENDING_APPROVAL,
        ])) {
            return false;
        }

        return $this->update([
            'tenant_status'        => self::TENANT_STATUS_TERMINATED,
            'tenant_move_out_date' => now(),
            'tenant_vacate_reason' => $reason,
            'termination_details'  => $terminationDetails,
            'status'               => self::STATUS_UNDER_MAINTENANCE,
            'is_available'         => false,
            'current_lease_id'     => null,
            'current_rent_amount'  => null,
            'lease_start_date'     => null,
            'lease_end_date'       => null,
        ]);
    }

    public function markMaintenanceComplete(array $maintenanceData): bool
    {
        if ($this->status !== self::STATUS_UNDER_MAINTENANCE) {
            return false;
        }

        $maintenanceHistory = $this->maintenance_history ?? [];
        $maintenanceHistory[] = array_merge($maintenanceData, [
            'completed_at' => now()->toISOString(),
        ]);

        return $this->update([
            'status'                 => self::STATUS_AVAILABLE,
            'is_available'           => true,
            'available_from'         => now(),
            'last_maintenance_date'  => now(),
            'maintenance_history'    => $maintenanceHistory,
        ]);
    }

    public function processDepositRefund(float $amount, ?string $deductionReason = null): bool
    {
        return $this->update([
            'security_deposit_refunded'         => $amount,
            'security_deposit_deduction_reason' => $deductionReason,
        ]);
    }

    public function archive(User $archivedBy): bool
    {
        if ($this->status === self::STATUS_OCCUPIED) {
            return false;
        }

        return $this->update([
            'is_archived'  => true,
            'archived_at'  => now(),
            'archived_by'  => $archivedBy->id,
            'is_available' => false,
        ]);
    }

    public function restoreFromArchive(): bool
    {
        return $this->update([
            'is_archived'  => false,
            'archived_at'  => null,
            'archived_by'  => null,
            'is_available' => $this->status === self::STATUS_AVAILABLE,
        ]);
    }

    public function assignLease(RentalAgreement $lease): bool
    {
        return $this->update([
            'current_lease_id'    => $lease->id,
            'current_rent_amount' => $lease->monthly_rent,
            'lease_start_date'    => $lease->start_date,
            'lease_end_date'      => $lease->end_date,
            'security_deposit'    => $lease->security_deposit,
        ]);
    }

    public function calculateAnnualValue(): float
    {
        return ($this->current_rent_amount ?? $this->monthly_rent) * 12;
    }

    public function isTenantAssignmentStale(): bool
    {
        if ($this->tenant_status !== self::TENANT_STATUS_PENDING_APPROVAL) {
            return false;
        }

        return $this->tenant_requested_at
            && $this->tenant_requested_at->diffInDays(now()) > 7;
    }

    // ========== ✅ INVOICE: UPDATED AGGREGATE METHODS ==========

    /**
     * ✅ INVOICE: Outstanding balance using PropertyUnitInvoice.
     */
    public function getOutstandingBalance(): float
    {
        return $this->outstanding_balance;
    }

    /**
     * ✅ INVOICE: Total rent collected (monthly + advance).
     */
    public function getTotalRentCollected(): float
    {
        return (float) $this->invoices()
            ->where('status', PropertyUnitInvoice::STATUS_PAID)
            ->whereIn('invoice_type', [
                PropertyUnitInvoice::TYPE_MONTHLY_RENT,
                PropertyUnitInvoice::TYPE_ADVANCE_RENT,
            ])
            ->sum('amount');
    }

    /**
     * ✅ INVOICE: Advance rent collected.
     */
    public function getAdvanceRentCollected(): float
    {
        return (float) $this->advanceRentInvoices()
            ->where('status', PropertyUnitInvoice::STATUS_PAID)
            ->sum('amount');
    }

    /**
     * ✅ INVOICE: Monthly rent collected.
     */
    public function getMonthlyRentCollected(): float
    {
        return (float) $this->monthlyRentInvoices()
            ->where('status', PropertyUnitInvoice::STATUS_PAID)
            ->sum('amount');
    }

    /**
     * ✅ INVOICE: Security deposit collected.
     */
    public function getSecurityDepositCollected(): float
    {
        return (float) $this->securityDepositInvoices()
            ->sum('amount_paid');
    }

    /**
     * ✅ INVOICE: Does the unit have overdue rent invoices?
     */
    public function hasOverdueRent(): bool
    {
        return $this->invoices()
            ->where('status', PropertyUnitInvoice::STATUS_OVERDUE)
            ->whereIn('invoice_type', [
                PropertyUnitInvoice::TYPE_MONTHLY_RENT,
                PropertyUnitInvoice::TYPE_ADVANCE_RENT,
            ])
            ->exists();
    }

    /**
     * ✅ INVOICE: Get all invoices for the current lease.
     */
    public function getCurrentLeaseInvoices()
    {
        if (!$this->current_lease_id) {
            return collect();
        }
        return $this->invoices()
            ->where('lease_id', $this->current_lease_id)
            ->orderBy('due_date')
            ->get();
    }

    public function getMaintenanceStats(): array
    {
        return [
            'total'       => $this->maintenanceRequests()->count(),
            'pending'     => $this->maintenanceRequests()->where('status', 'pending')->count(),
            'in_progress' => $this->maintenanceRequests()->where('status', 'in_progress')->count(),
            'completed'   => $this->maintenanceRequests()->where('status', 'completed')->count(),
        ];
    }

    // ========== STATIC METHODS ==========

    public static function getUnitsCountByProperty($propertyId): int
    {
        return self::where('property_id', $propertyId)->count();
    }

    public static function getAvailableUnitsCountByProperty($propertyId): int
    {
        return self::where('property_id', $propertyId)->available()->count();
    }

    public static function getReservedUnitsCountByProperty($propertyId): int
    {
        return self::where('property_id', $propertyId)->reserved()->count();
    }

    public static function getOccupiedUnitsCountByProperty($propertyId): int
    {
        return self::where('property_id', $propertyId)->occupied()->count();
    }

    public static function getPendingApprovalCountByProperty($propertyId): int
    {
        return self::where('property_id', $propertyId)->pendingTenantApproval()->count();
    }

    /**
     * ✅ INVOICE: Count units with overdue rent invoices.
     */
    public static function getOverdueRentCountByProperty($propertyId): int
    {
        return self::where('property_id', $propertyId)
            ->withApprovedTenants()
            ->whereHas('invoices', function ($query) {
                $query->where('status', PropertyUnitInvoice::STATUS_OVERDUE)
                      ->whereIn('invoice_type', [
                          PropertyUnitInvoice::TYPE_MONTHLY_RENT,
                          PropertyUnitInvoice::TYPE_ADVANCE_RENT,
                      ]);
            })
            ->count();
    }

    public static function getMonthlyRentalIncomeByProperty($propertyId): float
    {
        return self::where('property_id', $propertyId)
            ->withApprovedTenants()
            ->sum(DB::raw('COALESCE(current_rent_amount, monthly_rent)'));
    }

    /**
     * ✅ INVOICE: Total outstanding balance across a property's units.
     */
    public static function getOutstandingBalanceByProperty($propertyId): float
    {
        return (float) PropertyUnitInvoice::where('property_id', $propertyId)
            ->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
                PropertyUnitInvoice::STATUS_OVERDUE,
            ])
            ->sum(DB::raw('amount - amount_paid'));
    }

    /**
     * ✅ INVOICE: Total collected for a property.
     */
    public static function getTotalCollectedByProperty($propertyId): float
    {
        return (float) PropertyUnitInvoice::where('property_id', $propertyId)
            ->where('status', PropertyUnitInvoice::STATUS_PAID)
            ->sum('amount_paid');
    }

    public static function findByPropertyAndUnitNumber($propertyId, $unitNumber): ?self
    {
        return self::where('property_id', $propertyId)
            ->where('unit_number', $unitNumber)
            ->first();
    }

    public static function getOccupancyRateByProperty($propertyId): float
    {
        $totalUnits = self::where('property_id', $propertyId)->count();
        $occupiedUnits = self::where('property_id', $propertyId)->occupied()->count();

        if ($totalUnits === 0) {
            return 0;
        }

        return round(($occupiedUnits / $totalUnits) * 100, 2);
    }

    public static function getReservationRateByProperty($propertyId): float
    {
        $totalUnits = self::where('property_id', $propertyId)->count();
        $reservedUnits = self::where('property_id', $propertyId)->reserved()->count();

        if ($totalUnits === 0) {
            return 0;
        }

        return round(($reservedUnits / $totalUnits) * 100, 2);
    }

    public static function getDashboardStats(?int $landlordId = null): array
    {
        $query = self::query();

        if ($landlordId) {
            $query->whereHas('property', function ($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            });
        }

        $totalUnits       = $query->count();
        $availableUnits   = $query->clone()->available()->count();
        $occupiedUnits    = $query->clone()->occupied()->count();
        $reservedUnits    = $query->clone()->reserved()->count();
        $maintenanceUnits = $query->clone()->underMaintenance()->count();
        $pendingApproval  = $query->clone()->pendingTenantApproval()->count();

        $totalMonthlyRent = $query->clone()->withApprovedTenants()
            ->sum(DB::raw('COALESCE(current_rent_amount, monthly_rent)'));
        $totalAnnualRent = $totalMonthlyRent * 12;

        // ✅ INVOICE: aggregate invoice data for the dashboard
        $invoiceQuery = PropertyUnitInvoice::query();
        if ($landlordId) {
            $invoiceQuery->whereHas('unit.property', function ($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId);
            });
        }

        $outstanding = (float) $invoiceQuery->clone()
            ->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
                PropertyUnitInvoice::STATUS_OVERDUE,
            ])
            ->sum(DB::raw('amount - amount_paid'));

        $overdueCount = $invoiceQuery->clone()
            ->where('status', PropertyUnitInvoice::STATUS_OVERDUE)
            ->count();

        return [
            'total_units'        => $totalUnits,
            'available_units'    => $availableUnits,
            'occupied_units'     => $occupiedUnits,
            'reserved_units'     => $reservedUnits,
            'maintenance_units'  => $maintenanceUnits,
            'pending_approval'   => $pendingApproval,
            'total_monthly_rent' => $totalMonthlyRent,
            'total_annual_rent'  => $totalAnnualRent,
            'average_rent'       => $occupiedUnits > 0 ? $totalMonthlyRent / $occupiedUnits : 0,
            'occupancy_rate'     => $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 2) : 0,

            // ✅ INVOICE: new dashboard fields
            'outstanding_balance'  => $outstanding,
            'overdue_invoice_count'=> $overdueCount,
            'collection_rate'      => ($totalMonthlyRent * 12) > 0
                ? round(($totalAnnualRent - $outstanding) / $totalAnnualRent * 100, 2)
                : 0,
        ];
    }
}