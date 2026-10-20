<?php

use App\Http\Resources\RecurringEntryOccurrenceResource;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringOccurrencePaymentAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\CreditDebts\DerivedObligationsService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\RecurringOccurrenceReadService;
use App\Services\Transactions\RecurringEntryLifecycleService;
use App\Support\Pwa\PwaManifestData;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 10, 9));
});

function partialPaymentOccurrence(string $direction = 'income', string $type = 'recurring'): RecurringEntryOccurrence
{
    $user = User::factory()->create();
    $accountType = AccountType::firstOrCreate(['code' => 'payment_account'], ['name' => 'Conto', 'balance_nature' => 'asset']);
    $account = Account::create(['user_id' => $user->id, 'account_type_id' => $accountType->id, 'name' => 'Conto', 'currency' => 'EUR', 'currency_code' => 'EUR', 'is_active' => true]);
    $entry = RecurringEntry::create(['user_id' => $user->id, 'account_id' => $account->id, 'title' => 'Affitto', 'direction' => $direction, 'currency' => 'EUR', 'entry_type' => $type, 'status' => 'active', 'is_active' => true, 'start_date' => '2026-10-08', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 1, 'installments_count' => 1, 'expected_amount' => 500, 'total_amount' => 500, 'is_amount_variable' => false, 'auto_create_transaction' => true]);

    return $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-08', 'due_date' => '2026-10-08', 'expected_amount' => 500, 'status' => 'pending']);
}

