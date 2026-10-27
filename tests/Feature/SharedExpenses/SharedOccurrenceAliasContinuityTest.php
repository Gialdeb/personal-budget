<?php

use App\Enums\CounterpartyObligationSourceEnum;
use App\Enums\CounterpartyPaymentDirectionEnum;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartySourceLink;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\CounterpartyLedgerService;
use App\Services\Counterparties\SharedSourceProjector;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\SharedExpenses\SharedExpenseOccurrencePostingService;
use App\Services\SharedExpenses\SharedOccurrenceCounterpartyResolver;
use App\Services\TrackedItems\TrackedItemIdentityService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['counterparty_ledger.shadow_shared_enabled' => true]);
    $this->actor = User::factory()->create(['base_currency_code' => 'EUR']);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $this->source = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Origine', 'slug' => 'origin', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->target = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Canonico', 'slug' => 'canonical', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
});

function continuityOccurrence(object $test, string $payer = 'self', int $sequence = 1, ?RecurringEntry $entry = null): RecurringEntryOccurrence
{
    $entry ??= RecurringEntry::create(['user_id' => $test->actor->id, 'account_id' => $test->account->id, 'title' => 'Ricorrenza condivisa', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true, 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'expected_amount' => 100, 'shared_expense_counterparty_id' => $test->source->id, 'shared_expense_paid_by' => $payer, 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);

    return $entry->occurrences()->create(['sequence_number' => $sequence, 'expected_date' => '2026-10-01', 'due_date' => '2026-10-01', 'expected_amount' => 100, 'status' => 'pending']);
}

function continuityComponent(object $test, RecurringEntryOccurrence $occurrence, array $overrides = []): Transaction
{
    return Transaction::create(['user_id' => $test->actor->id, 'account_id' => $test->account->id, 'transaction_date' => '2026-10-01', 'amount' => '40.00', 'currency' => 'EUR', 'direction' => 'expense', 'kind' => 'scheduled', 'source_type' => 'generated', 'status' => 'confirmed', 'balance_after' => 960, 'recurring_entry_occurrence_id' => $occurrence->id, 'shared_expense_counterparty_id' => $test->source->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 2000, 'shared_expense_counterparty_share_cents' => 2000, ...$overrides]);
}

/** @return array<string, array<int, object>> */
function continuitySnapshot(): array
{
    $tables = ['transactions', 'shared_expense_ledger_entries', 'counterparty_relations', 'counterparty_obligations', 'counterparty_source_links', 'counterparty_ledger_entries', 'counterparty_payments', 'counterparty_allocation_entries', 'recurring_entry_occurrences', 'accounts'];

    return collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->all()])->all();
}

test('a partial shared bank occurrence continues on the original identity and a new due date uses the canonical', function () {
    $occurrence = continuityOccurrence($this);
    $first = continuityComponent($this, $occurrence);
    $obligation = CounterpartyObligation::sole();
    $links = DB::table('counterparty_source_links')->get()->all();
    $ledger = DB::table('counterparty_ledger_entries')->get()->all();
    $original = $first->fresh()->getRawOriginal();
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    $entry = $occurrence->recurringEntry;
    $entry->update(['shared_expense_counterparty_id' => $this->target->id]);
    $second = app(RecurringEntryPostingService::class)->post($occurrence, $this->actor);
    expect($second->shared_expense_counterparty_id)->toBe($this->source->id)
        ->and($second->amount)->toBe('60.00')->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and(CounterpartyObligation::sole()->id)->toBe($obligation->id)
        ->and($first->fresh()->getRawOriginal())->toEqual($original)
        ->and(DB::table('counterparty_source_links')->whereIn('id', array_column($links, 'id'))->get()->all())->toEqual($links)
        ->and(DB::table('counterparty_ledger_entries')->whereIn('id', array_column($ledger, 'id'))->get()->all())->toEqual($ledger);
    $next = continuityOccurrence($this, sequence: 2, entry: $entry);
    $new = app(RecurringEntryPostingService::class)->post($next, $this->actor);
    expect($new->shared_expense_counterparty_id)->toBe($this->target->id)
        ->and($this->source->fresh()->is_active)->toBeFalse();
});

test('partial external occurrence preserves its original source link and new occurrences use the canonical', function (bool $clearPlanParty) {
    $occurrence = continuityOccurrence($this, 'counterparty');
    $posting = app(SharedExpenseOccurrencePostingService::class);
    $first = $posting->post($occurrence, $this->actor, ['amount' => '40.00']);
    $link = CounterpartySourceLink::sole()->getRawOriginal();
    $obligation = CounterpartyObligation::sole()->getRawOriginal();
    $balance = $this->account->fresh()->current_balance;
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    $occurrence->recurringEntry->update(['shared_expense_counterparty_id' => $clearPlanParty ? null : $this->target->id]);
    $continued = $posting->post($occurrence, $this->actor, ['amount' => '60.00']);
    expect($continued->id)->toBe($first->id)->and($continued->counterparty_id)->toBe($this->source->id)
        ->and($continued->total_cents)->toBe(10000)->and(CounterpartySourceLink::sole()->getRawOriginal())->toEqual($link)
        ->and(CounterpartyObligation::sole()->getRawOriginal())->toEqual($obligation)
        ->and($this->account->fresh()->current_balance)->toBe($balance);
    $occurrence->recurringEntry->update(['shared_expense_counterparty_id' => $this->source->id]);
    $next = continuityOccurrence($this, 'counterparty', 2, $occurrence->recurringEntry);
    expect($posting->post($next, $this->actor)->counterparty_id)->toBe($this->target->id);
})->with([false, true]);

