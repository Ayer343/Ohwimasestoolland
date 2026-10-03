<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityAvailability extends Model
{
    use HasFactory;

    protected $table = 'security_availabilities'; // or whatever your table is named

    protected $fillable = [
        'user_id',
        'date',
        'status',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}