function partialPaymentTransaction(RecurringEntryOccurrence $occurrence, int $amount): Transaction
{
    $entry = $occurrence->recurringEntry;

    return Transaction::create(['user_id' => $entry->user_id, 'account_id' => $entry->account_id, 'direction' => $entry->direction, 'amount' => $amount, 'currency' => 'EUR', 'transaction_date' => '2026-10-09', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
}

test('partial payments expose only the residual and close at zero without new transactions', function (string $direction, string $type) {
    $occurrence = partialPaymentOccurrence($direction, $type);
    $user = $occurrence->recurringEntry->user;
    $service = app(AllocateOccurrencePaymentService::class);
    $first = partialPaymentTransaction($occurrence, 300);
    expect((new RecurringEntryOccurrenceResource($occurrence))->toArray(request())['settlement_status'])->toBe('due');
    $service->allocate($occurrence, $first, $user, '300.00');
    expect((new RecurringEntryOccurrenceResource($occurrence->fresh()))->toArray(request())['settlement_status'])->toBe('partial');
    $position = app(DerivedObligationsService::class)->positions($user)->first();
    expect($position['remaining_amount'])->toBe('200.00')->and($position['paid_amount'])->toBe('300.00')->and($position['status'])->toBe('partial')
        ->and($position['type'])->toBe($direction === 'income' ? 'credit' : 'debit');
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(1)->and($occurrence->fresh()->converted_transaction_id)->toBeNull();
    $second = partialPaymentTransaction($occurrence, 200);
    $service->allocate($occurrence, $second, $user, '200');
    $service->allocate($occurrence, $second, $user, '200');
    expect((new RecurringEntryOccurrenceResource($occurrence->fresh()))->toArray(request())['settlement_status'])->toBe('settled');
    expect($occurrence->remainingPaymentCents())->toBe(0)
        ->and(RecurringEntryOccurrence::openObligations()->whereKey($occurrence->id)->exists())->toBeFalse()
        ->and(app(DerivedObligationsService::class)->positions($user))->toHaveCount(0)
        ->and(Transaction::count())->toBe(2);
    expect($occurrence->paymentAllocations()->count())->toBe(2);
})->with([['income', 'recurring'], ['expense', 'recurring'], ['income', 'installment'], ['expense', 'installment']]);

test('a 450 payment leaves exactly 50 in the plan credit debts and dashboard arrears until settled', function (string $direction) {
    config(['features.credits_debts.enabled' => true]);
    $occurrence = partialPaymentOccurrence($direction);
    $occurrence->update(['expected_date' => '2026-09-08', 'due_date' => '2026-09-08']);
    $entry = $occurrence->recurringEntry;
    $user = $entry->user;
    $this->actingAs($user);
    $transaction = partialPaymentTransaction($occurrence, 450);
    $url = route('recurring-entries.occurrences.payments.store', [$entry->uuid, $occurrence->uuid]);
    $this->postJson($url, ['transaction_uuid' => $transaction->uuid, 'amount' => 450])->assertRedirect();
    $this->postJson($url, ['transaction_uuid' => $transaction->uuid, 'amount' => 450])->assertRedirect();
    expect($transaction->fresh()->amount)->toBe('450.00')
        ->and($occurrence->paymentAllocations()->sum('amount_cents'))->toBe(45000)
        ->and($occurrence->paymentAllocations()->count())->toBe(1);
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('recurringEntry.occurrences.0.expected_amount', 500)
        ->where('recurringEntry.occurrences.0.allocated_amount', 450)
        ->where('recurringEntry.occurrences.0.remaining_amount', 50)
        ->where('recurringEntry.occurrences.0.settlement_status', 'partial'));
    $summaryKey = $direction === 'income' ? 'credits' : 'debts';
    $this->get(route('credits-debts.index', ['year' => 2026, 'month' => 10]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('items', 1)->where('items.0.remaining_amount', '50.00')
        ->where('summary.'.$summaryKey.'_remaining_total', '50.00'));
    $this->get(route('dashboard', ['year' => 2026, 'month' => 10]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dashboard.credits_debts.'.$summaryKey.'_open_total_raw', fn ($value) => (float) $value === 50.0)
        ->has('dashboard.credits_debts.arrears', 1)
        ->where('dashboard.credits_debts.arrears.0.amount', '50.00'));
    $last = partialPaymentTransaction($occurrence, 50);
    $this->postJson($url, ['transaction_uuid' => $last->uuid, 'amount' => 50])->assertRedirect();
    expect($occurrence->remainingPaymentCents())->toBe(0)
        ->and((new RecurringEntryOccurrenceResource($occurrence->fresh()))->toArray(request())['settlement_status'])->toBe('settled')
        ->and(CreditDebtItem::count())->toBe(0);
    $this->get(route('credits-debts.index'))->assertInertia(fn (AssertableInertia $page) => $page->has('items', 0));
    $this->get(route('dashboard', ['year' => 2026, 'month' => 10]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dashboard.credits_debts.'.$summaryKey.'_open_total_raw', fn ($value) => (float) $value === 0.0)
        ->has('dashboard.credits_debts.arrears', 0));
})->with(['income', 'expense']);

test('allocation retries are idempotent and reject changed quotas or overpayments atomically', function () {
    $occurrence = partialPaymentOccurrence();
    $user = $occurrence->recurringEntry->user;
    $service = app(AllocateOccurrencePaymentService::class);
    $transaction = partialPaymentTransaction($occurrence, 300);
    $allocation = $service->allocate($occurrence, $transaction, $user, '300');
    expect($service->allocate($occurrence, $transaction, $user, '300')->id)->toBe($allocation->id);
    expect(fn () => $service->allocate($occurrence, $transaction, $user, '200'))->toThrow(ValidationException::class);
    $tooLarge = partialPaymentTransaction($occurrence, 300);
    expect(fn () => $service->allocate($occurrence, $tooLarge, $user, '201'))->toThrow(ValidationException::class);
    expect(RecurringOccurrencePaymentAllocation::count())->toBe(1)->and($occurrence->remainingPaymentCents())->toBe(20000);
    expect(fn () => $occurrence->update(['expected_amount' => 299]))->toThrow(ValidationException::class);
    expect($occurrence->fresh()->expected_amount)->toBe('500.00');
    expect(fn () => app(RecurringEntryPostingService::class)->post($occurrence))->toThrow(ValidationException::class);
});

test('a transaction can allocate distinct quotas to multiple occurrences but never beyond its amount', function () {
    $first = partialPaymentOccurrence();
    $entry = $first->recurringEntry;
    $second = $entry->occurrences()->create(['sequence_number' => 2, 'expected_date' => '2026-10-08', 'due_date' => '2026-10-08', 'expected_amount' => 500, 'status' => 'pending']);
    $transaction = partialPaymentTransaction($first, 300);
    $service = app(AllocateOccurrencePaymentService::class);
    $service->allocate($first, $transaction, $entry->user, '200');
    expect(fn () => $service->allocate($second, $transaction, $entry->user, '101'))->toThrow(ValidationException::class);
    $service->allocate($second, $transaction, $entry->user, '100');
    expect($first->remainingPaymentCents())->toBe(30000)->and($second->remainingPaymentCents())->toBe(40000)
        ->and($transaction->occurrencePaymentAllocations()->sum('amount_cents'))->toBe(30000);
});

test('allocation and automation block roll back together on an error', function () {
    $occurrence = partialPaymentOccurrence();
    $transaction = partialPaymentTransaction($occurrence, 300);
    Event::listen('eloquent.updating: '.RecurringEntryOccurrence::class, function (): void {
        throw new RuntimeException('Allocation failure');
    });
    try {
        expect(fn () => app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $transaction, $occurrence->recurringEntry->user, '300'))->toThrow(RuntimeException::class);
    } finally {
        Event::forget('eloquent.updating: '.RecurringEntryOccurrence::class);
    }
    expect(RecurringOccurrencePaymentAllocation::count())->toBe(0)->and($occurrence->fresh()->automatic_posting_blocked_at)->toBeNull();
});

test('ineligible occurrences and transactions reject allocations without changing records', function (string $invalid) {
    $occurrence = partialPaymentOccurrence();
    $transaction = partialPaymentTransaction($occurrence, 300);
    match ($invalid) {
        'future' => $occurrence->update(['due_date' => '2026-12-01']),
        'skipped' => $occurrence->update(['status' => 'skipped']),
        'variable' => $occurrence->recurringEntry->update(['is_amount_variable' => true]),
        'direction' => $transaction->update(['direction' => 'expense']),
        'currency' => $transaction->update(['currency' => 'USD']),
        'invalid' => $transaction->update(['status' => 'ignored']),
    };
    expect(fn () => app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $transaction, $occurrence->recurringEntry->user, '300'))->toThrow(ValidationException::class);
    expect(RecurringOccurrencePaymentAllocation::count())->toBe(0);
})->with(['future', 'skipped', 'variable', 'direction', 'currency', 'invalid']);

test('cent allocations close exactly and deletion reopens only the removed quota', function () {
    $occurrence = partialPaymentOccurrence();
    $occurrence->update(['expected_amount' => '500.01']);
    $first = partialPaymentTransaction($occurrence, 300);
    $second = partialPaymentTransaction($occurrence, 201);
    $service = app(AllocateOccurrencePaymentService::class);
    $user = $occurrence->recurringEntry->user;
    $service->allocate($occurrence, $first, $user, '300.00');
    $service->allocate($occurrence, $second, $user, '200.01');
    expect(RecurringEntryOccurrence::openObligations()->whereKey($occurrence->id)->exists())->toBeFalse();
    $first->delete();
    expect($occurrence->remainingPaymentCents())->toBe(30000)
        ->and(app(DerivedObligationsService::class)->positions($user)->first()['remaining_amount'])->toBe('300.00');
});

test('a replaced payment cannot be restored or revalidated beyond the residual', function (string $removal) {
    $occurrence = partialPaymentOccurrence();
    $user = $occurrence->recurringEntry->user;
    $service = app(AllocateOccurrencePaymentService::class);
    $first = partialPaymentTransaction($occurrence, 500);
    $service->allocate($occurrence, $first, $user, '500');
    if ($removal === 'deleted') {
        $first->delete();
    } else {
        $first->update(['status' => 'ignored']);
    }
    $replacement = partialPaymentTransaction($occurrence, 500);
    $service->allocate($occurrence, $replacement, $user, '500');
    expect(fn () => $removal === 'deleted' ? $first->restore() : $first->update(['status' => 'confirmed']))->toThrow(ValidationException::class);
    expect(fn () => $replacement->update(['amount' => 499]))->toThrow(ValidationException::class);
    expect($occurrence->allocatedPaymentCents())->toBe(50000)->and($occurrence->remainingPaymentCents())->toBe(0);
})->with(['deleted', 'invalidated']);

test('deleted or invalidated allocated transactions reopen the live obligation', function (string $removal) {
    $occurrence = partialPaymentOccurrence();
    $user = $occurrence->recurringEntry->user;
    $transaction = partialPaymentTransaction($occurrence, 500);
    app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $transaction, $user, '500');
    if ($removal === 'invalidated') {
        $transaction->update(['status' => 'ignored']);
    } elseif ($removal === 'force') {
        $transaction->forceDelete();
    } else {
        $transaction->delete();
    }
    expect($occurrence->remainingPaymentCents())->toBe(50000)
        ->and(app(DerivedObligationsService::class)->positions($user)->first()['remaining_amount'])->toBe('500.00');
})->with(['soft', 'force', 'invalidated']);

test('allocation endpoint enforces ownership and amount limits', function () {
    $occurrence = partialPaymentOccurrence();
    $user = $occurrence->recurringEntry->user;
    $transaction = partialPaymentTransaction($occurrence, 300);
    $url = route('recurring-entries.occurrences.payments.store', [$occurrence->recurringEntry->uuid, $occurrence->uuid]);
    $this->actingAs(User::factory()->create())->postJson($url, ['transaction_uuid' => $transaction->uuid, 'amount' => 300])->assertNotFound();
    $foreignTransaction = partialPaymentTransaction(partialPaymentOccurrence(), 300);
    $this->actingAs($user)->postJson($url, ['transaction_uuid' => $foreignTransaction->uuid, 'amount' => 300])->assertNotFound();
    $this->postJson($url, ['transaction_uuid' => $transaction->uuid, 'amount' => 301])->assertUnprocessable();
    $this->postJson($url, ['transaction_uuid' => $transaction->uuid, 'amount' => 300])->assertRedirect();
    $this->postJson($url, ['transaction_uuid' => $transaction->uuid, 'amount' => 300])->assertRedirect();
    expect($occurrence->paymentAllocations()->count())->toBe(1);
});

test('legacy manual pending and generated obligations survive new nullable lifecycle fields and month changes', function (string $status, string $direction) {
    config(['features.credits_debts.enabled' => true]);
    $occurrence = partialPaymentOccurrence($direction);
    $entry = $occurrence->recurringEntry;
    $entry->update(['auto_create_transaction' => false]);
    $occurrence->forceFill(['status' => $status, 'expected_date' => '2026-09-08', 'due_date' => null,
        'amount_confirmed_at' => null, 'automatic_posting_blocked_at' => null, 'payment_failed_at' => null,
        'converted_transaction_id' => null, 'matched_transaction_id' => null,
        'created_at' => '2026-03-01 12:00:00'])->save();
    expect($occurrence->paymentAllocations()->count())->toBe(0)->and(Transaction::count())->toBe(0);
    $this->actingAs($entry->user);
    expect($occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists())->toBeTrue();
    $this->get(route('credits-debts.index', ['year' => 2026, 'month' => 10]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('items', 1)->where('items.0.remaining_amount', '500.00')
        ->where('items.0.type', $direction === 'income' ? 'credit' : 'debit')
        ->where('summary.credits_remaining_total', $direction === 'income' ? '500.00' : '0.00')
        ->where('summary.debts_remaining_total', $direction === 'expense' ? '500.00' : '0.00'));
    $this->get(route('dashboard', ['year' => 2026, 'month' => 10]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('dashboard.credits_debts.arrears', 1)->where('dashboard.credits_debts.arrears.0.amount', '500.00'));
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('recurringEntry.occurrences.0.settlement_status', 'due')->where('recurringEntry.occurrences.0.can_register_payment', true));
})->with(['pending', 'generated'])->with(['income', 'expense']);

test('legacy closed occurrences never become open obligations or arrears', function (string $status, string $direction) {
    config(['features.credits_debts.enabled' => true]);
    $occurrence = partialPaymentOccurrence($direction);
    $entry = $occurrence->recurringEntry;
    $entry->update(['auto_create_transaction' => false]);
    $occurrence->forceFill(['status' => $status, 'expected_date' => '2026-09-08', 'due_date' => null,
        'amount_confirmed_at' => null, 'automatic_posting_blocked_at' => null, 'payment_failed_at' => null,
        'created_at' => '2026-03-01 12:00:00'])->save();
    if (in_array($status, ['completed', 'refunded'], true)) {
        $transaction = partialPaymentTransaction($occurrence, 500);
        $occurrence->update(['converted_transaction_id' => $transaction->id]);
        if ($status === 'refunded') {
            $refund = partialPaymentTransaction($occurrence, 500);
            $refund->forceFill(['refunded_transaction_id' => $transaction->id, 'kind' => 'refund',
                'direction' => $direction === 'income' ? 'expense' : 'income'])->save();
        }
    }
    $this->actingAs($entry->user);
    expect($occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists())->toBeFalse();
    $this->get(route('credits-debts.index', ['year' => 2026, 'month' => 10]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('items', 0)->where('summary.credits_remaining_total', '0.00')->where('summary.debts_remaining_total', '0.00'));
    $this->get(route('dashboard', ['year' => 2026, 'month' => 10]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('dashboard.credits_debts.arrears', 0));
    if ($status === 'cancelled') {
        $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('recurringEntry.occurrences', 0)->where('recurringEntry.occurrence_years.0.cancelled_count', 1));
    }
    $this->get(route('recurring-entries.show', ['recurringEntry' => $entry->uuid, 'show_cancelled' => 1]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('recurringEntry.occurrences.0.can_register_payment', false));
})->with(['skipped', 'cancelled', 'completed', 'refunded'])->with(['income', 'expense']);

test('manual occurrence payments use the normal transaction store and preserve advance balance history', function (string $direction) {
    config(['features.credits_debts.enabled' => true]);
    $occurrence = partialPaymentOccurrence($direction);
    $entry = $occurrence->recurringEntry;
    $entry->update(['auto_create_transaction' => false]);
    $occurrence->update(['expected_date' => '2026-09-08', 'due_date' => '2026-09-08']);
    $category = Category::create(['user_id' => $entry->user_id, 'name' => 'Pagamento', 'slug' => 'pagamento', 'direction_type' => $direction, 'group_type' => $direction, 'is_active' => true, 'is_selectable' => true]);
    $entry->account->update(['settings' => ['allow_negative_balance' => true]]);
    UserYear::create(['user_id' => $entry->user_id, 'year' => 2026, 'is_closed' => false]);
    $this->actingAs($entry->user);
    $this->travelTo(now()->setDate(2026, 10, 10));
    $payload = ['payment_occurrence_uuid' => $occurrence->uuid, 'transaction_day' => 10, 'type_key' => $direction,
        'account_uuid' => $entry->account->uuid, 'category_uuid' => $category->uuid, 'amount' => '450.00', 'description' => 'Pagamento affitto'];
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $response = $this->post($url, $payload)->assertSessionHasNoErrors();
    $response->assertRedirect(route('transactions.show', ['year' => 2026, 'month' => 10, 'highlight' => Transaction::latest('id')->firstOrFail()->uuid]));
    expect(Transaction::count())->toBe(1)->and($occurrence->remainingPaymentCents())->toBe(5000)
        ->and($occurrence->fresh()->expected_amount)->toBe('500.00');
    $balanceAfterAdvance = $entry->account->fresh()->current_balance;
    $this->post($url, $payload)->assertSessionHasErrors();
    $this->post($url, [...$payload, 'amount' => '51.00'])->assertSessionHasErrors();
    expect(Transaction::count())->toBe(1)->and($occurrence->paymentAllocations()->count())->toBe(1)
        ->and($entry->account->fresh()->current_balance)->toBe($balanceAfterAdvance);
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('recurringEntry.occurrences.0.settlement_status', 'partial')->where('recurringEntry.occurrences.0.can_register_payment', true)
        ->where('recurringEntry.summary.remaining_amount', 50));
    $this->get(route('recurring-entries.show', $entry->uuid), ['Accept' => 'text/html', 'X-Inertia' => 'true', 'X-Inertia-Version' => app(PwaManifestData::class)->version(), 'X-Inertia-Partial-Component' => 'transactions/recurring/Show', 'X-Inertia-Partial-Data' => 'transactionSheet'])
        ->assertSuccessful()->assertJsonPath('component', 'transactions/recurring/Show')->assertJsonStructure(['props' => ['transactionSheet' => ['editor' => ['accounts']]]]);
    $this->travelTo(now()->setDate(2026, 10, 18));
    $this->post($url, [...$payload, 'transaction_day' => 18, 'amount' => '50.00'])->assertSessionHasNoErrors();
    expect(Transaction::count())->toBe(2)->and($occurrence->remainingPaymentCents())->toBe(0)
        ->and($occurrence->paymentAllocations()->sum('amount_cents'))->toBe(50000)->and(CreditDebtItem::count())->toBe(0);
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('recurringEntry.occurrences.0.settlement_status', 'settled')->where('recurringEntry.occurrences.0.can_register_payment', false)
        ->has('recurringEntry.occurrences.0.payments', 2)
        ->where('recurringEntry.occurrences.0.payments.0.date', '2026-10-10')
        ->where('recurringEntry.occurrences.0.payments.0.classification', 'advance')
        ->where('recurringEntry.occurrences.0.payments.1.date', '2026-10-18')
        ->where('recurringEntry.occurrences.0.payments.1.classification', 'balance'));
    Transaction::where('amount', 50)->firstOrFail()->delete();
    expect($occurrence->remainingPaymentCents())->toBe(5000)
        ->and(app(DerivedObligationsService::class)->positions($entry->user)->first()['remaining_amount'])->toBe('50.00');
    $this->post($url, [...$payload, 'payment_occurrence_uuid' => partialPaymentOccurrence()->uuid, 'amount' => '50.00'])->assertSessionHasErrors('payment_occurrence_uuid');
})->with(['income', 'expense']);

test('refunded allocated payments no longer reduce the occurrence residual', function () {
    $occurrence = partialPaymentOccurrence();
    $transaction = partialPaymentTransaction($occurrence, 450);
    app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $transaction, $occurrence->recurringEntry->user, '450');
    $refund = partialPaymentTransaction($occurrence, 450);
    $refund->forceFill(['refunded_transaction_id' => $transaction->id, 'kind' => 'refund', 'direction' => 'expense'])->save();
    expect($occurrence->remainingPaymentCents())->toBe(50000)
        ->and((new RecurringEntryOccurrenceResource($occurrence->fresh()))->toArray(request())['payments'])->toBe([]);
});

test('batched rendering snapshots match fresh payment reads without influencing payment mutations', function () {
    $occurrence = partialPaymentOccurrence();
    $user = $occurrence->recurringEntry->user;
    $read = app(RecurringOccurrenceReadService::class);
    $read->prepare(collect([$occurrence]));
    expect($read->remaining($occurrence))->toBe(50000);
    $transaction = partialPaymentTransaction($occurrence, 300);
    app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $transaction, $user, '300');
    expect($occurrence->remainingPaymentCents())->toBe(20000);
    $read->prepare(collect([$occurrence]));
    expect($read->remaining($occurrence))->toBe($occurrence->remainingPaymentCents())
        ->and($read->allocated($occurrence))->toBe($occurrence->allocatedPaymentCents())
        ->and($read->count($occurrence))->toBe(1);
    $another = partialPaymentTransaction($occurrence, 200);
    app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $another, $user, '200');
    $read->prepare(collect([$occurrence]));
    expect($read->remaining($occurrence))->toBe(0)->and($read->count($occurrence))->toBe(2);
});

