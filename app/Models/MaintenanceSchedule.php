<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaintenanceSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'maintenance_schedules';

    protected $fillable = [
        'title',
        'description',
        'scheduled_start',
        'scheduled_end',
        'status',
        'progress',
        'created_by',
        'affected_modules',
        'notifications_sent'
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'affected_modules' => 'array',
        'notifications_sent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function affectedModules()
    {
        return $this->belongsToMany(SystemModule::class, 'maintenance_affected_modules');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // Helper methods
    public function isActive()
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_IN_PROGRESS]);
    }

    public function canBeStarted()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeCompleted()
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }
}