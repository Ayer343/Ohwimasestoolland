<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReminder extends Model
{
    use HasFactory;

    protected $table = 'invoice_reminders';

    /* ============================================================
     | FILLABLE
     * ============================================================ */
    protected $fillable = [
        'invoice_id',
        'developer_setting_id',
        'super_admin_id',
        'days_before_due',
        'scheduled_for',
        'channels',
        'status',
        'sent_at',
        'failure_reason',
        'delivery_results',
    ];

    /* ============================================================
     | CASTS
     * ============================================================ */
    protected $casts = [
        'invoice_id'           => 'integer',
        'developer_setting_id' => 'integer',
        'super_admin_id'       => 'integer',
        'days_before_due'      => 'integer',
        'scheduled_for'        => 'date',
        'sent_at'              => 'datetime',
        'delivery_results'     => 'array',
    ];

    /* ============================================================
     | CONSTANTS
     * ============================================================ */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const CHANNEL_EMAIL    = 'email';
    public const CHANNEL_SMS      = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    /* ============================================================
     | RELATIONSHIPS
     * ============================================================ */

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }

    public function developerSetting(): BelongsTo
    {
        return $this->belongsTo(DeveloperSetting::class, 'developer_setting_id');
    }

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    /* ============================================================
     | SCOPES — used by the scheduler & command
     * ============================================================ */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SKIPPED);
    }

    /**
     * Reminders whose scheduled_for has arrived (or passed).
     * Used by the DispatchPaymentReminders command.
     */
    public function scopeDue(Builder $query, ?Carbon $asOf = null): Builder
    {
        $asOf = $asOf ?? Carbon::today();

        return $query->whereDate('scheduled_for', '<=', $asOf->toDateString());
    }

    /**
     * Reminders for a specific invoice.
     */
    public function scopeForInvoice(Builder $query, int $invoiceId): Builder
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Reminders for a specific developer setting.
     */
    public function scopeForDeveloper(Builder $query, int $developerSettingId): Builder
    {
        return $query->where('developer_setting_id', $developerSettingId);
    }

    /* ============================================================
     | ACCESSORS — channel handling
     * ============================================================ */

    /**
     * Get channels as an array.
     * "email,sms" → ['email', 'sms']
     */
    public function getChannelsArrayAttribute(): array
    {
        $raw = $this->attributes['channels'] ?? 'email';

        if (!is_string($raw) || $raw === '') {
            return [self::CHANNEL_EMAIL];
        }

        $channels = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn ($c) => $c !== ''
        ));

        return $channels ?: [self::CHANNEL_EMAIL];
    }

    /**
     * Convenience flag — did this reminder target email?
     */
    public function getUsesEmailAttribute(): bool
    {
        return in_array(self::CHANNEL_EMAIL, $this->channels_array, true);
    }

    public function getUsesSmsAttribute(): bool
    {
        return in_array(self::CHANNEL_SMS, $this->channels_array, true);
    }

    public function getUsesWhatsappAttribute(): bool
    {
        return in_array(self::CHANNEL_WHATSAPP, $this->channels_array, true);
    }

    /**
     * Human-readable summary of channels, for UI badges.
     */
    public function getChannelsSummaryAttribute(): string
    {
        $labels = [
            self::CHANNEL_EMAIL    => '📧 Email',
            self::CHANNEL_SMS      => '📱 SMS',
            self::CHANNEL_WHATSAPP => '💬 WhatsApp',
        ];

        $parts = array_map(
            fn ($c) => $labels[$c] ?? $c,
            $this->channels_array
        );

        return implode(' + ', $parts);
    }

    /* ============================================================
     | STATUS HELPERS
     * ============================================================ */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    /**
     * Is this reminder overdue for dispatch?
     */
    public function isDue(?Carbon $asOf = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $asOf = $asOf ?? Carbon::today();

        return $this->scheduled_for
            && $this->scheduled_for->lte($asOf);
    }

    /* ============================================================
     | STATE TRANSITIONS — called by SendPaymentReminderJob & service
     * ============================================================ */

    /**
     * Mark as successfully sent, storing per-channel results.
     *
     * @param  array  $deliveryResults  e.g. ['email'=>['success'=>true], 'sms'=>['success'=>true]]
     */
    public function markAsSent(array $deliveryResults = []): bool
    {
        return $this->update([
            'status'           => self::STATUS_SENT,
            'sent_at'          => now(),
            'failure_reason'   => null,
            'delivery_results' => $deliveryResults ?: null,
        ]);
    }

    /**
     * Mark as failed with reason and per-channel results.
     */
    public function markAsFailed(string $reason, array $deliveryResults = []): bool
    {
        return $this->update([
            'status'           => self::STATUS_FAILED,
            'failure_reason'   => $reason,
            'delivery_results' => $deliveryResults ?: null,
        ]);
    }

    /**
     * Mark as skipped — used when the invoice is already paid, cancelled,
     * or the super admin has no delivery address.
     */
    public function markAsSkipped(string $reason = 'Skipped'): bool
    {
        return $this->update([
            'status'         => self::STATUS_SKIPPED,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Reset a failed reminder back to pending so it can be retried.
     */
    public function resetToPending(): bool
    {
        return $this->update([
            'status'         => self::STATUS_PENDING,
            'failure_reason' => null,
        ]);
    }

    /* ============================================================
     | QUERY HELPERS — static utilities
     * ============================================================ */

    /**
     * Count of pending reminders due today or earlier.
     * Used on the dashboard.
     */
    public static function countDue(?Carbon $asOf = null): int
    {
        return static::query()->pending()->due($asOf)->count();
    }

    /**
     * Number of reminders already sent for a specific invoice.
     */
    public static function countSentForInvoice(int $invoiceId): int
    {
        return static::query()->forInvoice($invoiceId)->sent()->count();
    }

    /**
     * Number of reminders still pending for a specific invoice.
     */
    public static function countPendingForInvoice(int $invoiceId): int
    {
        return static::query()->forInvoice($invoiceId)->pending()->count();
    }

    /**
     * Did the given invoice already schedule a reminder at this interval?
     */
    public static function existsFor(int $invoiceId, int $daysBeforeDue): bool
    {
        return static::query()
            ->where('invoice_id', $invoiceId)
            ->where('days_before_due', $daysBeforeDue)
            ->exists();
    }

    /**
     * Immediately cancel all pending reminders for an invoice.
     * Called when an invoice is paid, cancelled, or deleted.
     *
     * @return int  Number of rows updated.
     */
    public static function cancelForInvoice(int $invoiceId, string $reason = 'Invoice no longer needs reminders'): int
    {
        return static::query()
            ->forInvoice($invoiceId)
            ->pending()
            ->update([
                'status'         => self::STATUS_SKIPPED,
                'failure_reason' => $reason,
                'updated_at'     => now(),
            ]);
    }
}