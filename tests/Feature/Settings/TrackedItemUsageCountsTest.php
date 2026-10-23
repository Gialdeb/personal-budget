<?php

use App\Models\CreditDebtItem;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringOccurrencePaymentAllocation;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\TrackedItemAlias;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\TrackedItems\MoveTrackedItemMovementsService;
use App\Services\TrackedItems\TrackedItemUsageReadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false, 'counterparty_ledger.shadow_manual_enabled' => false]);
    $this->actor = User::factory()->create();
    $this->account = createTestAccount($this->actor);
    $this->reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Provider', 'slug' => 'provider', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->other = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Provider', 'slug' => 'provider-other', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($this->actor);
});

function usagePlan(object $test): RecurringEntry
{
    return RecurringEntry::create(['user_id' => $test->actor->id, 'account_id' => $test->account->id, 'tracked_item_id' => $test->reference->id, 'title' => 'Plan', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'expected_amount' => 20]);
}

function usageOccurrence(RecurringEntry $plan, int $sequence = 1): RecurringEntryOccurrence
{
    return RecurringEntryOccurrence::create(['recurring_entry_id' => $plan->id, 'account_id' => $plan->account_id, 'sequence_number' => $sequence, 'expected_date' => '2026-10-01', 'due_date' => '2026-10-01', 'expected_amount' => 20, 'status' => 'pending']);
}

function usageCounts(object $test): array
{
    return app(TrackedItemUsageReadService::class)->counts($test->actor->id, TrackedItem::query()->ownedBy($test->actor->id)->get());
}

test('direct usage is deduplicated by entity and matches the move preview without changing records', function () {
    userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->reference->id, 'shared_expense_counterparty_id' => $this->reference->id]);
    TransactionTemplate::factory()->create(['user_id' => $this->actor->id, 'tracked_item_id' => $this->reference->id, 'shared_counterparty_id' => $this->reference->id]);
    CreditDebtItem::factory()->forAccount($this->account)->create(['reference_id' => $this->reference->id, 'counterparty_id' => $this->reference->id]);
    SharedExpenseLedgerEntry::create(['user_id' => $this->actor->id, 'counterparty_id' => $this->reference->id, 'entry_date' => '2026-10-01', 'title' => 'External', 'currency' => 'EUR', 'total_cents' => 2000, 'my_share_cents' => 1000, 'counterparty_share_cents' => 1000, 'paid_by' => 'counterparty', 'status' => 'recorded']);
    $plan = usagePlan($this);
    $plan->update(['shared_expense_counterparty_id' => $this->reference->id]);
    $occurrence = usageOccurrence($plan);
    userTransaction($this->actor, $this->account, ['tracked_item_id' => null, 'recurring_entry_occurrence_id' => $occurrence->id]);
    $preview = app(MoveTrackedItemMovementsService::class)->preview($this->actor, $this->reference->uuid, $this->other->uuid);
    $counts = usageCounts($this)[$this->reference->id]['link_counts'];
    expect($counts['direct'])->toBe($preview['count'])->toBe(5)->and($counts['total'])->toBe(7);
    expect(collect($counts['groups'])->keyBy('key')->get('shared')['total'])->toBe(2);
    expect(usageCounts($this)[$this->other->id]['usage_count'])->toBe(0);
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $response = $this->getJson(route('tracked-items.edit'))->assertSuccessful();
    $item = collect($response->json('trackedItems.flat'))->firstWhere('uuid', $this->reference->uuid);
    expect($item['usage_count'])->toBe(7)->and($item['link_counts'])->toBe($counts)->and($writes)->toBe([]);
    if (getenv('TRACKED_ITEM_USAGE_FIXTURE')) {
        file_put_contents(getenv('TRACKED_ITEM_USAGE_FIXTURE'), json_encode($response->json(), JSON_THROW_ON_ERROR));
    }
});

test('occurrences and indirect payments are separate and the same payment is never counted twice', function () {
    $plan = usagePlan($this);
    $occurrence = usageOccurrence($plan);
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => null, 'recurring_entry_occurrence_id' => $occurrence->id]);
    $occurrence->update(['converted_transaction_id' => $transaction->id, 'matched_transaction_id' => $transaction->id]);
    $counts = usageCounts($this)[$this->reference->id]['link_counts'];
    expect($counts['direct'])->toBe(1)->and($counts['indirect'])->toBe(2)->and($counts['total'])->toBe(3);
    $groups = collect($counts['groups'])->keyBy('key');
    expect($groups['transactions']['indirect'])->toBe(1)->and($groups['occurrences']['total'])->toBe(1)->and($groups['recurring_entries']['direct'])->toBe(1);
});

