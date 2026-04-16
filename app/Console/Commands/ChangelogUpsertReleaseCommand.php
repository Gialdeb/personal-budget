<?php

namespace App\Console\Commands;

use App\Models\ChangelogRelease;
use App\Services\Changelog\ChangelogReleaseUpsertService;
use App\Support\Changelog\ChangelogVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('changelog:upsert-release
    {version : Target release version label, for example 1.0.1}
    {--title-it= : Italian public title}
    {--title-en= : English public title}
    {--body-it= : Italian public body}
    {--body-en= : English public body}
    {--excerpt-it= : Italian short excerpt for cards and metadata}
    {--excerpt-en= : English short excerpt for cards and metadata}
    {--published=1 : Whether the release should be published (1 or 0)}
    {--pinned=0 : Whether the release should be pinned (1 or 0)}
    {--sort-order= : Optional custom sort order}
    {--published-at= : Optional publication timestamp}
    {--channel=stable : Release channel, defaults to stable}')]
#[Description('Create or update a public changelog release in Italian and English without creating duplicates.')]
class ChangelogUpsertReleaseCommand extends Command
{
    private const DEFAULT_TITLE_IT = 'Esperienza mobile piu fluida e miglioramenti generali';

    private const DEFAULT_TITLE_EN = 'Improved mobile experience and overall refinements';

    private const DEFAULT_BODY_IT = 'Abbiamo introdotto una nuova serie di miglioramenti pensati per rendere Soamco Budget piu chiaro, piu fluido e piu affidabile nell\'uso quotidiano. L\'esperienza mobile e stata rifinita in diversi punti chiave, con interazioni piu semplici, flussi meglio guidati e una navigazione piu coerente. Abbiamo inoltre migliorato la stabilita generale dell\'app e la qualita di alcune sezioni importanti, per offrire un prodotto sempre piu ordinato, solido e piacevole da usare.';

    private const DEFAULT_BODY_EN = 'We\'ve introduced a new set of improvements designed to make Soamco Budget clearer, smoother, and more reliable in everyday use. The mobile experience has been refined across several key areas, with simpler interactions, better guided flows, and more consistent navigation. We\'ve also improved the app\'s overall stability and polished a number of important sections to deliver a product that feels more organized, solid, and pleasant to use.';

    private const DEFAULT_EXCERPT_IT = 'Esperienza mobile piu fluida, interazioni piu chiare e miglioramenti generali di stabilita e usabilita.';

    private const DEFAULT_EXCERPT_EN = 'A smoother mobile experience, clearer interactions, and overall stability and usability refinements.';

    public function handle(ChangelogReleaseUpsertService $upsertService): int
    {
        try {
            $version = ChangelogVersion::parse(
                versionLabel: (string) $this->argument('version'),
                channel: (string) $this->option('channel'),
            );
        } catch (InvalidArgumentException) {
            $this->components->error('The provided version label is invalid.');

            return self::FAILURE;
        }

        $existingRelease = ChangelogRelease::query()
            ->where('version_label', $version->label())
            ->first();

        $payload = [
            'version_label' => $version->label(),
            'channel' => $version->channel,
            'is_published' => $this->booleanOption('published', true),
            'is_pinned' => $this->booleanOption('pinned', false),
            'published_at' => $this->option('published-at') ?: null,
            'sort_order' => $this->option('sort-order') !== null
                ? (int) $this->option('sort-order')
                : null,
            'translations' => [
                [
                    'locale' => 'it',
                    'title' => $this->stringOption('title-it', self::DEFAULT_TITLE_IT),
                    'summary' => $this->paragraph($this->stringOption('body-it', self::DEFAULT_BODY_IT)),
                    'excerpt' => $this->stringOption('excerpt-it', self::DEFAULT_EXCERPT_IT),
                ],
                [
                    'locale' => 'en',
                    'title' => $this->stringOption('title-en', self::DEFAULT_TITLE_EN),
                    'summary' => $this->paragraph($this->stringOption('body-en', self::DEFAULT_BODY_EN)),
                    'excerpt' => $this->stringOption('excerpt-en', self::DEFAULT_EXCERPT_EN),
                ],
            ],
            'sections' => [
                [
                    'key' => 'highlights',
                    'sort_order' => 1,
                    'translations' => [
                        ['locale' => 'it', 'label' => 'In evidenza'],
                        ['locale' => 'en', 'label' => 'Highlights'],
                    ],
                    'items' => [
                        [
                            'sort_order' => 1,
                            'item_type' => 'highlight',
                            'platform' => 'all',
                            'translations' => [
                                [
                                    'locale' => 'it',
                                    'title' => $this->stringOption('title-it', self::DEFAULT_TITLE_IT),
                                    'body' => $this->paragraph($this->stringOption('body-it', self::DEFAULT_BODY_IT)),
                                ],
                                [
                                    'locale' => 'en',
                                    'title' => $this->stringOption('title-en', self::DEFAULT_TITLE_EN),
                                    'body' => $this->paragraph($this->stringOption('body-en', self::DEFAULT_BODY_EN)),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $release = $upsertService->upsert($existingRelease, $payload);

        $action = $existingRelease === null ? 'created' : 'updated';

        $this->components->info(sprintf(
            'Changelog release %s successfully.',
            $action,
        ));

        $this->table(
            ['Action', 'Version', 'Channel', 'Published', 'Pinned'],
            [[
                $action,
                $release->version_label,
                $release->channel,
                $release->is_published ? 'yes' : 'no',
                $release->is_pinned ? 'yes' : 'no',
            ]],
        );

        return self::SUCCESS;
    }

    private function stringOption(string $name, string $default): string
    {
        $value = trim((string) ($this->option($name) ?? ''));

        return $value !== '' ? $value : $default;
    }

    private function booleanOption(string $name, bool $default): bool
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function paragraph(string $text): string
    {
        return '<p>'.e($text).'</p>';
    }
}
