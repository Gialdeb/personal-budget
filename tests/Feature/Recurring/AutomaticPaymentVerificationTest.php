<?php

use App\Http\Resources\RecurringEntryOccurrenceResource;
use App\Jobs\Automation\RunRecurringPipelineJob;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Dashboard\DashboardService;
use App\Services\Recurring\AutomaticPaymentVerificationService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use App\Services\Transactions\RecurringEntryLifecycleService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

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

test('verification expires exactly after seven days without accounting movements', function () {
    $transaction = app(RecurringEntryPostingService::class)->post(paymentVerificationOccurrence(), metadata: ['automatic_posting' => true]);
    $verification = app(AutomaticPaymentVerificationService::class);
    $this->travel(7)->days();
    $this->travel(-1)->seconds();
    expect($verification->confirmExpired())->toBe(0);
    $this->travel(1)->seconds();
    expect($verification->confirmExpired())->toBe(1)
        ->and($verification->confirmExpired())->toBe(0)
        ->and($transaction->fresh()->payment_verification_method)->toBe('automatic')
        ->and(Transaction::count())->toBe(1)
        ->and((float) $transaction->account->fresh()->current_balance)->toBe(985.0);
});

test('payment problem before or after verification removes registration and blocks automatic retries', function (bool $expired) {
    $occurrence = paymentVerificationOccurrence();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    if ($expired) {
        $this->travel(8)->days();
        app(AutomaticPaymentVerificationService::class)->confirmExpired();
    }
    $this->actingAs($occurrence->recurringEntry->user)->post(route('transactions.payment-problem', $transaction->uuid))->assertRedirect();
    expect(Transaction::withTrashed()->find($transaction->id))->toBeNull()
        ->and($occurrence->fresh()->converted_transaction_id)->toBeNull()
        ->and($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->automatic_posting_blocked_at)->not->toBeNull()
        ->and($occurrence->fresh()->payment_failed_at)->not->toBeNull()
        ->and((float) $occurrence->recurringEntry->account->fresh()->current_balance)->toBe(1000.0);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(0);
    app(App\Services\Recurring\RecurringEntryLifecycleService::class)->synchronize($occurrence->recurringEntry);
    expect(Transaction::count())->toBe(0);
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
    $this->post(route('transactions.payment-problem', $transaction->uuid))->assertForbidden();
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
    expect(fn () => app(AutomaticPaymentVerificationService::class)->reportProblem($transaction))->toThrow(RuntimeException::class);
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
    app(AutomaticPaymentVerificationService::class)->reportProblem($transaction);
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
    app(AutomaticPaymentVerificationService::class)->confirm($transaction->fresh());
    app(AutomaticPaymentVerificationService::class)->reportProblem($transaction->fresh());
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(0)->and($occurrence->fresh()->automatic_posting_blocked_at)->not->toBeNull();
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
