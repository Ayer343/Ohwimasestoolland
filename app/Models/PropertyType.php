<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'is_active',
        'is_custom',
        'sort_order',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_custom' => 'boolean',
        'sort_order' => 'integer'
    ];

    protected $appends = [
        'display_name',
        'properties_count',
        'formatted_color'
    ];

    // Relationships
    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStandard($query)
    {
        return $query->where('is_custom', false);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopePopular($query)
    {
        return $query->withCount('properties')->orderBy('properties_count', 'desc');
    }

    // Helpers
    public function getDisplayNameAttribute()
    {
        return $this->name;
    }

    public function getPropertiesCountAttribute()
    {
        if ($this->relationLoaded('properties')) {
            return $this->properties->count();
        }
        
        return $this->properties()->count();
    }

    public function getFormattedColorAttribute()
    {
        return $this->color ?: '#3b82f6';
    }

    public function getIconHtmlAttribute()
    {
        if (!$this->icon) {
            return '<i class="fas fa-home"></i>';
        }

        // Ensure proper Font Awesome classes
        $icon = $this->icon;
        if (!str_contains($icon, 'fa-')) {
            $icon = 'fas fa-' . $icon;
        }

        return '<i class="' . $icon . '" style="color: ' . $this->formatted_color . '"></i>';
    }

    public function isDeletable()
    {
        // Standard types with properties cannot be deleted
        if (!$this->is_custom && $this->properties_count > 0) {
            return false;
        }

        return true;
    }

    public function isStandard()
    {
        return !$this->is_custom;
    }

    public function isCustom()
    {
        return $this->is_custom;
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Ensure slug is generated if not provided
            if (empty($model->slug)) {
                $model->slug = \Illuminate\Support\Str::slug($model->name);
            }

            // Set default sort order for new records
            if (empty($model->sort_order)) {
                $maxOrder = static::max('sort_order');
                $model->sort_order = $maxOrder ? $maxOrder + 1 : 1;
            }
        });

        static::updating(function ($model) {
            // Update slug if name changed
            if ($model->isDirty('name')) {
                $model->slug = \Illuminate\Support\Str::slug($model->name);
            }
        });
    }
}