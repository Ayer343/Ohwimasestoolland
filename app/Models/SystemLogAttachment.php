<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLogAttachment extends Model
{
    use HasFactory;

    protected $table = 'system_log_attachments';
    
    protected $fillable = [
        'system_log_id',
        'filename',
        'original_filename',
        'mime_type',
        'path',
        'size',
        'metadata',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the system log
     */
    public function systemLog(): BelongsTo
    {
        return $this->belongsTo(SystemLog::class);
    }

    /**
     * Get the user who uploaded the attachment
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get file URL
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Check if file is an image
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if file is a document
     */
    public function getIsDocumentAttribute(): bool
    {
        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ];
        
        return in_array($this->mime_type, $documentMimes);
    }
}