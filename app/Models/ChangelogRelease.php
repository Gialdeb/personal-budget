<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ChangelogReleaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangelogRelease extends Model
{
    /** @use HasFactory<ChangelogReleaseFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'version_label',
        'version_major',
        'version_minor',
        'version_patch',
        'version_suffix',
        'channel',
        'is_published',
        'is_pinned',
        'published_at',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(ChangelogReleaseTranslation::class, 'release_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ChangelogSection::class, 'release_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw("case when channel = 'stable' then 1 else 0 end desc")
            ->orderByDesc('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public function resolveTranslation(string $locale, string $fallbackLocale): ?ChangelogReleaseTranslation
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', $fallbackLocale)
            ?? $translations->first();
    }

    public function availableLocales(): array
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->pluck('locale')
            ->filter()
            ->values()
            ->all();
    }
}
