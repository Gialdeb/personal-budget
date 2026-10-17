<?php

use App\Models\AccountMembership;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\TrackedItems\MoveTrackedItemMovementsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $this->actor = User::factory()->create();
    $this->account = createTestAccount($this->actor);
    $this->source = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Origine', 'slug' => 'source', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->target = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Destinazione', 'slug' => 'target', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->transaction = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $this->actingAs($this->actor);
});

function registerTransactionMove(object $test): object
{
    $service = app(MoveTrackedItemMovementsService::class);
    $preview = $service->preview($test->actor, $test->source->uuid, $test->target->uuid);
    $service->confirm($test->actor, $test->source->uuid, $test->target->uuid, $preview['preview_token']);

    return DB::table('tracked_item_transaction_moves')->orderByDesc('id')->first();
}

function restoreTransactionMove(object $test, object $move): TestResponse
{
    return $test->postJson(route('tracked-items.movements.restore', $move->uuid), ['confirmed' => true]);
}

function referenceEconomicSnapshot(): array
{
    return collect(['accounts', 'tracked_items', 'tracked_item_aliases', 'tracked_item_categories', 'recurring_entries', 'recurring_entry_occurrences', 'recurring_entry_periods', 'recurring_occurrence_payment_allocations', 'scheduled_entries', 'transaction_templates', 'credit_debt_items', 'credit_debt_payments', 'shared_expense_ledger_entries', 'shared_expense_settlements', 'counterparty_relations', 'counterparty_obligations', 'counterparty_source_links', 'counterparty_ledger_entries', 'counterparty_payments', 'counterparty_allocation_entries', 'counterparty_operations', 'counterparty_sync_issues'])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->all()])->all();
}

test('persistent registry records exact transactions and restores only their references', function () {
    $before = DB::table('transactions')->where('id', $this->transaction->id)->first();
    $deleted = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $deleted->delete();
    $deletedBefore = $deleted->fresh()->getRawOriginal();
    $move = registerTransactionMove($this);
    expect($move->actor_id)->toBe($this->actor->id)->and($move->transaction_count)->toBe(2)->and($move->source_id)->toBe($this->source->id)
        ->and(CarbonImmutable::parse($move->created_at)->addDays(30)->equalTo($move->expires_at))->toBeTrue();
    $entry = DB::table('tracked_item_transaction_move_entries')->orderBy('id')->first();
    expect($entry->transaction_id)->toBe($this->transaction->id)->and($entry->transaction_uuid)->toBe($this->transaction->uuid)
        ->and($entry->previous_tracked_item_id)->toBe($this->source->id)->and($entry->applied_tracked_item_id)->toBe($this->target->id);
    $later = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->target->id]);
    $laterBefore = $later->fresh()->getRawOriginal();
    restoreTransactionMove($this, $move)->assertSuccessful();
    expect(DB::table('transactions')->where('id', $before->id)->first())->toEqual($before)
        ->and($later->fresh()->getRawOriginal())->toBe($laterBefore)->and($deleted->fresh()->getRawOriginal())->toBe($deletedBefore)
        ->and(DB::table('tracked_item_transaction_moves')->first()->restored_by_id)->toBe($this->actor->id);
    restoreTransactionMove($this, $move)->assertUnprocessable();
});

test('shared occurrences with protected ledger abort the complete movement', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => true]);
    $plan = RecurringEntry::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'title' => 'Condivisa', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true, 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'expected_amount' => 100, 'shared_expense_counterparty_id' => $this->source->id, 'tracked_item_id' => $this->source->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
    $occurrence = $plan->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-01', 'due_date' => '2026-10-01', 'expected_amount' => 100, 'status' => 'pending']);
    Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'transaction_date' => '2026-10-01', 'amount' => '40.00', 'currency' => 'EUR', 'direction' => 'expense', 'kind' => 'scheduled', 'source_type' => 'generated', 'status' => 'confirmed', 'recurring_entry_occurrence_id' => $occurrence->id, 'tracked_item_id' => $this->source->id, 'shared_expense_counterparty_id' => $this->source->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 2000, 'shared_expense_counterparty_share_cents' => 2000]);
    TransactionTemplate::factory()->create(['user_id' => $this->actor->id, 'tracked_item_id' => $this->source->id]);
    expect(DB::table('counterparty_source_links')->count())->toBeGreaterThan(0);
    $before = referenceEconomicSnapshot();
    $transactions = DB::table('transactions')->orderBy('id')->get()->all();
    $service = app(MoveTrackedItemMovementsService::class);
    $preview = $service->preview($this->actor, $this->source->uuid, $this->target->uuid);
    expect($preview['block_reason'])->not->toBeNull();
    $this->postJson(route('tracked-items.movements.store'), ['source_uuid' => $this->source->uuid, 'destination_uuid' => $this->target->uuid, 'preview_token' => $preview['preview_token'], 'confirmed' => true])->assertUnprocessable();
    expect(DB::table('transactions')->orderBy('id')->get()->all())->toEqual($transactions)->and(referenceEconomicSnapshot())->toEqual($before);
});

