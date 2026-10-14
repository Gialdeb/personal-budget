<?php

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\CreditDebtPaymentService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\Reminders\DailyCreditDebtReminderService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\TransactionMutationService;
use App\Support\MoneyAmount;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config(['features.credits_debts.enabled' => true]);
});

function integrationCounterparty(User $user, string $name = 'Giulia'): TrackedItem
{
    return TrackedItem::query()->create(['user_id' => $user->id, 'name' => $name, 'slug' => str($name)->slug(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
}

function integrationSharedExpense(User $user, TrackedItem $counterparty): Transaction
{
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);

    return userTransaction($user, $account, ['amount' => '100.00', 'shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
}

function integrationSettlement(User $user, TrackedItem $counterparty): SharedExpenseSettlement
{
    $transaction = integrationSharedExpense($user, $counterparty);

    return app(SharedExpenseSettlementService::class)->record($user, $counterparty, ['amount' => '20.00', 'settled_at' => today()->toDateString(), 'account_uuid' => $transaction->account->uuid, 'reference' => null]);
}

test('manual positions can be created without a due date or a counterparty', function (string $type, bool $omitDate) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $category = Category::query()->create(['user_id' => $user->id, 'name' => 'Spese', 'slug' => 'spese', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $data = ['type' => $type, 'description' => 'Importo pregresso', 'total_amount' => '200.00', 'currency_code' => 'EUR', 'account_id' => $account->id, 'category_id' => $category->id];
    if (! $omitDate) {
        $data['due_date'] = null;
    }
    $this->actingAs($user)->postJson(route('credits-debts.store'), $data)
        ->assertSuccessful()->assertJsonPath('data.due_date', null)->assertJsonPath('data.counterparty', null)->assertJsonPath('data.status', 'open');

    expect(Transaction::query()->count())->toBe(0);
})->with(['credit', 'debit'])->with([true, false]);

test('a due date can be removed and undated settled history remains visible across periods', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['total_amount' => '50.00']);
    $this->actingAs($user)->putJson(route('credits-debts.update', $item), ['due_date' => null])->assertSuccessful()->assertJsonPath('data.due_date', null);
    app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '50.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id]);
    $this->getJson(route('credits-debts.index', ['year' => 2030, 'month' => 2, 'due_bucket' => 'undated', 'status' => 'settled']))
        ->assertSuccessful()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'settled');
});

test('undated positions are visible and summarized but never overdue future or reminded', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $item = CreditDebtItem::factory()->create(['user_id' => $user->id, 'due_date' => null, 'total_amount' => '200.00']);
    $this->actingAs($user)->get(route('credits-debts.index', ['year' => 2030, 'month' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('items', 1)->where('summary.credits_remaining_total', '200.00')->where('summary.overdue_count', 0)
        ->where('summary.future_credits_total', '0.00')->where('summary.undated_credits_total', '200.00'));
    foreach (['overdue', 'future', 'current_month'] as $bucket) {
        $this->getJson(route('credits-debts.index', ['due_bucket' => $bucket]))->assertJsonCount(0, 'data');
    }
    expect(app(DailyCreditDebtReminderService::class)->run())->toMatchArray(['scanned' => 0, 'notified' => 0]);
    expect($item->fresh()->status()->value)->toBe('open');
    $account = createTestAccount($user);
    app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '25.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id]);
    expect($item->fresh()->status()->value)->toBe('partial')
        ->and(app(DailyCreditDebtReminderService::class)->run())->toMatchArray(['scanned' => 0, 'notified' => 0]);
});

test('a manual counterparty is explicit and independent from its descriptive reference', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($user);
    $category = Category::query()->create(['user_id' => $user->id, 'name' => 'Spese', 'slug' => 'spese', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $counterparty = integrationCounterparty($user);
    $reference = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Viaggio', 'slug' => 'viaggio', 'is_active' => true]);
    $this->actingAs($user)->postJson(route('credits-debts.store'), ['type' => 'credit', 'description' => 'Anticipo', 'total_amount' => '200.00', 'currency_code' => 'EUR', 'account_id' => $account->id, 'category_id' => $category->id, 'counterparty_uuid' => $counterparty->uuid, 'reference_id' => $reference->id])
        ->assertSuccessful()->assertJsonPath('data.counterparty.uuid', $counterparty->uuid);
    $item = CreditDebtItem::query()->firstOrFail();
    expect($item->reference_id)->toBe($reference->id)->and($item->counterparty_id)->toBe($counterparty->id);
    $this->getJson(route('credits-debts.show', $item))->assertJsonPath('data.counterparty.uuid', $counterparty->uuid);
});

test('manual counterparty selection rejects foreign account scoped inactive and ineligible references', function (string $case) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($case === 'foreign' ? User::factory()->create() : $user);
    if ($case === 'account') {
        $counterparty->update(['account_id' => createTestAccount($user)->id]);
    } elseif ($case === 'inactive') {
        $counterparty->update(['is_active' => false]);
    } elseif ($case === 'ineligible') {
        $counterparty->update(['is_shared_expense_counterpart' => false]);
    }
    $item = CreditDebtItem::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user)->putJson(route('credits-debts.update', $item), ['counterparty_uuid' => $counterparty->uuid])
        ->assertUnprocessable()->assertJsonValidationErrors('counterparty_id');
    expect($item->fresh()->counterparty_id)->toBeNull();
})->with(['foreign', 'account', 'inactive', 'ineligible']);

test('unknown counterparty uuids cannot silently become an unassigned position', function () {
    $user = User::factory()->create();
    $item = CreditDebtItem::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user)->putJson(route('credits-debts.update', $item), ['counterparty_uuid' => (string) str()->uuid()])->assertUnprocessable()->assertJsonValidationErrors('counterparty_id');
});

test('counterparty cannot change after active payments but historical eligibility can be retained', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    $other = integrationCounterparty($user, 'Anna');
    $account = createTestAccount($user);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['counterparty_id' => $counterparty->id]);
    app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '20.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id]);
    $this->actingAs($user)->putJson(route('credits-debts.update', $item), ['counterparty_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('counterparty_id');
    $this->putJson(route('credits-debts.update', $item), ['counterparty_uuid' => null])->assertUnprocessable()->assertJsonValidationErrors('counterparty_id');
    $counterparty->update(['is_active' => false, 'is_shared_expense_counterpart' => false]);
    $this->putJson(route('credits-debts.update', $item), ['counterparty_uuid' => $counterparty->uuid, 'due_date' => null])->assertSuccessful();
    expect($item->fresh()->counterparty_id)->toBe($counterparty->id);
});

test('the same normalized person is reused from the common counterparty endpoint', function () {
    $user = User::factory()->create();
    $counterparty = integrationCounterparty($user);
    $this->actingAs($user)->postJson(route('shared-expenses.counterparties.store'), ['name' => '  GIULIA  '])->assertCreated()->assertJsonPath('uuid', $counterparty->uuid);
    expect(TrackedItem::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('aggregate balances use manual residuals and shared quotas without counting either twice', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    $transaction = integrationSharedExpense($user, $counterparty);
    $item = CreditDebtItem::factory()->forAccount($transaction->account)->create(['counterparty_id' => $counterparty->id, 'total_amount' => '200.00', 'due_date' => null]);
    $service = app(CounterpartyBalanceService::class);
    expect($service->balance($user, $counterparty, 'EUR'))->toBe(['currency' => 'EUR', 'manual_cents' => 20000, 'shared_cents' => 5000, 'total_cents' => 25000]);
    $this->actingAs($user)->get(route('credits-debts.index'))->assertInertia(fn (Assert $page) => $page
        ->has('items', 2)->where('summary.credits_remaining_total', '250.00')->where("counterpartyBalances.{$counterparty->uuid}.0.total_cents", 25000));
    $this->get(route('shared-expenses.index', ['year' => now()->year, 'month' => now()->month]))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where("balances.{$counterparty->uuid}", 5000)->where("counterpartyBalances.{$counterparty->uuid}.0.manual_cents", 20000));
    $payment = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '60.00', 'paid_at' => today()->toDateString(), 'account_id' => $transaction->account_id]);
    expect($service->balance($user, $counterparty, 'EUR')['total_cents'])->toBe(19000)->and($item->fresh()->status()->value)->toBe('partial');
    $this->get(route('credits-debts.index'))->assertInertia(fn (Assert $page) => $page->where('summary.credits_remaining_total', '190.00'));
    app(CreditDebtPaymentService::class)->delete($user, $payment);
    expect($service->balance($user, $counterparty, 'EUR')['total_cents'])->toBe(25000);
});

