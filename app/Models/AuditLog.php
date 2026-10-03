<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'action',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'loggable_type',
        'loggable_id',
        'developer_setting_id',
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
     * Get the parent loggable model.
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to filter by developer setting.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $developerSettingId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDeveloper($query, int $developerSettingId)
    {
        return $query->where('developer_setting_id', $developerSettingId)
            ->orWhere(function ($query) use ($developerSettingId) {
                $query->where('loggable_type', 'developer')
                    ->where('metadata->developer_setting_id', $developerSettingId);
            });
    }

    /**
     * Scope a query to filter by action.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $action
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', 'LIKE', '%' . $action . '%');
    }

    /**
     * Scope a query to filter by date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        ]);
    }

    /**
     * Get the formatted metadata.
     *
     * @return string
     */
    public function getFormattedMetadataAttribute(): string
    {
        if (empty($this->metadata)) {
            return 'No metadata';
        }

        $formatted = [];
        foreach ($this->metadata as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }
            $formatted[] = "<strong>{$key}:</strong> {$value}";
        }

        return implode('<br>', $formatted);
    }

    /**
     * Get the readable action name.
     *
     * @return string
     */
    public function getReadableActionAttribute(): string
    {
        $actions = [
            'billing_' => 'Billing',
            'security_' => 'Security',
            'system_' => 'System',
            'api_' => 'API',
            'dashboard_view' => 'Dashboard View',
            'settings_update' => 'Settings Update',
            'invoice_generated' => 'Invoice Generated',
            'payment_confirmed' => 'Payment Confirmed',
            'reminder_sent' => 'Reminder Sent',
        ];

        foreach ($actions as $prefix => $name) {
            if (str_starts_with($this->action, $prefix)) {
                $actionName = str_replace($prefix, '', $this->action);
                $actionName = str_replace('_', ' ', $actionName);
                return $name . ': ' . ucwords($actionName);
            }
        }

        return ucwords(str_replace('_', ' ', $this->action));
    }

    /**
     * Get the severity level for the action.
     *
     * @return string
     */
    public function getSeverityAttribute(): string
    {
        $criticalActions = [
            'security_',
            'payment_confirmed',
            'proposal_approved',
            'super_admin_payment_received',
        ];

        $warningActions = [
            'failed_login',
            'payment_failed',
            'invoice_overdue',
            'proposal_rejected',
        ];

        foreach ($criticalActions as $action) {
            if (str_starts_with($this->action, $action)) {
                return 'critical';
            }
        }

        foreach ($warningActions as $action) {
            if (str_starts_with($this->action, $action)) {
                return 'warning';
            }
        }

        return 'info';
    }

    /**
     * Get the badge color based on severity.
     *
     * @return string
     */
    public function getBadgeColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'primary',
        };
    }
}