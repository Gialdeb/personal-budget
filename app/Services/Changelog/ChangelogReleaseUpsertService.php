<?php

namespace App\Services\Changelog;

use App\Models\ChangelogItem;
use App\Models\ChangelogItemTranslation;
use App\Models\ChangelogRelease;
use App\Models\ChangelogReleaseTranslation;
use App\Models\ChangelogSection;
use App\Models\ChangelogSectionTranslation;
use App\Support\Changelog\ChangelogVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ChangelogReleaseUpsertService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(?ChangelogRelease $release, array $payload): ChangelogRelease
    {
        return DB::transaction(function () use ($release, $payload): ChangelogRelease {
            $version = ChangelogVersion::parse(
                versionLabel: (string) $payload['version_label'],
                channel: (string) $payload['channel'],
            );

            $release ??= new ChangelogRelease;

            $release->fill([
                'version_label' => $version->label(),
                'version_major' => $version->major,
                'version_minor' => $version->minor,
                'version_patch' => $version->patch,
                'version_suffix' => $version->suffix,
                'channel' => $version->channel,
                'is_published' => (bool) ($payload['is_published'] ?? false),
                'is_pinned' => (bool) ($payload['is_pinned'] ?? false),
                'published_at' => (bool) ($payload['is_published'] ?? false)
                    ? ($payload['published_at'] ?? $release->published_at ?? now())
                    : null,
                'sort_order' => Arr::get($payload, 'sort_order'),
            ]);
            $release->save();

            $release->translations()->delete();
            $release->sections()->delete();

            foreach ((array) $payload['translations'] as $translation) {
                $releaseTranslation = new ChangelogReleaseTranslation;
                $releaseTranslation->locale = (string) $translation['locale'];
                $releaseTranslation->title = (string) $translation['title'];
                $releaseTranslation->summary = $translation['summary'] ?? null;
                $releaseTranslation->excerpt = $translation['excerpt'] ?? null;

                $release->translations()->save($releaseTranslation);
            }

            foreach ((array) $payload['sections'] as $sectionPayload) {
                $section = new ChangelogSection;
                $section->key = (string) $sectionPayload['key'];
                $section->sort_order = (int) ($sectionPayload['sort_order'] ?? 1);

                $release->sections()->save($section);

                foreach ((array) $sectionPayload['translations'] as $translation) {
                    $sectionTranslation = new ChangelogSectionTranslation;
                    $sectionTranslation->locale = (string) $translation['locale'];
                    $sectionTranslation->label = (string) $translation['label'];

                    $section->translations()->save($sectionTranslation);
                }

                foreach ((array) ($sectionPayload['items'] ?? []) as $itemPayload) {
                    $item = new ChangelogItem;
                    $item->sort_order = (int) ($itemPayload['sort_order'] ?? 1);
                    $item->screenshot_key = $itemPayload['screenshot_key'] ?? null;
                    $item->link_url = $itemPayload['link_url'] ?? null;
                    $item->link_label = $itemPayload['link_label'] ?? null;
                    $item->item_type = $itemPayload['item_type'] ?? null;
                    $item->platform = $itemPayload['platform'] ?? null;

                    $section->items()->save($item);

                    foreach ((array) $itemPayload['translations'] as $translation) {
                        $itemTranslation = new ChangelogItemTranslation;
                        $itemTranslation->locale = (string) $translation['locale'];
                        $itemTranslation->title = $translation['title'] ?? null;
                        $itemTranslation->body = (string) $translation['body'];

                        $item->translations()->save($itemTranslation);
                    }
                }
            }

            return $release->fresh([
                'translations',
                'sections.translations',
                'sections.items.translations',
            ]);
        });
    }
}
