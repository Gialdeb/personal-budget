<?php

use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Models\UserYear;
use App\Services\TrackedItems\PendingTrackedItemService;
use App\Services\TrackedItems\TrackedItemIdentityService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $this->actor = User::factory()->create();
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000, 'opening_balance_date' => '2026-01-01']);
    $this->category = Category::create(['user_id' => $this->actor->id, 'name' => 'Istruzione', 'slug' => 'education', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->actingAs($this->actor);
    $this->context = ['account_uuid' => $this->account->uuid, 'category_uuid' => $this->category->uuid, 'type_key' => 'expense'];
    $this->submission = [...$this->context, 'transaction_day' => 17, 'amount' => 20, 'description' => 'Conferma riferimento'];
});

function draftReference(object $test, array $input = []): string
{
    return $test->postJson(route('transactions.tracked-items.store'), [...$test->context, 'draft' => true, 'name' => 'Speak Up', ...$input])
        ->assertOk()->json('item.value');
}

test('reference preparation and cancellation never write catalog records or associations', function () {
    $before = DB::table('tracked_items')->get()->all();
    $token = draftReference($this);
    expect($token)->toStartWith(PendingTrackedItemService::PREFIX)
        ->and(DB::table('tracked_items')->get()->all())->toEqual($before)
        ->and(DB::table('tracked_item_categories')->count())->toBe(0);
});

test('a submitted draft is created atomically and repeated drafts reuse its UUID', function () {
    $first = draftReference($this);
    $second = draftReference($this, ['name' => '  SPEAK   ÚP ']);
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $this->post($url, [...$this->submission, 'tracked_item_uuid' => $first])->assertSessionHasNoErrors();
    $reference = TrackedItem::where('slug', 'speak-up')->sole();
    expect(Transaction::where('description', 'Conferma riferimento')->sole()->tracked_item_id)->toBe($reference->id);
    $this->post($url, [...$this->submission, 'transaction_day' => 18, 'description' => 'Seconda conferma', 'tracked_item_uuid' => $second])->assertSessionHasNoErrors();
    expect(TrackedItem::where('slug', 'speak-up')->count())->toBe(1)
        ->and(Transaction::where('description', 'Seconda conferma')->sole()->tracked_item_id)->toBe($reference->id);
});

test('selecting another category is provisional and invalid submissions roll back catalog changes', function () {
    $otherCategory = $this->category->replicate(['uuid']);
    $otherCategory->slug = 'other';
    $otherCategory->save();
    $item = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Speak Up', 'slug' => 'speak-up', 'is_active' => true]);
    $item->compatibleCategories()->attach($otherCategory->id);
    $before = $item->fresh()->getRawOriginal();
    $token = draftReference($this, ['tracked_item_uuid' => $item->uuid]);
    expect($item->fresh()->getRawOriginal())->toBe($before)->and($item->fresh()->compatibleCategories->modelKeys())->toBe([$otherCategory->id]);
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $this->post($url, [...$this->submission, 'amount' => -1, 'tracked_item_uuid' => $token])->assertSessionHasErrors('amount');
    expect($item->fresh()->getRawOriginal())->toBe($before)->and($item->fresh()->compatibleCategories->modelKeys())->toBe([$otherCategory->id]);
    $this->get(route('transactions.show', ['year' => 2026, 'month' => 10]))->assertOk();
    $this->post($url, [...$this->submission, 'tracked_item_uuid' => $token])->assertSessionHasNoErrors();
    expect($item->fresh()->compatibleCategories->modelKeys())->toContain($otherCategory->id, $this->category->id);
});

test('failed submissions leave no newly created reference or financial operation', function () {
    $token = draftReference($this);
    $this->postJson(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->submission, 'amount' => -1, 'tracked_item_uuid' => $token])->assertUnprocessable();
    expect(TrackedItem::where('name', 'Speak Up')->exists())->toBeFalse()->and(Transaction::count())->toBe(0);
});

test('drafts cannot be reused by another user or after their account category context changes', function () {
    $token = draftReference($this);
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $this->postJson($url, [...$this->submission, 'category_uuid' => fake()->uuid(), 'tracked_item_uuid' => $token])->assertUnprocessable();
    $this->actingAs(User::factory()->create())->postJson($url, [...$this->submission, 'tracked_item_uuid' => $token])->assertUnprocessable();
    expect(TrackedItem::where('name', 'Speak Up')->exists())->toBeFalse();
});

