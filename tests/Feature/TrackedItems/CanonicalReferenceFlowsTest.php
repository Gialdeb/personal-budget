<?php

use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Models\UserYear;
use App\Services\TrackedItems\TrackedItemIdentityService;
use App\Services\TrackedItems\TrackedItemResolver;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['features.credits_debts.enabled' => true, 'counterparty_ledger.shadow_manual_enabled' => false]);
    $this->actor = User::factory()->create();
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->category = Category::create(['user_id' => $this->actor->id, 'name' => 'Spese', 'slug' => 'spese-canonical', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->source = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Caffè precedente', 'slug' => 'old-reference', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->target = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Bar canonico', 'slug' => 'canonical-reference', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    foreach ([$this->source, $this->target] as $item) {
        $item->compatibleCategories()->attach($this->category);
    }
});

function canonicalFlowAlias(object $test): void
{
    app(TrackedItemIdentityService::class)->register($test->actor, $test->source, $test->target);
}

/** @return array<string, mixed> */
function canonicalFlowTransaction(object $test, array $overrides = []): array
{
    return ['transaction_day' => 17, 'type_key' => 'expense', 'account_uuid' => $test->account->uuid, 'category_uuid' => $test->category->uuid,
        'tracked_item_uuid' => $test->source->uuid, 'amount' => 15, 'description' => 'Operazione canonica', ...$overrides];
}

/** @return array<string, mixed> */
function canonicalFlowPlan(object $test, array $overrides = []): array
{
    return ['title' => 'Nuovo piano', 'account_uuid' => $test->account->uuid, 'category_uuid' => $test->category->uuid, 'tracked_item_uuid' => $test->source->uuid,
        'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 17], 'start_date' => '2026-10-17', 'end_mode' => 'never', 'expected_amount' => 20,
        'is_amount_variable' => false, 'auto_generate_occurrences' => false, 'auto_create_transaction' => false, 'is_active' => true, 'reminder_days_before' => [], ...$overrides];
}

test('inline textual and UUID reference creation reuse canonical identities without reactivating aliases', function (string $endpoint, bool $uuid) {
    canonicalFlowAlias($this);
    $payload = ['account_uuid' => $this->account->uuid, 'category_uuid' => $this->category->uuid, 'type_key' => 'expense', 'direction' => 'expense'];
    $payload += $uuid ? ['tracked_item_uuid' => $this->source->uuid] : ['name' => ' CAFFE  PRECEDENTE '];
    $route = $endpoint === 'shared-expenses' ? 'shared-expenses.counterparties.store' : $endpoint.'.tracked-items.store';
    $response = $this->actingAs($this->actor)->postJson(route($route), $payload)->assertSuccessful();
    expect($response->json($endpoint === 'shared-expenses' ? 'uuid' : 'item.uuid'))->toBe($this->target->uuid)
        ->and(TrackedItem::count())->toBe(2)->and($this->source->fresh()->is_active)->toBeFalse();
})->with(['transactions', 'recurring-entries', 'shared-expenses'])->with([true, false]);

test('textual aliases with a shared canonical destination are not ambiguous and archived roots are never reactivated', function () {
    canonicalFlowAlias($this);
    $second = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'CAFFE PRECEDENTE', 'slug' => 'old-two', 'is_active' => true]);
    app(TrackedItemIdentityService::class)->register($this->actor, $second, $this->target);
    expect(app(TrackedItemResolver::class)->resolve($this->actor->id, null, $this->source->name)->id)->toBe($this->target->id);
    $this->target->update(['is_active' => false]);
    expect(fn () => app(TrackedItemResolver::class)->resolve($this->actor->id, null, $this->source->name))->toThrow(ValidationException::class)
        ->and($this->target->fresh()->is_active)->toBeFalse()->and(TrackedItem::count())->toBe(3);
});

