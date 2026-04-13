<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ContextualHelpEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContextualHelpEntry extends Model
{
    /** @use HasFactory<ContextualHelpEntryFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'page_key',
        'knowledge_article_id',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(ContextualHelpEntryTranslation::class);
    }

    public function knowledgeArticle(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('page_key')
            ->orderBy('created_at');
    }

    public function resolveTranslation(string $locale, string $fallbackLocale): ?ContextualHelpEntryTranslation
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
