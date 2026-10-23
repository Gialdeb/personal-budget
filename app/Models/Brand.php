<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\TrackedItems\WebsiteLogoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Brand extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = ['name', 'aliases', 'alternative_domains', 'domain', 'category', 'logo_path', 'logo_mime_type', 'is_published'];

    protected $attributes = ['is_published' => false];

    protected function casts(): array
    {
        return ['aliases' => 'array', 'alternative_domains' => 'array', 'is_published' => 'boolean'];
    }

    public static function normalize(string $value): string
    {
        return Str::lower(Str::ascii(Str::squish($value)));
    }

    public static function normalizeDomain(string $value): string
    {
        $url = app(WebsiteLogoService::class)->normalizeUrl($value);

        return preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST));
    }

    /** @param list<string> $domains */
    public static function conflictingDomains(array $domains, ?self $except = null): bool
    {
        return self::query()->when($except?->exists, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->get(['domain', 'alternative_domains'])->contains(function (self $brand) use ($domains): bool {
                $existing = array_map(self::normalizeDomain(...), [$brand->domain, ...($brand->alternative_domains ?? [])]);

                return array_intersect($domains, $existing) !== [];
            });
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            $brand->name = Str::squish($brand->name);
            $brand->domain = Str::lower($brand->domain);
            $brand->normalized_name = self::normalize($brand->name);
            $brand->identity_key = hash('sha256', $brand->normalized_name.'|'.$brand->domain);
            $brand->search_text = self::normalize(implode(' ', [$brand->name, $brand->domain, ...($brand->aliases ?? []), ...($brand->alternative_domains ?? [])]));
        });
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function scopeSearch(Builder $query, ?string $search): void
    {
        if (filled($search)) {
            try {
                $search = self::normalizeDomain($search);
            } catch (ValidationException) {
                // A name or partial domain remains a text search.
            }
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], self::normalize($search));
            $query->where('search_text', 'like', '%'.$term.'%');
        }
    }
}
