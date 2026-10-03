<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeveloperBillingRecord extends Model
{
    use SoftDeletes;

    protected $table = 'developer_billing_records';
    
    protected $fillable = [
        'developer_setting_id',
        'admin_id',
        'invoice_number',
        'amount',
        'currency',
        'billing_period',
        'invoice_date',
        'due_date',
        'paid_date',
        'payment_method',
        'payment_reference',
        'payment_status',
        'payment_details',
        'transaction_id',
        'billing_items',
        'tax_amount',
        'total_amount',
        'admin_approved',
        'approved_by',
        'approved_at',
        'developer_confirmed',
        'confirmed_at',
        'invoice_sent',
        'invoice_sent_at',
        'reminder_sent',
        'reminder_sent_at',
        'notes',
        'metadata',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'billing_items' => 'array',
        'payment_details' => 'array',
        'admin_approved' => 'boolean',
        'approved_at' => 'datetime',
        'developer_confirmed' => 'boolean',
        'confirmed_at' => 'datetime',
        'invoice_sent' => 'boolean',
        'invoice_sent_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    // Payment statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Generate invoice number before creating
        static::creating(function ($model) {
            if (empty($model->invoice_number)) {
                $model->invoice_number = $model->generateInvoiceNumber();
            }
        });
    }
    
    /**
     * Generate invoice number
     */
    public function generateInvoiceNumber()
    {
        $date = now()->format('Ym');
        $lastInvoice = self::where('invoice_number', 'like', "INV-{$date}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }
        
        return "INV-{$date}-{$nextNumber}";
    }
    
    /**
     * Relationship with developer settings
     */
    public function developerSetting()
    {
        return $this->belongsTo(DeveloperSetting::class, 'developer_setting_id');
    }
    
    /**
     * Relationship with admin who created/approved
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
    
    /**
     * Relationship with approver
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    /**
     * Check if invoice is overdue
     */
    public function isOverdue()
    {
        return $this->payment_status === self::STATUS_PENDING && 
               $this->due_date && 
               $this->due_date->isPast();
    }
    
    /**
     * Check if invoice is due soon
     */
    public function isDueSoon($days = 7)
    {
        return $this->payment_status === self::STATUS_PENDING && 
               $this->due_date && 
               $this->due_date->isFuture() &&
               $this->due_date->diffInDays(now()) <= $days;
    }
    
    /**
     * Get overdue days
     */
    public function getOverdueDays()
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        return $this->due_date->diffInDays(now());
    }
    
    /**
     * Mark as paid
     */
    public function markAsPaid(array $data = [])
    {
        $this->update([
            'payment_status' => self::STATUS_PAID,
            'paid_date' => $data['paid_date'] ?? now(),
            'payment_method' => $data['payment_method'] ?? $this->payment_method,
            'payment_reference' => $data['payment_reference'] ?? $this->payment_reference,
            'transaction_id' => $data['transaction_id'] ?? $this->transaction_id,
            'payment_details' => array_merge(
                $this->payment_details ?? [],
                $data['payment_details'] ?? []
            ),
            'developer_confirmed' => true,
            'confirmed_at' => now(),
        ]);
        
        return $this;
    }
    
    /**
     * Mark as sent
     */
    public function markAsSent()
    {
        $this->update([
            'invoice_sent' => true,
            'invoice_sent_at' => now(),
        ]);
        
        return $this;
    }
    
    /**
     * Mark reminder as sent
     */
    public function markReminderAsSent()
    {
        $this->update([
            'reminder_sent' => true,
            'reminder_sent_at' => now(),
        ]);
        
        return $this;
    }
    
    /**
     * Approve invoice
     */
    public function approve($approvedBy)
    {
        $this->update([
            'admin_approved' => true,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
        
        return $this;
    }
    
    /**
     * Get billing items with formatted amounts
     */
    public function getFormattedBillingItems()
    {
        if (empty($this->billing_items)) {
            return [];
        }
        
        $items = [];
        $total = 0;
        
        foreach ($this->billing_items as $item) {
            $amount = $item['amount'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $subtotal = $amount * $quantity;
            $total += $subtotal;
            
            $items[] = [
                'description' => $item['description'] ?? 'Service',
                'amount' => number_format($amount, 2),
                'quantity' => $quantity,
                'subtotal' => number_format($subtotal, 2),
                'currency' => $this->currency,
            ];
        }
        
        return [
            'items' => $items,
            'subtotal' => number_format($total, 2),
            'tax' => number_format($this->tax_amount, 2),
            'total' => number_format($this->total_amount, 2),
            'currency' => $this->currency,
        ];
    }
    
    /**
     * Get invoice summary
     */
    public function getSummary()
    {
        return [
            'invoice_number' => $this->invoice_number,
            'date' => $this->invoice_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),
            'amount' => number_format($this->amount, 2),
            'tax' => number_format($this->tax_amount, 2),
            'total' => number_format($this->total_amount, 2),
            'currency' => $this->currency,
            'status' => $this->payment_status,
            'is_overdue' => $this->isOverdue(),
            'overdue_days' => $this->getOverdueDays(),
            'developer' => $this->developerSetting->developer_name,
            'developer_email' => $this->developerSetting->developer_email,
        ];
    }
    
    /**
     * Create invoice from developer settings
     */
    public static function createFromDeveloperSettings(DeveloperSetting $settings, $billingPeriod = null)
    {
        if (!$settings->isBillingSetup()) {
            throw new \Exception('Developer billing is not setup');
        }
        
        $billingPeriod = $billingPeriod ?? now()->format('Y-m');
        
        // Check if invoice already exists for this period
        $existing = self::where('developer_setting_id', $settings->id)
            ->where('billing_period', $billingPeriod)
            ->first();
            
        if ($existing) {
            return $existing;
        }
        
        $amount = $settings->calculateInvoiceAmount();
        $dueDate = now()->addDays(15); // 15 days to pay
        
        $invoice = self::create([
            'developer_setting_id' => $settings->id,
            'invoice_number' => null, // Will be auto-generated
            'amount' => $amount,
            'currency' => $settings->billing_currency,
            'billing_period' => $billingPeriod,
            'invoice_date' => now(),
            'due_date' => $dueDate,
            'payment_status' => self::STATUS_PENDING,
            'billing_items' => [
                [
                    'description' => "Developer Services - {$settings->billing_cycle} billing",
                    'amount' => $settings->monthly_billing_amount,
                    'quantity' => $settings->billing_cycle === DeveloperSetting::CYCLE_MONTHLY ? 1 : 
                                ($settings->billing_cycle === DeveloperSetting::CYCLE_QUARTERLY ? 3 : 12),
                    'unit' => 'month',
                ]
            ],
            'tax_amount' => 0.00, // No tax by default
            'total_amount' => $amount,
        ]);
        
        return $invoice;
    }
    
    /**
     * Scope: Pending invoices
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', self::STATUS_PENDING);
    }
    
    /**
     * Scope: Paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', self::STATUS_PAID);
    }
    
    /**
     * Scope: Overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('payment_status', self::STATUS_PENDING)
                    ->where('due_date', '<', now());
    }
    
    /**
     * Scope: Due soon
     */
    public function scopeDueSoon($query, $days = 7)
    {
        return $query->where('payment_status', self::STATUS_PENDING)
                    ->where('due_date', '>=', now())
                    ->where('due_date', '<=', now()->addDays($days));
    }
    
    /**
     * Scope: By developer
     */
    public function scopeByDeveloper($query, $developerId)
    {
        return $query->where('developer_setting_id', $developerId);
    }
    
    /**
     * Scope: By period
     */
    public function scopeByPeriod($query, $period)
    {
        return $query->where('billing_period', $period);
    }
    
    /**
     * Get statistics
     */
    public static function getStatistics($developerId = null)
    {
        $query = self::query();
        
        if ($developerId) {
            $query->where('developer_setting_id', $developerId);
        }
        
        $total = $query->count();
        $paid = $query->where('payment_status', self::STATUS_PAID)->count();
        $pending = $query->where('payment_status', self::STATUS_PENDING)->count();
        $overdue = $query->where('payment_status', self::STATUS_PENDING)
                        ->where('due_date', '<', now())
                        ->count();
        
        $totalAmount = $query->where('payment_status', self::STATUS_PAID)->sum('total_amount');
        $pendingAmount = $query->where('payment_status', self::STATUS_PENDING)->sum('total_amount');
        
        return [
            'total_invoices' => $total,
            'paid_invoices' => $paid,
            'pending_invoices' => $pending,
            'overdue_invoices' => $overdue,
            'total_revenue' => $totalAmount,
            'pending_revenue' => $pendingAmount,
            'conversion_rate' => $total > 0 ? round(($paid / $total) * 100, 2) : 0,
        ];
    }
}