<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangelogReleaseDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'version_label' => $this->version_label,
            'channel' => $this->channel,
            'is_published' => (bool) $this->is_published,
            'is_pinned' => (bool) $this->is_pinned,
            'published_at' => $this->published_at?->format('Y-m-d\TH:i'),
            'sort_order' => $this->sort_order,
            'translations' => $this->translations
                ->sortBy('locale')
                ->values()
                ->map(fn ($translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'summary' => $translation->summary,
                    'excerpt' => $translation->excerpt,
                ])
                ->all(),
            'sections' => $this->sections
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($section): array => [
                    'key' => $section->key,
                    'sort_order' => $section->sort_order,
                    'translations' => $section->translations
                        ->sortBy('locale')
                        ->values()
                        ->map(fn ($translation): array => [
                            'locale' => $translation->locale,
                            'label' => $translation->label,
                        ])
                        ->all(),
                    'items' => $section->items
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($item): array => [
                            'sort_order' => $item->sort_order,
                            'screenshot_key' => $item->screenshot_key,
                            'link_url' => $item->link_url,
                            'link_label' => $item->link_label,
                            'item_type' => $item->item_type,
                            'platform' => $item->platform,
                            'translations' => $item->translations
                                ->sortBy('locale')
                                ->values()
                                ->map(fn ($translation): array => [
                                    'locale' => $translation->locale,
                                    'title' => $translation->title,
                                    'body' => $translation->body,
                                ])
                                ->all(),
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
