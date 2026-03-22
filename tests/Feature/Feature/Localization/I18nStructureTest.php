<?php

it('provides the required backend i18n namespaces for supported locales', function () {
    $requiredNamespaces = [
        'app',
        'nav',
        'dashboard',
        'planning',
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
