<?php

use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('shared register paginates the complete filtered set and preserves balances and filters', function () {
    $user = User::factory()->create();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $user->id, 'name' => 'Mario', 'slug' => 'mario',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    for ($index = 1; $index <= 25; $index++) {
        SharedExpenseLedgerEntry::query()->create([
            'user_id' => $user->id, 'counterparty_id' => $counterparty->id,
            'entry_date' => '2026-09-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'title' => 'Cena '.$index, 'total_cents' => 2000, 'currency' => 'EUR',
            'status' => 'posted', 'paid_by' => 'counterparty', 'my_share_cents' => 1000, 'counterparty_share_cents' => 1000,
        ]);
    }
    $filters = ['year' => 2026, 'month' => 9, 'counterparty' => $counterparty->uuid, 'search' => 'Cena', 'effect' => 'payable', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];
    $this->actingAs($user)->get(route('shared-expenses.index', $filters))
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('entries', 20)->where('entries.0.title', 'Cena 25')
        ->where('pagination.total', 25)->where('pagination.last_page', 2)
        ->where('counterpartyEntryCounts.'.$counterparty->uuid, 25)
        ->where('balances.'.$counterparty->uuid, -25000)
        ->where('pagination.next_page_url', function (string $url) use ($filters): bool {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            expect($query)->toEqual([...$filters, 'page' => 2]);

            return true;
        }));
    $this->get(route('shared-expenses.index', [...$filters, 'page' => 2]))
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('entries', 5)->where('entries.0.title', 'Cena 5')
        ->where('pagination.current_page', 2)->where('pagination.total', 25)
        ->where('counterpartyEntryCounts.'.$counterparty->uuid, 25)
        ->where('balances.'.$counterparty->uuid, -25000));
    $this->get(route('shared-expenses.index', [...$filters, 'search' => 'missing', 'page' => 2]))
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('entries', 0)->where('pagination.current_page', 1));
});

test('counterparty cards hide only zero balances without filtered activity', function () {
    $user = User::factory()->create();
    $items = collect(['Unused', 'Open', 'Active'])->mapWithKeys(function (string $name) use ($user): array {
        return [$name => TrackedItem::query()->create([
            'user_id' => $user->id, 'name' => $name, 'slug' => strtolower($name),
            'is_active' => true, 'is_shared_expense_counterpart' => true,
        ])];
    });
    foreach (['Open' => '2026-08-01', 'Active' => '2026-09-01'] as $name => $date) {
        SharedExpenseLedgerEntry::query()->create([
            'user_id' => $user->id, 'counterparty_id' => $items[$name]->id,
            'entry_date' => $date, 'title' => $name, 'total_cents' => 1000, 'currency' => 'EUR',
            'status' => 'posted', 'paid_by' => 'counterparty', 'my_share_cents' => $name === 'Open' ? 500 : 0,
            'counterparty_share_cents' => $name === 'Open' ? 500 : 1000,
        ]);
    }
    $this->actingAs($user)->get(route('shared-expenses.index', ['year' => 2026, 'month' => 9]))
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('visibleCounterparties', 2)->where('visibleCounterparties.0.name', 'Active')
        ->where('visibleCounterparties.1.name', 'Open')
        ->where('balances.'.$items['Active']->uuid, 0)
        ->where('balances.'.$items['Open']->uuid, -500)
        ->where('counterpartyEntryCounts.'.$items['Active']->uuid, 1)
        ->has('counterparties', 3));
});

test('pagination merges transactions ledger entries and repayments before slicing by date', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $counterparty = TrackedItem::query()->create([
        'user_id' => $user->id, 'name' => 'Mario', 'slug' => 'mario',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    for ($index = 1; $index <= 19; $index++) {
        SharedExpenseLedgerEntry::query()->create([
            'user_id' => $user->id, 'counterparty_id' => $counterparty->id,
            'entry_date' => '2026-09-10', 'title' => 'Ledger '.$index,
            'total_cents' => 1000, 'currency' => 'EUR', 'status' => 'posted',
            'paid_by' => 'counterparty', 'my_share_cents' => 500, 'counterparty_share_cents' => 500,
        ]);
    }
    $transaction = userTransaction($user, $account, [
        'transaction_date' => '2026-09-20', 'amount' => 10,
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 500,
        'shared_expense_counterparty_share_cents' => 500,
    ]);
    $repaymentTransaction = userTransaction($user, $account, ['transaction_date' => '2026-09-01', 'amount' => 5]);
    $repayment = SharedExpenseSettlement::query()->create([
        'transaction_id' => $repaymentTransaction->id,
        'user_id' => $user->id, 'counterparty_id' => $counterparty->id,
        'settled_at' => '2026-09-01', 'amount_cents' => 500, 'direction' => 'me_to_counterparty',
    ]);
    $this->actingAs($user)->get(route('shared-expenses.index', ['year' => 2026, 'month' => 9, 'effect' => 'all']))
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('entries', 20)->where('entries.0.uuid', $transaction->uuid)
        ->where('pagination.total', 21));
    $this->get(route('shared-expenses.index', ['year' => 2026, 'month' => 9, 'effect' => 'all', 'page' => 2]))
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('entries', 1)->where('entries.0.uuid', $repayment->uuid)
        ->where('entries.0.is_settlement', true)->where('pagination.total', 21));
});
