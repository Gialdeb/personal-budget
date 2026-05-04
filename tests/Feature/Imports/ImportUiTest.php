<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\ImportFormatStatusEnum;
use App\Enums\ImportFormatTypeEnum;
use App\Enums\ImportRowParseStatusEnum;
use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportFormat;
use App\Models\ImportRow;
use App\Models\Merchant;
use App\Models\NotificationTopic;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use App\Supports\Imports\ImportFingerprintGenerator;
use App\Supports\Imports\ImportTemplateXlsxBuilder;
use Database\Seeders\NotificationTopicSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('features.imports.enabled', true);
    $this->seed(NotificationTopicSeeder::class);
});

it('serves imports routes under the settings prefix', function () {
    expect(route('imports.index'))->toEndWith('/settings/imports')
        ->and(route('imports.template'))->toEndWith('/settings/imports/template/xlsx')
        ->and(route('imports.show', ['import' => 'test-import-uuid']))->toEndWith('/settings/imports/test-import-uuid');
});

function importUiUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    UserSetting::query()->create([
        'user_id' => $user->id,
        'active_year' => 2026,
        'base_currency' => 'EUR',
    ]);

    return $user;
}

function importUiAccount(User $user): Account
{
    $bank = Bank::query()->create([
        'name' => 'Banca Operativa Societa Per Azioni Filiale Centrale',
        'display_name' => 'Banca Operativa',
        'slug' => 'banca-operativa',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $accountType = AccountType::query()->create([
        'code' => 'payment-account-ui',
        'name' => 'Conto operativo',
        'balance_nature' => AccountBalanceNatureEnum::ASSET,
    ]);

    return Account::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto famiglia',
        'currency' => $user->base_currency_code,
        'currency_code' => $user->base_currency_code,
        'opening_balance' => 1000,
        'current_balance' => 1000,
        'is_manual' => true,
        'is_active' => true,
    ]);
}

function importUiFormat(?Bank $bank = null): ImportFormat
{
    return ImportFormat::query()->create([
        'bank_id' => $bank?->id,
        'code' => 'generic-csv-ui',
        'name' => 'Template XLSX guidato v1',
        'version' => 'v1',
        'type' => ImportFormatTypeEnum::GENERIC_CSV,
        'status' => ImportFormatStatusEnum::ACTIVE,
        'is_generic' => true,
        'notes' => 'Formato guidato basato sul template XLSX ufficiale generato dall’app.',
    ]);
}

function importUiCategory(User $user, string $name = 'Spesa alimentare'): Category
{
    return Category::query()->create([
        'user_id' => $user->id,
        'name' => $name,
        'slug' => str($name)->slug()->value(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);
}

function importUiMerchant(User $user, ?Category $category = null, string $name = 'Bar Centrale'): Merchant
{
    return Merchant::query()->create([
        'user_id' => $user->id,
        'name' => $name,
        'normalized_name' => str($name)->lower()->value(),
        'default_category_id' => $category?->id,
        'is_active' => true,
    ]);
}

function importUiTrackedItem(User $user, string $name = 'Spesa generica', array $settings = []): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $user->id,
        'account_id' => null,
        'parent_id' => null,
        'name' => $name,
        'slug' => str($name)->slug()->value(),
        'type' => null,
        'is_active' => true,
        'settings' => $settings,
    ]);
}

function xlsxZipFromResponse(TestResponse $response): ZipArchive
{
    $file = $response->baseResponse->getFile();
    $zip = new ZipArchive;
    $zip->open($file->getPathname());

    return $zip;
}

function importUiXlsxWithAccountCell(string $templatePath, string $oldValue, string $newValue): string
{
    $path = tempnam(sys_get_temp_dir(), 'imports-test-').'.xlsx';
    copy($templatePath, $path);

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->addFromString(
        'xl/worksheets/sheet1.xml',
        str_replace(
            htmlspecialchars($oldValue, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
            htmlspecialchars($newValue, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
            $sheet
        )
    );
    $zip->close();

    return $path;
}

function importUiSharedStringsXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'imports-shared-').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $strings = collect($rows)->flatten()->map(fn ($value): string => (string) $value)->values();
    $stringIndexes = $strings->flip();
    $sheetRows = collect($rows)->map(function (array $row, int $rowIndex) use ($stringIndexes): string {
        $cells = collect(array_values($row))->map(function (string $value, int $columnIndex) use ($rowIndex, $stringIndexes): string {
            $cell = chr(65 + $columnIndex).($rowIndex + 1);

            return '<c r="'.$cell.'" t="s"><v>'.$stringIndexes[$value].'</v></c>';
        })->implode('');

        return '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
    })->implode('');
    $sharedStrings = $strings->map(function (string $value): string {
        $splitAt = max(1, intdiv(mb_strlen($value), 2));

        return '<si><r><t>'.htmlspecialchars(mb_substr($value, 0, $splitAt), ENT_XML1 | ENT_COMPAT, 'UTF-8').'</t></r><r><t>'.htmlspecialchars(mb_substr($value, $splitAt), ENT_XML1 | ENT_COMPAT, 'UTF-8').'</t></r></si>';
    })->implode('');

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Movements" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$strings->count().'" uniqueCount="'.$strings->count().'">'.$sharedStrings.'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    $zip->close();

    return $path;
}

function importUiStructurallyInvalidXlsx(): string
{
    $path = tempnam(sys_get_temp_dir(), 'imports-invalid-').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
    $zip->close();

    return $path;
}

function importUiMediobancaXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'imports-mediobanca-').'.xlsx';
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
                    $cellReference = $column.$rowNumber;

                    if (is_string($value)) {
                        return '<c r="'.$cellReference.'" t="s"><v>'.$sharedStringIndexes[$value].'</v></c>';
                    }

                    if ($value === null) {
                        return '<c r="'.$cellReference.'"/>';
                    }

                    return '<c r="'.$cellReference.'"><v>'.$value.'</v></c>';
                })
                ->implode('');

            return '<row r="'.$rowNumber.'">'.$rowCells.'</row>';
        })
        ->implode('');

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Movimenti" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$sharedStrings->count().'" uniqueCount="'.$sharedStrings->count().'">'.$sharedStringsXml.'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    $zip->close();

    return $path;
}

function importUiInlineRichTextTemplate(string $templatePath): string
{
    $path = tempnam(sys_get_temp_dir(), 'imports-inline-rich-').'.xlsx';
    copy($templatePath, $path);

    $zip = new ZipArchive;
    $zip->open($path);

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

    $updatedSheet = preg_replace_callback(
        '/<c r="([A-J]1)" t="inlineStr"(.*?)><is><t>(.*?)<\/t><\/is><\/c>/',
        static function (array $matches): string {
            $value = htmlspecialchars_decode($matches[3], ENT_QUOTES | ENT_XML1);
            $firstChunk = mb_substr($value, 0, max(1, intdiv(mb_strlen($value), 2)));
            $secondChunk = mb_substr($value, mb_strlen($firstChunk));

            return '<c r="'.$matches[1].'" t="inlineStr"'.$matches[2].'><is><r><t>'.
                htmlspecialchars($firstChunk, ENT_XML1 | ENT_COMPAT, 'UTF-8').
                '</t></r><r><t>'.
                htmlspecialchars($secondChunk, ENT_XML1 | ENT_COMPAT, 'UTF-8').
                '</t></r></is></c>';
        },
        $sheet ?? '',
    );

    $zip->addFromString('xl/worksheets/sheet1.xml', $updatedSheet ?: $sheet);
    $zip->close();

    return $path;
}

function importUiDateSerialXlsx(array $headers, array $row, int $dateStyleIndex = 1): string
{
    $path = tempnam(sys_get_temp_dir(), 'imports-date-serial-').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $headerCells = collect($headers)->values()->map(
        fn (string $value, int $columnIndex): string => '<c r="'.chr(65 + $columnIndex).'1" t="inlineStr"><is><t>'.
            htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8').
            '</t></is></c>'
    )->implode('');

    $rowCells = collect($row)->values()->map(function (string|int|float|null $value, int $columnIndex) use ($dateStyleIndex): string {
        $cell = chr(65 + $columnIndex).'2';

        if ($columnIndex === 1 && is_numeric($value)) {
            return '<c r="'.$cell.'" s="'.$dateStyleIndex.'"><v>'.$value.'</v></c>';
        }

        return '<c r="'.$cell.'" t="inlineStr"><is><t>'.
            htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8').
            '</t></is></c>';
    })->implode('');

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Movements" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$headerCells.'</row><row r="2">'.$rowCells.'</row></sheetData></worksheet>');
    $zip->close();

    return $path;
}

function importUiRecord(User $user, Account $account, ImportFormat $format): Import
{
    return Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'movimenti-marzo.csv',
        'stored_filename' => 'imports/test.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv',
        'status' => ImportStatusEnum::REVIEW_REQUIRED,
        'rows_count' => 4,
        'ready_rows_count' => 1,
        'review_rows_count' => 1,
        'invalid_rows_count' => 1,
        'duplicate_rows_count' => 1,
        'meta' => [
            'parser' => 'generic_csv',
            'management_year' => 2026,
        ],
    ]);
}

