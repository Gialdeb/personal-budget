<?php

namespace App\Http\Resources\Sharing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'role' => $this->role?->value,
            'role_label' => $this->role?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
            'left_reason' => $this->left_reason,
            'revoked_at' => $this->revoked_at,
            'restored_at' => $this->restored_at,
            'user' => [
                'uuid' => $this->user?->uuid,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
        ];
    }
}
