<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegistrationNote extends Model
{
    use SoftDeletes;

    protected $table = 'registration_notes';

    protected $fillable = [
        'registration_id',
        'user_id',
        'content',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    /**
     * Get the registration that owns this note
     */
    public function registration()
    {
        return $this->belongsTo(LandlordConstructionRegistration::class, 'registration_id');
    }

    /**
     * Get the user who created this note
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for internal notes
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    /**
     * Scope for public notes
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }
}