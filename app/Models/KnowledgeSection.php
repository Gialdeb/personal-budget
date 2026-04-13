<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\KnowledgeSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSection extends Model
{
    /** @use HasFactory<KnowledgeSectionFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'slug',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(KnowledgeSectionTranslation::class, 'section_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class, 'section_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function resolveTranslation(string $locale, string $fallbackLocale): ?KnowledgeSectionTranslation
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
