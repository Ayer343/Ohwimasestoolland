<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'zone'                   => $this->zone,
            'section'                => $this->section,
            'naming_pattern'         => $this->naming_pattern,
            'starting_point'         => $this->starting_point,
            'next_available_name'    => $this->next_available_name,
            'estimated_houses'       => $this->estimated_houses,
            'sequence_type'          => $this->sequence_type,
            'status'                 => $this->status,
            'agent_assignment_type'  => $this->agent_assignment_type,
            'registration_start_date'=> $this->registration_start_date,
            'registration_end_date'  => $this->registration_end_date,
            'instructions'           => $this->instructions,
            'boundaries_description' => $this->boundaries_description,
            'progress_percentage'    => $this->progress_percentage ?? null,
            'properties_count'       => $this->whenCounted('properties'),
            'creator'                => new UserResource($this->whenLoaded('creator')),
            'assigned_agents'        => PlanAgentAssignmentResource::collection($this->whenLoaded('assignedAgents')),
            'recent_properties'      => PropertyResource::collection($this->whenLoaded('properties')),
            'invitations'            => InvitationResource::collection($this->whenLoaded('invitations')),
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
            'deleted_at'             => $this->deleted_at?->toIso8601String(),
        ];
    }
}