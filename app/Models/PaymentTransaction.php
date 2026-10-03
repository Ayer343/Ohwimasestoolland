<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    /**
     * Transaction type constants
     */
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'admin_billing_record_id',
        'amount',
        'currency',
        'transaction_type',
        'transaction_reference',
        'payment_method',
        'gateway_response',
        'status',
        'confirmed_by',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    /**
     * Relationship with AdminBillingRecord
     */
    public function adminBillingRecord()
    {
        return $this->belongsTo(AdminBillingRecord::class);
    }

    /**
     * Relationship with confirmer (User)
     */
    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Get transaction type label
     */
    public function getTransactionTypeLabelAttribute(): string
    {
        $labels = [
            self::TYPE_PAYMENT => 'Payment',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_ADJUSTMENT => 'Adjustment',
        ];
        
        return $labels[$this->transaction_type] ?? 'Unknown';
    }
}