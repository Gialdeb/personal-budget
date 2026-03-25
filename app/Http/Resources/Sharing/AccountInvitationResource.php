<?php

namespace App\Http\Resources\Sharing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'role' => $this->role?->value,
            'role_label' => $this->role?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'expires_at' => $this->expires_at,
            'accepted_at' => $this->accepted_at,
            'created_at' => $this->created_at,
        ];
    }
}
