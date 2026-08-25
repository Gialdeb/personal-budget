<?php

namespace Database\Factories;

use App\Models\WebsiteIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteIdentity>
 */
class WebsiteIdentityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'domain' => $domain,
            'canonical_url' => 'https://'.$domain,
            'logo_path' => null,
            'logo_mime_type' => null,
            'logo_source_url' => null,
            'status' => 'pending',
            'fetched_at' => null,
            'retry_after' => null,
        ];
    }
}
