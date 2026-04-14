<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
use App\Enums\ImportRowParseStatusEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportFormat;
use App\Models\ImportRow;
use App\Models\User;
use App\Models\UserYear;
use App\Supports\Imports\ImportFingerprintGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('features.imports.enabled', true);
    $this->user = User::factory()->create([
        'locale' => 'it',
    ]);

    $this->accountType = AccountType::firstOrCreate(
        ['code' => 'conto-test-type'],
        [
            'name' => 'Conto test type',
        ]
    );

    $this->account = Account::create([
        'user_id' => $this->user->id,
        'account_type_id' => $this->accountType->id,
        'name' => 'Conto test',
        'is_active' => true,
        'currency' => 'EUR',
    ]);

    $this->importFormat = ImportFormat::create([
        'code' => 'generic_csv_v1',
        'name' => 'CSV generico',
        'version' => 'v1',
        'type' => ImportFormatTypeEnum::GENERIC_CSV,
        'status' => ImportFormatStatusEnum::ACTIVE,
        'is_generic' => true,
        'settings' => [],
    ]);

    $this->userYear = UserYear::create([
        'user_id' => $this->user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $parentCategory = Category::create([
        'user_id' => $this->user->id,
        'name' => 'Spese',
        'slug' => 'spese',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    Category::create([
        'user_id' => $this->user->id,
        'parent_id' => $parentCategory->id,
        'name' => 'Pasti lavoro',
        'slug' => 'pasti-lavoro',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->import = Import::create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'import_format_id' => $this->importFormat->id,
        'status' => ImportStatusEnum::REVIEW_REQUIRED,
        'original_filename' => 'test.csv',
        'stored_filename' => 'imports/test.csv',
        'mime_type' => 'text/csv',
        'source_type' => 'csv',
        'meta' => [
            'management_year' => 2026,
        ],
    ]);
});

it('updates a review row and marks it ready when data is valid', function () {
    $row = ImportRow::create([
        'import_id' => $this->import->id,
        'row_index' => 1,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
        'raw_payload' => [],
        'normalized_payload' => [
            'date' => '2026-03-31',
            'type' => 'expense',
            'amount' => '12.50',
            'detail' => 'Pranzo operativo',
            'category' => null,
            'reference' => null,
            'merchant' => null,
            'external_reference' => null,
            'balance' => '1050.40',
        ],
        'errors' => [],
        'warnings' => ['La categoria non è valorizzata e richiede revisione.'],
        'parse_status' => ImportRowParseStatusEnum::cases()[0],
        'parse_error' => null,
    ]);

    $response = $this
        ->actingAs($this->user)
        ->from(route('imports.show', $this->import->uuid))
        ->patch(route('imports.rows.update-review', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]), [
            'date' => '31/03/2026',
            'type' => 'Spesa',
            'amount' => '12,50',
            'detail' => 'Pranzo operativo',
            'category' => 'Spese > Pasti lavoro',
            'reference' => null,
            'merchant' => 'Bar Centrale',
            'external_reference' => 'EXT-001',
            'balance' => '1050,40',
        ]);

    $response
        ->assertRedirect(route('imports.show', $this->import->uuid))
        ->assertSessionHas('success');

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::READY->value)
        ->and($row->normalized_payload['date'])->toBe('2026-03-31')
        ->and($row->normalized_payload['type'])->toBe('expense')
        ->and($row->normalized_payload['amount'])->toBe('12.50')
        ->and($row->normalized_payload['category'])->toBe('Spese > Pasti lavoro')
        ->and($row->errors)->toBeArray()->toBeEmpty();
});

it('marks a row as blocked_year when reviewed with a date outside management year', function () {
    $row = ImportRow::create([
        'import_id' => $this->import->id,
        'row_index' => 2,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
        'raw_payload' => [],
        'normalized_payload' => [],
        'errors' => [],
        'warnings' => [],
        'parse_status' => ImportRowParseStatusEnum::cases()[0],
        'parse_error' => null,
    ]);

    $this
        ->actingAs($this->user)
        ->from(route('imports.show', $this->import->uuid))
        ->patch(route('imports.rows.update-review', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]), [
            'date' => '31/12/2025',
            'type' => 'Spesa',
            'amount' => '12,50',
            'detail' => 'Pranzo operativo',
            'category' => 'Spese > Pasti lavoro',
            'reference' => null,
            'merchant' => null,
            'external_reference' => null,
            'balance' => null,
        ])
        ->assertRedirect();

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::BLOCKED_YEAR->value)
        ->and($row->errors)->toBeArray()
        ->and(collect($row->errors)->implode(' '))->toContain('anno');
});

