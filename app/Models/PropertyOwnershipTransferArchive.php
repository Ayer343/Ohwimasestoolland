<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PropertyOwnershipTransferArchive extends Model
{
    protected $table = 'property_ownership_transfer_archives';
    
    protected $fillable = [
        'original_transfer_id',
        'property_id',
        'current_landlord_id',
        'new_landlord_id',
        'requested_by_id',
        'admin_approved_by_id',
        'completed_by_id',
        'rejected_by_id',
        'deleted_by',
        'status',
        'transfer_date',
        'sale_amount',
        'document_type',
        'document_reference',
        'document_url',
        'certificate_url',
        'new_owner_name',
        'new_owner_phone',
        'new_owner_email',
        'new_owner_address',
        'reason_for_transfer',
        'notes',
        'admin_notes',
        'rejection_reason',
        'approved_at',
        'completed_at',
        'rejected_at',
        'cancelled_at',
        'can_resubmit_after',
        'archive_year',
        'archive_month',
        'archived_at',
        'archived_by',
        'archive_reason',
        'metadata',
        'original_metadata',
        'reversal_status',
        'is_reversed',
        'reversal_transfer_id',
    ];
    
    protected $casts = [
        'transfer_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'archived_at' => 'datetime',
        'can_resubmit_after' => 'datetime',
        'sale_amount' => 'decimal:2',
        'metadata' => 'array',
        'original_metadata' => 'array',
        'is_reversed' => 'boolean',
    ];
    
    protected $appends = [
        'status_label',
        'document_type_label',
        'formatted_sale_amount',
        'is_reversal_record',
        'reversal_status_label',
    ];
    
    // =============================================
    // RELATIONSHIPS
    // =============================================
    
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id')->withTrashed();
    }
    
    public function currentLandlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_landlord_id')->withTrashed();
    }
    
    public function newLandlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_landlord_id')->withTrashed();
    }
    
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id')->withTrashed();
    }
    
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by')->withTrashed();
    }
    
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_approved_by_id')->withTrashed();
    }
    
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id')->withTrashed();
    }
    
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id')->withTrashed();
    }
    
    public function originalTransfer(): BelongsTo
    {
        return $this->belongsTo(PropertyOwnershipTransfer::class, 'original_transfer_id')->withTrashed();
    }
    
    public function reversalTransfer(): BelongsTo
    {
        return $this->belongsTo(PropertyOwnershipTransfer::class, 'reversal_transfer_id')->withTrashed();
    }
    
    // =============================================
    // REVERSAL METHODS - FIXED (These were missing)
    // =============================================
    
    /**
     * Check if this archived record is a reversal record
     */
    public function isReversalRecord(): bool
    {
        if ($this->original_transfer_id) {
            return true;
        }
        
        return isset($this->metadata['is_reversal']) && $this->metadata['is_reversal'] === true;
    }
    
    /**
     * Get the reversal status label
     */
    public function getReversalStatusLabelAttribute(): string
    {
        $labels = [
            'none' => 'No Reversal',
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'expired' => 'Expired',
        ];
        
        return $labels[$this->reversal_status] ?? ucfirst($this->reversal_status ?? 'none');
    }
    
    /**
     * Get the is_reversal_record appended attribute
     */
    public function getIsReversalRecordAttribute(): bool
    {
        return $this->isReversalRecord();
    }
    
    /**
     * Get reversal details if applicable
     */
    public function getReversalDetailsAttribute(): ?array
    {
        if (!$this->isReversalRecord() && $this->reversal_status === 'none') {
            return null;
        }
        
        return [
            'is_reversal_record' => $this->isReversalRecord(),
            'status' => $this->reversal_status,
            'status_label' => $this->reversal_status_label,
            'is_reversed' => $this->is_reversed,
            'original_transfer_id' => $this->original_transfer_id,
            'reversal_transfer_id' => $this->reversal_transfer_id,
        ];
    }
    
    // =============================================
    // ACCESSORS
    // =============================================
    
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }
    
    public function getDocumentTypeLabelAttribute(): string
    {
        $types = [
            'deed_of_assignment' => 'Deed of Assignment',
            'sale_agreement' => 'Sale Agreement',
            'transfer_instrument' => 'Transfer Instrument',
            'court_order' => 'Court Order',
            'will_probate' => 'Will/Probate',
            'gift_deed' => 'Gift Deed',
            'other' => 'Other',
        ];
        
        return $types[$this->document_type] ?? ucfirst(str_replace('_', ' ', $this->document_type));
    }
    
    public function getFormattedSaleAmountAttribute(): string
    {
        if (!$this->sale_amount) {
            return 'N/A';
        }
        return '₵' . number_format($this->sale_amount, 2);
    }
    
    public function getDocumentUrlAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        return Storage::disk('public')->url($value);
    }
    
    public function getCertificateUrlAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        return Storage::disk('public')->url($value);
    }
    
    public function getArchivePeriodAttribute(): string
    {
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        $month = $monthNames[$this->archive_month] ?? '';
        return trim($month . ' ' . $this->archive_year);
    }
    
    // =============================================
    // SCOPES
    // =============================================
    
    public function scopeForYear($query, $year)
    {
        return $query->where('archive_year', $year);
    }
    
    public function scopeForMonth($query, $month)
    {
        return $query->where('archive_month', $month);
    }
    
    public function scopeForYearAndMonth($query, $year, $month)
    {
        return $query->where('archive_year', $year)->where('archive_month', $month);
    }
    
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transfer_date', [$startDate, $endDate]);
    }
    
    public function scopeReversalRecords($query)
    {
        return $query->where(function($q) {
            $q->whereNotNull('original_transfer_id')
              ->orWhere('metadata->is_reversal', true);
        });
    }
    
    public function scopeRegularRecords($query)
    {
        return $query->where(function($q) {
            $q->whereNull('original_transfer_id')
              ->where(function($sub) {
                  $sub->whereNull('metadata->is_reversal')
                      ->orWhere('metadata->is_reversal', false);
              });
        });
    }
    
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeByArchiveReason($query, $reason)
    {
        return $query->where('archive_reason', $reason);
    }
    
    public function scopeArchivedByUser($query, $userId)
    {
        return $query->where('archived_by', $userId);
    }
    
    public function scopeArchivedAfter($query, $date)
    {
        return $query->where('archived_at', '>=', $date);
    }
    
    public function scopeArchivedBefore($query, $date)
    {
        return $query->where('archived_at', '<=', $date);
    }
    
    // =============================================
    // STATISTICS METHODS
    // =============================================
    
    public static function getStatisticsByYear(int $year): array
    {
        return [
            'total' => self::where('archive_year', $year)->count(),
            'completed' => self::where('archive_year', $year)->where('status', 'completed')->count(),
            'rejected' => self::where('archive_year', $year)->where('status', 'rejected')->count(),
            'cancelled' => self::where('archive_year', $year)->where('status', 'cancelled')->count(),
            'pending' => self::where('archive_year', $year)->where('status', 'pending')->count(),
            'approved' => self::where('archive_year', $year)->where('status', 'approved')->count(),
            'total_value' => self::where('archive_year', $year)->where('status', 'completed')->sum('sale_amount'),
            'reversal_count' => self::where('archive_year', $year)->reversalRecords()->count(),
            'regular_count' => self::where('archive_year', $year)->regularRecords()->count(),
        ];
    }
    
    public static function getOverallStatistics(): array
    {
        return [
            'total_archived' => self::count(),
            'total_value' => self::where('status', 'completed')->sum('sale_amount'),
            'years_available' => self::select('archive_year')->distinct()->orderBy('archive_year', 'desc')->pluck('archive_year')->toArray(),
            'reversal_count' => self::reversalRecords()->count(),
            'regular_count' => self::regularRecords()->count(),
            'by_status' => self::select('status', \DB::raw('COUNT(*) as count'))->groupBy('status')->get(),
            'by_year' => self::select('archive_year', \DB::raw('COUNT(*) as count'))->groupBy('archive_year')->orderBy('archive_year', 'desc')->get(),
            'by_archive_reason' => self::select('archive_reason', \DB::raw('COUNT(*) as count'))->groupBy('archive_reason')->get(),
        ];
    }
    
    public static function getArchivedMonths(): array
    {
        return self::select('archive_year', 'archive_month')
            ->distinct()
            ->orderBy('archive_year', 'desc')
            ->orderBy('archive_month', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'year' => $item->archive_year,
                    'month' => $item->archive_month,
                    'period' => $item->archive_period,
                ];
            })
            ->toArray();
    }
    
    // =============================================
    // FILE MANAGEMENT
    // =============================================
    
    public function deleteAssociatedFiles(): bool
    {
        $success = true;
        
        if ($this->document_url) {
            $path = str_replace('/storage/', '', parse_url($this->document_url, PHP_URL_PATH));
            if ($path && Storage::disk('public')->exists($path)) {
                $success = $success && Storage::disk('public')->delete($path);
            }
        }
        
        if ($this->certificate_url) {
            $path = str_replace('/storage/', '', parse_url($this->certificate_url, PHP_URL_PATH));
            if ($path && Storage::disk('public')->exists($path)) {
                $success = $success && Storage::disk('public')->delete($path);
            }
        }
        
        return $success;
    }
    
    // =============================================
    // BOOT METHOD
    // =============================================
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($archive) {
            if (empty($archive->archive_year)) {
                $archive->archive_year = now()->year;
            }
            
            if (empty($archive->archive_month)) {
                $archive->archive_month = now()->month;
            }
            
            if (empty($archive->archived_at)) {
                $archive->archived_at = now();
            }
            
            if (empty($archive->reversal_status)) {
                $archive->reversal_status = 'none';
            }
        });
        
        static::created(function ($archive) {
            Log::info('Transfer archived', [
                'archive_id' => $archive->id,
                'original_transfer_id' => $archive->original_transfer_id,
                'archive_year' => $archive->archive_year,
                'archive_month' => $archive->archive_month,
                'archived_by' => $archive->archived_by,
                'is_reversal' => $archive->isReversalRecord(),
                'document_reference' => $archive->document_reference,
            ]);
        });
        
        static::deleting(function ($archive) {
            Log::info('Archive being deleted', [
                'archive_id' => $archive->id,
                'document_reference' => $archive->document_reference,
                'is_reversal' => $archive->isReversalRecord(),
                'archive_year' => $archive->archive_year,
            ]);
            
            $archive->deleteAssociatedFiles();
        });
        
        static::deleted(function ($archive) {
            Log::info('Archive deleted', [
                'archive_id' => $archive->id,
                'document_reference' => $archive->document_reference,
                'deleted_by' => auth()->id() ?? 'system',
            ]);
        });
    }
}