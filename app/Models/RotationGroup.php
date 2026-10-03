<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RotationGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rotation_groups';

    protected $fillable = [
        'name',
        'code',
        'description',
        'security_post_id',
        'security_shift_id',
        'group_type',
        'rotation_config',
        'max_members',
        'min_members',
        'current_members',
        'preference_weights',
        'auto_rotate',
        'auto_rotate_schedule',
        'auto_rotate_time',
        'last_rotated_at',
        'is_active', // Changed from 'status'
        'settings',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rotation_config' => 'array',
        'preference_weights' => 'array',
        'settings' => 'array',
        'auto_rotate' => 'boolean',
        'is_active' => 'boolean', // Changed from 'status'
        'last_rotated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the post associated with this rotation group
     */
    public function post()
    {
        return $this->belongsTo(SecurityPost::class, 'security_post_id');
    }

    /**
     * Get the shift associated with this rotation group
     */
    public function shift()
    {
        return $this->belongsTo(SecurityShift::class, 'security_shift_id');
    }

    /**
     * Get the members of this rotation group
     */
    public function members()
    {
        return $this->hasMany(RotationGroupMember::class, 'rotation_group_id');
    }

    /**
     * Get the schedules that use this rotation group
     */
    public function schedules()
    {
        return $this->hasMany(SecuritySchedule::class, 'rotation_group_id');
    }

    /**
     * Get the user who created this group
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this group
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include active groups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true); // Changed from 'status', 'active'
    }

    /**
     * Scope a query to only include rotating groups
     */
    public function scopeRotating($query)
    {
        return $query->where('group_type', 'rotating');
    }

    /**
     * Check if group needs rotation
     */
    public function needsRotation()
    {
        if (!$this->auto_rotate || $this->group_type !== 'rotating') {
            return false;
        }

        $nextRotation = $this->rotation_config['next_rotation_date'] ?? null;
        if (!$nextRotation) {
            return false;
        }

        return now()->parse($nextRotation)->lte(now()->addDays(3));
    }

    /**
     * Get the next scheduled rotation date
     */
    public function getNextRotationDateAttribute()
    {
        if (!$this->auto_rotate || $this->group_type !== 'rotating') {
            return null;
        }

        return isset($this->rotation_config['next_rotation_date']) 
            ? now()->parse($this->rotation_config['next_rotation_date']) 
            : null;
    }
    
    /**
     * Accessor to maintain backward compatibility with code expecting 'status'
     */
    public function getStatusAttribute()
    {
        return $this->is_active ? 'active' : 'inactive';
    }
    
    /**
     * Mutator to allow setting status and have it update is_active
     */
    public function setStatusAttribute($value)
    {
        $this->attributes['is_active'] = in_array($value, ['active', 'Active', 'ACTIVE', 1, '1', true], true);
    }
}