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
use App\Models\ImportFormat;
use App\Models\ImportRow;
use App\Models\Transaction;
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

function makeImportServiceTestImport(
    User $user,
    Account $account,
    string $storedFilename,
    ?ImportFormat $format = null,
    ImportSourceTypeEnum $sourceType = ImportSourceTypeEnum::CSV,
): Import {
    return Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format?->id,
        'original_filename' => basename($storedFilename),
        'stored_filename' => $storedFilename,
        'mime_type' => $sourceType === ImportSourceTypeEnum::XLSX
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv',
        'source_type' => $sourceType,
        'parser_key' => 'generic_csv',
        'status' => ImportStatusEnum::UPLOADED,
    ]);
}

function makeImportServiceXlsx(array $rows, string $sheetName = 'Movements'): string
{
    $path = tempnam(sys_get_temp_dir(), 'mediobanca-import-test-').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $sharedStrings = collect($rows)
        ->flatten()
        ->filter(fn ($value): bool => is_string($value) && $value !== '')
        ->unique()
        ->values();
    $sharedStringIndexes = $sharedStrings->flip();
    $sharedStringsXml = $sharedStrings
        ->map(fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>')
        ->implode('');
    $sheetRows = collect($rows)
        ->map(function (array $cells, int $rowNumber) use ($sharedStringIndexes): string {
            $rowCells = collect($cells)
                ->map(function ($value, string $column) use ($rowNumber, $sharedStringIndexes): string {
                    $escapedReference = $column.$rowNumber;

                    if (is_string($value)) {
                        $index = $sharedStringIndexes[$value];

                        return '<c r="'.$escapedReference.'" t="s"><v>'.$index.'</v></c>';
                    }

                    if ($value === null) {
                        return '<c r="'.$escapedReference.'"/>';
                    }

                    return '<c r="'.$escapedReference.'"><v>'.$value.'</v></c>';
                })
                ->implode('');

            return '<row r="'.$rowNumber.'">'.$rowCells.'</row>';
        })
        ->implode('');

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.htmlspecialchars($sheetName, ENT_XML1).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$sharedStrings->count().'" uniqueCount="'.$sharedStrings->count().'">'.$sharedStringsXml.'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    $zip->close();

    $content = (string) file_get_contents($path);
    unlink($path);

    return $content;
}

function makeImportServiceTwoSheetXlsx(array $firstSheetRows, array $secondSheetRows, string $secondSheetName): string
{
    $path = tempnam(sys_get_temp_dir(), 'hype-import-test-').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $sharedStrings = collect([$firstSheetRows, $secondSheetRows])
        ->flatten()
        ->filter(fn ($value): bool => is_string($value) && $value !== '')
        ->unique()
        ->values();
    $sharedStringIndexes = $sharedStrings->flip();
    $sharedStringsXml = $sharedStrings
        ->map(fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>')
        ->implode('');
    $sheetXml = function (array $rows) use ($sharedStringIndexes): string {
        $sheetRows = collect($rows)
            ->map(function (array $cells, int $rowNumber) use ($sharedStringIndexes): string {
                $rowCells = collect($cells)
                    ->map(function ($value, string $column) use ($rowNumber, $sharedStringIndexes): string {
                        $reference = $column.$rowNumber;

                        if (is_string($value)) {
                            return '<c r="'.$reference.'" t="s"><v>'.$sharedStringIndexes[$value].'</v></c>';
                        }

                        if ($value === null) {
                            return '<c r="'.$reference.'"/>';
                        }

                        return '<c r="'.$reference.'"><v>'.$value.'</v></c>';
                    })
                    ->implode('');

                return '<row r="'.$rowNumber.'">'.$rowCells.'</row>';
            })
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>';
    };

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Intro" sheetId="1" r:id="rId1"/><sheet name="'.htmlspecialchars($secondSheetName, ENT_XML1).'" sheetId="2" r:id="rId2"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$sharedStrings->count().'" uniqueCount="'.$sharedStrings->count().'">'.$sharedStringsXml.'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml($firstSheetRows));
    $zip->addFromString('xl/worksheets/sheet2.xml', $sheetXml($secondSheetRows));
    $zip->close();

    $content = (string) file_get_contents($path);
    unlink($path);

    return $content;
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

test('bank profile csv maps custom columns and suggests a category from transaction history without auto importing', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa alimentare',
        'slug' => 'spesa-alimentare',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $format = ImportFormat::query()->create([
        'bank_id' => $account->bank_id,
        'code' => 'bank-profile-test',
        'name' => 'Banca Test CSV',
        'version' => 'v1',
        'type' => 'bank_csv',
        'status' => 'active',
        'is_generic' => false,
        'settings' => [
            'source_types' => ['csv', 'xlsx'],
            'header_row' => 2,
            'skip_rows' => [1],
            'columns' => [
                'date' => 'Data operazione',
                'amount' => 'Importo',
                'description' => 'Descrizione',
                'merchant' => 'Esercente',
                'balance' => 'Saldo',
            ],
            'amount' => [
                'mode' => 'signed_amount',
                'debit_column' => null,
                'credit_column' => null,
                'debit_sign' => 'negative',
            ],
            'normalization' => [
                'date_format' => 'Y-m-d',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ],
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-02-10',
        'direction' => 'expense',
        'kind' => 'manual',
        'amount' => 24.20,
        'currency' => 'EUR',
        'description' => 'Pagamento supermercato sole',
        'bank_description_clean' => 'Pagamento supermercato sole',
        'counterparty_name' => 'Supermercato Sole',
        'source_type' => 'manual',
        'status' => 'confirmed',
    ]);

    Storage::disk('local')->put('imports/bank-profile.csv', <<<'CSV'
Report movimenti
Data operazione;Importo;Descrizione;Esercente;Saldo
2026-03-01;-24,20;Pagamento   supermercato   sole;Supermercato Sole;975,80
CSV);

    $import = makeImportServiceTestImport($user, $account, 'imports/bank-profile.csv', $format);

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $row = $processedImport->rows()->firstOrFail();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->review_rows_count)->toBe(1)
        ->and($processedImport->ready_rows_count)->toBe(0)
        ->and($processedImport->meta)->toMatchArray([
            'parser' => 'profile_csv',
            'import_format_profile' => true,
        ])
        ->and($row->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($row->normalized_payload)->toMatchArray([
            'date' => '2026-03-01',
            'type' => 'expense',
            'amount' => '24.20',
            'detail' => 'Pagamento supermercato sole',
            'merchant' => 'Supermercato Sole',
            'balance' => '975.80',
        ])
        ->and($row->normalized_payload['category'])->toBeNull()
        ->and($row->normalized_payload['suggested_category'])->toMatchArray([
            'category_uuid' => $category->uuid,
            'category_label' => 'Spesa alimentare',
            'source' => 'historical_transactions',
        ]);
});

test('mediobanca xlsx profile reads the real header row and maps separate credit debit amounts prudently', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    $format = ImportFormat::ensureMediobancaXlsx();

    $xlsx = makeImportServiceXlsx([
        1 => ['B' => 'Conto Mediobanca'],
        10 => ['B' => 'Lista movimenti'],
        15 => [
            'B' => 'Data contabile',
            'C' => 'Data valuta',
            'D' => 'Tipologia',
            'E' => 'Entrate',
            'F' => 'Uscite',
            'G' => 'Divisa',
        ],
        16 => [
            'B' => '17/03/2026',
            'C' => '17/03/2026',
            'D' => 'Bonif. v/fav. - RIF:214622034ORD. PAYPAL INST INSTANT TRANSFER',
            'E' => 300.0,
            'F' => null,
            'G' => 'EUR',
        ],
        17 => [
            'B' => '13/03/2026',
            'C' => '11/03/2026',
            'D' => 'Pagam. POS - PAGAMENTO POS 80,29 EUR DEL 11.03.2026 A (LUX) AMAZON',
            'E' => null,
            'F' => -80.29,
            'G' => 'EUR',
        ],
        18 => [
            'B' => '12/03/2026',
            'C' => '10/03/2026',
            'D' => 'Movimento anomalo doppio importo',
            'E' => 10.0,
            'F' => -2.0,
            'G' => 'EUR',
        ],
        19 => [
            'B' => '11/03/2026',
            'C' => '10/03/2026',
            'D' => 'Movimento senza importo',
            'E' => null,
            'F' => null,
            'G' => 'EUR',
        ],
        20 => [
            'B' => null,
            'C' => '10/03/2026',
            'D' => 'Movimento senza data contabile',
            'E' => null,
            'F' => -1.5,
            'G' => 'EUR',
        ],
    ]);

    Storage::disk('local')->put('imports/mediobanca.xlsx', $xlsx);

    $import = makeImportServiceTestImport(
        user: $user,
        account: $account,
        storedFilename: 'imports/mediobanca.xlsx',
        format: $format,
        sourceType: ImportSourceTypeEnum::XLSX,
    );

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $rows = ImportRow::query()
        ->where('import_id', $processedImport->id)
        ->orderBy('row_index')
        ->get()
        ->values();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->rows_count)->toBe(5)
        ->and($processedImport->review_rows_count)->toBe(5)
        ->and($processedImport->ready_rows_count)->toBe(0)
        ->and($processedImport->meta)->toMatchArray([
            'parser' => 'profile_xlsx',
            'import_format_profile' => true,
            'mapped_headers' => [
                'Data contabile' => 'date',
                'Data valuta' => 'value_date',
                'Tipologia' => 'detail',
                'Entrate' => 'credit',
                'Uscite' => 'debit',
                'Divisa' => 'currency',
            ],
        ]);

    expect($rows[0]->raw_payload)->toMatchArray([
        'date' => '17/03/2026',
        'value_date' => '17/03/2026',
        'detail' => 'Bonif. v/fav. - RIF:214622034ORD. PAYPAL INST INSTANT TRANSFER',
        'amount' => '300.00',
        'currency' => 'EUR',
    ])
        ->and($rows[0]->normalized_payload)->toMatchArray([
            'date' => '2026-03-17',
            'value_date' => '2026-03-17',
            'type' => 'income',
            'amount' => '300.00',
            'detail' => 'Bonif. v/fav. - RIF:214622034ORD. PAYPAL INST INSTANT TRANSFER',
            'currency' => 'EUR',
        ]);

    expect($rows[1]->raw_payload)->toMatchArray([
        'amount' => '80.29',
        'currency' => 'EUR',
    ])
        ->and($rows[1]->normalized_payload)->toMatchArray([
            'type' => 'expense',
            'amount' => '80.29',
            'value_date' => '2026-03-11',
            'currency' => 'EUR',
        ]);

    expect($rows[2]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[2]->warnings)->toContain('Le colonne Entrate/Uscite sono entrambe valorizzate e la riga richiede revisione.')
        ->and($rows[3]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[3]->warnings)->toContain('Le colonne Entrate/Uscite non contengono un importo valido e la riga richiede revisione.')
        ->and($rows[4]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[4]->warnings)->toContain('Il campo Data è obbligatorio.');
});

