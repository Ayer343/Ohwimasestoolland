<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RotationGroupMember extends Model
{
    use HasFactory;

    protected $table = 'rotation_group_members';

    protected $fillable = [
        'rotation_group_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'preference_score',
        'assigned_by',
        'status',
        'rotation_count',
        'last_rotation_date',
        'notes',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'last_rotation_date' => 'datetime',
        'preference_score' => 'float',
        'rotation_count' => 'integer',
    ];

    /**
     * Get the rotation group this member belongs to
     */
    public function group()
    {
        return $this->belongsTo(RotationGroup::class, 'rotation_group_id');
    }

    /**
     * Get the user who is a member
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who assigned this member
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope a query to only include active members
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if member is active
     */
    public function isActive()
    {
        return $this->status === 'active' && !$this->left_at;
    }
}