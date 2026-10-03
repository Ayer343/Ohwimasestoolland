<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ─────────────────────────────────────────────
            // Identity
            // ─────────────────────────────────────────────
            'id'                    => $this->id,

            // ✅ THE FIX: send `property_name` (what Flutter reads).
            //    Keep `house_name` as an alias for any legacy consumer.
            'property_name'         => $this->property_name,
            'house_name'            => $this->property_name,

            // ─────────────────────────────────────────────
            // Address
            // ─────────────────────────────────────────────
            'house_number'          => $this->house_number,
            'street_name'           => $this->street_name,
            'block_number'          => $this->block_number,
            'digital_address'       => $this->digital_address,
            'zone'                  => $this->zone,
            'section'               => $this->section,
            'description'           => $this->description,

            // ─────────────────────────────────────────────
            // Registration / naming
            // ─────────────────────────────────────────────
            'registration_pattern'  => $this->registration_pattern,

            // ─────────────────────────────────────────────
            // Foreign keys
            // ─────────────────────────────────────────────
            'landlord_id'           => $this->landlord_id,
            'registration_plan_id'  => $this->registration_plan_id,
            'property_type_id'      => $this->property_type_id,
            'custom_property_type'  => $this->custom_property_type,
            'registered_by_id'      => $this->registered_by_id,
            'agent_id'              => $this->agent_id,

            // ─────────────────────────────────────────────
            // Status & lifecycle
            // ─────────────────────────────────────────────
            'status'                => $this->status,
            'construction_status'   => $this->construction_status,

            // ─────────────────────────────────────────────
            // Construction details
            // ─────────────────────────────────────────────
            'bedrooms'              => $this->bedrooms,
            'bathrooms'             => $this->bathrooms,
            'estimated_completion'  => optional($this->estimated_completion)->toIso8601String(),
            'completed_at'          => optional($this->completed_at)->toIso8601String(),
            'completion_notes'      => $this->completion_notes,
            'has_plans'             => (bool) $this->has_plans,
            'construction_documents'=> $this->construction_documents ?? [],
            'construction_notes'    => $this->construction_notes,

            // ─────────────────────────────────────────────
            // Occupancy
            // ─────────────────────────────────────────────
            'is_rented'             => (bool) $this->is_rented,

            // ─────────────────────────────────────────────
            // Extra
            // ─────────────────────────────────────────────
            'metadata'              => $this->metadata ?? [],

            // ─────────────────────────────────────────────
            // Relations — only when eager-loaded
            // ─────────────────────────────────────────────
            'landlord'              => new UserResource($this->whenLoaded('landlord')),
            'registration_plan'     => new RegistrationPlanResource($this->whenLoaded('registrationPlan')),
            'registered_by'         => new UserResource($this->whenLoaded('registeredBy')),
            'property_type'         => new PropertyTypeResource($this->whenLoaded('propertyType')),
            'tenants'               => UserResource::collection($this->whenLoaded('tenants')),
            'photos'                => \App\Http\Resources\PhotoResource::collection($this->whenLoaded('photos')),

            // ─────────────────────────────────────────────
            // Counts — only present when withCount() was called
            // ─────────────────────────────────────────────
            'photos_count'          => $this->when(
                isset($this->photos_count),
                fn () => (int) $this->photos_count
            ),
            'tenants_count'         => $this->when(
                isset($this->tenants_count),
                fn () => (int) $this->tenants_count
            ),

            // ─────────────────────────────────────────────
            // Timestamps
            // ─────────────────────────────────────────────
            'created_at'            => optional($this->created_at)->toIso8601String(),
            'updated_at'            => optional($this->updated_at)->toIso8601String(),
            'deleted_at'            => optional($this->deleted_at)->toIso8601String(),
        ];
    }
}