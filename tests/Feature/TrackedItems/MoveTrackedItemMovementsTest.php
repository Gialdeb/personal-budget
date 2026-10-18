<?php

use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySyncIssue;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\RecurringEntry;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\TrackedItemAlias;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyLedgerService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\TrackedItems\TrackedItemIdentityService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['counterparty_ledger.shadow_shared_enabled' => false, 'counterparty_ledger.shadow_manual_enabled' => false]);
    $this->actor = User::factory()->create();
    $this->account = createTestAccount($this->actor);
    $this->source = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Origine', 'slug' => 'source', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->target = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Destinazione', 'slug' => 'target', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($this->actor);
});

function movementPreview(object $test): array
{
    return $test->getJson(route('tracked-items.movements.preview', ['source_uuid' => $test->source->uuid, 'destination_uuid' => $test->target->uuid]))->assertSuccessful()->json();
}

function movementConfirm(object $test, array $preview): TestResponse
{
    return $test->postJson(route('tracked-items.movements.store'), ['source_uuid' => $test->source->uuid, 'destination_uuid' => $test->target->uuid, 'preview_token' => $preview['preview_token'], 'confirmed' => true]);
}

test('preview is read only and moves only attribution columns keeping both references and aliases intact', function () {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $before = $transaction->fresh()->getRawOriginal();
    $balance = $this->account->fresh()->getAttributes();
    $refs = DB::table('tracked_items')->orderBy('id')->get()->toArray();
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $preview = movementPreview($this);
    expect($preview['count'])->toBe(1)->and(array_keys($preview))->toBe(['count', 'groups', 'block_reason', 'preview_token'])->and($writes)->toBe([]);
    movementConfirm($this, $preview)->assertSuccessful();
    $after = $transaction->fresh()->getRawOriginal();
    $before['tracked_item_id'] = $this->target->id;
    expect($after)->toBe($before)->and($this->account->fresh()->getAttributes())->toBe($balance)
        ->and(DB::table('tracked_items')->orderBy('id')->get()->toArray())->toEqual($refs)
        ->and(TrackedItemAlias::count())->toBe(0);
});

test('moves all mutable references including shared parties debts external expenses and plans', function () {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id, 'shared_expense_counterparty_id' => $this->source->id]);
    $debt = CreditDebtItem::factory()->forAccount($this->account)->create(['reference_id' => $this->source->id, 'counterparty_id' => $this->source->id]);
    $external = SharedExpenseLedgerEntry::create(['user_id' => $this->actor->id, 'counterparty_id' => $this->source->id, 'entry_date' => '2026-10-01', 'title' => 'Esterna', 'currency' => 'EUR', 'total_cents' => 10000, 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'paid_by' => 'counterparty', 'status' => 'recorded']);
    $plan = RecurringEntry::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'title' => 'Futura', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'tracked_item_id' => $this->source->id]);
    $preview = movementPreview($this);
    expect($preview['count'])->toBe(4);
    movementConfirm($this, $preview)->assertSuccessful();
    expect($transaction->fresh()->tracked_item_id)->toBe($this->target->id)
        ->and($transaction->fresh()->shared_expense_counterparty_id)->toBe($this->target->id)
        ->and($debt->fresh()->reference_id)->toBe($this->target->id)
        ->and($debt->fresh()->counterparty_id)->toBe($this->target->id)
        ->and($external->fresh()->counterparty_id)->toBe($this->target->id)
        ->and($plan->fresh()->tracked_item_id)->toBe($this->target->id);
});

test('requires explicit confirmation and invalidates changed previews without partial writes', function () {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $preview = movementPreview($this);
    $this->postJson(route('tracked-items.movements.store'), ['source_uuid' => $this->source->uuid, 'destination_uuid' => $this->target->uuid, 'preview_token' => $preview['preview_token']])->assertUnprocessable();
    DB::table('transactions')->where('id', $transaction->id)->update(['amount' => 99]);
    movementConfirm($this, $preview)->assertUnprocessable()->assertJsonValidationErrors('movements');
    expect($transaction->fresh()->tracked_item_id)->toBe($this->source->id)->and(TrackedItemAlias::count())->toBe(0);
});