test('imports index renders operational list and upload options', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $account->update(['is_default' => true]);
    $format = importUiFormat($account->bank);
    $import = importUiRecord($user, $account, $format);

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->where('importsPage.active_year', 2026)
            ->where('importsPage.active_year_notice', fn (string $value) => str_contains($value, 'anno gestionale 2026'))
            ->where('importsPage.available_years.0.value', 2026)
            ->where('imports.summary.total_count', 1)
            ->where('imports.summary.review_required_count', 1)
            ->where('imports.data.0.uuid', $import->uuid)
            ->where('imports.data.0.original_filename', 'movimenti-marzo.csv')
            ->where('imports.data.0.parser_label', 'Template XLSX guidato')
            ->where('imports.data.0.bank_name', 'Banca Operativa')
            ->where('imports.data.0.review_rows_count', 1)
            ->where('imports.data.0.management_year', 2026)
            ->where('imports.pagination.current_page', 1)
            ->where('imports.pagination.last_page', 1)
            ->where('imports.pagination.has_pages', false)
            ->where('options.formats.0.name', 'Template XLSX guidato v1')
            ->where('options.default_format_uuid', null)
            ->where('options.has_single_active_format', false)
        );
});

test('imports index auto-provisions the generic csv format when none exists', function () {
    $user = importUiUser();
    importUiAccount($user);

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->where('options.formats.0.code', 'generic_csv_v1')
            ->where('options.default_format_uuid', fn (?string $value) => filled($value))
            ->where('options.has_single_active_format', true)
        );

    $this->assertDatabaseHas('import_formats', [
        'code' => 'generic_csv_v1',
        'type' => ImportFormatTypeEnum::GENERIC_CSV->value,
        'status' => ImportFormatStatusEnum::ACTIVE->value,
        'is_generic' => true,
    ]);
});

test('imports index exposes bank profile formats only to admin users', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = importUiUser();
    $account = importUiAccount($user);
    $admin = importUiUser();
    $admin->assignRole('admin');

    importUiFormat($account->bank);
    ImportFormat::query()->create([
        'bank_id' => $account->bank_id,
        'code' => 'advanced-bank-profile',
        'name' => 'Profilo banca avanzato',
        'version' => 'v1',
        'type' => ImportFormatTypeEnum::BANK_CSV,
        'status' => ImportFormatStatusEnum::ACTIVE,
        'is_generic' => false,
        'settings' => [
            'source_types' => ['csv', 'xlsx'],
            'header_row' => 1,
            'skip_rows' => [],
            'columns' => [
                'date' => 'Data operazione',
                'amount' => 'Importo',
                'description' => 'Descrizione',
            ],
            'amount' => [
                'mode' => 'signed_amount',
                'debit_column' => null,
                'credit_column' => null,
                'debit_sign' => 'negative',
            ],
            'normalization' => [
                'date_format' => 'd/m/Y',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->has('options.formats', 2)
            ->where('options.formats.0.is_generic', true)
            ->where('options.formats.1.is_generic', true));

    $this->actingAs($admin)
        ->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->has('options.formats', 9)
            ->where('options.formats.2.is_advanced', true)
            ->where('options.formats.2.name', 'Hype XLSX')
            ->where('options.formats.2.parser_label', 'XLSX bancario')
            ->where('options.formats.2.bank_name', 'Hype')
            ->where('options.formats.3.is_advanced', true)
            ->where('options.formats.3.name', 'Mediobanca XLSX')
            ->where('options.formats.3.parser_label', 'XLSX bancario')
            ->where('options.formats.3.bank_name', 'Mediobanca')
            ->where('options.formats.4.is_advanced', true)
            ->where('options.formats.4.name', 'N26 CSV')
            ->where('options.formats.4.parser_label', 'CSV bancario')
            ->where('options.formats.4.bank_name', 'N26')
            ->where('options.formats.5.is_advanced', true)
            ->where('options.formats.5.name', 'PayPal CSV')
            ->where('options.formats.5.parser_label', 'CSV bancario')
            ->where('options.formats.5.bank_name', 'PayPal')
            ->where('options.formats.6.is_advanced', true)
            ->where('options.formats.7.is_advanced', true)
            ->where('options.formats.7.name', 'Revolut CSV')
            ->where('options.formats.7.parser_label', 'CSV bancario')
            ->where('options.formats.7.bank_name', 'Revolut')
            ->where('options.formats.8.is_advanced', true)
            ->where('options.formats.8.name', 'Satispay XLSX')
            ->where('options.formats.8.parser_label', 'XLSX bancario')
            ->where('options.formats.8.bank_name', 'Satispay'));
});

test('imports store rejects bank profile formats for non admin users', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = importUiUser();
    $account = importUiAccount($user);
    $format = ImportFormat::query()->create([
        'bank_id' => $account->bank_id,
        'code' => 'non-admin-bank-profile',
        'name' => 'Profilo banca riservato',
        'version' => 'v1',
        'type' => ImportFormatTypeEnum::BANK_CSV,
        'status' => ImportFormatStatusEnum::ACTIVE,
        'is_generic' => false,
        'settings' => [
            'source_types' => ['csv'],
            'header_row' => 1,
            'skip_rows' => [],
            'columns' => [
                'date' => 'Data operazione',
                'amount' => 'Importo',
                'description' => 'Descrizione',
            ],
            'amount' => [
                'mode' => 'signed_amount',
                'debit_column' => null,
                'credit_column' => null,
                'debit_sign' => 'negative',
            ],
            'normalization' => [
                'date_format' => 'd/m/Y',
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'description_cleanup' => [
                    'collapse_spaces' => true,
                    'uppercase' => false,
                ],
            ],
        ],
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'movimenti.csv',
        "Data operazione;Importo;Descrizione\n01/03/2026;-12,50;Spesa test\n",
    );

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.store'), [
            'import_format_uuid' => $format->uuid,
            'file' => $file,
        ])
        ->assertRedirect(route('imports.index'))
        ->assertSessionHasErrors([
            'import_format_uuid' => 'Il formato selezionato non è disponibile per il tuo profilo.',
        ]);
});

test('imports detail renders rows with raw and normalized payloads', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $import = importUiRecord($user, $account, $format);

    ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 2,
        'raw_date' => '04/03/2025',
        'raw_description' => 'Vecchia spesa',
        'raw_amount' => '10,00',
        'raw_balance' => '1040,00',
        'raw_payload' => [
            'date' => '04/03/2025',
            'type' => 'Spesa',
            'detail' => 'Vecchia spesa',
        ],
        'normalized_payload' => [
            'date' => '2025-03-04',
            'type' => 'expense',
            'amount' => '10.00',
            'balance' => '1040.00',
            'detail' => 'Vecchia spesa',
        ],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::BLOCKED_YEAR,
        'errors' => ["La riga è del 2025, ma questo import lavora sull'anno gestionale 2026."],
        'warnings' => ['Anno non disponibile per l’import corrente.'],
    ]);

    $this->actingAs($user)
        ->get(route('imports.show', ['import' => $import->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Show')
            ->where('importDetail.uuid', $import->uuid)
            ->where('importDetail.original_filename', 'movimenti-marzo.csv')
            ->where('importDetail.bank_name', 'Banca Operativa')
            ->where('importDetail.management_year', 2026)
            ->where('importDetail.blocked_year_rows_count', 1)
            ->where('importDetail.can_import_ready', true)
            ->where('importDetail.can_rollback', false)
            ->where('rows.0.row_index', 2)
            ->where('rows.0.status', ImportRowStatusEnum::BLOCKED_YEAR->value)
            ->where('rows.0.type_label', 'Spesa')
            ->where('rows.0.category_label', null)
            ->where('rows.0.can_edit_review', true)
            ->where('rows.0.can_skip', true)
            ->where('rows.0.amount_value_raw', '10.00')
            ->where('rows.0.review_values.date', '04/03/2025')
            ->where('rows.0.review_values.type', 'Spesa')
            ->where('rows.0.review_values.amount_value_raw', '10.00')
            ->where('rows.0.review_values.balance_value_raw', '1040.00')
            ->where('rows.0.errors.0', "La riga è del 2025, ma questo import lavora sull'anno gestionale 2026.")
            ->where('rows.0.raw_payload.0.label', 'Data')
            ->where('rows.0.normalized_payload.0.value', '2025-03-04')
            ->where('categories', [])
        );
});

