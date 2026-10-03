<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalAgreementRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_agreement_id',
        'old_data',
        'new_data',
        'change_description',
        'changed_by',
        'change_type'
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array'
    ];

    public function rentalAgreement(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}