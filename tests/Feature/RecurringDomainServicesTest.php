<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Recurring\InstallmentAmountAllocatorService;
use App\Services\Recurring\RecurringEntryLifecycleService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\RecurringEntryValidatorService;
use App\Services\Recurring\TransactionRefundService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

test('validator accepts a valid recurring plan', function () {
    $context = recurringDomainContext();
    $validator = app(RecurringEntryValidatorService::class);

    $validated = $validator->validate($context['user'], recurringPayload($context, [
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'expected_amount' => 49.9,
        'end_mode' => RecurringEndModeEnum::UNTIL_DATE->value,
        'end_date' => '2026-06-15',
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 15],
    ]));

    expect($validated['entry_type'])->toBe(RecurringEntryTypeEnum::RECURRING->value)
        ->and($validated['expected_amount'])->toBe(49.9)
        ->and($validated['end_mode'])->toBe(RecurringEndModeEnum::UNTIL_DATE->value)
        ->and($validated['next_occurrence_date'])->toBe('2026-01-15');
});

test('validator accepts a valid installment plan and normalizes installment fields', function () {
    $context = recurringDomainContext();
    $validator = app(RecurringEntryValidatorService::class);

    $validated = $validator->validate($context['user'], recurringPayload($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'expected_amount' => 999,
        'total_amount' => 1000,
        'installments_count' => 3,
        'end_mode' => RecurringEndModeEnum::NEVER->value,
    ]));

    expect($validated['entry_type'])->toBe(RecurringEntryTypeEnum::INSTALLMENT->value)
        ->and($validated['expected_amount'])->toBeNull()
        ->and($validated['end_mode'])->toBe(RecurringEndModeEnum::AFTER_OCCURRENCES->value)
        ->and($validated['occurrences_limit'])->toBe(3);
});

test('validator rejects recurring plans with installment fields', function () {
    $context = recurringDomainContext();

    expect(fn () => app(RecurringEntryValidatorService::class)->validate(
        $context['user'],
        recurringPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'expected_amount' => 49.9,
            'total_amount' => 1000,
            'installments_count' => 3,
        ])
    ))->toThrow(ValidationException::class);
});

test('validator rejects installment plans without total amount', function () {
    $context = recurringDomainContext();

    expect(fn () => app(RecurringEntryValidatorService::class)->validate(
        $context['user'],
        recurringPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'total_amount' => null,
            'installments_count' => 3,
        ])
    ))->toThrow(ValidationException::class);
});

test('validator rejects installment plans without installments count', function () {
    $context = recurringDomainContext();

    expect(fn () => app(RecurringEntryValidatorService::class)->validate(
        $context['user'],
        recurringPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'total_amount' => 1000,
            'installments_count' => null,
        ])
    ))->toThrow(ValidationException::class);
});

test('validator rejects after occurrences without a limit', function () {
    $context = recurringDomainContext();

    expect(fn () => app(RecurringEntryValidatorService::class)->validate(
        $context['user'],
        recurringPayload($context, [
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => null,
        ])
    ))->toThrow(ValidationException::class);
});

test('validator rejects until date without end date', function () {
    $context = recurringDomainContext();

    expect(fn () => app(RecurringEntryValidatorService::class)->validate(
        $context['user'],
        recurringPayload($context, [
            'end_mode' => RecurringEndModeEnum::UNTIL_DATE->value,
            'end_date' => null,
        ])
    ))->toThrow(ValidationException::class);
});

test('validator rejects end dates before the start date', function () {
    $context = recurringDomainContext();

    expect(fn () => app(RecurringEntryValidatorService::class)->validate(
        $context['user'],
        recurringPayload($context, [
            'end_mode' => RecurringEndModeEnum::UNTIL_DATE->value,
            'start_date' => '2026-02-01',
            'end_date' => '2026-01-31',
        ])
    ))->toThrow(ValidationException::class);
});

test('allocator handles exact installment division', function () {
    $amounts = app(InstallmentAmountAllocatorService::class)->allocate(900, 3);

    expect($amounts)->toBe(['300.00', '300.00', '300.00']);
});

test('allocator handles rounded installment division with the last installment absorbing the remainder', function () {
    $amounts = app(InstallmentAmountAllocatorService::class)->allocate(1000, 3);

    expect($amounts)->toBe(['333.33', '333.33', '333.34'])
        ->and(array_sum(array_map('floatval', $amounts)))->toBe(1000.0);
});

test('generator creates daily recurring occurrences', function () {
    $entry = makeRecurringEntry(recurringDomainContext(), [
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::DAILY->value,
        'recurrence_interval' => 2,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 3,
        'start_date' => '2026-01-01',
    ]);

    $created = app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry);

    expect($created->pluck('expected_date')->map->toDateString()->all())
        ->toBe(['2026-01-01', '2026-01-03', '2026-01-05']);
});

