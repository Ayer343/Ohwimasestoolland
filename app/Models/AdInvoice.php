<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AdInvoice extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_REFUNDED  = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'invoice_number', 'ad_campaign_id', 'landlord_id',
        'amount', 'currency', 'status', 'gateway', 'gateway_reference',
        'gateway_payload', 'paid_at', 'due_at', 'refunded_at', 'notes',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'gateway_payload' => 'array',
        'paid_at'         => 'datetime',
        'due_at'          => 'datetime',
        'refunded_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = 'AD-' . strtoupper(Str::random(10));
            }
            if (empty($invoice->due_at)) {
                $invoice->due_at = now()->addDays(3);
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markPaid(string $gateway, ?string $reference = null, array $payload = []): void
    {
        $this->status            = self::STATUS_PAID;
        $this->gateway           = $gateway;
        $this->gateway_reference = $reference;
        $this->gateway_payload   = $payload;
        $this->paid_at           = now();
        $this->save();
    }
}