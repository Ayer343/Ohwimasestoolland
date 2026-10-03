<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandlordPhone extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'landlord_phones';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'landlord_id',
        'phone_number',
        'is_primary',
        'type',
        'verified_at',
        'verified_by',
        'verification_code',
        'verification_sent_at',
        'notes',
        'status',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'verification_sent_at' => 'datetime',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'verification_code',
    ];

    /**
     * Get the landlord that owns this phone.
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Scope a query to only include primary phones.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to only include verified phones.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope a query to only include active phones.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if phone is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Check if phone is primary.
     */
    public function isPrimary(): bool
    {
        return (bool) $this->is_primary;
    }

    /**
     * Mark phone as verified.
     */
    public function markAsVerified(?int $verifiedBy = null): bool
    {
        return $this->update([
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'status' => 'active',
        ]);
    }

    /**
     * Send verification code.
     */
    public function sendVerificationCode(): string
    {
        $code = sprintf('%06d', random_int(1, 999999));
        
        $this->update([
            'verification_code' => $code,
            'verification_sent_at' => now(),
            'status' => 'pending_verification',
        ]);

        return $code;
    }

    /**
     * Verify phone with code.
     */
    public function verifyWithCode(string $code): bool
    {
        if ($this->verification_code !== $code) {
            return false;
        }

        // Check if code is expired (15 minutes)
        if ($this->verification_sent_at && $this->verification_sent_at->addMinutes(15)->isPast()) {
            return false;
        }

        return $this->markAsVerified();
    }

    /**
     * Get formatted phone number for display.
     */
    public function getFormattedPhoneAttribute(): string
    {
        $phone = $this->phone_number;
        
        // Format for Ghana numbers (+233XXXXXXXXX)
        if (preg_match('/^\+233(\d{9})$/', $phone, $matches)) {
            return '0' . $matches[1];
        }
        
        return $phone;
    }

    /**
     * Get phone number in international format.
     */
    public function getInternationalFormatAttribute(): string
    {
        $phone = $this->phone_number;
        
        // Convert local format to international
        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            return '+233' . $matches[1];
        }
        
        // Ensure it has + prefix
        if (!str_starts_with($phone, '+')) {
            return '+' . $phone;
        }
        
        return $phone;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($phone) {
            // Standardize phone number before saving
            if (!empty($phone->phone_number)) {
                $tempUser = new User();
                $phone->phone_number = $tempUser->standardizePhoneNumber($phone->phone_number);
            }

            // Set default status if not set
            if (empty($phone->status)) {
                $phone->status = 'active';
            }

            // If this is primary, ensure other phones for this landlord are not primary
            if ($phone->is_primary && $phone->landlord_id) {
                static::where('landlord_id', $phone->landlord_id)
                    ->where('id', '!=', $phone->id)
                    ->update(['is_primary' => false]);
            }
        });

        static::updating(function ($phone) {
            // Standardize phone number if being updated
            if ($phone->isDirty('phone_number') && !empty($phone->phone_number)) {
                $tempUser = new User();
                $phone->phone_number = $tempUser->standardizePhoneNumber($phone->phone_number);
            }

            // If this is being set as primary, ensure other phones for this landlord are not primary
            if ($phone->isDirty('is_primary') && $phone->is_primary && $phone->landlord_id) {
                static::where('landlord_id', $phone->landlord_id)
                    ->where('id', '!=', $phone->id)
                    ->update(['is_primary' => false]);
            }
        });
    }
}