test('counterparty preparation is read only and a failed shared submission rolls it back', function () {
    $token = $this->postJson(route('shared-expenses.counterparties.store'), ['draft' => true, 'name' => 'Persona provvisoria'])->assertOk()->json('uuid');
    expect(TrackedItem::where('name', 'Persona provvisoria')->exists())->toBeFalse();
    $this->postJson(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->submission, 'amount' => -1, 'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $token, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share' => '10', 'shared_expense_counterparty_share' => '10'])->assertUnprocessable();
    expect(TrackedItem::where('name', 'Persona provvisoria')->exists())->toBeFalse();
});

test('staged aliases resolve canonically without changing historical transactions', function () {
    $alias = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Vecchio nome', 'slug' => 'old', 'is_active' => true]);
    $canonical = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Nuovo nome', 'slug' => 'new', 'is_active' => true]);
    $historical = userTransaction($this->actor, $this->account, ['tracked_item_id' => $alias->id]);
    app(TrackedItemIdentityService::class)->register($this->actor, $alias, $canonical);
    $token = draftReference($this, ['tracked_item_uuid' => $alias->uuid]);
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->submission, 'tracked_item_uuid' => $token])->assertSessionHasNoErrors();
    expect($historical->fresh()->tracked_item_id)->toBe($alias->id)->and(Transaction::where('description', 'Conferma riferimento')->sole()->tracked_item_id)->toBe($canonical->id);
});

test('recurring preparation is read only and materializes only on a valid recurring submission', function () {
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $token = $this->postJson(route('recurring-entries.tracked-items.store'), ['draft' => true, 'name' => 'Corso provvisorio', 'account_uuid' => $this->account->uuid, 'category_uuid' => $this->category->uuid, 'direction' => 'expense'])->assertOk()->json('item.value');
    expect($writes)->toBe([]);
    $this->post(route('recurring-entries.store'), [
        'account_uuid' => $this->account->uuid, 'category_uuid' => $this->category->uuid, 'tracked_item_uuid' => $token,
        'title' => 'Corso', 'direction' => 'expense', 'entry_type' => 'recurring', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1,
        'start_date' => '2026-10-17', 'expected_amount' => 20, 'is_amount_variable' => false, 'reminder_days_before' => [], 'auto_create_transaction' => false,
    ])->assertSessionHasNoErrors();
    expect(RecurringEntry::where('title', 'Corso')->sole()->tracked_item_id)->toBe(TrackedItem::where('name', 'Corso provvisorio')->sole()->id);
});

test('templates save provisional references together with the confirmed template', function () {
    $token = draftReference($this);
    $this->post(route('transaction-templates.store'), [
        'name' => 'Corso rapido', 'icon' => 'Book', 'color' => 'primary', 'direction' => 'expense',
        ...$this->context, 'tracked_item_uuid' => $token, 'shared_enabled' => false, 'shared_payer' => 'self',
        'shared_split_mode' => 'equal', 'my_share_basis_points' => 5000, 'is_active' => true,
    ])->assertSessionHasNoErrors();
    expect(TransactionTemplate::sole()->tracked_item_id)->toBe(TrackedItem::where('name', 'Speak Up')->sole()->id);
});

test('ambiguous names and archived references cannot be silently created or reactivated', function () {
    TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Speak Up', 'slug' => 'one', 'is_active' => true]);
    TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'SPEAK ÚP', 'slug' => 'two', 'is_active' => true]);
    $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => ' speak  up '])->assertUnprocessable()->assertJsonValidationErrors('name');
    $archived = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Archiviato', 'slug' => 'archived', 'is_active' => false]);
    $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Archiviato'])->assertUnprocessable();
    expect($archived->fresh()->is_active)->toBeFalse()->and(TrackedItem::count())->toBe(3);
});

test('expired or tampered drafts are rejected without writes', function () {
    $token = draftReference($this);
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $this->postJson($url, [...$this->submission, 'tracked_item_uuid' => $token.'invalid'])->assertUnprocessable()->assertJsonValidationErrors('tracked_item_uuid');
    $this->travel(25)->hours();
    $this->postJson($url, [...$this->submission, 'tracked_item_uuid' => $token])->assertUnprocessable();
    expect(TrackedItem::where('name', 'Speak Up')->exists())->toBeFalse();
});

test('validation messages describe selections without technical identifiers', function () {
    $response = $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'tracked_item_uuid' => 'invalid']);
    $response->assertUnprocessable();
    expect($response->json('errors.tracked_item_uuid.0'))->toContain('riferimento')->not->toContain('UUID', 'tracked_item_uuid');
});