test('generator creates weekly recurring occurrences using weekdays', function () {
    $entry = makeRecurringEntry(recurringDomainContext(), [
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::WEEKLY->value,
        'recurrence_rule' => ['weekdays' => ['mon', 'wed']],
        'start_date' => '2026-01-05',
    ]);

    $created = app(RecurringEntryOccurrenceGeneratorService::class)->generate(
        $entry,
        CarbonImmutable::parse('2026-01-14')
    );

    expect($created->pluck('expected_date')->map->toDateString()->all())
        ->toBe(['2026-01-05', '2026-01-07', '2026-01-12', '2026-01-14']);
});

test('generator creates monthly day of month recurring occurrences', function () {
    $entry = makeRecurringEntry(recurringDomainContext(), [
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'start_date' => '2026-01-31',
        'due_day' => 31,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 31],
        'end_mode' => RecurringEndModeEnum::UNTIL_DATE->value,
        'end_date' => '2026-03-31',
    ]);

    $created = app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry);

    expect($created->pluck('expected_date')->map->toDateString()->all())
        ->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

test('generator creates monthly ordinal weekday recurring occurrences', function () {
    $entry = makeRecurringEntry(recurringDomainContext(), [
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'start_date' => '2026-01-01',
        'recurrence_rule' => ['mode' => 'ordinal_weekday', 'ordinal' => 'second', 'weekday' => 'fri'],
        'end_mode' => RecurringEndModeEnum::UNTIL_DATE->value,
        'end_date' => '2026-03-31',
    ]);

    $created = app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry);

    expect($created->pluck('expected_date')->map->toDateString()->all())
        ->toBe(['2026-01-09', '2026-02-13', '2026-03-13']);
});

test('generator creates installment occurrences with allocated amounts and correct sequence numbers', function () {
    $entry = makeRecurringEntry(recurringDomainContext(), [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 1000,
        'installments_count' => 3,
        'start_date' => '2026-01-10',
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 10],
    ]);

    $created = app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry);

    expect($created->pluck('sequence_number')->all())->toBe([1, 2, 3])
        ->and($created->pluck('expected_amount')->all())->toBe(['333.33', '333.33', '333.34'])
        ->and($entry->fresh()->next_occurrence_date)->toBeNull();
});

test('generator is idempotent and respects occurrence limits', function () {
    $entry = makeRecurringEntry(recurringDomainContext(), [
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::DAILY->value,
        'start_date' => '2026-01-01',
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);

    $generator = app(RecurringEntryOccurrenceGeneratorService::class);

    $firstPass = $generator->generate($entry);
    $secondPass = $generator->generate($entry);

    expect($firstPass)->toHaveCount(2)
        ->and($secondPass)->toHaveCount(0)
        ->and($entry->fresh()->occurrences)->toHaveCount(2)
        ->and($entry->fresh()->next_occurrence_date)->toBeNull();
});

test('lifecycle respects auto create transaction flag', function () {
    $context = recurringDomainContext();
    $autoEntry = makeRecurringEntry($context, [
        'start_date' => '2026-01-01',
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::DAILY->value,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
        'auto_create_transaction' => true,
    ]);
    $manualEntry = makeRecurringEntry($context, [
        'title' => 'Manual recurring entry',
        'start_date' => '2026-01-01',
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::DAILY->value,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
        'auto_create_transaction' => false,
    ]);

    $lifecycle = app(RecurringEntryLifecycleService::class);
    $autoResult = $lifecycle->synchronize($autoEntry, CarbonImmutable::parse('2026-01-10'));
    $manualResult = $lifecycle->synchronize($manualEntry, CarbonImmutable::parse('2026-01-10'));

    expect($autoResult['posted_transactions'])->toHaveCount(2)
        ->and($manualResult['posted_transactions'])->toHaveCount(0)
        ->and($autoEntry->fresh()->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(2)
        ->and($manualEntry->fresh()->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(0);
});

test('posting converts an occurrence into a scheduled transaction without duplicates', function () {
    $context = recurringDomainContext();
    $entry = makeRecurringEntry($context);
    $occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-01-15',
        'due_date' => '2026-01-17',
        'expected_amount' => '49.90',
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
    ]);

    $posting = app(RecurringEntryPostingService::class);

    $transaction = $posting->post($occurrence);
    $sameTransaction = $posting->post($occurrence->fresh());

    expect($transaction->kind)->toBe(TransactionKindEnum::SCHEDULED)
        ->and($transaction->recurring_entry_occurrence_id)->toBe($occurrence->id)
        ->and($transaction->transaction_date?->toDateString())->toBe('2026-01-17')
        ->and($transaction->account_id)->toBe($entry->account_id)
        ->and($transaction->category_id)->toBe($entry->category_id)
        ->and($transaction->currency)->toBe($entry->currency)
        ->and($transaction->description)->toBe($entry->title)
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($transaction->id)
        ->and($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatusEnum::COMPLETED)
        ->and($sameTransaction->id)->toBe($transaction->id)
        ->and(Transaction::query()->where('recurring_entry_occurrence_id', $occurrence->id)->count())->toBe(1);
});

test('refund service creates a correct one to one refund for a scheduled transaction', function () {
    $context = recurringDomainContext();
    $entry = makeRecurringEntry($context);
    $occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-01-15',
        'expected_amount' => '49.90',
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
    ]);
    $scheduledTransaction = app(RecurringEntryPostingService::class)->post($occurrence);

    $refund = app(TransactionRefundService::class)->refund($scheduledTransaction, [
        'transaction_date' => '2026-01-20',
    ]);

    expect($refund->kind)->toBe(TransactionKindEnum::REFUND)
        ->and($refund->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and($refund->amount)->toBe($scheduledTransaction->amount)
        ->and($refund->account_id)->toBe($scheduledTransaction->account_id)
        ->and($refund->currency)->toBe($scheduledTransaction->currency)
        ->and($refund->refunded_transaction_id)->toBe($scheduledTransaction->id)
        ->and($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatusEnum::REFUNDED)
        ->and($scheduledTransaction->fresh()->recurringOccurrence?->is($occurrence))->toBeTrue();
});

