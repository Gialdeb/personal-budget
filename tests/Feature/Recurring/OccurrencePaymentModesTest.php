<?php

use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\CreditDebts\DerivedObligationsService;
use App\Services\Recurring\ManageOccurrencePaymentService;
use App\Services\Recurring\RecurringEntryPostingService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 10, 10)->setTime(12, 0));
    config(['features.credits_debts.enabled' => true]);
});

function paymentModesOccurrence(string $direction, bool $variable): RecurringEntryOccurrence
{
    $user = User::factory()->create();
    UserYear::create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Payment', 'slug' => 'payment', 'direction_type' => $direction, 'group_type' => $direction, 'is_active' => true, 'is_selectable' => true]);
    $entry = RecurringEntry::create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
        'title' => 'Payment modes', 'direction' => $direction, 'currency' => 'EUR', 'entry_type' => 'recurring',
        'status' => 'active', 'is_active' => true, 'start_date' => '2026-09-09',
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'never',
        'expected_amount' => 500, 'is_amount_variable' => $variable, 'auto_create_transaction' => false,
        'auto_generate_occurrences' => false,
    ]);

    return $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-09-09', 'due_date' => '2026-09-09', 'expected_amount' => 500, 'status' => 'pending']);
}

function assertPaymentModesProjection(RecurringEntryOccurrence $occurrence, string $remaining): void
{
    $side = $occurrence->recurringEntry->direction->value === 'income' ? 'credits' : 'debts';
    test()->get(route('credits-debts.index', ['year' => 2026, 'month' => 10]))->assertInertia(fn (Assert $page) => $page
        ->has('items', $remaining === '0.00' ? 0 : 1)
        ->where('summary.'.$side.'_remaining_total', $remaining));
    test()->get(route('dashboard', ['year' => 2026, 'month' => 10]))->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.credits_debts.'.$side.'_open_total_raw', fn ($value): bool => (float) $value === (float) $remaining)
        ->has('dashboard.credits_debts.arrears', $remaining === '0.00' ? 0 : 1));
    expect(CreditDebtItem::count())->toBe(0);
}

function submitPaymentMode(RecurringEntryOccurrence $occurrence, string $flow, string $mode, string $amount, ?string $total = null): TestResponse
{
    $entry = $occurrence->recurringEntry;
    $modeData = ['payment_type' => $mode, 'final_total_amount' => $total];
    if ($flow === 'manage') {
        return test()->post(route('recurring-entries.occurrences.manage-payment', [$entry->uuid, $occurrence->uuid]), [
            ...$modeData, 'request_id' => (string) Str::uuid(), 'reason' => 'already_paid', 'action' => 'record',
            'actual_amount' => $amount, 'actual_date' => '2026-10-10', 'confirm_new_payment' => true,
        ]);
    }

    return test()->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [
        ...$modeData, 'payment_occurrence_uuid' => $occurrence->uuid, 'transaction_day' => 10,
        'type_key' => $entry->direction->value, 'account_uuid' => $entry->account->uuid,
        'category_uuid' => $entry->category->uuid, 'amount' => $amount, 'description' => 'Payment modes',
    ]);
}

dataset('payment mode entries', [
    'fixed income' => ['income', false], 'fixed expense' => ['expense', false],
    'variable income' => ['income', true], 'variable expense' => ['expense', true],
]);

test('overdue unpaid estimates remain visible without confirming the amount', function (string $direction, bool $variable) {
    $occurrence = paymentModesOccurrence($direction, $variable);
    $this->actingAs($occurrence->recurringEntry->user);
    assertPaymentModesProjection($occurrence, '500.00');
    expect($occurrence->fresh()->amount_confirmed_at)->toBeNull()->and(Transaction::count())->toBe(0);
})->with('payment mode entries');

