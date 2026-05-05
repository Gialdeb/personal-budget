<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\User;

test('category display name matrix respects foundation defaults and custom names', function () {
    $foundationCategory = Category::query()->make([
        'name' => 'Alimentari',
        'name_is_custom' => false,
        'slug' => 'alimentari',
    ]);

    $renamedFoundationCategory = Category::query()->make([
        'name' => 'Insurance',
        'name_is_custom' => true,
        'slug' => 'auto-assicurazione',
    ]);

    $customCategory = Category::query()->make([
        'name' => 'Bottega sotto casa',
        'name_is_custom' => true,
        'slug' => 'bottega-sotto-casa',
    ]);

    expect($foundationCategory->displayName('it'))->toBe('Alimentari')
        ->and($foundationCategory->displayName('en'))->toBe('Groceries')
        ->and($renamedFoundationCategory->displayName('it'))->toBe('Insurance')
        ->and($renamedFoundationCategory->displayName('en'))->toBe('Insurance')
        ->and($customCategory->displayName('it'))->toBe('Bottega sotto casa')
        ->and($customCategory->displayName('en'))->toBe('Bottega sotto casa');
});

test('category custom name backfill flags only legacy foundation rows that were truly renamed', function () {
    $user = User::factory()->create([
        'locale' => 'it',
    ]);

    $defaultFoundationCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Alimentari',
        'name_is_custom' => false,
        'slug' => 'alimentari',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $renamedFoundationCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Insurance',
        'name_is_custom' => false,
        'slug' => 'auto-assicurazione',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $customCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Bottega sotto casa',
        'name_is_custom' => false,
        'slug' => 'bottega-sotto-casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $migration = require database_path('migrations/2026_05_04_152005_add_name_is_custom_to_categories_table.php');
    $backfill = new ReflectionMethod($migration, 'backfillExistingCustomNames');
    $backfill->invoke($migration);

    expect($defaultFoundationCategory->fresh()->name_is_custom)->toBeFalse()
        ->and($renamedFoundationCategory->fresh()->name_is_custom)->toBeTrue()
        ->and($customCategory->fresh()->name_is_custom)->toBeFalse();
});
