<?php

use App\Enums\CounterpartyPaymentDirectionEnum;
use App\Models\Category;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\CounterpartyLedgerService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => config(['features.credits_debts.enabled' => true, 'counterparty_ledger.shadow_manual_enabled' => false, 'counterparty_ledger.shadow_shared_enabled' => false]));

test('person cards combine origins and include confirmed manual only people', function (bool $samePerson) {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $people = collect(['Mario Rossi', 'Pino Mauro'])->map(fn (string $name) => TrackedItem::create(['user_id' => $user->id, 'name' => $name, 'slug' => str()->slug($name), 'is_active' => true, 'is_shared_expense_counterpart' => true]));
    [$mario, $pino] = $people->all();
    userTransaction($user, $account, ['shared_expense_counterparty_id' => $mario->id, 'amount' => 122, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 6100, 'shared_expense_counterparty_share_cents' => 6100]);
    CreditDebtItem::factory()->forAccount($account)->create(['reference_id' => $samePerson ? $mario->id : $pino->id, 'total_amount' => 280]);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('filters.effect', 'pending')->has('entries', 1)->where('entries.0.display_state', 'receivable')
        ->has('visibleCounterparties', $samePerson ? 1 : 2)->where('visibleCounterparties.0.uuid', $mario->uuid)
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

test('Roberta confirmation exposes one relationship and subsequent payments and shared expenses keep one balance', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Entrate', 'slug' => 'entrate', 'direction_type' => 'income', 'group_type' => 'income', 'is_active' => true, 'is_selectable' => true]);
    $roberta = TrackedItem::create(['user_id' => $user->id, 'name' => 'Roberta Delta', 'slug' => 'roberta', 'is_active' => true]);
    $unconfirmed = TrackedItem::create(['user_id' => $user->id, 'name' => 'Da confermare', 'slug' => 'da-confermare', 'is_active' => true]);
    $item = legacyCreditDebtItem($user, ['reference_id' => $roberta->id, 'total_amount' => '350.00', 'account_id' => $account->id, 'category_id' => $category->id]);
    $other = legacyCreditDebtItem($user, ['reference_id' => $unconfirmed->id, 'counterparty_id' => $roberta->id, 'total_amount' => '99.00']);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('visibleCounterparties', 0));
    $this->putJson(route('credits-debts.update', $item), ['reference_uuid' => $roberta->uuid])->assertUnprocessable();
    $this->putJson(route('credits-debts.update', $item), ['reference_uuid' => $roberta->uuid, 'confirm_reference' => true, 'note' => 'Confermato'])->assertSuccessful()->assertJsonPath('data.reference_needs_review', false);
    expect($item->fresh()->counterparty_id)->toBe($roberta->id)->and($item->fresh()->note)->toBe('Confermato')
        ->and($roberta->fresh()->isEconomicSubject())->toBeTrue()->and($roberta->fresh()->is_shared_expense_counterpart)->toBeFalse()
        ->and(Transaction::count())->toBe(0)->and(CounterpartyObligation::count())->toBe(0);
    $assertRoberta = fn (int $manual, int $shared, int $total) => $this->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page
        ->has('visibleCounterparties', 1)->where('visibleCounterparties.0.uuid', $roberta->uuid)
        ->where("counterpartyBalances.{$roberta->uuid}.0.manual_cents", $manual)
        ->where("counterpartyBalances.{$roberta->uuid}.0.shared_cents", $shared)
        ->where("counterpartyBalances.{$roberta->uuid}.0.total_cents", $total));
    $assertRoberta(35000, 0, 35000);
    $this->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->where("relationshipOrigins.{$roberta->uuid}", ['manual_credit'])->where('referenceReviewPeople', [])->has('entries', 0));
    $this->putJson(route('credits-debts.update', $item), ['reference_uuid' => $roberta->uuid, 'confirm_reference' => true])->assertSuccessful();
    expect(Activity::where('event', 'economic_subject_aligned')->count())->toBe(1);
    $this->postJson(route('credits-debts.payments.store', $item), ['amount' => '50.00', 'account_id' => $account->id, 'paid_at' => now()->toDateString()])->assertSuccessful();
    $assertRoberta(30000, 0, 30000);
    $assertRoberta(30000, 0, 30000);
    expect(CreditDebtPayment::count())->toBe(1)->and(Transaction::count())->toBe(1);
    $this->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->where("relationshipOrigins.{$roberta->uuid}", ['manual_credit', 'payments']));
    $this->postJson(route('shared-expenses.counterparties.store'), ['name' => 'Roberta Delta'])->assertSuccessful()->assertJsonPath('uuid', $roberta->uuid);
    userTransaction($user, $account, ['amount' => 122, 'shared_expense_counterparty_id' => $roberta->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 6100, 'shared_expense_counterparty_share_cents' => 6100]);
    $assertRoberta(30000, 6100, 36100);
    expect(TrackedItem::where('name', 'Roberta Delta')->count())->toBe(1)->and($other->fresh()->reference_id)->toBe($unconfirmed->id)
        ->and($other->fresh()->counterparty_id)->toBe($roberta->id)->and(CounterpartyObligation::count())->toBe(0);
});

test('payment only relationships stay visible in their currency without invented shared expenses', function () {
    $user = User::factory()->create();
    $party = TrackedItem::create(['user_id' => $user->id, 'name' => 'Solo pagamento', 'slug' => 'solo-pagamento', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $account = createTestAccount($user, ['currency_code' => 'USD']);
    $relation = CounterpartyRelation::create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'currency_code' => 'USD', 'minor_unit' => 2, 'activated_at' => now()]);
    app(CounterpartyLedgerService::class)->recordPayment($relation, CounterpartyPaymentDirectionEnum::RECEIVED, 5000, now()->toDateString(), $account, 'payment-only');
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page
        ->has('entries', 0)->has('visibleCounterparties', 1)->where('visibleCounterparties.0.uuid', $party->uuid)
        ->where("relationshipOrigins.{$party->uuid}", ['payments'])->where("counterpartyBalances.{$party->uuid}.1.currency", 'USD')
        ->where("counterpartyBalances.{$party->uuid}.1.total_cents", -5000));
});

test('a fully paid confirmed manual relationship remains visible at zero with payment history', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $party = TrackedItem::create(['user_id' => $user->id, 'name' => 'Rapporto regolato', 'slug' => 'regolato', 'is_active' => true, 'settings' => ['economic_subject' => true]]);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['reference_id' => $party->id, 'total_amount' => '50.00']);
    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $item), ['amount' => '50.00', 'account_id' => $account->id, 'paid_at' => now()->toDateString()])->assertSuccessful();
    $this->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page
        ->has('entries', 0)->has('visibleCounterparties', 1)->where('visibleCounterparties.0.uuid', $party->uuid)
        ->where("counterpartyBalances.{$party->uuid}.0.total_cents", 0)->where("relationshipOrigins.{$party->uuid}", ['manual_credit', 'payments']));
});
