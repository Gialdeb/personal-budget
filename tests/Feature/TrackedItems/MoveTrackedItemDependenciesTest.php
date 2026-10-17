<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\RecurringEntry;
use App\Models\ScheduledEntry;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\TrackedItems\MoveTrackedItemMovementsService;
use App\Services\TrackedItems\TrackedItemMovementGraph;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['counterparty_ledger.shadow_shared_enabled' => false, 'counterparty_ledger.shadow_manual_enabled' => false]);
    $this->actor = User::factory()->create();
    $this->account = createTestAccount($this->actor);
    $this->source = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Origine', 'slug' => 'source', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->target = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Destinazione', 'slug' => 'target', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($this->actor);
});

function allReferenceDependencies(object $test, TrackedItem $reference): RecurringEntry
{
    userTransaction($test->actor, $test->account, ['description' => 'Transazione normale', 'tracked_item_id' => $reference->id]);
    userTransaction($test->actor, $test->account, ['description' => 'Condivisa', 'tracked_item_id' => $reference->id, 'shared_expense_counterparty_id' => $reference->id]);
    $plan = RecurringEntry::create(['user_id' => $test->actor->id, 'account_id' => $test->account->id, 'title' => 'Piano', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'expected_amount' => 100, 'tracked_item_id' => $reference->id]);
    ScheduledEntry::create(['user_id' => $test->actor->id, 'account_id' => $test->account->id, 'title' => 'Pianificato', 'direction' => 'expense', 'expected_amount' => 25, 'currency' => 'EUR', 'scheduled_date' => '2026-11-01', 'status' => 'planned', 'tracked_item_id' => $reference->id]);
    $category = Category::create(['user_id' => $test->actor->id, 'name' => 'Categoria', 'slug' => (string) Str::uuid(), 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true]);
    Budget::create(['user_id' => $test->actor->id, 'category_id' => $category->id, 'year' => 2026, 'month' => 10, 'amount' => 100, 'tracked_item_id' => $reference->id]);
    TransactionTemplate::factory()->create(['user_id' => $test->actor->id, 'tracked_item_id' => $reference->id, 'shared_counterparty_id' => $reference->id]);
    CreditDebtItem::factory()->forAccount($test->account)->create(['reference_id' => $reference->id, 'counterparty_id' => $reference->id]);
    SharedExpenseLedgerEntry::create(['user_id' => $test->actor->id, 'counterparty_id' => $reference->id, 'entry_date' => '2026-10-01', 'title' => 'Condivisa esterna', 'currency' => 'EUR', 'total_cents' => 10000, 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'paid_by' => 'counterparty', 'status' => 'recorded']);

    return $plan;
}

function allDependencyRows(): array
{
    return collect(['transactions', 'recurring_entries', 'scheduled_entries', 'budgets', 'transaction_templates', 'credit_debt_items', 'shared_expense_ledger_entries'])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->all()])->all();
}

function completeReferenceMove(object $test): object
{
    $service = app(MoveTrackedItemMovementsService::class);
    $preview = $service->preview($test->actor, $test->source->uuid, $test->target->uuid);
    expect($preview['block_reason'])->toBeNull();
    $service->confirm($test->actor, $test->source->uuid, $test->target->uuid, $preview['preview_token']);

    return DB::table('tracked_item_transaction_moves')->orderByDesc('id')->first();
}