test('manual debts and currencies stay separate and legacy references are not assigned', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    CreditDebtItem::factory()->create(['user_id' => $user->id, 'counterparty_id' => $counterparty->id, 'type' => 'debit', 'total_amount' => '30.00', 'currency_code' => 'EUR']);
    CreditDebtItem::factory()->create(['user_id' => $user->id, 'counterparty_id' => $counterparty->id, 'total_amount' => '200.00', 'currency_code' => 'USD']);
    $legacy = CreditDebtItem::factory()->create(['user_id' => $user->id, 'reference_id' => $counterparty->id, 'total_amount' => '75.00']);
    $service = app(CounterpartyBalanceService::class);
    expect($service->balance($user, $counterparty, 'EUR')['manual_cents'])->toBe(-3000)
        ->and($service->balance($user, $counterparty, 'USD')['manual_cents'])->toBe(20000)
        ->and($legacy->fresh()->counterparty_id)->toBeNull();
    $this->actingAs($user)->get(route('credits-debts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('summary.credits_remaining_total', '75.00')->where('summary.debts_remaining_total', '30.00')
        ->where('summariesByCurrency.USD.credits_remaining_total', '200.00'));
});

test('aggregate balance refuses another owners counterparty', function () {
    $user = User::factory()->create();
    app(CounterpartyBalanceService::class)->balance($user, integrationCounterparty(User::factory()->create()), 'EUR');
})->throws(HttpException::class);

