<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'type',
        'title',
        'message',
        'is_read',
        'read_at',
        'data',
        'channel',
        'status',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    /**
     * Get the parent notifiable model (contract, user, etc.)
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Scope notifications by role
     */
    public function scopeForRole(Builder $query, ?string $roleSlug): Builder
    {
        if (!$roleSlug) {
            return $query;
        }
        
        return $query->where(function($q) use ($roleSlug) {
            $q->whereJsonContains('data->roles', $roleSlug)
              ->orWhereJsonContains('data->roles', 'all')
              ->orWhereNull('data->roles');
        });
    }
    
    /**
     * Scope notifications by multiple roles
     */
    public function scopeForRoles(Builder $query, array $roleSlugs): Builder
    {
        if (empty($roleSlugs)) {
            return $query;
        }
        
        return $query->where(function($q) use ($roleSlugs) {
            foreach ($roleSlugs as $roleSlug) {
                $q->orWhereJsonContains('data->roles', $roleSlug);
            }
            $q->orWhereJsonContains('data->roles', 'all')
              ->orWhereNull('data->roles');
        });
    }
    
    /**
     * Check if notification is visible for a specific role
     */
    public function isVisibleForRole(string $roleSlug): bool
    {
        $roles = $this->data['roles'] ?? null;
        
        if (!$roles) {
            return true;
        }
        
        if ($roles === 'all' || in_array('all', $roles)) {
            return true;
        }
        
        return in_array($roleSlug, $roles);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }
}