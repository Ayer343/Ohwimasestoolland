<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmergencyAffectedUser extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'emergency_mode_id',
        'user_id',
        'user_type',
        'affected_features',
        'notified_at',
        'notification_channels',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'affected_features' => 'array',
        'notification_channels' => 'array',
        'notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the emergency mode that owns the affected user record.
     */
    public function emergencyMode()
    {
        return $this->belongsTo(EmergencyMode::class);
    }

    /**
     * Get the user who is affected.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to filter by user type.
     */
    public function scopeByUserType($query, $type)
    {
        return $query->where('user_type', $type);
    }

    /**
     * Scope a query to get users who have been notified.
     */
    public function scopeNotified($query)
    {
        return $query->whereNotNull('notified_at');
    }

    /**
     * Scope a query to get users who haven't been notified.
     */
    public function scopeNotNotified($query)
    {
        return $query->whereNull('notified_at');
    }

    /**
     * Get the user type name.
     */
    public function getUserTypeNameAttribute()
    {
        $types = [
            1 => 'Admin',
            2 => 'Landlord',
            3 => 'Tenant',
            4 => 'Agent',
            5 => 'Developer',
        ];

        return $types[$this->user_type] ?? 'Unknown';
    }

    /**
     * Get formatted affected features.
     */
    public function getFormattedFeaturesAttribute()
    {
        if (empty($this->affected_features)) {
            return 'None';
        }

        $featureNames = [
            'user_management' => 'User Management',
            'property_management' => 'Property Management',
            'payment_processing' => 'Payment Processing',
            'communication' => 'Communication',
            'reporting' => 'Reporting',
            'api' => 'API Services',
            'dashboard' => 'Dashboard',
            'authentication' => 'Authentication',
            'database' => 'Database',
            'queue' => 'Queues',
        ];

        $formatted = array_map(function($feature) use ($featureNames) {
            return $featureNames[$feature] ?? ucfirst(str_replace('_', ' ', $feature));
        }, $this->affected_features);

        return implode(', ', $formatted);
    }
}