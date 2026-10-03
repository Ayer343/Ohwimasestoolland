<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractorContract extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'contractor_profile_id',
        'construction_contract_id',
        'assigned_by',
        'status',
        'start_date',
        'end_date',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    public function contractorProfile()
    {
        return $this->belongsTo(ContractorProfile::class);
    }

    public function constructionContract()
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
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_TERMINATED => 'Terminated',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_ACTIVE => 'badge-success',
            self::STATUS_COMPLETED => 'badge-info',
            self::STATUS_TERMINATED => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    public function getWorkersCountAttribute()
    {
        return ConstructionWorker::whereHas('contract', function($query) {
            $query->where('id', $this->construction_contract_id);
        })->count();
    }

    public function getActiveWorkersCountAttribute()
    {
        return ConstructionWorker::whereHas('contract', function($query) {
            $query->where('id', $this->construction_contract_id);
        })->where('status', 'active')->count();
    }
}