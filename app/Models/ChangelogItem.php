<?php

namespace App\Models;

use Database\Factories\ChangelogItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangelogItem extends Model
{
    /** @use HasFactory<ChangelogItemFactory> */
    use HasFactory;

    protected $fillable = [
        'section_id',
        'sort_order',
        'screenshot_key',
        'link_url',
        'link_label',
        'item_type',
        'platform',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ChangelogSection::class, 'section_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ChangelogItemTranslation::class, 'item_id');
    }

    public function resolveTranslation(string $locale, string $fallbackLocale): ?ChangelogItemTranslation
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', $fallbackLocale)
            ?? $translations->first();
    }
}