test('incompatible state changes block the entire restore', function (string $change) {
    $second = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $move = registerTransactionMove($this);
    if ($change === 'amount') {
        DB::table('transactions')->where('id', $second->id)->update(['amount' => 999]);
    }
    if ($change === 'reference') {
        DB::table('transactions')->where('id', $second->id)->update(['tracked_item_id' => null]);
    }
    if ($change === 'deleted') {
        DB::table('transactions')->where('id', $second->id)->update(['deleted_at' => now()]);
    }
    if ($change === 'missing') {
        DB::table('transactions')->where('id', $second->id)->delete();
    }
    if ($change === 'inactive source') {
        $this->source->update(['is_active' => false]);
    }
    if ($change === 'payment') {
        $debt = CreditDebtItem::factory()->forAccount($this->account)->create(['reference_id' => $this->source->id, 'counterparty_id' => $this->source->id]);
        CreditDebtPayment::create(['user_id' => $this->actor->id, 'credit_debt_item_id' => $debt->id, 'transaction_id' => $second->id, 'account_id' => $this->account->id, 'currency_code' => 'EUR', 'amount' => 10, 'paid_at' => '2026-10-01']);
    }
    $before = DB::table('transactions')->orderBy('id')->get()->all();
    restoreTransactionMove($this, $move)->assertUnprocessable();
    expect(DB::table('transactions')->orderBy('id')->get()->all())->toEqual($before)->and(DB::table('tracked_item_transaction_moves')->first()->restored_at)->toBeNull();
    $this->getJson(route('tracked-items.movements.history'))->assertSuccessful()->assertJsonPath('data.0.can_restore', false);
})->with(['amount', 'reference', 'deleted', 'missing', 'inactive source', 'payment']);

test('restore requires explicit consent and enforces the exact thirty day deadline', function (int $days, bool $allowed) {
    $move = registerTransactionMove($this);
    $this->postJson(route('tracked-items.movements.restore', $move->uuid))->assertUnprocessable();
    $this->travelTo(CarbonImmutable::parse($move->created_at)->addDays($days));
    $response = restoreTransactionMove($this, $move);
    if ($allowed) {
        $response->assertSuccessful();
    } else {
        $response->assertUnprocessable();
    }
    expect($this->transaction->fresh()->tracked_item_id)->toBe($allowed ? $this->source->id : $this->target->id);
})->with([[29, true], [30, false], [31, false]]);

test('later movements cannot be overwritten even after their own restore', function () {
    $first = registerTransactionMove($this);
    $service = app(MoveTrackedItemMovementsService::class);
    $preview = $service->preview($this->actor, $this->target->uuid, $this->source->uuid);
    $service->confirm($this->actor, $this->target->uuid, $this->source->uuid, $preview['preview_token']);
    $second = DB::table('tracked_item_transaction_moves')->orderByDesc('id')->first();
    restoreTransactionMove($this, $second)->assertSuccessful();
    restoreTransactionMove($this, $first)->assertUnprocessable();
    expect($this->transaction->fresh()->tracked_item_id)->toBe($this->target->id);
});

test('foreign users cannot read restore history or restore an operation', function () {
    $move = registerTransactionMove($this);
    $this->actingAs(User::factory()->create());
    $this->getJson(route('tracked-items.movements.history'))->assertSuccessful()->assertJsonCount(0, 'data');
    restoreTransactionMove($this, $move)->assertNotFound();
    expect($this->transaction->fresh()->tracked_item_id)->toBe($this->target->id);
});

