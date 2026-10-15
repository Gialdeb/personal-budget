<?php

use App\Models\Category;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyReconciliationService;
use App\Services\Counterparties\ManualCreditDebtShadowService;
use App\Services\CreditDebts\AlignManualEconomicSubjects;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\CreditDebtItemService;
use App\Services\CreditDebts\CreditDebtPaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => config(['features.credits_debts.enabled' => true, 'counterparty_ledger.shadow_manual_enabled' => false]));

function economicSubject(User $user, string $name = 'Pino', bool $shared = false): TrackedItem
{
    return TrackedItem::create(['user_id' => $user->id, 'name' => $name, 'slug' => (string) str()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => $shared, 'settings' => ['economic_subject' => true]]);
}

test('manual creation derives one subject for credits debts and different minor units', function (string $type, string $currency, string $amount) {
    $user = User::factory()->create();
    $subject = economicSubject($user);
    $item = app(CreditDebtItemService::class)->create($user, ['reference_id' => $subject->id, 'type' => $type, 'total_amount' => $amount, 'currency_code' => $currency, 'description' => 'Economic subject']);
    expect($item->reference_id)->toBe($subject->id)->and($item->counterparty_id)->toBe($subject->id);
    expect($subject->fresh()->is_shared_expense_counterpart)->toBeFalse();
    expect(Transaction::count())->toBe(0);
})->with([['credit', 'EUR', '280.00'], ['debit', 'EUR', '280.00'], ['credit', 'JPY', '280']]);

test('programmatic and factory writes cannot persist different subjects', function (string $path) {
    $user = User::factory()->create();
    $pino = economicSubject($user);
    $mario = economicSubject($user, 'Mario');
    $data = ['user_id' => $user->id, 'reference_id' => $pino->id, 'counterparty_id' => $mario->id, 'type' => 'credit', 'total_amount' => 280, 'currency_code' => 'EUR', 'description' => 'Mismatch'];
    expect(fn () => $path === 'factory' ? CreditDebtItem::factory()->create($data) : CreditDebtItem::create($data))->toThrow(ValidationException::class);
    expect(CreditDebtItem::count())->toBe(0);
})->with(['model', 'factory']);

test('partial reference updates derive the counterpart and payment history locks the economic subject', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $pino = economicSubject($user);
    $mario = economicSubject($user, 'Mario');
    $item = CreditDebtItem::factory()->forAccount($account)->create(['reference_id' => $pino->id]);
    $service = app(CreditDebtItemService::class);
    $item = $service->update($user, $item, ['reference_id' => $mario->id]);
    expect($item->reference_id)->toBe($mario->id)->and($item->counterparty_id)->toBe($mario->id);
    $payment = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '25.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id]);
    expect($payment->transaction->tracked_item_id)->toBe($mario->id)->and($item->fresh()->remainingAmount())->toBe('75.00');
    expect(fn () => $service->update($user, $item, ['reference_id' => $pino->id]))->toThrow(ValidationException::class);
    expect($item->fresh()->uuid)->toBe($item->uuid)->and($item->fresh()->counterparty_id)->toBe($mario->id);
});