test('used counterparties cannot be deleted including soft deleted economic history', function (string $source) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    if ($source === 'transaction') {
        integrationSharedExpense($user, $counterparty)->delete();
    } elseif ($source === 'manual') {
        CreditDebtItem::factory()->create(['user_id' => $user->id, 'counterparty_id' => $counterparty->id])->delete();
    } elseif ($source === 'settlement') {
        integrationSettlement($user, $counterparty);
    } elseif ($source === 'recurring') {
        RecurringEntry::query()->create(['user_id' => $user->id, 'account_id' => createTestAccount($user)->id, 'title' => 'Affitto', 'shared_expense_counterparty_id' => $counterparty->id, 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true, 'start_date' => today(), 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'never', 'expected_amount' => '100.00', 'is_amount_variable' => false]);
    } else {
        SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $counterparty->id, 'entry_date' => today(), 'title' => 'Spesa', 'currency' => 'EUR', 'total_cents' => 10000, 'paid_by' => 'counterparty', 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'status' => 'recorded']);
    }
    $this->actingAs($user)->deleteJson(route('tracked-items.destroy', $counterparty->uuid))->assertUnprocessable();
    expect(TrackedItem::find($counterparty->id))->not->toBeNull();
    expect(fn () => $counterparty->delete())->toThrow(ValidationException::class);
})->with(['transaction', 'manual', 'settlement', 'ledger', 'recurring']);

test('generated obligation transactions reject generic updates deletion refunds and restores', function (string $source) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    if ($source === 'manual') {
        $account = createTestAccount($user);
        $item = CreditDebtItem::factory()->forAccount($account)->create();
        $transaction = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '20.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id])->transaction;
    } else {
        $transaction = integrationSettlement($user, integrationCounterparty($user))->transaction;
    }
    $service = app(TransactionMutationService::class);
    expect(fn () => $service->update($user, $transaction, []))->toThrow(ValidationException::class)
        ->and(fn () => $service->destroy($user, $transaction))->toThrow(ValidationException::class)
        ->and(fn () => app(TransactionRefundService::class)->refund($transaction))->toThrow(ValidationException::class)
        ->and(fn () => $service->restore($user, $transaction))->toThrow(ValidationException::class);
    $this->actingAs($user)->deleteJson(route('transactions.destroy', ['year' => $transaction->transaction_date->year, 'month' => $transaction->transaction_date->month, 'transaction' => $transaction->uuid]))->assertUnprocessable();
    expect($transaction->fresh()->amount)->toBe('20.00')->and($transaction->fresh()->trashed())->toBeFalse();
})->with(['manual', 'settlement']);