test('imports detail exposes selectable categories for row review', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Spese casa');
    $import = importUiRecord($user, $account, $format);

    ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 1,
        'raw_date' => '04/03/2026',
        'raw_description' => 'Spesa casa',
        'raw_amount' => '10,00',
        'raw_balance' => '1040,00',
        'raw_payload' => [
            'date' => '04/03/2026',
            'type' => 'Spesa',
            'detail' => 'Spesa casa',
            'category' => 'Categoria mancante CSV',
        ],
        'normalized_payload' => [
            'date' => '2026-03-04',
            'type' => 'expense',
            'detail' => 'Spesa casa',
            'category' => 'Categoria mancante CSV',
        ],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW,
        'errors' => [],
        'warnings' => ['La categoria non è valorizzata e richiede revisione.'],
    ]);

    $this->actingAs($user)
        ->get(route('imports.show', ['import' => $import->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Show')
            ->where('categories.0.value', $category->uuid)
            ->where('categories.0.label', $category->name)
            ->where('categories.0.group_type', CategoryGroupTypeEnum::EXPENSE->value)
            ->where('rows.0.review_values.category', 'Categoria mancante CSV')
        );
});

test('imports detail exposes a single persisted feedback message for duplicate and skipped rows', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $import = importUiRecord($user, $account, $format);

    ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 1,
        'raw_date' => '25/03/2026',
        'raw_description' => 'Pagamento ricorrente',
        'raw_amount' => '22,00',
        'raw_balance' => '800,00',
        'raw_payload' => [],
        'normalized_payload' => [],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::DUPLICATE_CANDIDATE,
        'errors' => [],
        'warnings' => ['Questa riga corrisponde a un movimento ancora presente nel ledger e richiede una decisione manuale.'],
    ]);

    ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 2,
        'raw_date' => '26/03/2026',
        'raw_description' => 'Riga saltata',
        'raw_amount' => '10,00',
        'raw_balance' => '790,00',
        'raw_payload' => [],
        'normalized_payload' => [],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::SKIPPED,
        'errors' => [],
        'warnings' => ['Riga saltata manualmente dall’utente.'],
    ]);

    $this->actingAs($user)
        ->get(route('imports.show', ['import' => $import->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Show')
            ->where('rows.0.status', ImportRowStatusEnum::DUPLICATE_CANDIDATE->value)
            ->where('rows.0.errors', [])
            ->where('rows.0.warnings', ['Questa riga corrisponde a un movimento ancora presente nel ledger e richiede una decisione manuale.'])
            ->where('rows.1.status', ImportRowStatusEnum::SKIPPED->value)
            ->where('rows.1.errors', [])
            ->where('rows.1.warnings', ['Riga saltata manualmente dall’utente.'])
        );
});

test('imports detail exposes hierarchical categories and tracked item references for the review dialog', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $parentCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese',
        'slug' => 'spese',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => false,
    ]);
    $category = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $parentCategory->id,
        'name' => 'Casa',
        'slug' => 'casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $trackedItem = importUiTrackedItem($user, 'Bollette casa', [
        'transaction_group_keys' => ['expense'],
        'transaction_category_uuids' => [$category->uuid],
    ]);
    $import = importUiRecord($user, $account, $format);

    $this->actingAs($user)
        ->get(route('imports.show', ['import' => $import->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Show')
            ->where('categories.0.value', $category->uuid)
            ->where('categories.0.full_path', 'Spese > Casa')
            ->where('categories.0.group_type', CategoryGroupTypeEnum::EXPENSE->value)
            ->where('categories.0.ancestor_uuids.0', $parentCategory->uuid)
            ->where('reference_options.0.value', $trackedItem->uuid)
            ->where('reference_options.0.full_path', 'Bollette casa')
            ->where('reference_options.0.category_uuids.0', $category->uuid)
        );
});

test('imports index paginates the import history', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);

    foreach (range(1, 12) as $index) {
        Import::query()->create([
            'user_id' => $user->id,
            'bank_id' => $account->bank_id,
            'account_id' => $account->id,
            'import_format_id' => $format->id,
            'original_filename' => "import-{$index}.csv",
            'stored_filename' => "imports/import-{$index}.csv",
            'mime_type' => 'text/csv',
            'source_type' => ImportSourceTypeEnum::CSV,
            'parser_key' => 'generic_csv_v1',
            'status' => ImportStatusEnum::PARSED,
            'meta' => ['management_year' => 2026],
        ]);
    }

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->has('imports.data', 10)
            ->where('imports.pagination.current_page', 1)
            ->where('imports.pagination.last_page', 2)
            ->where('imports.pagination.has_pages', true)
            ->where('imports.pagination.total', 12)
            ->where('imports.pagination.pages.0.label', '1')
        );
});

test('imports index filters by status and management year', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);

    Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'review.csv',
        'stored_filename' => 'imports/review.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::REVIEW_REQUIRED,
        'meta' => ['management_year' => 2026],
    ]);

    Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'rolled-back.csv',
        'stored_filename' => 'imports/rolled-back.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::ROLLED_BACK,
        'meta' => ['management_year' => 2025],
    ]);

    $this->actingAs($user)
        ->get(route('imports.index', [
            'status' => ImportStatusEnum::REVIEW_REQUIRED->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->where('importsPage.active_year', 2026)
            ->where('filters.current_status', ImportStatusEnum::REVIEW_REQUIRED->value)
            ->where('imports.data.0.original_filename', 'review.csv')
            ->where('imports.summary.total_count', 1)
        );
});

test('imports index follows the selected active management year like other sections', function () {
    $user = importUiUser();
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2025,
        'is_closed' => false,
    ]);
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);

    Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'year-2025.csv',
        'stored_filename' => 'imports/year-2025.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::PARSED,
        'meta' => ['management_year' => 2025],
    ]);

    Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'year-2026.csv',
        'stored_filename' => 'imports/year-2026.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::PARSED,
        'meta' => ['management_year' => 2026],
    ]);

    $this->actingAs($user)
        ->get(route('imports.index', ['year' => 2025]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->where('importsPage.active_year', 2025)
            ->where('imports.data.0.original_filename', 'year-2025.csv')
            ->where('imports.summary.total_count', 1)
        );

    expect($user->fresh()->settings?->active_year)->toBe(2025);
});

