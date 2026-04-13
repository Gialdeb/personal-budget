<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportRequestDetailResource extends JsonResource
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
            'category' => $this->category,
            'subject' => $this->subject,
            'message' => $this->message,
            'locale' => $this->locale,
            'status' => $this->status,
            'source_url' => $this->source_url,
            'source_route' => $this->source_route,
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
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