test('catalog search and validation are read only even with legacy personal references on a shared account', function () {
    $editor = User::factory()->create();
    AccountMembership::create(['account_id' => $this->account->id, 'user_id' => $editor->id, 'role' => 'editor', 'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $this->actor->id, 'joined_at' => now()]);
    $historical = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id, 'category_id' => $this->category->id]);
    $this->target->update(['account_id' => $this->account->id]);
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $resolver = app(OperationalTransactionCategoryResolver::class);
    $items = $resolver->trackedItemsForAccount($this->account, [$this->source->id]);
    $options = collect($resolver->trackedItemOptionsFromCollection($items, $this->account));
    expect($resolver->findTrackedItemForAccount($this->account, $this->source->id)->id)->toBe($this->source->id);
    $resolver->categoriesForAccount($this->account, [$this->category->id]);
    $resolver->findCategoryForAccount($this->account, $this->category->id);
    $this->actingAs($this->actor)->postJson(route('transactions.store', ['year' => 2026, 'month' => 10]), canonicalFlowTransaction($this, ['amount' => -1, 'tracked_item_uuid' => $this->target->uuid]))->assertUnprocessable();
    expect($writes)->toBe([])->and($historical->fresh()->tracked_item_id)->toBe($this->source->id)
        ->and($options->firstWhere('value', $this->source->uuid)['is_selectable'])->toBeFalse();
});

test('search options advertise alias terms on the canonical and keep historical aliases disabled', function () {
    canonicalFlowAlias($this);
    $resolver = app(OperationalTransactionCategoryResolver::class);
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $options = collect($resolver->trackedItemOptionsFromCollection($resolver->trackedItemsForAccount($this->account, [$this->source->id])));
    expect($options->firstWhere('value', $this->target->uuid)['search_terms'])->toContain($this->source->name, $this->source->uuid)
        ->and($options->firstWhere('value', $this->source->uuid)['is_selectable'])->toBeFalse()
        ->and($options->firstWhere('value', $this->target->uuid)['is_selectable'])->toBeTrue()
        ->and(collect(app(TrackedItemResolver::class)->counterpartyOptions($this->actor->id))->pluck('value')->all())->not->toContain($this->source->uuid)
        ->and($writes)->toBe([]);
});

test('new transaction insertion resolves alias UUIDs without rewriting existing transactions', function () {
    $old = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id, 'category_id' => $this->category->id, 'transaction_date' => '2026-10-01', 'balance_after' => 900]);
    canonicalFlowAlias($this);
    $before = DB::table('transactions')->where('id', $old->id)->first();
    $this->actingAs($this->actor)->post(route('transactions.store', ['year' => 2026, 'month' => 10]), canonicalFlowTransaction($this))->assertSessionHasNoErrors();
    expect(Transaction::where('description', 'Operazione canonica')->sole()->tracked_item_id)->toBe($this->target->id)
        ->and(DB::table('transactions')->where('id', $old->id)->first())->toEqual($before);
});

test('transaction edits preserve their original reference after alias registration', function () {
    $old = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id, 'category_id' => $this->category->id, 'transaction_date' => '2026-10-17']);
    canonicalFlowAlias($this);
    $this->actingAs($this->actor)->patch(route('transactions.update', ['year' => 2026, 'month' => 10, 'transaction' => $old->uuid]), canonicalFlowTransaction($this, ['amount' => 100, 'description' => 'Descrizione aggiornata']))->assertSessionHasNoErrors();
    expect($old->fresh()->tracked_item_id)->toBe($this->source->id)->and($old->fresh()->description)->toBe('Descrizione aggiornata');
});