test('revolut csv profile maps signed amounts fee fallback and completed state prudently', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    $format = ImportFormat::ensureRevolutCsv();

    Storage::disk('local')->put('imports/revolut.csv', <<<'CSV'
Tipo,Prodotto,Data di inizio,Data di completamento,Descrizione,Importo,Costo,Valuta,State,Saldo
Ricarica,Attuale,2026-01-30 11:08:11,2026-01-30 11:08:13,Ricarica di Google Pay con *2920,506.99,0.00,EUR,COMPLETATO,506.99
Addebita,Attuale,2026-01-30 11:08:15,2026-01-30 11:08:15,Commissione per la consegna della carta,0.00,6.99,EUR,COMPLETATO,500.00
Pagamento con carta,Attuale,2026-03-11 19:13:44,2026-03-12 13:16:16,Supermercati Decò,-46.28,0.00,EUR,COMPLETATO,453.72
Pagamento con carta,Attuale,2026-03-17 09:00:00,2026-03-17 09:01:00,Pagamento sospeso,-10.00,0.00,EUR,IN ATTESA,443.72
CSV);

    $import = makeImportServiceTestImport(
        user: $user,
        account: $account,
        storedFilename: 'imports/revolut.csv',
        format: $format,
    );

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $rows = ImportRow::query()
        ->where('import_id', $processedImport->id)
        ->orderBy('row_index')
        ->get()
        ->values();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->rows_count)->toBe(4)
        ->and($processedImport->review_rows_count)->toBe(4)
        ->and($processedImport->ready_rows_count)->toBe(0)
        ->and($processedImport->duplicate_rows_count)->toBe(0)
        ->and($processedImport->meta)->toMatchArray([
            'parser' => 'profile_csv',
            'import_format_profile' => true,
            'mapped_headers' => [
                'Data di completamento' => 'date',
                'Data di inizio' => 'value_date',
                'Importo' => 'amount',
                'Descrizione' => 'detail',
                'Valuta' => 'currency',
            ],
        ]);

    expect($rows)->toHaveCount(4);

    expect($rows[0]->raw_payload)->toMatchArray([
        'date' => '30/01/2026',
        'value_date' => '30/01/2026',
        'detail' => 'Ricarica di Google Pay con *2920',
        'amount' => '506.99',
        'currency' => 'EUR',
        'balance' => '506.99',
        'import_metadata' => [
            'type' => 'Ricarica',
            'product' => 'Attuale',
            'state' => 'COMPLETATO',
            'fee' => '0.00',
        ],
    ])
        ->and($rows[0]->normalized_payload)->toMatchArray([
            'date' => '2026-01-30',
            'value_date' => '2026-01-30',
            'type' => 'income',
            'amount' => '506.99',
            'detail' => 'Ricarica di Google Pay con *2920',
            'currency' => 'EUR',
            'balance' => '506.99',
            'import_metadata' => [
                'type' => 'Ricarica',
                'product' => 'Attuale',
                'state' => 'COMPLETATO',
                'fee' => '0.00',
            ],
        ]);

    expect($rows[1]->normalized_payload)->toMatchArray([
        'date' => '2026-01-30',
        'type' => 'expense',
        'amount' => '6.99',
        'detail' => 'Commissione per la consegna della carta',
        'currency' => 'EUR',
        'balance' => '500.00',
        'import_metadata' => [
            'type' => 'Addebita',
            'product' => 'Attuale',
            'state' => 'COMPLETATO',
            'fee' => '6.99',
        ],
    ]);

    expect($rows[2]->normalized_payload)->toMatchArray([
        'date' => '2026-03-12',
        'value_date' => '2026-03-11',
        'type' => 'expense',
        'amount' => '46.28',
        'detail' => 'Supermercati Decò',
        'currency' => 'EUR',
        'balance' => '453.72',
    ]);

    expect($rows[3]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[3]->warnings)->toContain('Stato movimento non importabile automaticamente: IN ATTESA.')
        ->and($rows[3]->fingerprint)->toBeNull()
        ->and($rows[3]->normalized_payload)->toMatchArray([
            'date' => '2026-03-17',
            'type' => 'expense',
            'amount' => '10.00',
            'detail' => 'Pagamento sospeso',
            'currency' => 'EUR',
            'import_metadata' => [
                'type' => 'Pagamento con carta',
                'product' => 'Attuale',
                'state' => 'IN ATTESA',
                'fee' => '0.00',
            ],
        ]);
});