test('any protected economic relation blocks the entire all references operation', function () {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $preview = movementPreview($this);
    app(CounterpartyLedgerService::class)->relation($this->actor, $this->source, 'EUR');
    $before = DB::table('counterparty_relations')->get()->toArray();
    movementConfirm($this, $preview)->assertUnprocessable();
    expect($transaction->fresh()->tracked_item_id)->toBe($this->source->id)->and(DB::table('counterparty_relations')->get()->toArray())->toEqual($before);
});

test('foreign catalogs aliases and inactive targets cannot receive movements', function (string $case) {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    if ($case === 'foreign') {
        $this->target->update(['user_id' => User::factory()->create()->id]);
    }
    if ($case === 'shared') {
        $this->target->update(['account_id' => $this->account->id]);
    }
    if ($case === 'inactive') {
        $this->target->update(['is_active' => false]);
    }
    if ($case === 'alias') {
        $canonical = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Altro', 'slug' => 'other', 'is_active' => true]);
        app(TrackedItemIdentityService::class)->register($this->actor, $this->target, $canonical);
    }
    $aliases = DB::table('tracked_item_aliases')->get()->toArray();
    $response = $this->getJson(route('tracked-items.movements.preview', ['source_uuid' => $this->source->uuid, 'destination_uuid' => $this->target->uuid]));
    expect($response->status())->toBeIn([404, 422]);
    expect($transaction->fresh()->tracked_item_id)->toBe($this->source->id)->and(DB::table('tracked_item_aliases')->get()->toArray())->toEqual($aliases);
})->with(['foreign', 'shared', 'inactive', 'alias']);

test('choices show names category and hierarchy instead of slug or identity details', function () {
    $category = Category::create(['user_id' => $this->actor->id, 'name' => 'Casa', 'slug' => 'home', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true]);
    $this->source->compatibleCategories()->attach($category);
    $this->source->update(['parent_id' => $this->target->id]);
    $items = $this->getJson(route('tracked-items.movements.options'))->assertSuccessful()->json('items');
    $source = collect($items)->firstWhere('value', $this->source->uuid);
    expect($source['label'])->toBe('Origine')->and($source['full_path'])->toBe('Destinazione › Origine · Casa')->and($source)->not->toHaveKey('catalog_label')->not->toHaveKey('slug');
});

test('a failure after an attribution update rolls back the entire movement', function () {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $preview = movementPreview($this);
    $second = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $preview = movementPreview($this);
    DB::statement("CREATE TRIGGER block_move BEFORE UPDATE OF tracked_item_id ON transactions WHEN OLD.id = {$second->id} BEGIN SELECT RAISE(ABORT, 'test failure'); END");
    $this->withoutExceptionHandling();
    try {
        movementConfirm($this, $preview);
        $this->fail('Expected database rejection.');
    } catch (QueryException) {
        expect($transaction->fresh()->tracked_item_id)->toBe($this->source->id)->and($second->fresh()->tracked_item_id)->toBe($this->source->id)->and($this->source->fresh()->is_active)->toBeTrue()
            ->and(DB::table('tracked_item_transaction_moves')->count())->toBe(0)->and(DB::table('tracked_item_transaction_move_entries')->count())->toBe(0);
    }
});

test('protected debt history blocks all referenced records', function () {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $debt = CreditDebtItem::factory()->forAccount($this->account)->create(['reference_id' => $this->source->id, 'counterparty_id' => $this->source->id]);
    $payment = CreditDebtPayment::create(['user_id' => $this->actor->id, 'credit_debt_item_id' => $debt->id, 'account_id' => $this->account->id, 'currency_code' => 'EUR', 'amount' => 25, 'paid_at' => '2026-10-01']);
    $payment->delete();
    $before = $debt->fresh()->getRawOriginal();
    movementConfirm($this, movementPreview($this))->assertUnprocessable();
    expect($transaction->fresh()->tracked_item_id)->toBe($this->source->id)->and($debt->fresh()->getRawOriginal())->toBe($before);
});

