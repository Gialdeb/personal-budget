<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

function counterpartyTransactionFixture(): array
{
    $user = User::factory()->create();
    $account = createTestAccount($user, [
        'opening_balance' => 1000,
        'current_balance' => 1000,
        'opening_balance_date' => '2026-01-01',
    ]);
    UserYear::query()->create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);
    $category = Category::query()->create([
        'user_id' => $user->id, 'name' => 'Luce', 'slug' => 'luce-'.$user->id,
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true, 'is_selectable' => true,
    ]);
    $counterparty = TrackedItem::query()->create([
        'user_id' => $user->id, 'name' => 'Mario Rossi', 'slug' => 'mario-rossi',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    $payload = [
        'transaction_day' => 7, 'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
        'account_uuid' => $account->uuid, 'category_uuid' => $category->uuid,
        'amount' => '100.00', 'description' => 'Spesa anticipata Mario Rossi',
        'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $counterparty->uuid,
        'shared_expense_paid_by' => 'counterparty', 'shared_expense_my_share' => '50.00',
        'shared_expense_counterparty_share' => '50.00',
    ];

    return [$user, $account, $counterparty, $payload];
}

test('a counterparty ledger movement reopens in the transaction form and updates in place', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $this->actingAs($user)->post(route('transactions.store', ['year' => 2026, 'month' => 9]), $payload)->assertSessionHasNoErrors();
    $entry = SharedExpenseLedgerEntry::query()->sole();
    $otherCounterparty = $counterparty->replicate(['uuid']);
    $otherCounterparty->name = 'Emanuela';
    $otherCounterparty->slug = 'emanuela';
    $otherCounterparty->save();
    $payload = array_replace($payload, [
        'amount' => '120.00', 'shared_expense_my_share' => '30.00', 'shared_expense_counterparty_share' => '90.00',
        'shared_expense_counterparty_uuid' => $otherCounterparty->uuid, 'notes' => 'Nota modificata',
        'description' => 'Titolo modificato', 'transaction_day' => 8,
    ]);
    $this->patch(route('transactions.shared-ledger.update', ['year' => 2026, 'month' => 9, 'ledger' => $entry->uuid]), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(SharedExpenseLedgerEntry::query()->count())->toBe(1)
        ->and($entry->fresh()->uuid)->toBe($entry->uuid)
        ->and($entry->fresh()->counterparty_id)->toBe($otherCounterparty->id)
        ->and($entry->fresh()->total_cents)->toBe(12000)
        ->and(Transaction::withTrashed()->count())->toBe(0)
        ->and($account->fresh()->current_balance)->toEqual(1000)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(0)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $otherCounterparty))->toBe(-3000);
    $sheet = app(MonthlyTransactionSheetService::class)->build($user, 2026, 9);
    expect($sheet['shared_ledger_movements'][0]['uuid'])->toBe($entry->uuid)
        ->and($sheet['shared_ledger_movements'][0]['can_edit'])->toBeTrue()
        ->and($sheet['shared_ledger_movements'][0]['is_shared_ledger'])->toBeTrue()
        ->and($sheet['shared_ledger_movements'][0]['notes'])->toBe('Nota modificata')
        ->and($sheet['shared_ledger_movements'][0]['date'])->toBe('2026-09-08')
        ->and($sheet['shared_ledger_movements'][0]['category_uuid'])->toBe($payload['category_uuid'])
        ->and($sheet['totals']['actual_expense_raw'])->toEqual(0);
    $this->get(route('transactions.show', ['year' => 2026, 'month' => 9]))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('transactions/Show')
            ->where('monthlySheet.shared_ledger_movements.0.uuid', $entry->uuid)
            ->where('monthlySheet.shared_ledger_movements.0.is_shared_ledger', true)
            ->where('monthlySheet.shared_ledger_movements.0.can_edit', true));
});

