<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ─────────────────────────────────────────────
            // Identity
            // ─────────────────────────────────────────────
            'id'             => $this->id,
            'property_id'    => $this->property_id,

            // ─────────────────────────────────────────────
            // File metadata
            // ─────────────────────────────────────────────
            'file_name'      => $this->file_name,
            'file_size'      => $this->file_size,
            'mime_type'      => $this->mime_type,
            'dimensions'     => $this->dimensions,

            // ─────────────────────────────────────────────
            // URLs — resolved via $appends on PropertyPhoto
            //   getPhotoUrlAttribute()
            //   getThumbnailUrlAttribute()
            //   getMediumUrlAttribute()
            // ─────────────────────────────────────────────
            'photo_url'           => $this->photo_url,
            'thumbnail_url'       => $this->thumbnail_url,
            'medium_url'          => $this->medium_url,

            // ─────────────────────────────────────────────
            // Convenience / display
            // ─────────────────────────────────────────────
            'file_size_formatted' => $this->file_size_formatted,
            'is_primary'          => (bool) $this->is_primary,
            'sort_order'          => (int) ($this->sort_order ?? 0),
            'caption'             => $this->caption,
            'alt_text'            => $this->alt_text,

            // ─────────────────────────────────────────────
            // Upload info
            // ─────────────────────────────────────────────
            'uploaded_by'    => $this->uploaded_by,

            // ─────────────────────────────────────────────
            // Extra
            // ─────────────────────────────────────────────
            'metadata'       => $this->metadata ?? [],

            // ─────────────────────────────────────────────
            // Timestamps
            // ─────────────────────────────────────────────
            'created_at'     => optional($this->created_at)->toIso8601String(),
            'updated_at'     => optional($this->updated_at)->toIso8601String(),
        ];
    }
}