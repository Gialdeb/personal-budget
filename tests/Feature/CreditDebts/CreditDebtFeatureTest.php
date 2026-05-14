<?php

use App\Enums\CreditDebtStatusEnum;
use App\Enums\CreditDebtTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use App\Services\CreditDebts\CreditDebtPaymentService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['features.credits_debts.enabled' => true]);
});

test('credits debts routes are hidden when the feature flag is disabled', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['total_amount' => '100.00']);
    $payment = createCreditDebtPayment($user, $item, $account, '25.00', '2026-05-01');

    config(['features.credits_debts.enabled' => false]);

    $this->actingAs($user)
        ->getJson(route('credits-debts.index'))
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('credits-debts.store'), [])
        ->assertNotFound();

    $this->actingAs($user)
        ->getJson(route('credits-debts.show', $item))
        ->assertNotFound();

    $this->actingAs($user)
        ->putJson(route('credits-debts.update', $item), [])
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(route('credits-debts.destroy', $item))
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('credits-debts.payments.store', $item), [])
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(route('credits-debts.payments.destroy', [$item, $payment]))
        ->assertNotFound();
});

test('credits debts index returns inertia payload with summary options and shared feature flag', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
    ]);

    CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Rimborso cliente',
        'total_amount' => '100.00',
        'due_date' => '2026-05-20',
    ]);

    $this->actingAs($user)
        ->get(route('credits-debts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('credits-debts/Index')
            ->where('features.credits_debts_enabled', true)
            ->has('items', 1)
            ->where('summary.credits_remaining_total', '100.00')
            ->has('options.accounts', 1)
            ->has('options.categories')
            ->has('options.references')
            ->has('options.years')
            ->where('filters.year', '2026')
        );
});

test('credits debts index filters by selected year and returns payments as arrays', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
    ]);
    $currentItem = CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Credito corrente',
        'total_amount' => '100.00',
        'due_date' => '2026-05-20',
    ]);
    CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Credito vecchio',
        'total_amount' => '80.00',
        'due_date' => '2025-05-20',
    ]);
    createCreditDebtPayment($user, $currentItem, $account, '25.00', '2026-05-14');

    $this->actingAs($user)
        ->get(route('credits-debts.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.description', 'Credito corrente')
            ->where('items.0.payments_count', 1)
            ->has('items.0.payments', 1)
            ->where('items.0.payments.0.amount', '25.00')
            ->where('summary.credits_remaining_total', '75.00')
        );
});

test('credits debts index can filter a selected month', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
    ]);

    CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Credito maggio',
        'total_amount' => '100.00',
        'due_date' => '2026-05-20',
    ]);
    CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Credito giugno',
        'total_amount' => '80.00',
        'due_date' => '2026-06-20',
    ]);

    $this->actingAs($user)
        ->get(route('credits-debts.index', ['year' => 2026, 'month' => 5]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.description', 'Credito maggio')
            ->where('filters.month', '5')
            ->where('summary.credits_remaining_total', '100.00')
            ->has('options.months')
        );
});

test('credits debts index searches description reference note and keeps results scoped to the user', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $other = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
        'name' => 'Conto Allianz',
    ]);
    $secondaryAccount = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
        'name' => 'Conto Famiglia',
    ]);
    $otherAccount = createTestAccount($other);
    $category = creditDebtCategory($user);
    $reference = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Pino Mauro',
        'slug' => 'pino-mauro',
        'is_active' => true,
    ]);

    $matchingItem = CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Allianz Assicurazioni',
        'total_amount' => '180.00',
        'note' => 'Polizza annuale',
        'category_id' => $category->id,
        'reference_id' => $reference->id,
        'due_date' => '2026-05-20',
    ]);

    CreditDebtItem::factory()->forAccount($secondaryAccount)->create([
        'type' => CreditDebtTypeEnum::DEBIT->value,
        'description' => 'Voce non correlata',
        'total_amount' => '50.00',
        'due_date' => '2026-05-20',
    ]);

    $otherReference = TrackedItem::query()->create([
        'user_id' => $other->id,
        'name' => 'Pino Mauro',
        'slug' => 'pino-mauro-other',
        'is_active' => true,
    ]);
    CreditDebtItem::factory()->forAccount($otherAccount)->create([
        'description' => 'Pino altro utente',
        'reference_id' => $otherReference->id,
        'due_date' => '2026-05-20',
    ]);

    foreach (['Pino', 'mauro', 'ASSICURAZIONI', 'polizza', '180', 'Conto Allianz'] as $search) {
        $response = $this->actingAs($user)
            ->get(route('credits-debts.index', ['year' => 2026, 'search' => $search]))
            ->assertOk();

        $this->assertCount(1, $response->inertiaProps('items'), $search);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('items.0.uuid', $matchingItem->uuid)
            ->where('filters.search', $search)
        );
    }

    $this->actingAs($user)
        ->get(route('credits-debts.index', [
            'year' => 2026,
            'search' => 'pino',
            'type' => CreditDebtTypeEnum::DEBIT->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 0)
            ->where('filters.search', 'pino')
            ->where('filters.type', CreditDebtTypeEnum::DEBIT->value)
        );
});