test('editing a counterparty ledger to self payment creates exactly one real transaction', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $this->actingAs($user)->post(route('transactions.store', ['year' => 2026, 'month' => 9]), $payload)->assertSessionHasNoErrors();
    $entry = SharedExpenseLedgerEntry::query()->sole();
    $payload['shared_expense_paid_by'] = 'self';
    $this->patch(route('transactions.shared-ledger.update', ['year' => 2026, 'month' => 9, 'ledger' => $entry->uuid]), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(SharedExpenseLedgerEntry::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(1)
        ->and(Transaction::query()->sole()->shared_expense_paid_by)->toBe('self')
        ->and($account->fresh()->current_balance)->toEqual(900)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(5000);
});

test('ledger edits reject invalid shares and foreign owners without side effects', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $this->actingAs($user)->post(route('transactions.store', ['year' => 2026, 'month' => 9]), $payload)->assertSessionHasNoErrors();
    $entry = SharedExpenseLedgerEntry::query()->sole();
    $this->patch(route('transactions.shared-ledger.update', ['year' => 2026, 'month' => 9, 'ledger' => $entry->uuid]), array_replace($payload, ['shared_expense_my_share' => '80.00']))->assertSessionHasErrors();
    [$otherUser, $otherAccount, $otherCounterparty, $otherPayload] = counterpartyTransactionFixture();
    $this->actingAs($otherUser)->patch(route('transactions.shared-ledger.update', ['year' => 2026, 'month' => 9, 'ledger' => $entry->uuid]), $otherPayload)->assertNotFound();
    expect($entry->fresh()->my_share_cents)->toBe(5000)
        ->and(SharedExpenseLedgerEntry::query()->count())->toBe(1)->and(Transaction::query()->count())->toBe(0);
});

test('ledger conversion rolls back a created transaction when removing ledger fails', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withoutExceptionHandling();
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $this->actingAs($user)->post(route('transactions.store', ['year' => 2026, 'month' => 9]), $payload)->assertSessionHasNoErrors();
    $entry = SharedExpenseLedgerEntry::query()->sole();
    $dispatcher = SharedExpenseLedgerEntry::getEventDispatcher();
    SharedExpenseLedgerEntry::setEventDispatcher(clone $dispatcher);
    SharedExpenseLedgerEntry::deleting(function (): void {
        throw new RuntimeException('Ledger deletion failed');
    });
    try {
        $this->patch(route('transactions.shared-ledger.update', ['year' => 2026, 'month' => 9, 'ledger' => $entry->uuid]), array_replace($payload, ['shared_expense_paid_by' => 'self']));
        $this->fail('Conversion should fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Ledger deletion failed');
    } finally {
        SharedExpenseLedgerEntry::setEventDispatcher($dispatcher);
    }
    $this->assertModelExists($entry);
    expect(Transaction::withTrashed()->count())->toBe(0)->and($account->fresh()->current_balance)->toEqual(1000);
});

test('transaction form records counterparty payment only in the shared ledger', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $before = app(MonthlyTransactionSheetService::class)->build($user, 2026, 9);

    $this->actingAs($user)->post(route('transactions.store', ['year' => 2026, 'month' => 9]), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(Transaction::withTrashed()->count())->toBe(0)
        ->and($account->fresh()->current_balance)->toEqual(1000)
        ->and(SharedExpenseLedgerEntry::query()->sole()->total_cents)->toBe(10000)
        ->and(SharedExpenseLedgerEntry::query()->sole()->my_share_cents)->toBe(5000)
        ->and(SharedExpenseLedgerEntry::query()->sole()->counterparty_share_cents)->toBe(5000)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(-5000);
    $after = app(MonthlyTransactionSheetService::class)->build($user, 2026, 9);
    expect($after['totals'])->toBe($before['totals'])
        ->and($after['meta']['transactions_count'])->toBe(0);

    $this->get('/shared-expenses?year=2026&month=9')->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('SharedExpenses')
            ->has('entries', 1)->where('entries.0.title', $payload['description'])
            ->where('entries.0.counterparty', 'Mario Rossi')->where('entries.0.effect_cents', -5000));
});

