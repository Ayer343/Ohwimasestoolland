<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'section',
        'key',
        'value',
        'data_type'
    ];

    protected $casts = [
        'value' => 'string'
    ];
}