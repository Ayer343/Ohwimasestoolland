<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionProgressUpdate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_id',
        'user_id',
        'notes',
        'photo_path',
        'progress_percentage',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function contract()
    {
        return $this->belongsTo(ConstructionContract::class, 'contract_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Accessors
    public function getPhotoUrlAttribute()
    {
        if ($this->photo_path) {
            return asset('storage/' . $this->photo_path);
        }
        return null;
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    // Scopes
    public function scopeRecent($query, $limit = 10)
    {
        return $query->latest()->limit($limit);
    }

    public function scopeForContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    public function scopeWithProgress($query, $minPercentage = 0, $maxPercentage = 100)
    {
        return $query->whereBetween('progress_percentage', [$minPercentage, $maxPercentage]);
    }
}