test('credits debts future summaries include debit items due after today', function () {
    $this->travelTo(CarbonImmutable::create(2026, 5, 14, 12, 0, 0, config('app.timezone')));

    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
    ]);

    CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::DEBIT->value,
        'description' => 'Debito in scadenza domani',
        'total_amount' => '80.00',
        'due_date' => '2026-05-15',
    ]);

    $this->actingAs($user)
        ->get(route('credits-debts.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.debts_remaining_total', '80.00')
            ->where('summary.future_debts_total', '80.00')
        );

    $this->actingAs($user)
        ->get(route('credits-debts.index', ['year' => 2026, 'due_bucket' => 'future']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.description', 'Debito in scadenza domani')
        );
});

test('guests cannot create credit debt items', function () {
    $this->postJson(route('credits-debts.store'), [
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Rimborso',
        'total_amount' => '100.00',
        'currency_code' => 'EUR',
    ])->assertUnauthorized();
});

test('an authenticated user can create credits and debts without changing account balances', function (string $type) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
        'current_balance' => '50.00',
    ]);
    $category = creditDebtCategory($user);

    $response = $this->actingAs($user)->postJson(route('credits-debts.store'), [
        'type' => $type,
        'description' => 'Posizione puntuale',
        'total_amount' => '120.00',
        'currency_code' => 'EUR',
        'account_id' => $account->id,
        'category_id' => $category->id,
        'due_date' => '2026-05-31',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.type', $type)
        ->assertJsonPath('data.status', CreditDebtStatusEnum::OPEN->value)
        ->assertJsonPath('data.paid_amount', '0.00')
        ->assertJsonPath('data.remaining_amount', '120.00');

    expect($account->fresh()->current_balance)->toBe('50.00');
})->with([
    'credit' => [CreditDebtTypeEnum::CREDIT->value],
    'debit' => [CreditDebtTypeEnum::DEBIT->value],
]);

test('item validation blocks invalid type amount currency and foreign owned relations', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $other = User::factory()->create(['base_currency_code' => 'EUR']);
    $otherAccount = createTestAccount($other);
    $otherCategory = creditDebtCategory($other);

    $this->actingAs($user)->postJson(route('credits-debts.store'), [
        'type' => 'splitwise',
        'description' => 'Invalid',
        'total_amount' => '0',
        'currency_code' => 'XXX',
        'account_id' => $otherAccount->id,
        'category_id' => $otherCategory->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'total_amount', 'currency_code', 'account_id', 'category_id', 'due_date']);
});

test('item with payments cannot change locked fields or total amount', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $item = CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'total_amount' => '100.00',
    ]);

    createCreditDebtPayment($user, $item, $account, '40.00', '2026-05-01');

    $this->actingAs($user)->putJson(route('credits-debts.update', $item), [
        'type' => CreditDebtTypeEnum::DEBIT->value,
        'total_amount' => '100.00',
        'currency_code' => 'EUR',
        'description' => 'Updated',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);

    $this->actingAs($user)->putJson(route('credits-debts.update', $item), [
        'total_amount' => '140.00',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['total_amount']);

    $this->actingAs($user)->putJson(route('credits-debts.update', $item), [
        'description' => 'Descrizione aggiornata',
        'note' => 'Nota aggiornata',
        'due_date' => '2026-06-01',
    ])->assertSuccessful()
        ->assertJsonPath('data.description', 'Descrizione aggiornata')
        ->assertJsonPath('data.remaining_amount', '60.00');
});

