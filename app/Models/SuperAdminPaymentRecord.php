<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuperAdminPaymentRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'super_admin_payment_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'agreement_id',
        'developer_setting_id',
        'super_admin_id',
        'amount_paid',
        'payment_date',
        'billing_month',
        'payment_method',
        'transaction_reference',
        'notes',
        'recorded_by',
        'confirmed_by',
        'confirmed_at',
        'status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_date' => 'date',
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the agreement associated with this payment record.
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(AdminBillingRecord::class, 'agreement_id');
    }

    /**
     * Get the developer setting associated with this payment record.
     */
    public function developerSetting(): BelongsTo
    {
        return $this->belongsTo(DeveloperSetting::class, 'developer_setting_id');
    }

    /**
     * Get the super admin who made this payment.
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    /**
     * Get the user who recorded this payment.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the user who confirmed this payment.
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Scope a query to only include confirmed payments.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to filter by billing month.
     */
    public function scopeForMonth($query, $month)
    {
        return $query->where('billing_month', $month);
    }

    /**
     * Scope a query to filter by developer.
     */
    public function scopeForDeveloper($query, $developerSettingId)
    {
        return $query->where('developer_setting_id', $developerSettingId);
    }

    /**
     * Check if payment is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Confirm the payment.
     */
    public function confirm($confirmedByUserId): bool
    {
        $this->status = 'confirmed';
        $this->confirmed_by = $confirmedByUserId;
        $this->confirmed_at = now();
        
        return $this->save();
    }
}