<?php

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
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Imports\ImportReadyRowsService;
use App\Services\Imports\RollbackImportService;
use Carbon\Carbon;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->user = User::factory()->create([
        'locale' => 'it',
    ]);

    $this->accountType = AccountType::firstOrCreate(
        ['code' => 'conto-test-type'],
        [
            'name' => 'Conto test type',
        ]
    );

    $this->transferCategory = Category::create([
        'user_id' => $this->user->id,
        'name' => 'Trasferimenti',
        'slug' => 'trasferimenti',
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'direction_type' => 'expense',
        'is_active' => true,
        'is_selectable' => true,
        'sort_order' => 1,
    ]);

    $this->sourceAccount = Account::create([
        'user_id' => $this->user->id,
        'account_type_id' => $this->accountType->id,
        'name' => 'Conto origine',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $this->destinationAccount = Account::create([
        'user_id' => $this->user->id,
        'account_type_id' => $this->accountType->id,
        'name' => 'Conto destinazione',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $openingBalanceColumns = Schema::getColumnListing('account_opening_balances');

    $balanceValueColumn = collect([
        'opening_balance',
        'amount',
        'balance',
        'value',
    ])->first(fn (string $column) => in_array($column, $openingBalanceColumns, true));

    if (! $balanceValueColumn) {
        throw new RuntimeException('Impossibile determinare la colonna del saldo iniziale per account_opening_balances.');
    }

    $payload = [
        'account_id' => $this->sourceAccount->id,
        'balance_date' => Carbon::parse('2026-01-01')->toDateString(),
        $balanceValueColumn => 1000,
        'created_at' => Carbon::parse('2026-01-01 00:00:00'),
        'updated_at' => Carbon::parse('2026-01-01 00:00:00'),
    ];

    if (in_array('uuid', $openingBalanceColumns, true)) {
        $payload['uuid'] = (string) Str::uuid();
    }

    if (in_array('year', $openingBalanceColumns, true)) {
        $payload['year'] = 2026;
    }

    DB::table('account_opening_balances')->insert($payload);

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
        'account_id' => $this->sourceAccount->id,
        'import_format_id' => $this->importFormat->id,
        'status' => ImportStatusEnum::REVIEW_REQUIRED,
        'original_filename' => 'transfer.csv',
        'stored_filename' => 'imports/transfer.csv',
        'mime_type' => 'text/csv',
        'source_type' => 'csv',
        'meta' => [
            'management_year' => 2026,
        ],
    ]);
});

function makeTransferRow(Import $import, array $normalizedPayload = [], array $overrides = []): ImportRow
{
    return ImportRow::create(array_merge([
        'import_id' => $import->id,
        'row_index' => 1,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
        'raw_payload' => [
            'date' => '31/03/2026',
            'type' => 'Giroconto',
            'amount' => '150,00',
            'detail' => 'Spostamento fondi',
            'category' => 'Trasferimenti',
            'reference' => 'TRF-001',
            'merchant' => null,
            'external_reference' => 'EXT-TRF-001',
            'balance' => '1200,00',
        ],
        'normalized_payload' => array_merge([
            'date' => '2026-03-31',
            'type' => 'transfer',
            'amount' => '150.00',
            'detail' => 'Spostamento fondi',
            'category' => 'Trasferimenti',
            'reference' => 'TRF-001',
            'merchant' => null,
            'external_reference' => 'EXT-TRF-001',
            'balance' => '1200.00',
            'destination_account_id' => null,
            'destination_account_uuid' => null,
        ], $normalizedPayload),
        'errors' => [],
        'warnings' => [],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'parse_error' => null,
    ], $overrides));
}

it('keeps a transfer row in needs_review when destination account is missing', function () {
    $row = makeTransferRow($this->import);

    $this->actingAs($this->user)
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
            'reference' => 'TRF-001',
            'merchant' => null,
            'external_reference' => 'EXT-TRF-001',
            'balance' => '1200,00',
            'destination_account_id' => null,
        ])
        ->assertRedirect();

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::NEEDS_REVIEW->value)
        ->and($row->normalized_payload['type'])->toBe('transfer')
        ->and($row->normalized_payload['destination_account_id'])->toBeNull()
        ->and(collect($row->warnings)->implode(' '))->toContain('conto destinazione');
});

