<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmergencyModeLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'emergency_mode_id',
        'action',
        'details',
        'metadata',
        'performed_by',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the emergency mode that owns the log.
     */
    public function emergencyMode()
    {
        return $this->belongsTo(EmergencyMode::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Scope a query to filter by action.
     */
    public function scopeWhereAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to get logs for a specific emergency.
     */
    public function scopeForEmergency($query, $emergencyId)
    {
        return $query->where('emergency_mode_id', $emergencyId);
    }

    /**
     * Scope a query to get logs from a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('performed_by', $userId);
    }

    /**
     * Get the log's readable action name.
     */
    public function getActionNameAttribute()
    {
        $actions = [
            'created' => 'Emergency Created',
            'updated' => 'Emergency Updated',
            'activation_started' => 'Activation Started',
            'activated' => 'Emergency Activated',
            'activation_failed' => 'Activation Failed',
            'deactivation_started' => 'Deactivation Started',
            'deactivated' => 'Emergency Deactivated',
            'deactivation_failed' => 'Deactivation Failed',
            'extended' => 'Duration Extended',
            'cancelled' => 'Scheduled Emergency Cancelled',
            'user_notified' => 'User Notification Sent',
            'system_restrictions_applied' => 'System Restrictions Applied',
            'system_restrictions_removed' => 'System Restrictions Removed',
            'recovery_started' => 'Recovery Process Started',
            'recovery_completed' => 'Recovery Process Completed',
            'recovery_failed' => 'Recovery Process Failed',
        ];

        return $actions[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * Get the log's severity class for UI.
     */
    public function getSeverityClassAttribute()
    {
        $actionSeverity = [
            'activation_failed' => 'danger',
            'deactivation_failed' => 'danger',
            'recovery_failed' => 'danger',
            'activated' => 'warning',
            'deactivated' => 'success',
            'cancelled' => 'info',
            'created' => 'info',
            'updated' => 'info',
        ];

        return $actionSeverity[$this->action] ?? 'secondary';
    }

    /**
     * Get formatted metadata for display.
     */
    public function getFormattedMetadataAttribute()
    {
        if (empty($this->metadata)) {
            return null;
        }

        $formatted = [];
        foreach ($this->metadata as $key => $value) {
            $formattedKey = ucfirst(str_replace('_', ' ', $key));
            
            if (is_array($value)) {
                $formattedValue = json_encode($value, JSON_PRETTY_PRINT);
            } else {
                $formattedValue = $value;
            }
            
            $formatted[] = "<strong>{$formattedKey}:</strong> {$formattedValue}";
        }

        return implode('<br>', $formatted);
    }

    /**
     * Get a brief summary of the log.
     */
    public function getSummaryAttribute()
    {
        $summary = $this->details;
        
        if (!empty($this->metadata)) {
            // Add key metadata points to summary
            $importantKeys = ['reason', 'duration_minutes', 'affected_users', 'minutes_added'];
            $extraInfo = [];
            
            foreach ($importantKeys as $key) {
                if (isset($this->metadata[$key])) {
                    $label = str_replace('_', ' ', $key);
                    $extraInfo[] = ucfirst($label) . ": " . $this->metadata[$key];
                }
            }
            
            if (!empty($extraInfo)) {
                $summary .= ' (' . implode(', ', $extraInfo) . ')';
            }
        }
        
        return $summary;
    }
}