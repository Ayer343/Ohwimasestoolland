<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AdCampaign extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT            = 'draft';
    public const STATUS_PENDING_PAYMENT  = 'pending_payment';
    public const STATUS_ACTIVE           = 'active';
    public const STATUS_PAUSED           = 'paused';
    public const STATUS_RENTED_EARLY     = 'rented_early';
    public const STATUS_EXPIRED          = 'expired';
    public const STATUS_CANCELLED        = 'cancelled';
    public const STATUS_REJECTED         = 'rejected';

    /**
     * Statuses that represent a finished campaign — no further
     * transitions, no further billing, safe to no-op.
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_RENTED_EARLY,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_REJECTED,
    ];

    // ================================================================
    // ✅ NEW: Landlord-deletable campaign states
    // ----------------------------------------------------------------
    // These are the only states in which a landlord may soft-delete
    // their own campaign. Any other state (pending_payment, active,
    // paused, rented_early, expired) must go through Cancel first,
    // which preserves the audit trail.
    // ================================================================
    public const LANDLORD_DELETABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CANCELLED,
        self::STATUS_REJECTED,
    ];

    // ================================================================
    // ✅ NEW: Invoice statuses that represent REAL money movement
    // ----------------------------------------------------------------
    // A pending invoice is created automatically during the draft
    // lifecycle — it becomes a financial record only once it's paid,
    // refunded, or fails at the gateway. A cancelled invoice is
    // equally pre-money (it was voided before or after payment was
    // attempted, but the attempt was aborted).
    //
    // Only the statuses below block landlord deletion.
    // ================================================================
    public const FINANCIAL_INVOICE_STATUSES = [
        AdInvoice::STATUS_PAID,
        AdInvoice::STATUS_REFUNDED,
        AdInvoice::STATUS_FAILED, // failed = gateway was contacted → audit it
    ];

    protected $fillable = [
        'uuid', 'property_unit_id', 'landlord_id', 'ad_pricing_plan_id',
        'plan_name', 'billing_period', 'duration_months',
        'unit_price_snapshot', 'total_paid', 'currency', 'status',
        'starts_at', 'ends_at', 'cancelled_at', 'cancel_reason',
        'rented_requested_at', 'rented_reviewed_by', 'rented_approved_at', 'rented_proof_path',
        'channels', 'views_count', 'clicks_count', 'sort_weight',
        'approved_by', 'approved_at',
    ];

    protected $casts = [
        'channels'            => 'array',
        'unit_price_snapshot' => 'decimal:2',
        'total_paid'          => 'decimal:2',
        'duration_months'     => 'integer',
        'views_count'         => 'integer',
        'clicks_count'        => 'integer',
        'sort_weight'         => 'integer',
        'starts_at'           => 'datetime',
        'ends_at'             => 'datetime',
        'cancelled_at'        => 'datetime',
        'rented_requested_at' => 'datetime',
        'rented_approved_at'  => 'datetime',
        'approved_at'         => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $campaign) {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relations ───────────────────────────────────────────
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AdPricingPlan::class, 'ad_pricing_plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(AdInvoice::class, 'ad_campaign_id');
    }

    // ─── Status helpers ──────────────────────────────────────
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->ends_at
            && $this->ends_at->isFuture();
    }

    public function isPendingPayment(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    /**
     * Is this campaign in a terminal state (finished, no further transitions)?
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * ✅ UPDATED: landlords can cancel:
     *   - ACTIVE           — normal "I want to stop this ad"
     *   - PAUSED           — cancel a paused ad
     *   - DRAFT            — clean up an unsaved campaign
     *   - PENDING_PAYMENT  — abandon a campaign before paying
     *
     * Admins bypass this entirely (see AdCampaignService::cancelCampaign).
     * Terminal states are handled separately (no-op in cancel()).
     */
    public function canBeCancelledByLandlord(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_DRAFT,
            self::STATUS_PENDING_PAYMENT,
        ], true);
    }

    public function canBeMarkedRented(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->rented_requested_at === null
            && $this->ends_at
            && $this->ends_at->isFuture();
    }

    // ================================================================
    // ✅ NEW: Landlord deletion helpers
    // ----------------------------------------------------------------
    // These methods are the SINGLE SOURCE OF TRUTH for whether a
    // landlord may delete their own campaign. Both the controller's
    // `destroy()` guard and the index blade's "can I show the Delete
    // button?" check call these — so the button state can never
    // disagree with the guard.
    // ================================================================

    /**
     * Does this campaign have any invoice that represents real money
     * movement (paid, refunded, or a failed gateway attempt)?
     *
     * Pending and cancelled invoices do NOT count — they're pre-money
     * records that can be discarded with the campaign.
     */
    public function hasFinancialInvoice(): bool
    {
        return $this->invoices()
            ->whereIn('status', self::FINANCIAL_INVOICE_STATUSES)
            ->exists();
    }

    /**
     * Has this campaign ever been shown publicly (views or clicks)?
     * Once it has, deleting would corrupt aggregate analytics.
     */
    public function hasPublicActivity(): bool
    {
        return ((int) $this->views_count) > 0
            || ((int) $this->clicks_count) > 0;
    }

    /**
     * ✅ FIXED: Can the landlord delete this campaign?
     *
     * All three conditions must be true:
     *   1. Status ∈ {draft, cancelled, rejected}
     *   2. No FINANCIAL invoice (pending/cancelled invoices don't count)
     *   3. No public views or clicks
     */
    public function canBeDeletedByLandlord(): bool
    {
        return in_array($this->status, self::LANDLORD_DELETABLE_STATUSES, true)
            && ! $this->hasFinancialInvoice()
            && ! $this->hasPublicActivity();
    }

    /**
     * ✅ NEW: Human-readable reason the landlord can't delete this
     * campaign. Used for tooltips on the disabled Delete button.
     * Returns an empty string when the campaign IS deletable.
     */
    public function landlordDeleteBlockReason(): string
    {
        if ($this->canBeDeletedByLandlord()) {
            return '';
        }

        if ($this->hasFinancialInvoice()) {
            return 'This campaign has a paid invoice on record — cancel it instead to preserve the financial record.';
        }

        if ($this->hasPublicActivity()) {
            return 'This campaign has been shown publicly (views/clicks) and cannot be deleted.';
        }

        return 'Only draft, cancelled, or rejected campaigns can be deleted.';
    }

    // ─── State machine transitions ───────────────────────────
    public function markAsActive(): void
    {
        if (!in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_DRAFT], true)) {
            throw new \RuntimeException("Cannot activate campaign from status: {$this->status}");
        }

        $this->status      = self::STATUS_ACTIVE;
        $this->starts_at   = now();
        $this->ends_at     = now()->addMonths($this->duration_months);
        $this->approved_at = now();
        $this->save();
    }

    public function markAsRentedEarly(User $approver): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new \RuntimeException("Cannot mark rented from status: {$this->status}");
        }

        $this->status              = self::STATUS_RENTED_EARLY;
        $this->ends_at             = now(); // ends now, no refund for current period
        $this->rented_approved_at  = now();
        $this->rented_reviewed_by  = $approver->id;
        $this->save();
    }

    /**
     * ✅ UPDATED: cancel is idempotent for terminal states, and covers
     *    RENTED_EARLY and REJECTED in addition to CANCELLED and EXPIRED.
     *
     * Preserves `starts_at` when the campaign had actually started (for
     * audit), and only sets `starts_at` to now when cancelling a campaign
     * that never reached ACTIVE.
     */
    public function cancel(?string $reason = null): void
    {
        // Terminal states are already resolved — no-op.
        if ($this->isTerminal()) {
            return;
        }

        $this->status        = self::STATUS_CANCELLED;
        $this->cancelled_at  = now();
        $this->cancel_reason = $reason;
        $this->ends_at       = now();

        // Preserve the original start time for audit; only stamp
        // starts_at if the campaign never actually started.
        if (!$this->starts_at) {
            $this->starts_at = now();
        }

        $this->save();
    }

    public function expire(): void
    {
        if ($this->status === self::STATUS_ACTIVE && $this->ends_at && $this->ends_at->isPast()) {
            $this->status = self::STATUS_EXPIRED;
            $this->save();
        }
    }

    public function pause(): void
    {
        if ($this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_PAUSED;
            $this->save();
        }
    }

    public function resume(): void
    {
        if ($this->status === self::STATUS_PAUSED && $this->ends_at && $this->ends_at->isFuture()) {
            $this->status = self::STATUS_ACTIVE;
            $this->save();
        }
    }

    // ─── Scopes ──────────────────────────────────────────────
    public function scopeActiveNow($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeForLandlord($query, int $landlordId)
    {
        return $query->where('landlord_id', $landlordId);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('ends_at', '<', now());
    }

    /**
     * ✅ NEW: scope for active campaigns that are in a terminal status —
     *    useful for cleanup jobs and for the admin view.
     */
    public function scopeInTerminalState($query)
    {
        return $query->whereIn('status', self::TERMINAL_STATUSES);
    }

    /**
     * ✅ NEW: scope for campaigns that can still be cancelled (i.e. not
     *    terminal) — useful when querying "cancellable" campaigns in bulk.
     */
    public function scopeCancellable($query)
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    /**
     * ✅ NEW: scope for campaigns the landlord can delete — draft /
     *    cancelled / rejected, no financial invoice, no public activity.
     */
    public function scopeLandlordDeletable($query)
    {
        return $query->whereIn('status', self::LANDLORD_DELETABLE_STATUSES)
            ->where(function ($q) {
                $q->where('views_count', 0)->orWhereNull('views_count');
            })
            ->where(function ($q) {
                $q->where('clicks_count', 0)->orWhereNull('clicks_count');
            })
            ->whereDoesntHave('invoices', function ($q) {
                $q->whereIn('status', self::FINANCIAL_INVOICE_STATUSES);
            });
    }
}