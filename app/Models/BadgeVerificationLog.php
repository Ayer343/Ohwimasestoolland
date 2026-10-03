<?php
namespace App\Models;

class BadgeVerificationLog extends Model
{
    protected $fillable = [
        'worker_badge_id',
        'security_post_id',
        'verified_by',
        'verification_method',
        'ip_address',
        'user_agent',
        'location_data',
        'status',
        'notes'
    ];

    protected $casts = [
        'location_data' => 'array',
    ];

    public function badge()
    {
        return $this->belongsTo(WorkerBadge::class);
    }

    public function post()
    {
        return $this->belongsTo(SecurityPost::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}