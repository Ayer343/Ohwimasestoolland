<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveCleanupLog extends Model
{
    protected $table = 'archive_cleanup_logs';
    
    protected $fillable = [
        'performed_by',
        'performed_by_name',
        'method',
        'years_old',
        'selected_count',
        'cutoff_date',
        'records_deleted',
        'total_amount',
        'archive_type',  // Changed from 'type' to 'archive_type'
        'ip_address',
        'user_agent'
    ];
    
    protected $casts = [
        'records_deleted' => 'integer',
        'total_amount' => 'decimal:2',
        'years_old' => 'integer',
        'selected_count' => 'integer',
        'cutoff_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
    
    // Scope for landlord archives
    public function scopeLandlord($query)
    {
        return $query->where('archive_type', 'landlord');
    }
    
    // Scope for tenant archives
    public function scopeTenant($query)
    {
        return $query->where('archive_type', 'tenant');
    }
}