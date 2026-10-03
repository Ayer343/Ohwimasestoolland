<?php
// app/Models/CollectionZone.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectionZone extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * ✅ FIXED: Removed 'status' since it doesn't exist
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'coordinates',
        'boundaries',
        'assigned_personnel_id',
        'created_by',
        'metadata',
        'region',
        'district',
        'latitude',
        'longitude',
        'boundary_coordinates',
        'priority',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'coordinates' => 'array',
        'boundaries' => 'array',
        'metadata' => 'array',
        'boundary_coordinates' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'priority' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'metadata',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'status_label',
        'status_badge',
    ];

    // ============================================ //
    // ✅ SCOPES                                   //
    // ============================================ //

    /**
     * Scope a query to only include active zones.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive zones.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to search by name or code.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to filter by assigned personnel.
     */
    public function scopeAssignedTo($query, $personnelId)
    {
        return $query->where('assigned_personnel_id', $personnelId);
    }

    /**
     * Scope a query to filter by region.
     */
    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Scope a query to filter by district.
     */
    public function scopeByDistrict($query, $district)
    {
        return $query->where('district', $district);
    }

    /**
     * Scope a query to get unassigned zones.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_personnel_id');
    }

    /**
     * Scope a query to get assigned zones.
     */
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('assigned_personnel_id');
    }

    /**
     * Scope a query to order by priority.
     */
    public function scopeOrderByPriority($query, $direction = 'asc')
    {
        return $query->orderBy('priority', $direction);
    }

    // ============================================ //
    // ✅ RELATIONSHIPS                            //
    // ============================================ //

    /**
     * Get the assigned personnel for this zone.
     */
    public function assignedPersonnel()
    {
        return $this->belongsTo(SanitationPersonnel::class, 'assigned_personnel_id');
    }

    /**
     * Get the user who created this zone.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get properties in this zone.
     */
    public function properties()
    {
        return $this->hasMany(Property::class, 'collection_zone_id');
    }

    /**
     * Get waste collection requests in this zone.
     */
    public function wasteCollectionRequests()
    {
        return $this->hasMany(WasteCollectionRequest::class, 'collection_zone_id');
    }

    // ============================================ //
    // ✅ ACCESSORS                                //
    // ============================================ //

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    /**
     * Get status badge class.
     */
    public function getStatusBadgeAttribute(): string
    {
        return $this->is_active ? 'success' : 'secondary';
    }

    /**
     * Get the full display name with code.
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = [];
        if ($this->code) {
            $parts[] = $this->code;
        }
        $parts[] = $this->name;
        return implode(' - ', $parts);
    }

    /**
     * Get the location coordinates as a string.
     */
    public function getLocationAttribute(): ?string
    {
        if ($this->latitude && $this->longitude) {
            return "{$this->latitude}, {$this->longitude}";
        }
        return null;
    }

    /**
     * Get the count of properties in this zone.
     */
    public function getPropertiesCountAttribute(): int
    {
        return $this->properties()->count();
    }

    /**
     * Get the count of active properties in this zone.
     */
    public function getActivePropertiesCountAttribute(): int
    {
        return $this->properties()->where('status', 'active')->count();
    }

    /**
     * Get the count of waste collection requests in this zone.
     */
    public function getRequestsCountAttribute(): int
    {
        return $this->wasteCollectionRequests()->count();
    }

    /**
     * Get the count of pending requests in this zone.
     */
    public function getPendingRequestsCountAttribute(): int
    {
        return $this->wasteCollectionRequests()
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Get the count of completed requests in this zone.
     */
    public function getCompletedRequestsCountAttribute(): int
    {
        return $this->wasteCollectionRequests()
            ->where('status', 'completed')
            ->count();
    }

    // ============================================ //
    // ✅ HELPER METHODS                           //
    // ============================================ //

    /**
     * Activate the zone.
     */
    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    /**
     * Deactivate the zone.
     */
    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }

    /**
     * Toggle the zone's active status.
     */
    public function toggleActive(): bool
    {
        $this->is_active = !$this->is_active;
        return $this->save();
    }

    /**
     * Assign personnel to this zone.
     */
    public function assignPersonnel($personnelId): bool
    {
        $this->assigned_personnel_id = $personnelId;
        return $this->save();
    }

    /**
     * Unassign personnel from this zone.
     */
    public function unassignPersonnel(): bool
    {
        $this->assigned_personnel_id = null;
        return $this->save();
    }

    /**
     * Check if zone has assigned personnel.
     */
    public function hasAssignedPersonnel(): bool
    {
        return !is_null($this->assigned_personnel_id);
    }

    /**
     * Get the zone's coverage area as a string.
     */
    public function getCoverageArea(): string
    {
        $parts = [];
        if ($this->region) {
            $parts[] = $this->region;
        }
        if ($this->district) {
            $parts[] = $this->district;
        }
        return implode(' - ', $parts) ?: 'No coverage area specified';
    }

    /**
     * Check if zone has geographical data.
     */
    public function hasGeographicalData(): bool
    {
        return ($this->latitude && $this->longitude) || 
               ($this->coordinates && count($this->coordinates) > 0) ||
               ($this->boundary_coordinates && count($this->boundary_coordinates) > 0);
    }

    /**
     * Get zone statistics.
     */
    public function getStats(): array
    {
        return [
            'total_properties' => $this->properties_count,
            'active_properties' => $this->active_properties_count,
            'total_requests' => $this->requests_count,
            'pending_requests' => $this->pending_requests_count,
            'completed_requests' => $this->completed_requests_count,
            'has_assigned_personnel' => $this->hasAssignedPersonnel(),
            'assigned_personnel_name' => $this->assignedPersonnel?->full_name,
            'is_active' => $this->is_active,
            'coverage_area' => $this->getCoverageArea(),
        ];
    }
}