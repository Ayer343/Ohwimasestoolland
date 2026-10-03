<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAttendance extends Model
{
    protected $fillable = [
        'worker_id',
        'contract_id',
        'marked_by',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'status',
        'notes'
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    // Relationships
    public function worker()
    {
        return $this->belongsTo(ConstructionWorker::class);
    }

    public function contract()
    {
        return $this->belongsTo(ConstructionContract::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('attendance_date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('attendance_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'half_day' => 'Half Day',
            'holiday' => 'Holiday',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'present' => 'badge-success',
            'absent' => 'badge-danger',
            'late' => 'badge-warning',
            'half_day' => 'badge-info',
            'holiday' => 'badge-secondary',
            default => 'badge-secondary',
        };
    }

    public function getWorkHoursAttribute()
    {
        if (!$this->check_in_time || !$this->check_out_time) {
            return null;
        }
        return $this->check_in_time->diffInHours($this->check_out_time);
    }
}