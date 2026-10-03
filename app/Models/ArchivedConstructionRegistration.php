<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArchivedConstructionRegistration extends Model
{
    protected $table = 'archived_construction_registrations';

    protected $fillable = [
        // All the same fields as LandlordConstructionRegistration plus archive metadata
        'registration_type', 'purpose', 'name', 'email', 'primary_phone',
        'additional_phones', 'landlord_id', 'property_name', 'plot_number',
        'street_name', 'digital_address', 'land_description', 'land_ownership_document',
        'zone', 'section', 'property_type', 'custom_property_type', 'property_status',
        'estimated_bedrooms', 'has_plans', 'estimated_completion', 'construction_documents',
        'existing_property_type', 'existing_custom_property_type', 'existing_property_status',
        'existing_bedrooms', 'existing_bathrooms', 'year_built', 'property_photos',
        'property_documents', 'has_tenants', 'tenant_count', 'tenant_data',
        'status', 'access_token', 'submitted_at', 'reviewed_at', 'reviewed_by',
        'approved_property_id', 'cancelled_at', 'rejection_reason', 'admin_notes',
        'info_requested', 'assigned_to', 'assigned_at',
        
        // Archive metadata
        'archived_at', 'archive_year', 'archive_reason', 'archived_by', 'original_id',
        'original_created_at', 'original_updated_at', 'original_deleted_at'
    ];

    protected $casts = [
        'additional_phones' => 'array',
        'construction_documents' => 'array',
        'property_photos' => 'array',
        'property_documents' => 'array',
        'tenant_data' => 'array',
        'has_plans' => 'boolean',
        'has_tenants' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'assigned_at' => 'datetime',
        'archived_at' => 'datetime',
        'original_created_at' => 'datetime',
        'original_updated_at' => 'datetime',
        'original_deleted_at' => 'datetime',
    ];

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    // Scope for year
    public function scopeByYear($query, $year)
    {
        return $query->where('archive_year', $year);
    }

    // Scope for permanent deletion (older than X years)
    public function scopeOlderThan($query, $years)
    {
        $cutoffYear = now()->subYears($years)->year;
        return $query->where('archive_year', '<', $cutoffYear);
    }
}