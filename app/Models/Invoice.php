<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    // ✅ INVOICE STATUS CONSTANTS
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PARTIAL = 'partial';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CONSOLIDATED = 'consolidated';

    // ✅ PAYMENT METHOD CONSTANTS
    const METHOD_CASH = 'cash';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CHEQUE = 'cheque';
    const METHOD_CARD = 'card';
    const METHOD_MOBILE_MONEY = 'mobile_money';
    const METHOD_BULK_PAYMENT = 'bulk_payment';
    const METHOD_MTN_MOMO = 'mtn_momo';
    const METHOD_TELECEL_CASH = 'telecel_cash';
    const METHOD_AIRTELTIGO_CASH = 'airteltigo_cash';
    const METHOD_PAYSTACK = 'paystack';

    protected $table = 'invoices';

    protected $fillable = [
        'property_id',
        'unit_id',
        'invoice_number',
        'amount',
        'penalty_amount',
        'period',
        'due_date',
        'penalty_applied_date',
        'status',
        'payment_id',
        'payment_method',
        'payment_reference',
        'payment_date',
        'bulk_payment_reference',
        'bulk_payment_id',
        'is_bulk_payment',
        'bulk_months',
        'covers_periods',
        'bulk_coverage_start',
        'bulk_coverage_end',
        'description',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
        'last_reminder_sent_at',
        'reminder_count',
        'year_end_archived_at',
        'year_end_archive_year',
        'original_year',
        'archive_status',
        'archive_type',
        'archive_reason',
        'archived_at',
        'archive_approved_by_tenant',
        'archive_approved_at'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'payment_date' => 'datetime',
        'penalty_applied_date' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'is_bulk_payment' => 'boolean',
        'bulk_months' => 'integer',
        'covers_periods' => 'array',
        'bulk_coverage_start' => 'datetime',
        'bulk_coverage_end' => 'datetime',
        'reminder_count' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'year_end_archived_at' => 'datetime',
        'archived_at' => 'datetime',
        'archive_approved_at' => 'datetime',
        'archive_approved_by_tenant' => 'boolean'
    ];

    protected $appends = [
        'formatted_period',
        'status_display',
        'status_badge_class',
        'status_color',
        'status_icon',
        'days_overdue',
        'days_until_due',
        'is_due_soon',
        'has_penalty',
        'payment_method_display',
        'is_processing',
        'can_cancel',
        'can_edit',
        'formatted_amount',
        'formatted_penalty',
        'formatted_total_amount',
        'notification_sent',
        'notification_sent_at',
        'reminder_sent',
        'last_reminder_sent_at_display',
        'generation_method',
        'coverage_summary',
        'is_coverage_active',
        'total_amount'
    ];

    // ========== RELATIONSHIPS ==========

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    public function archive(): HasOne
    {
        return $this->hasOne(InvoiceArchive::class, 'original_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function bulkPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'bulk_payment_id');
    }

    public function childInvoices(): HasMany
    {
        return $this->hasMany(self::class, 'bulk_payment_id');
    }

    public function includedInvoices(): HasMany
    {
        return $this->childInvoices()->where('is_bulk_payment', false);
    }

    public function landlord()
    {
        return $this->property->landlord();
    }

    // ========== SCOPES ==========

    /**
     * Scope to exclude consolidated invoices
     */
    public function scopeExcludeConsolidated($query)
    {
        return $query->where('status', '!=', self::STATUS_CONSOLIDATED);
    }

    /**
     * Scope to only include invoices that are eligible for overdue marking
     */
    public function scopeEligibleForOverdue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where('status', '!=', self::STATUS_CONSOLIDATED) // ✅ FIX: Exclude consolidated
                    ->whereDate('due_date', '<', now()->startOfDay());
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where('status', '!=', self::STATUS_CONSOLIDATED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopePartial($query)
    {
        return $query->where('status', self::STATUS_PARTIAL);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeConsolidated($query)
    {
        return $query->where('status', self::STATUS_CONSOLIDATED);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_OVERDUE, self::STATUS_PROCESSING])
                    ->where('status', '!=', self::STATUS_CONSOLIDATED);
    }

    public function scopeBulkPayments($query)
    {
        return $query->where('is_bulk_payment', true);
    }

    public function scopeRegularInvoices($query)
    {
        return $query->where('is_bulk_payment', false);
    }

    public function scopeWithPenalties($query)
    {
        return $query->where('penalty_amount', '>', 0);
    }

    public function scopeDueSoon($query, $days = 7)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where('due_date', '>=', now())
                    ->where('due_date', '<=', now()->addDays($days));
    }

    public function scopePeriodRange($query, $start, $end)
    {
        return $query->whereBetween('period', [$start, $end]);
    }

    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeForPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    public function scopeCoversPeriod($query, string $period)
    {
        return $query->where('is_bulk_payment', true)
                    ->where('status', self::STATUS_PAID)
                    ->where(function($q) use ($period) {
                        $q->whereJsonContains('covers_periods', $period)
                          ->orWhere(function($sub) use ($period) {
                              $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                              $sub->whereNotNull('bulk_coverage_start')
                                   ->whereNotNull('bulk_coverage_end')
                                   ->where('bulk_coverage_start', '<=', $periodDate)
                                   ->where('bulk_coverage_end', '>=', $periodDate->copy()->endOfMonth());
                          });
                    });
    }

    public function scopeActiveBulkCoverage($query)
    {
        return $query->where('is_bulk_payment', true)
                    ->where('status', self::STATUS_PAID)
                    ->whereNotNull('covers_periods');
    }

    public function scopeDueSoonForReminder($query, int $daysBefore)
    {
        $today = now()->startOfDay();
        $targetDate = $today->copy()->addDays($daysBefore);
        
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_OVERDUE])
                    ->whereDate('due_date', '<=', $targetDate)
                    ->whereDate('due_date', '>', $today);
    }

    public function scopeNeedsReminder($query, int $daysBefore)
    {
        $today = now()->startOfDay();
        $targetDate = $today->copy()->addDays($daysBefore);
        
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_OVERDUE])
                    ->whereDate('due_date', '<=', $targetDate)
                    ->whereDate('due_date', '>', $today)
                    ->where(function($q) {
                        $q->whereNull('last_reminder_sent_at')
                          ->orWhere('last_reminder_sent_at', '<', now()->subDay());
                    });
    }

    public function scopeOverdueForPenalty($query, int $gracePeriodDays)
    {
        $penaltyDate = now()->subDays($gracePeriodDays)->startOfDay();
        
        return $query->where('status', self::STATUS_PENDING)
                    ->where('status', '!=', self::STATUS_CONSOLIDATED) // ✅ FIX: Exclude consolidated
                    ->whereDate('due_date', '<', $penaltyDate)
                    ->where(function($q) {
                        $q->whereNull('penalty_applied_date')
                          ->orWhere('penalty_amount', 0);
                    });
    }

    public function scopeAutoGenerated($query)
    {
        return $query->where('metadata->auto_generated', true);
    }

    public function scopeManuallyGenerated($query)
    {
        return $query->where('metadata->manual_generated', true);
    }

    public function scopeYearEndArchived($query)
    {
        return $query->whereNotNull('year_end_archived_at');
    }

    public function scopeNotYearEndArchived($query)
    {
        return $query->whereNull('year_end_archived_at');
    }

    public function scopeArchiveYear($query, $year)
    {
        return $query->where('year_end_archive_year', $year);
    }

    public function scopeEligibleForYearEndArchive($query, $year = null)
    {
        $year = $year ?? now()->subYear()->year;
        
        return $query->where('status', self::STATUS_PAID)
                    ->whereNull('year_end_archived_at')
                    ->whereYear('created_at', '<=', $year);
    }

    public function scopeEligibleForPostPaymentArchive($query, int $retentionMonths = 3)
    {
        $cutoffDate = now()->subMonths($retentionMonths);
        
        return $query->where('status', self::STATUS_PAID)
                    ->whereNotNull('year_end_archived_at')
                    ->whereNull('deleted_at')
                    ->whereNotNull('payment_date')
                    ->where('payment_date', '<=', $cutoffDate);
    }

    // ========== STATUS CHECK METHODS ==========

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOverdue(): bool
    {
        // ✅ FIX: Consolidated invoices should never be considered overdue
        if ($this->status === self::STATUS_CONSOLIDATED) {
            return false;
        }
        
        return $this->status === self::STATUS_OVERDUE || 
               ($this->status === self::STATUS_PENDING && $this->due_date && $this->due_date->isPast());
    }

    public function isPartial(): bool
    {
        return $this->status === self::STATUS_PARTIAL;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function isConsolidated(): bool
    {
        return $this->status === self::STATUS_CONSOLIDATED;
    }

    public function isBulkPayment(): bool
    {
        return $this->is_bulk_payment === true;
    }

    public function hasPenalty(): bool
    {
        return $this->penalty_amount > 0;
    }

    public function isPayable(): bool
    {
        // ✅ FIX: Consolidated invoices are not payable
        if ($this->status === self::STATUS_CONSOLIDATED) {
            return false;
        }
        
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_OVERDUE, self::STATUS_PROCESSING]);
    }

    public function canBeEdited(): bool
    {
        // ✅ FIX: Consolidated invoices cannot be edited
        if ($this->status === self::STATUS_CONSOLIDATED) {
            return false;
        }
        
        return !in_array($this->status, [
            self::STATUS_PAID, 
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED
        ]) && !$this->isBulkPayment();
    }

    public function canBeCancelled(): bool
    {
        // ✅ FIX: Consolidated invoices cannot be cancelled
        if ($this->status === self::STATUS_CONSOLIDATED) {
            return false;
        }
        
        return in_array($this->status, [
            self::STATUS_PENDING, 
            self::STATUS_OVERDUE, 
            self::STATUS_PROCESSING
        ]) && !$this->isBulkPayment();
    }

    public function canBeDeleted(): bool
    {
        // ✅ FIX: Consolidated invoices cannot be deleted
        if ($this->status === self::STATUS_CONSOLIDATED) {
            return false;
        }
        
        return !$this->isPaid() && !$this->payment()->exists();
    }

    public function isYearEndArchived(): bool
    {
        return !is_null($this->year_end_archived_at);
    }

    // ========== BULK COVERAGE METHODS ==========

    /**
     * Check if this invoice covers a specific period
     */
    public function coversPeriod(string $period): bool
    {
        if (!$this->isBulkPayment() || !$this->isPaid()) {
            return false;
        }

        $coveredPeriods = $this->getCoveredPeriods();
        
        if (!empty($coveredPeriods) && in_array($period, $coveredPeriods)) {
            return true;
        }

        if ($this->bulk_coverage_start && $this->bulk_coverage_end) {
            $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            return $periodDate->between(
                $this->bulk_coverage_start->startOfMonth(),
                $this->bulk_coverage_end->endOfMonth()
            );
        }

        return false;
    }

    /**
     * Get covered periods array from invoice
     */
    public function getCoveredPeriods(): array
    {
        if (!$this->isBulkPayment()) {
            return [];
        }

        $periods = $this->covers_periods;
        
        // If it's a string (JSON), decode it
        if (is_string($periods)) {
            $decoded = json_decode($periods, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return [];
        }
        
        // If it's already an array, return it
        if (is_array($periods)) {
            return $periods;
        }

        // If covers_periods is empty, try to generate from bulk_coverage dates
        if ($this->bulk_coverage_start && $this->bulk_coverage_end) {
            $generatedPeriods = [];
            $current = $this->bulk_coverage_start->copy()->startOfMonth();
            $end = $this->bulk_coverage_end->copy()->startOfMonth();

            while ($current <= $end) {
                $generatedPeriods[] = $current->format('Y-m');
                $current->addMonth();
            }

            return $generatedPeriods;
        }

        return [];
    }

    public function activateBulkCoverage(array $periods, ?string $start = null, ?string $end = null): bool
    {
        if (!$this->isBulkPayment()) {
            return false;
        }

        $this->covers_periods = $periods;
        
        if ($start) {
            $this->bulk_coverage_start = Carbon::createFromFormat('Y-m', $start)->startOfMonth();
        }
        
        if ($end) {
            $this->bulk_coverage_end = Carbon::createFromFormat('Y-m', $end)->endOfMonth();
        }

        $metadata = $this->metadata ?? [];
        $metadata['coverage_activated_at'] = now()->toDateTimeString();
        $this->metadata = $metadata;

        return $this->save();
    }

    public function markAsYearEndArchived(int $year): bool
    {
        return $this->update([
            'year_end_archived_at' => now(),
            'year_end_archive_year' => $year,
            'original_year' => $year,
            'archive_type' => 'year_end',
            'metadata' => array_merge($this->metadata ?? [], [
                'year_end_archived' => true,
                'year_end_archived_at' => now()->toDateTimeString(),
                'year_end_archive_year' => $year
            ])
        ]);
    }

    // ========== PAYMENT PROCESSING METHODS ==========

    public function markAsProcessing(?string $paymentMethod = null): void
    {
        $data = [
            'status' => self::STATUS_PROCESSING,
            'updated_by' => auth()->check() ? auth()->id() : null
        ];

        if ($paymentMethod) {
            $data['payment_method'] = $paymentMethod;
        }

        $this->update($data);
        
        $this->addNote('Payment processing started via ' . ($paymentMethod ?? 'unknown method'));
    }

    public function markAsPaid($paymentMethod, $paymentReference = null, $paidAmount = null): void
    {
        $data = [
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'payment_date' => now(),
            'updated_by' => auth()->check() ? auth()->id() : null
        ];

        if ($paidAmount && $paidAmount < $this->total_amount) {
            $data['status'] = self::STATUS_PARTIAL;
            
            $metadata = $this->metadata ?? [];
            $metadata['partial_payments'] = array_merge($metadata['partial_payments'] ?? [], [
                [
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $this->total_amount - $paidAmount,
                    'paid_at' => now()->toDateTimeString(),
                    'reference' => $paymentReference
                ]
            ]);
            $data['metadata'] = $metadata;
        } else {
            $data['status'] = self::STATUS_PAID;
            
            if ($this->isBulkPayment() && !empty($this->covers_periods)) {
                $metadata = $this->metadata ?? [];
                $metadata['coverage_activated_at'] = now()->toDateTimeString();
                $data['metadata'] = $metadata;
            }
        }

        $this->update($data);
        
        $this->markNotificationSent('payment_confirmation');
        
        $note = "Marked as " . ($data['status'] === self::STATUS_PARTIAL ? 'partially paid' : 'paid') . " via {$paymentMethod}";
        if ($paymentReference) {
            $note .= " (Ref: {$paymentReference})";
        }
        if ($paidAmount && $paidAmount < $this->total_amount) {
            $note .= " - Amount paid: " . number_format($paidAmount, 2);
        }
        $this->addNote($note);
    }

    public function applyPenalty(float $penaltyAmount, ?string $reason = null): void
    {
        // ✅ FIX: Don't apply penalties to consolidated invoices
        if ($this->status === self::STATUS_CONSOLIDATED) {
            Log::warning("Attempted to apply penalty to consolidated invoice #{$this->id}");
            return;
        }
        
        $this->update([
            'penalty_amount' => $penaltyAmount,
            'penalty_applied_date' => now(),
            'status' => $this->due_date && $this->due_date->isPast() 
                ? self::STATUS_OVERDUE 
                : $this->status,
            'updated_by' => auth()->check() ? auth()->id() : null
        ]);

        $this->addNote("Penalty applied: " . ($reason ? $reason . " - " : "") . "Amount: " . number_format($penaltyAmount, 2));
    }

    public function removePenalty(?string $reason = null): void
    {
        $this->update([
            'penalty_amount' => 0.00,
            'penalty_applied_date' => null,
            'updated_by' => auth()->check() ? auth()->id() : null
        ]);

        if ($reason) {
            $this->addNote("Penalty removed: {$reason}");
        }
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'updated_by' => auth()->check() ? auth()->id() : null
        ]);

        if ($reason) {
            $this->addNote("Invoice cancelled: {$reason}");
        }
    }

    public function resetToPending(): void
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'payment_method' => null,
            'payment_reference' => null,
            'payment_date' => null,
            'updated_by' => auth()->check() ? auth()->id() : null
        ]);
        
        $this->addNote('Payment failed/cancelled - reset to pending');
    }

    // ========== NOTIFICATION METHODS ==========

    public function markNotificationSent(string $type = 'generation'): bool
    {
        $metadata = $this->metadata ?? [];
        
        if (!isset($metadata['notifications'])) {
            $metadata['notifications'] = [];
        }
        
        $metadata['notifications'][$type] = [
            'sent_at' => now()->toDateTimeString(),
            'type' => $type
        ];
        
        $metadata['notification_sent_at'] = now()->toDateTimeString();
        $metadata['notification_sent_count'] = ($metadata['notification_sent_count'] ?? 0) + 1;
        
        $this->metadata = $metadata;
        
        return $this->save();
    }

    public function markReminderSent(): bool
    {
        $this->last_reminder_sent_at = now();
        $this->reminder_count = ($this->reminder_count ?? 0) + 1;
        
        $metadata = $this->metadata ?? [];
        
        if (!isset($metadata['reminders'])) {
            $metadata['reminders'] = [];
        }
        
        $metadata['reminders'][] = [
            'sent_at' => now()->toDateTimeString(),
            'days_before_due' => $this->days_until_due
        ];
        
        $this->metadata = $metadata;
        
        return $this->save();
    }

    public function shouldSendReminder(int $reminderDaysBefore): bool
    {
        if (!$this->isPayable()) {
            return false;
        }
        
        $daysUntilDue = $this->days_until_due;
        
        if ($daysUntilDue === null || $daysUntilDue > $reminderDaysBefore) {
            return false;
        }
        
        if ($this->last_reminder_sent_at && $this->last_reminder_sent_at->isToday()) {
            return false;
        }
        
        return true;
    }

    public function getReminderStatus(): array
    {
        $settings = SystemSetting::getSettings();
        
        return [
            'reminders_enabled' => $settings->shouldSendPaymentReminders(),
            'reminder_days' => $settings->getReminderDaysBefore(),
            'should_send' => $this->shouldSendReminder($settings->getReminderDaysBefore()),
            'last_sent' => $this->last_reminder_sent_at,
            'count' => $this->reminder_count ?? 0
        ];
    }

    // ========== UTILITY METHODS ==========

    public function addNote(string $note): void
    {
        $currentNotes = $this->notes ?? '';
        $timestamp = now()->format('Y-m-d H:i:s');
        $userName = auth()->check() ? auth()->user()->name : 'System';
        
        $newNote = "[{$timestamp}] {$userName}: {$note}";
        
        $this->update([
            'notes' => ($currentNotes ? $currentNotes . "\n" : '') . $newNote
        ]);
    }

    public function updateMetadata(array $metadata, bool $merge = true): void
    {
        $currentMetadata = $this->metadata ?? [];
        
        if ($merge) {
            $newMetadata = array_merge($currentMetadata, $metadata);
        } else {
            $newMetadata = $metadata;
        }
        
        $this->update(['metadata' => $newMetadata]);
    }

    public function generatePaymentReference(): string
    {
        return 'INV-' . strtoupper(uniqid()) . '-' . $this->id;
    }

    public function getTotalDue(): float
    {
        return ($this->amount ?? 0) + ($this->penalty_amount ?? 0);
    }

    public function validateForGeneration(): array
    {
        $errors = [];
        
        if (!$this->property) {
            $errors[] = 'Property not found';
        } elseif ($this->property->status !== 'active') {
            $errors[] = 'Property is not active';
        }
        
        if ($this->amount <= 0) {
            $errors[] = 'Invoice amount must be greater than 0';
        }
        
        if (!$this->due_date || $this->due_date->isPast()) {
            $errors[] = 'Due date must be in the future';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    // ========== ACCESSOR METHODS ==========

    public function getTotalAmountAttribute(): float
    {
        return ($this->amount ?? 0) + ($this->penalty_amount ?? 0);
    }

    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_PAID => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_CONSOLIDATED => 'Consolidated',
            default => ucfirst($this->status)
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'bg-green-100 text-green-800',
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_PROCESSING => 'bg-blue-100 text-blue-800',
            self::STATUS_OVERDUE => 'bg-red-100 text-red-800',
            self::STATUS_PARTIAL => 'bg-purple-100 text-purple-800',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-800',
            self::STATUS_REFUNDED => 'bg-indigo-100 text-indigo-800',
            self::STATUS_CONSOLIDATED => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'success',
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_OVERDUE => 'danger',
            self::STATUS_PARTIAL => 'primary',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_REFUNDED => 'indigo',
            self::STATUS_CONSOLIDATED => 'orange',
            default => 'secondary'
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'fas fa-check-circle',
            self::STATUS_PENDING => 'fas fa-clock',
            self::STATUS_PROCESSING => 'fas fa-spinner fa-spin',
            self::STATUS_OVERDUE => 'fas fa-exclamation-circle',
            self::STATUS_PARTIAL => 'fas fa-adjust',
            self::STATUS_CANCELLED => 'fas fa-ban',
            self::STATUS_REFUNDED => 'fas fa-undo',
            self::STATUS_CONSOLIDATED => 'fas fa-layer-group',
            default => 'fas fa-question-circle'
        };
    }

    public function getFormattedPeriodAttribute(): string
    {
        if ($this->is_bulk_payment) {
            if ($this->bulk_coverage_start && $this->bulk_coverage_end) {
                return $this->bulk_coverage_start->format('M Y') . ' - ' . $this->bulk_coverage_end->format('M Y');
            }
            return 'Bulk Payment';
        }
        
        try {
            return Carbon::createFromFormat('Y-m', $this->period)->format('F Y');
        } catch (\Exception $e) {
            return $this->period;
        }
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isOverdue() || !$this->due_date) {
            return 0;
        }
        
        return max(0, now()->startOfDay()->diffInDays($this->due_date->startOfDay()));
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date || $this->isPaid() || $this->isOverdue() || $this->isConsolidated()) {
            return null;
        }
        
        $days = now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);
        return $days > 0 ? $days : 0;
    }

    public function getIsDueSoonAttribute(): bool
    {
        if ($this->isPaid() || $this->isOverdue() || $this->isConsolidated() || !$this->due_date) {
            return false;
        }
        
        $daysUntil = $this->days_until_due;
        return $daysUntil !== null && $daysUntil <= 7 && $daysUntil > 0;
    }

    public function getHasPenaltyAttribute(): bool
    {
        return $this->hasPenalty();
    }

    public function getPaymentMethodDisplayAttribute(): string
    {
        if (!$this->payment_method) return 'N/A';
        
        return match($this->payment_method) {
            self::METHOD_CASH => 'Cash',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CHEQUE => 'Cheque',
            self::METHOD_CARD => 'Credit/Debit Card',
            self::METHOD_MOBILE_MONEY => 'Mobile Money',
            self::METHOD_MTN_MOMO => 'MTN Mobile Money',
            self::METHOD_TELECEL_CASH => 'Telecel Cash',
            self::METHOD_AIRTELTIGO_CASH => 'AirtelTigo Cash',
            self::METHOD_PAYSTACK => 'Paystack',
            self::METHOD_BULK_PAYMENT => 'Bulk Payment',
            default => ucwords(str_replace('_', ' ', $this->payment_method))
        };
    }

    public function getIsProcessingAttribute(): bool
    {
        return $this->isProcessing();
    }

    public function getCanCancelAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    public function getCanEditAttribute(): bool
    {
        return $this->canBeEdited();
    }

    public function getFormattedAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->amount);
    }

    public function getFormattedPenaltyAttribute(): string
    {
        if (!$this->penalty_amount) {
            return 'GHS 0.00';
        }
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->penalty_amount);
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        $settings = SystemSetting::getSettings();
        return $settings->formatAmount($this->total_amount);
    }

    public function getNotificationSentAttribute(): bool
    {
        return isset($this->metadata['notification_sent_at']);
    }

    public function getNotificationSentAtAttribute(): ?Carbon
    {
        if (isset($this->metadata['notification_sent_at'])) {
            return Carbon::parse($this->metadata['notification_sent_at']);
        }
        return null;
    }

    public function getReminderSentAttribute(): bool
    {
        return !is_null($this->last_reminder_sent_at);
    }

    public function getLastReminderSentAtDisplayAttribute(): ?string
    {
        if (!$this->last_reminder_sent_at) {
            return null;
        }
        
        return $this->last_reminder_sent_at->diffForHumans();
    }

    public function getGenerationMethodAttribute(): string
    {
        if ($this->metadata['auto_generated'] ?? false) {
            return 'auto';
        }
        if ($this->metadata['manual_generated'] ?? false) {
            return 'manual';
        }
        return 'unknown';
    }

    public function getCoverageSummaryAttribute(): ?array
    {
        if (!$this->isBulkPayment()) {
            return null;
        }

        $periods = $this->getCoveredPeriods();
        
        return [
            'total_months' => count($periods),
            'periods' => $periods,
            'formatted_periods' => collect($periods)->map(function($period) {
                return Carbon::createFromFormat('Y-m', $period)->format('M Y');
            })->toArray(),
            'start' => $this->bulk_coverage_start?->format('M Y'),
            'end' => $this->bulk_coverage_end?->format('M Y'),
            'is_active' => $this->isPaid()
        ];
    }

    public function getIsCoverageActiveAttribute(): bool
    {
        return $this->isBulkPayment() && $this->isPaid() && !empty($this->covers_periods);
    }

    // ========== STATIC METHODS ==========

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_PAID,
            self::STATUS_OVERDUE,
            self::STATUS_PARTIAL,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
            self::STATUS_CONSOLIDATED,
        ];
    }

    public static function getPaymentMethods(): array
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CHEQUE => 'Cheque',
            self::METHOD_CARD => 'Credit/Debit Card',
            self::METHOD_MOBILE_MONEY => 'Mobile Money',
            self::METHOD_MTN_MOMO => 'MTN Mobile Money',
            self::METHOD_TELECEL_CASH => 'Telecel Cash',
            self::METHOD_AIRTELTIGO_CASH => 'AirtelTigo Cash',
            self::METHOD_PAYSTACK => 'Paystack',
            self::METHOD_BULK_PAYMENT => 'Bulk Payment',
        ];
    }

    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'property' => $this->property->name ?? 'Unknown',
            'unit' => $this->unit->unit_number ?? null,
            'period' => $this->formatted_period,
            'amount' => $this->formatted_amount,
            'penalty' => $this->formatted_penalty,
            'total' => $this->formatted_total_amount,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => [
                'text' => $this->status_display,
                'color' => $this->status_color,
                'badge' => $this->status_badge_class,
                'icon' => $this->status_icon
            ],
            'days_until_due' => $this->days_until_due,
            'days_overdue' => $this->days_overdue,
            'is_payable' => $this->isPayable(),
            'is_bulk' => $this->isBulkPayment(),
            'coverage' => $this->coverage_summary,
            'reminder_status' => $this->getReminderStatus(),
            'notification_sent' => $this->notification_sent,
            'generation_method' => $this->generation_method,
            'is_year_end_archived' => $this->isYearEndArchived(),
            'year_end_archive_year' => $this->year_end_archive_year,
            'original_year' => $this->original_year,
            'archive_type' => $this->archive_type
        ];
    }

    // ========== BOOT METHOD ==========

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            // Set created_by if not set
            if (auth()->check() && !isset($invoice->created_by)) {
                $invoice->created_by = auth()->id();
            } elseif (!isset($invoice->created_by)) {
                $invoice->created_by = 1; // System user ID
            }
            
            // Generate invoice number if not set
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = 'INV-' . date('Y') . '-' . str_pad(self::max('id') + 1, 6, '0', STR_PAD_LEFT);
            }
            
            if (!$invoice->due_date) {
                $invoice->due_date = now()->addDays(30);
            }
            
            if (is_null($invoice->penalty_amount)) {
                $invoice->penalty_amount = 0.00;
            }
            
            if (isset($invoice->attributes['total_amount'])) {
                unset($invoice->attributes['total_amount']);
            }

            if ($invoice->is_bulk_payment && is_null($invoice->covers_periods)) {
                $invoice->covers_periods = [];
            }
        });

        static::updating(function ($invoice) {
            if (isset($invoice->attributes['total_amount'])) {
                unset($invoice->attributes['total_amount']);
            }
            
            // Set updated_by on update
            if (auth()->check() && !isset($invoice->updated_by)) {
                $invoice->updated_by = auth()->id();
            }
        });

        static::deleting(function ($invoice) {
            if ($invoice->isBulkPayment()) {
                self::where('bulk_payment_id', $invoice->id)
                    ->update([
                        'bulk_payment_id' => null,
                        'bulk_payment_reference' => null,
                        'updated_by' => auth()->check() ? auth()->id() : null
                    ]);
            }
            
            if ($invoice->isPaid()) {
                throw new \Exception('Cannot delete paid invoices');
            }
            
            if ($invoice->isConsolidated()) {
                throw new \Exception('Cannot delete consolidated invoices');
            }
        });
    }
}