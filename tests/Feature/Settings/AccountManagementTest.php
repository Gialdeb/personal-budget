<?php

use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\AccountOpeningBalance;
use App\Models\AccountReconciliation;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Import;
use App\Models\RecurringEntry;
use App\Models\ScheduledEntry;
use App\Models\Scope;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function verifiedAccountUser(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
    ]);
}

function accountCsrfToken(): string
{
    return 'account-test-token';
}

function makeAccountType(string $code, string $name, string $balanceNature): AccountType
{
    return AccountType::query()->create([
        'code' => $code,
        'name' => $name,
        'balance_nature' => $balanceNature,
    ]);
}

function makeAccountForUser(User $user, AccountType $accountType, array $attributes = []): Account
{
    return Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Account '.fake()->unique()->word(),
        'currency' => 'EUR',
        'is_manual' => true,
        'is_active' => true,
        ...$attributes,
    ]);
}

test('accounts page returns payload ready for the ui', function () {
    $user = verifiedAccountUser();

    $bank = Bank::query()->create([
        'name' => 'Banca Test',
        'slug' => 'banca-test',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $scope = Scope::query()->create([
        'user_id' => $user->id,
        'name' => 'Famiglia',
        'is_active' => true,
    ]);

    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $creditCardType = makeAccountType('credit_card', 'Carta di credito', 'liability');
    $linkedAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto principale',
        'bank_id' => $bank->id,
        'scope_id' => $scope->id,
        'current_balance' => 1500,
    ]);

    makeAccountForUser($user, $creditCardType, [
        'name' => 'Carta Oro',
        'current_balance' => -250,
        'settings' => [
            'credit_limit' => 5000,
            'linked_payment_account_id' => $linkedAccount->id,
            'payment_day' => 3,
            'auto_pay' => true,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts')
            ->where('accounts.summary.total_count', 2)
            ->where('accounts.summary.credit_cards_count', 1)
            ->where('accounts.data.0.account_type.balance_nature_label', fn (?string $value) => is_string($value) && $value !== '')
            ->where('options.banks.0.name', 'Banca Test')
            ->where('options.scopes.0.name', 'Famiglia')
            ->where('options.linked_payment_accounts.0.name', 'Conto principale'),
        );
});

test('user can create a credit card account with validated settings', function () {
    $user = verifiedAccountUser();

    $creditCardType = makeAccountType('credit_card', 'Carta di credito', 'liability');
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $bank = Bank::query()->create([
        'name' => 'Intesa Demo',
        'slug' => 'intesa-demo',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $linkedAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto stipendio',
    ]);

    $response = $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Visa personale',
            'bank_id' => $bank->id,
            'account_type_id' => $creditCardType->id,
            'currency' => 'eur',
            'iban' => '',
            'account_number_masked' => '**** 4242',
            'opening_balance' => 0,
            'current_balance' => -120.50,
            'is_manual' => true,
            'is_active' => true,
            'notes' => 'Carta personale',
            'settings' => [
                'credit_limit' => 3000,
                'linked_payment_account_id' => $linkedAccount->id,
                'statement_closing_day' => 15,
                'payment_day' => 3,
                'auto_pay' => true,
            ],
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Visa personale',
        'bank_id' => $bank->id,
        'account_type_id' => $creditCardType->id,
        'currency' => 'EUR',
        'account_number_masked' => '**** 4242',
        'current_balance' => -120.50,
    ]);

    $createdAccount = Account::query()->where('user_id', $user->id)->where('name', 'Visa personale')->firstOrFail();

    expect($createdAccount->settings)->toMatchArray([
        'credit_limit' => 3000.0,
        'linked_payment_account_id' => $linkedAccount->id,
        'statement_closing_day' => 15,
        'payment_day' => 3,
        'auto_pay' => true,
    ]);
});