test('an untouched occurrence with a secondary plan counterparty resolves the canonical', function (string $payer) {
    $occurrence = continuityOccurrence($this, $payer);
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    $record = $payer === 'self' ? app(RecurringEntryPostingService::class)->post($occurrence, $this->actor) : app(SharedExpenseOccurrencePostingService::class)->post($occurrence, $this->actor);
    expect($record instanceof Transaction ? $record->shared_expense_counterparty_id : $record->counterparty_id)->toBe($this->target->id)
        ->and($occurrence->recurringEntry->fresh()->shared_expense_counterparty_id)->toBe($this->source->id);
})->with(['bank' => ['self'], 'external' => ['counterparty']]);

test('merging duplicate people retains the shared role for future payments without rewriting history', function (string $payer) {
    $this->source->update(['name' => 'Mario Rossi', 'slug' => 'mario-rossi-old']);
    $this->target->update(['name' => 'Mario Rossi', 'slug' => 'mario-rossi', 'is_shared_expense_counterpart' => false]);
    $historical = continuityOccurrence($this, $payer);
    $entry = $historical->recurringEntry;
    $post = fn (RecurringEntryOccurrence $occurrence, array $metadata = []): Transaction|SharedExpenseLedgerEntry => $payer === 'self'
        ? app(RecurringEntryPostingService::class)->post($occurrence, $this->actor, $metadata)
        : app(SharedExpenseOccurrencePostingService::class)->post($occurrence, $this->actor, $metadata);
    $record = $post($historical);
    $recordBefore = $record->fresh()->getRawOriginal();
    $partial = continuityOccurrence($this, sequence: 2, entry: $entry);
    continuityComponent($this, $partial);
    $next = continuityOccurrence($this, $payer, 3, $entry);
    $before = continuitySnapshot();
    $planBefore = $entry->fresh()->getRawOriginal();

    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);

    expect(continuitySnapshot())->toEqual($before)
        ->and($entry->fresh()->getRawOriginal())->toEqual($planBefore)
        ->and(app(SharedOccurrenceCounterpartyResolver::class)->historicalIdentity($historical)['counterparty_id'])->toBe($this->source->id)
        ->and(app(SharedOccurrenceCounterpartyResolver::class)->forPosting($partial)->id)->toBe($this->source->id);

    $new = $post($next);
    expect($this->target->fresh()->is_shared_expense_counterpart)->toBeTrue();
    expect($new instanceof Transaction ? $new->shared_expense_counterparty_id : $new->counterparty_id)->toBe($this->target->id)
        ->and($record->fresh()->getRawOriginal())->toEqual($recordBefore);
})->with(['bank' => ['self'], 'external' => ['counterparty']]);

test('persisted source links or obligations anchor an occurrence even without components', function (string $evidence) {
    $occurrence = continuityOccurrence($this);
    $ledger = app(CounterpartyLedgerService::class);
    $relation = $ledger->relation($this->actor, $this->source, 'EUR');
    if ($evidence === 'link') {
        CounterpartySourceLink::create(['relation_id' => $relation->id, 'source_type' => 'shared_transaction_share', 'source_identity' => fake()->uuid(), 'economic_identity' => 'shared-occurrence:'.$occurrence->uuid]);
    } else {
        $ledger->recognize($relation, CounterpartyObligationSourceEnum::SHARED_TRANSACTION, $occurrence->uuid, 'shared-occurrence:'.$occurrence->uuid, 5000, '2026-10-01', 'original');
    }
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    expect(app(RecurringEntryPostingService::class)->post($occurrence, $this->actor)->shared_expense_counterparty_id)->toBe($this->source->id);
})->with(['link', 'obligation']);

test('historical components remain authoritative after deletion or a full refund', function (string $history) {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $occurrence = continuityOccurrence($this);
    $component = continuityComponent($this, $occurrence);
    if ($history === 'deleted') {
        $component->delete();
    } else {
        Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'transaction_date' => '2026-10-02', 'amount' => '40.00', 'currency' => 'EUR', 'direction' => 'income', 'kind' => 'refund', 'source_type' => 'manual', 'status' => 'confirmed', 'refunded_transaction_id' => $component->id]);
    }
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    expect(app(RecurringEntryPostingService::class)->post($occurrence, $this->actor)->shared_expense_counterparty_id)->toBe($this->source->id);
})->with(['deleted', 'refunded']);

