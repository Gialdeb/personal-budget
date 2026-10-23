<?php

use App\Http\Resources\RecurringEntryOccurrenceResource;
use App\Jobs\Automation\RunRecurringPipelineJob;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use App\Services\Recurring\AutomaticPaymentVerificationService;
use App\Services\Recurring\ManageOccurrencePaymentService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use App\Services\Recurring\UpdateRecurringOccurrenceService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\RecurringEntryLifecycleService;
use App\Services\Transactions\TransactionMutationService;
use App\Services\Transactions\TransactionNavigationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 10, 9)->setTime(12, 0));
});

function paymentVerificationOccurrence(string $direction = 'expense', bool $automatic = true, string $type = 'recurring'): RecurringEntryOccurrence
{
    $user = User::factory()->create();
    UserYear::create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);
    $accountType = AccountType::query()->firstOrCreate(['code' => 'payment_account'], ['name' => 'Conto', 'balance_nature' => 'asset']);
    $account = Account::query()->create([
        'user_id' => $user->id, 'account_type_id' => $accountType->id,
        'name' => 'Conto test', 'currency' => 'EUR', 'currency_code' => 'EUR',
        'opening_balance' => 1000, 'current_balance' => 1000, 'is_manual' => true, 'is_active' => true,
    ]);
    $entry = RecurringEntry::query()->create([
        'user_id' => $user->id, 'account_id' => $account->id,
        'title' => 'Pagamento di prova', 'direction' => $direction, 'currency' => 'EUR',
        'entry_type' => $type, 'status' => 'active', 'is_active' => true,
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 9],
        'start_date' => '2026-10-09', 'end_mode' => 'never',
        'expected_amount' => 15, 'total_amount' => $type === 'installment' ? 30 : null,
        'installments_count' => $type === 'installment' ? 2 : null,
        'auto_generate_occurrences' => true, 'auto_create_transaction' => $automatic,
    ]);

    return $entry->occurrences()->create([
        'sequence_number' => 1, 'expected_date' => '2026-10-09', 'due_date' => '2026-10-09',
        'expected_amount' => 15, 'status' => 'pending',
    ]);
}

test('automatic posting accounts immediately but awaits verification without duplicate retries', function (string $direction, string $type) {
    $occurrence = paymentVerificationOccurrence($direction, type: $type);
    $pipeline = app(RecurringEntryLifecycleService::class);
    $pipeline->runAutomationPipeline();
    $transaction = $occurrence->fresh()->convertedTransaction;
    expect($transaction)->not->toBeNull()
        ->and($transaction->payment_verified_at)->toBeNull()
        ->and($transaction->payment_verification_due_at->equalTo(now()->addDays(7)))->toBeTrue()
        ->and((float) $transaction->account->current_balance)->toBe($direction === 'income' ? 1015.0 : 985.0);
    $pipeline->runAutomationPipeline();
    expect(Transaction::where('recurring_entry_occurrence_id', $occurrence->id)->count())->toBe(1);
})->with([['expense', 'recurring'], ['income', 'recurring'], ['expense', 'installment']]);

test('manual confirmation preserves identity amount and balance', function () {
    $occurrence = paymentVerificationOccurrence();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    $this->actingAs($occurrence->recurringEntry->user)->post(route('transactions.payment-confirmation', $transaction->uuid))->assertRedirect();
    $transaction->refresh();
    expect($transaction->payment_verified_at)->not->toBeNull()
        ->and($transaction->payment_verification_method)->toBe('manual')
        ->and((float) $transaction->amount)->toBe(15.0)
        ->and((float) $transaction->account->current_balance)->toBe(985.0)
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($transaction->id);
});

test('verification stays pending after seven days without accounting movements', function () {
    $transaction = app(RecurringEntryPostingService::class)->post(paymentVerificationOccurrence(), metadata: ['automatic_posting' => true]);
    $verification = app(AutomaticPaymentVerificationService::class);
    $this->travel(7)->days();
    $this->travel(-1)->seconds();
    expect($verification->confirmExpired())->toBe(0);
    $this->travel(1)->seconds();
    expect($verification->confirmExpired())->toBe(0)
        ->and($verification->confirmExpired())->toBe(0)
        ->and($transaction->fresh()->payment_verification_method)->toBeNull()
        ->and(Transaction::count())->toBe(1)
        ->and((float) $transaction->account->fresh()->current_balance)->toBe(985.0);
});

test('payment problem before or after verification rectifies registration and blocks automatic retries', function (bool $expired) {
    $occurrence = paymentVerificationOccurrence();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    if ($expired) {
        $this->travel(8)->days();
        app(AutomaticPaymentVerificationService::class)->confirmExpired();
    }
    $this->actingAs($occurrence->recurringEntry->user)->post(route('transactions.payment-problem', $transaction->uuid), ['confirm_rectification' => true])->assertRedirect();
    expect(Transaction::withTrashed()->find($transaction->id)->payment_original_amount)->toBe('15.00')
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($transaction->id)
        ->and($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->automatic_posting_blocked_at)->not->toBeNull()
        ->and($occurrence->fresh()->payment_failed_at)->not->toBeNull()
        ->and((float) $occurrence->recurringEntry->account->fresh()->current_balance)->toBe(1000.0);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(1);
    app(App\Services\Recurring\RecurringEntryLifecycleService::class)->synchronize($occurrence->recurringEntry);
    expect(Transaction::count())->toBe(1);
    $manual = app(RecurringEntryPostingService::class)->post($occurrence->fresh(), $occurrence->recurringEntry->user);
    expect($manual->payment_verification_started_at)->toBeNull()
        ->and($occurrence->fresh()->status->value)->toBe('completed');
})->with([false, true]);

test('waiting mode creates no transaction and manual settlement remains available', function () {
    $occurrence = paymentVerificationOccurrence(automatic: false);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(0);
    $resource = (new RecurringEntryOccurrenceResource($occurrence->fresh()))->resolve();
    expect($resource['settlement_status'])->toBe('due');
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, $occurrence->recurringEntry->user);
    expect($transaction->payment_verification_started_at)->toBeNull();
});

