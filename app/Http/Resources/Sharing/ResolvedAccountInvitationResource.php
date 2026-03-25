<?php

namespace App\Http\Resources\Sharing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResolvedAccountInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'state' => $this['state'],
            'email' => $this['email'],
            'role' => $this['role'],
            'role_label' => $this['invitation']->role?->label(),
            'expires_at' => $this['expires_at'],
            'requires_registration' => $this['requires_registration'],
            'requires_login' => $this['requires_login'],
            'can_accept' => $this['can_accept'],
            'account' => $this['account'],
            'inviter' => $this['inviter'],
            'invitation' => [
                'uuid' => $this['invitation']->uuid,
                'status' => $this['invitation']->status?->value,
                'status_label' => $this['invitation']->status?->label(),
            ],
        ];
    }
}
