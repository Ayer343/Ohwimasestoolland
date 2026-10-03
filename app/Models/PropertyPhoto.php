<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyPhoto extends Model
{
    use HasFactory;

    protected $table = 'property_photos';

    protected $fillable = [
        'property_id',
        'photo_path',
        'thumbnail_path',
        'medium_path',
        'photo_url',
        'thumbnail_url',
        'medium_url',
        'is_primary',
        'sort_order',
        'caption',
        'file_name',
        'file_size',
        'mime_type',
        'dimensions',
        'alt_text',
        'uploaded_by',
        'metadata'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'file_size' => 'integer',
        'dimensions' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'photo_url',
        'thumbnail_url',
        'medium_url',
        'file_size_formatted'
    ];

    /**
     * Get the property that owns the photo
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who uploaded the photo
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get photo URL attribute
     */
    public function getPhotoUrlAttribute()
    {
        if ($this->photo_path && Storage::disk('public')->exists($this->photo_path)) {
            return Storage::disk('public')->url($this->photo_path);
        }
        
        return $this->attributes['photo_url'] ?? null;
    }

    /**
     * Get thumbnail URL attribute
     */
    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail_path && Storage::disk('public')->exists($this->thumbnail_path)) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }
        
        return $this->attributes['thumbnail_url'] ?? null;
    }

    /**
     * Get medium URL attribute
     */
    public function getMediumUrlAttribute()
    {
        if ($this->medium_path && Storage::disk('public')->exists($this->medium_path)) {
            return Storage::disk('public')->url($this->medium_path);
        }
        
        return $this->attributes['medium_url'] ?? null;
    }

    /**
     * Get formatted file size
     */
    public function getFileSizeFormattedAttribute()
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $this->file_size > 0 ? floor(log($this->file_size, 1024)) : 0;
        
        return round($this->file_size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Upload the photo file (simplified version without image processing)
     */
    public function uploadPhoto($file): void
    {
        // Generate unique filename
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);
        $extension = $file->getClientOriginalExtension();
        $filename = "photo_{$timestamp}_{$random}.{$extension}";
        
        // Define path
        $propertyId = $this->property_id;
        $path = "property-photos/{$propertyId}/{$filename}";
        
        // Store the file
        $stored = Storage::disk('public')->put($path, file_get_contents($file));
        
        if (!$stored) {
            throw new \Exception('Failed to store image');
        }
        
        // Set the paths (all point to the same file for simplicity)
        $this->photo_path = $path;
        $this->thumbnail_path = $path;
        $this->medium_path = $path;
        $this->file_name = $file->getClientOriginalName();
        $this->file_size = $file->getSize();
        $this->mime_type = $file->getMimeType();
        
        // Set dimensions (optional - skip if not needed)
        $this->dimensions = null;
        
        // Set uploaded_by if authenticated
        if (auth()->check()) {
            $this->uploaded_by = auth()->id();
        }
    }

    /**
     * Delete the photo file from storage
     */
    public function deletePhotoFile(): bool
    {
        $deleted = true;
        
        if ($this->photo_path && Storage::disk('public')->exists($this->photo_path)) {
            $deleted = $deleted && Storage::disk('public')->delete($this->photo_path);
        }
        
        // Only try to delete thumbnail and medium if they are different files
        if ($this->thumbnail_path && $this->thumbnail_path !== $this->photo_path && Storage::disk('public')->exists($this->thumbnail_path)) {
            $deleted = $deleted && Storage::disk('public')->delete($this->thumbnail_path);
        }
        
        if ($this->medium_path && $this->medium_path !== $this->photo_path && Storage::disk('public')->exists($this->medium_path)) {
            $deleted = $deleted && Storage::disk('public')->delete($this->medium_path);
        }
        
        return $deleted;
    }

    /**
     * Boot method to handle cleanup on delete
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($photo) {
            $photo->deletePhotoFile();
        });
    }
}