it('marks a transfer row invalid when destination account equals source account', function () {
    $row = makeTransferRow($this->import);

    $this->actingAs($this->user)
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
            'reference' => 'TRF-001',
            'merchant' => null,
            'external_reference' => 'EXT-TRF-001',
            'balance' => '1200,00',
            'destination_account_id' => $this->sourceAccount->id,
        ])
        ->assertRedirect();

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::INVALID->value)
        ->and(collect($row->errors)->implode(' '))->toContain('diverso dal conto di origine');
});

it('marks a transfer row ready when destination account is valid and different', function () {
    $row = makeTransferRow($this->import);

    $this->actingAs($this->user)
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
            'reference' => 'TRF-001',
            'merchant' => null,
            'external_reference' => 'EXT-TRF-001',
            'balance' => '1200,00',
            'destination_account_id' => $this->destinationAccount->id,
        ])
        ->assertRedirect();

    $row->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::READY->value)
        ->and($row->normalized_payload['destination_account_id'])->toBe($this->destinationAccount->id)
        ->and($row->normalized_payload['destination_account_uuid'])->toBe($this->destinationAccount->uuid);
});

it('imports a ready transfer row by creating two linked transactions', function () {
    $this->sourceAccount->update([
        'settings' => ['allow_negative_balance' => true],
    ]);

    $row = makeTransferRow(
        $this->import,
        [
            'destination_account_id' => $this->destinationAccount->id,
            'destination_account_uuid' => $this->destinationAccount->uuid,
        ],
        [
            'status' => ImportRowStatusEnum::READY->value,
        ]
    );

    $this->import->forceFill([
        'status' => ImportStatusEnum::PARSED,
        'rows_count' => 1,
        'ready_rows_count' => 1,
        'review_rows_count' => 0,
        'invalid_rows_count' => 0,
        'duplicate_rows_count' => 0,
        'imported_rows_count' => 0,
    ])->save();

    app(ImportReadyRowsService::class)->execute($this->import->fresh());

    $row->refresh();
    $this->import->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::IMPORTED->value)
        ->and($row->transaction_id)->not->toBeNull();

    $outgoing = Transaction::find($row->transaction_id);
    expect($outgoing)->not->toBeNull();

    $incoming = Transaction::find($outgoing->related_transaction_id);
    expect($incoming)->not->toBeNull()
        ->and((int) $outgoing->account_id)->toBe($this->sourceAccount->id)
        ->and((int) $incoming->account_id)->toBe($this->destinationAccount->id)
        ->and((float) $outgoing->amount)->toBe(150.0)
        ->and((float) $incoming->amount)->toBe(150.0)
        ->and($outgoing->related_transaction_id)->toBe($incoming->id)
        ->and($incoming->related_transaction_id)->toBe($outgoing->id)
        ->and($this->import->imported_rows_count)->toBeGreaterThanOrEqual(1);

});

it('rolls back a transfer import by removing both linked transactions', function () {
    $this->sourceAccount->update([
        'settings' => ['allow_negative_balance' => true],
    ]);

    $row = makeTransferRow(
        $this->import,
        [
            'destination_account_id' => $this->destinationAccount->id,
            'destination_account_uuid' => $this->destinationAccount->uuid,
        ],
        [
            'status' => ImportRowStatusEnum::READY->value,
        ]
    );

    $this->import->forceFill([
        'status' => ImportStatusEnum::PARSED,
        'rows_count' => 1,
        'ready_rows_count' => 1,
        'review_rows_count' => 0,
        'invalid_rows_count' => 0,
        'duplicate_rows_count' => 0,
        'imported_rows_count' => 0,
    ])->save();

    app(ImportReadyRowsService::class)->execute($this->import->fresh());

    $row->refresh();
    $this->import->refresh();

    expect($row->status->value)->toBe(ImportRowStatusEnum::IMPORTED->value)
        ->and($row->transaction_id)->not->toBeNull();

    $outgoing = Transaction::find($row->transaction_id);
    expect($outgoing)->not->toBeNull();

    $incoming = Transaction::find($outgoing->related_transaction_id);
    expect($incoming)->not->toBeNull();

    app(RollbackImportService::class)->execute($this->import->fresh());

    $row->refresh();
    $this->import->refresh();

    expect(Transaction::find($outgoing->id))->toBeNull()
        ->and(Transaction::find($incoming->id))->toBeNull()
        ->and($row->status->value)->toBe(ImportRowStatusEnum::ROLLED_BACK->value)
        ->and($this->import->status->value)->toBe(ImportStatusEnum::ROLLED_BACK->value);
});