test('hype xlsx profile reads movimenti sheet and maps signed amounts with metadata', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    $format = ImportFormat::ensureHypeXlsx();

    $xlsx = makeImportServiceTwoSheetXlsx(
        firstSheetRows: [
            1 => ['A' => 'Questo foglio non contiene movimenti'],
        ],
        secondSheetRows: [
            1 => [
                'A' => 'Data operazione',
                'B' => 'Data contabile',
                'C' => 'Iban',
                'D' => 'Tipologia',
                'E' => 'Nome',
                'F' => 'Descrizione',
                'G' => 'Importo ( € )',
            ],
            2 => [
                'A' => '05/01/2026',
                'B' => '06/01/2026',
                'C' => 'IT00HYPE000000000001',
                'D' => 'Pagamento carta',
                'E' => 'Supermercato Sole',
                'F' => 'Acquisto POS supermercato',
                'G' => -24.2,
            ],
            3 => [
                'A' => '08/01/2026',
                'B' => '08/01/2026',
                'C' => 'IT00HYPE000000000001',
                'D' => 'Bonifico',
                'E' => 'Mario Rossi',
                'F' => 'Bonifico ricevuto',
                'G' => 150,
            ],
            4 => [
                'A' => '09/01/2026',
                'B' => 'data non valida',
                'C' => 'IT00HYPE000000000001',
                'D' => 'Pagamento carta',
                'E' => 'Merchant Test',
                'F' => 'Riga data invalida',
                'G' => -10,
            ],
        ],
        secondSheetName: 'Movimenti',
    );

    Storage::disk('local')->put('imports/hype.xlsx', $xlsx);

    $import = makeImportServiceTestImport(
        user: $user,
        account: $account,
        storedFilename: 'imports/hype.xlsx',
        format: $format,
        sourceType: ImportSourceTypeEnum::XLSX,
    );

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $rows = ImportRow::query()
        ->where('import_id', $processedImport->id)
        ->orderBy('row_index')
        ->get()
        ->values();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->rows_count)->toBe(3)
        ->and($processedImport->review_rows_count)->toBe(3)
        ->and($processedImport->ready_rows_count)->toBe(0)
        ->and($processedImport->meta)->toMatchArray([
            'parser' => 'profile_xlsx',
            'import_format_profile' => true,
            'mapped_headers' => [
                'Data contabile' => 'date',
                'Data operazione' => 'value_date',
                'Importo ( € )' => 'amount',
                'Descrizione' => 'detail',
                'Nome' => 'merchant',
            ],
        ]);

    expect($rows)->toHaveCount(3);

    expect($rows[0]->normalized_payload)->toMatchArray([
        'date' => '2026-01-06',
        'value_date' => '2026-01-05',
        'type' => 'expense',
        'amount' => '24.20',
        'detail' => 'Acquisto POS supermercato',
        'merchant' => 'Supermercato Sole',
        'import_metadata' => [
            'iban' => 'IT00HYPE000000000001',
            'transaction_type' => 'Pagamento carta',
            'name' => 'Supermercato Sole',
        ],
    ]);

    expect($rows[1]->normalized_payload)->toMatchArray([
        'date' => '2026-01-08',
        'value_date' => '2026-01-08',
        'type' => 'income',
        'amount' => '150.00',
        'detail' => 'Bonifico ricevuto',
        'merchant' => 'Mario Rossi',
        'import_metadata' => [
            'iban' => 'IT00HYPE000000000001',
            'transaction_type' => 'Bonifico',
            'name' => 'Mario Rossi',
        ],
    ]);

    expect($rows[2]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[2]->normalized_payload['date'])->toBeNull();
});

