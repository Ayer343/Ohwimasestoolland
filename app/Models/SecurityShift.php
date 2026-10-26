<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        // NOTE: start_time and end_time are intentionally NOT cast to datetime.
        // They are TIME columns and must round-trip as strings ("HH:MM").
        // Casting them to datetime breaks `Carbon::createFromFormat('H:i', ...)`
        // and mutates the stored value on save.
    ];

    protected $attributes = [
        'is_active' => true,
        'is_overnight' => false,
        'required_personnel' => 1,
        'rotation_type' => 'fixed',
        'category' => 'day',
        'day_type' => 'all_days',
    ];

    // ============================================================
    // Constants
    // ============================================================

    public const CATEGORIES = [
        'day' => 'Day Shift (06:00 - 18:00)',
        'night' => 'Night Shift (18:00 - 06:00)',
        'evening' => 'Evening Shift (14:00 - 22:00)',
        'special' => 'Special Shift',
        'holiday' => 'Holiday Shift',
    ];

    public const DAY_TYPES = [
        'all_days' => 'All Days',
        'weekday' => 'Weekdays',
        'weekend' => 'Weekends',
        'custom' => 'Custom Days',
    ];

    public const ROTATION_TYPES = [
        'fixed' => 'Fixed (Same shift always)',
        'rotating' => 'Rotating (Changes periodically)',
    ];

    public const ROTATION_SEQUENCES = [
        'morning_evening' => ['morning', 'evening', 'night', 'off'],
        'evening_morning' => ['evening', 'morning', 'night', 'off'],
        'night_morning' => ['night', 'morning', 'evening', 'off'],
    ];

    public const DAYS_OF_WEEK = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function schedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'security_shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

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
        return $query->forDayOfWeek(now()->dayOfWeekIso);
    }

    public function scopeForDate($query, $date)
    {
        return $query->forDayOfWeek(Carbon::parse($date)->dayOfWeekIso);
    }

    /**
     * Aligned with isApplicableOnDay() — single source of truth.
     *
     * NOTE: The previous implementation used `whereBetween(\DB::raw('?'), [1, 5])`
     * and `whereIn(\DB::raw('?'), [6, 7])`. Those are invalid — `whereBetween`
     * and `whereIn` expect a COLUMN as the first argument, not a bound
     * placeholder. Misusing them shifted the parameter bindings and produced
     * the SQLSTATE[HY093] "Invalid parameter number" error.
     *
     * `whereRaw('? BETWEEN 1 AND 5', [$dayNumber])` correctly binds the value.
     */
    public function scopeForDayOfWeek($query, int $dayNumber)
    {
        $dayNumber = (int) $dayNumber;

        return $query->where(function ($q) use ($dayNumber) {
            $q->where('day_type', 'all_days')
              ->orWhere(function ($q2) use ($dayNumber) {
                  $q2->where('day_type', 'weekday')
                     ->whereRaw('? BETWEEN 1 AND 5', [$dayNumber]);
              })
              ->orWhere(function ($q2) use ($dayNumber) {
                  $q2->where('day_type', 'weekend')
                     ->whereRaw('? IN (6, 7)', [$dayNumber]);
              })
              ->orWhere(function ($q2) use ($dayNumber) {
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

    public function scopeWithBreaks($query)
    {
        return $query->whereNotNull('break_schedule')
                     ->whereJsonLength('break_schedule', '>', 0);
    }

    public function scopeWithHandover($query)
    {
        return $query->whereNotNull('handover_config')
                     ->whereJsonLength('handover_config', '>', 0);
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getStartTimeFormattedAttribute(): string
    {
        return $this->formatTime($this->start_time);
    }

    public function getEndTimeFormattedAttribute(): string
    {
        return $this->formatTime($this->end_time);
    }

    public function getDurationInMinutesAttribute(): int
    {
        return (int) round(((float) $this->duration_hours) * 60);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function getRotationTypeLabelAttribute(): string
    {
        return self::ROTATION_TYPES[$this->rotation_type] ?? ucfirst((string) $this->rotation_type);
    }

    public function getDayTypeLabelAttribute(): string
    {
        return self::DAY_TYPES[$this->day_type] ?? ucfirst((string) $this->day_type);
    }

    public function getApplicableDaysLabelAttribute(): string
    {
        if ($this->day_type !== 'custom') {
            return $this->day_type_label;
        }
        if (!is_array($this->applicable_days) || empty($this->applicable_days)) {
            return 'None';
        }
        return collect($this->applicable_days)
            ->sort()
            ->map(fn ($d) => self::DAYS_OF_WEEK[$d] ?? "Day {$d}")
            ->implode(', ');
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->is_active
            ? '<span class="px-2 py-1 bg-success/10 text-success rounded-full text-xs">Active</span>'
            : '<span class="px-2 py-1 bg-danger/10 text-danger rounded-full text-xs">Inactive</span>';
    }

    // ============================================================
    // Status helpers (used by views)
    // ============================================================

    public function getStatusWithColor(): array
    {
        if ($this->trashed()) {
            return [
                'status' => 'Deleted',
                'color' => 'danger',
                'icon' => 'fas fa-trash',
                'label' => 'Deleted',
                'badge_class' => 'badge-danger',
                'text_class' => 'text-danger',
                'tailwind' => 'bg-red-100 text-red-700',
            ];
        }

        if ($this->is_active) {
            return [
                'status' => 'Active',
                'color' => 'success',
                'icon' => 'fas fa-check-circle',
                'label' => 'Active',
                'badge_class' => 'badge-success',
                'text_class' => 'text-success',
                'tailwind' => 'bg-green-100 text-green-700',
            ];
        }

        return [
            'status' => 'Inactive',
            'color' => 'warning',
            'icon' => 'fas fa-pause-circle',
            'label' => 'Inactive',
            'badge_class' => 'badge-warning',
            'text_class' => 'text-warning',
            'tailwind' => 'bg-yellow-100 text-yellow-700',
        ];
    }

    public function getStatusColor(): string
    {
        return $this->getStatusWithColor()['color'];
    }

    public function getStatusLabel(): string
    {
        return $this->getStatusWithColor()['label'];
    }

    public function getStatusBadgeHtml(): string
    {
        $s = $this->getStatusWithColor();
        return "<span class='badge {$s['badge_class']} px-3 py-2'><i class='{$s['icon']} mr-1'></i> {$s['label']}</span>";
    }

    public function isDeleted(): bool
    {
        return !is_null($this->deleted_at);
    }

    // ============================================================
    // Day / applicability helpers
    // ============================================================

    public function isApplicableToday(): bool
    {
        return $this->isApplicableOnDay(now()->dayOfWeekIso);
    }

    public function isApplicableOnDate($date): bool
    {
        return $this->isApplicableOnDay(Carbon::parse($date)->dayOfWeekIso);
    }

    /**
     * Single source of truth for shift applicability.
     *
     * @param int  $dayNumber  ISO day-of-week (1=Mon … 7=Sun)
     * @param bool $isHoliday  If true, category=holiday shifts are allowed
     */
    public function isApplicableOnDay(int $dayNumber, bool $isHoliday = false): bool
    {
        // Holiday category gate: only applicable on holidays
        if ($this->category === 'holiday' && !$isHoliday) {
            return false;
        }

        return match ($this->day_type) {
            'all_days' => true,
            'weekday' => $dayNumber >= 1 && $dayNumber <= 5,
            'weekend' => $dayNumber >= 6 && $dayNumber <= 7,
            'custom' => is_array($this->applicable_days)
                && in_array($dayNumber, array_map('intval', $this->applicable_days), true),
            default => false,
        };
    }

    public function getApplicableDaysList(): array
    {
        if ($this->day_type !== 'custom' || !is_array($this->applicable_days)) {
            return [];
        }
        return collect($this->applicable_days)
            ->sort()
            ->map(fn ($d) => self::DAYS_OF_WEEK[$d] ?? "Day {$d}")
            ->values()
            ->all();
    }

    // ============================================================
    // Time helpers
    // ============================================================

    public function getTimeRange(string $format = 'h:i A'): string
    {
        return $this->formatTime($this->start_time, $format)
            . ' - '
            . $this->formatTime($this->end_time, $format);
    }

    public function getStartTimeCarbon(?Carbon $date = null): Carbon
    {
        $base = $date ? $date->copy() : now();
        return $base->copy()->setTimeFromTimeString($this->start_time);
    }

    public function getEndTimeCarbon(?Carbon $date = null): Carbon
    {
        $base = $date ? $date->copy() : now();
        $end = $base->copy()->setTimeFromTimeString($this->end_time);
        if ($this->is_overnight) {
            $end->addDay();
        }
        return $end;
    }

    // ============================================================
    // Break helpers
    // ============================================================

    public function hasBreaks(): bool
    {
        return is_array($this->break_schedule)
            && ($this->break_schedule['has_break'] ?? false) === true
            && !empty($this->break_schedule['breaks']);
    }

    public function getBreaks(): array
    {
        return $this->hasBreaks() ? ($this->break_schedule['breaks'] ?? []) : [];
    }

    public function getTotalBreakMinutes(): int
    {
        if (!$this->hasBreaks()) {
            return 0;
        }
        if (isset($this->break_schedule['total_break_minutes'])) {
            return (int) $this->break_schedule['total_break_minutes'];
        }
        return (int) array_sum(array_column($this->break_schedule['breaks'] ?? [], 'duration_minutes'));
    }

    public function getTotalBreakHours(): float
    {
        return round($this->getTotalBreakMinutes() / 60, 2);
    }

    public function getNetWorkingMinutes(): int
    {
        return max(0, $this->duration_in_minutes - $this->getTotalBreakMinutes());
    }

    public function getNetWorkingHours(): float
    {
        return round($this->getNetWorkingMinutes() / 60, 2);
    }

    // ============================================================
    // Handover helpers
    // ============================================================

    public function hasHandover(): bool
    {
        return is_array($this->handover_config)
            && ($this->handover_config['has_handover'] ?? false) === true;
    }

    public function getHandoverDuration(): int
    {
        return (int) ($this->handover_config['handover_duration'] ?? 30);
    }

    public function getHandoverChecklist(): array
    {
        return $this->handover_config['handover_checklist'] ?? [];
    }

    public function isHandoverNotesRequired(): bool
    {
        return (bool) ($this->handover_config['handover_notes_required'] ?? true);
    }

    // ============================================================
    // Rotation helpers
    // ============================================================

    public function isRotating(): bool
    {
        return $this->rotation_type === 'rotating';
    }

    public function getRotationSequence(): array
    {
        if (!$this->isRotating()) {
            return [];
        }
        $type = $this->rotation_config['rotation_sequence'] ?? 'morning_evening';
        return self::ROTATION_SEQUENCES[$type] ?? self::ROTATION_SEQUENCES['morning_evening'];
    }

    public function getCurrentShiftType(): ?string
    {
        if (!$this->isRotating() || !is_array($this->rotation_config)) {
            return null;
        }
        $seq = $this->getRotationSequence();
        if (empty($seq)) {
            return null;
        }
        $index = ((int) ($this->rotation_config['current_sequence_index'] ?? 0)) % count($seq);
        return $seq[$index] ?? null;
    }

    public function getNextRotationDate(): ?Carbon
    {
        if (!$this->isRotating()) {
            return null;
        }
        $config = $this->rotation_config ?? [];
        $rotationDays = (int) ($config['rotation_days'] ?? 7);
        $last = $config['last_rotation_date'] ?? null;

        return $last
            ? Carbon::parse($last)->addDays($rotationDays)
            : now()->addDays($rotationDays);
    }

    // ============================================================
    // Internal formatting
    // ============================================================

    private function formatTime(?string $time, string $format = 'h:i A'): string
    {
        if ($time === null || $time === '') {
            return '—';
        }
        try {
            return Carbon::createFromFormat('H:i:s', $time)->format($format);
        } catch (\Exception $e) {
            try {
                return Carbon::createFromFormat('H:i', $time)->format($format);
            } catch (\Exception $e2) {
                return (string) $time;
            }
        }
    }

    // ============================================================
    // Boot / lifecycle
    // ============================================================

    protected static function booted(): void
    {
        static::saving(function (SecurityShift $shift) {
            // Normalize time strings to H:i so duration math is stable
            foreach (['start_time', 'end_time'] as $field) {
                if (!empty($shift->{$field})) {
                    try {
                        $shift->{$field} = Carbon::parse($shift->{$field})->format('H:i');
                    } catch (\Exception $e) {
                        // leave as-is; validation will catch it
                    }
                }
            }

            // Recalculate duration + overnight whenever times change
            if ($shift->isDirty(['start_time', 'end_time']) || !$shift->exists) {
                if (!empty($shift->start_time) && !empty($shift->end_time)) {
                    $start = Carbon::createFromFormat('H:i', $shift->start_time);
                    $end = Carbon::createFromFormat('H:i', $shift->end_time);
                    if ($end <= $start) {
                        $end->addDay();
                        $shift->is_overnight = true;
                    } else {
                        $shift->is_overnight = false;
                    }
                    $shift->duration_hours = round($end->diffInMinutes($start) / 60, 2);
                }
            }

            // Default rotation config only if rotating and no config provided
            if ($shift->rotation_type === 'rotating' && empty($shift->rotation_config)) {
                $shift->rotation_config = [
                    'rotation_days' => 7,
                    'rotation_sequence' => 'morning_evening',
                    'last_rotation_date' => null,
                    'current_sequence_index' => 0,
                ];
            }

            // Clear rotation config when switching to fixed
            if ($shift->rotation_type === 'fixed') {
                $shift->rotation_config = null;
            }

            // Audit fields
            if (auth()->check()) {
                if (!$shift->exists && !$shift->created_by) {
                    $shift->created_by = auth()->id();
                }
                $shift->updated_by = auth()->id();
            }

            // Normalize empty arrays to null so `hasBreaks()` / `hasHandover()` behave
            if ($shift->break_schedule === [] || $shift->break_schedule === '') {
                $shift->break_schedule = null;
            }
            if ($shift->handover_config === [] || $shift->handover_config === '') {
                $shift->handover_config = null;
            }
            if ($shift->applicable_days === [] && $shift->day_type !== 'custom') {
                $shift->applicable_days = null;
            }
        });
    }
}