<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkerAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'worker_id',
        'contract_id',
        'assigned_by',
        'role_on_site',
        'section',
        'start_date',
        'end_date',
        'status',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'active' => 'Active',
            'completed' => 'Completed',
            'terminated' => 'Terminated',
            default => ucfirst($this->status),
        };
    }

    public function getDurationAttribute()
    {
        if (!$this->end_date) {
            return $this->start_date->diffInDays(now()) . ' days (ongoing)';
        }
        return $this->start_date->diffInDays($this->end_date) . ' days';
    }
}