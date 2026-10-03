<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SwapRequest extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'schedule_id',
        'user_id',
        'reason',
        'preferred_dates',
        'status',
        // add other fields as needed
    ];
    
    protected $casts = [
        'preferred_dates' => 'array',
    ];
    
    public function schedule()
    {
        return $this->belongsTo(SecuritySchedule::class, 'schedule_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}