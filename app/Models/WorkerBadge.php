<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WorkerBadge extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'construction_worker_id',
        'construction_contract_id',
        'badge_number',
        'qr_code',
        'card_number',
        'email_sent_to',
        'email_sent_at',
        'last_verified_at',
        'verification_count',
        'is_active',
        'valid_from',
        'valid_until',
        'security_features',
        'photo_path',
        'notes'
    ];

    protected $casts = [
        'security_features' => 'array',
        'email_sent_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function worker()
    {
        return $this->belongsTo(ConstructionWorker::class, 'construction_worker_id');
    }

    public function contract()
    {
        return $this->belongsTo(ConstructionContract::class, 'construction_contract_id');
    }

    public function verificationLogs()
    {
        return $this->hasMany(BadgeVerificationLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where('valid_until', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('valid_until', '<=', now());
    }

    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('valid_until', '<=', now()->addDays($days))
                     ->where('valid_until', '>', now());
    }

    // Accessors
    public function getIsExpiredAttribute()
    {
        return $this->valid_until && $this->valid_until <= now();
    }

    public function getIsExpiringSoonAttribute()
    {
        return $this->valid_until && 
               $this->valid_until > now() && 
               $this->valid_until <= now()->addDays(7);
    }

    public function getDaysUntilExpiryAttribute()
    {
        if (!$this->valid_until) return null;
        return now()->diffInDays($this->valid_until, false);
    }

    public function getStatusAttribute()
    {
        if (!$this->is_active) return 'inactive';
        if ($this->is_expired) return 'expired';
        return 'active';
    }

    // Helper Methods
    public function generateBadgeNumber()
    {
        $year = date('Y');
        $random = Str::upper(Str::random(5));
        $this->badge_number = "WB-{$year}-{$random}";
        return $this->badge_number;
    }

    public function generateQRCode()
    {
        // Format: BADGE-{badge_number}-{encrypted_id}
        $encryptedId = base64_encode($this->id . '|' . config('app.key'));
        $this->qr_code = "BADE-{$this->badge_number}-{$encryptedId}";
        return $this->qr_code;
    }

    public function getVerificationUrl()
    {
        return route('public.verify-badge', ['code' => $this->qr_code]);
    }

    public function getCardData()
    {
        return [
            'badge_number' => $this->badge_number,
            'worker_name' => $this->worker->full_name,
            'worker_photo' => $this->photo_path,
            'trade' => $this->worker->trade,
            'contract_number' => $this->contract->contract_number,
            'valid_from' => $this->valid_from->format('d M Y'),
            'valid_until' => $this->valid_until->format('d M Y'),
            'company_name' => $this->contract->contractor->company_name ?? 'Construction Company',
            'qr_code' => $this->qr_code,
        ];
    }

    public function logVerification($status, $postId = null, $userId = null, $metadata = [])
    {
        return $this->verificationLogs()->create([
            'security_post_id' => $postId,
            'verified_by' => $userId,
            'verification_method' => $metadata['method'] ?? 'qr_scan',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'location_data' => $metadata['location'] ?? null,
            'status' => $status,
            'notes' => $metadata['notes'] ?? null,
        ]);
    }

    public function verify($postId = null, $userId = null, $metadata = [])
    {
        if (!$this->is_active) {
            return ['success' => false, 'message' => 'Badge is inactive'];
        }

        if ($this->is_expired) {
            return ['success' => false, 'message' => 'Badge has expired'];
        }

        // Check if worker is still active on the contract
        if ($this->worker->status !== 'active') {
            return ['success' => false, 'message' => 'Worker is no longer active'];
        }

        // Log verification
        $this->logVerification('approved', $postId, $userId, $metadata);
        
        // Update counts
        $this->increment('verification_count');
        $this->update(['last_verified_at' => now()]);

        return [
            'success' => true,
            'message' => 'Badge verified successfully',
            'worker' => $this->worker,
            'contract' => $this->contract,
        ];
    }

    public static function generateForWorker(ConstructionWorker $worker)
    {
        $badge = self::create([
            'construction_worker_id' => $worker->id,
            'construction_contract_id' => $worker->contract_id,
            'badge_number' => (new self)->generateBadgeNumber(),
            'email_sent_to' => $worker->email,
            'valid_from' => $worker->start_date ?? now(),
            'valid_until' => $worker->end_date ?? now()->addMonths(6),
            'is_active' => $worker->status === 'active',
            'security_features' => [
                'requires_photo_id' => true,
                'requires_signature' => false,
            ],
        ]);

        $badge->generateQRCode();
        $badge->save();

        return $badge;
    }
}