test('late allocated payment retains both navigation links across different months', function () {
    $occurrence = partialPaymentOccurrence('expense');
    $entry = $occurrence->recurringEntry;
    $entry->update(['auto_create_transaction' => false]);
    $occurrence->update(['expected_date' => '2026-09-08', 'due_date' => '2026-09-08']);
    $payment = partialPaymentTransaction($occurrence, 500);
    app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $payment, $entry->user, '500');
    $this->actingAs($entry->user);
    $sheet = app(MonthlyTransactionSheetService::class)->build($entry->user, 2026, 10);
    $row = collect($sheet['transactions'])->firstWhere('uuid', $payment->uuid);
    expect($row['is_recurring_transaction'])->toBeTrue()
        ->and($row['recurring_occurrence_uuid'])->toBe($occurrence->uuid)
        ->and($row['recurring_entry_show_url'])->toContain($entry->uuid, 'highlight='.$occurrence->uuid);
    $this->getJson(route('recurring-entries.show', [$entry->uuid, 'highlight' => $occurrence->uuid]))->assertOk()
        ->assertJsonPath('recurringEntry.occurrences.0.settlement_status', 'settled')
        ->assertJsonPath('recurringEntry.occurrences.0.payments.0.uuid', $payment->uuid)
        ->assertJsonPath('recurringEntry.occurrences.0.payments.0.date', '2026-10-09');
});

