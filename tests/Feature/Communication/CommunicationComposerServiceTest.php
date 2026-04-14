<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Import;
use App\Models\User;
use App\Services\Communication\CommunicationComposerService;
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

it('composes the welcome communication for a user on the mail channel', function () {
    $user = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'giuseppe@example.com',
        'locale' => 'it',
    ]);

    $composed = app(CommunicationComposerService::class)->compose(
        'user.welcome_after_verification',
        CommunicationChannelEnum::MAIL,
        $user,
    );

    expect($composed->category->key)->toBe('user.welcome_after_verification')
        ->and($composed->template->key)->toBe('welcome_after_verification_mail')
        ->and($composed->body)->toContain('Giuseppe De Blasio')
        ->and($composed->title)->toBe('Benvenuto su Soamco Budget')
        ->and($composed->ctaLabel)->toBe('Apri dashboard')
        ->and($composed->ctaUrl)->toBe(url('/dashboard'));
});

it('composes the imports completed communication for an import on the mail channel', function () {
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

    $composed = app(CommunicationComposerService::class)->compose(
        'imports.completed',
        CommunicationChannelEnum::MAIL,
        $import,
    );

    expect($composed->category->key)->toBe('imports.completed')
        ->and($composed->template->key)->toBe('import_completed_mail')
        ->and($composed->context['import']['filename'])->toBe('movements.csv');
});

it('returns a normalized composed communication payload', function () {
    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
    ]);

    $composed = app(CommunicationComposerService::class)->compose(
        'user.welcome_after_verification',
        CommunicationChannelEnum::MAIL,
        $user,
    );

    $payload = $composed->toArray();

    expect($payload['category']['key'])->toBe('user.welcome_after_verification')
        ->and($payload['template']['key'])->toBe('welcome_after_verification_mail')
        ->and($payload['body'])->toContain('Mario Rossi');
});
