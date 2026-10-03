<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegistrationDocument extends Model
{
    use SoftDeletes;

    const TYPE_DEED = 'deed';
    const TYPE_SURVEY = 'survey';
    const TYPE_ID_PROOF = 'id_proof';
    const TYPE_CONSTRUCTION_PLAN = 'construction_plan';
    const TYPE_OTHER = 'other';

    protected $table = 'registration_documents';

    protected $fillable = [
        'registration_id',
        'filename',
        'file_path',
        'file_size',
        'mime_type',
        'document_type',
        'description',
        'uploaded_by',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the registration that owns this document
     */
    public function registration()
    {
        return $this->belongsTo(LandlordConstructionRegistration::class, 'registration_id');
    }

    /**
     * Get the user who uploaded this document
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who verified this document
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope for documents of a specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope for verified documents
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for unverified documents
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    /**
     * Get document type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->document_type) {
            self::TYPE_DEED => 'Property Deed',
            self::TYPE_SURVEY => 'Survey Plan',
            self::TYPE_ID_PROOF => 'ID Proof',
            self::TYPE_CONSTRUCTION_PLAN => 'Construction Plan',
            self::TYPE_OTHER => 'Other Document',
            default => ucfirst(str_replace('_', ' ', $this->document_type)),
        };
    }

    /**
     * Get file size in human readable format
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file icon based on mime type
     */
    public function getFileIconAttribute(): string
    {
        return match($this->mime_type) {
            'application/pdf' => 'fa-file-pdf',
            'image/jpeg', 'image/png', 'image/jpg' => 'fa-file-image',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fa-file-excel',
            default => 'fa-file',
        };
    }

    /**
     * Get file color based on mime type
     */
    public function getFileColorAttribute(): string
    {
        return match($this->mime_type) {
            'application/pdf' => 'danger',
            'image/jpeg', 'image/png', 'image/jpg' => 'success',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'primary',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'success',
            default => 'secondary',
        };
    }

    /**
     * Mark document as verified
     */
    public function markAsVerified(int $userId)
    {
        $this->is_verified = true;
        $this->verified_at = now();
        $this->verified_by = $userId;
        $this->save();
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($document) {
            // Delete the physical file when document is soft deleted
            if ($document->file_path && !$document->isForceDeleting()) {
                // You might want to handle file deletion differently
                // For now, we'll just keep a record of the deletion
                \Log::info('Document soft deleted: ' . $document->file_path);
            }
        });

        static::forceDeleting(function ($document) {
            // Delete the physical file when document is force deleted
            if ($document->file_path && \Storage::disk('public')->exists($document->file_path)) {
                \Storage::disk('public')->delete($document->file_path);
            }
        });
    }
}