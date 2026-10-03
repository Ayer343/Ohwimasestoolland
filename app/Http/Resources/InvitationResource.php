<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'agent_id'             => $this->agent_id,
            'registration_plan_id' => $this->registration_plan_id,
            'invitation_method'    => $this->invitation_method,
            'status'               => $this->status,
            'delivery_status'      => $this->delivery_status,
            'delivery_message'     => $this->delivery_message,
            'sent_via'             => $this->sent_via,
            'message_id'           => $this->message_id,
            'retry_count'          => $this->retry_count ?? 0,
            'expires_at'           => optional($this->expires_at)->toIso8601String(),

            'agent'                => new UserResource($this->whenLoaded('agent')),
            'sentBy'               => new UserResource($this->whenLoaded('sentBy')),

            'created_at'           => optional($this->created_at)->toIso8601String(),
            'updated_at'           => optional($this->updated_at)->toIso8601String(),
        ];
    }
}