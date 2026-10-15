<?php

use App\Models\Category;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\SharedCounterpartyShadowService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\UserYearService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => config(['counterparty_ledger.shadow_shared_enabled' => false, 'counterparty_ledger.public_enabled' => true]));

function sharedReviewPerson(User $user, string $name = 'Roberta Delta'): TrackedItem
{
    return TrackedItem::create(['user_id' => $user->id, 'name' => $name, 'slug' => str()->slug($name), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
}

test('review action opens the exact transaction and identifies the actual reason', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $party = sharedReviewPerson($user);
    $transaction = userTransaction($user, $account, ['status' => 'review_needed', 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'amount' => 100]);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page
        ->has('reviewEntries', 1)->where('reviewEntries.0.review_reason', 'transaction')
        ->where('reviewEntries.0.origin_url', route('transactions.show', ['year' => $transaction->transaction_date->year, 'month' => $transaction->transaction_date->month, 'highlight' => $transaction->uuid, 'edit' => $transaction->uuid, 'return_to' => '/shared-expenses'])));
    $this->get(route('shared-expenses.index', ['effect' => 'settled']))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 0));
});

test('an orphan external share is resolved in place without a bank transaction and without duplicate audit on retry', function () {
    $user = User::factory()->create();
    createTestAccount($user);
    $party = sharedReviewPerson($user);
    $invalid = TrackedItem::create(['user_id' => $user->id, 'account_id' => $user->accounts()->first()->id, 'name' => 'Vecchio riferimento', 'slug' => 'invalid', 'is_active' => true]);
    $entry = SharedExpenseLedgerEntry::create(['user_id' => $user->id, 'counterparty_id' => $invalid->id, 'entry_date' => now()->toDateString(), 'title' => 'Anticipata', 'currency' => 'EUR', 'total_cents' => 10000, 'paid_by' => 'counterparty', 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'status' => 'recorded']);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 1)->where('reviewEntries.0.review_reason', 'reference')->where('reviewEntries.0.origin_url', null));
    $url = route('shared-expenses.references.resolve', ['kind' => 'ledger', 'uuid' => $entry->uuid]);
    $this->patch($url, ['reference_uuid' => $party->uuid])->assertSessionHasErrors('confirm_reference');
    for ($retry = 0; $retry < 2; $retry++) {
        $this->patch($url, ['reference_uuid' => $party->uuid, 'confirm_reference' => true])->assertSessionHasNoErrors();
    }
    $this->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 0)->has('entries', 1)->where('entries.0.display_state', 'payable')->where('entries.0.remaining_cents', 5000));
    expect($entry->fresh()->counterparty_id)->toBe($party->id)->and($entry->fresh()->uuid)->toBe($entry->uuid)
        ->and(SharedExpenseLedgerEntry::count())->toBe(1)->and(Transaction::count())->toBe(0)->and(CounterpartyObligation::count())->toBe(0)
        ->and(Activity::where('event', 'shared_reference_confirmed')->count())->toBe(1);
});

test('a wrong shared person can be corrected without replacing the merchant reference', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $party = sharedReviewPerson($user);
    $correct = sharedReviewPerson($user, 'Mario Rossi');
    $merchant = sharedReviewPerson($user, 'Q8');
    $transaction = userTransaction($user, $account, ['tracked_item_id' => $merchant->id, 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'amount' => 100]);
    $this->actingAs($user)->patch(route('shared-expenses.references.resolve', ['kind' => 'transaction', 'uuid' => $transaction->uuid]), ['reference_uuid' => $correct->uuid, 'confirm_reference' => true])->assertSessionHasNoErrors();
    expect($transaction->fresh()->shared_expense_counterparty_id)->toBe($correct->id)->and($transaction->fresh()->tracked_item_id)->toBe($merchant->id)->and($transaction->fresh()->amount)->toBe('100.00');
});