test('complete preview lists each record once and moves all references without renaming or economic changes', function () {
    allReferenceDependencies($this, $this->source);
    $before = allDependencyRows();
    $preview = app(MoveTrackedItemMovementsService::class)->preview($this->actor, $this->source->uuid, $this->target->uuid);
    expect($preview['count'])->toBe(8)->and(collect($preview['groups'])->sum('count'))->toBe(8)
        ->and(collect($preview['groups'])->firstWhere('key', 'shared')['count'])->toBe(2)
        ->and(collect($preview['groups'])->firstWhere('key', 'recurring_entries')['items'][0]['title'])->toBe('Piano');
    $move = completeReferenceMove($this);
    expect($move->scope_version)->toBe(2)->and($move->record_count)->toBe(8)->and(DB::table('tracked_item_move_records')->count())->toBe(8);
    $allowed = ['tracked_item_id', 'shared_expense_counterparty_id', 'shared_counterparty_id', 'reference_id', 'counterparty_id'];
    foreach ($before as $table => $rows) {
        foreach ($rows as $row) {
            $after = DB::table($table)->where('id', $row->id)->first();
            $changed = array_diff_assoc((array) $after, (array) $row);
            expect($changed)->not->toBeEmpty()->and(array_diff(array_keys($changed), $allowed))->toBe([]);
            foreach ($changed as $value) {
                expect($value)->toBe($this->target->id);
            }
        }
    }
    $sourceSets = app(TrackedItemMovementGraph::class)->collect($this->source);
    expect(collect($sourceSets)->sum(fn ($rows) => $rows->count()))->toBe(0);
    $this->postJson(route('tracked-items.movements.restore', $move->uuid), ['confirmed' => true])->assertSuccessful();
    expect(allDependencyRows())->toEqual($before);
});

test('restore changes only originally recorded entities leaving every later type untouched', function () {
    allReferenceDependencies($this, $this->source);
    $original = allDependencyRows();
    $move = completeReferenceMove($this);
    allReferenceDependencies($this, $this->target);
    $later = allDependencyRows();
    $this->postJson(route('tracked-items.movements.restore', $move->uuid), ['confirmed' => true])->assertSuccessful();
    foreach ($original as $table => $rows) {
        $ids = collect($rows)->pluck('id');
        expect(DB::table($table)->whereIn('id', $ids)->orderBy('id')->get()->all())->toEqual($rows);
        expect(DB::table($table)->whereNotIn('id', $ids)->orderBy('id')->get()->all())->toEqual(collect($later[$table])->reject(fn (object $row) => $ids->contains($row->id))->values()->all());
    }
});

test('new occurrences on a moved plan block restore instead of indirectly changing their reference', function () {
    $plan = allReferenceDependencies($this, $this->source);
    $move = completeReferenceMove($this);
    $plan->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-11-01', 'due_date' => '2026-11-01', 'expected_amount' => 100, 'status' => 'pending']);
    $before = allDependencyRows();
    $this->postJson(route('tracked-items.movements.restore', $move->uuid), ['confirmed' => true])->assertUnprocessable();
    expect(allDependencyRows())->toEqual($before);
    $this->getJson(route('tracked-items.movements.history'))->assertSuccessful()->assertJsonPath('data.0.can_restore', false);
});

test('a changed recurring record invalidates the entire signed preview', function () {
    $plan = allReferenceDependencies($this, $this->source);
    $service = app(MoveTrackedItemMovementsService::class);
    $preview = $service->preview($this->actor, $this->source->uuid, $this->target->uuid);
    $plan->update(['title' => 'Modificato']);
    $before = allDependencyRows();
    $this->postJson(route('tracked-items.movements.store'), ['source_uuid' => $this->source->uuid, 'destination_uuid' => $this->target->uuid, 'preview_token' => $preview['preview_token'], 'confirmed' => true])->assertUnprocessable();
    expect(allDependencyRows())->toEqual($before)->and(DB::table('tracked_item_move_records')->count())->toBe(0);
});

test('legacy transaction only operations remain restorable without widening their scope', function () {
    $plan = allReferenceDependencies($this, $this->source);
    $transaction = DB::table('transactions')->where('description', 'Transazione normale')->first();
    DB::table('transactions')->where('id', $transaction->id)->update(['tracked_item_id' => $this->target->id]);
    $after = (array) DB::table('transactions')->where('id', $transaction->id)->first();
    ksort($after);
    $id = DB::table('tracked_item_transaction_moves')->insertGetId(['uuid' => $uuid = (string) Str::uuid(), 'catalog_user_id' => $this->actor->id, 'catalog_account_id' => null, 'source_id' => $this->source->id, 'source_uuid' => $this->source->uuid, 'source_name' => 'Origine', 'destination_id' => $this->target->id, 'destination_uuid' => $this->target->uuid, 'destination_name' => 'Destinazione', 'actor_id' => $this->actor->id, 'actor_name' => 'Autore', 'transaction_count' => 1, 'created_at' => now(), 'expires_at' => now()->addDays(30)]);
    DB::table('tracked_item_transaction_move_entries')->insert(['uuid' => (string) Str::uuid(), 'move_id' => $id, 'transaction_id' => $transaction->id, 'transaction_uuid' => $transaction->uuid, 'previous_tracked_item_id' => $this->source->id, 'applied_tracked_item_id' => $this->target->id, 'after_fingerprint' => hash('sha256', json_encode($after, JSON_THROW_ON_ERROR))]);
    $before = $plan->fresh()->getRawOriginal();
    $this->postJson(route('tracked-items.movements.restore', $uuid), ['confirmed' => true])->assertSuccessful();
    expect(DB::table('transactions')->where('id', $transaction->id)->value('tracked_item_id'))->toBe($this->source->id)->and($plan->fresh()->getRawOriginal())->toBe($before);
});