test('imports template downloads a localized xlsx with user dropdown data and prepared rows', function () {
    $user = importUiUser();
    $category = importUiCategory($user, 'Spese casa');
    $account = importUiAccount($user);
    $destinationAccount = Account::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_type_id' => $account->account_type_id,
        'name' => 'Conto risparmio',
        'currency' => $user->base_currency_code,
        'currency_code' => $user->base_currency_code,
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('imports.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $zip = xlsxZipFromResponse($response);

    expect($response->headers->get('content-disposition'))->toContain('template-importazioni.xlsx')
        ->and($zip->getFromName('xl/workbook.xml'))->toContain('Movements')
        ->and($zip->getFromName('xl/workbook.xml'))->toContain('Lists')
        ->and($zip->getFromName('xl/workbook.xml'))->toContain('Instructions');

    $movements = $zip->getFromName('xl/worksheets/sheet1.xml');
    $lists = $zip->getFromName('xl/worksheets/sheet2.xml');
    $instructions = $zip->getFromName('xl/worksheets/sheet3.xml');

    expect($movements)->toContain('Data')
        ->toContain('<c r="A1" t="inlineStr"')
        ->toContain('><is><t>Conto</t></is></c>')
        ->toContain('Conto destinazione')
        ->not->toContain('Conto UUID')
        ->not->toContain('Account UUID')
        ->not->toContain('Saldo')
        ->toContain('Conto')
        ->toContain($account->uuid)
        ->toContain('sqref="A2:A1001"')
        ->toContain('sqref="C2:C1001"')
        ->toContain('sqref="F2:F1001"')
        ->toContain('sqref="G2:G1001"')
        ->and($lists)->toContain('Spese casa')
        ->toContain($destinationAccount->uuid)
        ->and($instructions)->toContain('anno gestionale 2026')
        ->toContain('Usa una sola colonna Conto')
        ->toContain('Compila Conto destinazione solo per le righe di tipo giroconto');

    $zip->close();
});

test('imports template uses english copy for english users', function () {
    $user = importUiUser();
    $user->update(['locale' => 'en', 'format_locale' => 'en-GB']);

    $response = $this->actingAs($user)->get(route('imports.template'));

    $response->assertOk();

    $zip = xlsxZipFromResponse($response);

    expect($response->headers->get('content-disposition'))->toContain('imports-template.xlsx')
        ->and($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('Destination account')
        ->not->toContain('Account UUID')
        ->not->toContain('Balance')
        ->and($zip->getFromName('xl/worksheets/sheet3.xml'))
        ->toContain('management year 2026')
        ->toContain('Use a single Account column')
        ->toContain('Fill Destination account only for transfer rows');

    $zip->close();
});

test('imports store accepts generated xlsx template rows through the existing parser flow', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    importUiCategory($user, 'Spese casa');

    $template = app(ImportTemplateXlsxBuilder::class)->build($user, 2026);
    $file = new UploadedFile(
        $template['path'],
        'movimenti.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect();

    $import = Import::query()->latest('id')->first();
    $row = $import?->rows()->first();

    expect($import?->source_type)->toBe(ImportSourceTypeEnum::XLSX)
        ->and($import?->account_id)->toBeNull()
        ->and($import?->meta['parser'])->toBe('generic_xlsx')
        ->and($import?->rows_count)->toBe(1)
        ->and($row?->normalized_payload['account_id'])->toBe($account->id)
        ->and($row?->normalized_payload['category'])->toBe('Spese casa')
        ->and($row?->status)->toBe(ImportRowStatusEnum::READY);
});

test('imports store accepts the official generated xlsx template as the golden path for english users', function () {
    $user = importUiUser();
    $user->update(['locale' => 'en', 'format_locale' => 'en-GB']);
    $account = importUiAccount($user);
    importUiCategory($user, 'Current expenses');

    $template = app(ImportTemplateXlsxBuilder::class)->build($user, 2026);
    $file = new UploadedFile(
        $template['path'],
        'imports-template.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect();

    $import = Import::query()->latest('id')->first();
    $row = $import?->rows()->first();

    expect($import?->source_type)->toBe(ImportSourceTypeEnum::XLSX)
        ->and($import?->error_message)->toBeNull()
        ->and($import?->meta['parser'])->toBe('generic_xlsx')
        ->and($import?->rows_count)->toBe(1)
        ->and($row?->normalized_payload['account_id'])->toBe($account->id)
        ->and($row?->normalized_payload['type'])->toBe('expense')
        ->and($row?->normalized_payload['category'])->toBe('Current expenses')
        ->and($row?->status)->toBe(ImportRowStatusEnum::READY);
});

test('xlsx import keeps resolving a renamed account when the template carries its uuid', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    importUiCategory($user, 'Spese casa');

    $template = app(ImportTemplateXlsxBuilder::class)->build($user, 2026);
    $account->update(['name' => 'Conto rinominato']);

    $file = new UploadedFile(
        $template['path'],
        'movimenti-rinominati.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect();

    $row = Import::query()->latest('id')->firstOrFail()->rows()->firstOrFail();

    expect($row->status)->toBe(ImportRowStatusEnum::READY)
        ->and($row->normalized_payload['account_id'])->toBe($account->id)
        ->and($row->normalized_payload['account_uuid'])->toBe($account->uuid);
});

test('xlsx import accepts workbooks saved with shared string rich text', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    importUiCategory($user, 'Spese casa');

    $path = importUiSharedStringsXlsx([
        [
            'Conto',
            'Data',
            'Tipo',
            'Importo',
            'Dettaglio',
            'Categoria',
            'Conto destinazione',
            'Riferimento',
            'Esercente',
            'Riferimento esterno',
        ],
        [
            "Conto famiglia ({$account->uuid})",
            '15/03/2026',
            'Spesa',
            '18,50',
            'Spesa alimentare',
            'Spese casa',
            '',
            'RIF-001',
            'Bar Centrale',
            'EXT-001',
        ],
    ]);
    $file = new UploadedFile(
        $path,
        'movimenti-shared-strings.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Import letto correttamente: 1 righe elaborate, 1 pronte, 0 da verificare, 0 non valide, 0 duplicate.');

    $import = Import::query()->latest('id')->firstOrFail();
    $row = $import->rows()->firstOrFail();

    expect($import->status)->toBe(ImportStatusEnum::PARSED)
        ->and($import->error_message)->toBeNull()
        ->and($row->status)->toBe(ImportRowStatusEnum::READY)
        ->and($row->normalized_payload['account_id'])->toBe($account->id);
});

test('xlsx import accepts the official template after inline rich text round-trip on headers', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    importUiCategory($user, 'Spese casa');

    $template = app(ImportTemplateXlsxBuilder::class)->build($user, 2026);
    $path = importUiInlineRichTextTemplate($template['path']);
    $file = new UploadedFile(
        $path,
        'movimenti-inline-rich.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Import letto correttamente: 1 righe elaborate, 1 pronte, 0 da verificare, 0 non valide, 0 duplicate.');

    $import = Import::query()->latest('id')->firstOrFail();
    $row = $import->rows()->firstOrFail();

    expect($import->status)->toBe(ImportStatusEnum::PARSED)
        ->and($import->error_message)->toBeNull()
        ->and($import->meta['mapped_headers'])->toMatchArray([
            'Conto' => 'account',
            'Data' => 'date',
            'Tipo' => 'type',
            'Importo' => 'amount',
            'Dettaglio' => 'detail',
        ])
        ->and($row->status)->toBe(ImportRowStatusEnum::READY)
        ->and($row->normalized_payload['account_id'])->toBe($account->id);
});

test('xlsx import ignores destination account on non-transfer rows', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $destinationAccount = Account::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_type_id' => $account->account_type_id,
        'name' => 'Conto riserva',
        'currency' => $user->base_currency_code,
        'currency_code' => $user->base_currency_code,
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_manual' => true,
        'is_active' => true,
    ]);
    importUiCategory($user, 'Spese casa');

    $path = importUiSharedStringsXlsx([
        [
            'Conto',
            'Data',
            'Tipo',
            'Importo',
            'Dettaglio',
            'Categoria',
            'Conto destinazione',
            'Riferimento',
            'Esercente',
            'Riferimento esterno',
        ],
        [
            "Conto famiglia ({$account->uuid})",
            '15/03/2026',
            'Spesa',
            '18,50',
            'Spesa alimentare',
            'Spese casa',
            "Conto riserva ({$destinationAccount->uuid})",
            'RIF-001',
            'Bar Centrale',
            'EXT-001',
        ],
    ]);

    $file = new UploadedFile(
        $path,
        'movimenti-destination-ignored.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect();

    $row = Import::query()->latest('id')->firstOrFail()->rows()->firstOrFail();

    expect($row->status)->toBe(ImportRowStatusEnum::READY)
        ->and($row->normalized_payload['type'])->toBe('expense')
        ->and($row->normalized_payload['destination_account_id'] ?? null)->toBeNull();
});

test('xlsx import interprets excel serial dates from date-formatted cells', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    importUiCategory($user, 'Spese casa');

    $path = importUiDateSerialXlsx(
        [
            'Conto',
            'Data',
            'Tipo',
            'Importo',
            'Dettaglio',
            'Categoria',
            'Conto destinazione',
            'Riferimento',
            'Esercente',
            'Riferimento esterno',
        ],
        [
            "Conto famiglia ({$account->uuid})",
            46096,
            'Spesa',
            '18,50',
            'Spesa alimentare',
            'Spese casa',
            '',
            'RIF-001',
            'Bar Centrale',
            'EXT-001',
        ],
    );

    $file = new UploadedFile(
        $path,
        'movimenti-serial-date.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), ['file' => $file])
        ->assertRedirect();

    $import = Import::query()->latest('id')->firstOrFail();
    $row = $import->rows()->firstOrFail();

    expect($import->error_message)->toBeNull()
        ->and($row->status)->toBe(ImportRowStatusEnum::READY)
        ->and($row->raw_date)->toBe('15/03/2026')
        ->and($row->normalized_payload['date'])->toBe('2026-03-15')
        ->and($row->errors)->toBe([]);
});

test('xlsx import keeps accepting textual dates in the expected format', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    importUiCategory($user, 'Spese casa');

    $path = importUiSharedStringsXlsx([
        [
            'Conto',
            'Data',
            'Tipo',
            'Importo',
            'Dettaglio',
            'Categoria',
            'Conto destinazione',
            'Riferimento',
            'Esercente',
            'Riferimento esterno',
        ],
        [
            "Conto famiglia ({$account->uuid})",
            '15/03/2026',
            'Spesa',
            '18,50',
            'Spesa alimentare',
            'Spese casa',
            '',
            'RIF-001',
            'Bar Centrale',
            'EXT-001',
        ],
    ]);

    $file = new UploadedFile(
        $path,
        'movimenti-text-date.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), ['file' => $file])
        ->assertRedirect();

    $import = Import::query()->latest('id')->firstOrFail();
    $row = $import->rows()->firstOrFail();

    expect($import->error_message)->toBeNull()
        ->and($row->status)->toBe(ImportRowStatusEnum::READY)
        ->and($row->raw_date)->toBe('15/03/2026')
        ->and($row->normalized_payload['date'])->toBe('2026-03-15')
        ->and($row->errors)->toBe([]);
});