test('existing payment can be explicitly relinked without changing cash or creating transactions', function () {
    $occurrence = partialPaymentOccurrence('expense');
    $entry = $occurrence->recurringEntry;
    $payment = partialPaymentTransaction($occurrence, 500);
    $this->actingAs($entry->user);
    $balance = $entry->account->fresh()->current_balance;
    $candidatesUrl = route('recurring-entries.occurrences.payment-candidates', [$entry->uuid, $occurrence->uuid]);
    $this->getJson($candidatesUrl.'?date_from=2026-10-01&date_to=2026-10-31')->assertOk()->assertJsonPath('transactions.0.uuid', $payment->uuid);
    $this->getJson($candidatesUrl.'?date_from=2026-11-01')->assertOk()->assertJsonCount(0, 'transactions');
    $route = route('recurring-entries.occurrences.payments.store', [$entry->uuid, $occurrence->uuid]);
    $payload = ['transaction_uuid' => $payment->uuid, 'amount' => '500.00'];
    $this->post($route, $payload)->assertSessionHasNoErrors()->assertRedirect(route('recurring-entries.show', [$entry->uuid, 'highlight' => $occurrence->uuid]));
    $this->post($route, $payload)->assertSessionHasNoErrors();
    expect(Transaction::count())->toBe(1)->and($occurrence->paymentAllocations()->count())->toBe(1)
        ->and($entry->account->fresh()->current_balance)->toBe($balance)->and($occurrence->remainingPaymentCents())->toBe(0);
    $this->getJson($candidatesUrl)->assertOk()->assertJsonCount(0, 'transactions');
    $this->actingAs(User::factory()->create())->getJson($candidatesUrl)->assertNotFound();
    $this->post($route, $payload)->assertNotFound();
});

