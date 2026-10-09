<?php

use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\CreditDebts\DerivedObligationsService;
use App\Services\Dashboard\DashboardService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['features.credits_debts.enabled' => true]);
    $this->travelTo(now()->setDate(2026, 10, 9));
});

test('open arrears survive month and year changes and dashboard uses the same complete totals', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $credit = CreditDebtItem::factory()->forAccount($account)->create([
        'type' => 'credit', 'total_amount' => 350, 'due_date' => '2025-09-04',
    ]);
    CreditDebtItem::factory()->forAccount($account)->create([
        'type' => 'debit', 'total_amount' => 980, 'due_date' => '2026-09-10',
    ]);
    $shared = aggregateShared($user, 'counterparty');
    $shared->update(['total_cents' => 7000, 'my_share_cents' => 3500, 'counterparty_share_cents' => 3500]);
    CreditDebtItem::factory()->forAccount(createTestAccount(User::factory()->create()))->create(['total_amount' => 999]);
    $this->actingAs($user)->get(route('credits-debts.index', ['year' => 2026, 'month' => 10]))
        ->assertInertia(fn (Assert $page) => $page->has('items', 3)
            ->where('summary.credits_remaining_total', '350.00')
            ->where('summary.debts_remaining_total', '1015.00'));
    $dashboard = app(DashboardService::class)->build($user, 2026, 10);
    expect($dashboard['credits_debts']['credits_open_total_raw'])->toBe(350.0)
        ->and($dashboard['analysis']['spending_capacity']['simulation_impacts']['open_credits_raw'])->toBe(350.0)
        ->and($dashboard['analysis']['spending_capacity']['simulation_impacts']['open_debts_raw'])->toBe(-1015.0)
        ->and($dashboard['credits_debts']['debts_open_total_raw'])->toBe(1015.0)
        ->and($dashboard['credits_debts']['arrears'])->toHaveCount(2)
        ->and(collect($dashboard['credits_debts']['arrears'])->pluck('origin')->unique()->all())->toBe(['manual'])
        ->and($credit->fresh()->due_date->toDateString())->toBe('2025-09-04');
    foreach ($dashboard['credits_debts']['arrears'] as $item) {
        expect($item['source_url'])->toContain('selected=');
    }
});

test('capacity simulations expose overlap only for open occurrences forecast in the selected month', function () {
    $user = User::factory()->create();
    aggregateOccurrence($user, 'expense');
    $current = aggregateOccurrence($user, 'expense', 'installment');
    $current->update(['expected_date' => '2026-10-01', 'due_date' => '2026-10-01']);
    $dashboard = app(DashboardService::class)->build($user, 2026, 10);
    $impacts = $dashboard['analysis']['spending_capacity']['simulation_impacts'];
    expect($impacts['open_debts_raw'])->toBe(-1000.0)
        ->and($impacts['forecast_expenses_raw'])->toBe(-500.0)
        ->and($impacts['forecast_debts_overlap_raw'])->toBe(500.0)
        ->and($impacts['open_debts_raw'] + $impacts['forecast_expenses_raw'] + $impacts['forecast_debts_overlap_raw'])->toBe(-1000.0);
});

test('recurring and installment arrears are aggregated once and link to their plans', function () {
    $user = User::factory()->create();
    aggregateOccurrence($user, 'income');
    aggregateOccurrence($user, 'expense', 'installment');
    $dashboard = app(DashboardService::class)->build($user, 2026, 10);
    expect($dashboard['credits_debts']['credits_open_total_raw'])->toBe(500.0)
        ->and($dashboard['credits_debts']['debts_open_total_raw'])->toBe(500.0)
        ->and($dashboard['credits_debts']['arrears'])->toHaveCount(2)
        ->and($dashboard['pending_actions']['items'])->toBe([]);
    foreach ($dashboard['credits_debts']['arrears'] as $item) {
        expect($item['source_url'])->toContain('/recurring-entries/');
    }
});

function aggregateOccurrence(User $user, string $direction, string $type = 'recurring'): RecurringEntryOccurrence
{
    $account = createTestAccount($user);
    $entry = RecurringEntry::query()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'title' => 'Affitto',
        'direction' => $direction, 'currency' => 'EUR', 'entry_type' => $type,
        'status' => 'active', 'is_active' => true, 'start_date' => '2026-09-01',
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'never',
        'expected_amount' => 500, 'is_amount_variable' => false,
    ]);

    return $entry->occurrences()->create([
        'sequence_number' => 1, 'expected_date' => '2026-09-01', 'due_date' => '2026-09-01',
        'expected_amount' => 500, 'status' => 'pending',
    ]);
}

