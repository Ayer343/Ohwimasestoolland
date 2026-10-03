<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SecurityShiftTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'shift_config',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'shift_config' => 'array',
        'is_active' => 'boolean'
    ];

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(
            SecurityShift::class,
            'security_shift_template_shifts',
            'security_shift_template_id',
            'security_shift_id'
        )->withPivot('order')->withTimestamps()->orderBy('order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}