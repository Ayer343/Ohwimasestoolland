<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecuritySupervisorAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'security_supervisor_assignments';

    protected $fillable = [
        'user_id',
        'assigned_by',
        'title',
        'start_date',
        'end_date',
        'is_active',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
        'metadata'   => 'array',
    ];

    protected $attributes = [
        'title'     => 'Head of Security',
        'is_active' => true,
    ];

    protected $appends = [
        'is_current',
        'days_remaining',
        'supervisor_name',
        'assigned_by_name',
        'title_label',
        'coverage',
    ];

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeEffectiveOn($query, $date)
    {
        $date = Carbon::parse($date);

        return $query->where('is_active', true)
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            });
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('is_active', true)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('end_date')->where('end_date', '<', now());
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    public function getIsCurrentAttribute(): bool
    {
        return $this->is_active
            && $this->start_date
            && $this->start_date <= now()
            && ($this->end_date === null || $this->end_date >= now());
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->is_active || !$this->end_date || $this->end_date < now()) {
            return null;
        }

        return (int) now()->diffInDays($this->end_date, false);
    }

    public function getSupervisorNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    public function getAssignedByNameAttribute(): ?string
    {
        return $this->assignedBy?->name;
    }

    public function getTitleLabelAttribute(): string
    {
        return $this->title ?: 'Head of Security';
    }

    /**
     * Human-readable coverage line: "Head of Security · 2024-01-01 → ongoing".
     */
    public function getCoverageAttribute(): string
    {
        $from = $this->start_date?->format('Y-m-d') ?? '—';
        $to   = $this->end_date?->format('Y-m-d') ?? 'ongoing';

        return "{$from} → {$to}";
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * True when the appointment is active and within `end_date` threshold.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->is_active || !$this->end_date) {
            return false;
        }

        if ($this->end_date < now()) {
            return false;
        }

        return $this->end_date <= now()->addDays($days);
    }

    /**
     * Extend the end date and record the change in metadata.
     */
    public function extend($newEndDate, $extendedBy = null, $reason = null): bool
    {
        $oldEndDate = $this->end_date;

        $this->end_date = Carbon::parse($newEndDate);

        $extensions   = $this->metadata['extensions'] ?? [];
        $extensions[] = [
            'old_end_date' => $oldEndDate?->format('Y-m-d'),
            'new_end_date' => $this->end_date->format('Y-m-d'),
            'extended_by'  => $extendedBy ?? auth()->id(),
            'extended_at'  => now()->toDateTimeString(),
            'reason'       => $reason,
        ];

        $this->metadata = array_merge($this->metadata ?? [], ['extensions' => $extensions]);

        return $this->save();
    }

    /**
     * Terminate the appointment early.
     */
    public function terminate(?string $reason = null, $terminatedBy = null): bool
    {
        $this->is_active = false;
        $this->end_date  = now();

        $this->metadata = array_merge($this->metadata ?? [], [
            'terminated_at'      => now()->toDateTimeString(),
            'terminated_by'      => $terminatedBy ?? auth()->id(),
            'termination_reason' => $reason,
        ]);

        return $this->save();
    }

    // ================================================================
    // STATIC HELPERS
    // ================================================================

    /**
     * The currently active Head of Security, or null.
     */
    public static function currentHead(): ?self
    {
        return static::where('is_active', true)
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * True when an active head already exists (optionally excluding one id).
     */
    public static function hasActiveHead(?int $exceptId = null): bool
    {
        return static::where('is_active', true)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * Deactivate any active appointment (used before creating/replacing one).
     *
     * @return int Number of rows deactivated.
     */
    public static function deactivateCurrentHead(?string $replacedByName = null, ?int $exceptId = null): int
    {
        $query = static::where('is_active', true)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId));

        $rows = $query->get();

        foreach ($rows as $row) {
            $row->update([
                'is_active' => false,
                'end_date'  => now()->subDay(),
                'notes'     => trim(($row->notes ?? '') .
                    ($replacedByName
                        ? "\nReplaced by {$replacedByName} on " . now()->toDateString() . '.'
                        : "\nDeactivated on " . now()->toDateString() . '.')),
            ]);
        }

        return $rows->count();
    }
}