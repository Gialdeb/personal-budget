<?php

use App\Enums\CounterpartyObligationSourceEnum;
use App\Enums\CounterpartyPaymentDirectionEnum;
use App\Models\AccountMembership;
use App\Models\CounterpartyOperation;
use App\Models\CounterpartySourceLink;
use App\Models\CreditDebtItem;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\TrackedItemAlias;
use App\Models\User;
use App\Services\Counterparties\CounterpartyLedgerService;
use App\Services\Counterparties\CounterpartyProjectionService;
use App\Services\TrackedItems\TrackedItemIdentityService;
use App\Services\TrackedItems\TrackedItemResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @param array<string, mixed> $attributes */
function identityReference(User $owner, array $attributes = []): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $owner->id, 'name' => 'Riferimento '.Str::uuid(), 'slug' => (string) Str::uuid(),
        'is_active' => true, 'is_shared_expense_counterpart' => true, ...$attributes,
    ]);
}

function insertIdentityAlias(TrackedItem $source, TrackedItem $target, User $actor): void
{
    DB::table('tracked_item_aliases')->insert([
        'uuid' => (string) Str::uuid(), 'source_tracked_item_id' => $source->id,
        'canonical_tracked_item_id' => $target->id, 'created_by_user_id' => $actor->id, 'created_at' => now(),
    ]);
}

/** @return array<string, array<int, array<string, mixed>>> */
function identityEconomicSnapshot(): array
{
    return collect([
        'accounts', 'transactions', 'tracked_item_categories', 'recurring_entries', 'recurring_entry_occurrences',
        'scheduled_entries', 'budgets', 'transaction_templates', 'credit_debt_items', 'credit_debt_payments',
        'shared_expense_ledger_entries', 'shared_expense_settlements', 'counterparty_relations',
        'counterparty_obligations', 'counterparty_payments', 'counterparty_ledger_entries',
        'counterparty_allocation_entries', 'counterparty_operations', 'counterparty_source_links', 'counterparty_sync_issues',
    ])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all()])->all();
}

beforeEach(function () {
    $this->actor = User::factory()->create();
    $this->source = identityReference($this->actor);
    $this->target = identityReference($this->actor);
    $this->identities = app(TrackedItemIdentityService::class);
});

test('alias registration preserves original identities and records actor and time', function () {
    $sourceUuid = $this->source->uuid;
    $alias = $this->identities->register($this->actor, $this->source, $this->target);
    expect($alias->source->uuid)->toBe($sourceUuid)
        ->and($alias->canonical->id)->toBe($this->target->id)
        ->and($alias->createdBy->id)->toBe($this->actor->id)
        ->and($alias->created_at)->not->toBeNull()
        ->and($this->source->fresh()->is_active)->toBeFalse()
        ->and($this->target->fresh()->is_active)->toBeTrue()
        ->and($this->source->fresh()->identityAlias->id)->toBe($alias->id)
        ->and($this->target->incomingIdentityAliases->modelKeys())->toBe([$alias->id])
        ->and(TrackedItem::count())->toBe(2);
});

test('canonical resolution follows immutable chains and registration is idempotent', function () {
    $first = $this->identities->register($this->actor, $this->source, $this->target);
    $third = identityReference($this->actor);
    $this->identities->register($this->actor, $this->target, $third);
    $fourth = identityReference($this->actor);
    $direct = $this->identities->register($this->actor, $fourth, $this->source);
    expect($this->identities->canonicalFor($this->source)->id)->toBe($third->id)
        ->and($this->identities->canonicalFor($this->target)->id)->toBe($third->id)
        ->and($this->identities->canonicalFor($third)->id)->toBe($third->id)
        ->and($first->fresh()->canonical_tracked_item_id)->toBe($this->target->id)
        ->and($direct->canonical_tracked_item_id)->toBe($third->id)
        ->and($this->identities->register($this->actor, $this->source, $third)->id)->toBe($first->id)
        ->and(TrackedItemAlias::count())->toBe(3);
});