test('partially posted shared occurrences block all reference changes', function () {
    $ordinary = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $plan = RecurringEntry::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'title' => 'Condivisa', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'expected_amount' => 100, 'shared_expense_counterparty_id' => $this->source->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
    $occurrence = $plan->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-01', 'due_date' => '2026-10-01', 'expected_amount' => 100, 'status' => 'pending']);
    $component = userTransaction($this->actor, $this->account, ['amount' => 40, 'kind' => 'scheduled', 'recurring_entry_occurrence_id' => $occurrence->id, 'shared_expense_counterparty_id' => $this->source->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 2000, 'shared_expense_counterparty_share_cents' => 2000]);
    $before = $component->fresh()->getRawOriginal();
    movementConfirm($this, movementPreview($this))->assertUnprocessable();
    expect($ordinary->fresh()->tracked_item_id)->toBe($this->source->id)->and($component->fresh()->getRawOriginal())->toBe($before)->and($plan->fresh()->shared_expense_counterparty_id)->toBe($this->source->id);
});

test('shared catalogs require current editing permission at confirmation', function (string $role) {
    $owner = $this->actor;
    $editor = User::factory()->create();
    $membership = AccountMembership::create(['account_id' => $this->account->id, 'user_id' => $editor->id, 'role' => 'editor', 'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $owner->id, 'joined_at' => now()]);
    $this->source->update(['account_id' => $this->account->id]);
    $this->target->update(['account_id' => $this->account->id]);
    $transaction = userTransaction($owner, $this->account, ['tracked_item_id' => $this->source->id]);
    $this->actingAs($editor);
    $preview = movementPreview($this);
    $membership->update(['role' => $role]);
    $response = movementConfirm($this, $preview);
    if ($role === 'viewer') {
        $response->assertNotFound();
        expect($transaction->fresh()->tracked_item_id)->toBe($this->source->id);
    } else {
        $response->assertSuccessful();
        expect($transaction->fresh()->tracked_item_id)->toBe($this->target->id);
    }
})->with(['editor', 'viewer']);

test('existing aliases block all movement without modifying the identity graph', function () {
    $secondary = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Secondario', 'slug' => 'secondary', 'is_active' => true]);
    app(TrackedItemIdentityService::class)->register($this->actor, $secondary, $this->source);
    $historical = userTransaction($this->actor, $this->account, ['tracked_item_id' => $secondary->id]);
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $aliases = DB::table('tracked_item_aliases')->get()->toArray();
    $history = $historical->fresh()->getRawOriginal();
    movementConfirm($this, movementPreview($this))->assertUnprocessable();
    expect($transaction->fresh()->tracked_item_id)->toBe($this->source->id)->and($historical->fresh()->getRawOriginal())->toBe($history)
        ->and(DB::table('tracked_item_aliases')->get()->toArray())->toEqual($aliases)->and($this->source->fresh()->is_active)->toBeTrue();
});

test('descriptive references on payment documents move without changing payment identity', function () {
    $ordinary = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $bank = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $debt = CreditDebtItem::factory()->forAccount($this->account)->create(['reference_id' => $this->target->id, 'counterparty_id' => $this->target->id]);
    CreditDebtPayment::create(['user_id' => $this->actor->id, 'credit_debt_item_id' => $debt->id, 'transaction_id' => $bank->id, 'account_id' => $this->account->id, 'currency_code' => 'EUR', 'amount' => 25, 'paid_at' => '2026-10-01']);
    $payments = DB::table('credit_debt_payments')->get()->all();
    $bankBefore = $bank->fresh()->getRawOriginal();
    $preview = movementPreview($this);
    expect($preview['block_reason'])->toBeNull();
    movementConfirm($this, $preview)->assertSuccessful();
    $bankBefore['tracked_item_id'] = $this->target->id;
    expect($ordinary->fresh()->tracked_item_id)->toBe($this->target->id)->and($bank->fresh()->getRawOriginal())->toBe($bankBefore)
        ->and(DB::table('credit_debt_payments')->get()->all())->toEqual($payments);
});

test('Emanuela payment screenshot moves and restores its descriptive reference while every economic row remains unchanged', function () {
    config(['counterparty_ledger.public_enabled' => true]);
    $this->source->update(['name' => 'Emanuela Barbarulo']);
    $this->target->update(['name' => 'Emanuela Barbarulo']);
    CreditDebtItem::factory()->forAccount($this->account)->create(['reference_id' => $this->target->id, 'counterparty_id' => $this->target->id, 'type' => 'credit', 'total_amount' => '100.00']);
    app(CounterpartyBackfillService::class)->run($this->actor, $this->target, 'EUR', true);
    $relation = CounterpartyRelation::where('counterparty_id', $this->target->id)->sole();
    $payments = app(CounterpartyPaymentService::class);
    $payments->activate($this->actor, $relation, 'activate-move-case');
    $result = $payments->create($this->actor, $relation, ['operation_key' => 'emanuela-payment', 'amount' => '10.00', 'date' => '2026-10-12', 'account_uuid' => $this->account->uuid, 'direction' => 'received', 'scope' => 'all', 'allow_unallocated' => false]);
    $payment = CounterpartyPayment::where('uuid', $result['payment_uuid'])->sole();
    DB::table('transactions')->where('id', $payment->transaction_id)->update(['tracked_item_id' => $this->source->id]);
    $transaction = Transaction::findOrFail($payment->transaction_id);
    $before = $transaction->getRawOriginal();
    $economic = movementProtectedSnapshot();
    $preview = movementPreview($this);
    expect($preview['count'])->toBe(1)->and($preview['block_reason'])->toBeNull();
    movementConfirm($this, $preview)->assertSuccessful();
    $expected = $before;
    $expected['tracked_item_id'] = $this->target->id;
    expect($transaction->fresh()->getRawOriginal())->toBe($expected)->and(movementProtectedSnapshot())->toEqual($economic);
    $move = DB::table('tracked_item_transaction_moves')->sole();
    $this->postJson(route('tracked-items.movements.restore', $move->uuid), ['confirmed' => true])->assertSuccessful();
    expect($transaction->fresh()->getRawOriginal())->toBe($before)->and(movementProtectedSnapshot())->toEqual($economic);
});

test('Emanuela screenshot with two protected relations blocks all twenty eight links with concrete details', function () {
    $this->source->update(['name' => 'Emanuela Barbarulo']);
    $this->target->update(['name' => 'Emanuela Barbarulo']);
    foreach (['EUR', 'USD'] as $currency) {
        app(CounterpartyLedgerService::class)->relation($this->actor, $this->source, $currency);
    }
    foreach (range(1, 20) as $index) {
        SharedExpenseLedgerEntry::create(['user_id' => $this->actor->id, 'counterparty_id' => $this->source->id, 'entry_date' => '2026-10-14', 'title' => 'Spesa '.$index, 'currency' => 'EUR', 'total_cents' => 1000, 'my_share_cents' => 500, 'counterparty_share_cents' => 500, 'paid_by' => 'self', 'status' => 'recorded']);
    }
    foreach (['Telepedaggio', 'Pulizia Casa'] as $title) {
        RecurringEntry::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'title' => $title, 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'expected_amount' => 100, 'shared_expense_counterparty_id' => $this->source->id]);
    }
    TransactionTemplate::factory()->count(4)->create(['user_id' => $this->actor->id, 'shared_counterparty_id' => $this->source->id]);
    $before = movementProtectedSnapshot();
    $preview = movementPreview($this);
    expect($preview['count'])->toBe(28)->and($preview['block_reason'])->toContain('2 rapporti economici', 'storico economico');
    $protected = collect($preview['groups'])->firstWhere('key', 'protected');
    expect(array_column($protected['items'], 'title'))->toBe(['Rapporto economico · EUR', 'Rapporto economico · USD']);
    movementConfirm($this, $preview)->assertUnprocessable();
    expect(movementProtectedSnapshot())->toEqual($before)->and(DB::table('tracked_item_transaction_moves')->count())->toBe(0);
});

