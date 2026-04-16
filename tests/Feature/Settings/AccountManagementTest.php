<?php

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\AccountOpeningBalance;
use App\Models\AccountReconciliation;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\ExchangeRate;
use App\Models\Import;
use App\Models\RecurringEntry;
use App\Models\ScheduledEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserBank;
use App\Models\UserYear;
use Inertia\Testing\AssertableInertia as Assert;

function verifiedAccountUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->settings()->create([
        'active_year' => now()->year,
    ]);

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => now()->year,
        'is_closed' => false,
    ]);

    return $user;
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
        'currency' => $user->base_currency_code,
        'currency_code' => $user->base_currency_code,
        'is_manual' => true,
        'is_active' => true,
        ...$attributes,
    ]);
}

function makeUserBankForUser(User $user, array $attributes = []): UserBank
{
    $bank = $attributes['bank'] ?? Bank::query()->create([
        'name' => 'Banca '.fake()->unique()->company(),
        'slug' => 'banca-'.fake()->unique()->slug(),
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    unset($attributes['bank']);

    return UserBank::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'name' => $bank->name,
        'slug' => $bank->slug,
        'is_custom' => false,
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
    $userBank = makeUserBankForUser($user, ['bank' => $bank]);

    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $creditCardType = makeAccountType('credit_card', 'Carta di credito', 'liability');
    $linkedAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto principale',
        'bank_id' => $bank->id,
        'user_bank_id' => $userBank->id,
        'opening_balance' => 250,
        'opening_balance_date' => '2026-01-03',
        'current_balance' => 1500,
        'is_default' => true,
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
            ->where('accounts.data.0.opening_balance_direction', fn (string $value) => in_array($value, ['positive', 'negative'], true))
            ->where('accounts.data', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['name'] === 'Conto principale'
                    && $account['opening_balance_date'] === '2026-01-03'))
            ->where('options.opening_balance_date.available_years', [2026])
            ->where('options.opening_balance_date.max', now()->toDateString())
            ->where('options.default_account_uuid', $linkedAccount->uuid)
            ->where('options.banks.0.name', 'Banca Test')
            ->where('options.currencies.0.code', 'EUR')
            ->where('options.currencies.0.name', 'Euro')
            ->where('options.currencies.0.symbol', '€')
            ->where('options.currencies.0.label', 'EUR — Euro (€)')
            ->where('accounts.data', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $linkedAccount->uuid
                    && $account['is_default'] === true
                    && $account['currency_label'] === 'EUR — Euro (€)'
                    && $account['can_update_currency'] === false))
            ->where('options.linked_payment_accounts.0.name', 'Conto principale'),
        );
});

test('user can set an account as default and a new default removes the previous flag', function () {
    $user = verifiedAccountUser();

    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $first = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto principale',
    ]);
    $second = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto secondario',
    ]);

    foreach ([$first, $second] as $account) {
        $this
            ->withSession(['_token' => accountCsrfToken()])
            ->actingAs($user)
            ->patch(route('accounts.update', $account), [
                '_token' => accountCsrfToken(),
                'name' => $account->name,
                'user_bank_uuid' => null,
                'account_type_uuid' => $paymentType->uuid,
                'currency' => 'EUR',
                'iban' => '',
                'account_number_masked' => '',
                'opening_balance' => 0,
                'opening_balance_direction' => 'positive',
                'opening_balance_date' => '',
                'current_balance' => 0,
                'is_active' => true,
                'is_reported' => true,
                'is_default' => true,
                'notes' => '',
                'settings' => [],
            ])
            ->assertRedirect(route('accounts.edit'));
    }

    expect($first->fresh()?->is_default)->toBeFalse()
        ->and($second->fresh()?->is_default)->toBeTrue();
});

test('first active bank backed account becomes default automatically when the user has no default yet', function () {
    $user = verifiedAccountUser();

    $cashType = makeAccountType('cash_account', 'Contanti', 'asset');
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $userBank = makeUserBankForUser($user);

    makeAccountForUser($user, $cashType, [
        'name' => 'Cassa contanti',
        'is_default' => false,
        'user_bank_id' => null,
        'bank_id' => null,
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto corrente principale',
            'user_bank_id' => $userBank->id,
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'iban' => '',
            'account_number_masked' => '',
            'opening_balance' => 0,
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '',
            'current_balance' => 0,
            'is_manual' => true,
            'is_active' => true,
            'is_reported' => true,
            'is_default' => false,
            'notes' => '',
            'settings' => [],
        ])
        ->assertRedirect(route('accounts.edit'))
        ->assertSessionHasNoErrors();

    $created = Account::query()
        ->ownedBy($user->id)
        ->where('name', 'Conto corrente principale')
        ->firstOrFail();

    expect($created->is_default)->toBeTrue()
        ->and(Account::query()->defaultOwnedBy($user->id)->value('id'))->toBe($created->id);
});

