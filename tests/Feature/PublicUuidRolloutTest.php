<?php

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\User;
use App\Support\PublicUuidRollout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('all selected domain tables expose a public uuid column', function () {
    foreach (PublicUuidRollout::domainTables() as $table => $indexName) {
        expect(Schema::hasColumn($table, 'uuid'))->toBeTrue("Missing uuid column on table [{$table}].");
    }
});

test('all selected domain tables enforce uniqueness on the public uuid column', function () {
    foreach (PublicUuidRollout::domainTables() as $table => $indexName) {
        expect(Schema::hasIndex($table, $indexName, 'unique'))
            ->toBeTrue("Missing unique uuid index [{$indexName}] on table [{$table}].");
    }
});

test('new records automatically receive a public uuid', function () {
    $user = User::factory()->create();

    $bank = Bank::query()->create([
        'name' => 'Banco Test',
        'slug' => 'banco-test',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $accountType = AccountType::query()->create([
        'code' => 'uuid-rollout-test',
        'name' => 'UUID rollout test',
        'balance_nature' => 'asset',
    ]);

    expect($user->uuid)->not->toBeNull();
    expect($bank->uuid)->not->toBeNull();
    expect($accountType->uuid)->not->toBeNull();
});

test('backfill logic assigns uuids to legacy rows left without one', function () {
    $user = User::factory()->create();

    DB::table('users')
        ->where('id', $user->id)
        ->update([
            'uuid' => null,
        ]);

    expect(User::query()->findOrFail($user->id)->uuid)->toBeNull();

    PublicUuidRollout::backfillTable('users');

    $uuid = User::query()->findOrFail($user->id)->uuid;

    expect($uuid)->not->toBeNull();
    expect($uuid)->toHaveLength(36);
});
