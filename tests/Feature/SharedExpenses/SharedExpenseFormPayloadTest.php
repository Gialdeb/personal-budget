<?php

use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\User;
use App\Models\UserYear;
use App\Services\CreditDebts\DerivedObligationsService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
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

test('transaction and recurring forms expose all active personal references as shared choices in name order', function () {
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
    $amazon = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Amazon', 'slug' => 'amazon', 'is_active' => true, 'is_shared_expense_counterpart' => false]);
    $legacy = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Legacy disabilitata', 'slug' => 'legacy', 'type' => 'shared_expense_counterparty', 'is_active' => true, 'is_shared_expense_counterpart' => false]);

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2026, 'month' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.tracked_items', fn ($options): bool => collect($options)->contains('value', $amazon->uuid))
            ->where('monthlySheet.editor.shared_expense_counterparties', [
                ['value' => $alice->uuid, 'label' => 'Alice'],
                ['value' => $amazon->uuid, 'label' => 'Amazon'],
                ['value' => $emanuela->uuid, 'label' => 'Emanuela'],
                ['value' => $legacy->uuid, 'label' => 'Legacy disabilitata'],
            ]));

    $this->actingAs($user)
        ->get(route('recurring-entries.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formOptions.shared_expense_counterparties', [
                ['value' => $alice->uuid, 'label' => 'Alice'],
                ['value' => $amazon->uuid, 'label' => 'Amazon'],
                ['value' => $emanuela->uuid, 'label' => 'Emanuela'],
                ['value' => $legacy->uuid, 'label' => 'Legacy disabilitata'],
            ]));
});

test('disabling eligibility hides new shared choices but preserves ledger history and obligations', function () {
    $user = User::factory()->create();
    $mario = sharedExpenseFormCounterparty($user, 'Mario');
    $ledger = SharedExpenseLedgerEntry::query()->create([
        'user_id' => $user->id, 'counterparty_id' => $mario->id, 'entry_date' => now()->toDateString(),
        'title' => 'Cena', 'currency' => 'EUR', 'total_cents' => 3000, 'paid_by' => 'counterparty',
        'my_share_cents' => 1500, 'counterparty_share_cents' => 1500, 'status' => 'recorded',
    ]);
    $mario->update(['is_shared_expense_counterpart' => false]);
    expect(TrackedItem::query()->sharedExpenseCounterparties($user->id)->count())->toBe(0);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page
        ->has('counterparties', 0)->where('balances.'.$mario->uuid, -1500)
        ->where('historicalCounterparties.0.uuid', $mario->uuid)
        ->where('entries.0.counterparty_uuid', $mario->uuid));
    expect($ledger->fresh()->counterparty_id)->toBe($mario->id)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $mario))->toBe(-1500);
    $position = app(DerivedObligationsService::class)->openPositions($user)->sole();
    expect($position['origin'])->toBe('shared')
        ->and($position['remaining_amount'])->toBe('15.00');
});

test('settlement rejects normal inactive and foreign references as new counterparties', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $normal = sharedExpenseFormCounterparty($user, 'Amazon');
    $normal->update(['is_shared_expense_counterpart' => false, 'type' => 'shared_expense_counterparty']);
    $inactive = sharedExpenseFormCounterparty($user, 'Inactive', false);
    $foreign = sharedExpenseFormCounterparty(User::factory()->create(), 'Foreign');
    foreach ([$normal, $inactive, $foreign] as $reference) {
        $this->actingAs($user)->post(route('shared-expenses.settlements.store', $reference->uuid), [
            'amount' => '10.00', 'settled_at' => now()->toDateString(), 'account_uuid' => $account->uuid,
            'category_uuid' => null, 'reference' => null, 'notes' => null,
        ])->assertNotFound();
    }
    expect(SharedExpenseSettlement::query()->count())->toBe(0);
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