test('creating a new bank backed account does not override an existing default automatically', function () {
    $user = verifiedAccountUser();

    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $existingDefaultBank = makeUserBankForUser($user);
    $newBank = makeUserBankForUser($user);

    $existingDefault = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto già predefinito',
        'user_bank_id' => $existingDefaultBank->id,
        'bank_id' => $existingDefaultBank->bank_id,
        'is_default' => true,
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Nuovo conto bancario',
            'user_bank_id' => $newBank->id,
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'iban' => '',
            'account_number_masked' => '',
            'opening_balance' => 0,
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '',
            'current_balance' => 0,
            'is_manual' => true,
            'is_active' => true,
            'is_reported' => true,
            'is_default' => false,
            'notes' => '',
            'settings' => [],
        ])
        ->assertRedirect(route('accounts.edit'))
        ->assertSessionHasNoErrors();

    $created = Account::query()
        ->ownedBy($user->id)
        ->where('name', 'Nuovo conto bancario')
        ->firstOrFail();

    expect($existingDefault->fresh()->is_default)->toBeTrue()
        ->and($created->is_default)->toBeFalse()
        ->and(Account::query()->defaultOwnedBy($user->id)->value('id'))->toBe($existingDefault->id);
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
    $userBank = makeUserBankForUser($user, ['bank' => $bank]);

    $linkedAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto stipendio',
        'bank_id' => $bank->id,
        'user_bank_id' => $userBank->id,
    ]);

    $response = $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Visa personale',
            'user_bank_id' => $userBank->id,
            'account_type_id' => $creditCardType->id,
            'currency' => 'eur',
            'iban' => '',
            'account_number_masked' => '**** 4242',
            'opening_balance' => 120.50,
            'opening_balance_direction' => 'negative',
            'opening_balance_date' => '2026-01-10',
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
        'user_bank_id' => $userBank->id,
        'account_type_id' => $creditCardType->id,
        'currency' => 'EUR',
        'account_number_masked' => '**** 4242',
        'opening_balance' => -120.50,
        'current_balance' => -120.50,
    ]);

    $createdAccount = Account::query()->where('user_id', $user->id)->where('name', 'Visa personale')->firstOrFail();
    $openingTransaction = Transaction::query()
        ->where('account_id', $createdAccount->id)
        ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
        ->first();

    expect($createdAccount->settings)->toMatchArray([
        'credit_limit' => 3000.0,
        'linked_payment_account_id' => $linkedAccount->id,
        'statement_closing_day' => 15,
        'payment_day' => 3,
        'auto_pay' => true,
    ])->and($openingTransaction)->not->toBeNull()
        ->and($openingTransaction?->direction)->toBe(TransactionDirectionEnum::EXPENSE)
        ->and((float) $openingTransaction?->amount)->toBe(120.5)
        ->and($openingTransaction?->transaction_date?->toDateString())->toBe('2026-01-10')
        ->and($createdAccount->opening_balance_date?->toDateString())->toBe('2026-01-10');
});

test('credit card linked payment account must belong to the same selected bank and cannot be cash', function () {
    $user = verifiedAccountUser();

    $creditCardType = makeAccountType('credit_card', 'Carta di credito', 'liability');
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $cashType = makeAccountType('cash_account', 'Contanti', 'asset');

    $selectedUserBank = makeUserBankForUser($user, [
        'bank' => Bank::query()->create([
            'name' => 'Banca Uno',
            'slug' => 'banca-uno',
            'country_code' => 'IT',
            'is_active' => true,
        ]),
    ]);
    $otherUserBank = makeUserBankForUser($user, [
        'bank' => Bank::query()->create([
            'name' => 'Banca Due',
            'slug' => 'banca-due',
            'country_code' => 'IT',
            'is_active' => true,
        ]),
    ]);

    $differentBankAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto altra banca',
        'bank_id' => $otherUserBank->bank_id,
        'user_bank_id' => $otherUserBank->id,
    ]);
    $cashAccount = makeAccountForUser($user, $cashType, [
        'name' => 'Cassa contanti',
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Carta banca uno',
            'user_bank_id' => $selectedUserBank->id,
            'account_type_id' => $creditCardType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'settings' => [
                'linked_payment_account_id' => $differentBankAccount->id,
            ],
        ])
        ->assertSessionHasErrors('settings.linked_payment_account_uuid')
        ->assertRedirect(route('accounts.edit'));

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Carta cassa',
            'user_bank_id' => $selectedUserBank->id,
            'account_type_id' => $creditCardType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'settings' => [
                'linked_payment_account_id' => $cashAccount->id,
            ],
        ])
        ->assertSessionHasErrors('settings.linked_payment_account_uuid')
        ->assertRedirect(route('accounts.edit'));
});

