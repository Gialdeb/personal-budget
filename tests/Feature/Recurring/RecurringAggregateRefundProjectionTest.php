<?php

use App\Models\Category;
use App\Models\RecurringPlanRefundPayment;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\RecurringPlanRefundService;
use App\Services\Recurring\TransactionRefundService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 6, 20)->setTime(12, 0));
    $this->actor = User::factory()->create();
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $category = Category::create(['user_id' => $this->actor->id, 'name' => 'Refund projection', 'slug' => 'refund-projection', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->payload = [
        'title' => 'Refund projection', 'account_id' => $this->account->id, 'category_id' => $category->id,
        'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active',
        'recurrence_type' => 'yearly', 'recurrence_interval' => 1, 'recurrence_rule' => ['mode' => 'month_day', 'month' => 1, 'day' => 1],
        'start_date' => '2026-01-01', 'end_mode' => 'never', 'expected_amount' => 100,
        'auto_generate_occurrences' => true, 'auto_create_transaction' => false, 'is_active' => true,
    ];
    $this->actingAs($this->actor);
});

test('aggregate refunded occurrences expose the marker while keeping their paid settlement', function (string $kind) {
    $payload = $this->payload;
    if ($kind === 'installment') {
        $payload = [...$payload, 'entry_type' => 'installment', 'recurrence_type' => 'monthly', 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 1], 'total_amount' => 1200, 'installments_count' => 12, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 12];
    }
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $payload);
    $count = $kind === 'installment' ? 5 : 1;
    $paid = $entry->occurrences()->orderBy('sequence_number')->limit($count)->get();
    foreach ($paid as $row) {
        app(RecurringEntryPostingService::class)->post($row);
    }
    app(RecurringPlanRefundService::class)->refund($this->actor, $entry->uuid, [
        'request_uuid' => (string) Str::uuid(), 'transaction_date' => '2026-06-20', 'account_uuid' => $this->account->uuid,
        'expected_total_cents' => $count * 10000, 'irreversible' => true,
    ]);
    $entryBefore = $entry->fresh()->getAttributes();
    $rowsBefore = $entry->occurrences()->orderBy('id')->get()->map->getAttributes()->all();
    $accountBefore = $this->account->fresh()->getAttributes();
    $data = $this->getJson(route('recurring-entries.show', ['recurringEntry' => $entry->uuid, 'highlight' => $paid->first()->uuid]))->assertOk()->json();
    $rows = collect($data['recurringEntry']['occurrences']);
    $refunded = $rows->whereIn('uuid', $paid->pluck('uuid'));
    expect($refunded)->toHaveCount($count);
    foreach ($refunded as $row) {
        expect($row['status'])->toBe('completed')
            ->and($row['settlement_status'])->toBe('settled')
            ->and($row['is_refunded'])->toBeTrue()
            ->and($row['converted_transaction']['is_refunded'])->toBeTrue();
    }
    foreach ($rows->whereNotIn('uuid', $paid->pluck('uuid')) as $row) {
        expect($row['is_refunded'])->toBeFalse();
    }
    if ($kind === 'installment') {
        expect($rows)->toHaveCount(12)
            ->and($rows->where('is_refunded', false))->toHaveCount(7);
    }
    expect($data['recurringEntry']['summary']['settled_occurrences'])->toBe($count)
        ->and((float) $data['recurringEntry']['summary']['paid_amount'])->toBe($count * 100.0)
        ->and($entry->fresh()->getAttributes())->toBe($entryBefore)
        ->and($entry->occurrences()->orderBy('id')->get()->map->getAttributes()->all())->toBe($rowsBefore)
        ->and($this->account->fresh()->getAttributes())->toBe($accountBefore);
    $page = $this->getJson(route('recurring-entries.show', ['recurringEntry' => $entry->uuid, 'occurrence_year' => 2026, 'occurrence_offset' => 0]))->assertOk()->json();
    expect(collect($page['occurrences'])->where('is_refunded', true))->toHaveCount($count);
})->with(['twelve installments with five refunded' => ['installment'], 'annual subscription with one refunded' => ['annual']]);

test('ordinary refunded occurrences retain their existing refund metadata', function () {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $row = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $payment = app(RecurringEntryPostingService::class)->post($row);
    $refund = app(TransactionRefundService::class)->refund($payment);
    $before = $row->fresh()->getAttributes();
    $data = $this->getJson(route('recurring-entries.show', ['recurringEntry' => $entry->uuid, 'highlight' => $row->uuid]))->assertOk()->json();
    $projected = collect($data['recurringEntry']['occurrences'])->firstWhere('uuid', $row->uuid);
    expect($projected['is_refunded'])->toBeTrue()
        ->and($projected['converted_transaction']['is_refunded'])->toBeTrue()
        ->and($projected['converted_transaction']['refund_transaction']['uuid'])->toBe($refund->uuid)
        ->and($row->fresh()->getAttributes())->toBe($before)
        ->and(RecurringPlanRefundPayment::count())->toBe(0);
});
