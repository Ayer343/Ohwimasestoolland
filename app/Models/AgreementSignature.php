<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgreementSignature extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'agreement_signatures';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'agreement_id',
        'user_id',
        'signature_type',
        'signature_data',
        'signature_format',
        'signature_name',
        'signature_date',
        'signature_path',
        'ip_address',
        'user_agent',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'signature_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the agreement that owns the signature.
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(AdminBillingRecord::class, 'agreement_id');
    }

    /**
     * Get the user who signed.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope a query to only include developer signatures.
     */
    public function scopeDeveloperSignatures($query)
    {
        return $query->where('signature_type', 'developer');
    }

    /**
     * Scope a query to only include super admin signatures.
     */
    public function scopeSuperAdminSignatures($query)
    {
        return $query->where('signature_type', 'super_admin');
    }

    /**
     * Scope a query to only include verified signatures.
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    /**
     * Check if signature is verified.
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Get the signature type in a readable format.
     */
    public function getSignatureTypeTextAttribute(): string
    {
        return match($this->signature_type) {
            'developer' => 'Developer',
            'super_admin' => 'Super Admin',
            default => ucfirst($this->signature_type),
        };
    }

    /**
     * Get the signature format in a readable format.
     */
    public function getSignatureFormatTextAttribute(): string
    {
        return match($this->signature_format) {
            'typed' => 'Typed Signature',
            'draw' => 'Drawn Signature',
            'upload' => 'Uploaded Image',
            default => ucfirst($this->signature_format),
        };
    }

    /**
     * Get the full URL to the signature image.
     */
    public function getSignatureUrlAttribute(): ?string
    {
        if (!$this->signature_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::url($this->signature_path);
    }

    /**
     * Check if this signature is for a developer.
     */
    public function isDeveloperSignature(): bool
    {
        return $this->signature_type === 'developer';
    }

    /**
     * Check if this signature is for a super admin.
     */
    public function isSuperAdminSignature(): bool
    {
        return $this->signature_type === 'super_admin';
    }

    /**
     * Get the signature initials for display.
     */
    public function getInitialsAttribute(): string
    {
        if (!$this->signature_name) {
            return '??';
        }

        $nameParts = explode(' ', $this->signature_name);
        $initials = '';
        
        foreach ($nameParts as $part) {
            if (trim($part) !== '') {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
        
        return substr($initials, 0, 2);
    }
}