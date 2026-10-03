<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'path',
        'type',
        'size',
        'description',
        'user_id',
        'documentable_id',
        'documentable_type',
        'status',
        'uploaded_by',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Document status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ARCHIVED = 'archived';

    /**
     * Get the user who uploaded the document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who uploaded the document (alias for uploadedBy).
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the parent documentable model (PropertyUnit, RentalAgreement, etc.).
     */
    public function documentable()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include documents of a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include documents with specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include documents for a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the file extension.
     *
     * @return string
     */
    public function getExtensionAttribute()
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /**
     * Get the file name without extension.
     *
     * @return string
     */
    public function getFileNameAttribute()
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * Get the file URL for download.
     *
     * @return string
     */
    public function getDownloadUrl()
    {
        return route('documents.download', $this->id);
    }

    /**
     * Get the file preview URL (if applicable).
     *
     * @return string|null
     */
    public function getPreviewUrl()
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($this->extension, $imageExtensions)) {
            return Storage::url($this->path);
        }
        
        return null;
    }

    /**
     * Check if the document is an image.
     *
     * @return bool
     */
    public function isImage()
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        return in_array($this->extension, $imageExtensions);
    }

    /**
     * Check if the document is a PDF.
     *
     * @return bool
     */
    public function isPdf()
    {
        return $this->extension === 'pdf';
    }

    /**
     * Check if the document is pending.
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the document is approved.
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Get human readable file size.
     *
     * @return string
     */
    public function getFormattedSizeAttribute()
    {
        if ($this->size === 0) {
            return '0 Bytes';
        }

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($this->size) / log($k));

        return round($this->size / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Get the document type label.
     *
     * @return string
     */
    public function getTypeLabelAttribute()
    {
        $labels = [
            'id_proof' => 'ID Proof',
            'proof_of_income' => 'Proof of Income',
            'employment_letter' => 'Employment Letter',
            'bank_statement' => 'Bank Statement',
            'reference_letter' => 'Reference Letter',
            'utility_bill' => 'Utility Bill',
            'rent_receipt' => 'Rent Receipt',
            'lease_agreement' => 'Lease Agreement',
            'maintenance_request' => 'Maintenance Request',
            'other' => 'Other Document',
        ];

        return $labels[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /**
     * Get the icon for the document type.
     *
     * @return string
     */
    public function getIconAttribute()
    {
        $icons = [
            'id_proof' => 'fa-id-card',
            'proof_of_income' => 'fa-money-bill',
            'employment_letter' => 'fa-briefcase',
            'bank_statement' => 'fa-university',
            'reference_letter' => 'fa-envelope-open-text',
            'utility_bill' => 'fa-bolt',
            'rent_receipt' => 'fa-receipt',
            'lease_agreement' => 'fa-file-contract',
            'maintenance_request' => 'fa-tools',
            'other' => 'fa-file',
        ];

        return $icons[$this->type] ?? 'fa-file';
    }

    /**
     * Get the badge color for the document status.
     *
     * @return string
     */
    public function getStatusColorAttribute()
    {
        $colors = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_ARCHIVED => 'secondary',
        ];

        return $colors[$this->status] ?? 'secondary';
    }
}