test('credit card requires bank, debit account, and billing cycle settings', function () {
    $user = verifiedAccountUser();

    $creditCardType = makeAccountType('credit_card', 'Carta di credito', 'liability');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Carta incompleta',
            'account_type_id' => $creditCardType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'settings' => [],
        ])
        ->assertSessionHasErrors([
            'user_bank_uuid',
            'settings.linked_payment_account_uuid',
            'settings.statement_closing_day',
            'settings.payment_day',
        ])
        ->assertRedirect(route('accounts.edit'));
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
        ->assertSessionHasErrors('settings.linked_payment_account_uuid')
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
        ->assertSessionHasErrors('settings.linked_payment_account_uuid')
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

test('accounts page excludes cash accounts from linked payment account options', function () {
    $user = verifiedAccountUser();

    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $cashType = makeAccountType('cash_account', 'Contanti', 'asset');
    $userBank = makeUserBankForUser($user);

    $linkedPaymentAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto operativo',
        'bank_id' => $userBank->bank_id,
        'user_bank_id' => $userBank->id,
    ]);
    makeAccountForUser($user, $cashType, [
        'name' => 'Cassa contanti',
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where(
                'options.linked_payment_accounts',
                fn ($options) => collect($options)
                    ->contains(fn ($option) => $option['uuid'] === $linkedPaymentAccount->uuid
                        && $option['user_bank_uuid'] === $userBank->uuid)
                    && collect($options)->every(fn ($option) => $option['account_type_code'] !== 'cash_account')
            ));
});

test('negative balances are blocked by default but can be enabled on eligible accounts', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto base',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'current_balance' => -50,
            'settings' => [
                'allow_negative_balance' => false,
            ],
        ])
        ->assertSessionHasErrors('current_balance')
        ->assertRedirect(route('accounts.edit'));

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto flessibile',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'current_balance' => -50,
            'settings' => [
                'allow_negative_balance' => true,
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $account = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Conto flessibile')
        ->firstOrFail();

    expect(data_get($account->settings, 'allow_negative_balance'))->toBeTrue();
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
    $userBank = makeUserBankForUser($user, ['bank' => $bank]);
    $account = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto famiglia',
        'currency' => 'EUR',
        'is_active' => true,
        'current_balance' => 450,
    ]);

    ExchangeRate::query()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'EUR',
        'rate' => '0.92000000',
        'rate_date' => '2026-02-14',
        'source' => 'test',
        'fetched_at' => now(),
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->patch(route('accounts.update', $account), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto famiglia aggiornato',
            'user_bank_id' => $userBank->id,
            'account_type_id' => $paymentType->id,
            'currency' => 'USD',
            'is_manual' => false,
            'is_active' => true,
            'opening_balance' => 1000,
            'opening_balance_date' => '2026-02-14',
            'current_balance' => 1250,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'name' => 'Conto famiglia aggiornato',
        'bank_id' => $bank->id,
        'user_bank_id' => $userBank->id,
        'currency' => 'USD',
        'currency_code' => 'USD',
        'is_manual' => false,
    ]);

    expect((float) $account->fresh()->current_balance)->toBe(1000.0)
        ->and($account->fresh()->opening_balance_date?->toDateString())->toBe('2026-02-14');

    $this
        ->actingAs($user)
        ->patch(route('accounts.toggle-active', $account))
        ->assertRedirect(route('accounts.edit'));

    expect($account->fresh()->is_active)->toBeFalse();
});

