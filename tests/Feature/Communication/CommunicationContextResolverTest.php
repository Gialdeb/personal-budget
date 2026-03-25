<?php

use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Import;
use App\Models\User;
use App\Services\Communication\CommunicationContextResolverRegistry;
use App\Services\Communication\CommunicationVariableResolver;
use App\Services\Communication\ContextResolvers\ImportCommunicationContextResolver;
use App\Services\Communication\ContextResolvers\UserCommunicationContextResolver;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('resolves the user context with expected fields', function () {
    $user = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'giuseppe@example.com',
        'locale' => 'it',
    ]);

    $context = app(UserCommunicationContextResolver::class)->resolve($user);

    expect($context['user']['name'])->toBe('Giuseppe')
        ->and($context['user']['surname'])->toBe('De Blasio')
        ->and($context['user']['full_name'])->toBe('Giuseppe De Blasio')
        ->and($context['user']['email'])->toBe('giuseppe@example.com');
});

it('resolves the import context with expected fields', function () {
    $user = User::factory()->create([
        'email' => 'owner@example.com',
    ]);

    $import = Import::query()->forceCreate([
        'user_id' => $user->id,
        'original_filename' => 'movements.csv',
        'source_type' => ImportSourceTypeEnum::CSV->value,
        'rows_count' => 20,
        'imported_rows_count' => 18,
        'review_rows_count' => 0,
        'invalid_rows_count' => 0,
        'duplicate_rows_count' => 0,
        'status' => ImportStatusEnum::COMPLETED,
    ]);

    $context = app(ImportCommunicationContextResolver::class)->resolve($import);

    expect($context['import']['filename'])->toBe('movements.csv')
        ->and($context['import']['rows_count'])->toBe(20)
        ->and($context['import']['imported_rows_count'])->toBe(18)
        ->and($context['import']['user_email'])->toBe('owner@example.com');
});

it('returns the correct resolver from the registry', function () {
    $userResolver = app(CommunicationContextResolverRegistry::class)->for('user');
    $importResolver = app(CommunicationContextResolverRegistry::class)->for('import');

    expect($userResolver)->toBeInstanceOf(UserCommunicationContextResolver::class)
        ->and($importResolver)->toBeInstanceOf(ImportCommunicationContextResolver::class);
});

it('replaces simple placeholders from a resolved context', function () {
    $resolved = app(CommunicationVariableResolver::class)->replacePlaceholders(
        'Hello {user.full_name}, your email is {user.email}.',
        [
            'user' => [
                'full_name' => 'Giuseppe De Blasio',
                'email' => 'giuseppe@example.com',
            ],
        ]
    );

    expect($resolved)->toBe('Hello Giuseppe De Blasio, your email is giuseppe@example.com.');
});

it('leaves unknown placeholders untouched', function () {
    $resolved = app(CommunicationVariableResolver::class)->replacePlaceholders(
        'Import file: {import.filename}, payment: {payment.amount}',
        [
            'import' => [
                'filename' => 'movements.csv',
            ],
        ]
    );

    expect($resolved)->toBe('Import file: movements.csv, payment: {payment.amount}');
});

it('exposes available variables metadata for user and import contexts', function () {
    $userVariables = app(UserCommunicationContextResolver::class)->availableVariables();
    $importVariables = app(ImportCommunicationContextResolver::class)->availableVariables();

    expect(collect($userVariables)->pluck('key'))->toContain('user.full_name')
        ->and(collect($importVariables)->pluck('key'))->toContain('import.filename');
});