test('explicit advances preserve the residual and a final payment preserves prior allocations', function (string $direction, bool $variable, string $flow) {
    $occurrence = paymentModesOccurrence($direction, $variable);
    $this->actingAs($occurrence->recurringEntry->user);
    submitPaymentMode($occurrence, $flow, 'advance', '300.00')->assertSessionHasNoErrors();
    expect($occurrence->fresh()->remainingPaymentCents())->toBe(20000)
        ->and($occurrence->fresh()->payment_actual_amount)->toBeNull();
    assertPaymentModesProjection($occurrence, '200.00');
    $firstAllocation = $occurrence->paymentAllocations()->sole()->getAttributes();
    $firstTransaction = Transaction::sole()->getAttributes();
    submitPaymentMode($occurrence, $flow, 'final', $variable ? '150.00' : '200.00', $variable ? '450.00' : '500.00')->assertSessionHasNoErrors();
    expect($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and($occurrence->paymentAllocations()->count())->toBe(2)
        ->and($occurrence->paymentAllocations()->find($firstAllocation['id'])->getAttributes())->toBe($firstAllocation)
        ->and(Transaction::find($firstTransaction['id'])->getAttributes())->toBe($firstTransaction)
        ->and(Transaction::count())->toBe(2)
        ->and($occurrence->fresh()->expected_amount)->toBe('500.00');
    assertPaymentModesProjection($occurrence, '0.00');
})->with('payment mode entries')->with(['store', 'manage']);

test('an inconsistent final amount rolls back without changing previous payments', function (string $direction, bool $variable, string $flow) {
    $occurrence = paymentModesOccurrence($direction, $variable);
    $this->actingAs($occurrence->recurringEntry->user);
    submitPaymentMode($occurrence, $flow, 'advance', '300.00')->assertSessionHasNoErrors();
    $before = $occurrence->fresh()->getAttributes();
    submitPaymentMode($occurrence, $flow, 'final', '100.00', $variable ? '450.00' : '500.00')->assertSessionHasErrors('final_total_amount');
    expect($occurrence->fresh()->getAttributes())->toBe($before)
        ->and(Transaction::count())->toBe(1)->and($occurrence->paymentAllocations()->count())->toBe(1);
})->with('payment mode entries')->with(['store', 'manage']);

test('a single final payment confirms the variable total while retaining the original forecast', function (string $direction, bool $variable, string $flow) {
    $occurrence = paymentModesOccurrence($direction, $variable);
    $this->actingAs($occurrence->recurringEntry->user);
    $total = $variable ? '450.00' : '500.00';
    submitPaymentMode($occurrence, $flow, 'final', $total, $total)->assertSessionHasNoErrors();
    expect($occurrence->fresh()->payment_actual_amount)->toBe($total)
        ->and($occurrence->fresh()->expected_amount)->toBe('500.00')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and($occurrence->paymentAllocations()->sum('amount_cents'))->toBe($variable ? 45000 : 50000)
        ->and(Transaction::count())->toBe(1);
    assertPaymentModesProjection($occurrence, '0.00');
})->with('payment mode entries')->with(['store', 'manage']);

test('an automatic payment corrected to an advance remains partial and accepts a separate final payment', function (string $direction, bool $variable) {
    $occurrence = paymentModesOccurrence($direction, $variable);
    $user = $occurrence->recurringEntry->user;
    $this->actingAs($user);
    $automatic = app(RecurringEntryPostingService::class)->post($occurrence, $user, ['automatic_posting' => true]);
    $url = route('recurring-entries.occurrences.manage-payment', [$occurrence->recurringEntry->uuid, $occurrence->uuid]);
    $data = ['request_id' => (string) Str::uuid(), 'reason' => 'different_amount', 'action' => 'correct',
        'payment_type' => 'advance', 'actual_amount' => '300.00', 'actual_date' => '2026-10-10'];
    $this->post($url, $data)->assertSessionHasNoErrors();
    $this->post($url, $data)->assertSessionHasNoErrors();
    expect($occurrence->fresh()->remainingPaymentCents())->toBe(20000)
        ->and($occurrence->fresh()->payment_management_state)->toBe('partial')
        ->and($automatic->fresh()->amount)->toBe('300.00');
    $management = app(ManageOccurrencePaymentService::class)->presentation($occurrence->fresh(), $user);
    expect($management['has_automatic_transaction'])->toBeFalse();
    assertPaymentModesProjection($occurrence, '200.00');
    $position = app(DerivedObligationsService::class)->positions($user)->sole();
    expect($position['paid_amount'])->toBe('300.00')->and($position['status'])->toBe('partial');
    submitPaymentMode($occurrence, 'manage', 'final', $variable ? '150.00' : '200.00', $variable ? '450.00' : '500.00')->assertSessionHasNoErrors();
    expect($automatic->fresh()->amount)->toBe('300.00')->and(Transaction::count())->toBe(2);
    assertPaymentModesProjection($occurrence, '0.00');
})->with('payment mode entries');

test('linking an existing payment is idempotent and leaves its transaction untouched', function (string $direction, bool $variable) {
    $occurrence = paymentModesOccurrence($direction, $variable);
    $entry = $occurrence->recurringEntry;
    $this->actingAs($entry->user);
    $existing = userTransaction($entry->user, $entry->account, ['kind' => 'manual', 'direction' => $direction, 'amount' => '300.00', 'transaction_date' => '2026-10-10']);
    $before = $existing->fresh()->getAttributes();
    $data = ['request_id' => (string) Str::uuid(), 'reason' => 'already_paid', 'action' => 'record',
        'payment_type' => 'advance', 'actual_amount' => '300.00', 'actual_date' => '2026-10-10', 'transaction_uuid' => $existing->uuid];
    $url = route('recurring-entries.occurrences.manage-payment', [$entry->uuid, $occurrence->uuid]);
    $this->post($url, $data)->assertSessionHasNoErrors();
    $this->post($url, $data)->assertSessionHasNoErrors();
    expect($existing->fresh()->getAttributes())->toBe($before)
        ->and(Transaction::count())->toBe(1)->and($occurrence->paymentAllocations()->count())->toBe(1);
    assertPaymentModesProjection($occurrence, '200.00');
})->with('payment mode entries');

test('an existing final payment settles allocations without changing either transaction', function (string $direction, bool $variable) {
    $occurrence = paymentModesOccurrence($direction, $variable);
    $entry = $occurrence->recurringEntry;
    $this->actingAs($entry->user);
    submitPaymentMode($occurrence, 'store', 'advance', '300.00')->assertSessionHasNoErrors();
    $first = Transaction::sole();
    $firstBefore = $first->getAttributes();
    $balance = userTransaction($entry->user, $entry->account, ['kind' => 'manual', 'direction' => $direction, 'amount' => $variable ? '150.00' : '200.00', 'transaction_date' => '2026-10-10']);
    $before = $balance->fresh()->getAttributes();
    $data = ['request_id' => (string) Str::uuid(), 'reason' => 'already_paid', 'action' => 'record',
        'payment_type' => 'final', 'final_total_amount' => $variable ? '450.00' : '500.00',
        'actual_amount' => $variable ? '150.00' : '200.00', 'actual_date' => '2026-10-10', 'transaction_uuid' => $balance->uuid];
    $url = route('recurring-entries.occurrences.manage-payment', [$entry->uuid, $occurrence->uuid]);
    $this->post($url, $data)->assertSessionHasNoErrors();
    $this->post($url, $data)->assertSessionHasNoErrors();
    expect($first->fresh()->getAttributes())->toBe($firstBefore)
        ->and($balance->fresh()->getAttributes())->toBe($before)
        ->and(Transaction::count())->toBe(2)->and($occurrence->paymentAllocations()->count())->toBe(2);
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('recurringEntry.occurrences.0.payments.0.classification', 'advance')
        ->where('recurringEntry.occurrences.0.payments.1.classification', 'balance'));
    assertPaymentModesProjection($occurrence, '0.00');
})->with('payment mode entries');