test('captured shares refuse identity changes and ownership is enforced', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $party = sharedReviewPerson($user);
    $correct = sharedReviewPerson($user, 'Mario Rossi');
    $transaction = userTransaction($user, $account, ['shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'amount' => 100]);
    app(SharedCounterpartyShadowService::class)->synchronizeSource($transaction);
    $url = route('shared-expenses.references.resolve', ['kind' => 'transaction', 'uuid' => $transaction->uuid]);
    $before = CounterpartyLedgerEntry::count();
    $this->actingAs($user)->patch($url, ['reference_uuid' => $correct->uuid, 'confirm_reference' => true])->assertSessionHasErrors('reference_uuid');
    expect($transaction->fresh()->shared_expense_counterparty_id)->toBe($party->id)->and(CounterpartyLedgerEntry::count())->toBe($before)->and(Activity::where('event', 'shared_reference_confirmed')->count())->toBe(0);
    $this->actingAs(User::factory()->create())->patch($url, ['reference_uuid' => $party->uuid, 'confirm_reference' => true])->assertNotFound();
});

test('existing reimbursements can be explicitly linked through the common payment lifecycle without duplicate bank movements', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $party = sharedReviewPerson($user);
    $source = userTransaction($user, $account, ['shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 0, 'shared_expense_counterparty_share_cents' => 10000, 'amount' => 100]);
    $settlement = app(SharedExpenseSettlementService::class)->record($user, $party, ['amount' => '100', 'settled_at' => now()->toDateString(), 'account_uuid' => $account->uuid, 'category_uuid' => null, 'reference' => null, 'notes' => null]);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 1)->where('reviewEntries.0.review_reason', 'payments'));
    $before = Transaction::count();
    $this->post(route('shared-expenses.payments.prepare', $party->uuid))->assertSessionHasNoErrors();
    $relation = CounterpartyRelation::sole();
    $payment = CounterpartyPayment::sole();
    $obligation = CounterpartyObligation::sole();
    $this->post(route('shared-expenses.payments.prepare', $party->uuid))->assertSessionHasNoErrors();
    expect(Transaction::count())->toBe($before)->and(CounterpartyPayment::count())->toBe(1)->and(CounterpartyObligation::count())->toBe(1);
    $this->patchJson(route('counterparties.payments.allocations', ['relation' => $relation->uuid, 'payment' => $payment->uuid]), ['operation_key' => (string) str()->uuid(), 'scope' => 'shared', 'allocations' => [['obligation_uuid' => $obligation->uuid, 'amount' => '100']], 'allow_unallocated' => false])->assertSuccessful();
    $this->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 0)->has('entries', 0));
    $this->get(route('shared-expenses.index', ['effect' => 'settled']))->assertInertia(fn (Assert $page) => $page->has('entries', 1)->where('entries.0.uuid', $source->uuid)->where('entries.0.remaining_cents', 0));
    expect(Transaction::count())->toBe($before)->and(app(SharedExpenseSettlementService::class)->balanceCents($user, $party))->toBe(0);
});

test('confirming an already correct external person does not change balances or create audit events', function () {
    $user = User::factory()->create();
    createTestAccount($user);
    $party = sharedReviewPerson($user);
    $entry = SharedExpenseLedgerEntry::create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'entry_date' => now()->toDateString(), 'title' => 'Anticipata', 'currency' => 'EUR', 'total_cents' => 10000, 'paid_by' => 'counterparty', 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'status' => 'recorded']);
    $url = route('shared-expenses.references.resolve', ['kind' => 'ledger', 'uuid' => $entry->uuid]);
    $this->actingAs($user)->patch($url, ['reference_uuid' => $party->uuid, 'confirm_reference' => true])->assertSessionHasNoErrors();
    expect(app(SharedExpenseSettlementService::class)->balanceCents($user, $party))->toBe(-5000)->and(Transaction::count())->toBe(0)->and(Activity::where('event', 'shared_reference_confirmed')->count())->toBe(0);
});