test('all occurrence components are checked before party grouping and conflicts roll back', function (string $conflict, string $payer) {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $occurrence = continuityOccurrence($this, $payer);
    $first = continuityComponent($this, $occurrence, ['amount' => '10.00']);
    $other = continuityComponent($this, $occurrence, ['amount' => '10.00']);
    $attributes = $conflict === 'currency' ? ['currency' => 'USD'] : ['shared_expense_counterparty_id' => $this->target->id];
    DB::table('transactions')->where('id', $other->id)->update($attributes);
    $before = continuitySnapshot();
    expect(fn () => app(SharedSourceProjector::class)->describe($first->fresh()))->toThrow(ValidationException::class);
    expect(fn () => $payer === 'self' ? app(RecurringEntryPostingService::class)->post($occurrence, $this->actor) : app(SharedExpenseOccurrencePostingService::class)->post($occurrence, $this->actor, ['amount' => '10.00']))->toThrow(ValidationException::class);
    expect(continuitySnapshot())->toEqual($before);
})->with(['currency', 'counterparty'])->with(['bank' => ['self'], 'external' => ['counterparty']]);

test('foreign historical identities are rejected without changing economic records', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $occurrence = continuityOccurrence($this);
    $component = continuityComponent($this, $occurrence);
    $foreign = TrackedItem::create(['user_id' => User::factory()->create()->id, 'name' => 'Altro catalogo', 'slug' => 'foreign', 'is_active' => true]);
    DB::table('transactions')->where('id', $component->id)->update(['shared_expense_counterparty_id' => $foreign->id]);
    $before = continuitySnapshot();
    expect(fn () => app(RecurringEntryPostingService::class)->post($occurrence, $this->actor))->toThrow(ValidationException::class);
    expect(continuitySnapshot())->toEqual($before);
});

test('existing payments and allocations stay on the original relation when a partial occurrence continues', function () {
    $occurrence = continuityOccurrence($this, 'counterparty');
    $posting = app(SharedExpenseOccurrencePostingService::class);
    $posting->post($occurrence, $this->actor, ['amount' => '40.00']);
    $obligation = CounterpartyObligation::sole();
    $ledger = app(CounterpartyLedgerService::class);
    $payment = $ledger->recordPayment($obligation->relation, CounterpartyPaymentDirectionEnum::SENT, 500, '2026-10-01', $this->account, 'payment');
    $ledger->allocate($payment, $obligation, 500, '2026-10-01', 'allocation');
    $tables = ['counterparty_payments', 'counterparty_allocation_entries', 'counterparty_source_links'];
    $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->get()->all()])->all();
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    $continued = $posting->post($occurrence, $this->actor, ['amount' => '60.00']);
    expect($continued->counterparty_id)->toBe($this->source->id)
        ->and(CounterpartyObligation::sole()->relation_id)->toBe($obligation->relation_id);
    foreach ($tables as $table) {
        expect(DB::table($table)->get()->all())->toEqual($before[$table]);
    }
});

test('occurrence identity inspection and projection never write or reactivate aliases', function () {
    $occurrence = continuityOccurrence($this);
    $component = continuityComponent($this, $occurrence);
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    expect(app(SharedOccurrenceCounterpartyResolver::class)->historicalIdentity($occurrence)['counterparty_id'])->toBe($this->source->id)
        ->and(app(SharedSourceProjector::class)->describe($component)['counterparty_id'])->toBe($this->source->id)
        ->and($writes)->toBe([])->and($this->source->fresh()->is_active)->toBeFalse();
});

test('new occurrences reject an archived canonical while historical continuations remain usable', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $occurrence = continuityOccurrence($this);
    continuityComponent($this, $occurrence);
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    $this->target->update(['is_active' => false]);
    expect(app(RecurringEntryPostingService::class)->post($occurrence, $this->actor)->shared_expense_counterparty_id)->toBe($this->source->id);
    $next = continuityOccurrence($this, sequence: 2, entry: $occurrence->recurringEntry);
    $before = continuitySnapshot();
    expect(fn () => app(RecurringEntryPostingService::class)->post($next, $this->actor))->toThrow(ValidationException::class);
    expect(continuitySnapshot())->toEqual($before);
});

test('a later balance validation failure rolls back the new document and all economic side effects', function () {
    $this->account->update(['opening_balance' => 0, 'current_balance' => 0]);
    $occurrence = continuityOccurrence($this);
    app(TrackedItemIdentityService::class)->register($this->actor, $this->source, $this->target);
    $before = continuitySnapshot();
    expect(fn () => app(RecurringEntryPostingService::class)->post($occurrence, $this->actor))->toThrow(ValidationException::class);
    expect(continuitySnapshot())->toEqual($before);
});
