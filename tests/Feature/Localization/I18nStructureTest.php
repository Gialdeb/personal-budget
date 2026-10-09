<?php

use App\Services\Communication\CommunicationPreferenceCatalog;
use App\Support\ContextualHelp\CurrentContextualHelpResolver;
use App\Support\Pwa\PwaManifestData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

it('provides the required backend i18n namespaces for supported locales', function () {
    $requiredNamespaces = [
        'app',
        'nav',
        'dashboard',
        'planning',
        'export',
        'imports',
        'accounts',
        'categories',
        'tracked_items',
        'transactions',
        'settings',
        'ui',
    ];

    foreach (['it', 'en'] as $locale) {
        foreach ($requiredNamespaces as $namespace) {
            $path = lang_path("{$locale}/{$namespace}.php");

            expect($path)->toBeFile();
            expect(require $path)->toBeArray()->not->toBeEmpty();
        }
    }
});

it('keeps backend message keys and placeholders aligned in Italian and English', function () {
    foreach (File::files(lang_path('it')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $italian = Arr::dot(require $file->getPathname());
        $english = Arr::dot(require lang_path('en/'.$file->getFilename()));

        expect(array_diff_key($italian, $english))->toBeEmpty($file->getFilename());
        expect(array_diff_key($english, $italian))->toBeEmpty($file->getFilename());

        foreach ($italian as $key => $value) {
            if (! is_string($value) || ! is_string($english[$key])) {
                continue;
            }

            preg_match_all('/:[a-zA-Z_]+/', $value, $itPlaceholders);
            preg_match_all('/:[a-zA-Z_]+/', $english[$key], $enPlaceholders);
            sort($itPlaceholders[0]);
            sort($enPlaceholders[0]);
            expect($enPlaceholders[0])->toBe($itPlaceholders[0], $file->getFilename().'.'.$key);
        }
    }
});

it('localizes manifest shortcuts and contextual help without changing their identifiers', function (string $locale, string $overview) {
    app()->setLocale($locale);
    $manifest = app(PwaManifestData::class)->manifest();
    expect($manifest['lang'])->toBe($locale)
        ->and($manifest['shortcuts'][0]['name'])->toBe($overview)
        ->and($manifest['shortcuts'][0]['url'])->toBe('/dashboard?source=pwa-shortcut-dashboard');

    $options = app(CurrentContextualHelpResolver::class)->options();
    expect($options[0]['key'])->toBe('dashboard')
        ->and($options[0]['label'])->toBe($overview)
        ->and($options[0]['route_names'])->toBe(['dashboard', 'dashboard.data']);
})->with([['it', 'Panoramica'], ['en', 'Overview']]);

it('keeps core validation attributes available for both supported locales', function () {
    $requiredAttributes = [
        'account_uuid',
        'import_format_uuid',
        'locale',
        'transaction_date',
    ];

    foreach (['it', 'en'] as $locale) {
        /** @var array<string, mixed> $validation */
        $validation = require lang_path("{$locale}/validation.php");

        expect($validation)->toHaveKey('attributes');

        foreach ($requiredAttributes as $attribute) {
            expect($validation['attributes'])->toHaveKey($attribute);
        }
    }
});

it('keeps literal backend translation keys resolvable for supported locales', function () {
    $keys = collect(File::allFiles(app_path()))
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->flatMap(function (string $contents): array {
            preg_match_all("/__\\(\\s*'([^']+)'/", $contents, $singleQuotedMatches);
            preg_match_all('/__\\(\\s*"([^"]+)"/', $contents, $doubleQuotedMatches);

            return [
                ...($singleQuotedMatches[1] ?? []),
                ...($doubleQuotedMatches[1] ?? []),
            ];
        })
        ->filter(fn ($key): bool => is_string($key)
            && str_contains($key, '.')
            && ! str_contains($key, '$')
            && ! str_contains($key, '{')
            && ! str_contains($key, '}')
            && ! str_ends_with($key, '.'))
        ->unique()
        ->sort()
        ->values();

    foreach (['it', 'en'] as $locale) {
        foreach ($keys as $key) {
            $segments = explode('.', $key);
            $namespace = array_shift($segments);

            if (! is_string($namespace) || $namespace === '') {
                continue;
            }

            /** @var array<string, mixed> $messages */
            $messages = require lang_path("{$locale}/{$namespace}.php");
            $resolved = data_get($messages, implode('.', $segments));

            expect($resolved)->not->toBeNull("Missing [{$locale}] translation for key [{$key}]");
        }
    }
});

it('translates every built-in notification preference category in both languages', function () {
    config(['features.imports.enabled' => true]);
    $categories = app(CommunicationPreferenceCatalog::class)->categoryTopicMap();
    foreach (['it', 'en'] as $locale) {
        foreach (array_keys($categories) as $category) {
            foreach (['label', 'description'] as $field) {
                $key = "settings.profile.notifications.categories.{$category}.{$field}";
                expect(trans($key, [], $locale))->not->toBe($key);
            }
        }
    }
});
