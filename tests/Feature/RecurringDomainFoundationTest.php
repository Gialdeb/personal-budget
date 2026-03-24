<?php

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('recurring domain schema exposes the new foundational columns', function () {
    expect(Schema::hasColumns('recurring_entries', [
        'entry_type',
        'status',
        'end_mode',
        'occurrences_limit',
        'total_amount',
        'installments_count',
        'next_occurrence_date',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('recurring_entry_occurrences', [
            'sequence_number',
            'matched_transaction_id',
            'converted_transaction_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('transactions', [
            'recurring_entry_occurrence_id',
            'refunded_transaction_id',
            'kind',
        ]))->toBeTrue();
});

test('recurring entry, occurrence and transaction casts use the new enums', function () {
    $user = User::factory()->create();
    $account = userAccount($user);

    $entry = RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'title' => 'Prestito auto',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => '75.50',
        'total_amount' => '906.00',
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'start_date' => '2026-01-10',
        'end_date' => '2026-12-10',
        'next_occurrence_date' => '2026-02-10',
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 12,
        'installments_count' => 12,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
    ]);

    $occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-02-10',
        'due_date' => '2026-02-12',
        'expected_amount' => '75.50',
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
    ]);

    $scheduledTransaction = transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'recurring_entry_occurrence_id' => $occurrence->id,
    ]);

    $refundBase = transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::MANUAL->value,
        'description' => 'Original payment',
    ]);

    $refundTransaction = transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::REFUND->value,
        'refunded_transaction_id' => $refundBase->id,
        'description' => 'Refund payment',
    ]);

    $occurrence->forceFill([
        'converted_transaction_id' => $scheduledTransaction->id,
    ])->save();

    expect($entry->fresh()->entry_type)->toBe(RecurringEntryTypeEnum::INSTALLMENT)
        ->and($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::ACTIVE)
        ->and($entry->fresh()->end_mode)->toBe(RecurringEndModeEnum::AFTER_OCCURRENCES)
        ->and($entry->fresh()->expected_amount)->toBe('75.50')
        ->and($entry->fresh()->total_amount)->toBe('906.00')
        ->and($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatusEnum::PENDING)
        ->and($occurrence->fresh()->sequence_number)->toBe(1)
        ->and($scheduledTransaction->fresh()->kind)->toBe(TransactionKindEnum::SCHEDULED)
        ->and($refundTransaction->fresh()->kind)->toBe(TransactionKindEnum::REFUND);
});

test('recurring domain relations are wired coherently', function () {
    $user = User::factory()->create();
    $account = userAccount($user);

    $entry = recurringEntryForDomain($user, $account);

    $occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-04-15',
        'due_date' => '2026-04-15',
        'expected_amount' => '29.90',
        'status' => RecurringOccurrenceStatusEnum::GENERATED->value,
    ]);

    $transaction = transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'recurring_entry_occurrence_id' => $occurrence->id,
    ]);

    $occurrence->forceFill([
        'converted_transaction_id' => $transaction->id,
    ])->save();

    $originalTransaction = transactionForRecurringDomain($user, $account, [
        'description' => 'Original recurring payment',
    ]);

    $refundTransaction = transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::REFUND->value,
        'refunded_transaction_id' => $originalTransaction->id,
        'description' => 'Recurring payment refund',
    ]);

    expect($entry->occurrences)->toHaveCount(1)
        ->and($occurrence->recurringEntry->is($entry))->toBeTrue()
        ->and($occurrence->convertedTransaction?->is($transaction))->toBeTrue()
        ->and($transaction->recurringOccurrence?->is($occurrence))->toBeTrue()
        ->and($refundTransaction->refundedTransaction?->is($originalTransaction))->toBeTrue()
        ->and($originalTransaction->refundTransaction?->is($refundTransaction))->toBeTrue();
});

test('occurrence sequence number is unique within a recurring entry', function () {
    $user = User::factory()->create();
    $account = userAccount($user);
    $entry = recurringEntryForDomain($user, $account);

    RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-05-01',
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
    ]);

    expect(fn () => RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-06-01',
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
    ]))->toThrow(QueryException::class);
});

