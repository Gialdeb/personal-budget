<?php

namespace App\Services\Brands;

use App\Models\Brand;
use App\Models\WebsiteIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BrandImportService
{
    /** @return array{data: array, has_more: bool, page: int} */
    public function candidates(?string $search, int $page): array
    {
        $domain = $this->domainExpression('website_identities.domain');
        $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search ?? '');
        $rows = WebsiteIdentity::query()->where('status', 'ready')->whereNotNull('logo_path')->where('logo_path', '<>', '')
            ->whereNotIn(DB::raw($domain), Brand::query()->selectRaw($this->domainExpression('brands.domain')))
            ->when(filled($search), fn ($query) => $query->whereLike('domain', '%'.$term.'%'))
            ->lazyById(100)
            ->filter(fn (WebsiteIdentity $identity): bool => $this->eligible($identity))
            ->unique(fn (WebsiteIdentity $identity): string => $this->normalizeDomain($identity->domain))
            ->skip(($page - 1) * 24)->take(25)->values()->collect();

        return ['data' => $rows->take(24)->map(fn (WebsiteIdentity $identity): array => [
            'uuid' => $identity->uuid, 'domain' => $this->normalizeDomain($identity->domain), 'logo_url' => $identity->logoUrl(),
        ])->all(), 'has_more' => $rows->count() > 24, 'page' => $page];
    }

    /** @param array<string, mixed> $attributes */
    public function import(string $uuid, array $attributes): Brand
    {
        return DB::transaction(function () use ($uuid, $attributes): Brand {
            $source = WebsiteIdentity::query()->where('uuid', $uuid)->first();
            if ($source === null) {
                throw ValidationException::withMessages(['source_logo_uuid' => 'Il logo non è più disponibile. Aggiorna l’elenco.']);
            }
            $domain = $this->normalizeDomain($source->domain);
            $sources = WebsiteIdentity::query()->whereRaw($this->domainExpression('website_identities.domain').' = ?', [$domain])
                ->orderBy('id')->lockForUpdate()->get();
            /** @var WebsiteIdentity|null $source */
            $source = $sources->firstWhere('uuid', $uuid);
            if ($source === null || ! $this->eligible($source)) {
                throw ValidationException::withMessages(['source_logo_uuid' => 'Il logo non è più disponibile. Aggiorna l’elenco.']);
            }
            if (($attributes['domain'] ?? null) !== $domain) {
                throw ValidationException::withMessages(['domain' => 'Il dominio deve corrispondere al logo selezionato.']);
            }
            if (Brand::query()->whereRaw($this->domainExpression('brands.domain').' = ?', [$domain])->exists()) {
                throw ValidationException::withMessages(['source_logo_uuid' => 'Questo dominio è già presente nel catalogo.']);
            }

            return Brand::query()->create([...$attributes, 'domain' => $domain, 'logo_path' => $source->logo_path, 'logo_mime_type' => $source->logo_mime_type]);
        }, 3);
    }

    protected function eligible(WebsiteIdentity $identity): bool
    {
        return $identity->hasStoredLogo() && in_array($identity->logo_mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'], true);
    }

    protected function normalizeDomain(string $domain): string
    {
        return preg_replace('/^www\./', '', strtolower(rtrim($domain, '.')));
    }

    protected function domainExpression(string $column): string
    {
        return "LOWER(RTRIM(CASE WHEN LOWER({$column}) LIKE 'www.%' THEN SUBSTR({$column}, 5) ELSE {$column} END, '.'))";
    }
}
