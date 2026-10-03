<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SecuritySchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'security_schedules';

    protected $fillable = [
        // Core Foreign Keys
        'security_post_id',
        'security_shift_id',
        'security_user_id',
        'assigned_by',
        
        // Rotation foreign keys
        'rotation_group_id',
        'rotated_from_user_id',
        
        // Core Schedule Information
        'assignment_date',
        'status',
        
        // Time Tracking
        'checkin_time',
        'checkout_time',
        'checkin_location',
        'checkout_location',
        
        // Late/Overtime Tracking
        'late_minutes',
        'overtime_minutes',
        
        // Break Management
        'include_breaks',
        'current_break_id',
        'break_start_time',
        'break_end_time',
        'break_duration',
        'break_status',
        'break_notes',
        'break_history',
        
        // Handover Information
        'handover_info',
        'handover_completed',
        'handover_completed_at',
        'handover_completed_by',
        'handover_notes',
        
        // Rotation Data
        'rotation_data',
        'rotation_group_type',
        'rotation_sequence_number',
        'rotation_cycle_start_date',
        'rotation_cycle_end_date',
        'rotation_swap_count',
        'rotation_preference_score',
        'is_rotated',
        'rotated_at',
        'rotation_history',
        
        // Approval Tracking
        'is_approved',
        'approved_by',
        'approved_at',
        
        // Additional Information
        'notes',
        'emergency_contact',
        'special_instructions',
        'checkin_notes',
        
        // Performance Metrics
        'attendance_score',
        'punctuality_score',
        
        // Audit & Metadata
        'audit_log',
        'metadata',
    ];

    protected $casts = [
        'assignment_date' => 'date',
        'checkin_time' => 'datetime',
        'checkout_time' => 'datetime',
        'checkin_location' => 'array',
        'checkout_location' => 'array',
        'handover_info' => 'array',
        'rotation_data' => 'array',
        'break_start_time' => 'datetime',
        'break_end_time' => 'datetime',
        'break_history' => 'array',
        'include_breaks' => 'boolean',
        'handover_completed' => 'boolean',
        'handover_completed_at' => 'datetime',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'metadata' => 'array',
        'late_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'break_duration' => 'integer',
        
        // Rotation fields casts
        'rotation_cycle_start_date' => 'date',
        'rotation_cycle_end_date' => 'date',
        'rotation_swap_count' => 'integer',
        'rotation_preference_score' => 'integer',
        'is_rotated' => 'boolean',
        'rotated_at' => 'datetime',
        'rotation_history' => 'array',
        'rotation_sequence_number' => 'integer',
        
        // Performance metrics
        'attendance_score' => 'float',
        'punctuality_score' => 'float',
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'include_breaks' => false,
        'handover_completed' => false,
        'is_approved' => false,
        'late_minutes' => 0,
        'overtime_minutes' => 0,
        'break_duration' => 0,
        
        // Default attributes for rotation
        'rotation_swap_count' => 0,
        'is_rotated' => false,
        'rotation_preference_score' => 5, // Neutral default
        'rotation_sequence_number' => 0,
    ];

    protected $dates = [
        'assignment_date',
        'checkin_time',
        'checkout_time',
        'break_start_time',
        'break_end_time',
        'handover_completed_at',
        'approved_at',
        'rotated_at',
        'rotation_cycle_start_date',
        'rotation_cycle_end_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // =============================================
    // CONSTANTS
    // =============================================
    
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ABSENT = 'absent';
    const STATUS_CANCELLED = 'cancelled';
    
    const USER_TYPE_SECURITY_PERSONNEL = 'security_personnel';
    const USER_STATUS_ACTIVE = 'active';
    
    const ROTATION_GROUP_DAY = 'day';
    const ROTATION_GROUP_NIGHT = 'night';
    const ROTATION_GROUP_EVENING = 'evening';
    const ROTATION_GROUP_ROTATING = 'rotating';
    
    const ROTATION_PATTERN_FULL_SWAP = 'full_swap';
    const ROTATION_PATTERN_STAGGERED = 'staggered';
    const ROTATION_PATTERN_PARTIAL = 'partial';
    
    const ROTATION_FREQUENCY_DAILY = 'daily';
    const ROTATION_FREQUENCY_WEEKLY = 'weekly';
    const ROTATION_FREQUENCY_BIWEEKLY = 'biweekly';
    const ROTATION_FREQUENCY_MONTHLY = 'monthly';

    const BREAK_STATUS_ACTIVE = 'active';
    const BREAK_STATUS_COMPLETED = 'completed';
    const BREAK_STATUS_SCHEDULED = 'scheduled';

    // =============================================
    // RELATIONSHIPS
    // =============================================
    
    /**
     * Get the security post associated with this schedule
     */
    public function post()
    {
        return $this->belongsTo(SecurityPost::class, 'security_post_id')
                    ->withTrashed();
    }

    /**
     * Get the security shift associated with this schedule
     */
    public function shift()
    {
        return $this->belongsTo(SecurityShift::class, 'security_shift_id')
                    ->withTrashed();
    }

    /**
     * Get the security personnel assigned to this schedule
     */
    public function securityUser()
    {
        return $this->belongsTo(User::class, 'security_user_id')
                    ->withTrashed();
    }

    /**
     * Get the user who assigned this schedule
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the user who approved this schedule
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    /**
     * Get the original user before rotation
     */
    public function rotatedFromUser()
    {
        return $this->belongsTo(User::class, 'rotated_from_user_id')
                    ->withTrashed();
    }
    
    /**
     * Get the rotation group for this schedule
     */
    public function rotationGroup()
    {
        return $this->belongsTo(RotationGroup::class, 'rotation_group_id');
    }

    /**
     * Get the user who completed the handover
     */
    public function handoverCompletedBy()
    {
        return $this->belongsTo(User::class, 'handover_completed_by');
    }

    // In SecuritySchedule model
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
    
    public function canBeSupervisedBy($userId): bool
    {
        // Check if user is assigned as supervisor for this post on this date
        return SecuritySupervisorAssignment::where('user_id', $userId)
            ->where('security_post_id', $this->security_post_id)
            ->active()
            ->where('start_date', '<=', $this->assignment_date)
            ->exists();
    }

    // =============================================
    // SCOPES
    // =============================================
    
    /**
     * Scope a query to only include schedules for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('assignment_date', today());
    }

    /**
     * Scope a query to only include schedules for tomorrow
     */
    public function scopeTomorrow($query)
    {
        return $query->whereDate('assignment_date', today()->addDay());
    }

    /**
     * Scope a query to only include active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include scheduled schedules
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope a query to only include completed schedules
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include absent schedules
     */
    public function scopeAbsent($query)
    {
        return $query->where('status', self::STATUS_ABSENT);
    }

    /**
     * Scope a query to only include cancelled schedules
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope a query to only include late check-ins
     */
    public function scopeLate($query)
    {
        return $query->where('late_minutes', '>', 0);
    }

    /**
     * Scope a query to only include schedules with overtime
     */
    public function scopeWithOvertime($query)
    {
        return $query->where('overtime_minutes', '>', 0);
    }

    /**
     * Scope a query to only include schedules with breaks
     */
    public function scopeWithBreaks($query)
    {
        return $query->where('include_breaks', true);
    }

    /**
     * Scope a query to only include schedules on break
     */
    public function scopeOnBreak($query)
    {
        return $query->where('break_status', self::BREAK_STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include schedules needing handover
     */
    public function scopeNeedsHandover($query)
    {
        return $query->whereNotNull('handover_info')
                    ->where('handover_completed', false)
                    ->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope a query to only include schedules for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('security_user_id', $userId);
    }

    /**
     * Scope a query to only include schedules for a specific post
     */
    public function scopeForPost($query, $postId)
    {
        return $query->where('security_post_id', $postId);
    }

    /**
     * Scope a query to only include schedules for a specific shift
     */
    public function scopeForShift($query, $shiftId)
    {
        return $query->where('security_shift_id', $shiftId);
    }

    /**
     * Scope a query to only include schedules between dates
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('assignment_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include upcoming schedules
     */
    public function scopeUpcoming($query, $days = 7)
    {
        return $query->whereDate('assignment_date', '>=', today())
                    ->whereDate('assignment_date', '<=', today()->addDays($days));
    }

    /**
     * Scope a query to only include past schedules
     */
    public function scopePast($query, $days = 7)
    {
        return $query->whereDate('assignment_date', '>=', today()->subDays($days))
                    ->whereDate('assignment_date', '<', today());
    }

    /**
     * Scope a query to only include approved schedules
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope a query to only include unapproved schedules
     */
    public function scopeUnapproved($query)
    {
        return $query->where('is_approved', false);
    }
    
    /**
     * Scope a query to only include schedules in a rotation group
     */
    public function scopeInRotationGroup($query, $groupId)
    {
        return $query->where('rotation_group_id', $groupId);
    }
    
    /**
     * Scope a query to only include schedules with a specific rotation group type
     */
    public function scopeWithRotationGroupType($query, $type)
    {
        return $query->where('rotation_group_type', $type);
    }
    
    /**
     * Scope a query to only include schedules that have been rotated
     */
    public function scopeRotated($query)
    {
        return $query->where('is_rotated', true);
    }
    
    /**
     * Scope a query to only include schedules that have not been rotated
     */
    public function scopeNotRotated($query)
    {
        return $query->where('is_rotated', false);
    }
    
    /**
     * Scope a query to only include schedules in a rotation cycle
     */
    public function scopeInRotationCycle($query, $date = null)
    {
        $date = $date ?? now();
        return $query->where('rotation_cycle_start_date', '<=', $date)
                     ->where('rotation_cycle_end_date', '>=', $date);
    }
    
    /**
     * Scope a query to only include day shifts
     */
    public function scopeDayShifts($query)
    {
        return $query->whereHas('shift', function($q) {
            $q->where('category', 'day');
        });
    }
    
    /**
     * Scope a query to only include night shifts
     */
    public function scopeNightShifts($query)
    {
        return $query->whereHas('shift', function($q) {
            $q->where('category', 'night');
        });
    }
    
    /**
     * Scope a query to only include evening shifts
     */
    public function scopeEveningShifts($query)
    {
        return $query->whereHas('shift', function($q) {
            $q->where('category', 'evening');
        });
    }
    
    /**
     * Scope a query to only include schedules by preference score
     */
    public function scopeByPreferenceScore($query, $minScore = 1)
    {
        return $query->where('rotation_preference_score', '>=', $minScore);
    }

    // =============================================
    // ACCESSORS & MUTATORS
    // =============================================
    
    /**
     * Get the check-in time formatted
     */
    public function getFormattedCheckinTimeAttribute(): string
    {
        return $this->checkin_time ? $this->checkin_time->format('h:i A') : 'N/A';
    }

    /**
     * Get the check-out time formatted
     */
    public function getFormattedCheckoutTimeAttribute(): string
    {
        return $this->checkout_time ? $this->checkout_time->format('h:i A') : 'N/A';
    }

    /**
     * Get the assignment date formatted
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->assignment_date->format('D, M j, Y');
    }

    /**
     * Get the shift time range
     */
    public function getShiftTimeRangeAttribute(): string
    {
        if (!$this->shift) {
            return 'N/A';
        }
        
        try {
            return $this->shift->getTimeRange();
        } catch (\Exception $e) {
            return 'Invalid time';
        }
    }

    /**
     * Get the duration formatted
     */
    public function getFormattedDurationAttribute(): string
    {
        return $this->getDurationFormatted();
    }

    /**
     * Get late minutes formatted
     */
    public function getFormattedLateMinutesAttribute(): string
    {
        return $this->getLateMinutesFormatted();
    }

    /**
     * Get overtime formatted
     */
    public function getFormattedOvertimeAttribute(): string
    {
        return $this->getOvertimeFormatted();
    }

    // =============================================
    // HELPER METHODS FOR DATE PARSING
    // =============================================
    
   /**
 * Get scheduled start time as Carbon instance
 */
public function getScheduledStartTime(): ?Carbon
{
    if (!$this->shift) {
        return null;
    }

    try {
        // Make sure assignment_date is a Carbon instance
        $date = $this->assignment_date instanceof Carbon 
            ? $this->assignment_date 
            : Carbon::parse($this->assignment_date);
        
        // Get the shift start time - ensure we only extract the time portion
        $startTime = $this->shift->start_time;
        
        // If start_time is a full datetime string, extract just the time part
        if (strpos($startTime, ' ') !== false) {
            $startTime = explode(' ', $startTime)[1] ?? $startTime;
        }
        
        // If start_time contains seconds, we might need to format it properly
        if (strpos($startTime, ':') !== false) {
            // Ensure we only have hours:minutes or hours:minutes:seconds
            $timeParts = explode(':', $startTime);
            if (count($timeParts) == 3) {
                // Already has seconds, keep as is
            } elseif (count($timeParts) == 2) {
                // Add seconds if missing
                $startTime .= ':00';
            }
        }
        
        // Combine date and time properly
        $dateString = $date->format('Y-m-d') . ' ' . $startTime;
        
        return Carbon::parse($dateString);
    } catch (\Exception $e) {
        Log::error('Error parsing scheduled start time', [
            'schedule_id' => $this->id,
            'date' => $this->assignment_date,
            'start_time' => $this->shift->start_time,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Get scheduled end time as Carbon instance
 */
public function getScheduledEndTime(): ?Carbon
{
    if (!$this->shift) {
        return null;
    }

    try {
        $date = $this->assignment_date instanceof Carbon 
            ? $this->assignment_date 
            : Carbon::parse($this->assignment_date);
        
        // Get the shift end time - ensure we only extract the time portion
        $endTime = $this->shift->end_time;
        
        // If end_time is a full datetime string, extract just the time part
        if (strpos($endTime, ' ') !== false) {
            $endTime = explode(' ', $endTime)[1] ?? $endTime;
        }
        
        // If end_time contains seconds, we might need to format it properly
        if (strpos($endTime, ':') !== false) {
            $timeParts = explode(':', $endTime);
            if (count($timeParts) == 3) {
                // Already has seconds, keep as is
            } elseif (count($timeParts) == 2) {
                // Add seconds if missing
                $endTime .= ':00';
            }
        }
        
        // Combine date and time properly
        $dateString = $date->format('Y-m-d') . ' ' . $endTime;
        $endDateTime = Carbon::parse($dateString);
        
        // Handle overnight shifts
        if ($this->shift->is_overnight) {
            $endDateTime->addDay();
        }
        
        return $endDateTime;
    } catch (\Exception $e) {
        Log::error('Error parsing scheduled end time', [
            'schedule_id' => $this->id,
            'date' => $this->assignment_date,
            'end_time' => $this->shift->end_time,
            'is_overnight' => $this->shift->is_overnight,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

    /**
     * Get scheduled start time with grace period
     */
    public function getEarliestCheckinTime(): ?Carbon
    {
        $startTime = $this->getScheduledStartTime();
        
        if (!$startTime) {
            return null;
        }
        
        return $startTime->copy()->subMinutes(15);
    }

    // =============================================
    // CHECKIN/CHECKOUT METHODS
    // =============================================
    
    /**
     * Check in for the shift
     */
    public function checkin($location = null, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'checkin_time' => now(),
            'checkin_location' => $location ?? ['lat' => null, 'lng' => null],
            'notes' => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);

        // Calculate late minutes
        $this->calculateLateMinutes();

        // Log activity
        $this->logActivity('checkin', [
            'post_id' => $this->security_post_id,
            'post_name' => $this->post->name ?? 'Unknown',
            'location' => $location,
            'late_minutes' => $this->late_minutes,
        ]);

        // Clear cache
        $this->clearCache();

        return $this;
    }

    /**
     * Check out from the shift
     */
    public function checkout($location = null, $notes = null)
    {
        // End any active break first
        if ($this->break_status === self::BREAK_STATUS_ACTIVE) {
            $this->endBreak($notes);
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'checkout_time' => now(),
            'checkout_location' => $location ?? ['lat' => null, 'lng' => null],
            'notes' => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);

        // Calculate overtime
        $this->calculateOvertimeMinutes();

        // Log activity
        $this->logActivity('checkout', [
            'post_id' => $this->security_post_id,
            'post_name' => $this->post->name ?? 'Unknown',
            'duration' => $this->getDuration(),
            'overtime_minutes' => $this->overtime_minutes,
            'location' => $location,
        ]);

        // Clear cache
        $this->clearCache();

        return $this;
    }

    /**
     * Mark as absent
     */
    public function markAsAbsent($reason = null)
    {
        $this->update([
            'status' => self::STATUS_ABSENT,
            'notes' => $reason ? ($this->notes ? $this->notes . "\nAbsent: " . $reason : 'Absent: ' . $reason) : $this->notes,
        ]);

        $this->logActivity('absent', [
            'post_id' => $this->security_post_id,
            'reason' => $reason,
        ]);

        // Clear cache
        $this->clearCache();

        return $this;
    }

    /**
     * Mark as complete
     */
    public function markAsComplete($notes = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'notes' => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);

        $this->logActivity('marked_complete', [
            'post_id' => $this->security_post_id,
            'marked_by' => Auth::id(),
        ]);

        // Clear cache
        $this->clearCache();

        return $this;
    }

    /**
     * Cancel the schedule
     */
    public function cancel($reason = null)
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'notes' => $reason ? ($this->notes ? $this->notes . "\nCancelled: " . $reason : 'Cancelled: ' . $reason) : $this->notes,
        ]);

        $this->logActivity('cancelled', [
            'post_id' => $this->security_post_id,
            'reason' => $reason,
        ]);

        // Clear cache
        $this->clearCache();

        return $this;
    }

    /**
     * Approve the schedule
     */
    public function approve($approvedBy = null)
    {
        $this->update([
            'is_approved' => true,
            'approved_by' => $approvedBy ?? Auth::id(),
            'approved_at' => now(),
        ]);

        $this->logActivity('approved', [
            'post_id' => $this->security_post_id,
            'approved_by' => $approvedBy ?? Auth::id(),
        ]);

        // Clear cache
        $this->clearCache();

        return $this;
    }

    /**
     * Unapprove the schedule
     */
    public function unapprove($reason = null)
    {
        $this->update([
            'is_approved' => false,
            'approved_by' => null,
            'approved_at' => null,
            'notes' => $reason ? ($this->notes ? $this->notes . "\nUnapproved: " . $reason : 'Unapproved: ' . $reason) : $this->notes,
        ]);

        $this->logActivity('unapproved', [
            'post_id' => $this->security_post_id,
            'reason' => $reason,
        ]);

        // Clear cache
        $this->clearCache();

        return $this;
    }

    // =============================================
    // BREAK MANAGEMENT
    // =============================================
    
    /**
     * Start a break
     */
    public function startBreak($breakId = null, $notes = null)
    {
        if (!$this->include_breaks) {
            throw new \Exception('This schedule does not include breaks.');
        }

        if ($this->break_status === self::BREAK_STATUS_ACTIVE) {
            throw new \Exception('A break is already active.');
        }

        $this->update([
            'current_break_id' => $breakId,
            'break_start_time' => now(),
            'break_status' => self::BREAK_STATUS_ACTIVE,
            'break_notes' => $notes,
        ]);

        $this->logActivity('break_started', [
            'post_id' => $this->security_post_id,
            'break_id' => $breakId,
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * End the current break
     */
    public function endBreak($notes = null)
    {
        if ($this->break_status !== self::BREAK_STATUS_ACTIVE || !$this->break_start_time) {
            throw new \Exception('No active break to end.');
        }

        $breakDuration = now()->diffInMinutes($this->break_start_time);
        
        // Update break history
        $breakHistory = $this->break_history ?? [];
        $breakHistory[] = [
            'break_id' => $this->current_break_id,
            'start_time' => $this->break_start_time,
            'end_time' => now(),
            'duration' => $breakDuration,
            'notes' => $notes,
        ];

        $this->update([
            'break_end_time' => now(),
            'break_duration' => $breakDuration,
            'break_status' => self::BREAK_STATUS_COMPLETED,
            'break_history' => $breakHistory,
            'break_notes' => $notes,
            'current_break_id' => null,
            'break_start_time' => null,
        ]);

        $this->logActivity('break_ended', [
            'post_id' => $this->security_post_id,
            'duration_minutes' => $breakDuration,
            'notes' => $notes,
        ]);

        return $this;
    }

    // =============================================
    // HANDOVER MANAGEMENT
    // =============================================
    
    /**
     * Complete the handover process
     */
    public function completeHandover($notes = null, $checklist = null, $completedBy = null)
    {
        if (!$this->handover_info) {
            throw new \Exception('No handover configured for this schedule.');
        }

        $handoverInfo = $this->handover_info;
        
        // Update checklist items if provided
        if ($checklist && isset($handoverInfo['checklist_items'])) {
            foreach ($checklist as $index => $itemUpdate) {
                if (isset($handoverInfo['checklist_items'][$index])) {
                    $handoverInfo['checklist_items'][$index]['completed'] = $itemUpdate['completed'] ?? false;
                    $handoverInfo['checklist_items'][$index]['completed_by'] = $completedBy ?? Auth::id();
                    $handoverInfo['checklist_items'][$index]['completed_at'] = now();
                    $handoverInfo['checklist_items'][$index]['notes'] = $itemUpdate['notes'] ?? null;
                }
            }
        }

        // Add handover notes to handover_info
        if ($notes) {
            $handoverInfo['handover_notes'] = $notes;
            $handoverInfo['handover_notes_submitted_at'] = now();
            $handoverInfo['handover_notes_submitted_by'] = $completedBy ?? Auth::id();
        }

        $this->update([
            'handover_completed' => true,
            'handover_completed_at' => now(),
            'handover_completed_by' => $completedBy ?? Auth::id(),
            'handover_notes' => $notes,
            'handover_info' => $handoverInfo,
        ]);

        $this->logActivity('handover_completed', [
            'post_id' => $this->security_post_id,
            'notes' => $notes,
            'completed_by' => $completedBy ?? Auth::id(),
        ]);

        // Clear cache
        $this->clearCache();

        return $this;
    }

    /**
     * Update handover checklist (partial updates)
     */
    public function updateHandoverChecklist($checklistUpdates)
    {
        if (!$this->handover_info || !isset($this->handover_info['checklist_items'])) {
            return $this;
        }

        $handoverInfo = $this->handover_info;
        $checklistItems = $handoverInfo['checklist_items'];
        
        foreach ($checklistUpdates as $index => $update) {
            if (isset($checklistItems[$index])) {
                $checklistItems[$index]['completed'] = $update['completed'] ?? false;
                $checklistItems[$index]['completed_by'] = Auth::id();
                $checklistItems[$index]['completed_at'] = now();
                $checklistItems[$index]['notes'] = $update['notes'] ?? null;
            }
        }
        
        $handoverInfo['checklist_items'] = $checklistItems;
        $handoverInfo['checklist_last_updated_at'] = now();
        $handoverInfo['checklist_last_updated_by'] = Auth::id();
        
        $this->update(['handover_info' => $handoverInfo]);
        
        return $this;
    }

    // =============================================
    // ROTATION METHODS
    // =============================================
    
    /**
     * Apply a rotation swap to this schedule
     */
    public function applyRotation($newUserId, $pattern = self::ROTATION_PATTERN_FULL_SWAP, $metadata = [])
    {
        if (!$this->isEligibleForRotation()) {
            throw new \Exception('This schedule is not eligible for rotation.');
        }

        $oldUserId = $this->security_user_id;
        
        // Get rotation history
        $rotationHistory = $this->rotation_history ?? [];
        
        // Add to history
        $rotationHistory[] = [
            'old_user_id' => $oldUserId,
            'new_user_id' => $newUserId,
            'pattern' => $pattern,
            'rotated_at' => now(),
            'rotated_by' => Auth::id(),
            'metadata' => $metadata,
            'rotation_date' => $this->assignment_date->format('Y-m-d'),
            'shift_id' => $this->security_shift_id,
            'shift_category' => $this->shift?->category,
        ];
        
        // Update the schedule
        $this->update([
            'security_user_id' => $newUserId,
            'rotated_from_user_id' => $oldUserId,
            'is_rotated' => true,
            'rotated_at' => now(),
            'rotation_swap_count' => ($this->rotation_swap_count ?? 0) + 1,
            'rotation_history' => $rotationHistory,
            'rotation_data' => array_merge(
                $this->rotation_data ?? [],
                [
                    'last_rotation' => now(),
                    'rotation_pattern' => $pattern,
                    'previous_user' => $oldUserId,
                    'current_user' => $newUserId,
                    'swap_count' => ($this->rotation_swap_count ?? 0) + 1,
                    'rotated_from' => $oldUserId,
                    'rotated_at' => now(),
                    'rotation_type' => $metadata['rotation_type'] ?? 'shift_group',
                    'previous_shift' => $this->security_shift_id,
                    'new_shift' => $this->security_shift_id,
                    'rotation_group' => $metadata['rotation_group'] ?? 'day_night_swap',
                ]
            ),
        ]);
        
        // Log the rotation
        $this->logActivity('rotation_applied', [
            'old_user_id' => $oldUserId,
            'new_user_id' => $newUserId,
            'pattern' => $pattern,
            'swap_count' => $this->rotation_swap_count,
        ]);

        // Clear cache
        $this->clearCache();
        
        return $this;
    }
    
    /**
     * Revert the last rotation
     */
    public function revertLastRotation()
    {
        $rotationHistory = $this->rotation_history ?? [];
        
        if (empty($rotationHistory)) {
            throw new \Exception('No rotation history to revert.');
        }
        
        $lastRotation = array_pop($rotationHistory);
        
        $this->update([
            'security_user_id' => $lastRotation['old_user_id'],
            'rotated_from_user_id' => null,
            'rotated_at' => null,
            'rotation_swap_count' => max(0, ($this->rotation_swap_count ?? 1) - 1),
            'rotation_history' => $rotationHistory,
        ]);
        
        $this->logActivity('rotation_reverted', [
            'reverted_rotation' => $lastRotation,
        ]);

        // Clear cache
        $this->clearCache();
        
        return $this;
    }
    
    /**
     * Set the rotation group for this schedule
     */
    public function setRotationGroup($groupId, $groupType = null)
    {
        $data = [
            'rotation_group_id' => $groupId,
        ];
        
        if ($groupType) {
            $data['rotation_group_type'] = $groupType;
        }
        
        $this->update($data);
        
        // Clear cache
        $this->clearCache();
        
        return $this;
    }
    
    /**
     * Set rotation cycle dates
     */
    public function setRotationCycle($startDate, $endDate)
    {
        $this->update([
            'rotation_cycle_start_date' => $startDate,
            'rotation_cycle_end_date' => $endDate,
        ]);
        
        // Clear cache
        $this->clearCache();
        
        return $this;
    }
    
    /**
     * Update rotation preference score based on user preferences
     */
    public function updatePreferenceScore()
    {
        if (!$this->securityUser) {
            return $this;
        }
        
        $user = $this->securityUser;
        $shift = $this->shift;
        
        if (!$shift) {
            return $this;
        }
        
        // Base score
        $score = 5;
        
        // Check user preferences if they exist
        $preferences = $user->preferences ?? [];
        
        // Adjust based on shift category preference
        if (isset($preferences['preferred_shifts']) && is_array($preferences['preferred_shifts'])) {
            if (in_array($shift->category, $preferences['preferred_shifts'])) {
                $score += 3;
            }
        }
        
        // Adjust based on post preference
        if (isset($preferences['preferred_posts']) && is_array($preferences['preferred_posts'])) {
            if (in_array($this->security_post_id, $preferences['preferred_posts'])) {
                $score += 2;
            }
        }
        
        // Adjust based on rotation willingness
        if (isset($preferences['willing_to_rotate'])) {
            if ($preferences['willing_to_rotate'] === true) {
                $score += 2;
            } elseif ($preferences['willing_to_rotate'] === false) {
                $score -= 3;
            }
        }
        
        // Specific rotation direction preferences
        if (isset($preferences['willing_to_rotate']['day_to_night']) && $shift->category === 'night') {
            $score += $preferences['willing_to_rotate']['day_to_night'] ? 3 : -2;
        }
        
        if (isset($preferences['willing_to_rotate']['night_to_day']) && $shift->category === 'day') {
            $score += $preferences['willing_to_rotate']['night_to_day'] ? 3 : -2;
        }
        
        if ($shift->category === 'night' && isset($preferences['prefers_night_shift'])) {
            $score += $preferences['prefers_night_shift'] ? 3 : -2;
        }
        
        if ($shift->category === 'day' && isset($preferences['prefers_day_shift'])) {
            $score += $preferences['prefers_day_shift'] ? 3 : -2;
        }
        
        // Ensure score is between 1-10
        $score = max(1, min(10, $score));
        
        $this->update(['rotation_preference_score' => $score]);
        
        // Clear cache
        $this->clearCache();
        
        return $this;
    }
    
    /**
     * Check if this schedule is eligible for rotation
     */
    public function isEligibleForRotation(): bool
    {
        // Can't rotate completed, absent, or cancelled schedules
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_ABSENT, self::STATUS_CANCELLED])) {
            return false;
        }
        
        // Can't rotate if already in the past
        if ($this->assignment_date->isPast() && $this->status !== self::STATUS_SCHEDULED) {
            return false;
        }
        
        // Can't rotate if user has already checked in
        if ($this->checkin_time) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if this schedule is a day shift
     */
    public function isDayShift(): bool
    {
        return $this->shift && $this->shift->category === 'day';
    }
    
    /**
     * Check if this schedule is a night shift
     */
    public function isNightShift(): bool
    {
        return $this->shift && $this->shift->category === 'night';
    }
    
    /**
     * Check if this schedule is an evening shift
     */
    public function isEveningShift(): bool
    {
        return $this->shift && $this->shift->category === 'evening';
    }
    
    /**
     * Get the rotation history with user names
     */
    public function getRotationHistoryWithNames(): array
    {
        $history = $this->rotation_history ?? [];
        
        foreach ($history as &$entry) {
            $oldUser = User::withTrashed()->find($entry['old_user_id'] ?? null);
            $newUser = User::withTrashed()->find($entry['new_user_id'] ?? null);
            
            $entry['old_user_name'] = $oldUser ? $oldUser->name : 'Unknown';
            $entry['new_user_name'] = $newUser ? $newUser->name : 'Unknown';
            $entry['old_user_badge'] = $oldUser ? $oldUser->badge_number : null;
            $entry['new_user_badge'] = $newUser ? $newUser->badge_number : null;
            $entry['formatted_date'] = isset($entry['rotated_at']) 
                ? Carbon::parse($entry['rotated_at'])->format('M j, Y H:i') 
                : 'Unknown';
        }
        
        return $history;
    }
    
    /**
     * Get the current rotation status
     */
    public function getRotationStatus(): array
    {
        $rotationData = $this->rotation_data ?? [];
        $history = $this->rotation_history ?? [];
        
        return [
            'is_rotated' => $this->is_rotated,
            'swap_count' => $this->rotation_swap_count,
            'original_user_id' => $this->rotated_from_user_id,
            'original_user_name' => $this->rotatedFromUser?->name,
            'original_user_badge' => $this->rotatedFromUser?->badge_number,
            'current_user_id' => $this->security_user_id,
            'current_user_name' => $this->securityUser?->name,
            'current_user_badge' => $this->securityUser?->badge_number,
            'last_rotation_at' => $this->rotated_at,
            'last_rotation_formatted' => $this->rotated_at?->format('M j, Y H:i'),
            'rotation_group_type' => $this->rotation_group_type,
            'rotation_group_id' => $this->rotation_group_id,
            'in_rotation_cycle' => $this->rotation_cycle_start_date && 
                                   $this->rotation_cycle_end_date &&
                                   now()->between($this->rotation_cycle_start_date, $this->rotation_cycle_end_date),
            'cycle_start' => $this->rotation_cycle_start_date?->format('Y-m-d'),
            'cycle_end' => $this->rotation_cycle_end_date?->format('Y-m-d'),
            'preference_score' => $this->rotation_preference_score,
            'rotation_history_count' => count($history),
            'eligible_for_rotation' => $this->isEligibleForRotation(),
            'rotation_data' => $rotationData,
            // Additional fields for controller
            'sequence_type' => $rotationData['sequence_type'] ?? 'morning_evening',
            'current_position' => $rotationData['current_position'] ?? 'morning',
            'next_position' => $rotationData['next_position'] ?? 'evening',
            'next_rotation_date' => $rotationData['next_rotation_date'] ?? null,
            'rotation_days' => $rotationData['rotation_days'] ?? 7,
            'user_rotation_history' => $rotationData['user_rotation_history'] ?? [],
        ];
    }
    
    /**
     * Get the rotation summary for display
     */
    public function getRotationSummary(): string
    {
        if (!$this->is_rotated) {
            return 'No rotation applied';
        }
        
        $parts = [];
        
        if ($this->rotation_swap_count > 0) {
            $parts[] = "Swapped {$this->rotation_swap_count} time(s)";
        }
        
        if ($this->rotated_from_user_id && $this->rotatedFromUser) {
            $parts[] = "Originally: {$this->rotatedFromUser->name}";
        }
        
        if ($this->rotated_at) {
            $parts[] = "Last: " . $this->rotated_at->format('M j');
        }
        
        if ($this->rotation_group_type) {
            $parts[] = "Group: " . ucfirst($this->rotation_group_type);
        }
        
        return implode(' | ', $parts);
    }
    
    /**
     * Check if this schedule is in a specific rotation cycle
     */
    public function isInRotationCycle($date = null): bool
    {
        $date = $date ?? now();
        
        return $this->rotation_cycle_start_date && 
               $this->rotation_cycle_end_date &&
               $date->between($this->rotation_cycle_start_date, $this->rotation_cycle_end_date);
    }
    
    /**
     * Get the rotation cycle progress percentage
     */
    public function getRotationCycleProgress(): ?float
    {
        if (!$this->rotation_cycle_start_date || !$this->rotation_cycle_end_date) {
            return null;
        }
        
        $total = $this->rotation_cycle_start_date->diffInDays($this->rotation_cycle_end_date);
        $elapsed = $this->rotation_cycle_start_date->diffInDays(now());
        
        if ($total <= 0) {
            return 100;
        }
        
        $progress = ($elapsed / $total) * 100;
        return min(100, max(0, round($progress, 1)));
    }

    // =============================================
    // CALCULATION METHODS
    // =============================================
    
    /**
 * Calculate late minutes
 */
public function calculateLateMinutes()
{
    if (!$this->checkin_time || !$this->shift) {
        return 0;
    }

    try {
        $shiftStart = $this->getScheduledStartTime();
        if (!$shiftStart) {
            return 0;
        }
        
        $checkinTime = $this->checkin_time instanceof Carbon 
            ? $this->checkin_time 
            : Carbon::parse($this->checkin_time);
        
        // Ensure both are Carbon instances
        if (!$checkinTime instanceof Carbon) {
            $checkinTime = Carbon::parse($checkinTime);
        }
        
        if ($checkinTime->greaterThan($shiftStart)) {
            $lateMinutes = $shiftStart->diffInMinutes($checkinTime);
            $this->update(['late_minutes' => $lateMinutes]);
            return $lateMinutes;
        }
        
        $this->update(['late_minutes' => 0]);
        return 0;
        
    } catch (\Exception $e) {
        Log::error('Error calculating late minutes: ' . $e->getMessage(), [
            'schedule_id' => $this->id,
            'trace' => $e->getTraceAsString()
        ]);
        return 0;
    }
}

/**
 * Calculate overtime minutes
 */
public function calculateOvertimeMinutes()
{
    if (!$this->checkout_time || !$this->shift) {
        return 0;
    }

    try {
        $shiftEnd = $this->getScheduledEndTime();
        if (!$shiftEnd) {
            return 0;
        }
        
        $checkoutTime = $this->checkout_time instanceof Carbon 
            ? $this->checkout_time 
            : Carbon::parse($this->checkout_time);
        
        // Ensure both are Carbon instances
        if (!$checkoutTime instanceof Carbon) {
            $checkoutTime = Carbon::parse($checkoutTime);
        }
        
        if ($checkoutTime->greaterThan($shiftEnd)) {
            $overtimeMinutes = $shiftEnd->diffInMinutes($checkoutTime);
            $this->update(['overtime_minutes' => $overtimeMinutes]);
            return $overtimeMinutes;
        }
        
        $this->update(['overtime_minutes' => 0]);
        return 0;
        
    } catch (\Exception $e) {
        Log::error('Error calculating overtime minutes: ' . $e->getMessage(), [
            'schedule_id' => $this->id,
            'trace' => $e->getTraceAsString()
        ]);
        return 0;
    }
}

    /**
     * Get shift duration in minutes
     */
    public function getDuration(): ?int
    {
        try {
            if (!$this->checkin_time || !$this->checkout_time) {
                return null;
            }

            $checkin = $this->checkin_time instanceof Carbon 
                ? $this->checkin_time 
                : Carbon::parse($this->checkin_time);
            
            $checkout = $this->checkout_time instanceof Carbon 
                ? $this->checkout_time 
                : Carbon::parse($this->checkout_time);

            if ($checkout->lessThan($checkin)) {
                Log::warning('Checkout time is before checkin time', [
                    'schedule_id' => $this->id,
                    'checkin' => $checkin->toDateTimeString(),
                    'checkout' => $checkout->toDateTimeString()
                ]);
                return 0;
            }

            return $checkin->diffInMinutes($checkout);
            
        } catch (\Exception $e) {
            Log::error('Error calculating duration: ' . $e->getMessage(), [
                'schedule_id' => $this->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get duration formatted (e.g., "2h 30m")
     */
    public function getDurationFormatted(): string
    {
        try {
            $minutes = $this->getDuration();
            
            if ($minutes === null) {
                return 'N/A';
            }
            
            if ($minutes <= 0) {
                return '0m';
            }

            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;
            
            $parts = [];
            if ($hours > 0) {
                $parts[] = "{$hours}h";
            }
            if ($mins > 0) {
                $parts[] = "{$mins}m";
            }
            
            return implode(' ', $parts) ?: '0m';
            
        } catch (\Exception $e) {
            return 'Error';
        }
    }

    /**
     * Get duration in hours
     */
    public function getDurationInHours(): ?float
    {
        $minutes = $this->getDuration();
        
        if ($minutes === null) {
            return null;
        }
        
        return round($minutes / 60, 2);
    }

    /**
     * Get working duration (excluding breaks)
     */
    public function getWorkingDuration(): ?int
    {
        $totalMinutes = $this->getDuration();
        
        if ($totalMinutes === null) {
            return null;
        }
        
        // Subtract break time
        $breakMinutes = $this->break_duration ?? 0;
        $breakHistory = $this->break_history ?? [];
        
        foreach ($breakHistory as $break) {
            $breakMinutes += $break['duration'] ?? 0;
        }
        
        return max(0, $totalMinutes - $breakMinutes);
    }

    // =============================================
    // STATUS CHECK METHODS
    // =============================================
    
    /**
     * Check if checked in
     */
    public function isCheckedIn(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->checkin_time;
    }

    /**
     * Check if checkin is overdue
     */
    public function isOverdueCheckin(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }

        try {
            $shiftStart = $this->getScheduledStartTime();
            if (!$shiftStart) {
                return false;
            }

            return now()->greaterThan($shiftStart->copy()->addMinutes(30)); // 30 minutes grace period
            
        } catch (\Exception $e) {
            Log::error('Error checking overdue checkin: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if this is the current shift
     */
    public function isCurrentShift(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        try {
            $now = now();
            $shiftStart = $this->getScheduledStartTime();
            $shiftEnd = $this->getScheduledEndTime();
            
            if (!$shiftStart || !$shiftEnd) {
                return false;
            }

            return $now->between($shiftStart, $shiftEnd);
            
        } catch (\Exception $e) {
            Log::error('Error checking current shift: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if personnel can check in
     */
    public function canCheckin(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }

        try {
            $earliestCheckin = $this->getEarliestCheckinTime();
            if (!$earliestCheckin) {
                return true;
            }
            
            return now()->greaterThanOrEqualTo($earliestCheckin) && !$this->isOverdueCheckin();
            
        } catch (\Exception $e) {
            Log::error('Error checking canCheckin: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if personnel can check out
     */
    public function canCheckout(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if can start a break
     */
    public function canStartBreak(): bool
    {
        return $this->status === self::STATUS_ACTIVE && 
               $this->include_breaks && 
               $this->break_status !== self::BREAK_STATUS_ACTIVE &&
               $this->shift && $this->shift->hasBreaks();
    }

    /**
     * Check if can end the current break
     */
    public function canEndBreak(): bool
    {
        return $this->break_status === self::BREAK_STATUS_ACTIVE;
    }

    /**
     * Check if can complete handover
     */
    public function canCompleteHandover(): bool
    {
        return $this->handover_info && 
               !$this->handover_completed && 
               $this->status === self::STATUS_SCHEDULED;
    }

    /**
     * Check if personnel was on time
     */
    public function wasOnTime(): bool
    {
        return $this->late_minutes === 0 || $this->late_minutes <= 5; // 5 minutes grace
    }

    /**
     * Check if has overtime
     */
    public function hasOvertime(): bool
    {
        return $this->overtime_minutes > 0;
    }

    /**
     * Check if is approved
     */
    public function isApproved(): bool
    {
        return $this->is_approved === true;
    }

    // =============================================
    // FORMATTING METHODS
    // =============================================
    
    /**
     * Get check-in time formatted
     */
    public function getCheckinTimeFormatted(): string
    {
        return $this->checkin_time ? $this->checkin_time->format('h:i A') : 'N/A';
    }

    /**
     * Get check-out time formatted
     */
    public function getCheckoutTimeFormatted(): string
    {
        return $this->checkout_time ? $this->checkout_time->format('h:i A') : 'N/A';
    }

    /**
     * Get assignment date formatted
     */
    public function getAssignmentDateFormatted(): string
    {
        return $this->assignment_date->format('D, M j, Y');
    }

    /**
     * Get shift time range
     */
    public function getShiftTimeRange(): string
    {
        if (!$this->shift) {
            return 'N/A';
        }
        
        try {
            return $this->shift->getTimeRange();
        } catch (\Exception $e) {
            return 'Invalid time';
        }
    }

    /**
     * Get shift with handover range
     */
    public function getShiftWithHandoverRange(): string
    {
        if (!$this->shift) {
            return 'N/A';
        }
        
        try {
            $start = Carbon::parse($this->shift->start_time)->format('h:i A');
            $end = Carbon::parse($this->shift->end_time)->format('h:i A');
            
            if ($this->handover_info && isset($this->handover_info['handover_duration'])) {
                $handoverMinutes = $this->handover_info['handover_duration'];
                $endTime = Carbon::parse($this->shift->end_time)->addMinutes($handoverMinutes);
                $end = $endTime->format('h:i A') . " (incl. {$handoverMinutes} min handover)";
            }
            
            return "{$start} - {$end}";
        } catch (\Exception $e) {
            return 'Invalid time';
        }
    }

    /**
     * Check if late check-in
     */
    public function isLateCheckin(): bool
    {
        return $this->late_minutes > 0;
    }

    /**
     * Get late minutes formatted
     */
    public function getLateMinutesFormatted(): string
    {
        if ($this->late_minutes <= 0) {
            return 'On time';
        }

        $hours = intdiv($this->late_minutes, 60);
        $mins = $this->late_minutes % 60;
        
        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($mins > 0) {
            $parts[] = "{$mins}m";
        }
        
        return implode(' ', $parts) . ' late';
    }

    /**
     * Get overtime formatted
     */
    public function getOvertimeFormatted(): string
    {
        if ($this->overtime_minutes <= 0) {
            return 'No overtime';
        }

        $hours = intdiv($this->overtime_minutes, 60);
        $mins = $this->overtime_minutes % 60;
        
        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($mins > 0) {
            $parts[] = "{$mins}m";
        }
        
        return implode(' ', $parts) . ' overtime';
    }

    /**
     * Get status with color for display
     */
    public function getStatusWithColor(): array
    {
        $statusMap = [
            self::STATUS_SCHEDULED => ['label' => 'Scheduled', 'color' => 'secondary', 'icon' => 'clock', 'badge' => 'badge bg-secondary'],
            self::STATUS_ACTIVE => ['label' => 'On Duty', 'color' => 'success', 'icon' => 'user-check', 'badge' => 'badge bg-success'],
            self::STATUS_COMPLETED => ['label' => 'Completed', 'color' => 'info', 'icon' => 'check-circle', 'badge' => 'badge bg-info'],
            self::STATUS_ABSENT => ['label' => 'Absent', 'color' => 'danger', 'icon' => 'user-times', 'badge' => 'badge bg-danger'],
            self::STATUS_CANCELLED => ['label' => 'Cancelled', 'color' => 'warning', 'icon' => 'ban', 'badge' => 'badge bg-warning'],
        ];

        $status = $statusMap[$this->status] ?? ['label' => 'Unknown', 'color' => 'secondary', 'icon' => 'question', 'badge' => 'badge bg-secondary'];
        
        // Add special indicators
        if ($this->isLateCheckin()) {
            $status['label'] .= ' (Late)';
            $status['color'] = 'warning';
            $status['badge'] = 'badge bg-warning';
        }
        
        if ($this->hasOvertime()) {
            $status['label'] .= ' (Overtime)';
        }
        
        if ($this->break_status === self::BREAK_STATUS_ACTIVE) {
            $status['label'] .= ' (On Break)';
            $status['color'] = 'primary';
            $status['badge'] = 'badge bg-primary';
        }

        return $status;
    }

    // =============================================
    // LOCATION METHODS
    // =============================================
    
    /**
     * Get check-in coordinates
     */
    public function getCheckinCoordinates(): ?array
    {
        if (!$this->checkin_location || !is_array($this->checkin_location)) {
            return null;
        }

        $lat = $this->checkin_location['lat'] ?? null;
        $lng = $this->checkin_location['lng'] ?? null;

        if ($lat && $lng) {
            return ['lat' => (float) $lat, 'lng' => (float) $lng];
        }

        return null;
    }

    /**
     * Get check-out coordinates
     */
    public function getCheckoutCoordinates(): ?array
    {
        if (!$this->checkout_location || !is_array($this->checkout_location)) {
            return null;
        }

        $lat = $this->checkout_location['lat'] ?? null;
        $lng = $this->checkout_location['lng'] ?? null;

        if ($lat && $lng) {
            return ['lat' => (float) $lat, 'lng' => (float) $lng];
        }

        return null;
    }

    /**
     * Get Google Maps link for check-in location
     */
    public function getCheckinMapsLink(): ?string
    {
        $coords = $this->getCheckinCoordinates();
        
        if (!$coords) {
            return null;
        }

        return "https://www.google.com/maps?q={$coords['lat']},{$coords['lng']}";
    }

    /**
     * Get Google Maps link for check-out location
     */
    public function getCheckoutMapsLink(): ?string
    {
        $coords = $this->getCheckoutCoordinates();
        
        if (!$coords) {
            return null;
        }

        return "https://www.google.com/maps?q={$coords['lat']},{$coords['lng']}";
    }

    // =============================================
    // SUMMARY METHODS
    // =============================================
    
    /**
     * Get break summary
     */
    public function getBreakSummary(): string
    {
        if (!$this->include_breaks) {
            return 'No breaks scheduled';
        }

        if ($this->break_history && count($this->break_history) > 0) {
            $totalBreakMinutes = array_sum(array_column($this->break_history, 'duration'));
            return count($this->break_history) . ' break(s) taken, total: ' . 
                   floor($totalBreakMinutes / 60) . 'h ' . ($totalBreakMinutes % 60) . 'm';
        }

        if ($this->break_status === self::BREAK_STATUS_ACTIVE) {
            $activeMinutes = now()->diffInMinutes($this->break_start_time);
            return "Currently on break ({$activeMinutes}m)";
        }

        return 'Breaks scheduled but not taken';
    }

    /**
     * Get handover summary
     */
    public function getHandoverSummary(): array
    {
        if (!$this->handover_info) {
            return [
                'has_handover' => false,
                'summary' => 'No handover required',
            ];
        }

        $info = $this->handover_info;
        $summary = [
            'has_handover' => true,
            'completed' => $this->handover_completed,
            'scheduled_time' => ($info['handover_start'] ?? 'N/A') . ' - ' . ($info['handover_end'] ?? 'N/A'),
            'handover_start' => $info['handover_start'] ?? null,
            'handover_end' => $info['handover_end'] ?? null,
            'duration' => $info['handover_duration'] ?? 0,
            'handover_window' => $info['handover_window'] ?? null,
            'notes_required' => $info['notes_required'] ?? false,
            'checklist' => $info['checklist'] ?? [],
            'checklist_items' => $info['checklist_items'] ?? [],
            'completed_at' => $this->handover_completed_at,
            'completed_by' => $this->handover_completed_by,
            'notes' => $this->handover_notes,
        ];

        // Calculate checklist completion
        if ($summary['checklist_items']) {
            $completedItems = array_filter($summary['checklist_items'], function($item) {
                return $item['completed'] ?? false;
            });
            $summary['checklist_completed'] = count($completedItems);
            $summary['checklist_total'] = count($summary['checklist_items']);
            $summary['checklist_progress'] = $summary['checklist_total'] > 0 
                ? round(($summary['checklist_completed'] / $summary['checklist_total']) * 100, 0)
                : 100;
        }

        return $summary;
    }

    /**
     * Get rotation info
     */
    public function getRotationInfo(): array
    {
        if (!$this->rotation_data && !$this->is_rotated) {
            return [
                'is_rotating' => false,
                'message' => 'Fixed shift',
            ];
        }

        $data = $this->rotation_data ?? [];
        return [
            'is_rotating' => true,
            'sequence_type' => $data['sequence_type'] ?? 'unknown',
            'current_position' => $data['current_position'] ?? 'unknown',
            'next_position' => $data['next_position'] ?? 'unknown',
            'next_rotation_date' => $data['next_rotation_date'] ?? null,
            'rotation_days' => $data['rotation_days'] ?? 7,
            'user_rotation_history' => $data['user_rotation_history'] ?? [],
            
            // New fields
            'rotation_group_id' => $this->rotation_group_id,
            'rotation_group_type' => $this->rotation_group_type,
            'rotation_sequence_number' => $this->rotation_sequence_number,
            'rotation_cycle_start' => $this->rotation_cycle_start_date?->format('Y-m-d'),
            'rotation_cycle_end' => $this->rotation_cycle_end_date?->format('Y-m-d'),
            'rotation_swap_count' => $this->rotation_swap_count,
            'rotation_preference_score' => $this->rotation_preference_score,
            'is_rotated' => $this->is_rotated,
            'rotated_from_user_id' => $this->rotated_from_user_id,
            'rotated_from_user_name' => $this->rotatedFromUser?->name,
            'rotated_from_user_badge' => $this->rotatedFromUser?->badge_number,
            'rotated_at' => $this->rotated_at,
            'rotated_at_formatted' => $this->rotated_at?->format('Y-m-d H:i'),
            'rotation_history' => $this->getRotationHistoryWithNames(),
            'rotation_summary' => $this->getRotationSummary(),
            'cycle_progress' => $this->getRotationCycleProgress(),
            
            // Fields matching controller's prepareRotationDataOptimized
            'rotated_from' => $data['rotated_from'] ?? null,
            'rotated_at' => $data['rotated_at'] ?? null,
            'rotation_type' => $data['rotation_type'] ?? null,
            'previous_shift' => $data['previous_shift'] ?? null,
            'new_shift' => $data['new_shift'] ?? null,
            'rotation_group' => $data['rotation_group'] ?? null,
        ];
    }

    /**
     * Get approval info
     */
    public function getApprovalInfo(): array
    {
        return [
            'is_approved' => $this->is_approved,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'approver_name' => $this->approvedBy ? $this->approvedBy->name : null,
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'punctuality' => [
                'late_minutes' => $this->late_minutes,
                'was_on_time' => $this->wasOnTime(),
                'late_formatted' => $this->getLateMinutesFormatted(),
            ],
            'overtime' => [
                'overtime_minutes' => $this->overtime_minutes,
                'has_overtime' => $this->hasOvertime(),
                'overtime_formatted' => $this->getOvertimeFormatted(),
            ],
            'duration' => [
                'total_minutes' => $this->getDuration(),
                'working_minutes' => $this->getWorkingDuration(),
                'break_minutes' => $this->break_duration + array_sum(array_column($this->break_history ?? [], 'duration')),
                'total_formatted' => $this->getDurationFormatted(),
            ],
            'attendance' => [
                'status' => $this->status,
                'checked_in' => $this->isCheckedIn(),
                'completed' => $this->status === self::STATUS_COMPLETED,
            ],
            'rotation' => [
                'swap_count' => $this->rotation_swap_count,
                'is_rotated' => $this->is_rotated,
                'preference_score' => $this->rotation_preference_score,
            ],
        ];
    }

    /**
     * Get complete schedule summary
     */
    public function getScheduleSummary(): array
    {
        return [
            'id' => $this->id,
            'post' => [
                'id' => $this->post->id ?? null,
                'name' => $this->post->name ?? 'Unknown',
                'code' => $this->post->code ?? null,
            ],
            'shift' => [
                'id' => $this->shift->id ?? null,
                'name' => $this->shift->name ?? 'Unknown',
                'category' => $this->shift->category ?? 'unknown',
                'time_range' => $this->getShiftTimeRange(),
            ],
            'personnel' => [
                'id' => $this->securityUser->id ?? null,
                'name' => $this->securityUser->name ?? 'Unknown',
                'phone' => $this->securityUser->phone ?? null,
                'badge_number' => $this->securityUser->badge_number ?? null,
                'original_user_id' => $this->rotated_from_user_id,
                'original_user_name' => $this->rotatedFromUser?->name,
                'original_user_badge' => $this->rotatedFromUser?->badge_number,
            ],
            'schedule' => [
                'date' => $this->getAssignmentDateFormatted(),
                'status' => $this->getStatusWithColor(),
                'checkin_time' => $this->getCheckinTimeFormatted(),
                'checkout_time' => $this->getCheckoutTimeFormatted(),
                'duration' => $this->getDurationFormatted(),
            ],
            'handover' => $this->getHandoverSummary(),
            'rotation' => $this->getRotationInfo(),
            'performance' => $this->getPerformanceMetrics(),
            'approval' => $this->getApprovalInfo(),
        ];
    }

    /**
     * Get status information with color and icon
     */
    public function getStatusInfo(): array
    {
        $statusMap = [
            self::STATUS_SCHEDULED => [
                'label' => 'Scheduled',
                'color' => 'var(--info)',
                'bg' => 'rgba(var(--info-rgb), 0.1)',
                'icon' => 'clock'
            ],
            self::STATUS_ACTIVE => [
                'label' => 'Active',
                'color' => 'var(--success)',
                'bg' => 'rgba(var(--success-rgb), 0.1)',
                'icon' => 'play-circle'
            ],
            self::STATUS_COMPLETED => [
                'label' => 'Completed',
                'color' => 'var(--success)',
                'bg' => 'rgba(var(--success-rgb), 0.1)',
                'icon' => 'check-circle'
            ],
            self::STATUS_ABSENT => [
                'label' => 'Absent',
                'color' => 'var(--danger)',
                'bg' => 'rgba(var(--danger-rgb), 0.1)',
                'icon' => 'user-slash'
            ],
            self::STATUS_CANCELLED => [
                'label' => 'Cancelled',
                'color' => 'var(--warning)',
                'bg' => 'rgba(var(--warning-rgb), 0.1)',
                'icon' => 'ban'
            ]
        ];
        
        return $statusMap[$this->status] ?? [
            'label' => ucfirst($this->status),
            'color' => 'var(--text-secondary)',
            'bg' => 'rgba(var(--secondary-rgb), 0.1)',
            'icon' => 'circle'
        ];
    }

    // =============================================
    // CACHE MANAGEMENT
    // =============================================
    
    /**
     * Clear cache related to this schedule
     */
    protected function clearCache(): void
    {
        try {
            $cacheKeys = [
                "handover_{$this->security_post_id}_{$this->assignment_date->format('Y-m-d')}_{$this->security_shift_id}",
                "rotation_history_{$this->security_user_id}_{$this->security_shift_id}",
                "shift_{$this->security_shift_id}",
                "post_capacity_{$this->security_post_id}_{$this->assignment_date->format('Y-m-d')}",
                "availability_{$this->security_user_id}_{$this->assignment_date->format('Y-m-d')}",
                "user_{$this->security_user_id}_preferences",
                "schedule_stats_" . today()->format('Y-m-d'),
            ];
            
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear schedule caches', [
                'schedule_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // =============================================
    // ACTIVITY LOGGING - FIXED VERSION
    // =============================================
    
    /**
     * Log activity for this schedule - FIXED to include description field
     */
    protected function logActivity($action, $metadata = [])
    {
        try {
            // Check if ActivityLog model exists and table exists
            if (class_exists('App\Models\ActivityLog') && Schema::hasTable('activity_logs')) {
                
                // Generate a meaningful description
                $description = $this->generateActivityDescription($action, $metadata);
                
                // Prepare the data
                $logData = [
                    'user_id' => Auth::check() ? Auth::id() : $this->security_user_id,
                    'description' => $description, // FIX: Added description field
                    'type' => 'schedule',
                    'action' => $action,
                    'model_type' => self::class,
                    'model_id' => $this->id,
                    'metadata' => json_encode(array_merge($metadata, [
                        'schedule_id' => $this->id,
                        'post_id' => $this->security_post_id,
                        'post_name' => $this->post->name ?? null,
                        'user_id' => $this->security_user_id,
                        'user_name' => $this->securityUser->name ?? null,
                        'shift_id' => $this->security_shift_id,
                        'shift_name' => $this->shift->name ?? null,
                        'assignment_date' => $this->assignment_date->format('Y-m-d'),
                        'rotation_swap_count' => $this->rotation_swap_count,
                        'is_rotated' => $this->is_rotated,
                    ])),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ];
                
                // Remove null values
                $logData = array_filter($logData, function($value) {
                    return !is_null($value);
                });
                
                \App\Models\ActivityLog::create($logData);
            }
            
            // Also log to regular log
            Log::info("Schedule {$action}", [
                'schedule_id' => $this->id,
                'user_id' => Auth::id(),
                'metadata' => $metadata
            ]);
            
        } catch (\Exception $e) {
            // Just log the error but don't throw it - we don't want to break the main operation
            Log::error('Failed to log activity: ' . $e->getMessage(), [
                'schedule_id' => $this->id,
                'action' => $action,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Generate activity description
     */
    protected function generateActivityDescription($action, $metadata = []): string
    {
        $userName = $this->securityUser->name ?? 'Unknown';
        $postName = $this->post->name ?? 'Unknown';
        $shiftName = $this->shift->name ?? 'Unknown';
        $date = $this->assignment_date ? $this->assignment_date->format('M j, Y') : 'Unknown date';
        
        $descriptions = [
            'created' => "Schedule created: {$userName} assigned to {$postName} ({$shiftName}) on {$date}",
            'updated' => "Schedule updated for {$userName} at {$postName} on {$date}",
            'deleted' => "Schedule deleted for {$userName} at {$postName} on {$date}",
            'force_deleted' => "Schedule permanently deleted for {$userName} at {$postName} on {$date}",
            'restored' => "Schedule restored for {$userName} at {$postName} on {$date}",
            'checkin' => "{$userName} checked in at {$postName} on {$date}" . ($this->late_minutes > 0 ? " (late by {$this->late_minutes} min)" : ""),
            'checkout' => "{$userName} checked out from {$postName} on {$date}" . ($this->overtime_minutes > 0 ? " (overtime: {$this->overtime_minutes} min)" : ""),
            'absent' => "{$userName} marked absent for {$postName} on {$date}" . (isset($metadata['reason']) ? ": {$metadata['reason']}" : ""),
            'cancelled' => "Schedule cancelled for {$userName} at {$postName} on {$date}" . (isset($metadata['reason']) ? ": {$metadata['reason']}" : ""),
            'approved' => "Schedule approved for {$userName} at {$postName} on {$date}",
            'unapproved' => "Schedule unapproved for {$userName} at {$postName} on {$date}",
            'marked_complete' => "Schedule marked complete for {$userName} at {$postName} on {$date}",
            'break_started' => "{$userName} started break at {$postName} on {$date}",
            'break_ended' => "{$userName} ended break at {$postName} on {$date} (duration: " . ($metadata['duration_minutes'] ?? '?') . " min)",
            'handover_completed' => "Handover completed for {$postName} ({$shiftName}) on {$date}",
            'rotation_applied' => "Rotation applied for {$userName} at {$postName} on {$date} (swap count: {$this->rotation_swap_count})",
            'rotation_reverted' => "Rotation reverted for {$userName} at {$postName} on {$date}",
        ];
        
        return $descriptions[$action] ?? "Schedule {$action} for {$userName} at {$postName} on {$date}";
    }

    // =============================================
    // EVENT HANDLERS
    // =============================================
    
    /**
     * The "booted" method of the model
     */
    protected static function booted()
    {
        static::creating(function ($schedule) {
            // Set assigned_by if not provided
            if (!$schedule->assigned_by && Auth::check()) {
                $schedule->assigned_by = Auth::id();
            }

            // Set default status
            if (!$schedule->status) {
                $schedule->status = self::STATUS_SCHEDULED;
            }

            // Set default rotation values
            if (!isset($schedule->rotation_swap_count)) {
                $schedule->rotation_swap_count = 0;
            }
            
            if (!isset($schedule->is_rotated)) {
                $schedule->is_rotated = false;
            }
            
            if (!isset($schedule->rotation_preference_score)) {
                $schedule->rotation_preference_score = 5;
            }

            // Validate shift applicability
            if ($schedule->shift && $schedule->assignment_date) {
                $dayOfWeek = Carbon::parse($schedule->assignment_date)->dayOfWeekIso;
                if (!$schedule->shift->isApplicableOnDay($dayOfWeek)) {
                    throw new \Exception('Selected shift is not applicable on the chosen date.');
                }
            }
            
            // Set rotation group type based on shift category if not set
            if (!$schedule->rotation_group_type && $schedule->shift) {
                $schedule->rotation_group_type = $schedule->shift->category;
            }
        });

        static::created(function ($schedule) {
            // Auto-calculate preference score
            $schedule->updatePreferenceScore();
            
            // Log creation
            $schedule->logActivity('created', [
                'post_id' => $schedule->security_post_id,
                'user_id' => $schedule->security_user_id,
                'shift_id' => $schedule->security_shift_id,
            ]);
        });

        static::updating(function ($schedule) {
            // Prevent certain status changes
            if ($schedule->isDirty('status')) {
                $oldStatus = $schedule->getOriginal('status');
                $newStatus = $schedule->status;
                
                // Can't change from completed/absent/cancelled
                if (in_array($oldStatus, [self::STATUS_COMPLETED, self::STATUS_ABSENT, self::STATUS_CANCELLED])) {
                    throw new \Exception("Cannot change status from {$oldStatus}");
                }
                
                // Can't change to scheduled from active/completed
                if ($newStatus === self::STATUS_SCHEDULED && in_array($oldStatus, [self::STATUS_ACTIVE, self::STATUS_COMPLETED])) {
                    throw new \Exception("Cannot change to scheduled from {$oldStatus}");
                }
            }

            // Auto-calculate duration when checking out
            if ($schedule->isDirty('status') && $schedule->status === self::STATUS_COMPLETED) {
                if ($schedule->checkout_time && !$schedule->checkin_time) {
                    $schedule->checkin_time = now(); // Auto-set checkin if missing
                }
                
                // Calculate overtime
                $schedule->calculateOvertimeMinutes();
            }
            
            // If shift category changes, update rotation group type
            if ($schedule->isDirty('security_shift_id') && $schedule->shift) {
                $schedule->rotation_group_type = $schedule->shift->category;
            }
        });
        
        static::updated(function ($schedule) {
            // If user changed and it wasn't a rotation, update rotation data
            if ($schedule->isDirty('security_user_id') && !$schedule->isDirty('rotated_from_user_id')) {
                // This was a manual reassignment, not a rotation
                $schedule->updatePreferenceScore();
            }
            
            // Log update
            $schedule->logActivity('updated', [
                'changes' => $schedule->getChanges()
            ]);
            
            // Clear cache
            $schedule->clearCache();
        });
        
        static::deleted(function ($schedule) {
            // Log deletion
            $schedule->logActivity('deleted', [
                'schedule_id' => $schedule->id
            ]);
            
            // Clear cache
            $schedule->clearCache();
        });
        
        static::restored(function ($schedule) {
            // Log restoration
            $schedule->logActivity('restored', []);
            
            // Clear cache
            $schedule->clearCache();
        });
        
        static::forceDeleted(function ($schedule) {
            // Log force deletion
            $schedule->logActivity('force_deleted', []);
            
            // Clear cache
            $schedule->clearCache();
        });
    }
}