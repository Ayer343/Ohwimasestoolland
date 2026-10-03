<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class SecuritySupervisorAssignment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'security_supervisor_assignments';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'security_post_id',
        'assigned_by',
        'start_date',
        'end_date',
        'supervisor_type',
        'shift_ids',
        'applicable_days',
        'permissions',
        'can_override_checkins',
        'can_approve_swaps',
        'can_approve_overtime',
        'can_review_incidents',
        'can_verify_checkins',
        'can_request_backup',
        'can_approve_breaks',
        'can_escalate_issues',
        'can_view_all_schedules',
        'can_edit_schedules',
        'supervision_config',
        'is_active',
        'is_primary_supervisor',
        'handover_config',
        'notes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'shift_ids' => 'array',
        'applicable_days' => 'array',
        'permissions' => 'array',
        'can_override_checkins' => 'boolean',
        'can_approve_swaps' => 'boolean',
        'can_approve_overtime' => 'boolean',
        'can_review_incidents' => 'boolean',
        'can_verify_checkins' => 'boolean',
        'can_request_backup' => 'boolean',
        'can_approve_breaks' => 'boolean',
        'can_escalate_issues' => 'boolean',
        'can_view_all_schedules' => 'boolean',
        'can_edit_schedules' => 'boolean',
        'is_active' => 'boolean',
        'is_primary_supervisor' => 'boolean',
        'supervision_config' => 'array',
        'handover_config' => 'array',
        'metadata' => 'array',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'supervisor_type' => 'post_supervisor',
        'is_active' => true,
        'is_primary_supervisor' => false,
        'can_override_checkins' => true,
        'can_approve_swaps' => true,
        'can_approve_overtime' => true,
        'can_review_incidents' => true,
        'can_verify_checkins' => true,
        'can_request_backup' => true,
        'can_approve_breaks' => true,
        'can_escalate_issues' => true,
        'can_view_all_schedules' => true,
        'can_edit_schedules' => false,
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'is_current',
        'days_remaining',
        'supervisor_name',
        'post_name',
        'assigned_by_name',
        'permissions_list',
        'supervisor_type_name',
        'coverage_days',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user (security personnel) assigned as supervisor.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the security post being supervised.
     */
    public function post()
    {
        return $this->belongsTo(SecurityPost::class, 'security_post_id');
    }

    /**
     * Get the admin who made the assignment.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the schedules this supervisor oversees (through the post).
     */
    public function schedules()
    {
        return $this->hasManyThrough(
            SecuritySchedule::class,
            SecurityPost::class,
            'id',              // Foreign key on security_posts
            'security_post_id', // Foreign key on security_schedules
            'security_post_id', // Local key on this table
            'id'               // Local key on security_posts
        );
    }

    /**
 * Check if this assignment is expiring soon.
 *
 * @param int $days Number of days to consider as "soon"
 * @return bool
 */
