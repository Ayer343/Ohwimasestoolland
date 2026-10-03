<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class SecurityShift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'category',
        'start_time',
        'end_time',
        'duration_hours',
        'is_overnight',
        'day_type',
        'applicable_days',
        'rotation_type',
        'rotation_config',
        'is_active',
        'required_personnel',
        'description',
        'break_schedule',
        'handover_config',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'applicable_days' => 'array',
        'break_schedule' => 'array',
        'handover_config' => 'array',
        'rotation_config' => 'array',
        'is_active' => 'boolean',
        'is_overnight' => 'boolean',
        'required_personnel' => 'integer',
        'duration_hours' => 'decimal:2',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_overnight' => false,
        'required_personnel' => 1,
        'rotation_type' => 'fixed',
        'category' => 'day',
        'day_type' => 'all_days',
    ];

    // Constants for better maintainability
    const CATEGORIES = [
        'day' => 'Day Shift (06:00 - 18:00)',
        'night' => 'Night Shift (18:00 - 06:00)',
        'evening' => 'Evening Shift (14:00 - 22:00)',
        'special' => 'Special Shift',
        'holiday' => 'Holiday Shift'
    ];

    const DAY_TYPES = [
        'all_days' => 'All Days',
        'weekday' => 'Weekdays',
        'weekend' => 'Weekends',
        'custom' => 'Custom Days'
    ];

    const ROTATION_TYPES = [
        'fixed' => 'Fixed (Same shift always)',
        'rotating' => 'Rotating (Changes periodically)'
    ];

    const ROTATION_SEQUENCES = [
        'morning_evening' => ['morning', 'evening', 'night', 'off'],
        'evening_morning' => ['evening', 'morning', 'night', 'off'],
        'night_morning' => ['night', 'morning', 'evening', 'off']
    ];

    // Relationships
    public function schedules()
    {
        return $this->hasMany(SecuritySchedule::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForToday($query)
    {
        $dayOfWeek = now()->dayOfWeekIso;
        return $this->scopeForDayOfWeek($query, $dayOfWeek);
    }

    public function scopeForDate($query, $date)
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
        return $this->scopeForDayOfWeek($query, $dayOfWeek);
    }

    public function scopeForDayOfWeek($query, $dayNumber)
    {
        return $query->where(function($q) use ($dayNumber) {
            $q->where('day_type', 'all_days')
              ->orWhere(function($q2) use ($dayNumber) {
                  $q2->where('day_type', 'weekday')
                     ->whereRaw('? between 1 and 5', [$dayNumber]);
              })
              ->orWhere(function($q2) use ($dayNumber) {
                  $q2->where('day_type', 'weekend')
                     ->whereRaw('? in (6, 7)', [$dayNumber]);
              })
              ->orWhere(function($q2) use ($dayNumber) {
                  $q2->where('day_type', 'custom')
                     ->whereJsonContains('applicable_days', $dayNumber);
              });
        });
    }

    public function scopeFixed($query)
    {
        return $query->where('rotation_type', 'fixed');
    }

    public function scopeRotating($query)
    {
        return $query->where('rotation_type', 'rotating');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeWithMinimumPersonnel($query, $count)
    {
        return $query->where('required_personnel', '>=', $count);
    }

    // Accessors & Mutators
    public function getStartTimeFormattedAttribute()
    {
        return Carbon::parse($this->start_time)->format('h:i A');
    }

    public function getEndTimeFormattedAttribute()
    {
        return Carbon::parse($this->end_time)->format('h:i A');
    }

    public function getDurationInMinutesAttribute()
    {
        return round($this->duration_hours * 60);
    }

    public function getStatusBadgeAttribute()
    {
        return $this->is_active 
            ? '<span class="px-2 py-1 bg-success/10 text-success rounded-full text-xs">Active</span>'
            : '<span class="px-2 py-1 bg-danger/10 text-danger rounded-full text-xs">Inactive</span>';
    }

    public function getCategoryLabelAttribute()
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function getRotationTypeLabelAttribute()
    {
        return self::ROTATION_TYPES[$this->rotation_type] ?? ucfirst($this->rotation_type);
    }

    // ============== FIX: Added getStatusWithColor() method ==============
    /**
     * Get status with color for display in views
     * This method is called from trash.blade.php to display status with appropriate colors
     *
     * @return array
     */
    public function getStatusWithColor()
    {
        if ($this->trashed()) {
            return [
                'status' => 'Deleted',
                'color' => 'danger',
                'icon' => 'fas fa-trash',
                'label' => 'Deleted',
                'badge_class' => 'badge-danger',
                'text_class' => 'text-danger'
            ];
        }
        
        if ($this->is_active) {
            return [
                'status' => 'Active',
                'color' => 'success',
                'icon' => 'fas fa-check-circle',
                'label' => 'Active',
                'badge_class' => 'badge-success',
                'text_class' => 'text-success'
            ];
        }
        
        return [
            'status' => 'Inactive',
            'color' => 'warning',
            'icon' => 'fas fa-pause-circle',
            'label' => 'Inactive',
            'badge_class' => 'badge-warning',
            'text_class' => 'text-warning'
        ];
    }

    /**
     * Get status color only (for badge classes)
     *
     * @return string
     */
    public function getStatusColor()
    {
        return $this->getStatusWithColor()['color'];
    }

    /**
     * Get status label only
     *
     * @return string
     */
    public function getStatusLabel()
    {
        return $this->getStatusWithColor()['label'];
    }

    /**
     * Get status badge HTML
     *
     * @return string
     */
    public function getStatusBadgeHtml()
    {
        $status = $this->getStatusWithColor();
        return "<span class='badge {$status['badge_class']} px-3 py-2'><i class='{$status['icon']} mr-1'></i> {$status['label']}</span>";
    }

    /**
     * Check if shift is soft deleted (alias for trashed)
     *
     * @return bool
     */
    public function isDeleted()
    {
        return !is_null($this->deleted_at);
    }
    // ============== END OF FIX ==============

    // Helper Methods
    public function isApplicableToday()
    {
        return $this->isApplicableOnDay(now()->dayOfWeekIso);
    }

    public function isApplicableOnDate($date)
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
        return $this->isApplicableOnDay($dayOfWeek);
    }

    public function isApplicableOnDay($dayNumber)
    {
        switch ($this->day_type) {
            case 'all_days':
                return true;
            case 'weekday':
                return $dayNumber >= 1 && $dayNumber <= 5;
            case 'weekend':
                return $dayNumber >= 6;
            case 'custom':
                return is_array($this->applicable_days) && in_array($dayNumber, $this->applicable_days);
            default:
                return false;
        }
    }

    public function getTimeRange($format = 'h:i A')
    {
        $start = Carbon::parse($this->start_time)->format($format);
        $end = Carbon::parse($this->end_time)->format($format);
        
        return "{$start} - {$end}";
    }

    public function hasBreaks()
    {
        return $this->break_schedule && 
               isset($this->break_schedule['has_break']) && 
               $this->break_schedule['has_break'] === true &&
               !empty($this->break_schedule['breaks']);
    }

    public function getBreaks()
    {
        if (!$this->hasBreaks()) {
            return [];
        }

        return $this->break_schedule['breaks'] ?? [];
    }

    public function getTotalBreakMinutes()
    {
        if (!$this->hasBreaks()) {
            return 0;
        }

        return $this->break_schedule['total_break_minutes'] ?? 
               array_sum(array_column($this->break_schedule['breaks'] ?? [], 'duration_minutes'));
    }

    public function getTotalBreakHours()
    {
        return round($this->getTotalBreakMinutes() / 60, 2);
    }

    public function getNetWorkingMinutes()
    {
        $totalMinutes = $this->duration_in_minutes;
        $breakMinutes = $this->getTotalBreakMinutes();
        
        return max(0, $totalMinutes - $breakMinutes);
    }

    public function getNetWorkingHours()
    {
        return round($this->getNetWorkingMinutes() / 60, 2);
    }

    public function hasHandover()
    {
        return $this->handover_config && 
               isset($this->handover_config['has_handover']) && 
               $this->handover_config['has_handover'] === true;
    }

    public function getHandoverDuration()
    {
        return $this->handover_config['handover_duration'] ?? 30;
    }

    public function getHandoverChecklist()
    {
        return $this->handover_config['handover_checklist'] ?? [];
    }

    public function isHandoverNotesRequired()
    {
        return $this->handover_config['handover_notes_required'] ?? true;
    }

    public function isRotating()
    {
        return $this->rotation_type === 'rotating';
    }

    public function getRotationSequence()
    {
        if (!$this->isRotating() || !$this->rotation_config) {
            return [];
        }

        $sequenceType = $this->rotation_config['rotation_sequence'] ?? 'morning_evening';
        return self::ROTATION_SEQUENCES[$sequenceType] ?? self::ROTATION_SEQUENCES['morning_evening'];
    }

    public function getCurrentShiftType()
    {
        if (!$this->isRotating() || !$this->rotation_config) {
            return null;
        }

        $sequence = $this->getRotationSequence();
        $currentIndex = $this->rotation_config['current_sequence_index'] ?? 0;
        
        return $sequence[$currentIndex] ?? null;
    }

    public function getNextRotationDate()
    {
        if (!$this->isRotating() || !$this->rotation_config) {
            return null;
        }

        $lastRotation = $this->rotation_config['last_rotation_date'] ?? null;
        $rotationDays = $this->rotation_config['rotation_days'] ?? 7;

        if (!$lastRotation) {
            return now()->addDays($rotationDays);
        }

        return Carbon::parse($lastRotation)->addDays($rotationDays);
    }

    public function getApplicableDaysList()
    {
        if ($this->day_type === 'custom' && is_array($this->applicable_days)) {
            $days = [];
            foreach ($this->applicable_days as $dayNumber) {
                $days[] = now()->setISODate(2024, 1, $dayNumber)->format('l');
            }
            return $days;
        }

        return [];
    }

    // Query builder helpers
    public function newQuery()
    {
        return parent::newQuery()->withCount('schedules');
    }

    // Event handlers
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shift) {
            // Calculate duration and overnight status
            $start = Carbon::parse($shift->start_time);
            $end = Carbon::parse($shift->end_time);
            
            if ($end <= $start) {
                $end->addDay();
                $shift->is_overnight = true;
            } else {
                $shift->is_overnight = false;
            }
            
            $shift->duration_hours = round($end->diffInMinutes($start) / 60, 2);
            
            // Set default rotation config if rotating
            if ($shift->rotation_type === 'rotating' && !$shift->rotation_config) {
                $shift->rotation_config = [
                    'rotation_days' => 7,
                    'rotation_sequence' => 'morning_evening',
                    'last_rotation_date' => null,
                    'current_sequence_index' => 0
                ];
            }
            
            // Set created_by if not set
            if (!$shift->created_by && auth()->check()) {
                $shift->created_by = auth()->id();
            }

            // Ensure JSON fields are null if not set
            if (!$shift->break_schedule) {
                $shift->break_schedule = null;
            }
            if (!$shift->handover_config) {
                $shift->handover_config = null;
            }
            if (!$shift->rotation_config && $shift->rotation_type !== 'rotating') {
                $shift->rotation_config = null;
            }
        });

        static::updating(function ($shift) {
            // Recalculate duration if times changed
            if ($shift->isDirty(['start_time', 'end_time'])) {
                $start = Carbon::parse($shift->start_time);
                $end = Carbon::parse($shift->end_time);
                
                if ($end <= $start) {
                    $end->addDay();
                    $shift->is_overnight = true;
                } else {
                    $shift->is_overnight = false;
                }
                
                $shift->duration_hours = round($end->diffInMinutes($start) / 60, 2);
            }

            // Set updated_by if available
            if (!$shift->isDirty('updated_by') && auth()->check()) {
                $shift->updated_by = auth()->id();
            }

            // Clean up JSON fields when switching from rotating to fixed
            if ($shift->isDirty('rotation_type') && $shift->rotation_type === 'fixed') {
                $shift->rotation_config = null;
            }
        });

        static::retrieved(function ($shift) {
            // Ensure JSON fields are properly formatted
            if ($shift->break_schedule === []) {
                $shift->break_schedule = null;
            }
            if ($shift->handover_config === []) {
                $shift->handover_config = null;
            }
            if ($shift->rotation_config === [] || ($shift->rotation_type === 'fixed' && $shift->rotation_config)) {
                $shift->rotation_config = null;
            }
        });
    }
}