test('a counterparty removed from the form before submission is never materialized', function () {
    $token = $this->postJson(route('shared-expenses.counterparties.store'), ['draft' => true, 'name' => 'Selezione annullata'])->assertOk()->json('uuid');
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->submission, 'is_shared_expense' => false, 'shared_expense_counterparty_uuid' => $token])->assertSessionHasNoErrors();
    expect(TrackedItem::where('name', 'Selezione annullata')->exists())->toBeFalse();
});

test('preparation isolates personal and account scoped catalogs', function () {
    $personal = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Personale', 'slug' => 'personal', 'is_active' => true]);
    $shared = TrackedItem::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'name' => 'Condiviso', 'slug' => 'shared', 'is_active' => true]);
    $this->category->update(['account_id' => $this->account->id]);
    $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'tracked_item_uuid' => $personal->uuid])->assertUnprocessable();
    $before = $shared->fresh()->getRawOriginal();
    $token = draftReference($this, ['tracked_item_uuid' => $shared->uuid]);
    expect($shared->fresh()->getRawOriginal())->toBe($before);
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->submission, 'tracked_item_uuid' => $token])->assertSessionHasNoErrors();
    expect(Transaction::where('description', 'Conferma riferimento')->sole()->tracked_item_id)->toBe($shared->id);
});

test('a failure while saving the movement rolls back the reference and all economic tables', function () {
    $token = draftReference($this);
    $tables = ['tracked_items', 'tracked_item_categories', 'transactions', 'accounts', 'counterparty_relations', 'counterparty_obligations', 'counterparty_source_links', 'counterparty_ledger_entries', 'counterparty_payments', 'shared_expense_ledger_entries'];
    $snapshot = fn (): array => collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->get()->all()])->all();
    $before = $snapshot();
    DB::statement("CREATE TRIGGER fail_pending_movement BEFORE INSERT ON transactions BEGIN SELECT RAISE(ABORT, 'simulated movement failure'); END");
    $this->postJson(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->submission, 'tracked_item_uuid' => $token])->assertStatus(500);
    expect($snapshot())->toEqual($before);
});

test('credit debt forms materialize a provisional personal subject only on submit', function () {
    config(['features.credits_debts.enabled' => true, 'counterparty_ledger.shadow_manual_enabled' => false]);
    $token = draftReference($this, ['context' => 'credit_debt']);
    expect(TrackedItem::where('name', 'Speak Up')->exists())->toBeFalse();
    $this->postJson(route('credits-debts.store'), [
        'account_id' => $this->account->id, 'category_id' => $this->category->id, 'reference_uuid' => $token,
        'type' => 'credit', 'description' => 'Anticipo', 'currency_code' => 'EUR', 'total_amount' => '100.00',
    ])->assertSuccessful();
    $item = CreditDebtItem::sole();
    expect($item->reference_id)->toBe(TrackedItem::where('name', 'Speak Up')->sole()->id)->and($item->counterparty_id)->toBe($item->reference_id);
});

test('a name archived after preparation cannot be reactivated by a stale draft', function () {
    $token = draftReference($this);
    $archived = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'SPEAK UP', 'slug' => 'archived-later', 'is_active' => false]);
    $this->postJson(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->submission, 'tracked_item_uuid' => $token])->assertUnprocessable();
    expect($archived->fresh()->is_active)->toBeFalse()->and(TrackedItem::where('user_id', $this->actor->id)->count())->toBe(1);
});

test('plain UUID selections from inline forms acquire new category associations only on a valid submit', function () {
    $oldCategory = $this->category->replicate(['uuid']);
    $oldCategory->slug = 'old-category';
    $oldCategory->save();
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Esistente', 'slug' => 'existing', 'is_active' => true]);
    $reference->compatibleCategories()->attach($oldCategory->id);
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $this->postJson($url, [...$this->submission, 'tracked_item_uuid' => $reference->uuid, 'amount' => -1])->assertUnprocessable();
    expect($reference->fresh()->compatibleCategories->modelKeys())->toBe([$oldCategory->id]);
    $this->post($url, [...$this->submission, 'tracked_item_uuid' => $reference->uuid])->assertSessionHasNoErrors();
    expect($reference->fresh()->compatibleCategories->modelKeys())->toContain($oldCategory->id, $this->category->id);
});

