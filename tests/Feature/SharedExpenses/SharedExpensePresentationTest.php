<?php

use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\TrackedItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => config(['features.credits_debts.enabled' => true, 'counterparty_ledger.shadow_manual_enabled' => false, 'counterparty_ledger.shadow_shared_enabled' => false]));

test('shared cards distinguish manual balances and exclude manual only people', function (bool $samePerson) {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $people = collect(['Mario Rossi', 'Pino Mauro'])->map(fn (string $name) => TrackedItem::create(['user_id' => $user->id, 'name' => $name, 'slug' => str()->slug($name), 'is_active' => true, 'is_shared_expense_counterpart' => true]));
    [$mario, $pino] = $people->all();
    userTransaction($user, $account, ['shared_expense_counterparty_id' => $mario->id, 'amount' => 122, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 6100, 'shared_expense_counterparty_share_cents' => 6100]);
    CreditDebtItem::factory()->forAccount($account)->create(['reference_id' => $samePerson ? $mario->id : $pino->id, 'total_amount' => 280]);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('filters.effect', 'pending')->has('entries', 1)->where('entries.0.display_state', 'receivable')
        ->has('visibleCounterparties', 1)->where('visibleCounterparties.0.uuid', $mario->uuid)
        ->where("balances.{$mario->uuid}", 6100)->where("counterpartyBalances.{$mario->uuid}.0.total_cents", $samePerson ? 34100 : 6100)
        ->where("counterpartyBalances.{$pino->uuid}.0.total_cents", $samePerson ? 0 : 28000));
})->with([false, true]);

test('shared filters use actual partial and fully refunded residuals', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $person = TrackedItem::create(['user_id' => $user->id, 'name' => 'Mario Rossi', 'slug' => 'mario', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    foreach ([['Partial', 100, 40], ['Settled', 16, 16]] as [$title, $original, $refunded]) {
        $transaction = userTransaction($user, $account, ['description' => $title, 'amount' => $original, 'shared_expense_counterparty_id' => $person->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => $original * 50, 'shared_expense_counterparty_share_cents' => $original * 50]);
        userTransaction($user, $account, ['amount' => $refunded, 'direction' => 'income', 'refunded_transaction_id' => $transaction->id]);
    }
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page
        ->has('entries', 1)->where('entries.0.title', 'Partial')->where('entries.0.display_state', 'partial')
        ->where('entries.0.original_share_cents', 5000)->where('entries.0.repaid_cents', 2000)->where('entries.0.remaining_cents', 3000));
    $this->get(route('shared-expenses.index', ['effect' => 'settled']))->assertInertia(fn (Assert $page) => $page
        ->has('entries', 1)->where('entries.0.title', 'Settled')->where('entries.0.remaining_cents', 0));
    $this->get(route('shared-expenses.index', ['effect' => 'all']))->assertInertia(fn (Assert $page) => $page->has('entries', 2));
});

test('quick creation for a manual reference sets capability without enabling shared expenses', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Entrate', 'slug' => 'entrate', 'direction_type' => 'income', 'group_type' => 'income', 'is_active' => true, 'is_selectable' => true]);
    $this->actingAs($user)->postJson(route('transactions.tracked-items.store'), ['context' => 'credit_debt', 'name' => 'Nuovo riferimento', 'account_uuid' => $account->uuid, 'category_uuid' => $category->uuid, 'type_key' => 'income'])->assertSuccessful();
    $reference = TrackedItem::where('name', 'Nuovo riferimento')->sole();
    expect($reference->user_id)->toBe($user->id)->and($reference->account_id)->toBeNull()->and($reference->isEconomicSubject())->toBeTrue()->and($reference->is_shared_expense_counterpart)->toBeFalse();
    $this->postJson(route('credits-debts.store'), ['reference_uuid' => $reference->uuid, 'type' => 'credit', 'description' => 'Nuovo credito', 'total_amount' => 30, 'currency_code' => 'EUR', 'account_uuid' => $account->uuid, 'category_uuid' => $category->uuid])->assertSuccessful();
    expect(CreditDebtItem::sole()->reference_id)->toBe($reference->id)->and(CreditDebtItem::sole()->counterparty_id)->toBe($reference->id);
});