test('cross user item access is blocked', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $account = createTestAccount($owner);
    $item = CreditDebtItem::factory()->forAccount($account)->create();

    $this->actingAs($intruder)
        ->putJson(route('credits-debts.update', $item), [
            'type' => CreditDebtTypeEnum::CREDIT->value,
            'description' => 'Nope',
            'total_amount' => '100.00',
            'currency_code' => 'EUR',
        ])
        ->assertForbidden();
});

test('an item without payments can be deleted and an item with payments cannot', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $emptyItem = CreditDebtItem::factory()->forAccount($account)->create();
    $paidItem = CreditDebtItem::factory()->forAccount($account)->create(['total_amount' => '100.00']);

    createCreditDebtPayment($user, $paidItem, $account, '10.00', '2026-05-01');

    $this->actingAs($user)
        ->deleteJson(route('credits-debts.destroy', $emptyItem))
        ->assertNoContent();

    $this->assertSoftDeleted($emptyItem);

    $this->actingAs($user)
        ->deleteJson(route('credits-debts.destroy', $paidItem))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credit_debt_item']);
});

test('credit and debt payments create real linked transactions and update calculated status', function (string $itemType, TransactionDirectionEnum $direction, string $description) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
        'opening_balance' => '100.00',
        'current_balance' => '100.00',
    ]);
    $item = CreditDebtItem::factory()->forAccount($account)->create([
        'type' => $itemType,
        'description' => 'Mario',
        'total_amount' => '100.00',
    ]);

    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $item), [
        'amount' => '40.00',
        'account_id' => $account->id,
        'paid_at' => '2026-05-02',
    ])->assertSuccessful()
        ->assertJsonPath('data.amount', '40.00');

    $payment = CreditDebtPayment::query()->firstOrFail();
    $transaction = Transaction::query()->firstOrFail();

    expect($payment->transaction_id)->toBe($transaction->id)
        ->and($transaction->direction)->toBe($direction)
        ->and($transaction->source_type)->toBe(TransactionSourceTypeEnum::GENERATED)
        ->and($transaction->description)->toBe($description);

    $item->refresh();

    expect($item->paidAmount())->toBe('40.00')
        ->and($item->remainingAmount())->toBe('60.00')
        ->and($item->status())->toBe(CreditDebtStatusEnum::PARTIAL);

    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $item), [
        'amount' => '60.00',
        'account_id' => $account->id,
        'paid_at' => '2026-05-03',
    ])->assertSuccessful();

    expect($item->refresh()->status())->toBe(CreditDebtStatusEnum::SETTLED)
        ->and($item->remainingAmount())->toBe('0.00');

    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $item), [
        'amount' => '0.01',
        'account_id' => $account->id,
        'paid_at' => '2026-05-04',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
})->with([
    'credit income' => [CreditDebtTypeEnum::CREDIT->value, TransactionDirectionEnum::INCOME, 'Incasso credito: Mario'],
    'debit expense' => [CreditDebtTypeEnum::DEBIT->value, TransactionDirectionEnum::EXPENSE, 'Pagamento debito: Mario'],
]);

