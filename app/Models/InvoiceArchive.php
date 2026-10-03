<?php
// app/Models/InvoiceArchive.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class InvoiceArchive extends Model
{
    protected $table = 'invoice_archives';
    
    protected $fillable = [
        'original_invoice_id',
        'invoice_number',
        'property_id',
        'property_name',
        'landlord_id',
        'landlord_name',
        'period',
        'month_name',
        'due_date',
        'amount',
        'penalty_amount',
        'total_amount',
        'paid_amount',
        'balance',
        'status',
        'payment_method',
        'payment_reference',
        'payment_date',
        'is_bulk_payment',
        'bulk_payment_id',
        'covers_periods',
        'bulk_coverage_start',
        'bulk_coverage_end',
        'description',
        'notes',
        'metadata',
        'original_created_at',
        'original_created_by',
        'original_updated_at',
        'original_updated_by',
        'deleted_at',
        'deleted_by',
        'deleted_by_name',
        'deletion_reason',
        'deletion_ip',
        'deletion_user_agent',
        'archive_type', // 'manual', 'year_end', 'post_payment'
        'grace_period_days',
        'late_payment_percentage',
        'fixed_penalty_amount'
    ];
    
    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'original_created_at' => 'datetime',
        'original_updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'covers_periods' => 'array',
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_bulk_payment' => 'boolean',
    ];
    
    protected $appends = ['formatted_deleted_at', 'formatted_total_amount', 'formatted_paid_amount'];
    
    /**
     * Get the property that owned the invoice
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
    
    /**
     * Get the landlord that owned the invoice
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
    
    /**
     * Get the user who deleted the invoice
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
    
    /**
     * Get the user who originally created the invoice
     */
    public function originalCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_created_by');
    }
    
    /**
     * Get formatted deleted at date
     */
    public function getFormattedDeletedAtAttribute(): string
    {
        return $this->deleted_at ? $this->deleted_at->format('M d, Y H:i:s') : 'N/A';
    }
    
    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->total_amount);
    }
    
    /**
     * Get formatted paid amount
     */
    public function getFormattedPaidAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->paid_amount);
    }
    
    /**
     * Get month name from period
     */
    public function getMonthNameAttribute(): string
    {
        if (!$this->period) return '';
        return Carbon::parse($this->period . '-01')->format('F Y');
    }
    
    /**
     * Scope for archives by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('archive_type', $type);
    }
    
    /**
     * Scope for archives by date range
     */
    public function scopeDeletedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('deleted_at', [$startDate, $endDate]);
    }
    
    /**
     * Scope for archives older than given date
     */
    public function scopeOlderThan($query, $date)
    {
        return $query->where('deleted_at', '<', $date);
    }
}