test('xlsx import sends unresolved account rows to manual review and allows correction', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    importUiCategory($user, 'Spese casa');

    $template = app(ImportTemplateXlsxBuilder::class)->build($user, 2026);
    $path = importUiXlsxWithAccountCell(
        $template['path'],
        "Conto famiglia ({$account->uuid})",
        'Conto storico non trovato'
    );

    $file = new UploadedFile(
        $path,
        'movimenti-conto-non-trovato.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect();

    $import = Import::query()->latest('id')->firstOrFail();
    $row = $import->rows()->firstOrFail();

    expect($row->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($row->normalized_payload['account_id'])->toBeNull()
        ->and($row->warnings)->toContain('Il conto della riga non corrisponde a un conto attivo e richiede controllo manuale.');

    $this->actingAs($user)
        ->patch(route('imports.rows.update-review', ['import' => $import->uuid, 'row' => $row->uuid]), [
            'account_id' => $account->id,
            'date' => '15/03/2026',
            'type' => 'Spesa',
            'amount' => '18,50',
            'detail' => 'Spesa alimentare',
            'category' => 'Spese casa',
            'reference' => 'RIF-001',
            'merchant' => 'Bar Centrale',
            'external_reference' => 'EXT-001',
        ])
        ->assertRedirect();

    $row->refresh();

    expect($row->status)->toBe(ImportRowStatusEnum::READY)
        ->and($row->normalized_payload['account_id'])->toBe($account->id)
        ->and($row->normalized_payload['account_uuid'])->toBe($account->uuid);
});

test('mediobanca xlsx upload processes real profile rows and detects reimport duplicates', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = importUiUser();
    $admin->assignRole('admin');
    $account = importUiAccount($admin);
    $category = importUiCategory($admin, 'Telefonia');
    $format = ImportFormat::ensureMediobancaXlsx();

    $rows = [
        1 => ['B' => 'Conto Mediobanca'],
        10 => ['B' => 'Periodo movimenti'],
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
            'D' => 'POS-ILIAD ITALIARoma',
            'E' => null,
            'F' => -7.99,
            'G' => 'EUR',
        ],
    ];

    $firstFile = new UploadedFile(
        importUiMediobancaXlsx($rows),
        'mediobanca-gen-mar-26.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($admin)
        ->post(route('imports.store'), [
            'import_format_uuid' => $format->uuid,
            'account_uuid' => $account->uuid,
            'file' => $firstFile,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Import letto correttamente: 1 righe elaborate, 0 pronte, 1 da verificare, 0 non valide, 0 duplicate.');

    $firstImport = Import::query()->latest('id')->firstOrFail();
    $firstRow = $firstImport->rows()->firstOrFail();

    expect($firstImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($firstImport->account_id)->toBe($account->id)
        ->and($firstImport->rows_count)->toBe(1)
        ->and($firstImport->review_rows_count)->toBe(1)
        ->and($firstImport->duplicate_rows_count)->toBe(0)
        ->and($firstImport->meta['parser'])->toBe('profile_xlsx')
        ->and($firstRow->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($firstRow->raw_date)->toBe('17/03/2026')
        ->and($firstRow->raw_value_date)->toBe('17/03/2026')
        ->and($firstRow->raw_amount)->toBe('7.99')
        ->and($firstRow->raw_description)->toBe('POS-ILIAD ITALIARoma')
        ->and($firstRow->normalized_payload['account_id'])->toBe($account->id)
        ->and($firstRow->normalized_payload['currency'])->toBe('EUR')
        ->and($firstRow->fingerprint)->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('imports.show', ['import' => $firstImport->uuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Show')
            ->where('importDetail.rows_count', 1)
            ->where('importDetail.review_rows_count', 1)
            ->where('rows.0.review_values.account_id', $account->id)
            ->where('rows.0.review_values.date', '17/03/2026')
            ->where('rows.0.review_values.value_date', '17/03/2026')
            ->where('rows.0.review_values.currency', 'EUR'));

    $this->actingAs($admin)
        ->patch(route('imports.rows.update-review', ['import' => $firstImport->uuid, 'row' => $firstRow->uuid]), [
            'account_id' => $account->id,
            'date' => '17/03/2026',
            'type' => 'Spesa',
            'amount' => '7.99',
            'detail' => 'POS-ILIAD ITALIARoma',
            'category_uuid' => $category->uuid,
            'category' => $category->name,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('imports.import-ready', ['import' => $firstImport->uuid]))
        ->assertRedirect()
        ->assertSessionHas('success', '1 riga pronta è stata importata correttamente.');

    $secondFile = new UploadedFile(
        importUiMediobancaXlsx($rows),
        'mediobanca-gen-mar-26.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($admin)
        ->post(route('imports.store'), [
            'import_format_uuid' => $format->uuid,
            'account_uuid' => $account->uuid,
            'file' => $secondFile,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Import letto correttamente: 1 righe elaborate, 0 pronte, 0 da verificare, 0 non valide, 1 duplicate.');

    $secondImport = Import::query()->latest('id')->firstOrFail();
    $secondRow = $secondImport->rows()->firstOrFail();

    expect($secondImport->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($secondImport->rows_count)->toBe(1)
        ->and($secondImport->duplicate_rows_count)->toBe(1)
        ->and($secondRow->status)->toBe(ImportRowStatusEnum::DUPLICATE_CANDIDATE)
        ->and($secondRow->fingerprint)->toBe($firstRow->fresh()->fingerprint)
        ->and($secondRow->warnings)->toContain('Questa riga corrisponde a un movimento ancora presente nel ledger e richiede una decisione manuale.');
});

test('mediobanca xlsx learns category suggestions from previously imported similar rows', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = importUiUser();
    $admin->assignRole('admin');
    $account = importUiAccount($admin);
    $category = importUiCategory($admin, 'Telefonia');
    $format = ImportFormat::ensureMediobancaXlsx();

    $firstRows = [
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
            'D' => 'POS-ILIAD ITALIARoma',
            'E' => null,
            'F' => -7.99,
            'G' => 'EUR',
        ],
    ];

    $this->actingAs($admin)
        ->post(route('imports.store'), [
            'import_format_uuid' => $format->uuid,
            'account_uuid' => $account->uuid,
            'file' => new UploadedFile(
                importUiMediobancaXlsx($firstRows),
                'mediobanca-primo.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
        ])
        ->assertRedirect();

    $firstImport = Import::query()->latest('id')->firstOrFail();
    $firstRow = $firstImport->rows()->firstOrFail();

    expect($firstRow->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($firstRow->normalized_payload['category'])->toBeNull()
        ->and($firstRow->normalized_payload['suggested_category'] ?? null)->toBeNull();

    $this->actingAs($admin)
        ->patch(route('imports.rows.update-review', ['import' => $firstImport->uuid, 'row' => $firstRow->uuid]), [
            'account_id' => $account->id,
            'date' => '17/03/2026',
            'value_date' => '17/03/2026',
            'type' => 'Spesa',
            'amount' => '7.99',
            'detail' => 'POS-ILIAD ITALIARoma',
            'category_uuid' => $category->uuid,
            'category' => $category->name,
            'currency' => 'EUR',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('imports.import-ready', ['import' => $firstImport->uuid]))
        ->assertRedirect()
        ->assertSessionHas('success', '1 riga pronta è stata importata correttamente.');

    $secondRows = [
        15 => [
            'B' => 'Data contabile',
            'C' => 'Data valuta',
            'D' => 'Tipologia',
            'E' => 'Entrate',
            'F' => 'Uscite',
            'G' => 'Divisa',
        ],
        16 => [
            'B' => '18/03/2026',
            'C' => '18/03/2026',
            'D' => 'POS-ILIAD ITALIARoma CARTA 9999',
            'E' => null,
            'F' => -8.99,
            'G' => 'EUR',
        ],
    ];

    $this->actingAs($admin)
        ->post(route('imports.store'), [
            'import_format_uuid' => $format->uuid,
            'account_uuid' => $account->uuid,
            'file' => new UploadedFile(
                importUiMediobancaXlsx($secondRows),
                'mediobanca-secondo.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Import letto correttamente: 1 righe elaborate, 0 pronte, 1 da verificare, 0 non valide, 0 duplicate.');

    $secondImport = Import::query()->latest('id')->firstOrFail();
    $secondRow = $secondImport->rows()->firstOrFail();

    expect($secondRow->status)->toBe(ImportRowStatusEnum::NEEDS_REVIEW)
        ->and($secondRow->normalized_payload['category'])->toBeNull()
        ->and($secondRow->normalized_payload['suggested_category'])->toMatchArray([
            'category_uuid' => $category->uuid,
            'category_label' => 'Telefonia',
            'source' => 'historical_transactions',
            'strategy' => 'historical_similarity',
            'same_account_matches' => 1,
        ]);

    $this->actingAs($admin)
        ->get(route('imports.show', ['import' => $secondImport->uuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Show')
            ->where('rows.0.category_label', null)
            ->where('rows.0.suggested_category.category_uuid', $category->uuid)
            ->where('rows.0.suggested_category.category_label', 'Telefonia')
            ->where('rows.0.suggested_category.source', 'historical_transactions')
            ->where('rows.0.suggested_category.same_account_matches', 1)
            ->where('rows.0.review_values.category_uuid', $category->uuid)
            ->where('rows.0.review_values.category', 'Telefonia'));
});

test('xlsx import fails only for structural workbook errors', function () {
    $user = importUiUser();
    $path = importUiStructurallyInvalidXlsx();
    $file = new UploadedFile(
        $path,
        'movimenti-strutturale.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'import' => 'Il file XLSX non contiene il foglio movimenti atteso.',
        ]);

    $import = Import::query()->latest('id')->firstOrFail();

    expect($import->status)->toBe(ImportStatusEnum::FAILED)
        ->and($import->rows_count)->toBe(0)
        ->and($import->error_message)->toBe('Il file XLSX non contiene il foglio movimenti atteso.');
});

test('imports store shows a clear validation error when file is missing', function () {
    $user = importUiUser();
    $account = importUiAccount($user);

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.store'), [
        ])
        ->assertRedirect(route('imports.index'))
        ->assertSessionHasErrors([
            'file',
        ]);
});

test('imports store requires an explicit format when more than one generic csv format is active', function () {
    $user = importUiUser();
    $account = importUiAccount($user);

    importUiFormat($account->bank);

    $this->actingAs($user)->get(route('imports.index'))->assertOk();

    $secondFormat = ImportFormat::query()->create([
        'bank_id' => $account->bank_id,
        'code' => 'generic_csv_v2',
        'name' => 'CSV generico v2',
        'version' => 'v2',
        'type' => ImportFormatTypeEnum::GENERIC_CSV,
        'status' => ImportFormatStatusEnum::ACTIVE,
        'is_generic' => true,
    ]);

    expect($secondFormat)->not->toBeNull();

    $file = UploadedFile::fake()->createWithContent(
        'import-marzo.csv',
        "Conto;Data;Tipo;Importo;Dettaglio\nConto famiglia;01/03/2026;Spesa;18,50;Spesa alimentare\n",
    );

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.store'), [
            'file' => $file,
        ])
        ->assertRedirect(route('imports.index'))
        ->assertSessionHasErrors([
            'import_format_uuid' => 'Seleziona un formato import.',
        ]);
});

test('imports store can use the auto-provisioned single generic format', function () {
    Storage::fake('local');

    $user = importUiUser();
    $account = importUiAccount($user);

    $file = UploadedFile::fake()->createWithContent(
        'import-senza-formato.csv',
        <<<'CSV'
Conto;Data;Tipo;Importo;Dettaglio;Categoria;Riferimento;Esercente;Riferimento esterno
Conto famiglia;01/03/2026;Spesa;18,50;Spesa alimentare;Spesa;;;EXT-AUTO-1
CSV
    );

    $response = $this->actingAs($user)->post(route('imports.store'), [
        'file' => $file,
    ]);

    $import = Import::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('imports.show', ['import' => $import->uuid]));

    $this->assertDatabaseHas('import_formats', [
        'id' => $import->import_format_id,
        'code' => 'generic_csv_v1',
    ]);
});

test('imports store rejects unavailable or inactive formats with a clear message', function () {
    Storage::fake('local');

    $user = importUiUser();
    $account = importUiAccount($user);
    $format = ImportFormat::query()->create([
        'bank_id' => $account->bank_id,
        'code' => 'generic-csv-disabled',
        'name' => 'CSV non attivo',
        'version' => 'v1',
        'type' => ImportFormatTypeEnum::GENERIC_CSV,
        'status' => ImportFormatStatusEnum::DISABLED,
        'is_generic' => true,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'import-marzo.csv',
        "Conto;Data;Tipo;Importo;Dettaglio\nConto famiglia;01/03/2026;Spesa;18,50;Spesa alimentare\n",
    );

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.store'), [
            'import_format_uuid' => $format->uuid,
            'file' => $file,
        ])
        ->assertRedirect(route('imports.index'))
        ->assertSessionHasErrors([
            'import_format_uuid' => 'Il formato selezionato non è attivo.',
        ]);
});

test('imports store uploads a file and redirects to detail page', function () {
    Storage::fake('local');

    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    importUiCategory($user, 'Spesa');

    $file = UploadedFile::fake()->createWithContent(
        'import-marzo.csv',
        <<<'CSV'
Conto;Data;Tipo;Importo;Dettaglio;Categoria;Riferimento;Esercente;Riferimento esterno
Conto famiglia;01/03/2026;Spesa;18,50;Spesa alimentare;Spesa;;;EXT-UI-1
CSV
    );

    $response = $this->actingAs($user)->post(route('imports.store'), [
        'import_format_uuid' => $format->uuid,
        'file' => $file,
    ]);

    $import = Import::query()->latest('id')->firstOrFail();

    $response
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', 'Import letto correttamente: 1 righe elaborate, 1 pronte, 0 da verificare, 0 non valide, 0 duplicate.');

    expect($import->account_id)->toBeNull()
        ->and($import->import_format_id)->toBe($format->id)
        ->and($import->rows_count)->toBe(1)
        ->and($import->ready_rows_count)->toBe(1);

    Storage::disk('local')->assertExists($import->stored_filename);
    $this->assertDatabaseHas('import_rows', [
        'import_id' => $import->id,
        'row_index' => 1,
        'status' => ImportRowStatusEnum::READY->value,
    ]);
});

test('imports store processes blocked year review and already imported rows end to end', function () {
    Storage::fake('local');

    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    importUiCategory($user, 'Casa');

    $previousImport = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'storico.csv',
        'stored_filename' => 'imports/storico.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic-csv-ui',
        'status' => ImportStatusEnum::COMPLETED,
        'meta' => ['management_year' => 2026],
    ]);

    $alreadyImportedPayload = [
        'date' => '2026-03-25',
        'type' => 'expense',
        'amount' => '22.00',
        'detail' => 'Pagamento ricorrente',
        'category' => 'Casa',
        'reference' => null,
        'merchant' => null,
        'external_reference' => 'EXT-DUP-1',
        'balance' => '800.00',
    ];

    ImportRow::query()->create([
        'import_id' => $previousImport->id,
        'row_index' => 1,
        'raw_date' => '25/03/2026',
        'raw_description' => 'Pagamento ricorrente',
        'raw_amount' => '22,00',
        'raw_balance' => '800,00',
        'raw_payload' => [],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::IMPORTED,
        'fingerprint' => ImportFingerprintGenerator::make($alreadyImportedPayload, $user->id, $account->id),
        'normalized_payload' => $alreadyImportedPayload,
        'errors' => [],
        'warnings' => [],
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'import-completo.csv',
        <<<'CSV'
Conto;Data;Tipo;Importo;Dettaglio;Categoria;Riferimento;Esercente;Riferimento esterno
Conto famiglia;12/03/2026;Spesa;15,00;Spesa valida;Casa;;;EXT-VALID-1
Conto famiglia;18/03/2026;Giroconto;50,00;Giroconto interno;Trasferimenti;;;EXT-REVIEW-1
Conto famiglia;04/03/2025;Spesa;10,00;Riga fuori anno;Casa;;;EXT-YEAR-1
Conto famiglia;25/03/2026;Spesa;22,00;Pagamento ricorrente;Casa;;;EXT-DUP-1
CSV
    );

    $response = $this->actingAs($user)->post(route('imports.store'), [
        'import_format_uuid' => $format->uuid,
        'file' => $file,
    ]);

    $import = Import::query()->latest('id')->firstOrFail();

    $response
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', 'Import letto correttamente: 4 righe elaborate, 1 pronte, 1 da verificare, 1 non valide, 1 duplicate.');

    expect($import->status)->toBe(ImportStatusEnum::REVIEW_REQUIRED)
        ->and($import->rows_count)->toBe(4)
        ->and($import->ready_rows_count)->toBe(1)
        ->and($import->review_rows_count)->toBe(1)
        ->and($import->invalid_rows_count)->toBe(1)
        ->and($import->duplicate_rows_count)->toBe(1)
        ->and($import->meta['management_year'])->toBe(2026);

    $this->assertDatabaseHas('import_rows', [
        'import_id' => $import->id,
        'row_index' => 2,
        'status' => ImportRowStatusEnum::NEEDS_REVIEW->value,
    ]);
    $this->assertDatabaseHas('import_rows', [
        'import_id' => $import->id,
        'row_index' => 3,
        'status' => ImportRowStatusEnum::BLOCKED_YEAR->value,
    ]);
    $this->assertDatabaseHas('import_rows', [
        'import_id' => $import->id,
        'row_index' => 4,
        'status' => ImportRowStatusEnum::DUPLICATE_CANDIDATE->value,
    ]);
});

test('imports keep existing imported transactions untouched when a new file matches them as duplicates', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Casa');

    $previousImport = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'precedente.csv',
        'stored_filename' => 'imports/precedente.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::COMPLETED,
        'rows_count' => 1,
        'imported_rows_count' => 1,
        'meta' => ['management_year' => 2026],
    ]);

    $matchingPayload = [
        'date' => '2026-03-12',
        'type' => 'expense',
        'amount' => '15.00',
        'detail' => 'Spesa valida bloccata',
        'external_reference' => 'EXT-VALID-LOCK',
    ];

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'import_id' => $previousImport->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-03-12',
        'value_date' => '2026-03-12',
        'direction' => 'expense',
        'kind' => 'manual',
        'amount' => '15.00',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'exchange_rate' => '1.00000000',
        'exchange_rate_date' => '2026-03-12',
        'converted_base_amount' => '15.00',
        'exchange_rate_source' => 'identity',
        'description' => 'Spesa valida bloccata',
        'source_type' => TransactionSourceTypeEnum::IMPORT,
        'status' => 'confirmed',
        'external_hash' => ImportFingerprintGenerator::make($matchingPayload, $user->id, $account->id),
    ]);

    /** @var array<string, mixed> $previousImportedPayload */
    $previousImportedPayload = $matchingPayload + [
        'account' => $account->name,
        'account_id' => $account->id,
        'account_uuid' => $account->uuid,
        'category' => $category->name,
    ];

    ImportRow::query()->create([
        'import_id' => $previousImport->id,
        'row_index' => 1,
        'raw_date' => '12/03/2026',
        'raw_description' => 'Spesa valida bloccata',
        'raw_amount' => '15,00',
        'raw_payload' => [],
        'normalized_payload' => $previousImportedPayload,
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::IMPORTED,
        'fingerprint' => $transaction->external_hash,
        'errors' => [],
        'warnings' => [],
        'transaction_id' => $transaction->id,
        'imported_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('imports.store'), [
        'import_format_uuid' => $format->uuid,
        'file' => UploadedFile::fake()->createWithContent(
            'duplicate-existing.csv',
            <<<'CSV'
Conto;Data;Tipo;Importo;Dettaglio;Categoria;Riferimento esterno
Conto famiglia;12/03/2026;Spesa;15,00;Spesa valida bloccata;Casa;EXT-VALID-LOCK
CSV
        ),
    ]);

    $import = Import::query()->latest('id')->firstOrFail();

    $response
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', 'Import letto correttamente: 1 righe elaborate, 0 pronte, 0 da verificare, 0 non valide, 1 duplicate.');

    $this->assertDatabaseHas('import_rows', [
        'import_id' => $import->id,
        'row_index' => 1,
        'status' => ImportRowStatusEnum::DUPLICATE_CANDIDATE->value,
    ]);

    expect(Transaction::query()->count())->toBe(1)
        ->and($transaction->fresh()?->description)->toBe('Spesa valida bloccata')
        ->and($transaction->fresh()?->amount)->toBe('15.00')
        ->and($transaction->fresh()?->import_id)->toBe($previousImport->id)
        ->and($import->fresh()->ready_rows_count)->toBe(0)
        ->and($import->fresh()->imported_rows_count)->toBe(0);
});

test('imports allow reimport after a previously imported transaction was deleted from the ledger', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Casa');

    $previousImport = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'precedente-eliminato.csv',
        'stored_filename' => 'imports/precedente-eliminato.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::COMPLETED,
        'rows_count' => 1,
        'imported_rows_count' => 1,
        'meta' => ['management_year' => 2026],
    ]);

    $matchingPayload = [
        'date' => '2026-03-20',
        'type' => 'expense',
        'amount' => '19.90',
        'detail' => 'Spesa reimportabile',
        'external_reference' => 'EXT-REIMPORT-1',
    ];

    $deletedTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'import_id' => $previousImport->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-03-20',
        'value_date' => '2026-03-20',
        'direction' => 'expense',
        'kind' => 'manual',
        'amount' => '19.90',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'exchange_rate' => '1.00000000',
        'exchange_rate_date' => '2026-03-20',
        'converted_base_amount' => '19.90',
        'exchange_rate_source' => 'identity',
        'description' => 'Spesa reimportabile',
        'source_type' => TransactionSourceTypeEnum::IMPORT,
        'status' => 'confirmed',
        'external_hash' => ImportFingerprintGenerator::make($matchingPayload, $user->id, $account->id),
    ]);
    $deletedTransaction->delete();

    /** @var array<string, mixed> $historicalImportedPayload */
    $historicalImportedPayload = $matchingPayload + [
        'account' => $account->name,
        'account_id' => $account->id,
        'account_uuid' => $account->uuid,
        'category' => $category->name,
    ];

    ImportRow::query()->create([
        'import_id' => $previousImport->id,
        'row_index' => 1,
        'raw_date' => '20/03/2026',
        'raw_description' => 'Spesa reimportabile',
        'raw_amount' => '19,90',
        'raw_payload' => [],
        'normalized_payload' => $historicalImportedPayload,
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::IMPORTED,
        'fingerprint' => $deletedTransaction->external_hash,
        'errors' => [],
        'warnings' => [],
        'transaction_id' => $deletedTransaction->id,
        'imported_at' => now(),
    ]);

    $storeResponse = $this->actingAs($user)->post(route('imports.store'), [
        'import_format_uuid' => $format->uuid,
        'file' => UploadedFile::fake()->createWithContent(
            'reimport-after-delete.csv',
            <<<'CSV'
Conto;Data;Tipo;Importo;Dettaglio;Categoria;Riferimento esterno
Conto famiglia;20/03/2026;Spesa;19,90;Spesa reimportabile;Casa;EXT-REIMPORT-1
CSV
        ),
    ]);

    $import = Import::query()->latest('id')->firstOrFail();
    $row = $import->rows()->firstOrFail();

    $storeResponse
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', 'Import letto correttamente: 1 righe elaborate, 0 pronte, 0 da verificare, 0 non valide, 1 duplicate.');

    expect($row->status)->toBe(ImportRowStatusEnum::DUPLICATE_CANDIDATE)
        ->and($row->warnings)->toContain('Questa riga risulta già importata nello storico, ma il movimento non è più presente nel ledger: verifica se vuoi reimportarla.');

    $this->actingAs($user)
        ->post(route('imports.rows.approve-duplicate', [
            'import' => $import->uuid,
            'row' => $row->uuid,
        ]))
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]));

    $this->actingAs($user)
        ->post(route('imports.import-ready', ['import' => $import->uuid]))
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', '1 riga pronta è stata importata correttamente.');

    expect(Transaction::query()->count())->toBe(1)
        ->and(Transaction::withTrashed()->count())->toBe(2)
        ->and($import->fresh()->status)->toBe(ImportStatusEnum::COMPLETED)
        ->and($deletedTransaction->fresh()?->trashed())->toBeTrue();
});