test('legacy matched relation remains visible without an inverse transaction foreign key', function () {
    $occurrence = partialPaymentOccurrence('expense');
    $entry = $occurrence->recurringEntry;
    $payment = partialPaymentTransaction($occurrence, 500);
    $occurrence->update(['matched_transaction_id' => $payment->id, 'status' => 'completed']);
    $this->actingAs($entry->user);
    $sheet = app(MonthlyTransactionSheetService::class)->build($entry->user, 2026, 10);
    $row = collect($sheet['transactions'])->firstWhere('uuid', $payment->uuid);
    expect($row['is_recurring_transaction'])->toBeTrue()->and($row['recurring_occurrence_uuid'])->toBe($occurrence->uuid);
    $this->getJson(route('recurring-entries.show', [$entry->uuid, 'highlight' => $occurrence->uuid]))->assertOk()
        ->assertJsonPath('recurringEntry.occurrences.0.payment_history.0.uuid', $payment->uuid);
});

test('manual and automatic posting keep the source scadence when payment date changes', function (bool $automatic) {
    $occurrence = partialPaymentOccurrence('expense');
    $entry = $occurrence->recurringEntry;
    $occurrence->update(['expected_date' => '2026-09-08', 'due_date' => '2026-09-08']);
    $entry->account->update(['settings' => ['allow_negative_balance' => true]]);
    UserYear::create(['user_id' => $entry->user_id, 'year' => 2026, 'is_closed' => false]);
    $payment = app(RecurringEntryPostingService::class)->post($occurrence, $entry->user, ['transaction_date' => '2026-10-09', 'automatic_posting' => $automatic]);
    $this->actingAs($entry->user);
    $sheet = app(MonthlyTransactionSheetService::class)->build($entry->user, 2026, 10);
    $row = collect($sheet['transactions'])->firstWhere('uuid', $payment->uuid);
    expect($row['is_recurring_transaction'])->toBeTrue()->and($row['recurring_occurrence_uuid'])->toBe($occurrence->uuid)
        ->and($payment->recurring_entry_occurrence_id)->toBe($occurrence->id)
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($payment->id);
    $this->getJson(route('recurring-entries.show', [$entry->uuid, 'highlight' => $occurrence->uuid]))->assertOk()
        ->assertJsonPath('recurringEntry.occurrences.0.converted_transaction.uuid', $payment->uuid)
        ->assertJsonPath('recurringEntry.occurrences.0.converted_transaction.transaction_date', '2026-10-09');
})->with([false, true]);

