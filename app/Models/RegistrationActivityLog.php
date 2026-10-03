<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegistrationActivityLog extends Model
{
    use SoftDeletes;

    protected $table = 'registration_activity_logs';

    protected $fillable = [
        'registration_id',
        'action',
        'description',
        'user_id',
        'ip_address',
        'user_agent',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the registration that this activity log belongs to
     */
    public function registration()
    {
        return $this->belongsTo(LandlordConstructionRegistration::class, 'registration_id');
    }

    /**
     * Get the user who performed this action
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for specific action
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific registration
     */
    public function scopeForRegistration($query, $registrationId)
    {
        return $query->where('registration_id', $registrationId);
    }

    /**
     * Scope for recent activity
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted action label
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'restored' => 'Restored',
            'status_changed' => 'Status Changed',
            'assigned' => 'Assigned',
            'unassigned' => 'Unassigned',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'info_requested' => 'Information Requested',
            'note_added' => 'Note Added',
            'tenants_added' => 'Tenants Added',
            'document_uploaded' => 'Document Uploaded',
            'payment_received' => 'Payment Received',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}