it('keeps a transfer row in needs_review', function () {
    $row = ImportRow::create([
        'import_id' => $this->import->id,
        'row_index' => 3,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
        'raw_payload' => [],
        'normalized_payload' => [],
        'errors' => [],
        'warnings' => [],
        'parse_status' => ImportRowParseStatusEnum::cases()[0],
        'parse_error' => null,
    ]);

    $this
        ->actingAs($this->user)
        ->from(route('imports.show', $this->import->uuid))
        ->patch(route('imports.rows.update-review', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]), [
            'date' => '31/03/2026',
            'type' => 'Giroconto',
            'amount' => '150,00',
            'detail' => 'Spostamento fondi',
            'category' => 'Trasferimenti',
            'reference' => null,
            'merchant' => null,
            'external_reference' => null,
            'balance' => null,
        ])
        ->assertRedirect();

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::NEEDS_REVIEW->value)
        ->and($row->warnings)->toBeArray()
        ->and(collect($row->warnings)->implode(' '))->toContain('giroconto');
});

it('marks a reviewed row as already_imported using the import account relation', function () {
    $previousImport = Import::create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'import_format_id' => $this->importFormat->id,
        'status' => ImportStatusEnum::COMPLETED,
        'original_filename' => 'storico.csv',
        'stored_filename' => 'imports/storico.csv',
        'mime_type' => 'text/csv',
        'source_type' => 'csv',
        'meta' => [
            'management_year' => 2026,
        ],
    ]);

    $payload = [
        'date' => '2026-03-31',
        'type' => 'expense',
        'amount' => '12.50',
        'detail' => 'Pranzo operativo',
        'category' => 'Spese > Pasti lavoro',
        'reference' => null,
        'merchant' => 'Bar Centrale',
        'external_reference' => 'EXT-001',
        'balance' => '1050.40',
    ];

    ImportRow::create([
        'import_id' => $previousImport->id,
        'row_index' => 1,
        'status' => ImportRowStatusEnum::IMPORTED->value,
        'fingerprint' => ImportFingerprintGenerator::make($payload, $this->user->id, $this->account->id),
        'raw_payload' => [],
        'normalized_payload' => $payload,
        'errors' => [],
        'warnings' => [],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'parse_error' => null,
    ]);

    $row = ImportRow::create([
        'import_id' => $this->import->id,
        'row_index' => 5,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
        'raw_payload' => [],
        'normalized_payload' => [],
        'errors' => [],
        'warnings' => [],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'parse_error' => null,
    ]);

    $this
        ->actingAs($this->user)
        ->from(route('imports.show', $this->import->uuid))
        ->patch(route('imports.rows.update-review', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]), [
            'date' => '31/03/2026',
            'type' => 'Spesa',
            'amount' => '12,50',
            'detail' => 'Pranzo operativo',
            'category' => 'Spese > Pasti lavoro',
            'reference' => null,
            'merchant' => 'Bar Centrale',
            'external_reference' => 'EXT-001',
            'balance' => '1050,40',
        ])
        ->assertRedirect();

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::ALREADY_IMPORTED->value)
        ->and($row->warnings)->toContain('Questa riga risulta già importata in precedenza.');
});

it('marks a row as skipped', function () {
    $row = ImportRow::create([
        'import_id' => $this->import->id,
        'row_index' => 4,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
        'raw_payload' => [],
        'normalized_payload' => [],
        'errors' => [],
        'warnings' => [],
        'parse_status' => ImportRowParseStatusEnum::cases()[0],
        'parse_error' => null,
    ]);

    $this
        ->actingAs($this->user)
        ->from(route('imports.show', $this->import->uuid))
        ->post(route('imports.rows.skip', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]))
        ->assertRedirect(route('imports.show', $this->import->uuid))
        ->assertSessionHas('success');

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::SKIPPED->value)
        ->and($row->warnings)->toBeArray()
        ->and(collect($row->warnings)->implode(' '))->toContain('saltata');
});

it('updates import counters after skipping a row', function () {
    $row = ImportRow::create([
        'import_id' => $this->import->id,
        'row_index' => 5,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
        'raw_payload' => [],
        'normalized_payload' => [],
        'errors' => [],
        'warnings' => [],
        'parse_status' => ImportRowParseStatusEnum::cases()[0],
        'parse_error' => null,
    ]);

    $this
        ->actingAs($this->user)
        ->post(route('imports.rows.skip', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]))
        ->assertRedirect();

    $this->import->refresh();

    expect($this->import->review_rows_count)->toBeGreaterThanOrEqual(0)
        ->and($this->import->rows_count)->toBeGreaterThanOrEqual(1);
});