test('imports can promote ready rows into transactions', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Casa');
    $trackedItem = importUiTrackedItem($user, 'Spesa casa', [
        'transaction_group_keys' => ['expense'],
        'transaction_category_uuids' => [$category->uuid],
    ]);

    $import = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'pronte.csv',
        'stored_filename' => 'imports/pronte.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::PARSED,
        'rows_count' => 1,
        'ready_rows_count' => 1,
        'meta' => ['management_year' => 2026],
    ]);

    $row = ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 1,
        'raw_date' => '12/03/2026',
        'raw_description' => 'Spesa valida',
        'raw_amount' => '18,50',
        'raw_balance' => '900,00',
        'raw_payload' => [
            'date' => '12/03/2026',
            'type' => 'Spesa',
            'amount' => '18,50',
            'detail' => 'Spesa valida',
            'category' => 'Casa',
        ],
        'normalized_payload' => [
            'date' => '2026-03-12',
            'type' => 'expense',
            'amount' => '18.50',
            'detail' => 'Spesa valida',
            'category' => $category->name,
            'category_uuid' => $category->uuid,
            'reference' => 'RIF-IMPORT-1',
            'tracked_item_uuid' => $trackedItem->uuid,
            'merchant' => null,
            'external_reference' => 'EXT-IMPORT-1',
            'balance' => '900.00',
        ],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::READY,
        'fingerprint' => 'fingerprint-ready-row',
        'errors' => [],
        'warnings' => [],
    ]);

    $this->actingAs($user)
        ->post(route('imports.import-ready', ['import' => $import->uuid]))
        ->assertStatus(303)
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', '1 riga pronta è stata importata correttamente.');

    $transaction = Transaction::query()->where('import_id', $import->id)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction?->import_row_id)->toBe($row->id)
        ->and($transaction?->source_type)->toBe(TransactionSourceTypeEnum::IMPORT)
        ->and($transaction?->category_id)->toBe($category->id)
        ->and($transaction?->tracked_item_id)->toBe($trackedItem->id);

    $this->assertDatabaseHas('import_rows', [
        'id' => $row->id,
        'status' => ImportRowStatusEnum::IMPORTED->value,
    ]);

    $import->refresh();

    expect($import->status)->toBe(ImportStatusEnum::COMPLETED)
        ->and($import->ready_rows_count)->toBe(0)
        ->and($import->imported_rows_count)->toBe(1);
});