test('legacy classification is dry run first audited idempotent and preserves unresolved history', function (string $case, string $status) {
    $user = User::factory()->create();
    $pino = economicSubject($user);
    $mario = economicSubject($user, 'Mario');
    $unknown = TrackedItem::create(['user_id' => $user->id, 'name' => 'Unclassified', 'slug' => 'unclassified']);
    $identity = match ($case) {
        'reference_only' => ['reference_id' => $pino->id],
        'counterparty_only' => ['counterparty_id' => $pino->id],
        'equal' => ['reference_id' => $pino->id, 'counterparty_id' => $pino->id],
        'different' => ['reference_id' => $pino->id, 'counterparty_id' => $mario->id],
        'ambiguous' => ['reference_id' => $unknown->id],
        'deleted' => ['reference_id' => $pino->id, 'deleted_at' => now()],
        default => [],
    };
    $item = legacyCreditDebtItem($user, $identity);
    $before = $item->getRawOriginal();
    $service = app(AlignManualEconomicSubjects::class);
    $dry = $service->item($user, $item->uuid);
    expect($dry['status'])->toBe($status)->and($item->fresh()->getRawOriginal())->toBe($before);
    expect(Activity::where('event', 'economic_subject_aligned')->count())->toBe(0);
    $result = $service->item($user, $item->uuid, true);
    if ($status === 'safe') {
        expect($result['status'])->toBe('applied')->and($item->fresh()->counterparty_id)->toBe($pino->id)->and($item->fresh()->reference_id)->toBe($pino->id);
        expect($service->item($user, $item->uuid, true)['status'])->toBe('unchanged');
        expect(Activity::where('event', 'economic_subject_aligned')->count())->toBe(1);
    } else {
        expect($item->fresh()->getRawOriginal())->toBe($before);
    }
    expect(Transaction::count())->toBe(0)->and(CounterpartyObligation::count())->toBe(0);
})->with([['reference_only', 'safe'], ['counterparty_only', 'safe'], ['equal', 'unchanged'], ['different', 'safe'], ['ambiguous', 'blocked'], ['neither', 'blocked'], ['deleted', 'blocked']]);

test('Pino Mario and Mario Mario balances preserve the two economic subjects', function (bool $same) {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $mario = economicSubject($user, 'Mario', true);
    $pino = economicSubject($user, 'Pino', true);
    $merchant = economicSubject($user, 'Q8');
    $transaction = userTransaction($user, $account, ['tracked_item_id' => $merchant->id, 'amount' => '122.00', 'shared_expense_counterparty_id' => $mario->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 6100, 'shared_expense_counterparty_share_cents' => 6100]);
    $item = legacyCreditDebtItem($user, ['reference_id' => $same ? $mario->id : $pino->id, 'counterparty_id' => $mario->id, 'total_amount' => 280]);
    $balances = app(CounterpartyBalanceService::class);
    expect($balances->balance($user, $mario, 'EUR')['total_cents'])->toBe($same ? 34100 : 6100);
    $count = Transaction::count();
    app(AlignManualEconomicSubjects::class)->item($user, $item->uuid, true);
    expect($balances->balance($user, $mario, 'EUR')['total_cents'])->toBe($same ? 34100 : 6100);
    expect($balances->balance($user, $pino, 'EUR')['total_cents'])->toBe($same ? 0 : 28000);
    expect(Transaction::count())->toBe($count);
    expect($transaction->fresh()->tracked_item_id)->toBe($merchant->id)->and($transaction->fresh()->shared_expense_counterparty_id)->toBe($mario->id);
})->with([false, true]);

test('bulk SQL cannot bypass manual subject equality', function () {
    $user = User::factory()->create();
    $item = CreditDebtItem::factory()->create(['user_id' => $user->id]);
    $other = economicSubject($user);
    expect(fn () => DB::transaction(fn () => DB::table('credit_debt_items')->where('id', $item->id)->update(['counterparty_id' => $other->id])))->toThrow(QueryException::class);
    expect($item->fresh()->counterparty_id)->toBe($item->reference_id);
});

test('an incoherent legacy manual blocks shadow and ledger backfill', function () {
    $user = User::factory()->create();
    $mario = economicSubject($user, 'Mario', true);
    $pino = economicSubject($user, 'Pino', true);
    $item = legacyCreditDebtItem($user, ['reference_id' => $pino->id, 'counterparty_id' => $mario->id]);
    expect(fn () => app(ManualCreditDebtShadowService::class)->itemCreated($item))->toThrow(ValidationException::class);
    $backfill = app(CounterpartyBackfillService::class);
    expect($backfill->run($user, $mario, 'EUR')['ambiguous'])->not->toBeEmpty();
    expect(fn () => $backfill->run($user, $mario, 'EUR', true))->toThrow(ValidationException::class);
    $report = app(CounterpartyReconciliationService::class)->compare($user, $mario, 'EUR');
    expect(collect($report['anomalies'])->pluck('code'))->toContain('manual_subject_mismatch');
    expect($report['readiness'])->toBe('blocked');
    expect($backfill->run($user, $pino, 'EUR')['ambiguous'])->not->toBeEmpty();
    expect(app(CounterpartyReconciliationService::class)->compare($user, $pino, 'EUR')['readiness'])->toBe('blocked');
    expect(CounterpartyObligation::count())->toBe(0);
});

