<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'schedule_id',
        'user_id',
        'minutes',
        'reason',
        'status',
        // add other fields as needed
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