<?php

use App\Models\TrackedItem;
use App\Models\User;
use App\Models\UserYear;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('reimbursement form receives the existing reference catalog and category appearances', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $this->actingAs($user)->get(route('shared-expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('settlementOptions.categories.'.$account->uuid)
            ->has('settlementOptions.references.'.$account->uuid));
});

test('transaction and recurring forms expose active shared expense counterparties in name order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);
    createTestAccount($user);

    $emanuela = sharedExpenseFormCounterparty($user, 'Emanuela');
    $alice = sharedExpenseFormCounterparty($user, 'Alice');
    sharedExpenseFormCounterparty($user, 'Disattivata', false);
    sharedExpenseFormCounterparty($otherUser, 'Non mia');

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2026, 'month' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.shared_expense_counterparties', [
                ['value' => $alice->uuid, 'label' => 'Alice'],
                ['value' => $emanuela->uuid, 'label' => 'Emanuela'],
            ]));

    $this->actingAs($user)
        ->get(route('recurring-entries.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formOptions.shared_expense_counterparties', [
                ['value' => $alice->uuid, 'label' => 'Alice'],
                ['value' => $emanuela->uuid, 'label' => 'Emanuela'],
            ]));
});

function sharedExpenseFormCounterparty(User $user, string $name, bool $isActive = true): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::uuid(),
        'is_active' => $isActive,
        'is_shared_expense_counterpart' => true,
    ]);
}