test('a recurring occurrence can be linked to at most one real transaction', function () {
    $user = User::factory()->create();
    $account = userAccount($user);
    $entry = recurringEntryForDomain($user, $account);

    $occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-07-01',
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
    ]);

    transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'recurring_entry_occurrence_id' => $occurrence->id,
    ]);

    expect(fn () => transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'recurring_entry_occurrence_id' => $occurrence->id,
        'description' => 'Duplicate occurrence link',
    ]))->toThrow(QueryException::class);
});

test('a transaction can be refunded only once in v1', function () {
    $user = User::factory()->create();
    $account = userAccount($user);

    $originalTransaction = transactionForRecurringDomain($user, $account, [
        'description' => 'Original charge',
    ]);

    transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::REFUND->value,
        'refunded_transaction_id' => $originalTransaction->id,
        'description' => 'First refund',
    ]);

    expect(fn () => transactionForRecurringDomain($user, $account, [
        'kind' => TransactionKindEnum::REFUND->value,
        'refunded_transaction_id' => $originalTransaction->id,
        'description' => 'Second refund',
    ]))->toThrow(QueryException::class);
});

test('the migration backfills coherent defaults for legacy recurring data', function () {
    $migration = require base_path('database/migrations/2026_03_23_155854_evolve_recurring_domain_foundations.php');
    $migration->down();

    $user = User::factory()->create();
    $account = userAccount($user);

    DB::table('recurring_entries')->insert([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'scope_id' => null,
        'category_id' => null,
        'merchant_id' => null,
        'title' => 'Legacy entry',
        'description' => null,
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => '42.00',
        'currency' => 'EUR',
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'recurrence_rule' => null,
        'start_date' => '2026-01-15',
        'end_date' => '2026-03-15',
        'due_day' => 15,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        'notes' => null,
        'tracked_item_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $entryId = (int) DB::getPdo()->lastInsertId();

    DB::table('recurring_entry_occurrences')->insert([
        [
            'recurring_entry_id' => $entryId,
            'expected_date' => '2026-02-15',
            'due_date' => '2026-02-15',
            'expected_amount' => '42.00',
            'status' => 'planned',
            'matched_transaction_id' => null,
            'converted_transaction_id' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'recurring_entry_id' => $entryId,
            'expected_date' => '2026-03-15',
            'due_date' => '2026-03-15',
            'expected_amount' => '42.00',
            'status' => 'matched',
            'matched_transaction_id' => null,
            'converted_transaction_id' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $migration->up();

    $entry = RecurringEntry::query()->findOrFail($entryId);
    $occurrences = RecurringEntryOccurrence::query()
        ->where('recurring_entry_id', $entryId)
        ->orderBy('sequence_number')
        ->get();

    expect($entry->entry_type)->toBe(RecurringEntryTypeEnum::RECURRING)
        ->and($entry->status)->toBe(RecurringEntryStatusEnum::ACTIVE)
        ->and($entry->end_mode)->toBe(RecurringEndModeEnum::UNTIL_DATE)
        ->and($entry->next_occurrence_date?->toDateString())->toBe('2026-01-15')
        ->and($occurrences->pluck('sequence_number')->all())->toBe([1, 2])
        ->and($occurrences->pluck('status')->map(fn (RecurringOccurrenceStatusEnum $status) => $status->value)->all())
        ->toBe(['pending', 'generated']);
});

function recurringEntryForDomain(User $user, Account $account, array $attributes = []): RecurringEntry
{
    return RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'title' => 'Recurring domain entry',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => '29.90',
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'start_date' => '2026-04-15',
        'next_occurrence_date' => '2026-04-15',
        'end_mode' => RecurringEndModeEnum::NEVER->value,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        ...$attributes,
    ]);
}

function transactionForRecurringDomain(User $user, Account $account, array $attributes = []): Transaction
{
    return Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'value_date' => '2026-04-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => '29.90',
        'currency' => 'EUR',
        'description' => 'Recurring domain transaction',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        ...$attributes,
    ]);
}