test('revoked shared editing permissions block restore and history explains why', function () {
    $editor = User::factory()->create();
    $membership = AccountMembership::create(['account_id' => $this->account->id, 'user_id' => $editor->id, 'role' => 'editor', 'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $this->actor->id, 'joined_at' => now()]);
    $this->source->update(['account_id' => $this->account->id]);
    $this->target->update(['account_id' => $this->account->id]);
    $move = registerTransactionMove($this);
    $membership->update(['role' => 'viewer']);
    $this->actingAs($editor);
    $this->getJson(route('tracked-items.movements.history'))->assertSuccessful()->assertJsonPath('data.0.can_restore', false);
    restoreTransactionMove($this, $move)->assertNotFound();
});

test('restore failure after a successful row update rolls everything back', function () {
    $second = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $move = registerTransactionMove($this);
    DB::statement("CREATE TRIGGER fail_restore BEFORE UPDATE OF tracked_item_id ON transactions WHEN OLD.id = {$second->id} BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
    $this->withoutExceptionHandling();
    try {
        restoreTransactionMove($this, $move);
        $this->fail('Expected failure.');
    } catch (QueryException) {
        expect($this->transaction->fresh()->tracked_item_id)->toBe($this->target->id)->and($second->fresh()->tracked_item_id)->toBe($this->target->id)
            ->and(DB::table('tracked_item_transaction_moves')->first()->restored_at)->toBeNull();
    }
});

test('history is paginated and retains records after expiration', function () {
    for ($index = 0; $index < 11; $index++) {
        $move = registerTransactionMove($this);
        restoreTransactionMove($this, $move)->assertSuccessful();
    }
    $this->travel(31)->days();
    $this->getJson(route('tracked-items.movements.history'))->assertSuccessful()->assertJsonCount(10, 'data')->assertJsonPath('last_page', 2);
    $this->getJson(route('tracked-items.movements.history', ['page' => 2]))->assertSuccessful()->assertJsonCount(1, 'data');
});

test('preview fixes the complete set including archived and counterparty only records', function () {
    $partyOnly = userTransaction($this->actor, $this->account, ['tracked_item_id' => null, 'shared_expense_counterparty_id' => $this->source->id]);
    $deleted = userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $deleted->delete();
    $service = app(MoveTrackedItemMovementsService::class);
    $preview = $service->preview($this->actor, $this->source->uuid, $this->target->uuid);
    expect($preview['count'])->toBe(3);
    userTransaction($this->actor, $this->account, ['tracked_item_id' => $this->source->id]);
    $this->postJson(route('tracked-items.movements.store'), ['source_uuid' => $this->source->uuid, 'destination_uuid' => $this->target->uuid, 'preview_token' => $preview['preview_token'], 'confirmed' => true])->assertUnprocessable();
    expect(DB::table('tracked_item_transaction_moves')->count())->toBe(0)->and($this->transaction->fresh()->tracked_item_id)->toBe($this->source->id);
    $move = registerTransactionMove($this);
    expect($move->transaction_count)->toBe(4)->and($partyOnly->fresh()->tracked_item_id)->toBeNull()->and($deleted->fresh()->tracked_item_id)->toBe($this->target->id);
});

test('personal destination must be compatible with each actual account catalog', function () {
    $member = User::factory()->create();
    AccountMembership::create(['account_id' => $this->account->id, 'user_id' => $member->id, 'role' => 'editor', 'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $this->actor->id, 'joined_at' => now()]);
    $preview = app(MoveTrackedItemMovementsService::class)->preview($this->actor, $this->source->uuid, $this->target->uuid);
    expect($preview['block_reason'])->not->toBeNull();
    expect($this->transaction->fresh()->tracked_item_id)->toBe($this->source->id)->and(DB::table('tracked_item_transaction_moves')->count())->toBe(0);
});

test('history separates pending and restored moves and reports availability across pages', function () {
    $move = registerTransactionMove($this);
    for ($index = 0; $index < 11; $index++) {
        $restored = (array) $move;
        unset($restored['id']);
        $restored['uuid'] = (string) Str::uuid();
        $restored['restored_at'] = CarbonImmutable::now();
        DB::table('tracked_item_transaction_moves')->insert($restored);
    }
    $url = route('tracked-items.movements.history');
    $this->getJson($url.'?status=pending')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $move->uuid)->assertJsonPath('has_restorable', true)->assertJsonPath('last_page', 1);
    $this->getJson($url.'?status=restored')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('has_restorable', true)->assertJsonPath('last_page', 2);
    $this->getJson($url.'?status=restored&page=2')->assertOk()->assertJsonCount(1, 'data');
    restoreTransactionMove($this, $move)->assertOk();
    $this->getJson($url.'?status=pending')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('has_restorable', false);
});

test('history hides restore availability for conflicts expired moves and other catalogs', function () {
    $move = registerTransactionMove($this);
    $url = route('tracked-items.movements.history');
    $this->transaction->refresh()->update(['description' => 'Changed after move']);
    $this->getJson($url.'?status=pending')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('has_restorable', false);
    $this->getJson($url.'?status=blocked')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_restore', false);
    DB::table('tracked_item_transaction_moves')->where('id', $move->id)->update(['expires_at' => CarbonImmutable::now()]);
    $this->getJson($url.'?status=pending')->assertOk()->assertJsonPath('has_restorable', false);
    $this->actingAs(User::factory()->create())->getJson($url.'?status=pending')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('has_restorable', false);
});

test('deleted references appear only among non restorable moves with an explicit explanation', function () {
    $move = registerTransactionMove($this);
    $this->source->delete();
    $url = route('tracked-items.movements.history');
    $this->getJson($url.'?status=pending')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('has_restorable', false);
    $response = $this->getJson($url.'?status=blocked')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $move->uuid)->assertJsonPath('data.0.can_restore', false);
    expect($response->json('data.0.block_reason'))->toContain('origine', 'Origine', 'eliminato', 'entrambi i riferimenti originali');
    $this->getJson($url.'?status=restored')->assertOk()->assertJsonCount(0, 'data');
    expect($this->transaction->fresh()->tracked_item_id)->toBe($this->target->id);
});