test('movement choices disclose slugs only for same catalog homonyms', function () {
    $this->source->update(['name' => 'Emanuela Barbarulo']);
    $this->target->update(['name' => 'Emanuela Barbarulo']);
    $unique = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Corso inglese', 'slug' => 'hide-this-slug', 'is_active' => true]);
    $items = collect($this->getJson(route('tracked-items.movements.options'))->assertSuccessful()->json('items'))->keyBy('value');
    expect($items[$this->source->uuid]['disambiguation'])->toBe('source')->and($items[$this->target->uuid]['disambiguation'])->toBe('target')
        ->and($items[$unique->uuid])->not->toHaveKey('disambiguation');
});

function movementProtectedSnapshot(): array
{
    return collect(['accounts', 'tracked_items', 'tracked_item_aliases', 'credit_debt_items', 'credit_debt_payments', 'shared_expense_ledger_entries', 'shared_expense_settlements', 'recurring_entries', 'transaction_templates', 'counterparty_relations', 'counterparty_obligations', 'counterparty_source_links', 'counterparty_ledger_entries', 'counterparty_payments', 'counterparty_allocation_entries', 'counterparty_operations', 'counterparty_sync_issues'])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->all()])->all();
}

test('resolved diagnostics do not block moves while unresolved diagnostics require review', function (bool $resolved) {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $issue = CounterpartySyncIssue::create(['user_id' => $this->actor->id, 'counterparty_id' => $this->source->id, 'currency_code' => 'EUR', 'source_type' => 'shared_transaction_share', 'source_identity' => $transaction->uuid, 'message' => 'Diagnostica conservata', 'resolved_at' => $resolved ? now() : null]);
    $before = $issue->fresh()->getRawOriginal();
    $preview = movementPreview($this);
    if ($resolved) {
        expect($preview['block_reason'])->toBeNull();
        movementConfirm($this, $preview)->assertSuccessful();
        $before['counterparty_id'] = $this->target->id;
        expect($issue->fresh()->getRawOriginal())->toBe($before);
        $move = DB::table('tracked_item_transaction_moves')->sole();
        $this->postJson(route('tracked-items.movements.restore', $move->uuid), ['confirmed' => true])->assertSuccessful();
        expect($issue->fresh()->counterparty_id)->toBe($this->source->id);
    } else {
        expect($preview['block_reason'])->toContain('ancora aperta');
        movementConfirm($this, $preview)->assertUnprocessable();
        expect($issue->fresh()->getRawOriginal())->toBe($before)->and($transaction->fresh()->tracked_item_id)->toBe($this->source->id);
    }
})->with([true, false]);

test('future plans may select a destination with an economic relation without moving that relation', function () {
    $relation = app(CounterpartyLedgerService::class)->relation($this->actor, $this->target, 'EUR');
    $plan = RecurringEntry::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'title' => 'Nuova scadenza indipendente', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'start_date' => '2026-11-01', 'recurrence_type' => 'monthly', 'expected_amount' => 100, 'shared_expense_counterparty_id' => $this->source->id]);
    $economic = $relation->fresh()->getRawOriginal();
    $preview = movementPreview($this);
    expect($preview['block_reason'])->toBeNull();
    movementConfirm($this, $preview)->assertSuccessful();
    expect($plan->fresh()->shared_expense_counterparty_id)->toBe($this->target->id)->and($relation->fresh()->getRawOriginal())->toBe($economic);
});
