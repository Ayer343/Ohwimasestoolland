<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'description'   => $this->description,
            'icon'          => $this->icon,          // "fa-home", etc.
            'color'         => $this->color,         // "#3B82F6", etc.
            'is_active'     => (bool) $this->is_active,
            'is_custom'     => (bool) $this->is_custom,
            'sort_order'    => (int) $this->sort_order,
            'created_at'    => optional($this->created_at)->toIso8601String(),
            'updated_at'    => optional($this->updated_at)->toIso8601String(),
            'deleted_at'    => optional($this->deleted_at)->toIso8601String(),
        ];
    }
}