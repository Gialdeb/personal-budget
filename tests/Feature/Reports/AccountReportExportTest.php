<?php

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('accounts report export generates a pdf for a single filtered account', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $mainAccount = createTestAccount($user, [
        'name' => 'Conto operativo',
        'opening_balance' => 1000,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);
    $otherAccount = createTestAccount($user, [
        'name' => 'Conto riserva',
        'opening_balance' => 400,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    createReportTransaction($user, $mainAccount, [
        'transaction_date' => '2026-02-03',
        'value_date' => '2026-02-03',
        'description' => 'Incasso cliente Alpha',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 500,
        'balance_after' => 1500,
        'notes' => 'Fattura A-100',
    ]);
    createReportTransaction($user, $mainAccount, [
        'transaction_date' => '2026-02-12',
        'value_date' => '2026-02-12',
        'description' => 'Canone software',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 120,
        'balance_after' => 1380,
        'reference_code' => 'SOFT-42',
    ]);
    createReportTransaction($user, $mainAccount, [
        'transaction_date' => '2026-03-01',
        'value_date' => '2026-03-01',
        'description' => 'Fuori periodo',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 999,
    ]);
    createReportTransaction($user, $otherAccount, [
        'transaction_date' => '2026-02-15',
        'value_date' => '2026-02-15',
        'description' => 'Movimento altro conto',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 700,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $response = $this->actingAs($user)->get(route('reports.accounts.export', [
        'year' => 2026,
        'period' => 'monthly',
        'month' => 2,
        'account_uuid' => $mainAccount->uuid,
    ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))
        ->toMatch('/estratto-conto-conto-operativo-20260201-20260228-\d{8}-\d{6}\.pdf/');

    $pdf = $response->getContent();

    expect($pdf)
        ->toStartWith('%PDF-1.4')
        ->toContain('/Type /Catalog')
        ->toContain('/Type /Pages')
        ->toContain('/Type /Page')
        ->toContain('Soamco Budget')
        ->toContain('Report prodotto da Soamco Budget')
        ->not->toContain('(S) Tj')
        ->toContain('Estratto conto')
        ->toContain('Riepilogo')
        ->not->toContain('Executive summary')
        ->toContain('Movimenti del conto')
        ->toContain('Entrate')
        ->toContain('Uscite')
        ->toContain('Descrizione')
        ->toContain('Conto operativo')
        ->toContain('febbraio 2026')
        ->toContain('Incasso cliente Alpha')
        ->toContain('Fattura A-100')
        ->toContain('Canone software')
        ->toContain('SOFT-42')
        ->toContain('EUR -120,00')
        ->toContain('EUR 1.380,00')
        ->not->toContain('EUR1.380,00')
        ->not->toContain('-EUR 120,00')
        ->not->toContain('Movimento altro conto')
        ->not->toContain('Fuori periodo');
});

test('accounts report export is fully localized in english with locale-aware money formatting', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
        'locale' => 'en',
        'format_locale' => 'en-US',
    ]);
    $user->assignRole('user');

    $account = createTestAccount($user, [
        'name' => 'Operating account',
        'opening_balance' => 25130,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    createReportTransaction($user, $account, [
        'transaction_date' => '2026-02-03',
        'value_date' => '2026-02-03',
        'description' => 'Client payment',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 500,
        'balance_after' => 25630,
        'notes' => 'Invoice A-100',
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $response = $this->actingAs($user)->get(route('reports.accounts.export', [
        'year' => 2026,
        'period' => 'monthly',
        'month' => 2,
        'account_uuid' => $account->uuid,
    ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))
        ->toMatch('/account-statement-operating-account-20260201-20260228-\d{8}-\d{6}\.pdf/')
        ->not->toContain('estratto-conto');

    $pdf = $response->getContent();

    expect($pdf)
        ->toContain('Account statement')
        ->toContain('Report produced by Soamco Budget')
        ->toContain('Generated on')
        ->toContain('Executive summary')
        ->toContain('Scope and balances for the selected period')
        ->toContain('Account movements')
        ->toContain('Opening balance')
        ->toContain('Closing balance')
        ->toContain('Income')
        ->toContain('Expense')
        ->toContain('Description')
        ->toContain('Amount')
        ->toContain('Closing balance')
        ->toContain('EUR 25,130.00')
        ->toContain('EUR 25,630.00')
        ->not->toContain('Estratto conto')
        ->not->toContain('Riepilogo')
        ->not->toContain('Generato il')
        ->not->toContain('EUR25,130.00');
});

test('accounts report export groups all account movements and applies the active filters', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $cashAccount = createTestAccount($user, [
        'name' => 'Cassa principale',
        'opening_balance' => 100,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);
    $bankAccount = createTestAccount($user, [
        'name' => 'Banca aziendale',
        'opening_balance' => 200,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    createReportTransaction($user, $cashAccount, [
        'transaction_date' => '2026-01-08',
        'value_date' => '2026-01-08',
        'description' => 'Vendita banco',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 300,
    ]);
    createReportTransaction($user, $bankAccount, [
        'transaction_date' => '2026-02-10',
        'value_date' => '2026-02-10',
        'description' => 'Pagamento fornitore',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 80,
    ]);
    createReportTransaction($user, $bankAccount, [
        'transaction_date' => '2026-04-01',
        'value_date' => '2026-04-01',
        'description' => 'Fuori trimestre',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 1000,
    ]);
    createReportTransaction($user, $bankAccount, [
        'transaction_date' => '2026-02-12',
        'value_date' => '2026-02-12',
        'description' => 'Giroconto escluso',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 50,
        'is_transfer' => true,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026, 'active_month' => 3],
    );

    $response = $this->actingAs($user)->get(route('reports.accounts.export', [
        'year' => 2026,
        'period' => 'last_3_months',
        'month' => 3,
    ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))
        ->toMatch('/estratto-conto-tutti-i-conti-20260101-20260331-\d{8}-\d{6}\.pdf/');

    $pdf = $response->getContent();

    expect($pdf)
        ->toContain('Tutti i conti')
        ->toContain('Dettaglio per conto')
        ->toContain('Ultimi 3 mesi fino a marzo 2026')
        ->toContain('Cassa principale')
        ->toContain('Banca aziendale')
        ->toContain('Vendita banco')
        ->toContain('Pagamento fornitore')
        ->toContain('220,00')
        ->not->toContain('Fuori trimestre')
        ->not->toContain('Giroconto escluso');
});

function createReportTransaction(User $user, $account, array $attributes = []): Transaction
{
    return Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 100,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'converted_base_amount' => $attributes['amount'] ?? 100,
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Movimento test',
        'is_transfer' => false,
        ...$attributes,
    ]);
}
