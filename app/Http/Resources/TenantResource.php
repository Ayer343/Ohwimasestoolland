<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'formatted_phone' => $this->formatted_phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'notes' => $this->notes,
            'is_registered' => $this->is_registered,
            'status' => $this->status,
            'properties_count' => $this->properties_count,
            'invitations_count' => $this->invitations_count,
            'successful_invitations_count' => $this->successful_invitations_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'creator' => $this->whenLoaded('creator', fn() => new UserResource($this->creator)),
            'properties' => $this->whenLoaded('properties', fn() => PropertyResource::collection($this->properties)),
            'invitations' => $this->whenLoaded('invitations', fn() => TenantInvitationResource::collection($this->invitations)),
        ];
    }
}