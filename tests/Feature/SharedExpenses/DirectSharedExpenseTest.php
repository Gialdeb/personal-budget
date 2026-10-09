<?php

use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SharedExpenses\DirectSharedExpenseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

function directSharedCounterparty(User $user): TrackedItem
{
    return TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => 'emanuela', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
}

test('a direct shared expense paid by me creates a transaction', function () {
    $user = User::factory()->create();
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = directSharedCounterparty($user);
    app(DirectSharedExpenseService::class)->create($user, ['title' => 'Cena', 'entry_date' => now()->toDateString(), 'amount' => '100.00', 'counterparty_uuid' => $counterparty->uuid, 'paid_by' => 'self', 'my_share' => null, 'counterparty_share' => null, 'account_uuid' => $account->uuid, 'category_uuid' => null, 'reference_uuid' => null, 'notes' => null]);
    expect(Transaction::query()->count())->toBe(1)->and(Transaction::query()->first()->shared_expense_counterparty_id)->toBe($counterparty->id);
});

test('a direct shared expense paid by counterparty creates only ledger entry', function () {
    $user = User::factory()->create();
    $counterparty = directSharedCounterparty($user);
    app(DirectSharedExpenseService::class)->create($user, ['title' => 'Cena', 'entry_date' => now()->toDateString(), 'amount' => '100.00', 'counterparty_uuid' => $counterparty->uuid, 'paid_by' => 'counterparty', 'my_share' => null, 'counterparty_share' => null, 'account_uuid' => null, 'category_uuid' => null, 'reference_uuid' => null, 'notes' => null]);
    expect(SharedExpenseLedgerEntry::query()->count())->toBe(1)->and(Transaction::query()->count())->toBe(0);
});

test('direct shared expense validates custom allocations', function () {
    $user = User::factory()->create();
    $counterparty = directSharedCounterparty($user);
    app(DirectSharedExpenseService::class)->create($user, ['title' => 'Cena', 'entry_date' => now()->toDateString(), 'amount' => '100.00', 'counterparty_uuid' => $counterparty->uuid, 'paid_by' => 'counterparty', 'my_share' => '30.00', 'counterparty_share' => '80.00', 'account_uuid' => null, 'category_uuid' => null, 'reference_uuid' => null, 'notes' => null]);
})->throws(ValidationException::class);

test('direct shared expense is isolated to its owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $counterparty = directSharedCounterparty($owner);
    app(DirectSharedExpenseService::class)->create($other, ['title' => 'Cena', 'entry_date' => now()->toDateString(), 'amount' => '100.00', 'counterparty_uuid' => $counterparty->uuid, 'paid_by' => 'counterparty', 'my_share' => null, 'counterparty_share' => null, 'account_uuid' => null, 'category_uuid' => null, 'reference_uuid' => null, 'notes' => null]);
})->throws(ModelNotFoundException::class);