test('n26 csv profile maps signed eur amounts and preserves original amount metadata', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    $format = ImportFormat::ensureN26Csv();

    Storage::disk('local')->put('imports/n26.csv', <<<'CSV'
"Booking Date","Value Date","Partner Name","Partner Iban",Type,"Payment Reference","Account Name","Amount (EUR)","Original Amount","Original Currency","Exchange Rate"
2026-02-16,2026-02-16,APPLE.COM/BILL,,Presentment,,"Conto corrente principale",-3.99,3.99,EUR,1
2026-03-20,2026-03-20,Mario Rossi,IT00N260000000000001,Credit Transfer,Bonifico ricevuto,"Conto corrente principale",250.00,250.00,EUR,1
2026-03-21,2026-03-21,Merchant GBP,GB00N260000000000001,Presentment,Pagamento estero,"Conto corrente principale",-12.34,10.00,GBP,1.234
data non valida,2026-03-22,Merchant invalid,IT00N260000000000002,Presentment,Data invalida,"Conto corrente principale",-5.00,5.00,EUR,1
CSV);

    $import = makeImportServiceTestImport(
        user: $user,
        account: $account,
        storedFilename: 'imports/n26.csv',
        format: $format,
    );

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $rows = ImportRow::query()
        ->where('import_id', $processedImport->id)
        ->orderBy('row_index')
        ->get()
        ->values();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->rows_count)->toBe(4)
        ->and($processedImport->review_rows_count)->toBe(4)
        ->and($processedImport->ready_rows_count)->toBe(0)
        ->and($processedImport->meta)->toMatchArray([
            'parser' => 'profile_csv',
            'import_format_profile' => true,
            'mapped_headers' => [
                'Booking Date' => 'date',
                'Value Date' => 'value_date',
                'Partner Name' => 'detail',
                'Payment Reference' => 'reference',
                'Amount (EUR)' => 'amount',
                'Original Currency' => 'currency',
            ],
        ]);

    expect($rows)->toHaveCount(4);

    expect($rows[0]->normalized_payload)->toMatchArray([
        'date' => '2026-02-16',
        'value_date' => '2026-02-16',
        'type' => 'expense',
        'amount' => '3.99',
        'detail' => 'APPLE.COM/BILL',
        'merchant' => 'APPLE.COM/BILL',
        'currency' => 'EUR',
        'import_metadata' => [
            'transaction_type' => 'Presentment',
            'account_name' => 'Conto corrente principale',
            'original_amount' => '3.99',
            'original_currency' => 'EUR',
            'exchange_rate' => '1',
        ],
    ]);

    expect($rows[1]->normalized_payload)->toMatchArray([
        'date' => '2026-03-20',
        'type' => 'income',
        'amount' => '250.00',
        'detail' => 'Mario Rossi',
        'merchant' => 'Mario Rossi',
        'reference' => 'Bonifico ricevuto',
        'currency' => 'EUR',
        'import_metadata' => [
            'partner_iban' => 'IT00N260000000000001',
            'transaction_type' => 'Credit Transfer',
            'payment_reference' => 'Bonifico ricevuto',
            'account_name' => 'Conto corrente principale',
            'original_amount' => '250.00',
            'original_currency' => 'EUR',
            'exchange_rate' => '1',
        ],
    ]);

    expect($rows[2]->normalized_payload)->toMatchArray([
        'date' => '2026-03-21',
        'type' => 'expense',
        'amount' => '12.34',
        'detail' => 'Merchant GBP',
        'reference' => 'Pagamento estero',
        'currency' => 'GBP',
        'import_metadata' => [
            'partner_iban' => 'GB00N260000000000001',
            'transaction_type' => 'Presentment',
            'payment_reference' => 'Pagamento estero',
            'account_name' => 'Conto corrente principale',
            'original_amount' => '10.00',
            'original_currency' => 'GBP',
            'exchange_rate' => '1.234',
        ],
    ]);

    expect($rows[2]->normalized_payload['amount'])->not->toBe($rows[2]->normalized_payload['import_metadata']['original_amount'])
        ->and($rows[3]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[3]->normalized_payload['date'])->toBeNull();
});

