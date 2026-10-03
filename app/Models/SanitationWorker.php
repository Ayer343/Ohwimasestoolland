<?php
// app/Models/SanitationWorker.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SanitationWorker extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supervisor_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'profile_photo',
        'status',
        'address',
        'availability',
        'emergency_contact',
        'certifications',
        'hire_date',
        'skills',
    ];

    protected $casts = [
        'availability' => 'array',
        'certifications' => 'array',
        'skills' => 'array',
        'hire_date' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    public function supervisor()
    {
        return $this->belongsTo(SanitationPersonnel::class, 'supervisor_id');
    }

    public function assignedRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class, 'worker_id');
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ==================== ACCESSORS ====================

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'active' => 'success',
            'inactive' => 'danger',
        ];
        return $badges[$this->status] ?? 'secondary';
    }

    public function getActiveJobsCountAttribute()
    {
        return $this->assignedRequests()
            ->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])
            ->count();
    }
}