test('settlement effects ignore invalid bank transactions and recognize partial commercial refunds', function (string $state, int $expected) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    $settlement = integrationSettlement($user, $counterparty);
    $transaction = $settlement->transaction;
    if ($state === 'deleted') {
        $transaction->delete();
    } elseif ($state === 'pending') {
        $transaction->update(['status' => TransactionStatusEnum::DRAFT]);
    } elseif ($state === 'modified') {
        $transaction->update(['amount' => '10.00']);
    } elseif ($state === 'currency') {
        $transaction->update(['currency' => 'USD']);
    } elseif ($state === 'direction') {
        $transaction->update(['direction' => TransactionDirectionEnum::EXPENSE]);
    } else {
        userTransaction($user, $transaction->account, ['amount' => '10.00', 'refunded_transaction_id' => $transaction->id, 'direction' => 'expense', 'kind' => 'refund']);
    }
    expect(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe($expected);
    $this->actingAs($user)->get(route('shared-expenses.index', ['year' => now()->year, 'month' => now()->month]))->assertInertia(fn (Assert $page) => $page
        ->where('entries', function ($entries) use ($settlement, $expected): bool {
            $row = collect($entries)->firstWhere('uuid', $settlement->uuid);
            expect($row['effect_cents'])->toBe($expected - 5000);
            if ($expected === 5000) {
                expect($row['status'])->toBe(__('transactions.enums.status.review_needed'));
            }

            return true;
        }));
})->with(['pending' => ['pending', 5000], 'deleted' => ['deleted', 5000], 'modified' => ['modified', 5000], 'currency' => ['currency', 5000], 'direction' => ['direction', 5000], 'partial refund' => ['refund', 4000]]);

test('settlement updates reject an inverted balance and incompatible account currency', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    $settlement = integrationSettlement($user, $counterparty);
    $service = app(SharedExpenseSettlementService::class);
    $usd = createTestAccount($user, ['currency' => 'USD', 'currency_code' => 'USD']);
    $data = ['amount' => '10.00', 'settled_at' => today()->toDateString(), 'account_uuid' => $usd->uuid, 'reference' => null];
    expect(fn () => $service->record($user, $counterparty, $data))->toThrow(ValidationException::class)
        ->and(fn () => $service->update($user, $settlement, $data))->toThrow(ValidationException::class);
    SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $counterparty->id, 'entry_date' => today(), 'title' => 'Anticipo altrui', 'currency' => 'EUR', 'total_cents' => 10000, 'paid_by' => 'counterparty', 'my_share_cents' => 10000, 'counterparty_share_cents' => 0, 'status' => 'recorded']);
    $data['account_uuid'] = $settlement->transaction->account->uuid;
    expect(fn () => $service->update($user, $settlement, $data))->toThrow(ValidationException::class)
        ->and($settlement->fresh()->amount_cents)->toBe(2000)->and($service->balanceCents($user, $counterparty))->toBe(-7000);
    $service->delete($user, $settlement);
    expect($service->balanceCents($user, $counterparty))->toBe(-5000);
});

test('cent arithmetic remains exact for odd shares large amounts and signed residuals', function () {
    expect(MoneyAmount::cents('999999999999.99'))->toBe(99999999999999)
        ->and(MoneyAmount::decimal(-20001))->toBe('-200.01')
        ->and(MoneyAmount::proportional(99999999999999, 99999999999999, 99999999999999))->toBe(99999999999999)
        ->and(MoneyAmount::proportional(-1, 1, 2))->toBe(-1)
        ->and(MoneyAmount::proportional(1, 1, 3))->toBe(0);
});

