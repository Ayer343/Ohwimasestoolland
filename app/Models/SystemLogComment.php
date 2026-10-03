<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLogComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'system_log_comments';
    
    protected $fillable = [
        'system_log_id',
        'comment',
        'metadata',
        'created_by',
        'created_by_name',
        'created_by_type',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the system log
     */
    public function systemLog(): BelongsTo
    {
        return $this->belongsTo(SystemLog::class);
    }

    /**
     * Get the user who created the comment
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get formatted created at time
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('M d, Y H:i:s');
    }

    /**
     * Get time ago
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}