test('payment validation blocks foreign item account currency mismatch and overpayment', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $other = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user, ['currency_code' => 'EUR', 'currency' => 'EUR']);
    $usdAccount = createTestAccount($user, ['currency_code' => 'USD', 'currency' => 'USD']);
    $otherAccount = createTestAccount($other);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['total_amount' => '20.00']);
    $otherItem = CreditDebtItem::factory()->forAccount($otherAccount)->create();

    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $otherItem), [
        'amount' => '10.00',
        'account_id' => $account->id,
        'paid_at' => '2026-05-01',
    ])->assertForbidden();

    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $item), [
        'amount' => '10.00',
        'account_id' => $otherAccount->id,
        'paid_at' => '2026-05-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id']);

    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $item), [
        'amount' => '10.00',
        'account_id' => $usdAccount->id,
        'paid_at' => '2026-05-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id']);

    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $item), [
        'amount' => '20.01',
        'account_id' => $account->id,
        'paid_at' => '2026-05-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('credit debt generated transactions keep the reference expose a return link and cannot be deleted from transactions', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);
    UserSetting::query()->updateOrCreate(['user_id' => $user->id], [
        'active_year' => 2026,
        'base_currency' => 'EUR',
    ]);

    $account = createTestAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
    ]);
    $category = creditDebtCategory($user);
    $reference = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Mario Rossi',
        'slug' => 'mario-rossi-credit-debt',
        'is_active' => true,
    ]);
    $item = CreditDebtItem::factory()->forAccount($account)->create([
        'type' => CreditDebtTypeEnum::CREDIT->value,
        'description' => 'Rimborso',
        'total_amount' => '100.00',
        'category_id' => $category->id,
        'reference_id' => $reference->id,
        'due_date' => '2026-05-20',
    ]);

    $payment = createCreditDebtPayment($user, $item, $account, '25.00', '2026-05-14');
    $linkedTransaction = Transaction::query()->findOrFail($payment->transaction_id);

    expect($linkedTransaction->tracked_item_id)->toBe($reference->id);

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2026, 'month' => 5]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction): bool => $transaction['uuid'] === $linkedTransaction->uuid
                    && ($transaction['is_credit_debt_transaction'] ?? false) === true
                    && ($transaction['credit_debt_item_uuid'] ?? null) === $item->uuid
                    && ($transaction['credit_debt_item_show_url'] ?? null) === route('credits-debts.index', [
                        'year' => 2026,
                        'month' => 5,
                        'selected' => $item->uuid,
                    ])
                    && ($transaction['can_delete'] ?? true) === false))
        );

    $this->actingAs($user)
        ->delete(route('transactions.destroy', [
            'year' => 2026,
            'month' => 5,
            'transaction' => $linkedTransaction,
        ]))
        ->assertSessionHasErrors('transaction');

    expect($linkedTransaction->fresh())->not->toBeNull();
});

test('payments can only be deleted from newest to oldest and remove linked transactions', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['total_amount' => '100.00']);

    $first = createCreditDebtPayment($user, $item, $account, '25.00', '2026-05-01');
    $second = createCreditDebtPayment($user, $item, $account, '25.00', '2026-05-02');

    $this->actingAs($user)
        ->deleteJson(route('credits-debts.payments.destroy', [$item, $first]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment']);

    $this->actingAs($user)
        ->deleteJson(route('credits-debts.payments.destroy', [$item, $second]))
        ->assertNoContent();

    $this->assertSoftDeleted($second);
    $this->assertDatabaseMissing('transactions', [
        'id' => $second->transaction_id,
    ]);
    expect(Transaction::onlyTrashed()->whereKey($second->transaction_id)->exists())->toBeFalse();

    expect($item->refresh()->paidAmount())->toBe('25.00')
        ->and($item->remainingAmount())->toBe('75.00')
        ->and($item->status())->toBe(CreditDebtStatusEnum::PARTIAL);
});

test('payment deletion refuses to force delete a transaction that is not generated by the payment flow', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['total_amount' => '100.00']);
    $payment = createCreditDebtPayment($user, $item, $account, '25.00', '2026-05-01');
    $generatedTransactionId = $payment->transaction_id;
    $manualTransaction = userTransaction($user, $account);

    $payment->forceFill([
        'transaction_id' => $manualTransaction->id,
    ])->save();

    $this->actingAs($user)
        ->deleteJson(route('credits-debts.payments.destroy', [$item, $payment]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment']);

    expect($payment->fresh())->not->toBeNull()
        ->and($manualTransaction->fresh())->not->toBeNull()
        ->and(Transaction::onlyTrashed()->whereKey($manualTransaction->id)->exists())->toBeFalse()
        ->and(Transaction::query()->whereKey($generatedTransactionId)->exists())->toBeTrue();
});

test('credit debt flow does not create recurring entries', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['total_amount' => '100.00']);

    createCreditDebtPayment($user, $item, $account, '50.00', '2026-05-01');

    expect(RecurringEntry::query()->count())->toBe(0);
});

function creditDebtCategory(User $user): Category
{
    return Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Crediti e debiti',
        'slug' => 'crediti-debiti-'.str()->uuid(),
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'is_active' => true,
        'is_selectable' => true,
    ]);
}

function createCreditDebtPayment(User $user, CreditDebtItem $item, Account $account, string $amount, string $paidAt): CreditDebtPayment
{
    return app(CreditDebtPaymentService::class)->create($user, $item, [
        'amount' => $amount,
        'account_id' => $account->id,
        'paid_at' => $paidAt,
    ]);
}
