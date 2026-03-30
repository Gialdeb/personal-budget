<?php

namespace App\Http\Resources\Changelog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicChangelogReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = (string) $request->attributes->get('changelog_locale', 'it');
        $fallbackLocale = (string) $request->attributes->get('changelog_fallback_locale', 'en');
        $translation = $this->resolveTranslation($locale, $fallbackLocale);

        return [
            'uuid' => $this->uuid,
            'version_label' => $this->version_label,
            'channel' => $this->channel,
            'is_pinned' => (bool) $this->is_pinned,
            'published_at' => $this->published_at?->toJSON(),
            'locale' => $translation?->locale ?? $locale,
            'available_locales' => $this->availableLocales(),
            'title' => $translation?->title,
            'summary' => $translation?->summary,
            'excerpt' => $translation?->excerpt,
            'sections' => $this->sections
                ->sortBy('sort_order')
                ->values()
                ->map(function ($section) use ($locale, $fallbackLocale): array {
                    $sectionTranslation = $section->resolveTranslation($locale, $fallbackLocale);

                    return [
                        'key' => $section->key,
                        'label' => $sectionTranslation?->label,
                        'sort_order' => $section->sort_order,
                        'items' => $section->items
                            ->sortBy('sort_order')
                            ->values()
                            ->map(function ($item) use ($locale, $fallbackLocale): array {
                                $itemTranslation = $item->resolveTranslation($locale, $fallbackLocale);

                                return [
                                    'sort_order' => $item->sort_order,
                                    'screenshot_key' => $item->screenshot_key,
                                    'link_url' => $item->link_url,
                                    'link_label' => $item->link_label,
                                    'item_type' => $item->item_type,
                                    'platform' => $item->platform,
                                    'title' => $itemTranslation?->title,
                                    'body' => $itemTranslation?->body,
                                ];
                            })
                            ->all(),
                    ];
                })
                ->all(),
        ];
    }
}