test('shared selection enables an existing personal reference only when the expense is saved and preserves history', function (string $payer) {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Persona esistente', 'slug' => 'existing-person', 'is_active' => true, 'is_shared_expense_counterpart' => false]);
    $historical = userTransaction($this->actor, $this->account, ['tracked_item_id' => $reference->id, 'transaction_date' => '2026-10-01', 'value_date' => '2026-10-01', 'balance_after' => 900]);
    $history = $historical->fresh()->getAttributes();
    $count = TrackedItem::count();
    $token = $this->postJson(route('shared-expenses.counterparties.store'), ['draft' => true, 'tracked_item_uuid' => $reference->uuid])->assertOk()->json('uuid');
    expect($token)->toStartWith(PendingTrackedItemService::PREFIX)
        ->and($reference->fresh()->is_shared_expense_counterpart)->toBeFalse()->and(TrackedItem::count())->toBe($count);
    $payload = [...$this->submission,
        'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $token,
        'shared_expense_paid_by' => $payer, 'shared_expense_my_share' => '10', 'shared_expense_counterparty_share' => '10',
    ];
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $this->post($url, [...$payload, 'amount' => -1])->assertSessionHasErrors('amount');
    expect($reference->fresh()->is_shared_expense_counterpart)->toBeFalse()->and(TrackedItem::count())->toBe($count);
    $this->post($url, $payload)->assertSessionHasNoErrors();
    $record = $payer === 'self'
        ? Transaction::where('description', $this->submission['description'])->sole()
        : SharedExpenseLedgerEntry::where('title', $this->submission['description'])->sole();
    expect($payer === 'self' ? $record->shared_expense_counterparty_id : $record->counterparty_id)->toBe($reference->id)
        ->and(TrackedItem::count())->toBe($count)->and($reference->fresh()->is_shared_expense_counterpart)->toBeTrue()
        ->and($historical->fresh()->getAttributes())->toBe($history);
})->with(['self', 'counterparty']);

test('new shared counterparties stay provisional on failure and materialize once at the main save', function (string $payer) {
    $count = TrackedItem::count();
    $token = $this->postJson(route('shared-expenses.counterparties.store'), ['draft' => true, 'name' => 'Nuova controparte'])->assertOk()->json('uuid');
    expect(TrackedItem::count())->toBe($count);
    $payload = [...$this->submission, 'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $token,
        'shared_expense_paid_by' => $payer, 'shared_expense_my_share' => '10', 'shared_expense_counterparty_share' => '10'];
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $this->post($url, [...$payload, 'amount' => -1])->assertSessionHasErrors('amount');
    expect(TrackedItem::count())->toBe($count);
    $this->post($url, $payload)->assertSessionHasNoErrors();
    $reference = TrackedItem::where('name', 'Nuova controparte')->sole();
    expect(TrackedItem::count())->toBe($count + 1)->and($reference->is_shared_expense_counterpart)->toBeTrue();
    $this->post($url, [...$payload, 'transaction_day' => 18, 'description' => 'Seconda spesa condivisa'])->assertSessionHasNoErrors();
    expect(TrackedItem::count())->toBe($count + 1);
})->with(['self', 'counterparty']);

test('shared preparation rejects foreign archived and account scoped references without enabling them', function (string $invalid) {
    $reference = TrackedItem::create(['user_id' => $invalid === 'foreign' ? User::factory()->create()->id : $this->actor->id,
        'account_id' => $invalid === 'account' ? $this->account->id : null, 'name' => 'Non selezionabile', 'slug' => 'unavailable-person',
        'is_active' => $invalid !== 'archived', 'is_shared_expense_counterpart' => false]);
    $before = $reference->fresh()->getAttributes();
    $this->postJson(route('shared-expenses.counterparties.store'), ['draft' => true, 'tracked_item_uuid' => $reference->uuid])->assertUnprocessable();
    expect($reference->fresh()->getAttributes())->toBe($before)->and(Transaction::count())->toBe(0);
})->with(['foreign', 'archived', 'account']);

test('inline shared creation reuses a matching personal reference without creating another identity', function () {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Persona esistente', 'slug' => 'existing-person', 'is_active' => true, 'is_shared_expense_counterpart' => false]);
    $count = TrackedItem::count();
    $token = $this->postJson(route('shared-expenses.counterparties.store'), ['draft' => true, 'name' => '  PERSONA   ESISTENTE '])->assertOk()->json('uuid');
    expect(TrackedItem::count())->toBe($count)->and($reference->fresh()->is_shared_expense_counterpart)->toBeFalse();
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->submission,
        'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $token,
        'shared_expense_paid_by' => 'self', 'shared_expense_my_share' => '10', 'shared_expense_counterparty_share' => '10',
    ])->assertSessionHasNoErrors();
    expect(TrackedItem::count())->toBe($count)->and(Transaction::sole()->shared_expense_counterparty_id)->toBe($reference->id);
});