test('transaction form keeps the real transaction when I pay', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $payload['shared_expense_paid_by'] = 'self';
    $this->actingAs($user)->post(route('transactions.store', ['year' => 2026, 'month' => 9]), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(Transaction::query()->sole()->shared_expense_counterparty_id)->toBe($counterparty->id)
        ->and(SharedExpenseLedgerEntry::query()->count())->toBe(0)
        ->and($account->fresh()->current_balance)->toEqual(900)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(5000);
});

test('editing a shared transaction to counterparty payment restores the account and replaces it with ledger', function (string $originalPayer) {
    $this->withoutMiddleware(PreventRequestForgery::class);
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $transaction = userTransaction($user, $account, [
        'transaction_date' => '2026-09-07', 'amount' => '100.00',
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => $originalPayer,
        'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000,
    ]);
    app(TransactionMutationService::class)->recalculateAccount($account);
    expect($account->fresh()->current_balance)->toEqual(900);
    $this->actingAs($user)->patch(route('transactions.update', ['year' => 2026, 'month' => 9, 'transaction' => $transaction->uuid]), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(Transaction::withTrashed()->count())->toBe(0)
        ->and(SharedExpenseLedgerEntry::query()->count())->toBe(1)
        ->and($account->fresh()->current_balance)->toEqual(1000)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(-5000)
        ->and(app(MonthlyTransactionSheetService::class)->build($user, 2026, 9)['totals']['actual_expense_raw'])->toEqual(0);
})->with(['self', 'counterparty']);

test('counterparty transaction rejects invalid shares without creating accounting records', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $payload['shared_expense_counterparty_share'] = '70.00';
    $this->actingAs($user)->post(route('transactions.store', ['year' => 2026, 'month' => 9]), $payload)
        ->assertSessionHasErrors();
    expect(Transaction::query()->count())->toBe(0)->and(SharedExpenseLedgerEntry::query()->count())->toBe(0)
        ->and($account->fresh()->current_balance)->toEqual(1000);
});

test('counterparty transaction refuses another users counterparty', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $counterparty->update(['user_id' => User::factory()->create()->id]);
    $this->actingAs($user)->post(route('transactions.store', ['year' => 2026, 'month' => 9]), $payload)
        ->assertSessionHasErrors('shared_expense_counterparty_uuid');
    expect(Transaction::query()->count())->toBe(0)->and(SharedExpenseLedgerEntry::query()->count())->toBe(0);
});

test('counterparty conversion rolls back ledger and account changes if transaction deletion fails', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withoutExceptionHandling();
    [$user, $account, $counterparty, $payload] = counterpartyTransactionFixture();
    $transaction = userTransaction($user, $account, [
        'transaction_date' => '2026-09-07', 'amount' => '100.00',
        'shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => 'self',
        'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000,
    ]);
    app(TransactionMutationService::class)->recalculateAccount($account);
    $dispatcher = Transaction::getEventDispatcher();
    Transaction::setEventDispatcher(clone $dispatcher);
    Transaction::deleting(function (): void {
        throw new RuntimeException('Simulated deletion failure');
    });
    try {
        $this->actingAs($user)->patch(route('transactions.update', ['year' => 2026, 'month' => 9, 'transaction' => $transaction->uuid]), $payload);
        $this->fail('Conversion should have failed.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated deletion failure');
    } finally {
        Transaction::setEventDispatcher($dispatcher);
    }
    $this->assertModelExists($transaction);
    expect(SharedExpenseLedgerEntry::query()->count())->toBe(0)
        ->and($account->fresh()->current_balance)->toEqual(900)
        ->and($transaction->fresh()->shared_expense_paid_by)->toBe('self');
});

test('shared expense foundations persist counterparties independently from references', function () {
    $user = User::factory()->create();

    $counterparty = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Emanuela',
        'slug' => 'emanuela',
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);

    expect(Schema::hasColumns('transactions', ['shared_expense_counterparty_id']))->toBeTrue()
        ->and(Schema::hasColumns('recurring_entries', ['shared_expense_counterparty_id']))->toBeTrue()
        ->and($counterparty->sharedExpenseTransactions())->toBeInstanceOf(HasMany::class);
});