test('paypal csv profile maps net amounts and keeps funding rows in review', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    $format = ImportFormat::ensurePayPalCsv();

    Storage::disk('local')->put('imports/paypal.csv', <<<'CSV'
﻿"Data","Ora","Fuso orario","Descrizione","Valuta","Lordo ","Tariffa ","Netto","Saldo","Codice transazione","Indirizzo email mittente","Nome","Nome banca","Conto bancario","Importo per spedizione e imballaggio","IVA","N. fattura pro-forma","Codice transazione di riferimento"
"3/1/2026","19:54:59","Europe/Berlin","Pagamento Express Checkout","EUR","-314,50","0,00","-314,50","-115,80","65111421VJ024184P","nexi.paypal.pagopa@nexigroup.com","Nexi Payments S.p.a.","","","0,00","0,00","404289239981260039",""
"3/1/2026","19:54:59","Europe/Berlin","Versamento generico con carta","EUR","314,50","0,00","314,50","198,70","77F11395KN441230J","","","","","0,00","0,00","404289239981260039","65111421VJ024184P"
"4/1/2026","01:33:00","Europe/Berlin","Pagamento preautorizzato utenza","EUR","-19,97","0,00","-19,97","178,73","9VC41084KL328342C","finance-portugal@paddle.com","Paddle.net","","","0,00","0,00","","B-1K441373W4014852H"
CSV);

    $import = makeImportServiceTestImport(
        user: $user,
        account: $account,
        storedFilename: 'imports/paypal.csv',
        format: $format,
    );

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $rows = ImportRow::query()
        ->where('import_id', $processedImport->id)
        ->orderBy('row_index')
        ->get()
        ->values();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->rows_count)->toBe(3)
        ->and($processedImport->review_rows_count)->toBe(3)
        ->and($processedImport->ready_rows_count)->toBe(0)
        ->and($processedImport->meta)->toMatchArray([
            'parser' => 'profile_csv',
            'import_format_profile' => true,
            'mapped_headers' => [
                'Data' => 'date',
                'Descrizione' => 'detail',
                'Valuta' => 'currency',
                'Netto' => 'amount',
                'Codice transazione' => 'external_reference',
                'Nome' => 'merchant',
            ],
        ]);

    expect($rows)->toHaveCount(3);

    expect($rows[0]->normalized_payload)->toMatchArray([
        'date' => '2026-01-03',
        'type' => 'expense',
        'amount' => '314.50',
        'detail' => 'Pagamento Express Checkout',
        'merchant' => 'Nexi Payments S.p.a.',
        'external_reference' => '65111421VJ024184P',
        'currency' => 'EUR',
        'balance' => '-115.80',
        'import_metadata' => [
            'gross' => '-314,50',
            'fee' => '0,00',
            'balance' => '-115,80',
            'transaction_code' => '65111421VJ024184P',
            'sender_email' => 'nexi.paypal.pagopa@nexigroup.com',
            'shipping_amount' => '0,00',
            'vat' => '0,00',
            'pro_forma_invoice_number' => '404289239981260039',
            'timezone' => 'Europe/Berlin',
        ],
    ]);

    expect($rows[1]->normalized_payload)->toMatchArray([
        'date' => '2026-01-03',
        'type' => 'income',
        'amount' => '314.50',
        'detail' => 'Versamento generico con carta',
        'external_reference' => '77F11395KN441230J',
        'currency' => 'EUR',
        'import_metadata' => [
            'gross' => '314,50',
            'fee' => '0,00',
            'balance' => '198,70',
            'transaction_code' => '77F11395KN441230J',
            'shipping_amount' => '0,00',
            'vat' => '0,00',
            'pro_forma_invoice_number' => '404289239981260039',
            'reference_transaction_code' => '65111421VJ024184P',
            'timezone' => 'Europe/Berlin',
        ],
    ])
        ->and($rows[1]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[1]->warnings)->toContain('Tipo movimento da verificare prima dell’import: Versamento generico con carta.')
        ->and($rows[1]->fingerprint)->toBeNull();

    expect($rows[2]->normalized_payload)->toMatchArray([
        'date' => '2026-01-04',
        'type' => 'expense',
        'amount' => '19.97',
        'detail' => 'Pagamento preautorizzato utenza',
        'merchant' => 'Paddle.net',
        'import_metadata' => [
            'gross' => '-19,97',
            'fee' => '0,00',
            'balance' => '178,73',
            'transaction_code' => '9VC41084KL328342C',
            'sender_email' => 'finance-portugal@paddle.com',
            'shipping_amount' => '0,00',
            'vat' => '0,00',
            'reference_transaction_code' => 'B-1K441373W4014852H',
            'timezone' => 'Europe/Berlin',
        ],
    ]);
});

