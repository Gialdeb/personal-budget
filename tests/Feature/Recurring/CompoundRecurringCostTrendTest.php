<?php

use App\Models\Category;
use App\Models\CompoundRecurringEntry;
use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Recurring\CompoundRecurringCostTrendService;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 20));
    $this->user = User::factory()->create();
    $this->account = createTestAccount($this->user);
    $category = Category::query()->create(['user_id' => $this->user->id, 'name' => 'Imposte', 'slug' => 'trend-tax', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->plan = CompoundRecurringEntry::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id, 'name' => 'IMU', 'category_id' => $category->id]);
    $this->children = collect();
    foreach (['Acconto', 'Saldo'] as $index => $name) {
        $entry = RecurringEntry::query()->create([
            'user_id' => $this->user->id, 'account_id' => $this->account->id, 'title' => $name,
            'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active',
            'is_active' => true, 'is_amount_variable' => true, 'start_date' => '2023-01-01', 'recurrence_type' => 'yearly',
            'recurrence_interval' => 1, 'end_mode' => 'never', 'expected_amount' => 100, 'auto_create_transaction' => false,
        ]);
        $entry->periods()->create(['starts_on' => '2023-01-01', 'kind' => 'created', 'sequence_offset' => 0, 'terms' => []]);
        $this->plan->components()->create(['name' => $name, 'recurring_entry_id' => $entry->id]);
        foreach ([2024, 2025, 2026] as $year) {
            $occurrence = $entry->occurrences()->create(['sequence_number' => $year - 2023, 'expected_date' => $year.($index === 0 ? '-06-16' : '-12-16'), 'due_date' => ($year + 1).'-01-15', 'expected_amount' => $year === 2025 ? 120 : 100, 'status' => 'generated']);
            if ($year === 2026) {
                continue;
            }
            $tx = Transaction::query()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id, 'direction' => 'expense', 'amount' => $year === 2025 ? 120 : 100, 'currency' => 'EUR', 'transaction_date' => ($year + 1).'-01-15', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed', 'recurring_entry_occurrence_id' => $occurrence->id]);
            $occurrence->update(['converted_transaction_id' => $tx->id, 'matched_transaction_id' => $tx->id]);
        }
        $this->children->push($entry);
    }
    $this->actingAs($this->user);
});

test('compound trend attributes late real payments to original years and compares complete years only', function () {
    $data = app(CompoundRecurringCostTrendService::class)->years($this->plan);
    expect($data)->toHaveCount(2)->and($data[0]['year'])->toBe(2024)->and($data[0]['total'])->toEqual(200)
        ->and($data[1]['total'])->toEqual(240)->and($data[1]['components'])->toHaveCount(2)
        ->and($data[1]['comparison']['difference'])->toEqual(40)->and($data[1]['comparison']['ratio'])->toEqual(0.2);
    $this->getJson(route('recurring-entries.show', $this->plan->uuid))->assertSuccessful()
        ->assertJsonPath('recurringEntry.compound_cost_trend.1.year', 2025)
        ->assertJsonPath('recurringEntry.compound_cost_trend.1.total', 240);
    $this->actingAs(User::factory()->create())->getJson(route('recurring-entries.show', $this->plan->uuid))->assertNotFound();
});

test('compound trend sums allocation quotas once includes archived history and retains partial totals', function () {
    $rows = $this->children->map(fn ($entry) => $entry->occurrences()->whereYear('expected_date', 2025)->first());
    foreach ($rows as $row) {
        $tx = $row->convertedTransaction;
        $row->update(['converted_transaction_id' => null, 'matched_transaction_id' => null]);
        $tx->update(['status' => 'draft']);
    }
    $tx = Transaction::query()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id, 'direction' => 'expense', 'amount' => 200, 'currency' => 'EUR', 'transaction_date' => '2026-02-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    foreach ($rows as $row) {
        $row->paymentAllocations()->create(['transaction_id' => $tx->id, 'amount_cents' => 5000]);
    }
    $this->plan->components()->first()->forceFill(['archived_at' => now()])->save();
    $data = app(CompoundRecurringCostTrendService::class)->years($this->plan);
    expect($data[1]['total'])->toEqual(100)->and($data[1]['components'][0]['archived'])->toBeTrue()
        ->and($data[1]['components'][0]['total'])->toEqual(50)->and($data[1]['comparison'])->toBeNull();
});

test('compound trend suppresses changes for skipped missing and interrupted histories', function (string $condition) {
    $entry = $this->children->first();
    if ($condition === 'skipped') {
        $entry->occurrences()->whereYear('expected_date', 2025)->update(['status' => 'skipped']);
    } elseif ($condition === 'missing') {
        $entry->occurrences()->whereYear('expected_date', 2025)->update(['expected_date' => '2024-11-01']);
    } else {
        $entry->periods()->update(['ends_on' => '2025-06-01']);
    }
    $data = app(CompoundRecurringCostTrendService::class)->years($this->plan);
    expect($data[1]['comparison'])->toBeNull();
})->with(['skipped', 'missing', 'interrupted']);

test('compound trend excludes unavailable transactions without inventing annual comparisons', function (string $state) {
    $transaction = $this->children->first()->occurrences()->whereYear('expected_date', 2025)->first()->convertedTransaction;
    if ($state === 'deleted') {
        $transaction->delete();
    } elseif ($state === 'future') {
        $transaction->update(['transaction_date' => '2027-01-01']);
    } else {
        $transaction->update(['status' => 'draft']);
    }
    $data = app(CompoundRecurringCostTrendService::class)->years($this->plan);
    expect($data[1]['total'])->toEqual(120)->and($data[1]['complete'])->toBeFalse()->and($data[1]['comparison'])->toBeNull();
})->with(['deleted', 'future', 'draft']);

test('current annual totals include real partial payments but never expose an annual change', function () {
    $row = $this->children->first()->occurrences()->whereYear('expected_date', 2026)->first();
    Transaction::query()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id, 'direction' => 'expense', 'amount' => 20, 'currency' => 'EUR', 'transaction_date' => '2026-10-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed', 'recurring_entry_occurrence_id' => $row->id]);
    $data = app(CompoundRecurringCostTrendService::class)->years($this->plan);
    expect($data[2]['year'])->toBe(2026)->and($data[2]['total'])->toEqual(20)->and($data[2]['comparison'])->toBeNull();
});

test('archiving a component preserves its complete historical annual comparison', function () {
    $this->plan->components()->first()->forceFill(['archived_at' => now()])->save();
    $data = app(CompoundRecurringCostTrendService::class)->years($this->plan);
    expect($data[1]['components'][0]['archived'])->toBeTrue()->and($data[1]['total'])->toEqual(240)
        ->and($data[1]['comparison']['difference'])->toEqual(40);
});
