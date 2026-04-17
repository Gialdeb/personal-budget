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
        'account' => 'Conto Import',
        'account_id' => $account->id,
        'account_uuid' => $account->uuid,
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
Conto;Data;Tipo;Importo;Dettaglio;Categoria;Riferimento;Esercente;Riferimento esterno
Conto Import;01/03/2026;Spesa;12,50;Spesa bar;Tempo libero;RIF-001;Bar Roma;EXT-001
Conto Import;02/03/2026;Giroconto;50,00;Spostamento fondi;Trasferimenti;;;
Conto Import;03/03/2026;Entrata;100,00;Stipendio;;;Datore;EXT-003
Conto Import;04/03/2025;Spesa;10,00;Vecchia spesa;Casa;;;
Conto Import;05/03/2026;Alieno;10,00;Tipo errato;Casa;;;
Conto Import;01/03/2026;Spesa;12,50;Spesa bar;Tempo libero;RIF-001;Bar Roma;EXT-001
Conto Import;06/03/2026;Spesa;99,90;Pagamento carta;Carta;;;EXT-IMPORTED
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
            'account' => 'Conto Import',
            'account_id' => $account->id,
            'account_uuid' => $account->uuid,
            'date' => '2026-03-01',
            'type' => 'expense',
            'amount' => '12.50',
            'detail' => 'Spesa bar',
            'category' => 'Tempo libero',
            'reference' => 'RIF-001',
            'merchant' => 'Bar Roma',
            'external_reference' => 'EXT-001',
        ])
        ->and($rows[1]->fingerprint)->not->toBeNull();

    expect($rows[2]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[3]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[4]->status)->toBe(ImportRowStatusEnum::BLOCKED_YEAR)
        ->and($rows[4]->parse_status)->toBe(ImportRowParseStatusEnum::PARSED)
        ->and($rows[5]->status)->toBe(ImportRowStatusEnum::INVALID)
        ->and($rows[5]->parse_status)->toBe(ImportRowParseStatusEnum::FAILED)
        ->and($rows[6]->status)->toBe(ImportRowStatusEnum::DUPLICATE_CANDIDATE)
        ->and($rows[7]->status)->toBe(ImportRowStatusEnum::DUPLICATE_CANDIDATE);

    expect($rows[6]->warnings)->toContain('Questa riga sembra duplicata nello stesso import.')
        ->and($rows[7]->warnings)->toContain('Questa riga risulta già importata nello storico, ma il movimento non è più presente nel ledger: verifica se vuoi reimportarla.')
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
        ->and($processedImport->error_message)->toBe('Mancano colonne obbligatorie nel file di importazione.')
        ->and($processedImport->meta['missing_columns'])->toBe(['account', 'detail']);

    $this->assertDatabaseCount('import_rows', 0);
});

test('generic import sends ambiguous account names to manual review', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    Account::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_type_id' => $account->account_type_id,
        'name' => $account->name,
        'currency' => $user->base_currency_code,
        'currency_code' => $user->base_currency_code,
        'is_manual' => true,
        'is_active' => true,
    ]);

    Storage::disk('local')->put('imports/ambiguous-account.csv', <<<'CSV'
Conto;Data;Tipo;Importo;Dettaglio;Categoria
Conto Import;01/03/2026;Spesa;12,50;Spesa bar;
CSV);

    $import = makeImportServiceTestImport($user, $account, 'imports/ambiguous-account.csv');

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $row = $processedImport->rows()->firstOrFail();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->review_rows_count)->toBe(1)
        ->and($row->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($row->normalized_payload['account_id'])->toBeNull()
        ->and($row->warnings)->toContain('Il nome del conto della riga corrisponde a più conti attivi e richiede controllo manuale.');
});

test('generic import normalizes amounts reliably across eu and us formats and rejects ambiguous values', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Casa',
        'slug' => 'casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $csv = <<<'CSV'
Conto;Data;Tipo;Importo;Dettaglio;Categoria;Riferimento esterno
Conto Import;01/03/2026;Spesa;43,7;Decimale EU corto;Casa;AMT-1
Conto Import;02/03/2026;Spesa;43.7;Decimale US corto;Casa;AMT-2
Conto Import;03/03/2026;Spesa;121,89;Decimale EU;Casa;AMT-3
Conto Import;04/03/2026;Spesa;121.89;Decimale US;Casa;AMT-4
Conto Import;05/03/2026;Spesa;1.218,90;Migliaia EU;Casa;AMT-5
Conto Import;06/03/2026;Spesa;1,218.90;Migliaia US;Casa;AMT-6
Conto Import;07/03/2026;Spesa;1218;Intero;Casa;AMT-7
Conto Import;08/03/2026;Spesa;1.2189;Ambiguo;Casa;AMT-8
CSV;

    Storage::disk('local')->put('imports/amount-formats.csv', $csv);

    $import = makeImportServiceTestImport($user, $account, 'imports/amount-formats.csv');

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);

    $rows = ImportRow::query()
        ->where('import_id', $processedImport->id)
        ->orderBy('row_index')
        ->get()
        ->values();

    expect($rows)->toHaveCount(8)
        ->and($rows[0]->normalized_payload['amount'])->toBe('43.70')
        ->and($rows[1]->normalized_payload['amount'])->toBe('43.70')
        ->and($rows[2]->normalized_payload['amount'])->toBe('121.89')
        ->and($rows[3]->normalized_payload['amount'])->toBe('121.89')
        ->and($rows[4]->normalized_payload['amount'])->toBe('1218.90')
        ->and($rows[5]->normalized_payload['amount'])->toBe('1218.90')
        ->and($rows[6]->normalized_payload['amount'])->toBe('1218.00')
        ->and($rows[7]->status)->toBe(ImportRowStatusEnum::INVALID)
        ->and($rows[7]->errors)->toContain("L'importo 1.2189 non è valido.");
});

test('generic import does not crash on manipulated account guid values and sends the row to manual review', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Casa',
        'slug' => 'casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Storage::disk('local')->put('imports/manipulated-account-guid.csv', <<<'CSV'
Conto;Data;Tipo;Importo;Dettaglio;Categoria
Conto Import (4d02aba1-7a95-4481-a6ab-1b1a9c8bf63g);01/03/2026;Spesa;12,50;Spesa con conto manomesso;Casa
CSV);

    $import = makeImportServiceTestImport($user, $account, 'imports/manipulated-account-guid.csv');

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $row = $processedImport->rows()->firstOrFail();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->review_rows_count)->toBe(1)
        ->and($row->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($row->warnings)->toContain('Il conto della riga non corrisponde a un conto attivo e richiede controllo manuale.')
        ->and($row->normalized_payload['account_id'])->toBeNull();
});