test('numeric user option works with UUID typed PostgreSQL users', function () {
    $user = User::factory()->create();
    $this->artisan('counterparties:backfill', ['--user' => (string) $user->id, '--dry-run' => true])->assertSuccessful();
    $this->artisan('credits-debts:align-subjects', ['--user' => (string) $user->id, '--dry-run' => true])->assertSuccessful();
});

test('legacy normalization never reassigns a captured shadow obligation', function () {
    $user = User::factory()->create();
    $pino = economicSubject($user);
    $mario = economicSubject($user, 'Mario');
    $item = CreditDebtItem::factory()->create(['user_id' => $user->id, 'reference_id' => $mario->id]);
    config(['counterparty_ledger.shadow_manual_enabled' => true]);
    app(ManualCreditDebtShadowService::class)->itemCreated($item);
    $obligation = CounterpartyObligation::sole();
    $migration = require base_path('database/migrations/2026_10_15_080945_protect_manual_credit_debt_subject_identity.php');
    $migration->down();
    try {
        DB::table('credit_debt_items')->where('id', $item->id)->update(['reference_id' => $pino->id]);
    } finally {
        $migration->up();
    }
    $before = CounterpartyLedgerEntry::count();
    $result = app(AlignManualEconomicSubjects::class)->item($user, $item->uuid, true);
    expect($result['status'])->toBe('blocked')->and($result['reason'])->toBe('ledger_captured_requires_rectification');
    expect($item->fresh()->counterparty_id)->toBe($mario->id)->and(CounterpartyObligation::sole()->uuid)->toBe($obligation->uuid)
        ->and(CounterpartyLedgerEntry::count())->toBe($before)->and(Transaction::count())->toBe(0);
});

test('soft deleted payments remain a lock for alignment and subject changes', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $subject = economicSubject($user);
    $item = CreditDebtItem::factory()->forAccount($account)->create(['reference_id' => $subject->id]);
    $payment = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '25.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id]);
    $payment->delete();
    $migration = require base_path('database/migrations/2026_10_15_080945_protect_manual_credit_debt_subject_identity.php');
    $migration->down();
    try {
        DB::table('credit_debt_items')->where('id', $item->id)->update(['counterparty_id' => null]);
    } finally {
        $migration->up();
    }
    $result = app(AlignManualEconomicSubjects::class)->item($user, $item->uuid, true);
    expect($result['reason'])->toBe('payment_history_requires_review')->and($item->fresh()->counterparty_id)->toBeNull();
    expect(fn () => app(CreditDebtItemService::class)->update($user, $item->fresh(), ['reference_id' => $subject->id]))->toThrow(ValidationException::class);
    expect(CreditDebtPayment::withTrashed()->count())->toBe(1);
});