function aggregateShared(User $user, string $payer): SharedExpenseLedgerEntry
{
    $counterparty = TrackedItem::query()->create([
        'user_id' => $user->id, 'name' => 'Mario', 'slug' => fake()->uuid(),
        'is_shared_expense_counterpart' => true, 'is_active' => true,
    ]);

    return SharedExpenseLedgerEntry::query()->create([
        'user_id' => $user->id, 'counterparty_id' => $counterparty->id, 'entry_date' => '2026-09-01',
        'title' => 'Cena', 'currency' => 'EUR', 'total_cents' => 3000,
        'paid_by' => $payer, 'my_share_cents' => 1500, 'counterparty_share_cents' => 1500, 'status' => 'recorded',
    ]);
}

test('credits debts aggregates manual recurring installment and shared without persisting copies', function () {
    $user = User::factory()->create();
    CreditDebtItem::factory()->forAccount(createTestAccount($user))->create([
        'type' => 'credit', 'total_amount' => 100, 'due_date' => '2026-10-09',
    ]);
    aggregateOccurrence($user, 'income');
    aggregateOccurrence($user, 'expense', 'installment');
    aggregateShared($user, 'self');
    aggregateShared($user, 'counterparty');
    $this->actingAs($user)->get(route('credits-debts.index', ['year' => 2026, 'month' => 10]))
        ->assertInertia(fn (Assert $page) => $page->has('items', 5)
            ->where('summary.credits_remaining_total', '615.00')
            ->where('summary.debts_remaining_total', '515.00')
            ->where('summary.net_expected_total', '100.00'));
    expect(CreditDebtItem::count())->toBe(1);
    $items = $this->getJson(route('credits-debts.index'))->assertSuccessful()->json('data');
    expect(collect($items)->pluck('origin')->sort()->values()->all())->toBe(['installment', 'manual', 'recurring', 'shared', 'shared']);
    foreach (collect($items)->where('read_only', true) as $item) {
        expect($item['source_url'])->not->toBeEmpty();
        $this->deleteJson(route('credits-debts.destroy', $item['uuid']))->assertNotFound();
        $this->postJson(route('credits-debts.payments.store', $item['uuid']), [])->assertNotFound();
    }
});

test('closed skipped future variable and other users obligations are not aggregated', function () {
    $user = User::factory()->create();
    foreach (['completed', 'skipped', 'cancelled'] as $status) {
        aggregateOccurrence($user, 'income')->forceFill(['status' => $status])->save();
    }
    aggregateOccurrence($user, 'income')->forceFill(['due_date' => '2026-12-01'])->save();
    $variable = aggregateOccurrence($user, 'income');
    $variable->recurringEntry->forceFill(['is_amount_variable' => true])->save();
    aggregateOccurrence(User::factory()->create(), 'income');
    aggregateShared(User::factory()->create(), 'self');
    expect(app(DerivedObligationsService::class)->positions($user))->toHaveCount(0);
    $variable->forceFill(['amount_confirmed_at' => now(), 'expected_amount' => 150])->save();
    expect(app(DerivedObligationsService::class)->positions($user))->toHaveCount(1);
});

test('shared balances are read live and zero disappears without duplicate manual items', function () {
    $user = User::factory()->create();
    $ledger = aggregateShared($user, 'self');
    $service = app(DerivedObligationsService::class);
    expect($service->positions($user)->first()['remaining_amount'])->toBe('15.00');
    $account = createTestAccount($user);
    $settlements = app(SharedExpenseSettlementService::class);
    $settlements->record($user, $ledger->counterparty, [
        'amount' => '10.00', 'settled_at' => '2026-10-09', 'account_uuid' => $account->uuid, 'reference' => null,
    ]);
    expect($service->positions($user)->first()['remaining_amount'])->toBe('5.00');
    $settlements->record($user, $ledger->counterparty, [
        'amount' => '5.00', 'settled_at' => '2026-10-09', 'account_uuid' => $account->uuid, 'reference' => null,
    ]);
    expect($service->positions($user))->toHaveCount(0)->and(CreditDebtItem::count())->toBe(0);
});

test('derived filters compose search type reference and status', function () {
    $user = User::factory()->create();
    aggregateOccurrence($user, 'income');
    aggregateShared($user, 'counterparty');
    $this->actingAs($user)->getJson(route('credits-debts.index', ['search' => 'Affitto', 'type' => 'credit']))->assertJsonCount(1, 'data');
    $this->getJson(route('credits-debts.index', ['status' => 'settled']))->assertJsonCount(0, 'data');
    $this->getJson(route('credits-debts.index', ['search' => 'Mario', 'type' => 'debit']))->assertJsonCount(1, 'data');
});

test('a shared posted occurrence contributes only its net shared position', function () {
    $user = User::factory()->create();
    $occurrence = aggregateOccurrence($user, 'expense');
    $ledger = aggregateShared($user, 'counterparty');
    $ledger->forceFill(['recurring_entry_occurrence_id' => $occurrence->id])->save();
    $positions = app(DerivedObligationsService::class)->positions($user);
    expect($positions)->toHaveCount(1)
        ->and($positions->first()['origin'])->toBe('shared')
        ->and($positions->first()['remaining_amount'])->toBe('15.00');
});
