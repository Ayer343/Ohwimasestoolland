<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantInvoiceArchive extends Model
{
    protected $table = 'tenant_invoice_archives';
    
    protected $fillable = [
        'original_invoice_id',
        'invoice_number',
        'tenant_id',
        'tenant_name',
        'property_unit_id',
        'period',
        'due_date',
        'community_dues',
        'additional_charges',
        'total_amount',
        'paid_amount',
        'balance',
        'status',
        'payment_method',
        'payment_reference',
        'payment_date',
        'penalty_amount',
        'penalty_applied_at',
        'grace_period_days',
        'calculation_method',
        'calculation_details',
        'description',
        'notes',
        'metadata',
        'original_created_at',
        'original_created_by',
        'deleted_at',
        'deleted_by',
        'deleted_by_name',
        'deletion_reason',
        'deletion_ip',
        'deletion_user_agent'
    ];
    
    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'penalty_applied_at' => 'datetime',
        'community_dues' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'calculation_details' => 'array',
        'metadata' => 'array',
        'original_created_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * Get the original invoice that this archive belongs to
     */
    public function originalInvoice()
    {
        return $this->belongsTo(TenantInvoice::class, 'original_invoice_id')->withTrashed();
    }
    
    /**
     * Get the tenant who owned this invoice
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
    
    /**
     * Get the property unit
     */
    public function propertyUnit()
    {
        return $this->belongsTo(PropertyUnit::class);
    }
    
    /**
     * Get the user who deleted this invoice
     */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
    
    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAmountAttribute()
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->total_amount);
    }
    
    /**
     * Get formatted community dues
     */
    public function getFormattedCommunityDuesAttribute()
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->community_dues);
    }
    
    /**
     * Get formatted penalty amount
     */
    public function getFormattedPenaltyAmountAttribute()
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->penalty_amount);
    }
    
    /**
     * Get the month name of the period
     */
    public function getMonthNameAttribute()
    {
        return \Carbon\Carbon::parse($this->period . '-01')->format('F Y');
    }
    
    /**
     * Get the deletion date formatted
     */
    public function getFormattedDeletedAtAttribute()
    {
        return $this->deleted_at ? $this->deleted_at->format('M d, Y H:i') : 'N/A';
    }
    
    /**
     * Get the original creation date formatted
     */
    public function getFormattedOriginalCreatedAtAttribute()
    {
        return $this->original_created_at ? \Carbon\Carbon::parse($this->original_created_at)->format('M d, Y') : 'N/A';
    }
}