test('an authenticated user can create a personal shared expense counterparty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/shared-expenses/counterparties', ['name' => 'Emanuela'])
        ->assertCreated()
        ->assertJsonPath('name', 'Emanuela');

    expect(TrackedItem::query()
        ->where('user_id', $user->id)
        ->where('is_shared_expense_counterpart', true)
        ->where('name', 'Emanuela')
        ->exists())->toBeTrue();
});

test('counterparty creation reuses a reference having the same name', function () {
    $user = User::factory()->create();
    $reference = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Emanuela',
        'slug' => 'emanuela',
        'type' => 'person',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/shared-expenses/counterparties', ['name' => 'Emanuela'])
        ->assertCreated()
        ->assertJsonPath('uuid', $reference->uuid)
        ->assertJsonPath('name', 'Emanuela');

    expect($reference->fresh())
        ->is_shared_expense_counterpart->toBeTrue()
        ->type->toBe('person')
        ->and(TrackedItem::query()->ownedBy($user->id)->where('name', 'Emanuela')->count())->toBe(1);
});

test('counterparty creation reuses equivalent names without creating duplicates', function () {
    $user = User::factory()->create();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Emanuela Rossi',
        'slug' => 'emanuela-rossi',
        'type' => 'person',
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/shared-expenses/counterparties', [
            'name' => '  emanuela   rossi  ',
        ])
        ->assertCreated()
        ->assertJsonPath('uuid', $counterparty->uuid)
        ->assertJsonPath('name', 'Emanuela Rossi');

    expect(TrackedItem::query()
        ->ownedBy($user->id)
        ->where('is_shared_expense_counterpart', true)
        ->count())->toBe(1)
        ->and($counterparty->fresh()->type)->toBe('person');
});

test('a shared transaction resolves an active personal shared counterparty by uuid', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    $account = createTestAccount($user, [
        'opening_balance' => 100,
        'current_balance' => 100,
        'opening_balance_date' => '2026-01-01',
    ]);
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Ristoranti',
        'slug' => 'ristoranti-shared-'.$user->id,
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $counterparty = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Emanuela',
        'slug' => 'shared-expense-emanuela-'.$user->id,
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store', ['year' => 2026, 'month' => 3]), [
            'transaction_day' => 12,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $account->uuid,
            'category_uuid' => $category->uuid,
            'amount' => '20.00',
            'description' => 'Cena',
            'is_shared_expense' => true,
            'shared_expense_counterparty_uuid' => $counterparty->uuid,
            'shared_expense_paid_by' => 'self',
            'shared_expense_my_share' => '10.00',
            'shared_expense_counterparty_share' => '10.00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Transaction::query()->sole()->shared_expense_counterparty_id)
        ->toBe($counterparty->id);
});

test('counterparty creation reactivates an inactive personal counterparty', function () {
    $user = User::factory()->create();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Emanuela',
        'slug' => 'shared-expense-emanuela',
        'is_active' => false,
        'is_shared_expense_counterpart' => true,
    ]);

    $this->actingAs($user)
        ->postJson('/shared-expenses/counterparties', ['name' => 'Emanuela'])
        ->assertCreated()
        ->assertJsonPath('uuid', $counterparty->uuid)
        ->assertJsonPath('name', 'Emanuela');

    expect($counterparty->fresh()->is_active)->toBeTrue();
});

test('the transaction form receives only the authenticated users active counterparties ordered by name', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $alpha = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Alessia',
        'slug' => 'alessia',
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);
    $zeta = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Zeno',
        'slug' => 'zeno',
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);
    TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Inattiva',
        'slug' => 'inattiva',
        'is_active' => false,
        'is_shared_expense_counterpart' => true,
    ]);
    TrackedItem::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Altra utente',
        'slug' => 'altra-utente',
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);

    $sheet = app(MonthlyTransactionSheetService::class)->build($user, 2026, 1);

    expect($sheet['editor']['shared_expense_counterparties'])->toBe([
        ['value' => $alpha->uuid, 'label' => 'Alessia'],
        ['value' => $zeta->uuid, 'label' => 'Zeno'],
    ]);
});
