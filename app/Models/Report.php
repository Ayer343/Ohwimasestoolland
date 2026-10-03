<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'agent_id',
        'assignment_id',
        'title',
        'content',
        'status',
        'priority',
        'reported_at',
        'resolved_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function assignment()
    {
        return $this->belongsTo(PlanAgentAssignment::class, 'assignment_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}