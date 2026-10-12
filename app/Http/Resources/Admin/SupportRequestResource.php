<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'app_version' => $this->app_version,
            'closed_at' => $this->closed_at?->toJSON(),
            'last_admin_reply_at' => $this->last_admin_reply_at?->toJSON(),
            'last_user_message_at' => $this->last_user_message_at?->toJSON(),
            'category' => $this->category,
            'subject' => $this->subject,
            'status' => $this->status,
            'locale' => $this->locale,
            'source_url' => $this->source_url,
            'source_route' => $this->source_route,
            'updated_at' => $this->updated_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'user' => $this->user === null ? null : [
                'uuid' => $this->user->uuid,
                'name' => trim(implode(' ', array_filter([
                    $this->user->name,
                    $this->user->surname,
                ]))),
                'email' => $this->user->email,
            ],
        ];
    }
}
