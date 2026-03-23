<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\ImportRowParseStatusEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\Imports\ProcessGenericCsvImportService;
use App\Supports\Imports\ImportFingerprintGenerator;
use Illuminate\Support\Facades\Storage;

function makeImportServiceTestAccount(User $user): Account
{
    $bank = Bank::query()->create([
        'name' => 'Banca Import Test',
        'slug' => 'banca-import-test',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $accountType = AccountType::query()->create([
        'code' => 'payment-account',
        'name' => 'Conto di pagamento',
        'balance_nature' => AccountBalanceNatureEnum::ASSET,
    ]);

    return Account::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto Import',
        'currency' => $user->base_currency_code,
        'currency_code' => $user->base_currency_code,
        'is_manual' => true,
        'is_active' => true,
    ]);
}

function makeImportServiceTestImport(User $user, Account $account, string $storedFilename): Import
{
    return Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'original_filename' => basename($storedFilename),
        'stored_filename' => $storedFilename,
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv',
        'status' => ImportStatusEnum::UPLOADED,
    ]);
}

test('generic csv import parses, normalizes and classifies rows while updating counters', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Tempo libero',
        'slug' => 'tempo-libero',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $previousImport = makeImportServiceTestImport($user, $account, 'imports/previous.csv');

    $alreadyImportedPayload = [
        'date' => '2026-03-06',
        'type' => 'expense',
        'amount' => '99.90',
        'detail' => 'Pagamento carta',
        'category' => 'Carta',
        'reference' => null,
        'merchant' => null,
        'external_reference' => 'EXT-IMPORTED',
        'balance' => '840.10',
    ];

    ImportRow::query()->create([
        'import_id' => $previousImport->id,
        'row_index' => 1,
        'raw_date' => '06/03/2026',
        'raw_description' => 'Pagamento carta',
        'raw_amount' => '99,90',
        'raw_balance' => '840,10',
        'raw_payload' => [],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::IMPORTED,
        'fingerprint' => ImportFingerprintGenerator::make($alreadyImportedPayload, $user->id, $account->id),
        'normalized_payload' => $alreadyImportedPayload,
        'errors' => [],
        'warnings' => [],
    ]);

    $csv = <<<'CSV'
Data;Tipo;Importo;Dettaglio;Categoria;Riferimento;Esercente;Riferimento esterno;Saldo
01/03/2026;Spesa;12,50;Spesa bar;Tempo libero;RIF-001;Bar Roma;EXT-001;1000,00
02/03/2026;Giroconto;50,00;Spostamento fondi;Trasferimenti;;;950,00
03/03/2026;Entrata;100,00;Stipendio;;;Datore;EXT-003;1050,00
04/03/2025;Spesa;10,00;Vecchia spesa;Casa;;;1040,00
05/03/2026;Alieno;10,00;Tipo errato;Casa;;;1030,00
01/03/2026;Spesa;12,50;Spesa bar;Tempo libero;RIF-001;Bar Roma;EXT-001;1000,00
06/03/2026;Spesa;99,90;Pagamento carta;Carta;;;EXT-IMPORTED;840,10
CSV;

    Storage::disk('local')->put('imports/generic-batch-2.csv', $csv);

    $import = makeImportServiceTestImport($user, $account, 'imports/generic-batch-2.csv');

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->rows_count)->toBe(7)
        ->and($processedImport->ready_rows_count)->toBe(1)
        ->and($processedImport->review_rows_count)->toBe(2)
        ->and($processedImport->invalid_rows_count)->toBe(2)
        ->and($processedImport->duplicate_rows_count)->toBe(2)
        ->and($processedImport->imported_rows_count)->toBe(0)
        ->and($processedImport->meta)->toMatchArray([
            'parser' => 'generic_csv',
            'delimiter' => ';',
        ]);

    $rows = ImportRow::query()
        ->where('import_id', $processedImport->id)
        ->orderBy('row_index')
        ->get()
        ->keyBy('row_index');

    expect($rows)->toHaveCount(7);

    expect($rows[1]->status)->toBe(ImportRowStatusEnum::READY)
        ->and($rows[1]->parse_status)->toBe(ImportRowParseStatusEnum::PARSED)
        ->and($rows[1]->normalized_payload)->toMatchArray([
            'date' => '2026-03-01',
            'type' => 'expense',
            'amount' => '12.50',
            'detail' => 'Spesa bar',
            'category' => 'Tempo libero',
            'reference' => 'RIF-001',
            'merchant' => 'Bar Roma',
            'external_reference' => 'EXT-001',
            'balance' => '1000.00',
        ])
        ->and($rows[1]->fingerprint)->not->toBeNull();

    expect($rows[2]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[3]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[4]->status)->toBe(ImportRowStatusEnum::BLOCKED_YEAR)
        ->and($rows[4]->parse_status)->toBe(ImportRowParseStatusEnum::PARSED)
        ->and($rows[5]->status)->toBe(ImportRowStatusEnum::INVALID)
        ->and($rows[5]->parse_status)->toBe(ImportRowParseStatusEnum::FAILED)
        ->and($rows[6]->status)->toBe(ImportRowStatusEnum::DUPLICATE_CANDIDATE)
        ->and($rows[7]->status)->toBe(ImportRowStatusEnum::ALREADY_IMPORTED);

    expect($rows[6]->warnings)->toContain('Questa riga è duplicata rispetto a un’altra riga dello stesso file.')
        ->and($rows[7]->warnings)->toContain('Questa riga sembra già importata in precedenza.')
        ->and($rows[4]->errors)->toContain("La riga è del 2025, ma questo import lavora sull'anno gestionale 2026.")
        ->and($rows[5]->errors)->toContain('Il tipo Alieno non è valido.');
});

test('generic csv import fails when required headers are missing', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);

    Storage::disk('local')->put('imports/missing-headers.csv', <<<'CSV'
Data;Tipo;Importo
01/03/2026;Spesa;12,50
CSV);

    $import = makeImportServiceTestImport($user, $account, 'imports/missing-headers.csv');

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);

    expect($processedImport->status)->toBe(ImportStatusEnum::FAILED)
        ->and($processedImport->failed_at)->not->toBeNull()
        ->and($processedImport->error_message)->toBe('Mancano colonne obbligatorie nel file CSV.')
        ->and($processedImport->meta['missing_columns'])->toBe(['detail']);

    $this->assertDatabaseCount('import_rows', 0);
});
