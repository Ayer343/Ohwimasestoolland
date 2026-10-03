<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConstructionMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'title',
        'description',
        'target_date',
        'completed_date',
        'due_date',
        'status',
        'progress_percentage',
        'notes',
    ];

    protected $casts = [
        'target_date' => 'date',
        'completed_date' => 'date',
        'progress_percentage' => 'integer',
    ];

    /**
     * Get the contract that owns the milestone
     */
    public function contract()
    {
        return $this->belongsTo(ConstructionContract::class, 'contract_id');
    }

    /**
     * Check if milestone is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' || !is_null($this->completed_date);
    }

    /**
     * Check if milestone is overdue
     */
    public function isOverdue(): bool
    {
        return !$this->isCompleted() && $this->target_date && $this->target_date->isPast();
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'delayed' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }
}