<?php

use App\Enums\TransactionDirectionEnum;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use Illuminate\Validation\ValidationException;

function sharedExpenseCounterparty(User $user, string $name = 'Emanuela'): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => $name,
        'slug' => str($name)->slug(),
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);
}

test('a partial reimbursement creates a real incoming transaction and leaves the shared balance open', function () {
    $user = User::factory()->create();
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = sharedExpenseCounterparty($user);
    userTransaction($user, $account, [
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'self',
        'shared_expense_my_share_cents' => 0,
        'shared_expense_counterparty_share_cents' => 15000,
        'amount' => '150.00',
    ]);

    $settlement = app(SharedExpenseSettlementService::class)->record($user, $counterparty, [
        'amount' => '100.00',
        'settled_at' => now()->toDateString(),
        'account_uuid' => $account->uuid,
        'category_uuid' => null,
        'reference' => null,
        'notes' => 'Bonifico ricevuto',
    ]);

    expect($settlement->direction)->toBe(SharedExpenseSettlementService::COUNTERPARTY_TO_ME)
        ->and($settlement->transaction)->not->toBeNull()
        ->and($settlement->transaction->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and($settlement->transaction->shared_expense_counterparty_id)->toBeNull()
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(5000);
});

test('a complete payment creates an outgoing transaction and brings a debit to zero', function () {
    $user = User::factory()->create();
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = sharedExpenseCounterparty($user);
    userTransaction($user, $account, [
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'counterparty',
        'shared_expense_my_share_cents' => 15000,
        'shared_expense_counterparty_share_cents' => 0,
        'amount' => '150.00',
    ]);

    $settlement = app(SharedExpenseSettlementService::class)->record($user, $counterparty, [
        'amount' => '150.00',
        'settled_at' => now()->toDateString(),
        'account_uuid' => $account->uuid,
        'category_uuid' => null,
        'reference' => null,
        'notes' => null,
    ]);

    expect($settlement->direction)->toBe(SharedExpenseSettlementService::ME_TO_COUNTERPARTY)
        ->and($settlement->transaction->direction)->toBe(TransactionDirectionEnum::EXPENSE)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(0);
});

test('a complete reimbursement received brings a credit to zero', function () {
    $user = User::factory()->create();
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = sharedExpenseCounterparty($user);
    userTransaction($user, $account, [
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'self',
        'shared_expense_my_share_cents' => 0,
        'shared_expense_counterparty_share_cents' => 5000,
        'amount' => '50.00',
    ]);

    $settlement = app(SharedExpenseSettlementService::class)->record($user, $counterparty, [
        'amount' => '50.00', 'settled_at' => now()->toDateString(), 'account_uuid' => $account->uuid,
        'category_uuid' => null, 'reference' => null, 'notes' => null,
    ]);

    expect($settlement->transaction->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(0);
});

test('a partial payment leaves a shared debit open', function () {
    $user = User::factory()->create();
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = sharedExpenseCounterparty($user);
    userTransaction($user, $account, [
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'counterparty',
        'shared_expense_my_share_cents' => 15000,
        'shared_expense_counterparty_share_cents' => 0,
        'amount' => '150.00',
    ]);

    $settlement = app(SharedExpenseSettlementService::class)->record($user, $counterparty, [
        'amount' => '100.00', 'settled_at' => now()->toDateString(), 'account_uuid' => $account->uuid,
        'category_uuid' => null, 'reference' => null, 'notes' => null,
    ]);

    expect($settlement->transaction->direction)->toBe(TransactionDirectionEnum::EXPENSE)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $counterparty))->toBe(-5000);
});

test('a reimbursement cannot exceed the remaining balance and leaves no records behind', function () {
    $user = User::factory()->create();
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = sharedExpenseCounterparty($user);
    userTransaction($user, $account, [
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'self',
        'shared_expense_my_share_cents' => 0,
        'shared_expense_counterparty_share_cents' => 5000,
        'amount' => '50.00',
    ]);

    app(SharedExpenseSettlementService::class)->record($user, $counterparty, [
        'amount' => '51.00',
        'settled_at' => now()->toDateString(),
        'account_uuid' => $account->uuid,
        'category_uuid' => null,
        'reference' => null,
        'notes' => null,
    ]);
})->throws(ValidationException::class);

test('a user cannot settle another users counterparty balance', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownerAccount = userAccount($owner, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $otherAccount = userAccount($otherUser, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = sharedExpenseCounterparty($owner);
    userTransaction($owner, $ownerAccount, [
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'self',
        'shared_expense_my_share_cents' => 0,
        'shared_expense_counterparty_share_cents' => 5000,
        'amount' => '50.00',
    ]);

    $this->actingAs($otherUser)
        ->post("/shared-expenses/{$counterparty->uuid}/settlements", [
            'amount' => '50.00', 'settled_at' => now()->toDateString(), 'account_uuid' => $otherAccount->uuid,
        ])
        ->assertNotFound();

    expect(SharedExpenseSettlement::query()->count())->toBe(0);
});

test('a settlement update keeps its transaction and recalculates the balance', function () {
    $user = User::factory()->create();
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $otherAccount = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = sharedExpenseCounterparty($user);
    userTransaction($user, $account, ['shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 0, 'shared_expense_counterparty_share_cents' => 15000, 'amount' => '150.00']);
    $service = app(SharedExpenseSettlementService::class);
    $settlement = $service->record($user, $counterparty, ['amount' => '100.00', 'settled_at' => now()->toDateString(), 'account_uuid' => $account->uuid, 'category_uuid' => null, 'reference' => null, 'notes' => null]);
    $transactionId = $settlement->transaction_id;

    $updated = $service->update($user, $settlement, ['amount' => '50.00', 'settled_at' => now()->subDay()->toDateString(), 'account_uuid' => $otherAccount->uuid, 'category_uuid' => null, 'reference' => 'Bonifico corretto', 'notes' => 'Nota']);

    expect($updated->transaction_id)->toBe($transactionId)
        ->and($updated->transaction->account_id)->toBe($otherAccount->id)
        ->and($updated->transaction->transaction_date->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($service->balanceCents($user, $counterparty))->toBe(10000);
});

test('deleting a settlement deletes only its linked transaction and restores the balance', function () {
    $user = User::factory()->create();
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $counterparty = sharedExpenseCounterparty($user);
    userTransaction($user, $account, ['shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 0, 'shared_expense_counterparty_share_cents' => 10000, 'amount' => '100.00']);
    $service = app(SharedExpenseSettlementService::class);
    $settlement = $service->record($user, $counterparty, ['amount' => '100.00', 'settled_at' => now()->toDateString(), 'account_uuid' => $account->uuid, 'category_uuid' => null, 'reference' => null, 'notes' => null]);
    $unrelated = userTransaction($user, $account, ['amount' => '10.00']);

    $service->delete($user, $settlement);

    expect(SharedExpenseSettlement::query()->count())->toBe(0)
        ->and(Transaction::withTrashed()->find($settlement->transaction_id)->trashed())->toBeTrue()
        ->and(Transaction::find($unrelated->id))->not->toBeNull()
        ->and($service->balanceCents($user, $counterparty))->toBe(10000);
});

afterEach(function () {
    expect(SharedExpenseSettlement::query()->count())->toBeLessThanOrEqual(1);
});
