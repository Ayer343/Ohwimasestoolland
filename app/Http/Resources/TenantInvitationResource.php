<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantInvitationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'channels' => $this->channels,
            'formatted_channels' => $this->formatted_channels,
            'sent_channels' => $this->sent_channels,
            'formatted_sent_channels' => $this->formatted_sent_channels,
            'status' => $this->status,
            'is_expired' => $this->is_expired,
            'is_active' => $this->is_active,
            'days_until_expiry' => $this->days_until_expiry,
            'invitation_url' => $this->invitation_url,
            'sent_at' => $this->sent_at,
            'expires_at' => $this->expires_at,
            'completed_at' => $this->completed_at,
            'failure_reason' => $this->failure_reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tenant' => $this->whenLoaded('tenant', fn() => new TenantResource($this->tenant)),
            'property' => $this->whenLoaded('property', fn() => new PropertyResource($this->property)),
            'inviter' => $this->whenLoaded('inviter', fn() => new UserResource($this->inviter)),
            'registered_user' => $this->whenLoaded('registeredUser', fn() => new UserResource($this->registeredUser)),
        ];
    }
}