test('cancelled reimbursements are not presented as a reference verification', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $party = sharedReviewPerson($user);
    userTransaction($user, $account, ['shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 0, 'shared_expense_counterparty_share_cents' => 10000]);
    $settlement = app(SharedExpenseSettlementService::class)->record($user, $party, ['amount' => '100', 'settled_at' => now()->toDateString(), 'account_uuid' => $account->uuid, 'category_uuid' => null, 'reference' => null, 'notes' => null]);
    $settlement->transaction->update(['status' => 'ignored']);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 0)->has('blockedEntries', 0)->has('entries', 1));
    $this->get(route('shared-expenses.index', ['effect' => 'all']))->assertInertia(fn (Assert $page) => $page->where('entries', fn ($rows): bool => collect($rows)->firstWhere('uuid', $settlement->uuid)['display_state'] === 'cancelled'));
});

test('disabled payment management is explained as a protected control and does not offer a dead action', function () {
    config(['counterparty_ledger.public_enabled' => false]);
    $user = User::factory()->create();
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $party = sharedReviewPerson($user);
    userTransaction($user, $account, ['shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 0, 'shared_expense_counterparty_share_cents' => 10000]);
    app(SharedExpenseSettlementService::class)->record($user, $party, ['amount' => '100', 'settled_at' => now()->toDateString(), 'account_uuid' => $account->uuid, 'category_uuid' => null, 'reference' => null, 'notes' => null]);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 0)->has('blockedEntries', 1)->where('blockedEntries.0.review_reason', 'payments')->where('canPreparePayments', false));
    $this->post(route('shared-expenses.payments.prepare', $party->uuid))->assertNotFound();
    expect(CounterpartyObligation::count())->toBe(0)->and(Transaction::count())->toBe(2);
});

test('saving a shared review confirms the transaction only with the explicit confirmation signal', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    app(UserYearService::class)->ensureCurrentYearExists($user);
    $party = sharedReviewPerson($user);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Spese', 'slug' => 'spese-review', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $transaction = userTransaction($user, $account, ['category_id' => $category->id, 'status' => 'review_needed', 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
    $url = route('transactions.update', ['year' => now()->year, 'month' => now()->month, 'transaction' => $transaction->uuid]);
    $payload = ['account_uuid' => $account->uuid, 'category_uuid' => $category->uuid, 'transaction_day' => now()->day, 'type_key' => 'expense', 'amount' => '100.00', 'description' => 'Verificata', 'notes' => null, 'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $party->uuid, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share' => '50', 'shared_expense_counterparty_share' => '50'];
    $this->actingAs($user)->patch($url, $payload)->assertSessionHasNoErrors();
    expect($transaction->fresh()->status->value)->toBe('review_needed');
    $this->patch($url, [...$payload, 'confirm_shared_review' => true])->assertSessionHasNoErrors();
    expect($transaction->fresh()->status->value)->toBe('confirmed')->and(Transaction::count())->toBe(1);
    $this->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 0)->has('entries', 1)->where('entries.0.display_state', 'receivable')->where('entries.0.remaining_cents', 5000));
});

test('a mismatched reimbursement document remains visible as a protected inconsistency instead of being marked cancelled', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $party = sharedReviewPerson($user);
    $bankDocument = userTransaction($user, $account, ['amount' => 30, 'direction' => 'income']);
    SharedExpenseSettlement::create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'transaction_id' => $bankDocument->id, 'amount_cents' => 5000, 'direction' => 'counterparty_to_me', 'settled_at' => now()->toDateString()]);
    $this->actingAs($user)->get(route('shared-expenses.index'))->assertInertia(fn (Assert $page) => $page->has('reviewEntries', 0)->has('blockedEntries', 1)->where('blockedEntries.0.review_reason', 'payment_document')->where('blockedEntries.0.is_cancelled', false));
});
