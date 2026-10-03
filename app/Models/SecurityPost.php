<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecurityPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'location',
        'digital_address',
        'latitude',
        'longitude',
        'equipment',
        'max_personnel',
        'is_active',
        'requires_checkin',
        'working_hours',
        'restrictions',
        // Smart check-in fields
        'checkin_radius',
        'checkin_grace_period',
        'checkout_grace_period',
        'qr_code_rotation_minutes',
        'require_photo_on_checkin',
        'require_selfie_on_checkin',
        'require_gps_verification',
        'require_nfc_verification',
        'allowed_verification_methods',
        'gps_tolerance_level',
        'offline_checkin_allowed',
        'offline_timeout_minutes',
        'supervisor_approval_required',
    ];

    protected $casts = [
        'equipment' => 'array',
        'working_hours' => 'array',
        'restrictions' => 'array',
        'allowed_verification_methods' => 'array',
        'is_active' => 'boolean',
        'requires_checkin' => 'boolean',
        'require_photo_on_checkin' => 'boolean',
        'require_selfie_on_checkin' => 'boolean',
        'require_gps_verification' => 'boolean',
        'require_nfc_verification' => 'boolean',
        'offline_checkin_allowed' => 'boolean',
        'supervisor_approval_required' => 'boolean',
        'max_personnel' => 'integer',
        'checkin_radius' => 'integer',
        'checkin_grace_period' => 'integer',
        'checkout_grace_period' => 'integer',
        'qr_code_rotation_minutes' => 'integer',
        'gps_tolerance_level' => 'integer',
        'offline_timeout_minutes' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $appends = [
        'staffing_status',
        'staffing_percentage',
        'type_label',
        'coordinates',
        'map_url',
        'verification_requirements',
        'qr_codes_count',
        'active_qr_codes_count',
        'qr_usage_stats'
    ];

    /**
     * ==================== RELATIONSHIPS ====================
     */
    
    public function schedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'security_post_id');
    }

    public function currentSchedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'security_post_id')
            ->whereDate('assignment_date', now()->toDateString())
            ->whereIn('status', ['scheduled', 'active']);
    }

    public function upcomingSchedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'security_post_id')
            ->whereDate('assignment_date', '>', now()->toDateString())
            ->whereDate('assignment_date', '<=', now()->addDays(7)->toDateString())
            ->orderBy('assignment_date');
    }

    public function completedSchedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'security_post_id')
            ->where('status', 'completed');
    }

    public function activeSchedule()
    {
        return $this->hasOne(SecuritySchedule::class, 'security_post_id')
            ->where('status', 'active')
            ->whereDate('assignment_date', now())
            ->with('securityUser');
    }

    /**
     * QR Code Relationships
     */
    public function qrCodes()
    {
        return $this->hasMany(PostQrCode::class, 'post_id');
    }

    public function activeQrCodes()
    {
        return $this->qrCodes()
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            });
    }

    public function staticQrCodes()
    {
        return $this->qrCodes()->where('code_type', 'static');
    }

    public function oneTimeQrCodes()
    {
        return $this->qrCodes()->where('code_type', 'one_time');
    }

    public function timeBasedQrCodes()
    {
        return $this->qrCodes()->where('code_type', 'time_based');
    }

    public function expiredQrCodes()
    {
        return $this->qrCodes()
            ->where('expires_at', '<', now())
            ->whereNotNull('expires_at');
    }

    /**
     * NFC Tag Relationships
     */
    public function nfcTags()
    {
        return $this->hasMany(PostNfcTag::class, 'post_id');
    }

    public function activeNfcTags()
    {
        return $this->nfcTags()->where('is_active', true);
    }

    /**
     * Verification Logs Relationship
     */
    public function verificationLogs()
    {
        return $this->hasManyThrough(
            VerificationLog::class,
            PostQrCode::class,
            'post_id',
            'qr_code_id',
            'id',
            'id'
        );
    }

    public function todayVerificationLogs()
    {
        return $this->verificationLogs()
            ->whereDate('created_at', today());
    }


    public function supervisorAssignments()
    {
        return $this->hasMany(SecuritySupervisorAssignment::class, 'security_post_id');
    }
    
    public function currentSupervisor()
    {
        return $this->hasOne(SecuritySupervisorAssignment::class, 'security_post_id')
                    ->active()
                    ->with('user')
                    ->latest();
    }
    
    public function hasSupervisor(): bool
    {
        return $this->currentSupervisor()->exists();
    }
    
    public function getSupervisorNameAttribute(): ?string
    {
        return $this->currentSupervisor?->user?->name;
    }

    /**
     * ==================== SCOPES ====================
     */
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeMainGate($query)
    {
        return $query->where('type', 'main_gate');
    }

    public function scopeInternalGate($query)
    {
        return $query->where('type', 'internal_gate');
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('location', 'like', "%{$search}%")
              ->orWhere('digital_address', 'like', "%{$search}%");
        });
    }

    public function scopeHasLocation($query)
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    public function scopeWithinRadius($query, $lat, $lng, $radius)
    {
        return $query->whereRaw("
            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?
        ", [$lat, $lng, $lat, $radius]);
    }

    public function scopeHasQrCodes($query)
    {
        return $query->has('qrCodes');
    }

    public function scopeHasActiveQrCodes($query)
    {
        return $query->whereHas('activeQrCodes');
    }

    public function scopeStaffingStatus($query, $status)
    {
        switch ($status) {
            case 'fully_staffed':
                return $query->whereHas('currentSchedules', null, '>=', DB::raw('max_personnel'));
            case 'understaffed':
                return $query->whereHas('currentSchedules', null, '<', DB::raw('max_personnel'))
                    ->whereHas('currentSchedules', null, '>', 0);
            case 'unstaffed':
                return $query->whereDoesntHave('currentSchedules');
            default:
                return $query;
        }
    }

    /**
     * ==================== VERIFICATION METHODS ====================
     */

    /**
     * Generate a new QR code for this post
     */
    public function generateQRCode($name = null, $type = 'static', $expiresAt = null, $maxUses = null)
    {
        $code = $this->generateUniqueQrCode();
        
        $qrCode = PostQrCode::create([
            'post_id' => $this->id,
            'name' => $name ?? $this->name . ' QR Code',
            'code' => $code,
            'code_type' => $type,
            'expires_at' => $expiresAt,
            'max_uses' => $type === 'one_time' ? 1 : $maxUses,
            'uses_count' => 0,
            'is_active' => true,
            'created_by' => auth()->id(),
            'metadata' => json_encode([
                'generated_at' => now()->toDateTimeString(),
                'generated_by' => auth()->user()->name ?? 'system',
                'post_name' => $this->name,
                'post_code' => $this->code,
            ]),
        ]);
        
        return $qrCode;
    }

    /**
     * Generate multiple QR codes for redundancy
     */
    public function generateQRCodeBatch($count = 3, $type = 'static', $validMinutes = null)
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $expiresAt = $validMinutes ? now()->addMinutes($validMinutes) : null;
            $codes[] = $this->generateQRCode(
                $this->name . ' QR ' . ($i + 1),
                $type,
                $expiresAt
            );
        }
        return $codes;
    }

    /**
     * Verify a QR code
     */
    public function verifyQRCode($code, $userId = null)
    {
        $qrCode = PostQrCode::where('post_id', $this->id)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$qrCode) {
            return [
                'success' => false,
                'message' => 'Invalid QR code.'
            ];
        }

        // Check expiration
        if ($qrCode->expires_at && Carbon::parse($qrCode->expires_at)->isPast()) {
            return [
                'success' => false,
                'message' => 'QR code has expired.'
            ];
        }

        // Check max uses
        if ($qrCode->max_uses && $qrCode->uses_count >= $qrCode->max_uses) {
            return [
                'success' => false,
                'message' => 'QR code has reached maximum usage limit.'
            ];
        }

        // Check if user already used one_time code
        if ($qrCode->code_type === 'one_time' && $qrCode->uses_count >= 1) {
            return [
                'success' => false,
                'message' => 'QR code has already been used.'
            ];
        }

        // Check if user already checked in today
        if ($userId) {
            $alreadyCheckedIn = VerificationLog::where('qr_code_id', $qrCode->id)
                ->where('user_id', $userId)
                ->whereDate('created_at', today())
                ->exists();

            if ($alreadyCheckedIn) {
                return [
                    'success' => false,
                    'message' => 'User already checked in today.'
                ];
            }
        }

        return [
            'success' => true,
            'qr_code' => $qrCode,
            'message' => 'QR code verified successfully.'
        ];
    }

    /**
     * Generate unique QR code
     */
    private function generateUniqueQrCode()
    {
        $prefix = 'QR';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(8));
        $code = $prefix . '-' . $timestamp . '-' . $random;

        while (PostQrCode::where('code', $code)->exists()) {
            $random = strtoupper(Str::random(8));
            $code = $prefix . '-' . $timestamp . '-' . $random;
        }

        return $code;
    }

    /**
     * Get QR code statistics
     */
    public function getQrCodeStats()
    {
        $stats = [
            'total' => $this->qrCodes()->count(),
            'active' => $this->activeQrCodes()->count(),
            'static' => $this->staticQrCodes()->count(),
            'one_time' => $this->oneTimeQrCodes()->count(),
            'time_based' => $this->timeBasedQrCodes()->count(),
            'expired' => $this->expiredQrCodes()->count(),
            'trashed' => $this->qrCodes()->onlyTrashed()->count(),
        ];

        // Get total usage
        try {
            $stats['total_uses'] = VerificationLog::whereIn('qr_code_id', 
                $this->qrCodes()->pluck('id')
            )->count();

            $stats['unique_users'] = VerificationLog::whereIn('qr_code_id', 
                $this->qrCodes()->pluck('id')
            )->distinct('user_id')->count('user_id');

            $stats['today_uses'] = VerificationLog::whereIn('qr_code_id', 
                $this->qrCodes()->pluck('id')
            )->whereDate('created_at', today())->count();

        } catch (\Exception $e) {
            $stats['total_uses'] = 0;
            $stats['unique_users'] = 0;
            $stats['today_uses'] = 0;
        }

        return $stats;
    }

    /**
     * Register an NFC tag for this post
     */
    public function registerNFCTag($tagId, $name = null, $metadata = [])
    {
        return PostNfcTag::create([
            'post_id' => $this->id,
            'tag_id' => $tagId,
            'name' => $name,
            'metadata' => $metadata,
            'is_active' => true,
            'registered_at' => now()
        ]);
    }

    /**
     * Verify an NFC tag
     */
    public function verifyNFCTag($tagId)
    {
        $tag = PostNfcTag::where('post_id', $this->id)
            ->where('tag_id', $tagId)
            ->where('is_active', true)
            ->first();
        
        if ($tag) {
            $tag->update([
                'last_used_at' => now(),
                'last_used_by' => auth()->id()
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Calculate distance from post to given coordinates
     */
    public function distanceTo($lat, $lng)
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }
        
        $earthRadius = 6371000; // meters
        
        $latFrom = deg2rad($lat);
        $lonFrom = deg2rad($lng);
        $latTo = deg2rad($this->latitude);
        $lonTo = deg2rad($this->longitude);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        
        return $angle * $earthRadius;
    }

    /**
     * Check if coordinates are within check-in radius
     */
    public function isWithinRadius($lat, $lng, $accuracy = 10)
    {
        $distance = $this->distanceTo($lat, $lng);
        
        if ($distance === null) {
            return true; // No location set, skip check
        }
        
        $effectiveRadius = $this->checkin_radius + ($accuracy * 2);
        
        return $distance <= $effectiveRadius;
    }

    /**
     * Get verification requirements for this post
     */
    public function getVerificationRequirementsAttribute()
    {
        $requirements = [];
        
        if ($this->require_gps_verification) {
            $requirements[] = 'gps';
        }
        
        if ($this->require_nfc_verification) {
            $requirements[] = 'nfc';
        }
        
        if ($this->require_photo_on_checkin) {
            $requirements[] = 'photo';
        }
        
        if ($this->require_selfie_on_checkin) {
            $requirements[] = 'selfie';
        }
        
        return [
            'required_methods' => $requirements,
            'allowed_methods' => $this->allowed_verification_methods ?? ['gps', 'qr', 'nfc', 'manual'],
            'checkin_radius' => $this->checkin_radius ?? 100,
            'grace_period' => $this->checkin_grace_period ?? 5,
            'qr_code_rotation' => $this->qr_code_rotation_minutes ?? 5,
            'offline_allowed' => $this->offline_checkin_allowed ?? false,
            'offline_timeout' => $this->offline_timeout_minutes ?? 60,
            'supervisor_required' => $this->supervisor_approval_required ?? false,
        ];
    }

    /**
     * ==================== STAFFING METHODS ====================
     */

    /**
     * Get current personnel count (from schedules)
     */
    public function getCurrentPersonnelCount()
    {
        return $this->currentSchedules()
            ->where('status', 'active')
            ->count();
    }

    /**
     * Get personnel count from QR check-ins today
     */
    public function getQrCheckInsTodayCount()
    {
        try {
            return VerificationLog::whereIn('qr_code_id', 
                $this->qrCodes()->pluck('id')
            )->whereDate('created_at', today())
             ->distinct('user_id')
             ->count('user_id');
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get combined active personnel (schedules + QR check-ins)
     */
    public function getActivePersonnelCount()
    {
        $scheduled = $this->getCurrentPersonnelCount();
        $qrChecked = $this->getQrCheckInsTodayCount();
        
        // Return the higher count or combine based on business logic
        return max($scheduled, $qrChecked);
    }

    /**
     * Get scheduled personnel count for today
     */
    public function getScheduledPersonnelCount()
    {
        return $this->currentSchedules()
            ->where('status', 'scheduled')
            ->count();
    }

    /**
     * Get absent personnel count for today
     */
    public function getAbsentPersonnelCount()
    {
        return $this->currentSchedules()
            ->where('status', 'absent')
            ->count();
    }

    /**
     * Check if post is fully staffed
     */
    public function isFullyStaffed()
    {
        return $this->getActivePersonnelCount() >= $this->max_personnel;
    }

    /**
     * Check if post is understaffed
     */
    public function isUnderstaffed()
    {
        $current = $this->getActivePersonnelCount();
        return $current > 0 && $current < $this->max_personnel;
    }

    /**
     * Check if post is unstaffed
     */
    public function isUnstaffed()
    {
        return $this->getActivePersonnelCount() === 0;
    }

    /**
     * Get staffing percentage
     */
    public function getStaffingPercentageAttribute()
    {
        if ($this->max_personnel === 0) return 0;
        return min(100, round(($this->getActivePersonnelCount() / $this->max_personnel) * 100, 1));
    }

    /**
     * Get staffing status
     */
    public function getStaffingStatusAttribute()
    {
        if ($this->isFullyStaffed()) return 'fully_staffed';
        if ($this->isUnderstaffed()) return 'understaffed';
        return 'unstaffed';
    }

    /**
     * Get staffing status color
     */
    public function getStaffingStatusColor()
    {
        $statuses = [
            'fully_staffed' => 'success',
            'understaffed' => 'warning',
            'unstaffed' => 'danger',
        ];
        
        return $statuses[$this->staffing_status] ?? 'secondary';
    }

    /**
     * ==================== UTILITY METHODS ====================
     */

    /**
     * Get equipment list
     */
    public function getEquipmentList()
    {
        return is_array($this->equipment) ? implode(', ', $this->equipment) : 'No equipment';
    }

    /**
     * Get restrictions list
     */
    public function getRestrictionsList()
    {
        return is_array($this->restrictions) ? implode(', ', $this->restrictions) : 'No restrictions';
    }

    /**
     * Get formatted working hours
     */
    public function getWorkingHoursFormatted()
    {
        if (!$this->working_hours || !is_array($this->working_hours)) return '24/7';
        
        $hours = $this->working_hours;
        if (isset($hours['start']) && isset($hours['end'])) {
            return "{$hours['start']} - {$hours['end']}";
        }
        
        return '24/7';
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute()
    {
        $types = [
            'main_gate' => 'Main Gate',
            'internal_gate' => 'Internal Gate',
            'checkpoint' => 'Checkpoint',
            'patrol_route' => 'Patrol Route',
            'observation_post' => 'Observation Post',
            'control_room' => 'Control Room',
            'access_point' => 'Access Point',
        ];
        
        return $types[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /**
     * Get status badge HTML
     */
    public function getStatusBadge()
    {
        if ($this->is_active) {
            return '<span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">Active</span>';
        }
        return '<span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">Inactive</span>';
    }

    /**
     * Get staffing badge HTML
     */
    public function getStaffingBadge()
    {
        $color = $this->getStaffingStatusColor();
        
        return '<span class="px-2 py-1 text-xs rounded-full" style="background-color: rgba(var(--' . $color . '-rgb), 0.1); color: var(--' . $color . ');">' . $this->staffing_percentage . '% Staffed</span>';
    }

    /**
     * Get coordinates as array
     */
    public function getCoordinatesAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => $this->latitude,
                'lng' => $this->longitude
            ];
        }
        return null;
    }

    /**
     * Get map URL
     */
    public function getMapUrlAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
        }
        return null;
    }

    /**
     * Get Google Maps embed URL
     */
    public function getMapEmbedUrlAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return "https://www.google.com/maps/embed/v1/place?key=" . env('GOOGLE_MAPS_API_KEY') . "&q={$this->latitude},{$this->longitude}";
        }
        return null;
    }

    /**
     * Get QR codes count
     */
    public function getQrCodesCountAttribute()
    {
        return $this->qrCodes()->count();
    }

    /**
     * Get active QR codes count
     */
    public function getActiveQrCodesCountAttribute()
    {
        return $this->activeQrCodes()->count();
    }

    /**
     * Get QR usage stats
     */
    public function getQrUsageStatsAttribute()
    {
        return $this->getQrCodeStats();
    }

    /**
     * ==================== STATISTICS METHODS ====================
     */

    /**
     * Get post statistics
     */
    public static function getStats()
    {
        $posts = self::withCount([
            'qrCodes',
            'activeQrCodes',
            'schedules as total_schedules',
            'schedules as today_schedules' => function($q) {
                $q->whereDate('assignment_date', today());
            }
        ])->get();

        $totalQrUses = 0;
        try {
            $totalQrUses = VerificationLog::count();
        } catch (\Exception $e) {
            // Table might not exist
        }

        return [
            'total_posts' => $posts->count(),
            'active_posts' => $posts->where('is_active', true)->count(),
            'main_gates' => $posts->where('type', 'main_gate')->count(),
            'internal_gates' => $posts->where('type', 'internal_gate')->count(),
            'fully_staffed' => $posts->filter(fn($p) => $p->isFullyStaffed())->count(),
            'understaffed' => $posts->filter(fn($p) => $p->isUnderstaffed())->count(),
            'unstaffed' => $posts->filter(fn($p) => $p->isUnstaffed())->count(),
            'with_gps' => $posts->filter(fn($p) => $p->latitude && $p->longitude)->count(),
            'with_qr' => $posts->filter(fn($p) => $p->qr_codes_count > 0)->count(),
            'total_qr_codes' => $posts->sum('qr_codes_count'),
            'active_qr_codes' => $posts->sum('active_qr_codes_count'),
            'total_qr_uses' => $totalQrUses,
            'total_schedules' => $posts->sum('total_schedules'),
            'today_schedules' => $posts->sum('today_schedules'),
        ];
    }

    /**
     * Get fully staffed count
     */
    public static function getFullyStaffedCount()
    {
        return self::active()
            ->get()
            ->filter(fn($post) => $post->isFullyStaffed())
            ->count();
    }

    /**
     * Get understaffed count
     */
    public static function getUnderstaffedCount()
    {
        return self::active()
            ->get()
            ->filter(fn($post) => $post->isUnderstaffed())
            ->count();
    }

    /**
     * Get unstaffed count
     */
    public static function getUnstaffedCount()
    {
        return self::active()
            ->get()
            ->filter(fn($post) => $post->isUnstaffed())
            ->count();
    }

    /**
     * Get staffing statistics
     */
    public static function getStaffingStatistics()
    {
        $posts = self::active()->get();

        $staffingData = [];
        $totalUtilization = 0;
        $postCount = $posts->count();

        foreach ($posts as $post) {
            $current = $post->getActivePersonnelCount();
            $percentage = $post->staffing_percentage;
            $totalUtilization += $percentage;

            $staffingData[] = [
                'post' => [
                    'id' => $post->id,
                    'name' => $post->name,
                    'code' => $post->code,
                    'type' => $post->type,
                    'type_label' => $post->type_label,
                ],
                'current_personnel' => $current,
                'max_personnel' => $post->max_personnel,
                'percentage' => $percentage,
                'status' => $post->staffing_status,
                'coordinates' => $post->coordinates,
                'qr_checkins_today' => $post->getQrCheckInsTodayCount(),
                'qr_codes_count' => $post->qr_codes_count,
                'active_qr_count' => $post->active_qr_codes_count,
            ];
        }

        $averageUtilization = $postCount > 0 ? round($totalUtilization / $postCount, 1) : 0;

        return [
            'posts' => $postCount,
            'total_capacity' => $posts->sum('max_personnel'),
            'total_assigned' => $posts->sum(fn($p) => $p->getActivePersonnelCount()),
            'average_utilization' => $averageUtilization,
            'fully_staffed' => $posts->filter(fn($p) => $p->isFullyStaffed())->count(),
            'understaffed' => $posts->filter(fn($p) => $p->isUnderstaffed())->count(),
            'unstaffed' => $posts->filter(fn($p) => $p->isUnstaffed())->count(),
            'details' => $staffingData,
        ];
    }

    /**
     * Get post details
     */
    public function getPostDetails()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'location' => $this->location,
            'digital_address' => $this->digital_address,
            'coordinates' => $this->coordinates,
            'map_url' => $this->map_url,
            'max_personnel' => $this->max_personnel,
            'current_personnel' => $this->getCurrentPersonnelCount(),
            'qr_checkins_today' => $this->getQrCheckInsTodayCount(),
            'active_personnel' => $this->getActivePersonnelCount(),
            'scheduled_personnel' => $this->getScheduledPersonnelCount(),
            'absent_personnel' => $this->getAbsentPersonnelCount(),
            'staffing_percentage' => $this->staffing_percentage,
            'staffing_status' => $this->staffing_status,
            'working_hours' => $this->getWorkingHoursFormatted(),
            'equipment' => is_array($this->equipment) ? $this->equipment : [],
            'restrictions' => is_array($this->restrictions) ? $this->restrictions : [],
            'verification_requirements' => $this->verification_requirements,
            'requires_checkin' => $this->requires_checkin,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'qr_codes' => [
                'total' => $this->qr_codes_count,
                'active' => $this->active_qr_codes_count,
                'stats' => $this->qr_usage_stats,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get today's schedules with QR check-in info
     */
    public function getTodaySchedules()
    {
        $schedules = $this->currentSchedules()
            ->with(['securityUser', 'shift'])
            ->orderBy('assignment_date')
            ->get();

        // Enhance with QR check-in info
        foreach ($schedules as $schedule) {
            try {
                $schedule->qr_checked_in = VerificationLog::whereIn('qr_code_id', 
                    $this->qrCodes()->pluck('id')
                )->where('user_id', $schedule->security_user_id)
                 ->whereDate('created_at', today())
                 ->exists();
            } catch (\Exception $e) {
                $schedule->qr_checked_in = false;
            }
        }

        return $schedules;
    }

    /**
     * Get upcoming schedules
     */
    public function getUpcomingSchedules($days = 7)
    {
        return $this->schedules()
            ->whereDate('assignment_date', '>', now()->toDateString())
            ->whereDate('assignment_date', '<=', now()->addDays($days)->toDateString())
            ->with(['securityUser', 'shift'])
            ->orderBy('assignment_date')
            ->get();
    }

    /**
     * Get schedule statistics
     */
    public function getScheduleStatistics()
    {
        return [
            'total_assignments' => $this->schedules()->count(),
            'completed_shifts' => $this->schedules()->where('status', 'completed')->count(),
            'active_today' => $this->currentSchedules()->where('status', 'active')->count(),
            'scheduled_today' => $this->currentSchedules()->where('status', 'scheduled')->count(),
            'absent_today' => $this->currentSchedules()->where('status', 'absent')->count(),
            'staffing_rate' => $this->staffing_percentage,
            'upcoming_count' => $this->upcomingSchedules()->count(),
            'utilization_rate' => $this->calculateUtilizationRate(),
        ];
    }

    /**
     * Calculate utilization rate over last 30 days
     */
    public function calculateUtilizationRate($days = 30)
    {
        $startDate = now()->subDays($days);
        $endDate = now();
        
        $schedules = $this->schedules()
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->count();
        
        $totalPossible = $this->max_personnel * $days;
        
        return $totalPossible > 0 ? round(($schedules / $totalPossible) * 100, 1) : 0;
    }

    /**
     * Get trash statistics with QR info
     */
    public static function getTrashStatistics()
    {
        $trashedPosts = self::onlyTrashed()->count();
        $trashedQrCodes = 0;
        
        try {
            $trashedQrCodes = PostQrCode::onlyTrashed()
                ->whereHas('post', function($q) {
                    $q->onlyTrashed();
                })->count();
        } catch (\Exception $e) {
            // Handle missing table
        }

        return [
            'total_posts' => $trashedPosts,
            'recently_deleted' => self::onlyTrashed()
                ->where('deleted_at', '>=', now()->subDays(7))
                ->count(),
            'by_type' => self::onlyTrashed()
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'trashed_qr_codes' => $trashedQrCodes,
        ];
    }

    /**
     * ==================== VALIDATION RULES ====================
     */

    /**
     * Get validation rules
     */
    public static function validationRules($postId = null)
    {
        $uniqueName = 'unique:security_posts,name';
        $uniqueCode = 'unique:security_posts,code';

        if ($postId) {
            $uniqueName .= ",{$postId}";
            $uniqueCode .= ",{$postId}";
        }

        return [
            // Basic info
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'code' => ['required', 'string', 'max:50', $uniqueCode],
            'type' => ['required', 'string', 'in:main_gate,internal_gate,checkpoint,patrol_route,observation_post,control_room,access_point'],
            'description' => ['nullable', 'string', 'max:1000'],
            
            // Location
            'location' => ['required', 'string', 'max:500'],
            'digital_address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            
            // Equipment and personnel
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:100'],
            'max_personnel' => ['required', 'integer', 'min:1', 'max:10'],
            
            // Status
            'is_active' => ['boolean'],
            'requires_checkin' => ['boolean'],
            
            // Working hours
            'working_hours' => ['nullable', 'array'],
            'working_hours.start' => ['nullable', 'date_format:H:i'],
            'working_hours.end' => ['nullable', 'date_format:H:i', 'after:working_hours.start'],
            
            // Restrictions
            'restrictions' => ['nullable', 'array'],
            'restrictions.*' => ['string', 'max:255'],
            
            // Smart check-in settings
            'checkin_radius' => ['nullable', 'integer', 'min:10', 'max:1000'],
            'checkin_grace_period' => ['nullable', 'integer', 'min:0', 'max:60'],
            'checkout_grace_period' => ['nullable', 'integer', 'min:0', 'max:60'],
            'qr_code_rotation_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'require_photo_on_checkin' => ['boolean'],
            'require_selfie_on_checkin' => ['boolean'],
            'require_gps_verification' => ['boolean'],
            'require_nfc_verification' => ['boolean'],
            'allowed_verification_methods' => ['nullable', 'array'],
            'allowed_verification_methods.*' => ['in:gps,qr,nfc,biometric,manual'],
            'gps_tolerance_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'offline_checkin_allowed' => ['boolean'],
            'offline_timeout_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'supervisor_approval_required' => ['boolean'],
        ];
    }

    /**
     * Check if post has active schedules
     */
    public function hasActiveSchedules()
    {
        return $this->currentSchedules()->exists();
    }

    /**
     * Check if post has upcoming schedules
     */
    public function hasUpcomingSchedules()
    {
        return $this->upcomingSchedules()->exists();
    }

    /**
     * Clear cache for this post
     */
    public function clearCache()
    {
        Cache::forget("qr_code_{$this->id}");
        Cache::forget("post_{$this->id}");
        Cache::forget("post_{$this->id}_stats");
        Cache::forget("post_{$this->id}_qr_stats");
    }

    /**
     * The "booting" method of the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saved(function ($post) {
            $post->clearCache();
        });
        
        static::deleted(function ($post) {
            $post->clearCache();
        });
    }
}