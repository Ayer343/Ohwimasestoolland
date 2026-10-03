<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationLog extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'type',          // sms, whatsapp, email, in_app
        'provider',      // arkesel, twilio, smtp, etc.
        'recipient',     // phone number, email, user_id
        'sender',        // sender_id, from_address
        'message',       // Notification message
        'subject',       // Email subject (optional)
        'status',        // sent, delivered, failed, pending, error
        'delivery_status', // delivered, read, failed
        'attempts',      // Number of retry attempts
        'error_message', // Error message if failed
        'metadata',      // JSON data (plan_id, agent_id, etc.)
        'sent_at',       // When sent
        'delivered_at',  // When delivered
        'read_at',       // When read (for WhatsApp/Email)
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];
    
    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
    
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed')->orWhere('status', 'error');
    }
    
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'sent')->orWhere('status', 'delivered');
    }
    
    public function scopeOlderThan($query, $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }
    
    // Methods
    public function isSuccessful()
    {
        return in_array($this->status, ['sent', 'delivered']);
    }
    
    public function isFailed()
    {
        return in_array($this->status, ['failed', 'error']);
    }
    
    public function getFormattedMessageAttribute()
    {
        return Str::limit($this->message, 100);
    }
    
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'sent' => 'success',
            'delivered' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'error' => 'danger',
            default => 'secondary'
        };
    }
}