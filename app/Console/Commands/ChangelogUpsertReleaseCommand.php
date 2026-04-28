<?php

namespace App\Console\Commands;

use App\Models\ChangelogRelease;
use App\Services\Changelog\ChangelogReleaseUpsertService;
use App\Support\Changelog\ChangelogVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

#[Signature('changelog:upsert-release
    {version : Target release version label, for example 1.0.1}
    {--title-it= : Italian public title}
    {--title-en= : English public title}
    {--summary-it= : Italian public summary HTML}
    {--summary-en= : English public summary HTML}
    {--body-it= : Italian public body}
    {--body-en= : English public body}
    {--excerpt-it= : Italian short excerpt for cards and metadata}
    {--excerpt-en= : English short excerpt for cards and metadata}
    {--section-key=highlights : Default section key when no JSON payload is provided}
    {--section-label-it=In evidenza : Italian default section label}
    {--section-label-en=Highlights : English default section label}
    {--item-type=highlight : Default item type when no JSON payload is provided}
    {--platform=all : Default item platform when no JSON payload is provided}
    {--screenshot-key= : Optional default item screenshot key}
    {--link-url= : Optional default item link URL}
    {--link-label= : Optional default item link label}
    {--payload= : Full changelog payload as JSON. Overrides the single-section options.}
    {--payload-file= : Path to a JSON file containing the full changelog payload. Overrides the single-section options.}
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

        try {
            $payload = $this->jsonPayload() ?? $this->defaultPayload($version);
        } catch (JsonException $exception) {
            $this->components->error("The changelog JSON payload is invalid: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $payload = $this->withReleaseDefaults($payload, $version);

        try {
            $payloadVersion = ChangelogVersion::parse(
                versionLabel: (string) $payload['version_label'],
                channel: (string) $payload['channel'],
            );
        } catch (InvalidArgumentException) {
            $this->components->error('The resolved changelog payload version is invalid.');

            return self::FAILURE;
        }

        $existingRelease = ChangelogRelease::query()
            ->where('version_label', $payloadVersion->label())
            ->first();

        $release = $upsertService->upsert($existingRelease, $payload);

        $action = $existingRelease === null ? 'created' : 'updated';

        $this->components->info(sprintf(
            'Changelog release %s successfully.',
            $action,
        ));

        $this->table(
            ['Action', 'Version', 'Channel', 'Published', 'Pinned', 'Sections', 'Items'],
            [[
                $action,
                $release->version_label,
                $release->channel,
                $release->is_published ? 'yes' : 'no',
                $release->is_pinned ? 'yes' : 'no',
                $release->sections->count(),
                $release->sections->sum(fn ($section): int => $section->items->count()),
            ]],
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(ChangelogVersion $version): array
    {
        $titleIt = $this->stringOption('title-it', self::DEFAULT_TITLE_IT);
        $titleEn = $this->stringOption('title-en', self::DEFAULT_TITLE_EN);
        $bodyIt = $this->paragraph($this->stringOption('body-it', self::DEFAULT_BODY_IT));
        $bodyEn = $this->paragraph($this->stringOption('body-en', self::DEFAULT_BODY_EN));

        return [
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
                    'title' => $titleIt,
                    'summary' => $this->stringOption('summary-it', $bodyIt),
                    'excerpt' => $this->stringOption('excerpt-it', self::DEFAULT_EXCERPT_IT),
                ],
                [
                    'locale' => 'en',
                    'title' => $titleEn,
                    'summary' => $this->stringOption('summary-en', $bodyEn),
                    'excerpt' => $this->stringOption('excerpt-en', self::DEFAULT_EXCERPT_EN),
                ],
            ],
            'sections' => [
                [
                    'key' => $this->stringOption('section-key', 'highlights'),
                    'sort_order' => 1,
                    'translations' => [
                        ['locale' => 'it', 'label' => $this->stringOption('section-label-it', 'In evidenza')],
                        ['locale' => 'en', 'label' => $this->stringOption('section-label-en', 'Highlights')],
                    ],
                    'items' => [
                        [
                            'sort_order' => 1,
                            'screenshot_key' => $this->nullableStringOption('screenshot-key'),
                            'link_url' => $this->nullableStringOption('link-url'),
                            'link_label' => $this->nullableStringOption('link-label'),
                            'item_type' => $this->nullableStringOption('item-type') ?? 'highlight',
                            'platform' => $this->nullableStringOption('platform') ?? 'all',
                            'translations' => [
                                [
                                    'locale' => 'it',
                                    'title' => $titleIt,
                                    'body' => $bodyIt,
                                ],
                                [
                                    'locale' => 'en',
                                    'title' => $titleEn,
                                    'body' => $bodyEn,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws JsonException
     */
    private function jsonPayload(): ?array
    {
        $json = $this->option('payload');
        $payloadFile = $this->option('payload-file');

        if (filled($json) && filled($payloadFile)) {
            throw new JsonException('Use either --payload or --payload-file, not both.');
        }

        if (filled($payloadFile)) {
            $path = (string) $payloadFile;

            if (! File::exists($path)) {
                throw new JsonException("File [{$path}] does not exist.");
            }

            $json = File::get($path);
        }

        if (! is_string($json) || trim($json) === '') {
            return null;
        }

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new JsonException('The decoded payload must be a JSON object.');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withReleaseDefaults(array $payload, ChangelogVersion $version): array
    {
        return array_replace([
            'version_label' => $version->label(),
            'channel' => $version->channel,
            'is_published' => $this->booleanOption('published', true),
            'is_pinned' => $this->booleanOption('pinned', false),
            'published_at' => $this->option('published-at') ?: null,
            'sort_order' => $this->option('sort-order') !== null
                ? (int) $this->option('sort-order')
                : null,
            'translations' => [],
            'sections' => [],
        ], Arr::only($payload, [
            'version_label',
            'channel',
            'is_published',
            'is_pinned',
            'published_at',
            'sort_order',
            'translations',
            'sections',
        ]));
    }

    private function stringOption(string $name, string $default): string
    {
        $value = trim((string) ($this->option($name) ?? ''));

        return $value !== '' ? $value : $default;
    }

    private function nullableStringOption(string $name): ?string
    {
        $value = trim((string) ($this->option($name) ?? ''));

        return $value !== '' ? $value : null;
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
