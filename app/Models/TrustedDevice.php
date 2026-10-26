<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    protected $table = 'trusted_devices';

    protected $fillable = [
        'user_id',
        'device_token_hash',
        'device_fingerprint',
        'user_agent',
        'ip_address',
        'trusted_at',
        'expires_at',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'device_token_hash',
        'device_fingerprint',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}