test('self aliases cycles and reassignment to a different canonical identity are rejected', function () {
    expect(fn () => $this->identities->register($this->actor, $this->source, $this->source))->toThrow(ValidationException::class);
    $this->identities->register($this->actor, $this->source, $this->target);
    $third = identityReference($this->actor);
    expect(fn () => $this->identities->register($this->actor, $this->target, $this->source))->toThrow(ValidationException::class)
        ->and(fn () => $this->identities->register($this->actor, $this->source, $third))->toThrow(ValidationException::class)
        ->and($this->target->fresh()->is_active)->toBeTrue()
        ->and(TrackedItemAlias::count())->toBe(1);
});

test('personal and account catalogs cannot be combined even for the same owner', function (bool $twoAccounts) {
    $firstAccount = createTestAccount($this->actor);
    $secondAccount = createTestAccount($this->actor);
    $this->source->update(['account_id' => $twoAccounts ? $firstAccount->id : null]);
    $this->target->update(['account_id' => $secondAccount->id]);
    expect(fn () => $this->identities->register($this->actor, $this->source, $this->target))->toThrow(ValidationException::class)
        ->and(TrackedItemAlias::count())->toBe(0)->and($this->source->fresh()->is_active)->toBeTrue();
})->with(['personal to account' => false, 'two account catalogs' => true]);

test('actors cannot alias personal references owned by someone else', function () {
    $foreign = identityReference(User::factory()->create());
    expect(fn () => $this->identities->register($this->actor, $this->source, $foreign))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->identities->register($this->actor, $foreign, $this->target))->toThrow(AuthorizationException::class)
        ->and(TrackedItemAlias::count())->toBe(0);
});

