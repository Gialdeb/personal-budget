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
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use App\Supports\Imports\ImportFingerprintGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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
        'name' => 'Banca Operativa',
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
        'currency' => 'EUR',
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
        'name' => 'CSV gestionale v1',
        'version' => 'v1',
        'type' => ImportFormatTypeEnum::GENERIC_CSV,
        'status' => ImportFormatStatusEnum::ACTIVE,
        'is_generic' => true,
        'notes' => 'Formato CSV con intestazioni italiane.',
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
            ->where('imports.data.0.parser_label', 'Import CSV')
            ->where('imports.data.0.review_rows_count', 1)
            ->where('imports.data.0.management_year', 2026)
            ->where('imports.pagination.current_page', 1)
            ->where('imports.pagination.last_page', 1)
            ->where('imports.pagination.has_pages', false)
            ->where('options.accounts.0.name', 'Conto famiglia')
            ->where('options.formats.0.name', 'CSV generico v1')
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
            ->where('rows.0.review_values.date', '04/03/2025')
            ->where('rows.0.review_values.type', 'Spesa')
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
            ->where('categories.0.value', $category->name)
            ->where('categories.0.label', $category->name)
            ->where('rows.0.review_values.category', 'Categoria mancante CSV')
        );
});

test('imports detail exposes a single persisted feedback message for already imported and skipped rows', function () {
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
        'status' => ImportRowStatusEnum::ALREADY_IMPORTED,
        'errors' => [],
        'warnings' => ['Questa riga risulta già importata in precedenza.'],
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
            ->where('rows.0.status', ImportRowStatusEnum::ALREADY_IMPORTED->value)
            ->where('rows.0.errors', [])
            ->where('rows.0.warnings', ['Questa riga risulta già importata in precedenza.'])
            ->where('rows.1.status', ImportRowStatusEnum::SKIPPED->value)
            ->where('rows.1.errors', [])
            ->where('rows.1.warnings', ['Riga saltata manualmente dall’utente.'])
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

test('imports template uses active year and realistic database values when available', function () {
    $user = importUiUser();
    $category = importUiCategory($user, 'Spese casa');
    $merchant = importUiMerchant($user, $category, 'Mercato Rionale');

    $response = $this->actingAs($user)->get(route('imports.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->getContent();

    expect($content)->toContain('15/03/2026')
        ->toContain('Spese casa')
        ->toContain('Mercato Rionale')
        ->toContain('Rimborso o accredito');
});

test('imports store shows a clear validation error when file is missing', function () {
    $user = importUiUser();
    $account = importUiAccount($user);

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.store'), [
            'account_uuid' => $account->uuid,
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
        "Data;Tipo;Importo;Dettaglio\n01/03/2026;Spesa;18,50;Spesa alimentare\n",
    );

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.store'), [
            'account_uuid' => $account->uuid,
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
Data;Tipo;Importo;Dettaglio;Categoria;Riferimento;Esercente;Riferimento esterno;Saldo
01/03/2026;Spesa;18,50;Spesa alimentare;Spesa;;;EXT-AUTO-1;900,00
CSV
    );

    $response = $this->actingAs($user)->post(route('imports.store'), [
        'account_uuid' => $account->uuid,
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
        "Data;Tipo;Importo;Dettaglio\n01/03/2026;Spesa;18,50;Spesa alimentare\n",
    );

    $this->actingAs($user)
        ->from(route('imports.index'))
        ->post(route('imports.store'), [
            'account_uuid' => $account->uuid,
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
Data;Tipo;Importo;Dettaglio;Categoria;Riferimento;Esercente;Riferimento esterno;Saldo
01/03/2026;Spesa;18,50;Spesa alimentare;Spesa;;;EXT-UI-1;900,00
CSV
    );

    $response = $this->actingAs($user)->post(route('imports.store'), [
        'account_uuid' => $account->uuid,
        'import_format_uuid' => $format->uuid,
        'file' => $file,
    ]);

    $import = Import::query()->latest('id')->firstOrFail();

    $response
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', 'Importazione caricata correttamente.');

    expect($import->account_id)->toBe($account->id)
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
Data;Tipo;Importo;Dettaglio;Categoria;Riferimento;Esercente;Riferimento esterno;Saldo
12/03/2026;Spesa;15,00;Spesa valida;Casa;;;EXT-VALID-1;950,00
18/03/2026;Giroconto;50,00;Giroconto interno;Trasferimenti;;;EXT-REVIEW-1;900,00
04/03/2025;Spesa;10,00;Riga fuori anno;Casa;;;EXT-YEAR-1;890,00
25/03/2026;Spesa;22,00;Pagamento ricorrente;Casa;;;EXT-DUP-1;800,00
CSV
    );

    $response = $this->actingAs($user)->post(route('imports.store'), [
        'account_uuid' => $account->uuid,
        'import_format_uuid' => $format->uuid,
        'file' => $file,
    ]);

    $import = Import::query()->latest('id')->firstOrFail();

    $response
        ->assertRedirect(route('imports.show', ['import' => $import->uuid]))
        ->assertSessionHas('success', 'Importazione caricata correttamente.');

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
        'status' => ImportRowStatusEnum::ALREADY_IMPORTED->value,
    ]);
});

test('imports can promote ready rows into transactions', function () {
    $user = importUiUser();
    $account = importUiAccount($user);
    $format = importUiFormat($account->bank);
    $category = importUiCategory($user, 'Casa');

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
            'reference' => 'RIF-IMPORT-1',
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
