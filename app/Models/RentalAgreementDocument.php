<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalAgreementDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_agreement_id',
        'document_type',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'description',
        'uploaded_by',
        'is_verified',
        'verified_at',
        'verified_by'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime'
    ];

    // Document type constants
    const TYPE_AGREEMENT_COPY = 'agreement_copy';
    const TYPE_ID_PROOF = 'id_proof';
    const TYPE_INCOME_PROOF = 'income_proof';
    const TYPE_BANK_STATEMENT = 'bank_statement';
    const TYPE_EMPLOYMENT_LETTER = 'employment_letter';
    const TYPE_REFERENCE_LETTER = 'reference_letter';
    const TYPE_PHOTO = 'photo';
    const TYPE_OTHER = 'other';

    public function rentalAgreement(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public static function getDocumentTypeOptions(): array
    {
        return [
            self::TYPE_AGREEMENT_COPY => 'Agreement Copy',
            self::TYPE_ID_PROOF => 'ID Proof',
            self::TYPE_INCOME_PROOF => 'Income Proof',
            self::TYPE_BANK_STATEMENT => 'Bank Statement',
            self::TYPE_EMPLOYMENT_LETTER => 'Employment Letter',
            self::TYPE_REFERENCE_LETTER => 'Reference Letter',
            self::TYPE_PHOTO => 'Photo',
            self::TYPE_OTHER => 'Other Document',
        ];
    }
}