test('new recurring and installment plans use the canonical without rewriting old plans', function (string $kind) {
    $this->actingAs($this->actor)->post(route('recurring-entries.store'), canonicalFlowPlan($this, ['title' => 'Originale']))->assertSessionHasNoErrors();
    $old = RecurringEntry::where('title', 'Originale')->sole();
    canonicalFlowAlias($this);
    $overrides = $kind === 'installment' ? ['entry_type' => 'installment', 'total_amount' => 120, 'installments_count' => 6, 'expected_amount' => null, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 6] : [];
    $this->post(route('recurring-entries.store'), canonicalFlowPlan($this, $overrides))->assertSessionHasNoErrors();
    expect(RecurringEntry::where('title', 'Nuovo piano')->sole()->tracked_item_id)->toBe($this->target->id)
        ->and($old->fresh()->tracked_item_id)->toBe($this->source->id);
})->with(['recurring', 'installment']);

test('recurring edits preserve original references', function () {
    $this->actingAs($this->actor)->post(route('recurring-entries.store'), canonicalFlowPlan($this))->assertSessionHasNoErrors();
    $entry = RecurringEntry::sole();
    canonicalFlowAlias($this);
    $this->put(route('recurring-entries.update', $entry->uuid), canonicalFlowPlan($this, ['title' => 'Titolo aggiornato']))->assertSessionHasNoErrors();
    expect($entry->fresh()->tracked_item_id)->toBe($this->source->id);
});

test('new manual credit debts canonicalize both identity fields while edits preserve original subjects', function () {
    $payload = ['type' => 'credit', 'description' => 'Originale', 'total_amount' => '100.00', 'currency_code' => 'EUR', 'account_uuid' => $this->account->uuid, 'category_uuid' => $this->category->uuid, 'reference_uuid' => $this->source->uuid];
    $this->actingAs($this->actor)->post(route('credits-debts.store'), $payload)->assertSessionHasNoErrors();
    $old = CreditDebtItem::sole();
    canonicalFlowAlias($this);
    $this->post(route('credits-debts.store'), [...$payload, 'description' => 'Nuovo'])->assertSessionHasNoErrors();
    $new = CreditDebtItem::where('description', 'Nuovo')->sole();
    expect($new->reference_id)->toBe($this->target->id)->and($new->counterparty_id)->toBe($this->target->id)
        ->and($old->fresh()->reference_id)->toBe($this->source->id);
    $this->put(route('credits-debts.update', $old->uuid), ['note' => 'Nota aggiornata'])->assertSessionHasNoErrors();
    expect($old->fresh()->reference_id)->toBe($this->source->id)->and($old->fresh()->counterparty_id)->toBe($this->source->id);
});

test('canonical resolution cannot cross catalog boundaries', function () {
    canonicalFlowAlias($this);
    $foreign = User::factory()->create();
    $this->actingAs($foreign)->postJson(route('shared-expenses.counterparties.store'), ['tracked_item_uuid' => $this->source->uuid])->assertUnprocessable();
    $shared = createTestAccount($this->actor);
    $this->category->update(['account_id' => $shared->id]);
    TrackedItem::create(['user_id' => $this->actor->id, 'account_id' => $shared->id, 'name' => 'Condiviso', 'slug' => 'shared-canonical', 'is_active' => true]);
    $this->actingAs($this->actor)->postJson(route('transactions.tracked-items.store'), ['tracked_item_uuid' => $this->source->uuid, 'account_uuid' => $shared->uuid, 'category_uuid' => $this->category->uuid, 'type_key' => 'expense'])->assertUnprocessable();
});

test('failed validation resolves inputs without writing to the database', function () {
    canonicalFlowAlias($this);
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $this->actingAs($this->actor)->postJson(route('transactions.store', ['year' => 2026, 'month' => 10]), canonicalFlowTransaction($this, ['amount' => -1]))->assertUnprocessable();
    expect($writes)->toBe([])->and(Transaction::count())->toBe(0);
});