test('historical payments attributed to another reference are not assigned to the current plan reference', function () {
    $plan = usagePlan($this);
    for ($sequence = 1; $sequence <= 6; $sequence++) {
        $occurrence = usageOccurrence($plan, $sequence);
        $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->other->id, 'recurring_entry_occurrence_id' => $occurrence->id]);
        $occurrence->update(['converted_transaction_id' => $transaction->id]);
    }
    $counts = usageCounts($this);
    $groups = collect($counts[$this->reference->id]['link_counts']['groups'])->keyBy('key');
    expect($groups->has('transactions'))->toBeFalse()->and($groups['recurring_entries']['total'])->toBe(1)->and($groups['occurrences']['total'])->toBe(6);
    expect(collect($counts[$this->other->id]['link_counts']['groups'])->keyBy('key')['transactions']['total'])->toBe(6);
});

test('deleted transactions remain counted and explicitly distinguished while inaccessible accounts are excluded', function () {
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->reference->id]);
    $transaction->delete();
    $outsider = User::factory()->create();
    $outsideAccount = createTestAccount($outsider);
    userTransaction($outsider, $outsideAccount, ['tracked_item_id' => $this->reference->id]);
    $counts = usageCounts($this)[$this->reference->id]['link_counts'];
    expect($counts['total'])->toBe(1)->and($counts['groups'][0]['archived'])->toBe(1);
});

test('parents homonyms and permanent aliases keep separate record counts', function () {
    $child = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Child', 'slug' => 'child', 'parent_id' => $this->reference->id, 'is_active' => true]);
    userTransaction($this->actor, $this->account, ['tracked_item_id' => $child->id]);
    $this->other->update(['is_active' => false]);
    TrackedItemAlias::create(['source_tracked_item_id' => $this->other->id, 'canonical_tracked_item_id' => $this->reference->id, 'created_by_user_id' => $this->actor->id]);
    $counts = usageCounts($this);
    $groups = collect($counts[$this->reference->id]['link_counts']['groups'])->keyBy('key');
    expect($groups->has('transactions'))->toBeFalse()->and($groups['tracked_items']['total'])->toBe(1)->and($groups['tracked_item_aliases']['total'])->toBe(1);
    expect($counts[$child->id]['link_counts']['direct'])->toBe(1)->and($counts[$this->other->id]['link_counts']['direct'])->toBe(1);
});

test('a payment allocated to several occurrences counts once while occurrences remain distinct', function () {
    $plan = usagePlan($this);
    $first = usageOccurrence($plan);
    $second = usageOccurrence($plan, 2);
    $transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => null, 'amount' => 40]);
    foreach ([$first, $second] as $occurrence) {
        RecurringOccurrencePaymentAllocation::create(['occurrence_id' => $occurrence->id, 'transaction_id' => $transaction->id, 'amount_cents' => 2000]);
    }
    $counts = usageCounts($this)[$this->reference->id]['link_counts'];
    expect($counts['total'])->toBe(4)->and($counts['direct'])->toBe(1)->and($counts['indirect'])->toBe(3);
    $groups = collect($counts['groups'])->keyBy('key');
    expect($groups['transactions']['total'])->toBe(1)->and($groups['occurrences']['total'])->toBe(2);
});

test('pending imports count once and obey statuses ownership and account access', function () {
    $import = Import::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'status' => 'review_required', 'source_type' => 'csv', 'original_filename' => 'test.csv', 'stored_filename' => 'test.csv']);
    $payload = ['tracked_item_id' => $this->reference->id, 'tracked_item_uuid' => $this->reference->uuid, 'account_id' => $this->account->id];
    foreach (['ready', 'imported', 'skipped'] as $index => $status) {
        ImportRow::create(['import_id' => $import->id, 'row_index' => $index, 'status' => $status, 'parse_status' => 'parsed', 'raw_payload' => [], 'normalized_payload' => $payload]);
    }
    $other = User::factory()->create();
    $outside = createTestAccount($other);
    ImportRow::create(['import_id' => $import->id, 'row_index' => 4, 'status' => 'ready', 'parse_status' => 'parsed', 'raw_payload' => [], 'normalized_payload' => [...$payload, 'account_id' => $outside->id]]);
    $counts = usageCounts($this)[$this->reference->id]['link_counts'];
    expect($counts['direct'])->toBe(1)->and($counts['groups'][0]['key'])->toBe('import_rows');
    $preview = app(MoveTrackedItemMovementsService::class)->preview($this->actor, $this->reference->uuid, $this->other->uuid);
})->throws(ValidationException::class, 'Il conto di un’importazione non è modificabile.');

test('only explicit aliases expose historical links indirectly without merging homonymous or child records', function () {
    userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->other->id]);
    $this->other->update(['is_active' => false]);
    TrackedItemAlias::create(['source_tracked_item_id' => $this->other->id, 'canonical_tracked_item_id' => $this->reference->id, 'created_by_user_id' => $this->actor->id]);
    $counts = usageCounts($this);
    $canonical = $counts[$this->reference->id]['link_counts'];
    $alias = $counts[$this->other->id]['link_counts'];
    expect($canonical['direct'])->toBe(1)->and($canonical['indirect'])->toBe(1)->and($canonical['total'])->toBe(2);
    expect(collect($canonical['groups'])->keyBy('key')['transactions']['indirect'])->toBe(1);
    expect($alias['direct'])->toBe(2)->and($alias['indirect'])->toBe(0);
});