test('imports import-ready keeps the user on import detail when transaction creation fails', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $account->update([
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Casa');

    $import = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'saldo-bloccato.csv',
        'stored_filename' => 'imports/saldo-bloccato.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::PARSED,
        'rows_count' => 1,
        'ready_rows_count' => 1,
        'meta' => ['management_year' => 2026],
    ]);

    ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 1,
        'raw_date' => '12/03/2026',
        'raw_description' => 'Spesa che supera il saldo',
        'raw_amount' => '18,50',
        'raw_balance' => '-18,50',
        'raw_payload' => [],
        'normalized_payload' => [
            'date' => '2026-03-12',
            'type' => 'expense',
            'amount' => '18.50',
            'detail' => 'Spesa che supera il saldo',
            'category' => $category->name,
            'reference' => null,
            'merchant' => null,
            'external_reference' => null,
            'balance' => '-18.50',
        ],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::READY,
        'fingerprint' => 'fingerprint-import-ready-balance-fail',
        'errors' => [],
        'warnings' => [],
    ]);

    $this->actingAs($user)
        ->post(route('imports.import-ready', ['import' => $import->uuid]))
        ->assertStatus(303)
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHasErrors('import');
});

test('imports import-ready completes successfully when import completed topic is inactive', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Casa');

    NotificationTopic::query()
        ->where('key', 'import_completed')
        ->update(['is_active' => false]);

    $import = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'topic-inactive.xlsx',
        'stored_filename' => 'imports/topic-inactive.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'source_type' => ImportSourceTypeEnum::XLSX,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::PARSED,
        'rows_count' => 1,
        'ready_rows_count' => 1,
        'meta' => ['management_year' => 2026],
    ]);

    $row = ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 1,
        'raw_date' => '12/03/2026',
        'raw_description' => 'Spesa valida topic inattivo',
        'raw_amount' => '18,50',
        'raw_balance' => '900,00',
        'raw_payload' => [
            'date' => '12/03/2026',
            'type' => 'Spesa',
            'amount' => '18,50',
            'detail' => 'Spesa valida topic inattivo',
            'category' => 'Casa',
        ],
        'normalized_payload' => [
            'date' => '2026-03-12',
            'type' => 'expense',
            'amount' => '18.50',
            'detail' => 'Spesa valida topic inattivo',
            'category' => $category->name,
            'reference' => 'RIF-IMPORT-TOPIC',
            'merchant' => null,
            'external_reference' => 'EXT-IMPORT-TOPIC',
            'balance' => '900.00',
        ],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::READY,
        'fingerprint' => 'fingerprint-ready-row-topic-inactive',
        'errors' => [],
        'warnings' => [],
    ]);

    $this->actingAs($user)
        ->post(route('imports.import-ready', ['import' => $import->uuid]))
        ->assertStatus(303)
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', '1 riga pronta è stata importata correttamente.');

    $transaction = Transaction::query()->where('import_id', $import->id)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction?->import_row_id)->toBe($row->id)
        ->and($transaction?->source_type)->toBe(TransactionSourceTypeEnum::IMPORT)
        ->and($transaction?->category_id)->toBe($category->id);

    $this->assertDatabaseHas('import_rows', [
        'id' => $row->id,
        'status' => ImportRowStatusEnum::IMPORTED->value,
    ]);

    $import->refresh();

    expect($import->status)->toBe(ImportStatusEnum::COMPLETED)
        ->and($import->ready_rows_count)->toBe(0)
        ->and($import->imported_rows_count)->toBe(1);
});

