<?php

namespace App\Models;

use Database\Factories\ChangelogSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangelogSection extends Model
{
    /** @use HasFactory<ChangelogSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'release_id',
        'key',
        'sort_order',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(ChangelogRelease::class, 'release_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ChangelogSectionTranslation::class, 'section_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChangelogItem::class, 'section_id');
    }

    public function resolveTranslation(string $locale, string $fallbackLocale): ?ChangelogSectionTranslation
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', $fallbackLocale)
            ?? $translations->first();
    }
}