test('one transaction allocated to several occurrences exposes every canonical link', function () {
    $first = partialPaymentOccurrence('expense');
    $entry = $first->recurringEntry;
    $second = $entry->occurrences()->create(['sequence_number' => 2, 'expected_date' => '2026-10-09', 'expected_amount' => 500, 'status' => 'pending']);
    $payment = partialPaymentTransaction($first, 500);
    $service = app(AllocateOccurrencePaymentService::class);
    $service->allocate($first, $payment, $entry->user, '300');
    $service->allocate($second, $payment, $entry->user, '200');
    $this->actingAs($entry->user);
    $sheet = app(MonthlyTransactionSheetService::class)->build($entry->user, 2026, 10);
    $row = collect($sheet['transactions'])->firstWhere('uuid', $payment->uuid);
    expect(array_column($row['recurring_payment_links'], 'occurrence_uuid'))->toBe([$first->uuid, $second->uuid]);
    expect($first->remainingPaymentCents())->toBe(20000)->and($second->remainingPaymentCents())->toBe(30000);
});

test('linking an existing payment rejects incompatible amounts and foreign transactions atomically', function () {
    $occurrence = partialPaymentOccurrence('expense');
    $entry = $occurrence->recurringEntry;
    $payment = partialPaymentTransaction($occurrence, 100);
    $route = route('recurring-entries.occurrences.payments.store', [$entry->uuid, $occurrence->uuid]);
    $this->actingAs($entry->user)->post($route, ['transaction_uuid' => $payment->uuid, 'amount' => '101.00'])->assertSessionHasErrors('amount');
    expect($occurrence->paymentAllocations()->count())->toBe(0)->and($occurrence->remainingPaymentCents())->toBe(50000);
    $payment->update(['direction' => 'income']);
    $this->post($route, ['transaction_uuid' => $payment->uuid, 'amount' => '100.00'])->assertSessionHasErrors('transaction_uuid');
    $other = partialPaymentOccurrence('expense');
    $foreign = partialPaymentTransaction($other, 100);
    $this->post($route, ['transaction_uuid' => $foreign->uuid, 'amount' => '100.00'])->assertNotFound();
    expect($occurrence->paymentAllocations()->count())->toBe(0);
});
