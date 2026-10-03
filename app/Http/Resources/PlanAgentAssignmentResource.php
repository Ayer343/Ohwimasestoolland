<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanAgentAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'plan_id'                => $this->plan_id,
            'agent_id'               => $this->agent_id,
            'assigned_by'            => $this->assigned_by,

            // Nested relations — only included when eager-loaded
            'agent'                  => new UserResource($this->whenLoaded('agent')),
            'assigner'               => new UserResource($this->whenLoaded('assigner')),

            // Status + activity
            'is_active'              => (bool) ($this->is_active ?? true),
            'properties_registered'  => (int) ($this->properties_registered ?? 0),

            // Timestamps
            'assigned_at'            => optional($this->assigned_at)->toIso8601String(),
            'unassigned_at'          => optional($this->unassigned_at)->toIso8601String(),
            'unassignment_reason'    => $this->unassignment_reason,

            'created_at'             => optional($this->created_at)->toIso8601String(),
            'updated_at'             => optional($this->updated_at)->toIso8601String(),
        ];
    }
}