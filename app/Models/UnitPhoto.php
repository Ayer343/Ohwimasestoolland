<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class UnitPhoto extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'unit_photos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'property_unit_id',
        'uploaded_by',
        'disk',
        'path',
        'thumbnail_path',
        'mime',
        'size_bytes',
        'sort_order',
        'is_cover',
        'caption',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'property_unit_id' => 'integer',
        'uploaded_by'      => 'integer',
        'size_bytes'       => 'integer',
        'sort_order'       => 'integer',
        'is_cover'         => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'deleted_at'       => 'datetime',
    ];

    /**
     * Accessors that should be appended to array / JSON output.
     * These give the Blade and any API consumer ready-to-use URLs
     * without having to know which disk the file lives on.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'url',
        'thumb_url',
    ];

    // ========== RELATIONS ==========

    /**
     * The unit this photo belongs to.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    /**
     * The user who uploaded this photo.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ========== SCOPES ==========

    /**
     * Default ordering used everywhere in the app:
     *   cover photo first, then sort_order, then id as a stable tiebreaker.
     */
    public function scopeOrdered($query)
    {
        return $query
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Restrict to the unit's cover photo (or null).
     */
    public function scopeCover($query)
    {
        return $query->where('is_cover', true);
    }

    /**
     * Restrict to photos for a given unit.
     */
    public function scopeForUnit($query, int $unitId)
    {
        return $query->where('property_unit_id', $unitId);
    }

    // ========== ACCESSORS ==========

    /**
     * Public URL for the full-size image.
     */
    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Public URL for the thumbnail, falling back to the full image
     * if no thumbnail was generated (e.g. legacy rows).
     */
    public function getThumbUrlAttribute(): ?string
    {
        $path = $this->thumbnail_path ?: $this->path;
        if (!$path) {
            return null;
        }
        return Storage::disk($this->disk)->url($path);
    }

    // ========== DOMAIN HELPERS ==========

    /**
     * Does the underlying file still exist on disk?
     */
    public function fileExists(): bool
    {
        return $this->path && Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Delete the underlying files without touching the DB row.
     * (Used by the controller's deletePhoto / deleteUnitPhotoFiles.)
     */
    public function deleteFiles(): void
    {
        if ($this->path && Storage::disk($this->disk)->exists($this->path)) {
            Storage::disk($this->disk)->delete($this->path);
        }
        if ($this->thumbnail_path && Storage::disk($this->disk)->exists($this->thumbnail_path)) {
            Storage::disk($this->disk)->delete($this->thumbnail_path);
        }
    }
}