test('HTTP manual subject input accepts one identity and rejects conflicting or invalid UUIDs', function (string $case, bool $valid) {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $category = Category::query()->create(['user_id' => $user->id, 'name' => 'Crediti e debiti', 'slug' => fake()->uuid(), 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $subject = economicSubject($user);
    $other = economicSubject($user, 'Other');
    $identity = match ($case) {
        'same_ids' => ['reference_id' => $subject->id, 'counterparty_id' => $subject->id],
        'same_uuids' => ['reference_uuid' => $subject->uuid, 'counterparty_uuid' => $subject->uuid],
        'different' => ['reference_id' => $subject->id, 'counterparty_id' => $other->id],
        'unknown_uuid' => ['reference_uuid' => fake()->uuid()],
        'conflicting_alias' => ['reference_id' => $subject->id, 'reference_uuid' => $other->uuid],
        'invalid_uuid_type' => ['reference_uuid' => ['invalid']],
        default => [],
    };
    $response = $this->actingAs($user)->postJson(route('credits-debts.store'), [...$identity, 'account_id' => $account->id, 'category_id' => $category->id, 'type' => 'credit', 'description' => 'HTTP subject', 'currency_code' => 'EUR', 'total_amount' => '100.00']);
    if ($valid) {
        $response->assertSuccessful();
        expect(CreditDebtItem::sole()->counterparty_id)->toBe($subject->id)->and(CreditDebtItem::sole()->reference_id)->toBe($subject->id);
    } else {
        $response->assertUnprocessable();
        expect(CreditDebtItem::count())->toBe(0);
    }
})->with([['same_ids', true], ['same_uuids', true], ['different', false], ['unknown_uuid', false], ['conflicting_alias', false], ['invalid_uuid_type', false], ['missing', false]]);

test('database guards reject quiet bulk and foreign subject bypasses', function (string $case) {
    $user = User::factory()->create();
    $item = CreditDebtItem::factory()->create(['user_id' => $user->id]);
    $otherUser = User::factory()->create();
    $subject = economicSubject($case === 'foreign' ? $otherUser : $user);
    if ($case === 'inactive') {
        $subject->update(['is_active' => false]);
    }
    if ($case === 'scoped') {
        $subject->update(['account_id' => createTestAccount($user)->id]);
    }
    $data = ['reference_id' => $subject->id, 'counterparty_id' => in_array($case, ['quiet', 'deleted'], true) ? $item->counterparty_id : $subject->id];
    if ($case === 'deleted') {
        $data['deleted_at'] = now();
    }
    expect(fn () => DB::transaction(function () use ($case, $item, $data): void {
        if ($case === 'quiet') {
            $item->fill($data)->saveQuietly();
        } elseif ($case === 'insert') {
            DB::table('credit_debt_items')->insert([...$item->getRawOriginal(), 'id' => $item->id + 1, 'uuid' => fake()->uuid(), 'counterparty_id' => null]);
        } else {
            DB::table('credit_debt_items')->where('id', $item->id)->update($data);
        }
    }))->toThrow(QueryException::class);
})->with(['quiet', 'insert', 'foreign', 'scoped', 'inactive', 'deleted']);

test('an unchanged counterparty explicitly supplied by an old client still rejects a conflicting new reference', function () {
    $user = User::factory()->create();
    $first = economicSubject($user);
    $next = economicSubject($user, 'Next');
    $item = CreditDebtItem::factory()->create(['user_id' => $user->id, 'reference_id' => $first->id]);
    expect(fn () => app(CreditDebtItemService::class)->update($user, $item, ['reference_id' => $next->id, 'counterparty_id' => $first->id]))->toThrow(ValidationException::class);
    expect($item->fresh()->reference_id)->toBe($first->id)->and($item->fresh()->counterparty_id)->toBe($first->id);
});

test('ordinary legacy edits cannot silently infer an economic subject or bypass the audited alignment', function () {
    $user = User::factory()->create();
    $subject = economicSubject($user);
    $item = legacyCreditDebtItem($user, ['reference_id' => $subject->id]);
    expect(fn () => app(CreditDebtItemService::class)->update($user, $item, ['description' => 'Unrelated edit']))->toThrow(ValidationException::class);
    expect($item->fresh()->counterparty_id)->toBeNull()->and(Activity::where('event', 'economic_subject_aligned')->count())->toBe(0);
});

test('database history guards preserve the identity and UUID even when equality is maintained', function (string $history) {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $subject = economicSubject($user);
    $next = economicSubject($user, 'Next');
    $item = CreditDebtItem::factory()->forAccount($account)->create(['reference_id' => $subject->id]);
    if ($history === 'payment') {
        app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '25.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id]);
    } else {
        config(['counterparty_ledger.shadow_manual_enabled' => true]);
        app(ManualCreditDebtShadowService::class)->itemCreated($item);
    }
    expect(fn () => DB::transaction(fn () => DB::table('credit_debt_items')->where('id', $item->id)->update(['reference_id' => $next->id, 'counterparty_id' => $next->id])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('credit_debt_items')->where('id', $item->id)->update(['uuid' => fake()->uuid()])))->toThrow(QueryException::class);
    expect($item->fresh()->reference_id)->toBe($subject->id)->and($item->fresh()->uuid)->toBe($item->uuid);
})->with(['payment', 'ledger']);
