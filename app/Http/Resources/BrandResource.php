<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->uuid, 'name' => $this->name, 'aliases' => $this->aliases ?? [], 'domain' => $this->domain, 'alternative_domains' => $this->alternative_domains ?? [], 'category' => $this->category, 'logo_url' => $this->logo_path ? route('brands.logo', $this->uuid) : null];
    }
}