test('satispay xlsx profile reads transactions sheet and preserves meal voucher metadata', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $account = makeImportServiceTestAccount($user);
    $format = ImportFormat::ensureSatispayXlsx();

    $xlsx = makeImportServiceTwoSheetXlsx(
        firstSheetRows: [
            1 => ['A' => 'Legenda'],
            2 => ['A' => 'Questo foglio non contiene movimenti'],
        ],
        secondSheetRows: [
            1 => [
                'A' => 'Data',
                'B' => 'Nome',
                'C' => 'Descrizione',
                'D' => 'Importo',
                'E' => 'Tipo',
                'F' => 'Stato',
                'G' => 'Disponibilità',
                'H' => 'Buoni Pasto',
                'I' => 'Disponibilità dopo la transazione',
                'J' => "ID (Comunicalo all'Assistenza Clienti in caso di problemi)",
            ],
            2 => [
                'A' => '07/03/2026',
                'B' => 'Sole 365',
                'C' => null,
                'D' => -66.84,
                'E' => '🏬 a un Negozio',
                'F' => '✅ Approvato',
                'G' => '-10.84',
                'H' => '-56',
                'I' => '135.19',
                'J' => '019cc8a1-b4ae-734e-a652-27faff13cbbc',
            ],
            3 => [
                'A' => '10/03/2026',
                'B' => 'Mario Rossi',
                'C' => 'Ricarica ricevuta',
                'D' => 25,
                'E' => '💸 da un Contatto',
                'F' => '✅ Approvato',
                'G' => '25',
                'H' => null,
                'I' => '160.19',
                'J' => '019d0000-1111-7222-8333-000000000001',
            ],
            4 => [
                'A' => '11/03/2026',
                'B' => 'Pagamento sospeso',
                'C' => 'Operazione non conclusa',
                'D' => -8.5,
                'E' => '🏬 a un Negozio',
                'F' => '⏳ In attesa',
                'G' => '-8.5',
                'H' => null,
                'I' => '151.69',
                'J' => '019d0000-1111-7222-8333-000000000002',
            ],
        ],
        secondSheetName: 'Transactions',
    );

    Storage::disk('local')->put('imports/satispay.xlsx', $xlsx);

    $import = makeImportServiceTestImport(
        user: $user,
        account: $account,
        storedFilename: 'imports/satispay.xlsx',
        format: $format,
        sourceType: ImportSourceTypeEnum::XLSX,
    );

    $processedImport = app(ProcessGenericCsvImportService::class)->execute($import, 2026);
    $rows = ImportRow::query()
        ->where('import_id', $processedImport->id)
        ->orderBy('row_index')
        ->get()
        ->values();

    expect($processedImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($processedImport->rows_count)->toBe(3)
        ->and($processedImport->review_rows_count)->toBe(3)
        ->and($processedImport->ready_rows_count)->toBe(0)
        ->and($processedImport->meta)->toMatchArray([
            'parser' => 'profile_xlsx',
            'import_format_profile' => true,
            'mapped_headers' => [
                'Data' => 'date',
                'Nome' => 'detail',
                'Descrizione' => 'reference',
                'Importo' => 'amount',
                "ID (Comunicalo all'Assistenza Clienti in caso di problemi)" => 'external_reference',
            ],
        ]);

    expect($rows)->toHaveCount(3);

    expect($rows[0]->normalized_payload)->toMatchArray([
        'date' => '2026-03-07',
        'type' => 'expense',
        'amount' => '66.84',
        'detail' => 'Sole 365',
        'merchant' => 'Sole 365',
        'external_reference' => '019cc8a1-b4ae-734e-a652-27faff13cbbc',
        'import_metadata' => [
            'type' => '🏬 a un Negozio',
            'state' => '✅ Approvato',
            'availability' => '-10.84',
            'meal_vouchers' => '-56',
            'availability_after_transaction' => '135.19',
            'transaction_id' => '019cc8a1-b4ae-734e-a652-27faff13cbbc',
        ],
    ]);

    expect($rows[1]->normalized_payload)->toMatchArray([
        'date' => '2026-03-10',
        'type' => 'income',
        'amount' => '25.00',
        'detail' => 'Mario Rossi',
        'merchant' => 'Mario Rossi',
        'reference' => 'Ricarica ricevuta',
        'external_reference' => '019d0000-1111-7222-8333-000000000001',
        'import_metadata' => [
            'type' => '💸 da un Contatto',
            'state' => '✅ Approvato',
            'description' => 'Ricarica ricevuta',
            'availability' => '25',
            'availability_after_transaction' => '160.19',
            'transaction_id' => '019d0000-1111-7222-8333-000000000001',
        ],
    ]);

    expect($rows[2]->normalized_payload)->toMatchArray([
        'date' => '2026-03-11',
        'type' => 'expense',
        'amount' => '8.50',
        'detail' => 'Pagamento sospeso',
        'import_metadata' => [
            'type' => '🏬 a un Negozio',
            'state' => '⏳ In attesa',
            'description' => 'Operazione non conclusa',
            'availability' => '-8.5',
            'availability_after_transaction' => '151.69',
            'transaction_id' => '019d0000-1111-7222-8333-000000000002',
        ],
    ])
        ->and($rows[2]->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($rows[2]->warnings)->toContain('Stato movimento non importabile automaticamente: ⏳ In attesa.')
        ->and($rows[2]->fingerprint)->toBeNull();
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
