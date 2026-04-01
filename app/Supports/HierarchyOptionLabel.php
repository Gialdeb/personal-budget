<?php

namespace App\Supports;

use Illuminate\Support\Collection;

class HierarchyOptionLabel
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, string>
     */
    public static function labelsByKey(array $items, string $key = 'uuid'): array
    {
        $pathCounts = collect($items)
            ->countBy(fn (array $item): string => static::baseLabel($item));

        return collect($items)
            ->mapWithKeys(function (array $item) use ($key, $pathCounts): array {
                $identifier = (string) ($item[$key] ?? '');
                $baseLabel = static::baseLabel($item);

                if ($identifier === '') {
                    return [];
                }

                $label = (int) ($pathCounts[$baseLabel] ?? 0) > 1 && filled($item['slug'] ?? null)
                    ? sprintf('%s · %s', $baseLabel, $item['slug'])
                    : $baseLabel;

                return [$identifier => $label];
            })
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public static function withDisambiguatedLabels(Collection $items, string $key = 'uuid'): Collection
    {
        $labelsByKey = static::labelsByKey($items->values()->all(), $key);

        return $items->map(function (array $item) use ($key, $labelsByKey): array {
            $identifier = (string) ($item[$key] ?? '');

            return [
                ...$item,
                'label' => $identifier !== ''
                    ? ($labelsByKey[$identifier] ?? static::baseLabel($item))
                    : static::baseLabel($item),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected static function baseLabel(array $item): string
    {
        return (string) ($item['full_path'] ?? $item['name'] ?? $item['slug'] ?? 'Elemento');
    }
}