test('account alias registration requires current editing permission', function (string $role) {
    $account = createTestAccount($this->actor);
    $editor = User::factory()->create();
    $membership = AccountMembership::create(['account_id' => $account->id, 'user_id' => $editor->id, 'role' => $role, 'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $this->actor->id, 'joined_at' => now()]);
    $this->source->update(['account_id' => $account->id]);
    $this->target->update(['account_id' => $account->id]);
    if ($role === 'viewer') {
        expect(fn () => $this->identities->register($editor, $this->source, $this->target))->toThrow(AuthorizationException::class);
    } else {
        $alias = $this->identities->register($editor, $this->source, $this->target);
        expect($alias->created_by_user_id)->toBe($editor->id);
        $membership->update(['status' => 'revoked']);
        expect(fn () => $this->identities->register($editor, $this->source, $this->target))->toThrow(AuthorizationException::class);
    }
})->with(['editor', 'viewer']);

test('registration neither deactivates active children nor aliases to an inactive root', function () {
    $child = identityReference($this->actor, ['parent_id' => $this->source->id]);
    expect(fn () => $this->identities->register($this->actor, $this->source, $this->target))->toThrow(ValidationException::class);
    $child->update(['is_active' => false]);
    $this->target->update(['is_active' => false]);
    expect(fn () => $this->identities->register($this->actor, $this->source, $this->target))->toThrow(ValidationException::class)
        ->and($this->source->fresh()->is_active)->toBeTrue()->and(TrackedItemAlias::count())->toBe(0);
});

test('secondary references cannot be reactivated by models bulk writes or textual resolution', function () {
    $this->identities->register($this->actor, $this->source, $this->target);
    expect(fn () => $this->source->fresh()->update(['is_active' => true]))->toThrow(ValidationException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('tracked_items')->where('id', $this->source->id)->update(['is_active' => true])))->toThrow(QueryException::class)
        ->and(app(TrackedItemResolver::class)->resolve($this->actor->id, null, $this->source->name)->id)->toBe($this->target->id)
        ->and(app(TrackedItemResolver::class)->resolve($this->actor->id, null, null, $this->source->uuid)->id)->toBe($this->target->id)
        ->and($this->source->fresh()->is_active)->toBeFalse()->and(TrackedItem::count())->toBe(2);
});

test('alias records are immutable and their references cannot be deleted', function () {
    $alias = $this->identities->register($this->actor, $this->source, $this->target);
    expect(fn () => $alias->update(['canonical_tracked_item_id' => $this->source->id]))->toThrow(LogicException::class)
        ->and(fn () => $alias->delete())->toThrow(LogicException::class);
    foreach (['update', 'delete'] as $operation) {
        expect(fn () => DB::transaction(function () use ($alias, $operation): void {
            $query = DB::table('tracked_item_aliases')->where('id', $alias->id);
            $operation === 'update' ? $query->update(['canonical_tracked_item_id' => $this->source->id]) : $query->delete();
        }))->toThrow(QueryException::class);
    }
    foreach ([$this->source, $this->target] as $reference) {
        expect(fn () => DB::transaction(fn () => DB::table('tracked_items')->where('id', $reference->id)->delete()))->toThrow(QueryException::class);
    }
});

test('database guards reject invalid alias insertions independently of the service', function (string $invalid) {
    $this->source->update(['is_active' => false]);
    $target = $this->target;
    if ($invalid === 'self') {
        $target = $this->source;
    } elseif ($invalid === 'foreign owner') {
        $target = identityReference(User::factory()->create());
    } elseif ($invalid === 'foreign account') {
        $target->update(['account_id' => createTestAccount($this->actor)->id]);
    } elseif ($invalid === 'two accounts') {
        $this->source->update(['account_id' => createTestAccount($this->actor)->id]);
        $target->update(['account_id' => createTestAccount($this->actor)->id]);
    } elseif ($invalid === 'active source') {
        $this->source->update(['is_active' => true]);
    } elseif ($invalid === 'inactive target') {
        $target->update(['is_active' => false]);
    } else {
        $this->identities->register($this->actor, $target, identityReference($this->actor));
    }
    expect(fn () => DB::transaction(fn () => insertIdentityAlias($this->source, $target, $this->actor)))->toThrow(QueryException::class);
})->with(['self', 'foreign owner', 'foreign account', 'two accounts', 'active source', 'inactive target', 'secondary target']);

test('catalog and public identity changes are blocked for both ends of an alias', function (string $field) {
    $this->identities->register($this->actor, $this->source, $this->target);
    $value = match ($field) {
        'account_id' => createTestAccount($this->actor)->id,
        'user_id' => User::factory()->create()->id,
        'uuid' => (string) Str::uuid(),
    };
    foreach ([$this->source, $this->target] as $reference) {
        expect(fn () => DB::transaction(fn () => DB::table('tracked_items')->where('id', $reference->id)->update([$field => $value])))->toThrow(QueryException::class);
    }
})->with(['account_id', 'user_id', 'uuid']);

test('transaction rollback removes the alias and restores source activation', function () {
    $before = identityEconomicSnapshot();
    expect(fn () => DB::transaction(function (): void {
        $this->identities->register($this->actor, $this->source, $this->target);
        throw new RuntimeException('Abort the whole operation');
    }))->toThrow(RuntimeException::class, 'Abort the whole operation');
    expect(TrackedItemAlias::count())->toBe(0)->and($this->source->fresh()->is_active)->toBeTrue()
        ->and(identityEconomicSnapshot())->toBe($before);
});

test('a failed alias insert rolls back the preceding deactivation', function () {
    $event = 'eloquent.creating: '.TrackedItemAlias::class;
    $dispatcher = TrackedItemAlias::getEventDispatcher();
    $original = clone $dispatcher;
    $dispatcher->listen($event, fn () => throw new RuntimeException('Simulated alias insertion failure'));
    try {
        expect(fn () => $this->identities->register($this->actor, $this->source, $this->target))->toThrow(RuntimeException::class, 'Simulated alias insertion failure');
        expect($this->source->fresh()->is_active)->toBeTrue()->and(TrackedItemAlias::count())->toBe(0);
    } finally {
        TrackedItemAlias::setEventDispatcher($original);
    }
});

test('migration rollback refuses to discard persistent identity links', function () {
    $this->identities->register($this->actor, $this->source, $this->target);
    $migration = require base_path('database/migrations/2026_10_17_144737_create_tracked_item_aliases_table.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Cannot remove persisted tracked item aliases')
        ->and(TrackedItemAlias::count())->toBe(1);
});

test('alias infrastructure preserves protected economic records and balances byte for byte', function () {
    config(['counterparty_ledger.shadow_manual_enabled' => false]);
    $account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $manual = CreditDebtItem::factory()->forAccount($account)->create(['reference_id' => $this->source->id, 'counterparty_id' => $this->source->id]);
    $transaction = userTransaction($this->actor, $account, ['tracked_item_id' => $this->source->id, 'shared_expense_counterparty_id' => $this->source->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
    $ledger = app(CounterpartyLedgerService::class);
    $relation = $ledger->relation($this->actor, $this->source, 'EUR');
    $obligation = $ledger->recognize($relation, CounterpartyObligationSourceEnum::CREDIT_DEBT_ITEM, $manual->uuid, $manual->uuid, 10000, '2026-10-01', 'recognition');
    $payment = $ledger->recordPayment($relation, CounterpartyPaymentDirectionEnum::RECEIVED, 2500, '2026-10-02', $account, 'payment');
    $ledger->allocate($payment, $obligation, 2500, '2026-10-02', 'allocation');
    CounterpartySourceLink::create(['relation_id' => $relation->id, 'source_type' => 'credit_debt_item', 'source_identity' => $manual->uuid, 'economic_identity' => $manual->uuid]);
    CounterpartyOperation::create(['relation_id' => $relation->id, 'operation_key' => 'preserved-operation', 'payload_hash' => str_repeat('a', 64), 'kind' => 'payment', 'actor_id' => $this->actor->id, 'result' => ['payment_uuid' => $payment->uuid]]);
    SharedExpenseLedgerEntry::create(['user_id' => $this->actor->id, 'counterparty_id' => $this->source->id, 'entry_date' => '2026-10-01', 'title' => 'Spesa storica', 'currency' => 'EUR', 'total_cents' => 10000, 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'paid_by' => 'counterparty', 'status' => 'recorded']);
    SharedExpenseSettlement::create(['user_id' => $this->actor->id, 'counterparty_id' => $this->source->id, 'amount_cents' => 1000, 'direction' => 'received', 'settled_at' => '2026-10-02', 'transaction_id' => $transaction->id]);
    $before = identityEconomicSnapshot();
    $balance = app(CounterpartyProjectionService::class)->balance($relation);
    $this->identities->register($this->actor, $this->source, $this->target);
    expect(identityEconomicSnapshot())->toBe($before)
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe($balance)
        ->and($manual->fresh()->reference_id)->toBe($this->source->id)
        ->and($relation->fresh()->counterparty_id)->toBe($this->source->id)
        ->and($transaction->fresh()->tracked_item_id)->toBe($this->source->id);
});

test('identity factory produces a valid persistent link in an isolated personal catalog', function () {
    $alias = TrackedItemAlias::factory()->create();
    expect($this->identities->canonicalFor($alias->source)->id)->toBe($alias->canonical_tracked_item_id)
        ->and($alias->source->is_active)->toBeFalse()
        ->and($alias->canonical->user_id)->toBe($alias->created_by_user_id);
});

test('empty identity schema can be rolled back and reapplied without changing financial records', function () {
    $before = identityEconomicSnapshot();
    $migration = require base_path('database/migrations/2026_10_17_144737_create_tracked_item_aliases_table.php');
    $migration->down();
    try {
        expect(Schema::hasTable('tracked_item_aliases'))->toBeFalse();
    } finally {
        $migration->up();
    }
    expect(identityEconomicSnapshot())->toBe($before)->and(TrackedItemAlias::count())->toBe(0);
});
