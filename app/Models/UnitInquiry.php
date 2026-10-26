<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class UnitInquiry extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_NEW     = 'new';
    public const STATUS_READ    = 'read';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_CLOSED  = 'closed';
    public const STATUS_SPAM    = 'spam';

    protected $fillable = [
        'uuid', 'property_unit_id', 'property_id', 'landlord_id', 'user_id',
        'name', 'phone', 'email', 'message',
        'channels_requested', 'channels_sent', 'channels_failed',
        'ip_address', 'user_agent', 'status',
        'landlord_viewed_at', 'replied_at',
    ];

    protected $casts = [
        'channels_requested'   => 'array',
        'channels_sent'        => 'array',
        'channels_failed'      => 'array',
        'landlord_viewed_at'   => 'datetime',
        'replied_at'           => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $inquiry) {
            if (empty($inquiry->uuid)) {
                $inquiry->uuid = (string) Str::uuid();
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->user?->name ?? $this->name;
    }

    public function getDisplayPhoneAttribute(): string
    {
        return $this->user?->phone ?? $this->phone;
    }
}