test('pending import assignments move and restore without rewriting raw or imported history', function () {
    $import = Import::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'status' => 'review_required', 'source_type' => 'csv', 'original_filename' => 'test.csv', 'stored_filename' => 'test.csv']);
    $payload = ['tracked_item_id' => $this->source->id, 'tracked_item_uuid' => $this->source->uuid, 'account_id' => $this->account->id, 'description' => 'Originale', 'amount' => '100.00'];
    $pending = ImportRow::create(['import_id' => $import->id, 'row_index' => 1, 'status' => 'ready', 'parse_status' => 'parsed', 'raw_payload' => ['original' => 'invariato'], 'normalized_payload' => $payload]);
    $imported = ImportRow::create(['import_id' => $import->id, 'row_index' => 2, 'status' => 'imported', 'parse_status' => 'parsed', 'raw_payload' => ['original' => 'invariato'], 'normalized_payload' => $payload]);
    $before = $pending->fresh()->getRawOriginal();
    $importedBefore = $imported->fresh()->getRawOriginal();
    $move = completeReferenceMove($this);
    expect($move->record_count)->toBe(1)->and($pending->fresh()->normalized_payload['tracked_item_id'])->toBe($this->target->id)
        ->and($pending->fresh()->normalized_payload['tracked_item_uuid'])->toBe($this->target->uuid)
        ->and($pending->fresh()->normalized_payload['description'])->toBe('Originale')->and($pending->fresh()->raw_payload)->toBe(['original' => 'invariato'])
        ->and($imported->fresh()->getRawOriginal())->toBe($importedBefore);
    $this->postJson(route('tracked-items.movements.restore', $move->uuid), ['confirmed' => true])->assertSuccessful();
    expect($pending->fresh()->getRawOriginal())->toBe($before)->and($imported->fresh()->getRawOriginal())->toBe($importedBefore);
});

test('failure in a later dependency rolls back all types and their audit together', function () {
    allReferenceDependencies($this, $this->source);
    $before = allDependencyRows();
    $service = app(MoveTrackedItemMovementsService::class);
    $preview = $service->preview($this->actor, $this->source->uuid, $this->target->uuid);
    DB::statement("CREATE TRIGGER fail_plan_move BEFORE UPDATE OF tracked_item_id ON recurring_entries BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
    expect(fn () => $service->confirm($this->actor, $this->source->uuid, $this->target->uuid, $preview['preview_token']))->toThrow(QueryException::class);
    expect(allDependencyRows())->toEqual($before)->and(DB::table('tracked_item_transaction_moves')->count())->toBe(0)
        ->and(DB::table('tracked_item_move_records')->count())->toBe(0)->and(DB::table('tracked_item_transaction_move_entries')->count())->toBe(0);
});

test('failure during restore rolls back all entity types and preserves recoverable history', function () {
    allReferenceDependencies($this, $this->source);
    $move = completeReferenceMove($this);
    $before = allDependencyRows();
    DB::statement("CREATE TRIGGER fail_plan_restore BEFORE UPDATE OF tracked_item_id ON recurring_entries BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
    expect(fn () => app(MoveTrackedItemMovementsService::class)->restore($this->actor, $move->uuid))->toThrow(QueryException::class);
    expect(allDependencyRows())->toEqual($before)->and(DB::table('tracked_item_transaction_moves')->value('restored_at'))->toBeNull();
});
