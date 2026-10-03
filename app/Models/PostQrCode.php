<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // CRITICAL FIX: Using Facade, not Model
use Carbon\Carbon;

class PostQrCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'post_qr_codes';

    protected $fillable = [
        'post_id',
        'name',
        'description',
        'code',
        'code_type',
        'image_path',
        'expires_at',
        'max_uses',
        'uses_count',
        'is_active',
        'created_by',
        'metadata'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
        'deleted_at' => 'datetime'
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'is_permanent',
        'status',
        'status_text',
        'status_color',
        'expires_in',
        'usage_percentage',
        'remaining_uses',
        'is_expired',
        'is_valid',
        'age_in_days',
        'formatted_expiration',
        'post_name',
        'post_code',
        'post_status',
        'full_details'
    ];

    /**
     * ==================== RELATIONSHIPS ====================
     */

    /**
     * Get the post that owns this QR code
     */
    public function post()
    {
        return $this->belongsTo(SecurityPost::class, 'post_id')->withTrashed();
    }

    /**
     * Get the user who created this QR code
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the verification logs for this QR code
     */
    public function verificationLogs()
    {
        return $this->hasMany(VerificationLog::class, 'qr_code_id');
    }

    /**
     * Get today's verification logs
     */
    public function todayVerificationLogs()
    {
        return $this->verificationLogs()->whereDate('created_at', today());
    }

    /**
     * Get recent verification logs
     */
    public function recentVerificationLogs($limit = 10)
    {
        return $this->verificationLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * ==================== SCOPES ====================
     */

    /**
     * Scope a query to only include active QR codes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive QR codes
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include permanent QR codes
     */
    public function scopePermanent($query)
    {
        return $query->where('code_type', 'static')
                     ->whereNull('expires_at');
    }

    /**
     * Scope a query to only include time-based QR codes
     */
    public function scopeTimeBased($query)
    {
        return $query->where('code_type', 'time_based');
    }

    /**
     * Scope a query to only include one-time QR codes
     */
    public function scopeOneTime($query)
    {
        return $query->where('code_type', 'one_time');
    }

    /**
     * Scope a query to only include valid QR codes
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(function($q) {
                $q->whereNull('max_uses')
                  ->orWhereColumn('uses_count', '<', 'max_uses');
            });
    }

    /**
     * Scope a query to only include expired QR codes
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
                     ->whereNotNull('expires_at');
    }

    /**
     * Scope a query to only include expiring soon QR codes
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include QR codes that have reached max uses
     */
    public function scopeMaxUsesReached($query)
    {
        return $query->whereNotNull('max_uses')
                     ->whereColumn('uses_count', '>=', 'max_uses');
    }

    /**
     * Scope a query to only include QR codes nearing max uses
     */
    public function scopeNearingMaxUses($query, int $percentage = 80)
    {
        return $query->whereNotNull('max_uses')
                     ->whereRaw('(uses_count / max_uses * 100) >= ?', [$percentage])
                     ->whereColumn('uses_count', '<', 'max_uses');
    }

    /**
     * Scope a query to only include QR codes by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('code_type', $type);
    }

    /**
     * Scope a query to search QR codes
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to only include QR codes for active posts
     */
    public function scopeForActivePosts($query)
    {
        return $query->whereHas('post', function($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * ==================== ACCESSORS ====================
     */

    /**
     * Check if QR code is permanent
     */
    public function getIsPermanentAttribute(): bool
    {
        return $this->code_type === 'static' && $this->expires_at === null;
    }

    /**
     * Check if QR code is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && Carbon::parse($this->expires_at)->isPast();
    }

    /**
     * Check if QR code is valid
     */
    public function getIsValidAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->is_expired) {
            return false;
        }

        if ($this->max_uses && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Get human-readable status
     */
    public function getStatusAttribute(): string
    {
        if ($this->trashed()) {
            return 'deleted';
        }

        if (!$this->is_active) {
            return 'inactive';
        }
        
        if ($this->is_expired) {
            return 'expired';
        }
        
        if ($this->max_uses && $this->uses_count >= $this->max_uses) {
            return 'max_uses_reached';
        }
        
        if ($this->is_permanent) {
            return 'permanent';
        }
        
        return 'active';
    }

    /**
     * Get status text (for display)
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'permanent' => 'Permanent',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'expired' => 'Expired',
            'max_uses_reached' => 'Max Uses Reached',
            'deleted' => 'Deleted',
            default => 'Unknown'
        };
    }

    /**
     * Get status color (for views)
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'permanent' => 'success',
            'active' => 'primary',
            'inactive' => 'secondary',
            'expired' => 'danger',
            'max_uses_reached' => 'warning',
            'deleted' => 'dark',
            default => 'light'
        };
    }

    /**
     * Get status badge HTML
     */
    public function getStatusBadgeAttribute(): string
    {
        $color = $this->status_color;
        return '<span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--' . $color . '-rgb), 0.1); color: var(--' . $color . ');">' . $this->status_text . '</span>';
    }

    /**
     * Get formatted expiration
     */
    public function getFormattedExpirationAttribute(): string
    {
        if ($this->is_permanent) {
            return 'Never Expires';
        }
        
        if (!$this->expires_at) {
            return 'No Expiration';
        }
        
        return $this->expires_at->format('Y-m-d H:i:s');
    }

    /**
     * Get time until expiration
     */
    public function getExpiresInAttribute(): ?string
    {
        if ($this->is_permanent || !$this->expires_at) {
            return null;
        }
        
        if ($this->is_expired) {
            return 'Expired';
        }
        
        return $this->expires_at->diffForHumans();
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentageAttribute(): ?float
    {
        if (!$this->max_uses) {
            return null;
        }
        
        return round(($this->uses_count / $this->max_uses) * 100, 1);
    }

    /**
     * Get remaining uses
     */
    public function getRemainingUsesAttribute(): ?int
    {
        if (!$this->max_uses) {
            return null; // Unlimited
        }
        
        return max(0, $this->max_uses - $this->uses_count);
    }

    /**
     * FIXED: Get the QR code's age in days with null safety
     */
    public function getAgeInDaysAttribute(): int
    {
        if ($this->created_at === null) {
            return 0;
        }
        
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get post name
     */
    public function getPostNameAttribute(): string
    {
        return $this->post ? $this->post->name : 'Unknown Post';
    }

    /**
     * Get post code
     */
    public function getPostCodeAttribute(): string
    {
        return $this->post ? $this->post->code : 'N/A';
    }

    /**
     * Get post status
     */
    public function getPostStatusAttribute(): string
    {
        if (!$this->post) {
            return 'unknown';
        }
        
        if ($this->post->trashed()) {
            return 'deleted';
        }
        
        return $this->post->is_active ? 'active' : 'inactive';
    }

    /**
     * FIXED: Get full details array with null safety
     */
    public function getFullDetailsAttribute(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->code_type,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'status_color' => $this->status_color,
            'is_permanent' => $this->is_permanent,
            'is_valid' => $this->is_valid,
            'post' => [
                'id' => $this->post_id,
                'name' => $this->post_name,
                'code' => $this->post_code,
                'status' => $this->post_status
            ],
            'usage' => [
                'current' => $this->uses_count,
                'max' => $this->max_uses,
                'percentage' => $this->usage_percentage,
                'remaining' => $this->remaining_uses
            ],
            'expiration' => [
                'date' => $this->formatted_expiration,
                'expires_in' => $this->expires_in,
                'is_expired' => $this->is_expired
            ],
            'created_at' => $this->created_at?->toDateTimeString() ?? 'Unknown',
            'created_by' => $this->creator ? $this->creator->name : 'System',
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null
        ];
    }

    /**
     * ==================== VERIFICATION METHODS ====================
     */

    /**
     * Check if QR code is valid
     */
    public function isValid(): bool
    {
        return $this->is_valid;
    }

    /**
     * Check if QR code can be used
     */
    public function canBeUsed(): bool
    {
        if (!$this->is_valid) {
            return false;
        }

        if (!$this->post) {
            return false;
        }

        if ($this->post->trashed()) {
            return false;
        }

        if (!$this->post->is_active) {
            return false;
        }

        return true;
    }

    /**
     * Check if QR code can be used by a specific user
     */
    public function canBeUsedBy($userId): array
    {
        if (!$this->canBeUsed()) {
            return [
                'success' => false,
                'message' => 'QR code is not valid for use.'
            ];
        }

        // Check if user already used this one_time code
        if ($this->code_type === 'one_time' && $this->uses_count >= 1) {
            return [
                'success' => false,
                'message' => 'This one-time QR code has already been used.'
            ];
        }

        // Check if user already checked in today
        $alreadyCheckedIn = VerificationLog::where('qr_code_id', $this->id)
            ->where('user_id', $userId)
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyCheckedIn) {
            return [
                'success' => false,
                'message' => 'User has already checked in today with this QR code.'
            ];
        }

        return [
            'success' => true,
            'message' => 'QR code can be used.'
        ];
    }

    /**
     * Increment usage count
     */
    public function incrementUsage($userId = null, $metadata = []): void
    {
        $this->increment('uses_count');
        
        // Update metadata with usage info
        $metadataArray = $this->metadata ?? [];
        $metadataArray['last_used'] = now()->toDateTimeString();
        $metadataArray['last_used_by'] = $userId;
        $metadataArray['last_used_ip'] = $metadata['ip'] ?? request()->ip();
        $metadataArray['last_used_user_agent'] = $metadata['user_agent'] ?? request()->userAgent();
        
        // Keep history of recent uses (last 20)
        $metadataArray['recent_uses'] = $metadataArray['recent_uses'] ?? [];
        array_unshift($metadataArray['recent_uses'], [
            'timestamp' => now()->toDateTimeString(),
            'user_id' => $userId,
            'ip' => $metadata['ip'] ?? request()->ip(),
            'location' => $metadata['location'] ?? null
        ]);
        $metadataArray['recent_uses'] = array_slice($metadataArray['recent_uses'], 0, 20);
        
        $this->update(['metadata' => $metadataArray]);

        // Deactivate one_time codes after use
        if ($this->code_type === 'one_time' && $this->uses_count >= 1) {
            $this->update(['is_active' => false]);
        }
    }

    /**
     * Get usage statistics
     */
    public function getUsageStatistics()
    {
        $logs = $this->verificationLogs();
        
        return [
            'total_uses' => $logs->count(),
            'unique_users' => $logs->distinct('user_id')->count('user_id'),
            'last_24h' => $logs->where('created_at', '>=', now()->subDay())->count(),
            'last_7d' => $logs->where('created_at', '>=', now()->subDays(7))->count(),
            'last_30d' => $logs->where('created_at', '>=', now()->subDays(30))->count(),
            'average_daily' => $this->getAverageDailyUsage(),
            'peak_hour' => $this->getPeakUsageHour(),
            'recent' => $this->recentVerificationLogs(10)
        ];
    }

    /**
     * FIXED: Get average daily usage with null safety
     */
    private function getAverageDailyUsage(): float
    {
        if ($this->created_at === null) {
            return (float) $this->uses_count;
        }
        
        $daysSinceCreation = max(1, $this->created_at->diffInDays(now()));
        return round($this->uses_count / $daysSinceCreation, 1);
    }

    /**
     * Get peak usage hour
     */
    private function getPeakUsageHour(): ?int
    {
        try {
            $peak = DB::table('verification_logs')
                ->where('qr_code_id', $this->id)
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
                ->groupBy('hour')
                ->orderBy('count', 'desc')
                ->first();
            
            return $peak ? (int)$peak->hour : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * ==================== QR CODE DATA METHODS ====================
     */

    /**
     * FIXED: Get QR code data for verification with null safety
     */
    public function getVerificationData(): array
    {
        return [
            'type' => 'security_checkin',
            'post_id' => $this->post_id,
            'code' => $this->code,
            'name' => $this->name,
            'valid_from' => $this->created_at?->timestamp ?? now()->timestamp,
            'expires_at' => $this->expires_at?->timestamp,
            'one_time' => $this->code_type === 'one_time',
            'permanent' => $this->is_permanent,
            'version' => '1.0'
        ];
    }

    /**
     * FIXED: Get compact data for QR code with null safety
     */
    public function getCompactData(): array
    {
        $data = [
            't' => 'sc',
            'p' => $this->post_id,
            'c' => $this->code,
            'n' => substr($this->name, 0, 20),
            'vf' => $this->created_at?->timestamp ?? now()->timestamp,
            'v' => '1'
        ];

        if ($this->code_type === 'one_time') {
            $data['ot'] = 1;
        }

        if ($this->code_type === 'time_based' && $this->expires_at) {
            $data['ve'] = $this->expires_at->timestamp;
        }

        if ($this->is_permanent) {
            $data['perm'] = 1;
        }

        return $data;
    }

    /**
     * Get JSON data for QR code
     */
    public function getJsonData(): string
    {
        return json_encode($this->getCompactData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get data size in bits
     */
    public function getDataSizeInBits(): int
    {
        return strlen($this->getJsonData()) * 8;
    }

    /**
     * ==================== UTILITY METHODS ====================
     */

    /**
     * Check if QR code is nearing expiration
     */
    public function isNearingExpiration(int $daysThreshold = 7): bool
    {
        if ($this->is_permanent || !$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->isFuture() && 
               $this->expires_at->diffInDays(now()) <= $daysThreshold;
    }

    /**
     * Check if QR code is nearing max uses
     */
    public function isNearingMaxUses(int $percentageThreshold = 80): bool
    {
        if (!$this->max_uses) {
            return false;
        }
        
        $usagePercentage = ($this->uses_count / $this->max_uses) * 100;
        return $usagePercentage >= $percentageThreshold && $this->uses_count < $this->max_uses;
    }

    /**
     * Get warning messages
     */
    public function getWarnings(): array
    {
        $warnings = [];

        if ($this->is_nearing_expiration) {
            $warnings[] = "QR code expires in {$this->expires_in}";
        }

        if ($this->is_nearing_max_uses) {
            $warnings[] = "QR code has reached {$this->usage_percentage}% of max uses";
        }

        if ($this->post && $this->post->trashed()) {
            $warnings[] = "Associated post is deleted";
        }

        if ($this->post && !$this->post->is_active) {
            $warnings[] = "Associated post is inactive";
        }

        return $warnings;
    }

    /**
     * Get image URL
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /**
     * Get download URL
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('admin.security-posts.qr-codes.download', [
            'securityPost' => $this->post_id,
            'qrCode' => $this->id
        ]);
    }

    /**
     * Clear cache for this QR code
     */
    public function clearCache()
    {
        Cache::forget("qr_code_{$this->code}");
        Cache::forget("qr_code_{$this->id}");
        Cache::forget("qr_code_{$this->id}_stats");
    }

    /**
     * ==================== STATISTICS METHODS ====================
     */

    /**
     * Get statistics for this QR code
     */
    public static function getGlobalStatistics()
    {
        $query = self::query();
        
        return [
            'total' => $query->count(),
            'active' => $query->clone()->active()->count(),
            'inactive' => $query->clone()->inactive()->count(),
            'trashed' => self::onlyTrashed()->count(),
            'by_type' => [
                'static' => $query->clone()->where('code_type', 'static')->count(),
                'one_time' => $query->clone()->where('code_type', 'one_time')->count(),
                'time_based' => $query->clone()->where('code_type', 'time_based')->count(),
            ],
            'expired' => $query->clone()->expired()->count(),
            'expiring_soon' => $query->clone()->expiringSoon()->count(),
            'max_uses_reached' => $query->clone()->maxUsesReached()->count(),
            'total_uses' => VerificationLog::count(),
            'unique_users' => VerificationLog::distinct('user_id')->count('user_id'),
        ];
    }

    /**
     * Get statistics for a specific post
     */
    public static function getPostStatistics($postId)
    {
        $query = self::where('post_id', $postId);
        
        return [
            'total' => $query->count(),
            'active' => $query->clone()->active()->count(),
            'inactive' => $query->clone()->inactive()->count(),
            'trashed' => self::onlyTrashed()->where('post_id', $postId)->count(),
            'by_type' => [
                'static' => $query->clone()->where('code_type', 'static')->count(),
                'one_time' => $query->clone()->where('code_type', 'one_time')->count(),
                'time_based' => $query->clone()->where('code_type', 'time_based')->count(),
            ],
            'expired' => $query->clone()->expired()->count(),
            'expiring_soon' => $query->clone()->expiringSoon()->count(),
            'max_uses_reached' => $query->clone()->maxUsesReached()->count(),
            'total_uses' => VerificationLog::whereIn('qr_code_id', $query->pluck('id'))->count(),
        ];
    }

    /**
     * Get trash statistics
     */
    public static function getTrashStatistics($postId = null)
    {
        $query = self::onlyTrashed();
        
        if ($postId) {
            $query->where('post_id', $postId);
        }
        
        return [
            'total' => $query->count(),
            'recently_deleted' => $query->clone()
                ->where('deleted_at', '>=', now()->subDays(7))
                ->count(),
            'by_type' => $query->clone()
                ->select('code_type', DB::raw('count(*) as count'))
                ->groupBy('code_type')
                ->pluck('count', 'code_type')
                ->toArray(),
            'by_post' => $query->clone()
                ->join('security_posts', 'post_qr_codes.post_id', '=', 'security_posts.id')
                ->select('security_posts.name as post_name', DB::raw('count(*) as count'))
                ->groupBy('security_posts.name')
                ->pluck('count', 'post_name')
                ->toArray(),
        ];
    }

    /**
     * ==================== BOOT METHODS ====================
     */

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($qrCode) {
            if (empty($qrCode->metadata)) {
                $qrCode->metadata = [
                    'created_at' => now()->toDateTimeString(),
                    'initial_uses' => 0,
                    'version' => '1.0'
                ];
            }
        });

        static::created(function ($qrCode) {
            Log::info('QR code created', [
                'qr_code_id' => $qrCode->id,
                'post_id' => $qrCode->post_id,
                'code_type' => $qrCode->code_type,
                'created_by' => $qrCode->created_by
            ]);
            
            $qrCode->clearCache();
        });

        static::updating(function ($qrCode) {
            // Log significant changes in metadata
            if ($qrCode->isDirty('is_active')) {
                $metadata = $qrCode->metadata ?? [];
                $metadata['status_changes'] = $metadata['status_changes'] ?? [];
                $metadata['status_changes'][] = [
                    'from' => $qrCode->getOriginal('is_active'),
                    'to' => $qrCode->is_active,
                    'changed_at' => now()->toDateTimeString(),
                    'changed_by' => auth()->id()
                ];
                $qrCode->metadata = $metadata;
            }

            if ($qrCode->isDirty('code')) {
                $metadata = $qrCode->metadata ?? [];
                $metadata['code_changes'] = $metadata['code_changes'] ?? [];
                $metadata['code_changes'][] = [
                    'from' => $qrCode->getOriginal('code'),
                    'to' => $qrCode->code,
                    'changed_at' => now()->toDateTimeString(),
                    'changed_by' => auth()->id()
                ];
                $qrCode->metadata = $metadata;
            }
        });

        static::updated(function ($qrCode) {
            Log::info('QR code updated', [
                'qr_code_id' => $qrCode->id,
                'changes' => $qrCode->getChanges()
            ]);
            
            $qrCode->clearCache();
        });

        static::deleted(function ($qrCode) {
            Log::info('QR code soft deleted', [
                'qr_code_id' => $qrCode->id,
                'post_id' => $qrCode->post_id,
                'deleted_by' => auth()->id()
            ]);
            
            $qrCode->clearCache();
        });

        static::restored(function ($qrCode) {
            Log::info('QR code restored', [
                'qr_code_id' => $qrCode->id,
                'post_id' => $qrCode->post_id,
                'restored_by' => auth()->id()
            ]);
            
            $qrCode->clearCache();
        });

        static::forceDeleted(function ($qrCode) {
            Log::warning('QR code permanently deleted', [
                'qr_code_id' => $qrCode->id,
                'post_id' => $qrCode->post_id,
                'code' => $qrCode->code,
                'deleted_by' => auth()->id()
            ]);
        });
    }
}