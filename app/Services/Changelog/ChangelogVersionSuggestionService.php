<?php

namespace App\Services\Changelog;

use App\Models\ChangelogRelease;
use App\Support\Changelog\ChangelogVersion;

class ChangelogVersionSuggestionService
{
    /**
     * @return array{
     *     latest: ?string,
     *     patch: array{beta: string, stable: string},
     *     minor: array{beta: string, stable: string},
     *     major: array{beta: string, stable: string}
     * }
     */
    public function suggestions(?ChangelogRelease $latestRelease = null): array
    {
        $latestRelease ??= ChangelogRelease::query()->ordered()->first();

        $baseVersion = $latestRelease instanceof ChangelogRelease
            ? ChangelogVersion::fromRelease($latestRelease)
            : new ChangelogVersion(0, 0, 0, null, 'stable');

        return [
            'latest' => $latestRelease?->version_label,
            'patch' => [
                'beta' => $baseVersion->incrementPatch('beta')->label(),
                'stable' => $baseVersion->incrementPatch('stable')->label(),
            ],
            'minor' => [
                'beta' => $baseVersion->incrementMinor('beta')->label(),
                'stable' => $baseVersion->incrementMinor('stable')->label(),
            ],
            'major' => [
                'beta' => $baseVersion->incrementMajor('beta')->label(),
                'stable' => $baseVersion->incrementMajor('stable')->label(),
            ],
        ];
    }
}
