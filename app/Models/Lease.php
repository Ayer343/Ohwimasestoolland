<?php
// app/Models/Lease.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Lease extends Model
{
    use HasFactory, SoftDeletes;

    // Lease status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_TERMINATED = 'terminated';
    const STATUS_CANCELLED = 'cancelled';

    // Payment frequency constants
    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_QUARTERLY = 'quarterly';
    const FREQUENCY_BIANNUALLY = 'biannually';
    const FREQUENCY_ANNUALLY = 'annually';

    protected $table = 'leases';

    protected $fillable = [
        'property_unit_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount',
        'security_deposit',
        'payment_frequency',
        'payment_due_day',
        'status',
        'document_path',
        'signed_by_landlord',
        'signed_by_tenant',
        'landlord_signed_at',
        'tenant_signed_at',
        'terms_and_conditions',
        'special_clauses',
        'renewal_option',
        'notice_period_days',
        'late_fee_amount',
        'late_fee_percentage',
        'grace_period_days',
        'created_by',
        'updated_by',
        'metadata'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'rent_amount' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
        'late_fee_percentage' => 'decimal:2',
        'signed_by_landlord' => 'boolean',
        'signed_by_tenant' => 'boolean',
        'landlord_signed_at' => 'datetime',
        'tenant_signed_at' => 'datetime',
        'renewal_option' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $appends = [
        'status_label',
        'formatted_rent',
        'formatted_deposit',
        'duration_months',
        'is_active',
        'is_expiring_soon',
        'days_remaining'
    ];

    /**
     * Get the property unit associated with this lease
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    /**
     * Get the tenant associated with this lease
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the lease
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the lease
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get payments for this lease
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'lease_id');
    }

    /**
     * Get invoices for this lease
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'lease_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('end_date', '<=', Carbon::now()->addDays($days))
            ->where('end_date', '>=', Carbon::now());
    }

    public function scopeByLandlord($query, $landlordId)
    {
        return $query->whereHas('unit.property', function($q) use ($landlordId) {
            $q->where('landlord_id', $landlordId);
        });
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending Signature',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_TERMINATED => 'Terminated',
            self::STATUS_CANCELLED => 'Cancelled'
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getFormattedRentAttribute(): string
    {
        return 'GH₵ ' . number_format($this->rent_amount, 2);
    }

    public function getFormattedDepositAttribute(): string
    {
        return 'GH₵ ' . number_format($this->security_deposit ?? 0, 2);
    }

    public function getDurationMonthsAttribute(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }
        return $this->start_date->diffInMonths($this->end_date);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->end_date >= Carbon::now();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        return $this->end_date <= Carbon::now()->addDays(30);
    }

    public function getDaysRemainingAttribute(): int
    {
        if (!$this->is_active) {
            return 0;
        }
        return max(0, Carbon::now()->diffInDays($this->end_date, false));
    }

    // Helper methods
    public function isFullySigned(): bool
    {
        return $this->signed_by_landlord && $this->signed_by_tenant;
    }

    public function canBeActivated(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->isFullySigned();
    }

    public function activate(): bool
    {
        if (!$this->canBeActivated()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_ACTIVE
        ]);

        return true;
    }

    public function terminate(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_TERMINATED
        ]);

        return true;
    }

    public function renew($newEndDate, $newRentAmount = null): self
    {
        $newLease = $this->replicate();
        $newLease->start_date = $this->end_date->addDay();
        $newLease->end_date = $newEndDate;
        
        if ($newRentAmount) {
            $newLease->rent_amount = $newRentAmount;
        }
        
        $newLease->status = self::STATUS_PENDING;
        $newLease->signed_by_landlord = false;
        $newLease->signed_by_tenant = false;
        $newLease->landlord_signed_at = null;
        $newLease->tenant_signed_at = null;
        
        $newLease->save();
        
        return $newLease;
    }
}