test('shared catalog insertions resolve account aliases without touching historical personal references', function () {
    $editor = User::factory()->create();
    AccountMembership::create(['account_id' => $this->account->id, 'user_id' => $editor->id, 'role' => 'editor', 'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $this->actor->id, 'joined_at' => now()]);
    $legacy = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Legacy', 'slug' => 'legacy', 'is_active' => true]);
    $old = userTransaction($this->actor, $this->account, ['tracked_item_id' => $legacy->id, 'category_id' => $this->category->id]);
    $this->category->update(['account_id' => $this->account->id]);
    $this->source->update(['account_id' => $this->account->id]);
    $this->target->update(['account_id' => $this->account->id]);
    canonicalFlowAlias($this);
    $this->actingAs($this->actor)->post(route('transactions.store', ['year' => 2026, 'month' => 10]), canonicalFlowTransaction($this))->assertSessionHasNoErrors();
    expect(Transaction::where('description', 'Operazione canonica')->sole()->tracked_item_id)->toBe($this->target->id)
        ->and($old->fresh()->tracked_item_id)->toBe($legacy->id)->and($legacy->fresh()->account_id)->toBeNull();
});

test('identity changes after validation reject insertion and roll back the operation', function () {
    canonicalFlowAlias($this);
    $service = app(TrackedItemIdentityService::class);
    $validated = $service->forNewOperation(TrackedItem::query()->ownedBy($this->actor->id), $this->source->uuid);
    $third = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Terzo', 'slug' => 'third', 'is_active' => true]);
    $service->register($this->actor, $this->target, $third);
    $called = false;
    expect(fn () => $service->withNewReferences(['tracked_item_id' => $validated->id], function () use (&$called): void {
        $called = true;
    }))->toThrow(ValidationException::class);
    expect($called)->toBeFalse()->and(Transaction::count())->toBe(0);
    expect(fn () => $service->withNewReferences(['tracked_item_id' => $third->id], function (): void {
        TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Rollback', 'slug' => 'rollback', 'is_active' => true]);
        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);
    expect(TrackedItem::where('slug', 'rollback')->exists())->toBeFalse();
});

test('new templates use canonical references and do not rewrite existing templates', function () {
    $payload = ['name' => 'Originale', 'icon' => 'ShoppingCart', 'color' => 'primary', 'direction' => 'expense', 'account_uuid' => $this->account->uuid, 'category_uuid' => $this->category->uuid, 'tracked_item_uuid' => $this->source->uuid, 'shared_enabled' => false, 'shared_payer' => 'self', 'shared_split_mode' => 'equal', 'my_share_basis_points' => 5000, 'is_active' => true];
    $this->actingAs($this->actor)->post(route('transaction-templates.store'), $payload)->assertSessionHasNoErrors();
    $old = TransactionTemplate::sole();
    canonicalFlowAlias($this);
    $before = $old->getRawOriginal();
    $this->post(route('transaction-templates.store'), [...$payload, 'name' => 'Nuovo'])->assertSessionHasNoErrors();
    expect(TransactionTemplate::where('name', 'Nuovo')->sole()->tracked_item_id)->toBe($this->target->id)
        ->and($old->fresh()->getRawOriginal())->toEqual($before);
});

test('canonical lookup and new reference selection leave protected shared expense history intact', function () {
    $ledger = SharedExpenseLedgerEntry::create(['user_id' => $this->actor->id, 'counterparty_id' => $this->source->id, 'entry_date' => '2026-10-01', 'title' => 'Storico protetto', 'currency' => 'EUR', 'total_cents' => 10000, 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'paid_by' => 'counterparty', 'status' => 'recorded']);
    canonicalFlowAlias($this);
    $before = $ledger->fresh()->getRawOriginal();
    $resolver = app(OperationalTransactionCategoryResolver::class);
    $resolver->trackedItemOptionsFromCollection($resolver->trackedItemsForAccount($this->account, [$this->source->id]), $this->account);
    $this->actingAs($this->actor)->postJson(route('shared-expenses.counterparties.store'), ['tracked_item_uuid' => $this->source->uuid])->assertSuccessful()->assertJsonPath('uuid', $this->target->uuid);
    expect($ledger->fresh()->getRawOriginal())->toEqual($before)->and($ledger->fresh()->counterparty_id)->toBe($this->source->id);
});
