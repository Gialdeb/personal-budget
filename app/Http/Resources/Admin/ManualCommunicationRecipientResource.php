<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualCommunicationRecipientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullName = trim(implode(' ', array_filter([$this->name, $this->surname])));

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'surname' => $this->surname,
            'full_name' => $fullName !== '' ? $fullName : $this->email,
            'email' => $this->email,
            'label' => $fullName !== ''
                ? "{$fullName} · {$this->email}"
                : (string) $this->email,
        ];
    }
}