test('other users cannot confirm or invalidate automatic transactions', function () {
    $transaction = app(RecurringEntryPostingService::class)->post(paymentVerificationOccurrence(), metadata: ['automatic_posting' => true]);
    $this->actingAs(User::factory()->create());
    $this->post(route('transactions.payment-confirmation', $transaction->uuid))->assertForbidden();
    $this->post(route('transactions.payment-problem', $transaction->uuid), ['confirm_rectification' => true])->assertForbidden();
    expect($transaction->fresh()->payment_verified_at)->toBeNull();
});

test('variable automatic amounts post before confirmation and remain correctable afterwards', function () {
    $occurrence = paymentVerificationOccurrence();
    $occurrence->recurringEntry->forceFill(['is_amount_variable' => true])->save();
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    $transaction = $occurrence->fresh()->convertedTransaction;
    expect((float) $transaction->amount)->toBe(15.0)->and($occurrence->fresh()->amount_confirmed_at)->toBeNull();
    app(UpdateRecurringOccurrenceAmountService::class)->update($occurrence, $occurrence->recurringEntry->user, 23);
    expect((float) $transaction->fresh()->amount)->toBe(23.0)->and($transaction->fresh()->payment_verified_at)->toBeNull();
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(1);
});

test('skipped occurrences do not enter payment verification', function () {
    $occurrence = paymentVerificationOccurrence();
    $occurrence->forceFill(['status' => 'skipped'])->save();
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(0);
});

test('payment problem rollback restores the transaction and occurrence when accounting fails', function () {
    $occurrence = paymentVerificationOccurrence();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    $this->mock(TransactionMutationService::class)
        ->shouldReceive('recalculateAccount')->once()->andThrow(new RuntimeException('Accounting failure'));
    expect(fn () => app(AutomaticPaymentVerificationService::class)->reportProblem($transaction, true))->toThrow(RuntimeException::class);
    expect($transaction->fresh())->not->toBeNull()
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($transaction->id)
        ->and($occurrence->fresh()->automatic_posting_blocked_at)->toBeNull()
        ->and((float) $transaction->account->fresh()->current_balance)->toBe(985.0);
});

test('failure leaves other occurrences and transactions untouched and exposes only the open residual', function () {
    $occurrence = paymentVerificationOccurrence();
    $other = $occurrence->recurringEntry->occurrences()->create([
        'sequence_number' => 2, 'expected_date' => '2026-11-09', 'expected_amount' => 15, 'status' => 'pending',
    ]);
    $unrelated = app(RecurringEntryPostingService::class)->post(paymentVerificationOccurrence(), metadata: ['automatic_posting' => true]);
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    app(AutomaticPaymentVerificationService::class)->reportProblem($transaction, true);
    expect($unrelated->fresh())->not->toBeNull()
        ->and($other->fresh()->automatic_posting_blocked_at)->toBeNull()
        ->and($other->fresh()->status->value)->toBe('pending')
        ->and(RecurringEntryOccurrence::openObligations()->pluck('id')->all())->toBe([$occurrence->id]);
    app(RecurringEntryPostingService::class)->post($occurrence->fresh(), $occurrence->recurringEntry->user);
    expect(RecurringEntryOccurrence::openObligations()->count())->toBe(0);
});

test('dashboard lists pending payment verification independently of the selected period and removes confirmed items', function () {
    $occurrence = paymentVerificationOccurrence();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    $user = $occurrence->recurringEntry->user;
    $dashboard = app(DashboardService::class);
    $data = $dashboard->build($user, 2026, 11);
    $item = collect($data['pending_actions']['items'])->firstWhere('transaction_uuid', $transaction->uuid);
    expect($item)->not->toBeNull()
        ->and($item['requires_payment_confirmation'])->toBeTrue()
        ->and($item['plan_title'])->toBe($occurrence->recurringEntry->title)
        ->and($item['action_url'])->toContain($transaction->uuid);
    app(AutomaticPaymentVerificationService::class)->confirm($transaction);
    expect(collect($dashboard->build($user, 2026, 11)['pending_actions']['items'])->firstWhere('transaction_uuid', $transaction->uuid))->toBeNull();
    $otherUser = User::factory()->create();
    expect(collect($dashboard->build($otherUser, 2026, 11)['pending_actions']['items'])->firstWhere('transaction_uuid', $transaction->uuid))->toBeNull();
});

test('matched transactions and unconfirmed variable amounts are excluded from open obligations', function () {
    $occurrence = paymentVerificationOccurrence(automatic: false);
    $transaction = app(RecurringEntryPostingService::class)->post(paymentVerificationOccurrence());
    $occurrence->forceFill(['matched_transaction_id' => $transaction->id])->save();
    $variable = paymentVerificationOccurrence(automatic: false);
    $variable->recurringEntry->forceFill(['is_amount_variable' => true])->save();
    expect(RecurringEntryOccurrence::openObligations()->count())->toBe(0);
    $variable->forceFill(['amount_confirmed_at' => now()])->save();
    expect(RecurringEntryOccurrence::openObligations()->pluck('id')->all())->toBe([$variable->id]);
});

test('automatic lifecycle posts past variable schedules and waits only for future dates', function (string $start, int $expected) {
    $occurrence = paymentVerificationOccurrence('income');
    $entry = $occurrence->recurringEntry;
    $occurrence->delete();
    $entry->update(['start_date' => $start, 'is_amount_variable' => true, 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 1]]);
    $lifecycle = app(App\Services\Recurring\RecurringEntryLifecycleService::class);
    $lifecycle->synchronize($entry);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe($expected)->and(Transaction::pluck('recurring_entry_occurrence_id')->unique()->count())->toBe($expected);
    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->whereNotNull('amount_confirmed_at')->count())->toBe(0);
})->with([['2026-11-01', 0], ['2026-10-01', 1], ['2026-08-01', 3]]);

