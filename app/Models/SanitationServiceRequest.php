<?php
// app/Models/SanitationServiceRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SanitationServiceRequest extends Model
{
    use SoftDeletes;

    // ------------------------------------------------------------- //
    // ✅ Status constants — use these everywhere, never raw strings
    // ------------------------------------------------------------- //
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    // ------------------------------------------------------------- //
    // ✅ FIX: STATUS_APPROVED alias
    //
    // The WasteCollectionController (and possibly other callers)
    // references SanitationServiceRequest::STATUS_APPROVED. Semantically,
    // "approve" and "accept" represent the same state in this model's
    // lifecycle — a supervisor accepting a landlord's service request.
    //
    // Keep STATUS_ACCEPTED as the primary name (it's already used by
    // scopes, helpers, and the accept() transition method), and expose
    // STATUS_APPROVED as an alias so both names work without a migration
    // or refactor.
    // ------------------------------------------------------------- //
    public const STATUS_APPROVED  = self::STATUS_ACCEPTED;   // = 'accepted'

    // Priority constants
    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH   = 'high';

    /**
     * How long a pending service request stays valid before auto-expiry.
     */
    public const EXPIRY_DAYS = 14;

    /**
     * Default currency — kept here so downstream code and blades
     * never need to hardcode "GHS".
     */
    public const DEFAULT_CURRENCY = 'GHS';

    /**
     * The table associated with the model.
     */
    protected $table = 'sanitation_service_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // Relations
        'property_id',
        'landlord_id',
        'sanitation_personnel_id',
        'responded_by',

        // Lifecycle
        'status',
        'priority',
        'message',
        'response_notes',
        'responded_at',
        'expires_at',

        // Denormalized snapshot
        'property_name',
        'digital_address',
        'landlord_name',
        'landlord_phone',
        'landlord_email',
        'preferred_supervisor_name',

        // ✅ Agreement snapshot
        'collection_frequency',
        'quoted_monthly_fee',
        'quoted_emergency_fee',
        'quoted_currency',
        'agreement_accepted_at',
        'agreement_accepted_ip',
        'agreement_terms_snapshot',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        // Timestamps
        'responded_at'          => 'datetime',
        'expires_at'            => 'datetime',
        'agreement_accepted_at' => 'datetime',

        // Numeric
        'quoted_monthly_fee'    => 'float',
        'quoted_emergency_fee'  => 'float',

        // JSON
        'agreement_terms_snapshot' => 'array',
    ];

    // ================================================================ //
    // 🔗 RELATIONSHIPS                                                //
    // ================================================================ //

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(SanitationPersonnel::class, 'sanitation_personnel_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    // ================================================================ //
    // 🔍 SCOPES                                                       //
    // ================================================================ //

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeAccepted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * ✅ FIX: Alias scope so callers can use ->approved() interchangeably
     *         with ->accepted(). Same query, clearer name at call sites.
     */
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_REJECTED);
    }

    public function scopeForLandlord(Builder $q, int $landlordId): Builder
    {
        return $q->where('landlord_id', $landlordId);
    }

    public function scopeForProperty(Builder $q, int $propertyId): Builder
    {
        return $q->where('property_id', $propertyId);
    }

    public function scopeForFrequency(Builder $q, string $frequency): Builder
    {
        return $q->where('collection_frequency', $frequency);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
        ]);
    }

    /**
     * Pending requests that have not yet expired.
     */
    public function scopeUnexpired(Builder $q): Builder
    {
        return $q->where(function (Builder $inner) {
            $inner->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
        });
    }

    // ================================================================ //
    // ✅ STATUS HELPERS                                               //
    // ================================================================ //

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * ✅ FIX: Alias helper — treats "approved" as synonymous with "accepted"
     *         so callers can check either name.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Can the request still be responded to by a supervisor?
     */
    public function canBeRespondedTo(): bool
    {
        return $this->isPending() && !$this->expires_at?->isPast();
    }

    // ================================================================ //
    // 💰 AGREEMENT HELPERS                                            //
    // ================================================================ //

    /**
     * Has the landlord accepted the terms and pricing for this request?
     */
    public function hasAgreement(): bool
    {
        return !is_null($this->agreement_accepted_at);
    }

    /**
     * Human label for the agreed frequency, e.g. "Weekly".
     */
    public function getFrequencyLabelAttribute(): ?string
    {
        if (!$this->collection_frequency) {
            return null;
        }

        return SanitationSetting::getFrequencyLabel($this->collection_frequency);
    }

    /**
     * "GH₵ 200.00" — formatted monthly fee, or null if not set.
     */
    public function getFormattedMonthlyFeeAttribute(): ?string
    {
        return $this->formatFee($this->quoted_monthly_fee);
    }

    /**
     * "GH₵ 90.00" — formatted emergency fee, or null if not set.
     */
    public function getFormattedEmergencyFeeAttribute(): ?string
    {
        return $this->formatFee($this->quoted_emergency_fee);
    }

    /**
     * Symbol/prefix for the currency (kept simple — extend if you add more).
     */
    public function getCurrencySymbolAttribute(): string
    {
        return match ($this->quoted_currency ?: self::DEFAULT_CURRENCY) {
            'GHS' => 'GH₵',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'NGN' => '₦',
            default => $this->quoted_currency ?: self::DEFAULT_CURRENCY,
        };
    }

    /**
     * Format a numeric amount using the row's currency.
     */
    private function formatFee(?float $amount): ?string
    {
        if (is_null($amount)) {
            return null;
        }

        return $this->currency_symbol . ' ' . number_format($amount, 2);
    }

    /**
     * Compact summary of the agreed plan, e.g.
     *   "Weekly plan — GH₵ 200.00 / month + GH₵ 90.00 emergency"
     */
    public function getAgreementSummaryAttribute(): ?string
    {
        if (!$this->hasAgreement()) {
            return null;
        }

        $parts = [];

        if ($this->frequency_label) {
            $parts[] = $this->frequency_label . ' plan';
        }
        if ($this->quoted_monthly_fee !== null) {
            $parts[] = $this->currency_symbol . ' '
                . number_format($this->quoted_monthly_fee, 2) . ' / month';
        }
        if ($this->quoted_emergency_fee !== null) {
            $parts[] = '+ ' . $this->currency_symbol . ' '
                . number_format($this->quoted_emergency_fee, 2) . ' emergency';
        }

        return $parts ? implode(' — ', array_slice($parts, 0, 1)) . ' ' . implode(' ', array_slice($parts, 1)) : null;
    }

    // ================================================================ //
    // 📝 STATE TRANSITIONS                                            //
    // ================================================================ //

    /**
     * Accept the request — called by the supervisor.
     * (Aliased by "approve" in the caller's vocabulary.)
     */
    public function accept(int $respondedBy, ?string $notes = null): bool
    {
        return $this->update([
            'status'        => self::STATUS_ACCEPTED,
            'responded_by'  => $respondedBy,
            'responded_at'  => now(),
            'response_notes'=> $notes,
        ]);
    }

    /**
     * ✅ FIX: Alias transition — identical behaviour to accept(), but
     *         exposed under the "approve" verb so controller code reads
     *         naturally without needing to know the internal naming.
     */
    public function approve(int $respondedBy, ?string $notes = null): bool
    {
        return $this->accept($respondedBy, $notes);
    }

    /**
     * Reject the request — called by the supervisor.
     */
    public function reject(int $respondedBy, ?string $notes = null): bool
    {
        return $this->update([
            'status'         => self::STATUS_REJECTED,
            'responded_by'   => $respondedBy,
            'responded_at'   => now(),
            'response_notes' => $notes,
        ]);
    }

    /**
     * Cancel the request — called by the landlord.
     */
    public function cancel(int $respondedBy, ?string $notes = null): bool
    {
        return $this->update([
            'status'         => self::STATUS_CANCELLED,
            'responded_by'   => $respondedBy,
            'responded_at'   => now(),
            'response_notes' => $notes,
        ]);
    }

    /**
     * Expire the request — called by a scheduled command.
     */
    public function expire(): bool
    {
        return $this->update([
            'status'       => self::STATUS_EXPIRED,
            'responded_at' => now(),
        ]);
    }

    // ================================================================ //
    // 🧮 STATIC HELPERS                                               //
    // ================================================================ //

    /**
     * Does a pending request already exist for the given property?
     */
    public static function hasPendingForProperty(int $propertyId): bool
    {
        return static::query()
            ->where('property_id', $propertyId)
            ->where('status', self::STATUS_PENDING)
            ->exists();
    }

    /**
     * Default expiry date for a new request.
     */
    public static function defaultExpiry(): Carbon
    {
        return now()->addDays(self::EXPIRY_DAYS);
    }

    /**
     * Snapshot payload for a new landlord agreement.
     */
    public static function buildAgreementSnapshot(
        string $frequency,
        array $pricing,
        ?string $currency = null,
        array $termsExtras = []
    ): array {
        return [
            'collection_frequency'    => $frequency,
            'quoted_monthly_fee'      => (float) ($pricing['per_month']  ?? 0),
            'quoted_emergency_fee'    => (float) ($pricing['emergency']  ?? 0),
            'quoted_currency'         => $currency ?: self::DEFAULT_CURRENCY,
            'agreement_accepted_at'   => now(),
            'agreement_accepted_ip'   => request()?->ip(),
            'agreement_terms_snapshot'=> array_merge([
                'accepted_at' => now()->toIso8601String(),
                'frequency'   => $frequency,
            ], $termsExtras),
        ];
    }

    // ================================================================ //
    // 🔧 BOOT                                                         //
    // ================================================================ //

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Default currency if not provided
            if (empty($model->quoted_currency)) {
                $model->quoted_currency = self::DEFAULT_CURRENCY;
            }

            // Default expiry if not provided
            if (empty($model->expires_at)) {
                $model->expires_at = self::defaultExpiry();
            }
        });
    }
}