test('refund service forbids double refund refund on refund and refund on opening balance', function () {
    $context = recurringDomainContext();
    $transaction = scheduledTransactionForTests($context);
    $refundService = app(TransactionRefundService::class);

    $refund = $refundService->refund($transaction);

    expect(fn () => $refundService->refund($transaction))->toThrow(ValidationException::class)
        ->and(fn () => $refundService->refund($refund))->toThrow(ValidationException::class);

    $openingBalance = Transaction::query()->create([
        'user_id' => $context['user']->id,
        'account_id' => $context['account']->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => '1000.00',
        'currency' => 'EUR',
        'description' => 'Opening',
        'source_type' => 'manual',
        'status' => 'confirmed',
    ]);

    expect(fn () => $refundService->refund($openingBalance))->toThrow(ValidationException::class);
});

function recurringDomainContext(): array
{
    $user = User::factory()->create();
    $account = userAccount($user, [
        'opening_balance' => '1000.00',
        'current_balance' => '1000.00',
    ]);
    $scope = Scope::query()->create([
        'user_id' => $user->id,
        'name' => 'Famiglia',
        'type' => 'household',
        'color' => '#000000',
        'is_active' => true,
    ]);
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Affitti',
        'slug' => 'affitti-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $merchant = Merchant::query()->create([
        'user_id' => $user->id,
        'name' => 'Locatore',
        'normalized_name' => 'locatore',
        'is_active' => true,
    ]);
    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Casa',
        'slug' => 'casa-'.fake()->unique()->slug(),
        'type' => 'asset',
        'is_active' => true,
    ]);

    return compact('user', 'account', 'scope', 'category', 'merchant', 'trackedItem');
}

function recurringPayload(array $context, array $overrides = []): array
{
    return [
        'account_id' => $context['account']->id,
        'scope_id' => $context['scope']->id,
        'category_id' => $context['category']->id,
        'merchant_id' => $context['merchant']->id,
        'tracked_item_id' => $context['trackedItem']->id,
        'title' => 'Rent recurring entry',
        'description' => 'Monthly rent payment',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 49.9,
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 15],
        'start_date' => '2026-01-15',
        'end_mode' => RecurringEndModeEnum::NEVER->value,
        'due_day' => 15,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        'notes' => 'Recurring notes',
        ...$overrides,
    ];
}

function makeRecurringEntry(array $context, array $overrides = []): RecurringEntry
{
    $validated = app(RecurringEntryValidatorService::class)->validate(
        $context['user'],
        recurringPayload($context, $overrides)
    );

    return RecurringEntry::query()->create($validated);
}

function scheduledTransactionForTests(array $context): Transaction
{
    $entry = makeRecurringEntry($context);
    $occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-01-15',
        'expected_amount' => '49.90',
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
    ]);

    return app(RecurringEntryPostingService::class)->post($occurrence);
}

beforeEach(function () {
    AccountType::query()->firstOrCreate([
        'code' => 'payment_account',
    ], [
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);
});