test('invalid automatic amount waits without creating a zero transaction', function () {
    $occurrence = paymentVerificationOccurrence();
    $occurrence->update(['expected_amount' => null]);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(0);
});

test('changing the actual bank date preserves scheduled verification and problem reporting', function () {
    $occurrence = paymentVerificationOccurrence('income');
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    $transaction = $occurrence->fresh()->convertedTransaction;
    $user = $occurrence->recurringEntry->user;
    $category = Category::create(['user_id' => $user->id, 'name' => 'Income', 'slug' => 'income-date-test', 'direction_type' => 'income', 'group_type' => 'income', 'is_active' => true, 'is_selectable' => true]);
    app(TransactionMutationService::class)->update($user, $transaction, [
        'account_id' => $transaction->account_id, 'category_id' => $category->id, 'direction' => 'income',
        'type_key' => 'income', 'amount' => 15, 'transaction_date' => '2026-10-12', 'description' => 'Bank date correction', 'notes' => null,
    ]);
    expect($transaction->fresh()->transaction_date->toDateString())->toBe('2026-10-12')
        ->and($transaction->fresh()->kind->value)->toBe('scheduled')
        ->and($occurrence->fresh()->expected_date->toDateString())->toBe('2026-10-09');
    app(AutomaticPaymentVerificationService::class)->reportProblem($transaction->fresh(), true);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(1)->and($occurrence->fresh()->automatic_posting_blocked_at)->not->toBeNull();
});

test('scheduler job can run twice and also posts generated occurrences', function () {
    $occurrence = paymentVerificationOccurrence();
    $occurrence->update(['status' => 'generated']);
    $job = new RunRecurringPipelineJob;
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
    expect(Transaction::where('recurring_entry_occurrence_id', $occurrence->id)->count())->toBe(1);
});