test('account create defaults is_manual to true when the ui does not send it', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto senza flag manuale',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 50,
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '2026-01-04',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Conto senza flag manuale',
        'is_manual' => true,
    ]);
});

test('account update preserves existing is_manual when the ui omits the field', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $account = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto legacy import',
        'is_manual' => false,
        'current_balance' => 200,
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->patch(route('accounts.update', $account), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto legacy import aggiornato',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 200,
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '2026-01-04',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    expect($account->fresh()->name)->toBe('Conto legacy import aggiornato')
        ->and($account->fresh()->is_manual)->toBeFalse();
});

test('account update ignores current balance sent by the client', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $account = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto operativo',
        'currency' => 'EUR',
        'current_balance' => 320.75,
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->patch(route('accounts.update', $account), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto operativo aggiornato',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'opening_balance' => 100,
            'opening_balance_date' => '2026-01-05',
            'current_balance' => 9999,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $account->refresh();

    expect($account->name)->toBe('Conto operativo aggiornato')
        ->and((float) $account->current_balance)->toBe(100.0)
        ->and($account->opening_balance_date?->toDateString())->toBe('2026-01-05');
});

test('account opening balance creates and updates a dedicated opening balance transaction', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto apertura',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 250,
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '2026-01-04',
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $account = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Conto apertura')
        ->firstOrFail();

    $openingTransaction = Transaction::query()
        ->where('account_id', $account->id)
        ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
        ->firstOrFail();

    expect((float) $account->opening_balance)->toBe(250.0)
        ->and((float) $account->current_balance)->toBe(250.0)
        ->and($account->opening_balance_date?->toDateString())->toBe('2026-01-04')
        ->and($openingTransaction->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and((float) $openingTransaction->amount)->toBe(250.0)
        ->and($openingTransaction->transaction_date?->toDateString())->toBe('2026-01-04');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->patch(route('accounts.update', $account), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto apertura',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 180,
            'opening_balance_direction' => 'negative',
            'opening_balance_date' => '2026-01-02',
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(Transaction::query()
        ->where('account_id', $account->id)
        ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
        ->count())->toBe(1);

    $openingTransaction->refresh();
    $account->refresh();

    expect((float) $account->opening_balance)->toBe(-180.0)
        ->and((float) $account->current_balance)->toBe(-180.0)
        ->and($account->opening_balance_date?->toDateString())->toBe('2026-01-02')
        ->and($openingTransaction->direction)->toBe(TransactionDirectionEnum::EXPENSE)
        ->and((float) $openingTransaction->amount)->toBe(180.0)
        ->and($openingTransaction->transaction_date?->toDateString())->toBe('2026-01-02');
});

test('opening balance date is required when setting an opening balance', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto senza data',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 250,
            'opening_balance_direction' => 'positive',
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('opening_balance_date')
        ->assertRedirect(route('accounts.edit'));
});

test('opening balance date cannot be in the future or outside open management years', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto futuro',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 250,
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => now()->addDay()->toDateString(),
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('opening_balance_date')
        ->assertRedirect(route('accounts.edit'));

    UserYear::query()->where('user_id', $user->id)->delete();
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2025,
        'is_closed' => false,
    ]);
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => true,
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto fuori anno aperto',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 250,
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '2026-02-01',
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('opening_balance_date')
        ->assertRedirect(route('accounts.edit'));
});

test('opening balance date cannot be later than the first account transaction', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $account = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto storico',
        'opening_balance' => 0,
        'current_balance' => 50,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 50,
        'currency' => $user->base_currency_code,
        'source_type' => 'manual',
        'status' => 'confirmed',
    ]);

    $response = $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->patch(route('accounts.update', $account), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto storico',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 100,
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '2026-01-11',
            'is_manual' => true,
            'is_active' => true,
        ]);

    $response
        ->assertSessionHasErrors('opening_balance_date')
        ->assertRedirect(route('accounts.edit'));

    $sessionErrors = $this->app['session.store']->get('errors');

    expect($sessionErrors?->get('opening_balance_date'))
        ->toContain('La prima transazione del conto è del 10 gen 2026. Imposta una data di apertura uguale o precedente.');
});

