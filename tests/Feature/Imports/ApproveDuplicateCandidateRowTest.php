<?php

use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
use App\Enums\ImportRowParseStatusEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Import;
use App\Models\ImportFormat;
use App\Models\ImportRow;
use App\Models\User;
use App\Models\UserYear;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

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
        'currency' => 'EUR',
        'is_active' => true,
        'settings' => ['allow_negative_balance' => true],
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

    UserYear::create([
        'user_id' => $this->user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $this->import = Import::create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'import_format_id' => $this->importFormat->id,
        'status' => ImportStatusEnum::REVIEW_REQUIRED,
        'original_filename' => 'duplicates.csv',
        'stored_filename' => 'imports/duplicates.csv',
        'mime_type' => 'text/csv',
        'source_type' => 'csv',
        'rows_count' => 1,
        'ready_rows_count' => 0,
        'review_rows_count' => 0,
        'invalid_rows_count' => 0,
        'duplicate_rows_count' => 1,
        'imported_rows_count' => 0,
        'meta' => [
            'management_year' => 2026,
        ],
    ]);
});

function makeDuplicateRow(Import $import, string $status): ImportRow
{
    return ImportRow::create([
        'import_id' => $import->id,
        'row_index' => 1,
        'status' => $status,
        'raw_payload' => [],
        'normalized_payload' => [
            'date' => '2026-03-31',
            'type' => 'expense',
            'amount' => '12.50',
            'detail' => 'Pranzo operativo',
            'category' => 'Alimentari',
            'reference' => null,
            'merchant' => null,
            'external_reference' => 'EXT-001',
            'balance' => '1000.00',
        ],
        'errors' => ['Possibile duplicato.'],
        'warnings' => ['Riga individuata come duplicato candidato.'],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'parse_error' => null,
        'fingerprint' => 'dup-test-fingerprint',
    ]);
}

it('approves a duplicate candidate row and marks it ready', function () {
    $row = makeDuplicateRow($this->import, ImportRowStatusEnum::DUPLICATE_CANDIDATE->value);

    $this->actingAs($this->user)
        ->post(route('imports.rows.approve-duplicate', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]))
        ->assertRedirect(route('imports.show', $this->import->uuid));

    $row->refresh();
    $this->import->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::READY->value)
        ->and($row->errors)->toBe([])
        ->and(collect($row->warnings)->implode(' '))->toContain('approvato manualmente');

    expect($this->import->ready_rows_count)->toBe(1)
        ->and($this->import->duplicate_rows_count)->toBe(0);
});

it('does not approve an already imported row', function () {
    $row = makeDuplicateRow($this->import, ImportRowStatusEnum::ALREADY_IMPORTED->value);

    $this->actingAs($this->user)
        ->post(route('imports.rows.approve-duplicate', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]))
        ->assertSessionHasErrors();

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::ALREADY_IMPORTED->value);
});

it('does not approve a row belonging to another import', function () {
    $otherImport = Import::create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'import_format_id' => $this->importFormat->id,
        'status' => ImportStatusEnum::REVIEW_REQUIRED,
        'original_filename' => 'other.csv',
        'stored_filename' => 'imports/other.csv',
        'mime_type' => 'text/csv',
        'source_type' => 'csv',
        'meta' => [
            'management_year' => 2026,
        ],
    ]);

    $row = makeDuplicateRow($otherImport, ImportRowStatusEnum::DUPLICATE_CANDIDATE->value);

    $this->actingAs($this->user)
        ->post(route('imports.rows.approve-duplicate', [
            'import' => $this->import->uuid,
            'row' => $row->uuid,
        ]))
        ->assertStatus(404);

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::DUPLICATE_CANDIDATE->value);
});
