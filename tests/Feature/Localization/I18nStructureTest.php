<?php

use App\Http\Middleware\MaterializePendingTrackedItems;
use App\Http\Middleware\SetApplicationLocaleMiddleware;
use App\Mail\SupportRequestSubmittedMail;
use App\Models\SupportRequest;
use App\Services\Communication\CommunicationPreferenceCatalog;
use App\Support\ContextualHelp\CurrentContextualHelpResolver;
use App\Support\Pwa\PwaManifestData;
use Illuminate\Contracts\Http\Kernel;
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

it('translates form validation rules and field names in both languages', function (string $locale, string $required, string $positive) {
    app()->setLocale($locale);

    foreach (['enum', 'present', 'uploaded', 'dimensions', 'timezone'] as $rule) {
        expect(trans('validation.'.$rule, [], $locale))->not->toBe('validation.'.$rule);
    }

    $requiredValidator = validator([], ['amount' => ['required']]);
    expect($requiredValidator->errors()->first('amount'))->toBe($required);

    $positiveValidator = validator(['amount' => 0], ['amount' => ['numeric', 'gt:0']]);
    expect($positiveValidator->errors()->first('amount'))->toBe($positive);

    foreach ([
        [['month' => 13], ['month' => ['integer', 'between:1,12']]],
        [['currency' => 'EU'], ['currency' => ['string', 'size:3']]],
        [['currency' => 'EUR'], ['currency' => ['prohibited']]],
        [['source_uuid' => 'same', 'destination_uuid' => 'same'], ['destination_uuid' => ['different:source_uuid']]],
    ] as [$data, $rules]) {
        $message = validator($data, $rules)->errors()->first();
        expect($message)->not->toBeEmpty()->not->toContain('validation.');
        expect($message)->toMatch($locale === 'it' ? '/^(Il campo|I campi) /' : '/^The /');
    }
})->with([
    ['it', 'Il campo importo è obbligatorio.', 'Il campo importo deve essere maggiore di 0.'],
    ['en', 'The amount field is required.', 'The amount field must be greater than 0.'],
]);

it('keeps validation exception messages translatable throughout the backend', function () {
    foreach (File::allFiles(app_path()) as $file) {
        preg_match_all('/ValidationException::withMessages\(\[\s*[^\n]*?=>\s*\x27[^\x27]+\x27/s', $file->getContents(), $matches);
        expect($matches[0])->toBeEmpty($file->getRelativePathname());
    }
});

it('sets the request language before materializing pending form references', function () {
    $middleware = app(Kernel::class)->getMiddlewareGroups()['web'];

    expect(array_search(SetApplicationLocaleMiddleware::class, $middleware, true))
        ->toBeLessThan(array_search(MaterializePendingTrackedItems::class, $middleware, true));
});

it('renders support email labels in the current delivery language', function (string $locale, string $heading, string $unknownUser) {
    app()->setLocale($locale);
    $ticket = new SupportRequest([
        'uuid' => '00000000-0000-4000-8000-000000000001',
        'category' => 'general_support',
        'subject' => 'Example subject',
        'message' => 'Example message',
        'locale' => $locale,
    ]);
    $ticket->setRelation('user', null);

    $html = (new SupportRequestSubmittedMail($ticket))->render();
    expect($html)->toContain('lang="'.$locale.'"')->toContain($heading)->toContain($unknownUser);
})->with([
    ['it', 'Nuova richiesta di assistenza', 'Utente sconosciuto'],
    ['en', 'New support request', 'Unknown user'],
]);