test('the transaction endpoint permits actual date and amount corrections but protects plan fields', function () {
    $occurrence = paymentVerificationOccurrence('income');
    $category = Category::create(['user_id' => $occurrence->recurringEntry->user_id, 'name' => 'Salary', 'slug' => 'salary-endpoint', 'direction_type' => 'income', 'group_type' => 'income', 'is_active' => true, 'is_selectable' => true]);
    $occurrence->recurringEntry->update(['category_id' => $category->id]);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    $transaction = $occurrence->fresh()->convertedTransaction;
    $this->actingAs($occurrence->recurringEntry->user)->patch(route('transactions.update', ['year' => 2026, 'month' => 10, 'transaction' => $transaction->uuid]), [
        'account_uuid' => $transaction->account->uuid, 'category_uuid' => $category->uuid,
        'type_key' => 'income', 'transaction_day' => 12, 'amount' => 19, 'description' => 'Corrected', 'notes' => null,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($transaction->fresh()->transaction_date->toDateString())->toBe('2026-10-12')->and((float) $transaction->fresh()->amount)->toBe(19.0)->and($transaction->fresh()->kind->value)->toBe('scheduled');
    $this->post(route('transactions.payment-confirmation', $transaction->uuid))->assertSessionHasNoErrors();
    expect($transaction->fresh()->payment_verified_at)->not->toBeNull();
});

function sharedPaymentVerificationOccurrence(int $mine, int $other): RecurringEntryOccurrence
{
    $occurrence = paymentVerificationOccurrence();
    $entry = $occurrence->recurringEntry;
    $counterparty = TrackedItem::query()->create([
        'user_id' => $entry->user_id, 'name' => 'Partner', 'slug' => 'partner',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    $entry->update([
        'expected_amount' => 8, 'is_amount_variable' => true,
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'self',
        'shared_expense_my_share_cents' => $mine,
        'shared_expense_counterparty_share_cents' => $other,
    ]);
    $occurrence->update([
        'expected_amount' => 8,
        'shared_expense_paid_by' => 'self',
        'shared_expense_my_share_cents' => $mine,
        'shared_expense_counterparty_share_cents' => $other,
    ]);

    return $occurrence;
}

test('correcting a shared automatic amount preserves the plan allocation on every correction', function (int $mine, int $other, float $amount, int $expectedMine, int $expectedOther, bool $posted) {
    $occurrence = sharedPaymentVerificationOccurrence($mine, $other);
    $entry = $occurrence->recurringEntry;
    if ($posted) {
        app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    }
    $service = app(UpdateRecurringOccurrenceAmountService::class);
    foreach ([0.01, $amount] as $correctedAmount) {
        $service->update($occurrence, $entry->user, $correctedAmount);
    }
    $occurrence->refresh();
    expect($occurrence->shared_expense_my_share_cents)->toBe($expectedMine)
        ->and($occurrence->shared_expense_counterparty_share_cents)->toBe($expectedOther);
    $transaction = $posted
        ? $occurrence->convertedTransaction
        : app(RecurringEntryPostingService::class)->post($occurrence);
    expect((float) $transaction->amount)->toBe($amount)
        ->and($transaction->shared_expense_my_share_cents)->toBe($expectedMine)
        ->and($transaction->shared_expense_counterparty_share_cents)->toBe($expectedOther)
        ->and($transaction->shared_expense_my_share_cents + $transaction->shared_expense_counterparty_share_cents)->toBe((int) round($amount * 100))
        ->and((float) $transaction->account->fresh()->current_balance)->toBe(1000 - $amount)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($entry->user, $entry->sharedExpenseCounterparty))->toBe($expectedOther)
        ->and($entry->fresh()->shared_expense_my_share_cents)->toBe($mine)
        ->and($entry->fresh()->shared_expense_counterparty_share_cents)->toBe($other)
        ->and(Transaction::query()->count())->toBe(1);
})->with([
    'equal posted' => [400, 400, 10.0, 500, 500, true],
    'custom posted' => [200, 600, 10.0, 250, 750, true],
    'odd cents posted' => [400, 400, 10.01, 500, 501, true],
    'zero own share' => [0, 800, 10.0, 0, 1000, true],
    'full own share' => [800, 0, 10.0, 1000, 0, true],
    'decreasing posted' => [200, 600, 4.0, 100, 300, true],
    'equal pending' => [400, 400, 10.0, 500, 500, false],
    'custom pending' => [200, 600, 10.0, 250, 750, false],
]);

test('correcting a shared automatic transaction ignores a fixed old share and uses the plan percentage', function (int $mine, int $other, string $submittedMine, string $submittedOther, int $expectedMine, int $expectedOther) {
    $occurrence = sharedPaymentVerificationOccurrence($mine, $other);
    $entry = $occurrence->recurringEntry;
    $category = Category::create([
        'user_id' => $entry->user_id, 'name' => 'Subscription', 'slug' => 'subscription',
        'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true,
    ]);
    $entry->update(['category_id' => $category->id]);
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    $this->actingAs($entry->user)->patch(route('transactions.update', ['year' => 2026, 'month' => 10, 'transaction' => $transaction->uuid]), [
        'account_uuid' => $transaction->account->uuid, 'category_uuid' => $category->uuid,
        'type_key' => 'expense', 'transaction_day' => 9, 'amount' => 10, 'description' => 'Corrected', 'notes' => null,
        'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $entry->sharedExpenseCounterparty->uuid,
        'shared_expense_paid_by' => 'self', 'shared_expense_my_share' => $submittedMine,
        'shared_expense_counterparty_share' => $submittedOther,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $transaction->refresh();
    expect((float) $transaction->amount)->toBe(10.0)
        ->and($transaction->shared_expense_my_share_cents)->toBe($expectedMine)
        ->and($transaction->shared_expense_counterparty_share_cents)->toBe($expectedOther)
        ->and((float) $transaction->account->fresh()->current_balance)->toBe(990.0)
        ->and(app(SharedExpenseSettlementService::class)->balanceCents($entry->user, $entry->sharedExpenseCounterparty))->toBe($expectedOther)
        ->and($transaction->payment_verified_at)->toBeNull()
        ->and(Transaction::query()->count())->toBe(1);
})->with([
    'equal' => [400, 400, '6.00', '4.00', 500, 500],
    'custom' => [200, 600, '4.00', '6.00', 250, 750],
]);

test('correcting a variable automatic payment keeps one settled occurrence and its original transaction', function (float $amount, string $direction) {
    $occurrence = paymentVerificationOccurrence($direction);
    $entry = $occurrence->recurringEntry;
    $category = Category::create(['user_id' => $entry->user_id, 'name' => 'Variable bill', 'slug' => 'variable-bill', 'direction_type' => $direction, 'group_type' => $direction, 'is_active' => true, 'is_selectable' => true]);
    $entry->update(['is_amount_variable' => true, 'category_id' => $category->id]);
    $pipeline = app(RecurringEntryLifecycleService::class);
    $pipeline->runAutomationPipeline();
    $transaction = $occurrence->fresh()->convertedTransaction;
    $id = $transaction->id;
    $uuid = $transaction->uuid;
    $this->actingAs($entry->user)->patch(route('transactions.update', ['year' => 2026, 'month' => 10, 'transaction' => $uuid]), [
        'account_uuid' => $transaction->account->uuid, 'category_uuid' => $category->uuid,
        'type_key' => $direction, 'transaction_day' => 12, 'amount' => $amount, 'description' => 'Actual payment', 'notes' => null,
    ])->assertSessionHasNoErrors();
    $occurrence->refresh();
    expect($occurrence->status->value)->toBe('completed')
        ->and($occurrence->remainingPaymentCents())->toBe(0)
        ->and($occurrence->expected_amount)->toBe('15.00')
        ->and($occurrence->payment_actual_amount)->toBe(number_format($amount, 2, '.', ''))
        ->and($occurrence->paymentAllocations()->count())->toBe(0)
        ->and($transaction->fresh()->id)->toBe($id)
        ->and($transaction->fresh()->uuid)->toBe($uuid)
        ->and($transaction->fresh()->transaction_date->toDateString())->toBe('2026-10-12')
        ->and($occurrence->expected_date->toDateString())->toBe('2026-10-09');
    $correction = collect(app(ManageOccurrencePaymentService::class)->presentation($occurrence->fresh(), $entry->user)['history'])->firstWhere('action', 'correct');
    expect($correction['before']['actual_amount'])->toBe(15.0)
        ->and($correction['after']['actual_amount'])->toBe($amount)
        ->and($correction['after']['actual_date'])->toStartWith('2026-10-12');
    $pipeline->runAutomationPipeline();
    $pipeline->runAutomationPipeline();
    expect(app(RecurringEntryPostingService::class)->post($occurrence, $entry->user)->id)->toBe($id)
        ->and(Transaction::where('recurring_entry_occurrence_id', $occurrence->id)->count())->toBe(1)
        ->and((float) $transaction->account->fresh()->current_balance)->toBe($direction === 'income' ? 1000 + $amount : 1000 - $amount);
    $this->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()
        ->assertJsonPath('recurringEntry.occurrences.0.settlement_status', 'settled')
        ->assertJsonPath('recurringEntry.occurrences.0.can_convert', false)
        ->assertJsonPath('recurringEntry.occurrences.0.converted_transaction.uuid', $uuid)
        ->assertJsonPath('recurringEntry.occurrences.0.converted_transaction.amount', (int) $amount);
})->with([12.0, 23.0])->with(['expense', 'income']);

test('a variable amount and billing date can be confirmed before automatic generation', function () {
    $occurrence = paymentVerificationOccurrence();
    $entry = $occurrence->recurringEntry;
    $entry->update(['is_amount_variable' => true]);
    app(UpdateRecurringOccurrenceAmountService::class)->update($occurrence, $entry->user, 12);
    app(UpdateRecurringOccurrenceService::class)->update($occurrence, $entry->user, '2026-10-12', $entry->account->uuid);
    $pipeline = app(RecurringEntryLifecycleService::class);
    $pipeline->runAutomationPipeline();
    expect(Transaction::count())->toBe(0);
    $this->travelTo(now()->setDate(2026, 10, 12));
    $pipeline->runAutomationPipeline();
    $pipeline->runAutomationPipeline();
    $transaction = $occurrence->fresh()->convertedTransaction;
    expect(Transaction::count())->toBe(1)
        ->and($transaction->amount)->toBe('12.00')
        ->and($transaction->transaction_date->toDateString())->toBe('2026-10-12')
        ->and($occurrence->fresh()->expected_date->toDateString())->toBe('2026-10-09')
        ->and($occurrence->fresh()->due_date->toDateString())->toBe('2026-10-12')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and($occurrence->paymentAllocations()->count())->toBe(0)
        ->and($entry->account->fresh()->current_balance)->toBe('988.00');
});

test('a legacy variable residual cannot create a second transaction and can be corrected in place', function () {
    $occurrence = paymentVerificationOccurrence();
    $entry = $occurrence->recurringEntry;
    $entry->update(['is_amount_variable' => true]);
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, $entry->user);
    $transaction->update(['amount' => 12]);
    expect($occurrence->fresh()->status->value)->toBe('pending');
    $retried = app(RecurringEntryPostingService::class)->post($occurrence->fresh(), $entry->user);
    expect($retried->id)->toBe($transaction->id)->and(Transaction::count())->toBe(1);
    $this->actingAs($entry->user)->patch(route('recurring-entries.occurrences.amount.update', [$entry->uuid, $occurrence->uuid]), ['amount' => 12])->assertSessionHasNoErrors();
    expect($occurrence->fresh()->status->value)->toBe('completed')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($transaction->id)
        ->and(Transaction::count())->toBe(1);
});

test('explicit partial payments on a variable occurrence retain their allocation and residual', function () {
    $occurrence = paymentVerificationOccurrence(automatic: false);
    $entry = $occurrence->recurringEntry;
    $entry->update(['is_amount_variable' => true]);
    app(UpdateRecurringOccurrenceAmountService::class)->update($occurrence, $entry->user, 15);
    $transaction = Transaction::create(['user_id' => $entry->user_id, 'account_id' => $entry->account_id,
        'direction' => 'expense', 'amount' => 5, 'currency' => 'EUR', 'transaction_date' => '2026-10-09',
        'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $transaction, $entry->user, '5.00');
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect($occurrence->fresh()->expected_amount)->toBe('15.00')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(1000)
        ->and($occurrence->paymentAllocations()->sum('amount_cents'))->toBe(500)
        ->and(Transaction::count())->toBe(1);
    $this->actingAs($entry->user)->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()
        ->assertJsonPath('recurringEntry.occurrences.0.payments.0.classification', 'advance')
        ->assertJsonPath('recurringEntry.occurrences.0.settlement_status', 'partial');
});

test('a late variable payment records the actual amount once in the following month without an implied advance', function (bool $partial, float $amount) {
    $occurrence = paymentVerificationOccurrence(automatic: false);
    $entry = $occurrence->recurringEntry;
    $category = Category::create(['user_id' => $entry->user_id, 'name' => 'Variable expense', 'slug' => 'late-variable-expense', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $entry->update(['is_amount_variable' => true, 'category_id' => $category->id]);
    app(UpdateRecurringOccurrenceAmountService::class)->update($occurrence, $entry->user, 15);
    $other = $entry->occurrences()->create(['sequence_number' => 2, 'expected_date' => '2026-11-09', 'due_date' => '2026-11-09', 'expected_amount' => 15, 'status' => 'pending']);
    $this->travelTo(now()->setDate(2026, 11, 2));
    $route = route('transactions.store', ['year' => 2026, 'month' => 11]);
    $payload = ['account_uuid' => $entry->account->uuid, 'category_uuid' => $category->uuid, 'type_key' => 'expense', 'transaction_day' => 2,
        'amount' => $amount, 'description' => 'Late variable charge', 'payment_occurrence_uuid' => $occurrence->uuid, 'payment_is_partial' => $partial];
    $this->actingAs($entry->user)->post($route, $payload)->assertSessionHasNoErrors();
    $payment = Transaction::sole();
    expect($payment->transaction_date->toDateString())->toBe('2026-11-02')
        ->and($occurrence->fresh()->expected_date->toDateString())->toBe('2026-10-09')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe($partial ? 1500 - (int) round($amount * 100) : 0)
        ->and($occurrence->fresh()->status->value)->toBe($partial ? 'pending' : 'completed')
        ->and($occurrence->paymentAllocations()->count())->toBe($partial ? 1 : 0)
        ->and($other->fresh()->expected_amount)->toBe('15.00')
        ->and($entry->fresh()->expected_amount)->toBe('15.00')
        ->and($entry->account->fresh()->current_balance)->toBe(number_format(1000 - $amount, 2, '.', ''));
    $this->post($route, $payload)->assertSessionHasErrors();
    expect(Transaction::count())->toBe(1);
    if (! $partial) {
        expect($payment->recurring_entry_occurrence_id)->toBe($occurrence->id)
            ->and($occurrence->fresh()->converted_transaction_id)->toBe($payment->id);
        $this->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()
            ->assertJsonPath('recurringEntry.occurrences.0.payment_history.0.date', '2026-11-02')
            ->assertJsonPath('recurringEntry.occurrences.0.payment_history.0.status', 'confirmed')
            ->assertJsonPath('recurringEntry.occurrences.0.occurrence_url', route('recurring-entries.show', [$entry->uuid, 'highlight' => $occurrence->uuid]));
        $this->patch(route('transactions.update', ['year' => 2026, 'month' => 11, 'transaction' => $payment->uuid]), [
            'account_uuid' => $entry->account->uuid, 'category_uuid' => $category->uuid, 'type_key' => 'expense',
            'transaction_day' => 4, 'amount' => 11, 'description' => 'Corrected actual payment', 'notes' => null,
        ])->assertSessionHasNoErrors();
        expect(Transaction::count())->toBe(1)
            ->and($payment->fresh()->amount)->toBe('11.00')
            ->and($payment->fresh()->transaction_date->toDateString())->toBe('2026-11-04')
            ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
            ->and($entry->account->fresh()->current_balance)->toBe('989.00');
    }
})->with([[false, 12.0], [false, 23.0], [true, 5.0]]);

test('correcting an already refunded variable payment is rejected without altering cash or history', function () {
    $occurrence = paymentVerificationOccurrence();
    $entry = $occurrence->recurringEntry;
    $category = Category::create(['user_id' => $entry->user_id, 'name' => 'Protected expense', 'slug' => 'protected-expense', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $entry->update(['is_amount_variable' => true, 'category_id' => $category->id]);
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, $entry->user);
    $refund = app(TransactionRefundService::class)->refund($transaction, ['amount' => 3]);
    $snapshot = $transaction->fresh()->getAttributes();
    $occurrenceSnapshot = $occurrence->fresh()->getAttributes();
    $refundSnapshot = $refund->fresh()->getAttributes();
    $balance = $entry->account->fresh()->current_balance;
    $this->actingAs($entry->user)->patch(route('transactions.update', ['year' => 2026, 'month' => 10, 'transaction' => $transaction->uuid]), [
        'account_uuid' => $entry->account->uuid, 'category_uuid' => $category->uuid, 'type_key' => 'expense', 'transaction_day' => 12, 'amount' => 10, 'description' => 'Protected', 'notes' => null,
    ])->assertSessionHasErrors('transaction');
    expect($transaction->fresh()->getAttributes())->toBe($snapshot)
        ->and($occurrence->fresh()->getAttributes())->toBe($occurrenceSnapshot)
        ->and($refund->fresh()->getAttributes())->toBe($refundSnapshot)
        ->and($entry->account->fresh()->current_balance)->toBe($balance)
        ->and(Transaction::count())->toBe(2);
});

test('an early variable payment stays on its future occurrence and is not generated again when due', function () {
    $occurrence = paymentVerificationOccurrence();
    $entry = $occurrence->recurringEntry;
    $category = Category::create(['user_id' => $entry->user_id, 'name' => 'Early expense', 'slug' => 'early-expense', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $entry->update(['is_amount_variable' => true, 'category_id' => $category->id]);
    $occurrence->update(['expected_date' => '2026-11-09', 'due_date' => '2026-11-09']);
    $entry->update(['start_date' => '2026-11-09']);
    $this->actingAs($entry->user)->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()
        ->assertJsonPath('recurringEntry.occurrences.0.can_register_payment', true);
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [
        'account_uuid' => $entry->account->uuid, 'category_uuid' => $category->uuid, 'type_key' => 'expense', 'transaction_day' => 9,
        'amount' => 12, 'description' => 'Early actual payment', 'payment_occurrence_uuid' => $occurrence->uuid,
    ])->assertSessionHasNoErrors();
    $payment = Transaction::sole();
    $this->travelTo(now()->setDate(2026, 11, 9));
    $pipeline = app(RecurringEntryLifecycleService::class);
    $pipeline->runAutomationPipeline();
    $pipeline->runAutomationPipeline();
    expect(Transaction::count())->toBe(1)->and($occurrence->fresh()->converted_transaction_id)->toBe($payment->id)
        ->and($payment->transaction_date->toDateString())->toBe('2026-10-09')
        ->and($occurrence->fresh()->expected_date->toDateString())->toBe('2026-11-09')
        ->and($entry->account->fresh()->current_balance)->toBe('988.00');
});

test('moving an automatic charge to another period preserves its occurrence and balance', function (string $actualDate) {
    $occurrence = paymentVerificationOccurrence();
    UserYear::create(['user_id' => $occurrence->recurringEntry->user_id, 'year' => 2027, 'is_closed' => false]);
    $entry = $occurrence->recurringEntry;
    $category = Category::create(['user_id' => $entry->user_id, 'name' => 'Delayed charge', 'slug' => 'delayed-charge', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $entry->update(['is_amount_variable' => true, 'category_id' => $category->id]);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    $payment = $occurrence->fresh()->convertedTransaction;
    $this->travelTo(now()->setDate(2026, 11, 2));
    $this->actingAs($entry->user)->patch(route('transactions.update', ['year' => 2026, 'month' => 10, 'transaction' => $payment->uuid]), [
        'account_uuid' => $entry->account->uuid, 'category_uuid' => $category->uuid, 'type_key' => 'expense', 'selected_date' => $actualDate,
        'amount' => 12, 'description' => 'Actual debit', 'notes' => null,
    ])->assertSessionHasNoErrors();
    expect(Transaction::count())->toBe(1)
        ->and($payment->fresh()->transaction_date->toDateString())->toBe($actualDate)
        ->and($occurrence->fresh()->expected_date->toDateString())->toBe('2026-10-09')
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($payment->id)
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and($entry->account->fresh()->current_balance)->toBe('988.00');
    $json = $this->getJson(route('recurring-entries.show', $entry->uuid))->assertOk();
    $history = $json->json('recurringEntry.occurrences.0.payment_history');
    $this->get(route('recurring-entries.index', ['year' => 2026, 'month' => 10]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('monthlyCalendar.days.0.occurrences.0.payment_history', $history));
})->with(['2026-11-02', '2027-01-02']);

test('variable occurrences with historical refund and replacement payments cannot be rewritten as one payment', function () {
    $occurrence = paymentVerificationOccurrence();
    $entry = $occurrence->recurringEntry;
    $entry->update(['is_amount_variable' => true]);
    $original = app(RecurringEntryPostingService::class)->post($occurrence, $entry->user);
    $refund = app(TransactionRefundService::class)->refund($original, ['amount' => 3]);
    $replacement = app(RecurringEntryPostingService::class)->post($occurrence->fresh(), $entry->user, ['amount' => 3]);
    $snapshots = Transaction::orderBy('id')->get()->map->getAttributes()->all();
    $occurrenceSnapshot = $occurrence->fresh()->getAttributes();
    $balance = $entry->account->fresh()->current_balance;
    $this->actingAs($entry->user)->patch(route('recurring-entries.occurrences.amount.update', [$entry->uuid, $occurrence->uuid]), ['amount' => 4])->assertSessionHasErrors('amount');
    expect(Transaction::orderBy('id')->get()->map->getAttributes()->all())->toBe($snapshots)
        ->and($occurrence->fresh()->getAttributes())->toBe($occurrenceSnapshot)
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($replacement->id)
        ->and($refund->fresh()->refunded_transaction_id)->toBe($original->id)
        ->and($entry->account->fresh()->current_balance)->toBe($balance);
});

test('dashboard automatic confirmation records feedback once without touching accounting or plan fields', function () {
    $occurrence = paymentVerificationOccurrence();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    $beforeTransaction = collect($transaction->fresh()->getAttributes())->except(['payment_verified_at', 'payment_verification_method', 'updated_at'])->all();
    $beforeOccurrence = collect($occurrence->fresh()->getAttributes())->except(['payment_management_state', 'payment_resolved_at', 'payment_reminder_date', 'updated_at'])->all();
    $beforePlan = $occurrence->recurringEntry->fresh()->getAttributes();
    $beforeAccount = $transaction->account->fresh()->getAttributes();
    $this->mock(TransactionMutationService::class)->shouldNotReceive('recalculateAccount');
    $this->actingAs($occurrence->recurringEntry->user);
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->post(route('transactions.payment-confirmation', $transaction->uuid), ['amount' => 900, 'transaction_date' => '2026-11-04'])->assertSessionHasNoErrors();
    }
    expect(collect($transaction->fresh()->getAttributes())->except(['payment_verified_at', 'payment_verification_method', 'updated_at'])->all())->toBe($beforeTransaction)
        ->and(collect($occurrence->fresh()->getAttributes())->except(['payment_management_state', 'payment_resolved_at', 'payment_reminder_date', 'updated_at'])->all())->toBe($beforeOccurrence)
        ->and($occurrence->recurringEntry->fresh()->getAttributes())->toBe($beforePlan)
        ->and($transaction->account->fresh()->getAttributes())->toBe($beforeAccount)
        ->and($occurrence->paymentEvents()->count())->toBe(1)
        ->and(Transaction::count())->toBe(1);
});

test('a missing automatic transaction is an anomaly and dashboard confirmation cannot create it', function () {
    $occurrence = paymentVerificationOccurrence();
    $user = $occurrence->recurringEntry->user;
    $item = collect(app(DashboardService::class)->build($user, 2026, 10)['pending_actions']['items'])->firstWhere('occurrence_uuid', $occurrence->uuid);
    expect($item['automatic_payment_missing'])->toBeTrue()
        ->and($item['can_manage'])->toBeTrue()
        ->and($item['can_confirm_manual'])->toBeFalse()
        ->and($item['can_verify_payment'] ?? false)->toBeFalse();
    $this->actingAs($user)->post(route('recurring-entries.occurrences.convert', [$occurrence->recurringEntry->uuid, $occurrence->uuid]), [
        'return_to_dashboard' => true, 'confirm_payment' => true,
    ])->assertSessionHasErrors('payment');
    expect(Transaction::count())->toBe(0)->and($user->accounts()->first()->current_balance)->toBe('1000.00');
});

test('manual dashboard registration requires explicit payment confirmation and is idempotent', function () {
    $occurrence = paymentVerificationOccurrence(automatic: false);
    $user = $occurrence->recurringEntry->user;
    $item = collect(app(DashboardService::class)->build($user, 2026, 10)['pending_actions']['items'])->firstWhere('occurrence_uuid', $occurrence->uuid);
    expect($item['can_confirm_manual'])->toBeTrue()->and($item['recurrence_mode'])->toBe('manual');
    $url = route('recurring-entries.occurrences.convert', [$occurrence->recurringEntry->uuid, $occurrence->uuid]);
    $this->actingAs($user)->post($url, ['return_to_dashboard' => true])->assertSessionHasErrors('confirm_payment');
    expect(Transaction::count())->toBe(0);
    $this->from(route('dashboard'))->post($url, ['return_to_dashboard' => true, 'confirm_payment' => true])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
    $originalId = $occurrence->fresh()->converted_transaction_id;
    $this->post($url, ['return_to_dashboard' => true, 'confirm_payment' => true])->assertSessionHasNoErrors();
    expect(Transaction::count())->toBe(1)
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($originalId)
        ->and($occurrence->fresh()->convertedTransaction->recurring_entry_occurrence_id)->toBe($occurrence->id)
        ->and($occurrence->fresh()->convertedTransaction->payment_verification_started_at)->toBeNull()
        ->and($user->accounts()->first()->current_balance)->toBe('985.00');
});

test('future dashboard occurrences are forecasts without early payment actions', function (bool $automatic) {
    $occurrence = paymentVerificationOccurrence(automatic: $automatic);
    $occurrence->update(['due_date' => '2026-10-20']);
    $user = $occurrence->recurringEntry->user;
    $item = collect(app(DashboardService::class)->build($user, 2026, 10)['pending_actions']['items'])->firstWhere('occurrence_uuid', $occurrence->uuid);
    expect($item['is_future'])->toBeTrue()->and($item['can_manage'])->toBeFalse()
        ->and($item['can_confirm_manual'])->toBeFalse()->and($item['automatic_payment_missing'])->toBeFalse()
        ->and($item['plan_url'])->toContain($occurrence->recurringEntry->uuid);
    if (! $automatic) {
        $this->actingAs($user)->post(route('recurring-entries.occurrences.convert', [$occurrence->recurringEntry->uuid, $occurrence->uuid]), [
            'return_to_dashboard' => true, 'confirm_payment' => true, 'confirm_future_date' => true,
        ])->assertSessionHasErrors('payment');
        expect(Transaction::count())->toBe(0);
    } else {
        $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
        $this->actingAs($user)->post(route('transactions.payment-confirmation', $transaction->uuid))->assertSessionHasErrors('payment');
        expect($transaction->fresh()->payment_verified_at)->toBeNull()->and(Transaction::count())->toBe(1);
    }
})->with([true, false]);

test('rectified automatic income and expense are absent from ordinary lists and accounting counts but retained in occurrence history', function (string $direction) {
    $occurrence = paymentVerificationOccurrence($direction);
    $entry = $occurrence->recurringEntry;
    $user = $entry->user;
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    app(AutomaticPaymentVerificationService::class)->reportProblem($transaction, true);
    $zero = Transaction::create(['user_id' => $user->id, 'account_id' => $entry->account_id,
        'transaction_date' => '2026-10-09', 'amount' => 0, 'direction' => $direction, 'currency' => 'EUR',
        'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    app(TransactionMutationService::class)->recalculateAccount($transaction->account);
    $sheet = app(MonthlyTransactionSheetService::class)->build($user, 2026, 10);
    $navigation = app(TransactionNavigationService::class)->build($user, 2026, 10);
    expect(collect($sheet['transactions'])->pluck('uuid')->all())->toBe([$zero->uuid])
        ->and($sheet['meta']['transactions_count'])->toBe(1)
        ->and($sheet['totals']['actual_income_raw'])->toEqual(0)
        ->and($sheet['totals']['actual_expense_raw'])->toEqual(0)
        ->and($navigation['summary']['records_count'])->toBe(1)
        ->and($transaction->account->fresh()->current_balance)->toBe('1000.00')
        ->and($occurrence->fresh()->expected_amount)->toBe('15.00')
        ->and($occurrence->fresh()->payment_management_state)->toBe('pending')
        ->and(Transaction::withTrashed()->whereKey($transaction->id)->exists())->toBeTrue()
        ->and($transaction->fresh()->deleted_at)->toBeNull();
    $this->actingAs($user);
    $resource = (new RecurringEntryOccurrenceResource($occurrence->fresh()))->resolve(request());
    expect($resource['converted_transaction'])->toBeNull();
    expect(collect($resource['payment_history'])->firstWhere('uuid', $transaction->uuid))->toMatchArray(['amount' => 15.0, 'rectified' => true, 'status' => 'ignored']);
    $this->travelTo(now()->setDate(2026, 11, 2));
    $data = ['request_id' => (string) Str::uuid(), 'reason' => 'already_paid', 'action' => 'record', 'actual_date' => '2026-11-02', 'actual_amount' => 12, 'confirm_new_payment' => true];
    app(ManageOccurrencePaymentService::class)->manage($occurrence, $user, $data);
    app(ManageOccurrencePaymentService::class)->manage($occurrence, $user, $data);
    $actual = $occurrence->fresh()->convertedTransaction;
    expect($actual->transaction_date->toDateString())->toBe('2026-11-02')
        ->and($actual->amount)->toBe('12.00')
        ->and($actual->recurring_entry_occurrence_id)->toBe($occurrence->id)
        ->and(Transaction::notRectified()->where('recurring_entry_occurrence_id', $occurrence->id)->count())->toBe(1)
        ->and($actual->account->fresh()->current_balance)->toBe($direction === 'income' ? '1012.00' : '988.00');
})->with(['income', 'expense']);

test('occurrence timeline appends forecast and payment changes with event and effective dates kept separate', function () {
    $occurrence = paymentVerificationOccurrence();
    $entry = $occurrence->recurringEntry;
    $entry->update(['is_amount_variable' => true]);
    $user = $entry->user;
    $amounts = app(UpdateRecurringOccurrenceAmountService::class);
    $amounts->update($occurrence, $user, 18);
    $amounts->update($occurrence->fresh(), $user, 22);
    $amounts->update($occurrence->fresh(), $user, 22);
    $events = $occurrence->paymentEvents()->orderBy('id')->get()->map->getAttributes()->all();
    expect(count($events))->toBe(2);
    $service = app(ManageOccurrencePaymentService::class);
    $service->manage($occurrence, $user, ['request_id' => (string) Str::uuid(), 'reason' => 'postponed', 'action' => 'pending', 'estimated_date' => '2026-11-02', 'reminder_date' => '2026-10-11']);
    $this->travelTo(now()->setDate(2026, 11, 3));
    $service->manage($occurrence, $user, ['request_id' => (string) Str::uuid(), 'reason' => 'already_paid', 'action' => 'record', 'actual_date' => '2026-11-02', 'actual_amount' => 20, 'confirm_new_payment' => true]);
    $history = $service->presentation($occurrence->fresh(), $user)['history'];
    expect($occurrence->paymentEvents()->orderBy('id')->limit(2)->get()->map->getAttributes()->all())->toBe($events)
        ->and($history[0]['action'])->toBe('forecast')
        ->and($history[0]['before']['expected_amount'])->toBe(15.0)
        ->and($history[0]['after']['expected_amount'])->toBe(18.0)
        ->and($history[1]['after']['expected_amount'])->toBe(22.0)
        ->and($history[2]['after']['estimated_date'])->toStartWith('2026-11-02')
        ->and($history[2]['after']['reminder_date'])->toStartWith('2026-10-11');
    $recorded = collect($history)->firstWhere('action', 'record');
    expect($recorded['after']['actual_date'])->toStartWith('2026-11-02')
        ->and($recorded['after']['actual_amount'])->toBe(20.0)
        ->and($recorded['created_at']->toDateString())->toBe('2026-11-03');
});