test('opening balance date can match the first account transaction and updates the same opening entry', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $account = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto cronologico',
        'opening_balance' => 100,
        'opening_balance_date' => '2026-01-03',
        'current_balance' => 100,
    ]);

    $openingTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-01-03',
        'value_date' => '2026-01-03',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 100,
        'currency' => $user->base_currency_code,
        'source_type' => 'generated',
        'status' => 'confirmed',
        'balance_after' => 100,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 50,
        'currency' => $user->base_currency_code,
        'source_type' => 'manual',
        'status' => 'confirmed',
        'balance_after' => 150,
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->patch(route('accounts.update', $account), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto cronologico',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'opening_balance' => 180,
            'opening_balance_direction' => 'negative',
            'opening_balance_date' => '2026-01-10',
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    expect(Transaction::query()
        ->where('account_id', $account->id)
        ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
        ->count())->toBe(1);

    $openingTransactionId = $openingTransaction->id;
    $openingTransaction->refresh();
    $account->refresh();

    expect($openingTransaction->id)->toBe($openingTransactionId)
        ->and($openingTransaction->transaction_date?->toDateString())->toBe('2026-01-10')
        ->and($openingTransaction->direction)->toBe(TransactionDirectionEnum::EXPENSE)
        ->and((float) $openingTransaction->amount)->toBe(180.0)
        ->and($account->opening_balance_date?->toDateString())->toBe('2026-01-10')
        ->and((float) $account->current_balance)->toBe(-130.0);
});

test('account cannot use a bank from another user', function () {
    $user = verifiedAccountUser();
    $otherUser = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $foreignUserBank = makeUserBankForUser($otherUser);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto non valido',
            'user_bank_id' => $foreignUserBank->id,
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('user_bank_id')
        ->assertRedirect(route('accounts.edit'));
});

test('cash accounts force no bank on create and update', function () {
    $user = verifiedAccountUser();
    $cashType = makeAccountType('cash_account', 'Contanti', 'asset');
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');
    $userBank = makeUserBankForUser($user);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Cassa contanti',
            'user_bank_id' => $userBank->id,
            'account_type_id' => $cashType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $cashAccount = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Cassa contanti')
        ->firstOrFail();

    expect($cashAccount->user_bank_id)->toBeNull()
        ->and($cashAccount->bank_id)->toBeNull();

    $regularAccount = makeAccountForUser($user, $paymentType, [
        'name' => 'Conto da riallineare',
        'user_bank_id' => $userBank->id,
        'bank_id' => $userBank->bank_id,
    ]);

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->patch(route('accounts.update', $regularAccount), [
            '_token' => accountCsrfToken(),
            'name' => 'Cassa contanti',
            'user_bank_id' => $userBank->id,
            'account_type_id' => $cashType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    expect($regularAccount->fresh()->user_bank_id)->toBeNull()
        ->and($regularAccount->fresh()->bank_id)->toBeNull()
        ->and($regularAccount->fresh()->accountType?->code)->toBe('cash_account');
});

test('protected cash account cannot be deactivated or deleted', function () {
    $user = verifiedAccountUser();
    $cashType = makeAccountType('cash_account', 'Contanti', 'asset');
    $cashAccount = makeAccountForUser($user, $cashType, [
        'name' => 'Cassa contanti',
        'is_active' => true,
    ]);

    $this
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->patch(route('accounts.toggle-active', $cashAccount))
        ->assertSessionHasErrors('toggle')
        ->assertRedirect(route('accounts.edit'));

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->from(route('accounts.edit'))
        ->delete(route('accounts.destroy', $cashAccount), [
            '_token' => accountCsrfToken(),
        ])
        ->assertSessionHasErrors('delete')
        ->assertRedirect(route('accounts.edit'));

    expect($cashAccount->fresh()->is_active)->toBeTrue()
        ->and($cashAccount->fresh())->not->toBeNull();
});

test('account create and update persist the reported flag', function () {
    $user = verifiedAccountUser();
    $paymentType = makeAccountType('payment_account', 'Conto di pagamento', 'asset');

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->post(route('accounts.store'), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto escluso report',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'is_reported' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    $account = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Conto escluso report')
        ->firstOrFail();

    expect($account->is_reported)->toBeFalse();

    $this
        ->withSession(['_token' => accountCsrfToken()])
        ->actingAs($user)
        ->patch(route('accounts.update', $account), [
            '_token' => accountCsrfToken(),
            'name' => 'Conto escluso report',
            'account_type_id' => $paymentType->id,
            'currency' => 'EUR',
            'is_manual' => true,
            'is_active' => true,
            'is_reported' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('accounts.edit'));

    expect($account->fresh()->is_reported)->toBeTrue();
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