public function isExpiringSoon($days = 7): bool
{
    // Must be active
    if (!$this->is_active) {
        return false;
    }
    
    // Must have an end date
    if (!$this->end_date) {
        return false;
    }
    
    // Must not be expired
    if ($this->end_date < now()) {
        return false;
    }
    
    // Check if within the specified days
    return $this->end_date <= now()->addDays($days);
}

    /**
     * Get the verification logs for check-ins this supervisor may have verified.
     */
    public function verificationLogs()
    {
        return $this->hasMany(VerificationLog::class, 'verified_by')
                    ->where('verification_method', 'supervisor_override');
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get only active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function($q) {
                         $q->whereNull('end_date')
                           ->orWhere('end_date', '>=', now());
                     });
    }

    /**
     * Scope to get assignments for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get assignments for a specific post.
     */
    public function scopeForPost($query, $postId)
    {
        return $query->where('security_post_id', $postId);
    }

    /**
     * Scope to get primary supervisors.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary_supervisor', true);
    }

    /**
     * Scope to get assignments of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('supervisor_type', $type);
    }

    /**
     * Scope to get assignments that are effective on a specific date.
     */
    public function scopeEffectiveOn($query, $date)
    {
        $date = Carbon::parse($date);
        
        return $query->where('is_active', true)
                     ->where('start_date', '<=', $date)
                     ->where(function($q) use ($date) {
                         $q->whereNull('end_date')
                           ->orWhere('end_date', '>=', $date);
                     });
    }

    /**
     * Scope to get assignments that are expiring soon.
     */
    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('is_active', true)
                     ->whereNotNull('end_date')
                     ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    /**
     * Scope to get assignments that have expired.
     */
    public function scopeExpired($query)
    {
        return $query->where('is_active', true)
                     ->whereNotNull('end_date')
                     ->where('end_date', '<', now());
    }

    // ==================== ACCESSORS ====================

    /**
     * Check if assignment is currently active.
     */
    public function getIsCurrentAttribute(): bool
    {
        return $this->is_active && 
               $this->start_date <= now() && 
               ($this->end_date === null || $this->end_date >= now());
    }

    /**
     * Get days remaining in assignment.
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->is_current || !$this->end_date) {
            return null;
        }
        
        return now()->diffInDays($this->end_date, false);
    }

    /**
     * Get supervisor name.
     */
    public function getSupervisorNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    /**
     * Get post name.
     */
    public function getPostNameAttribute(): ?string
    {
        return $this->post?->name;
    }

    /**
     * Get assigned by name.
     */
    public function getAssignedByNameAttribute(): ?string
    {
        return $this->assignedBy?->name;
    }

    /**
     * Get all permissions as a formatted list.
     */
    public function getPermissionsListAttribute(): array
    {
        $permissions = [];
        
        $permissionMap = [
            'can_override_checkins' => 'Override Check-ins',
            'can_approve_swaps' => 'Approve Shift Swaps',
            'can_approve_overtime' => 'Approve Overtime',
            'can_review_incidents' => 'Review Incidents',
            'can_verify_checkins' => 'Verify Check-ins',
            'can_request_backup' => 'Request Backup',
            'can_approve_breaks' => 'Approve Breaks',
            'can_escalate_issues' => 'Escalate Issues',
            'can_view_all_schedules' => 'View All Schedules',
            'can_edit_schedules' => 'Edit Schedules',
        ];
        
        foreach ($permissionMap as $field => $label) {
            if ($this->$field) {
                $permissions[] = $label;
            }
        }
        
        // Add custom permissions from JSON
        if (!empty($this->permissions)) {
            $permissions = array_merge($permissions, $this->permissions);
        }
        
        return $permissions;
    }

    /**
     * Get supervisor type display name.
     */
    public function getSupervisorTypeNameAttribute(): string
    {
        $types = [
            'post_supervisor' => 'Post Supervisor',
            'shift_supervisor' => 'Shift Supervisor',
            'area_supervisor' => 'Area Supervisor',
            'relief_supervisor' => 'Relief Supervisor',
            'training_supervisor' => 'Supervisor in Training',
        ];
        
        return $types[$this->supervisor_type] ?? ucfirst(str_replace('_', ' ', $this->supervisor_type));
    }

    /**
     * Get coverage days as readable string.
     */
    public function getCoverageDaysAttribute(): string
    {
        if (empty($this->applicable_days)) {
            return 'All Days';
        }
        
        $days = [
            1 => 'Mon',
            2 => 'Tue', 
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
            7 => 'Sun'
        ];
        
        $selectedDays = array_map(function($day) use ($days) {
            return $days[$day] ?? '';
        }, $this->applicable_days);
        
        return implode(', ', $selectedDays);
    }

    /**
     * Get shift coverage as readable string.
     */
    public function getShiftCoverageAttribute(): string
    {
        if (empty($this->shift_ids)) {
            return 'All Shifts';
        }
        
        $shiftNames = SecurityShift::whereIn('id', $this->shift_ids)
            ->pluck('name')
            ->toArray();
        
        return implode(', ', $shiftNames);
    }

    // ==================== MUTATORS ====================

    /**
     * Set permissions from array.
     */
    public function setPermissionsAttribute($value)
    {
        $this->attributes['permissions'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Set shift IDs from array.
     */
    public function setShiftIdsAttribute($value)
    {
        $this->attributes['shift_ids'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Set applicable days from array.
     */
    public function setApplicableDaysAttribute($value)
    {
        $this->attributes['applicable_days'] = is_array($value) ? json_encode($value) : $value;
    }

    // ==================== METHODS ====================

    /**
     * Check if this supervisor has a specific permission.
     */
    public function hasPermission($permission): bool
    {
        // Check direct boolean fields
        if (property_exists($this, $permission) && $this->$permission) {
            return true;
        }
        
        // Check permissions JSON array
        if (!empty($this->permissions) && in_array($permission, $this->permissions)) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if this supervisor is responsible for a specific shift.
     */
    public function coversShift($shiftId): bool
    {
        if (empty($this->shift_ids)) {
            return true; // Covers all shifts
        }
        
        return in_array($shiftId, $this->shift_ids);
    }

    /**
     * Check if this supervisor is responsible on a specific day.
     */
    public function coversDay($dayOfWeek): bool
    {
        if (empty($this->applicable_days)) {
            return true; // Covers all days
        }
        
        return in_array($dayOfWeek, $this->applicable_days);
    }

    /**
     * Check if this supervisor is responsible for a specific schedule.
     */
    public function coversSchedule($schedule): bool
    {
        // Check if schedule belongs to supervised post
        if ($schedule->security_post_id != $this->security_post_id) {
            return false;
        }
        
        // Check if assignment is active on that date
        $scheduleDate = Carbon::parse($schedule->assignment_date);
        if ($scheduleDate < $this->start_date || 
            ($this->end_date && $scheduleDate > $this->end_date)) {
            return false;
        }
        
        // Check shift coverage
        if (!$this->coversShift($schedule->security_shift_id)) {
            return false;
        }
        
        // Check day coverage
        if (!$this->coversDay($scheduleDate->dayOfWeekIso)) {
            return false;
        }
        
        return true;
    }

    /**
     * Extend the assignment end date.
     */
    public function extend($newEndDate, $extendedBy = null): bool
    {
        $oldEndDate = $this->end_date;
        
        $this->end_date = Carbon::parse($newEndDate);
        
        // Track in metadata
        $extensions = $this->metadata['extensions'] ?? [];
        $extensions[] = [
            'old_end_date' => $oldEndDate?->format('Y-m-d'),
            'new_end_date' => $this->end_date->format('Y-m-d'),
            'extended_by' => $extendedBy ?? auth()->id(),
            'extended_at' => now()->toDateTimeString(),
            'reason' => 'Manual extension',
        ];
        
        $this->metadata = array_merge($this->metadata ?? [], [
            'extensions' => $extensions,
        ]);
        
        return $this->save();
    }

    /**
     * Terminate this assignment early.
     */
    public function terminate($reason = null, $terminatedBy = null): bool
    {
        $this->is_active = false;
        $this->end_date = now();
        
        // Track termination
        $this->metadata = array_merge($this->metadata ?? [], [
            'terminated_at' => now()->toDateTimeString(),
            'terminated_by' => $terminatedBy ?? auth()->id(),
            'termination_reason' => $reason,
        ]);
        
        return $this->save();
    }

    /**
     * Get all schedules this supervisor needs to oversee for a given date.
     */
    public function getSchedulesForDate($date)
    {
        $date = Carbon::parse($date);
        
        if (!$this->coversDay($date->dayOfWeekIso)) {
            return collect();
        }
        
        return SecuritySchedule::where('security_post_id', $this->security_post_id)
            ->whereDate('assignment_date', $date)
            ->when(!empty($this->shift_ids), function($query) {
                return $query->whereIn('security_shift_id', $this->shift_ids);
            })
            ->with(['securityUser', 'shift'])
            ->get();
    }

    /**
 * Get pending approvals for this supervisor.
 */
public function getPendingApprovals()
{
    $pending = [
        'swap_requests' => 0,
        'overtime_requests' => 0,
        'pending_verifications' => 0,
    ];

    try {
        // Get post IDs this supervisor oversees
        $postIds = [$this->security_post_id];
        
        // Get shift IDs this supervisor oversees
        $shiftIds = $this->shift_ids ?? [];

        // Safely count swap requests
        if (\Schema::hasTable('shift_swaps')) {
            if (\Schema::hasColumn('shift_swaps', 'status') && 
                \Schema::hasColumn('shift_swaps', 'security_post_id')) {
                $pending['swap_requests'] = \DB::table('shift_swaps')
                    ->whereIn('security_post_id', $postIds)
                    ->where('status', 'pending')
                    ->count();
            }
        }

        // Safely count overtime requests
        if (\Schema::hasTable('overtime_requests')) {
            if (\Schema::hasColumn('overtime_requests', 'status') && 
                \Schema::hasColumn('overtime_requests', 'security_post_id')) {
                $pending['overtime_requests'] = \DB::table('overtime_requests')
                    ->whereIn('security_post_id', $postIds)
                    ->where('status', 'pending')
                    ->count();
            }
        }
        // Alternative: Check security_schedules for pending overtime
        elseif (\Schema::hasTable('security_schedules')) {
            if (\Schema::hasColumn('security_schedules', 'overtime_approved')) {
                $pending['overtime_requests'] = \App\Models\SecuritySchedule::whereIn('security_post_id', $postIds)
                    ->whereIn('security_shift_id', $shiftIds)
                    ->where('overtime_minutes', '>', 0)
                    ->where('overtime_approved', false)
                    ->count();
            }
        }

        // Safely count pending verifications
        if (\Schema::hasTable('verification_logs')) {
            if (\Schema::hasColumn('verification_logs', 'verified_by') && 
                \Schema::hasColumn('verification_logs', 'verified_at')) {
                $pending['pending_verifications'] = \DB::table('verification_logs')
                    ->where('verified_by', $this->user_id)
                    ->whereNull('verified_at')
                    ->count();
            }
        }

    } catch (\Exception $e) {
        \Log::warning('Error getting pending approvals: ' . $e->getMessage(), [
            'assignment_id' => $this->id
        ]);
    }

    return $pending;
}

/**
 * Safely get verification logs for this supervisor.
 */
public function getVerificationLogsSafely($limit = 10)
{
    try {
        // Check if verification_logs table exists
        if (!\Schema::hasTable('verification_logs')) {
            return collect([]);
        }

        $query = \DB::table('verification_logs');
        
        // Check which columns exist and build query accordingly
        if (\Schema::hasColumn('verification_logs', 'verified_by')) {
            $query->where('verified_by', $this->user_id);
        } elseif (\Schema::hasColumn('verification_logs', 'supervisor_id')) {
            $query->where('supervisor_id', $this->user_id);
        } elseif (\Schema::hasColumn('verification_logs', 'user_id')) {
            $query->where('user_id', $this->user_id);
        } else {
            return collect([]);
        }

        // Add other conditions if columns exist
        if (\Schema::hasColumn('verification_logs', 'verification_method')) {
            $query->where('verification_method', 'supervisor_override');
        }

        // Order by created_at if column exists
        if (\Schema::hasColumn('verification_logs', 'created_at')) {
            $query->orderBy('created_at', 'desc');
        }

        return $query->limit($limit)->get();

    } catch (\Exception $e) {
        \Log::warning('Error getting verification logs: ' . $e->getMessage(), [
            'assignment_id' => $this->id,
            'user_id' => $this->user_id
        ]);
        return collect([]);
    }
}

   /**
 * Get supervision statistics for this assignment.
 */
public function getSupervisionHistory()
{
    $stats = [
        'total_days' => 0,
        'schedules_overseen' => 0,
        'verifications_performed' => 0,
        'approvals_given' => 0,
        'incidents_reported' => 0,
        'incidents_resolved' => 0,
        'backup_requests' => 0,
        'overtime_approved' => 0,
        'swaps_approved' => 0,
    ];

    try {
        // Calculate days
        $start = Carbon::parse($this->start_date);
        $end = $this->end_date ? Carbon::parse($this->end_date) : Carbon::now();
        $stats['total_days'] = $start->diffInDays($end) + 1;

        // Get schedules overseen
        $stats['schedules_overseen'] = \App\Models\SecuritySchedule::where('security_post_id', $this->security_post_id)
            ->whereBetween('assignment_date', [$this->start_date, $this->end_date ?? Carbon::now()])
            ->count();

        // CHECK IF TABLES/COLUMNS EXIST BEFORE QUERYING
        $this->safeCountVerifications($stats);
        $this->safeCountApprovals($stats);
        $this->safeCountIncidents($stats);
        $this->safeCountBackupRequests($stats);
        $this->safeCountOvertimeApprovals($stats);
        $this->safeCountSwapApprovals($stats);

    } catch (\Exception $e) {
        \Log::warning('Error getting supervision history: ' . $e->getMessage(), [
            'assignment_id' => $this->id
        ]);
    }

    return $stats;
}

/**
 * Safely count verifications
 */
private function safeCountVerifications(&$stats)
{
    try {
        // Check if verification_logs table exists and has the right columns
        if (\Schema::hasTable('verification_logs')) {
            if (\Schema::hasColumn('verification_logs', 'verified_by')) {
                $stats['verifications_performed'] = \DB::table('verification_logs')
                    ->where('verified_by', $this->user_id)
                    ->whereBetween('created_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                    ->count();
            } elseif (\Schema::hasColumn('verification_logs', 'user_id')) {
                $stats['verifications_performed'] = \DB::table('verification_logs')
                    ->where('user_id', $this->user_id)
                    ->whereBetween('created_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                    ->count();
            }
        }
    } catch (\Exception $e) {
        \Log::debug('Could not count verifications: ' . $e->getMessage());
    }
}

/**
 * Safely count approvals
 */
private function safeCountApprovals(&$stats)
{
    try {
        // Check if approvals table exists
        if (\Schema::hasTable('approvals')) {
            if (\Schema::hasColumn('approvals', 'approved_by')) {
                $stats['approvals_given'] = \DB::table('approvals')
                    ->where('approved_by', $this->user_id)
                    ->whereBetween('created_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                    ->count();
            }
        }
    } catch (\Exception $e) {
        \Log::debug('Could not count approvals: ' . $e->getMessage());
    }
}

/**
 * Safely count incidents using SecurityReport
 */
private function safeCountIncidents(&$stats)
{
    try {
        if (class_exists('App\Models\SecurityReport')) {
            // Count incidents reported
            $stats['incidents_reported'] = \App\Models\SecurityReport::where('reported_by', $this->user_id)
                ->whereBetween('created_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                ->count();

            // Count incidents resolved (if resolved_by column exists)
            if (\Schema::hasColumn('security_reports', 'resolved_by')) {
                $stats['incidents_resolved'] = \App\Models\SecurityReport::where('resolved_by', $this->user_id)
                    ->whereBetween('resolved_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                    ->count();
            }
        }
    } catch (\Exception $e) {
        \Log::debug('Could not count incidents: ' . $e->getMessage());
    }
}

/**
 * Safely count backup requests
 */
private function safeCountBackupRequests(&$stats)
{
    try {
        if (\Schema::hasTable('backup_requests')) {
            if (\Schema::hasColumn('backup_requests', 'requested_by')) {
                $stats['backup_requests'] = \DB::table('backup_requests')
                    ->where('requested_by', $this->user_id)
                    ->whereBetween('created_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                    ->count();
            }
        }
    } catch (\Exception $e) {
        \Log::debug('Could not count backup requests: ' . $e->getMessage());
    }
}

/**
 * Safely count overtime approvals
 */
private function safeCountOvertimeApprovals(&$stats)
{
    try {
        // Check if overtime_requests table exists
        if (\Schema::hasTable('overtime_requests')) {
            if (\Schema::hasColumn('overtime_requests', 'approved_by')) {
                $stats['overtime_approved'] = \DB::table('overtime_requests')
                    ->where('approved_by', $this->user_id)
                    ->whereBetween('approved_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                    ->where('status', 'approved')
                    ->count();
            }
        }
        // Alternative: Check security_schedules for overtime approvals
        elseif (\Schema::hasTable('security_schedules')) {
            if (\Schema::hasColumn('security_schedules', 'overtime_approved') && 
                \Schema::hasColumn('security_schedules', 'approved_by')) {
                $stats['overtime_approved'] = \App\Models\SecuritySchedule::where('approved_by', $this->user_id)
                    ->where('overtime_minutes', '>', 0)
                    ->where('overtime_approved', true)
                    ->whereBetween('updated_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                    ->count();
            }
        }
    } catch (\Exception $e) {
        \Log::debug('Could not count overtime approvals: ' . $e->getMessage());
    }
}

/**
 * Safely count swap approvals
 */
private function safeCountSwapApprovals(&$stats)
{
    try {
        if (\Schema::hasTable('shift_swaps')) {
            if (\Schema::hasColumn('shift_swaps', 'approved_by')) {
                $stats['swaps_approved'] = \DB::table('shift_swaps')
                    ->where('approved_by', $this->user_id)
                    ->whereBetween('approved_at', [$this->start_date, $this->end_date ?? Carbon::now()])
                    ->where('status', 'approved')
                    ->count();
            }
        }
    } catch (\Exception $e) {
        \Log::debug('Could not count swap approvals: ' . $e->getMessage());
    }
}

    // ==================== STATIC METHODS ====================

    /**
     * Get the current supervisor for a post.
     */
    public static function getCurrentSupervisorForPost($postId)
    {
        return self::where('security_post_id', $postId)
            ->active()
            ->with('user')
            ->first();
    }

    /**
     * Check if a user is currently supervising a post.
     */
    public static function isUserSupervisingPost($userId, $postId): bool
    {
        return self::where('user_id', $userId)
            ->where('security_post_id', $postId)
            ->active()
            ->exists();
    }

    /**
     * Get all active supervisors for a given date.
     */
    public static function getActiveSupervisorsForDate($date)
    {
        $date = Carbon::parse($date);
        
        return self::with(['user', 'post'])
            ->active()
            ->effectiveOn($date)
            ->get()
            ->filter(function($assignment) use ($date) {
                return $assignment->coversDay($date->dayOfWeekIso);
            });
    }

    /**
     * Get default permissions for a supervisor type.
     */
    public static function getDefaultPermissions($type): array
    {
        $permissions = [
            'post_supervisor' => [
                'can_override_checkins' => true,
                'can_approve_swaps' => true,
                'can_approve_overtime' => true,
                'can_review_incidents' => true,
                'can_verify_checkins' => true,
                'can_request_backup' => true,
                'can_approve_breaks' => true,
                'can_escalate_issues' => true,
                'can_view_all_schedules' => true,
                'can_edit_schedules' => false,
            ],
            'shift_supervisor' => [
                'can_override_checkins' => true,
                'can_approve_swaps' => true,
                'can_approve_overtime' => true,
                'can_review_incidents' => false,
                'can_verify_checkins' => true,
                'can_request_backup' => true,
                'can_approve_breaks' => true,
                'can_escalate_issues' => true,
                'can_view_all_schedules' => true,
                'can_edit_schedules' => false,
            ],
            'area_supervisor' => [
                'can_override_checkins' => true,
                'can_approve_swaps' => true,
                'can_approve_overtime' => true,
                'can_review_incidents' => true,
                'can_verify_checkins' => true,
                'can_request_backup' => true,
                'can_approve_breaks' => true,
                'can_escalate_issues' => true,
                'can_view_all_schedules' => true,
                'can_edit_schedules' => true,
            ],
            'relief_supervisor' => [
                'can_override_checkins' => true,
                'can_approve_swaps' => false,
                'can_approve_overtime' => false,
                'can_review_incidents' => false,
                'can_verify_checkins' => true,
                'can_request_backup' => true,
                'can_approve_breaks' => true,
                'can_escalate_issues' => true,
                'can_view_all_schedules' => true,
                'can_edit_schedules' => false,
            ],
            'training_supervisor' => [
                'can_override_checkins' => false,
                'can_approve_swaps' => false,
                'can_approve_overtime' => false,
                'can_review_incidents' => false,
                'can_verify_checkins' => true,
                'can_request_backup' => false,
                'can_approve_breaks' => true,
                'can_escalate_issues' => true,
                'can_view_all_schedules' => true,
                'can_edit_schedules' => false,
            ],
        ];
        
        return $permissions[$type] ?? $permissions['post_supervisor'];
    }

    /**
     * Create a new supervisor assignment with default permissions.
     */
    public static function createAssignment($userId, $postId, $assignedBy, $startDate, $type = 'post_supervisor', $options = [])
    {
        $defaultPermissions = self::getDefaultPermissions($type);
        
        $data = array_merge([
            'user_id' => $userId,
            'security_post_id' => $postId,
            'assigned_by' => $assignedBy,
            'start_date' => $startDate,
            'supervisor_type' => $type,
            'is_active' => true,
        ], $defaultPermissions, $options);
        
        // Deactivate any existing active assignments for this post
        self::where('security_post_id', $postId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
        
        return self::create($data);
    }
}