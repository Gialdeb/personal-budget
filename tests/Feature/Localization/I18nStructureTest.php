<?php

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
    ];

    foreach (['it', 'en'] as $locale) {
        foreach ($requiredNamespaces as $namespace) {
            $path = lang_path("{$locale}/{$namespace}.php");

            expect($path)->toBeFile();
            expect(require $path)->toBeArray()->not->toBeEmpty();
        }
    }
});

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
