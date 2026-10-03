<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class Activity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_name',
        'method',
        'url',
        'route_name',
        'old_data',
        'new_data',
        'description',
        'ip_address',
        'user_agent',
        'session_id',
        'duration_ms',
        'metadata',
        'is_successful',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'metadata' => 'array',
        'is_successful' => 'boolean',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject model (polymorphic relationship).
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to get activities for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to get activities by action type.
     */
    public function scopeOfAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to get successful activities only.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('is_successful', true);
    }

    /**
     * Scope a query to get failed activities only.
     */
    public function scopeFailed($query)
    {
        return $query->where('is_successful', false);
    }

    /**
     * Scope a query to get activities within a date range.
     */
    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to get today's activities.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    /**
     * Scope a query to get recent activities.
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->latest()->limit($limit);
    }

    /**
     * Helper method to log an activity.
     */
    public static function log($action, $description = null, $subject = null, $data = [])
    {
        $activity = new static();
        $activity->user_id = Auth::id();
        $activity->action = $action;
        $activity->description = $description;
        $activity->ip_address = request()->ip();
        $activity->user_agent = request()->userAgent();
        $activity->method = request()->method();
        $activity->url = request()->fullUrl();
        $activity->route_name = request()->route()?->getName();
        $activity->session_id = session()->getId();
        
        if ($subject) {
            $activity->subject_type = get_class($subject);
            $activity->subject_id = $subject->id;
            
            // Try to get a human-readable name
            if (method_exists($subject, 'getActivityName')) {
                $activity->subject_name = $subject->getActivityName();
            } elseif (isset($subject->name)) {
                $activity->subject_name = $subject->name;
            } elseif (isset($subject->title)) {
                $activity->subject_name = $subject->title;
            }
        }
        
        if (!empty($data)) {
            $activity->metadata = $data;
        }
        
        if (isset($data['old_data'])) {
            $activity->old_data = $data['old_data'];
        }
        
        if (isset($data['new_data'])) {
            $activity->new_data = $data['new_data'];
        }
        
        if (isset($data['duration_ms'])) {
            $activity->duration_ms = $data['duration_ms'];
        }
        
        $activity->save();
        
        return $activity;
    }

    /**
     * Helper method to log an error.
     */
    public static function logError($action, $errorMessage, $subject = null, $data = [])
    {
        $data['is_successful'] = false;
        $data['error_message'] = $errorMessage;
        
        return static::log($action, $errorMessage, $subject, $data);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_ms) {
            return 'N/A';
        }
        
        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        }
        
        return round($this->duration_ms / 1000, 2) . 's';
    }

    /**
     * Get a human-readable action label.
     */
    public function getActionLabelAttribute(): string
    {
        $labels = [
            'login' => 'User Login',
            'logout' => 'User Logout',
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'view' => 'Viewed',
            'export' => 'Exported',
            'import' => 'Imported',
            'login_failed' => 'Failed Login Attempt',
        ];
        
        return $labels[$this->action] ?? ucfirst($this->action);
    }

    /**
     * Get status badge class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return $this->is_successful ? 'success' : 'danger';
    }
}