test('imports can be rolled back after transactions were created', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Casa');

    $import = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'rollback.csv',
        'stored_filename' => 'imports/rollback.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::COMPLETED,
        'rows_count' => 1,
        'imported_rows_count' => 1,
        'meta' => ['management_year' => 2026],
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'import_id' => $import->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-03-12',
        'value_date' => '2026-03-12',
        'direction' => 'expense',
        'amount' => 18.50,
        'currency' => 'EUR',
        'description' => 'Spesa valida',
        'source_type' => TransactionSourceTypeEnum::IMPORT,
        'status' => 'confirmed',
    ]);

    $row = ImportRow::query()->create([
        'import_id' => $import->id,
        'transaction_id' => $transaction->id,
        'row_index' => 1,
        'raw_date' => '12/03/2026',
        'raw_description' => 'Spesa valida',
        'raw_amount' => '18,50',
        'raw_balance' => '900,00',
        'raw_payload' => [],
        'normalized_payload' => [
            'date' => '2026-03-12',
            'type' => 'expense',
            'amount' => '18.50',
            'detail' => 'Spesa valida',
            'category' => $category->name,
        ],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::IMPORTED,
        'fingerprint' => 'fingerprint-imported-row',
        'errors' => [],
        'warnings' => [],
        'imported_at' => now(),
    ]);

    $transaction->forceFill([
        'import_row_id' => $row->id,
    ])->save();

    $this->actingAs($user)
        ->post(route('imports.rollback', ['import' => $import->uuid]))
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', 'Import annullato correttamente.');

    $this->assertDatabaseMissing('transactions', [
        'id' => $transaction->id,
    ]);

    $this->assertDatabaseHas('import_rows', [
        'id' => $row->id,
        'status' => ImportRowStatusEnum::ROLLED_BACK->value,
        'transaction_id' => null,
    ]);

    $import->refresh();

    expect($import->status)->toBe(ImportStatusEnum::ROLLED_BACK)
        ->and($import->imported_rows_count)->toBe(0)
        ->and($import->rolled_back_at)->not->toBeNull();
});

test('imports can be archived filtered and restored without touching transactions', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Casa');

    $import = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'archiviabile.csv',
        'stored_filename' => 'imports/archiviabile.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::COMPLETED,
        'rows_count' => 1,
        'imported_rows_count' => 1,
        'meta' => ['management_year' => 2026],
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'import_id' => $import->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-03-12',
        'value_date' => '2026-03-12',
        'direction' => 'expense',
        'kind' => 'manual',
        'amount' => 18.50,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'exchange_rate' => '1.00000000',
        'exchange_rate_date' => '2026-03-12',
        'converted_base_amount' => '18.50',
        'exchange_rate_source' => 'identity',
        'description' => 'Spesa importata',
        'source_type' => TransactionSourceTypeEnum::IMPORT,
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)
        ->post(route('imports.archive', ['import' => $import->uuid]))
        ->assertRedirect(route('imports.index'))
        ->assertSessionHas('success', 'Import archiviato correttamente.');

    expect(Import::query()->find($import->id))->toBeNull()
        ->and(Import::withTrashed()->find($import->id)?->trashed())->toBeTrue()
        ->and(Transaction::query()->find($transaction->id))->not->toBeNull();

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->where('filters.current_archive', 'active')
            ->where('imports.summary.total_count', 0)
            ->has('imports.data', 0));

    $this->actingAs($user)
        ->get(route('imports.index', ['archive' => 'archived']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->where('filters.current_archive', 'archived')
            ->where('imports.summary.total_count', 1)
            ->where('imports.data.0.uuid', $import->uuid)
            ->where('imports.data.0.is_archived', true)
            ->where('imports.data.0.can_restore', true)
            ->where('imports.data.0.can_archive', false));

    $this->actingAs($user)
        ->get(route('imports.index', ['archive' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/Index')
            ->where('filters.current_archive', 'all')
            ->where('imports.summary.total_count', 1)
            ->where('imports.data.0.uuid', $import->uuid));

    $this->actingAs($user)
        ->post(route('imports.restore', ['import' => $import->uuid]))
        ->assertRedirect(route('imports.index'))
        ->assertSessionHas('success', 'Import ripristinato correttamente.');

    expect(Import::query()->find($import->id)?->trashed())->toBeFalse()
        ->and(Transaction::query()->find($transaction->id))->not->toBeNull();
});

test('rolled back imports can be deleted safely', function () {
    Storage::fake('local');

    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);

    Storage::disk('local')->put('imports/to-delete.csv', 'demo');

    $import = Import::query()->create([
        'user_id' => $user->id,
        'bank_id' => $account->bank_id,
        'account_id' => $account->id,
        'import_format_id' => $format->id,
        'original_filename' => 'to-delete.csv',
        'stored_filename' => 'imports/to-delete.csv',
        'mime_type' => 'text/csv',
        'source_type' => ImportSourceTypeEnum::CSV,
        'parser_key' => 'generic_csv_v1',
        'status' => ImportStatusEnum::ROLLED_BACK,
        'meta' => ['management_year' => 2026],
    ]);

    ImportRow::query()->create([
        'import_id' => $import->id,
        'row_index' => 1,
        'raw_date' => '12/03/2026',
        'raw_description' => 'Spesa valida',
        'raw_amount' => '18,50',
        'raw_balance' => '900,00',
        'raw_payload' => [],
        'normalized_payload' => [],
        'parse_status' => ImportRowParseStatusEnum::PARSED,
        'status' => ImportRowStatusEnum::ROLLED_BACK,
        'errors' => [],
        'warnings' => [],
    ]);

    $this->actingAs($user)
        ->delete(route('imports.destroy', ['import' => $import->uuid]))
        ->assertRedirect(route('imports.index'))
        ->assertSessionHas('success', 'Import eliminato correttamente.');

    $this->assertDatabaseMissing('imports', ['id' => $import->id]);
    $this->assertDatabaseMissing('import_rows', ['import_id' => $import->id]);
    Storage::disk('local')->assertMissing('imports/to-delete.csv');
});

test('active imports cannot be deleted', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $import = importUiRecord($user, $account, $format);

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->delete(route('imports.destroy', ['import' => $import->uuid]))
        ->assertRedirect(route('imports.index'))
        ->assertSessionHasErrors([
            'import' => 'Puoi eliminare solo import già annullati.',
        ]);
});