test('credit card cannot be linked to an account from another user or to itself', function () {
    $user = verifiedAccountUser();
    $otherUser = verifiedAccountUser();

    $creditCardType = makeAccountType('credit_card', 'Carta di credito', 'liability');
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');

    $foreignAccount = makeAccountForUser($otherUser, $paymentType);
    $creditCard = makeAccountForUser($user, $creditCardType, [
        'name' => 'Mastercard',
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->patch(route('accounts.update', $creditCard), [
            '_token' => accountCsrfToken(),
            'name' => 'Mastercard',
            'account_type_id' => $creditCardType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'settings' => [
                'linked_payment_account_id' => $foreignAccount->id,
            ],
        ])
        ->assertSessionHasErrors('settings.linked_payment_account_id')
        ->assertRedirect(route('accounts.edit'));

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->patch(route('accounts.update', $creditCard), [
            '_token' => accountCsrfToken(),
            'name' => 'Mastercard',
            'account_type_id' => $creditCardType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'settings' => [
                'linked_payment_account_id' => $creditCard->id,
            ],
        ])
        ->assertSessionHasErrors('settings.linked_payment_account_id')
        ->assertRedirect(route('accounts.edit'));
});

test('cash account cannot accept iban or masked account number', function () {
    $user = verifiedAccountUser();

    $cashType = makeAccountType('cash_account', 'Contanti', 'asset');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Portafoglio',
            'account_type_id' => $cashType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'iban' => 'IT60X0542811101000000123456',
            'account_number_masked' => '**** 1234',
        ])
        ->assertSessionHasErrors(['iban', 'account_number_masked'])
        ->assertRedirect(route('accounts.edit'));
});

test('user can update account and toggle active state', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $bank = Bank::query()->create([
        'name' => 'Banco Demo',
        'slug' => 'banco-demo',
        'country_code' => 'IT',
        'is_active' => true,
    ]);
    $scope = Scope::query()->create([
        'user_id' => $user->id,
        'name' => 'Casa',
        'is_active' => true,
    ]);
    $account = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto famiglia',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->patch(route('accounts.update', $account), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto famiglia aggiornato',
            'bank_id' => $bank->id,
            'scope_id' => $scope->id,
            'account_type_id' => $paymentType->id,
            'currency' => 'USD',
            'is_manual' => false,
            'is_active' => true,
            'opening_balance' => 1000,
            'current_balance' => 1250,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'name' => 'Conto famiglia aggiornato',
        'bank_id' => $bank->id,
        'scope_id' => $scope->id,
        'currency' => 'USD',
        'is_manual' => false,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('accounts.toggle-active', $account))
        ->assertRedirect(route('accounts.edit'));

    expect($account->fresh()->is_active)->toBeFalse();
});

test('used account cannot be deleted but unused account can be removed safely', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');

    $usedAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto usato',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $usedAccount->id,
        'transaction_date' => now()->toDateString(),
        'direction' => 'expense',
        'amount' => 24.50,
        'currency' => 'EUR',
        'source_type' => 'manual',
        'status' => 'confirmed',
    ]);

    Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $usedAccount->id,
        'original_filename' => 'conto.csv',
        'stored_filename' => 'conto.csv',
        'mime_type' => 'text/csv',
        'source_type' => 'csv',
        'status' => 'completed',
    ]);

    AccountOpeningBalance::query()->create([
        'account_id' => $usedAccount->id,
        'balance_date' => now()->toDateString(),
        'amount' => 1000,
    ]);

    AccountBalanceSnapshot::query()->create([
        'account_id' => $usedAccount->id,
        'snapshot_date' => now()->toDateString(),
        'balance' => 975.50,
        'source_type' => 'system',
    ]);

    AccountReconciliation::query()->create([
        'account_id' => $usedAccount->id,
        'reconciliation_date' => now()->toDateString(),
        'expected_balance' => 975.50,
        'actual_balance' => 975.50,
        'difference_amount' => 0,
    ]);

    RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $usedAccount->id,
        'title' => 'Abbonamento',
        'direction' => 'expense',
        'expected_amount' => 12.90,
        'currency' => 'EUR',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'start_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    ScheduledEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $usedAccount->id,
        'title' => 'Pagamento bolletta',
        'direction' => 'expense',
        'expected_amount' => 90,
        'currency' => 'EUR',
        'scheduled_date' => now()->toDateString(),
        'status' => 'planned',
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->delete(route('accounts.destroy', $usedAccount), [
            '_token' => accountCsrfToken(),
        ])
        ->assertSessionHasErrors('delete')
        ->assertRedirect(route('accounts.edit'));

    expect($usedAccount->fresh())->not->toBeNull();

    $unusedAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto archivio',
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->delete(route('accounts.destroy', $unusedAccount), [
            '_token' => accountCsrfToken(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $this->assertDatabaseMissing('accounts', [
        'id' => $unusedAccount->id,
    ]);
});