test('shared transaction currencies are presented separately and cannot be settled as EUR', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    integrationSharedExpense($user, $counterparty);
    $usd = createTestAccount($user, ['currency' => 'USD', 'currency_code' => 'USD']);
    userTransaction($user, $usd, ['amount' => '50.00', 'shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 2500, 'shared_expense_counterparty_share_cents' => 2500]);
    CreditDebtItem::factory()->forAccount($usd)->create(['counterparty_id' => $counterparty->id, 'total_amount' => '200.00']);
    $service = app(CounterpartyBalanceService::class);
    expect($service->balance($user, $counterparty, 'EUR')['total_cents'])->toBe(5000)
        ->and($service->balance($user, $counterparty, 'USD'))->toBe(['currency' => 'USD', 'manual_cents' => 20000, 'shared_cents' => 2500, 'total_cents' => 22500]);
    $this->actingAs($user)->get(route('credits-debts.index'))->assertInertia(fn (Assert $page) => $page
        ->where('summary.credits_remaining_total', '50.00')->where('summariesByCurrency.USD.credits_remaining_total', '225.00')->has('items', 3));
    $this->get(route('shared-expenses.index', ['year' => now()->year, 'month' => now()->month]))->assertInertia(fn (Assert $page) => $page
        ->where("balances.{$counterparty->uuid}", 5000)->where("counterpartyBalances.{$counterparty->uuid}.1.total_cents", 22500));
});

test('counterparty-only manual balances keep their shared card visible without shared movements', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    CreditDebtItem::factory()->create(['user_id' => $user->id, 'counterparty_id' => $counterparty->id, 'total_amount' => '200.00']);
    $this->actingAs($user)->get(route('shared-expenses.index', ['year' => now()->year, 'month' => now()->month]))->assertInertia(fn (Assert $page) => $page
        ->has('entries', 0)->has('visibleCounterparties', 1)->where("balances.{$counterparty->uuid}", 0)->where("counterpartyBalances.{$counterparty->uuid}.0.total_cents", 20000));
});

test('transaction UI hides generic actions for manual payments and shared settlements', function (string $source) {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    if ($source === 'settlement') {
        $transaction = integrationSettlement($user, integrationCounterparty($user))->transaction;
    } else {
        $account = createTestAccount($user);
        $item = CreditDebtItem::factory()->forAccount($account)->create();
        $transaction = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '20.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id])->transaction;
    }
    $sheet = app(MonthlyTransactionSheetService::class)->build($user, now()->year, now()->month);
    $rows = collect($sheet['transactions']);
    $row = $rows->firstWhere('uuid', $transaction->uuid);
    expect($row)->not->toBeNull()->and($row['can_edit'])->toBeFalse()->and($row['can_delete'])->toBeFalse()->and($row['can_refund'])->toBeFalse();
})->with(['manual', 'settlement']);

test('settlements retain their meaning on an editable account owned by another user', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $owner = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    integrationSharedExpense($user, $counterparty);
    $account = createTestAccount($owner);
    AccountMembership::query()->create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => 'editor', 'status' => 'active', 'granted_by_user_id' => $owner->id, 'source' => 'direct', 'joined_at' => now()]);
    $service = app(SharedExpenseSettlementService::class);
    $data = ['amount' => '20.00', 'settled_at' => today()->toDateString(), 'account_uuid' => $account->uuid, 'reference' => null];
    $settlement = $service->record($user, $counterparty, $data);
    expect($settlement->transaction->user_id)->toBe($owner->id)->and($service->balanceCents($user, $counterparty))->toBe(3000);
    $service->update($user, $settlement, [...$data, 'amount' => '10.00']);
    expect($service->balanceCents($user, $counterparty))->toBe(4000);
    $personalAccount = createTestAccount($user);
    $moved = $service->update($user, $settlement, [...$data, 'amount' => '10.00', 'account_uuid' => $personalAccount->uuid]);
    expect($moved->transaction->user_id)->toBe($user->id)->and($service->balanceCents($user, $counterparty))->toBe(4000);
    $service->delete($user, $settlement);
    expect($service->balanceCents($user, $counterparty))->toBe(5000);
});

test('legacy settlements with an already deleted transaction can be removed without altering the balance', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = integrationCounterparty($user);
    $settlement = integrationSettlement($user, $counterparty);
    $settlement->transaction->delete();
    $service = app(SharedExpenseSettlementService::class);
    expect($service->balanceCents($user, $counterparty))->toBe(5000);
    $service->delete($user, $settlement);
    expect($service->balanceCents($user, $counterparty))->toBe(5000)->and(SharedExpenseSettlement::find($settlement->id))->toBeNull();
});
