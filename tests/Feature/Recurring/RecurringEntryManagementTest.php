<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\MembershipSourceEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ReminderDelivery;
use App\Models\Scope;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Accounts\AccountOpeningBalanceService;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\RecurringEntryLifecycleService;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\Recurring\UndoRecurringOccurrenceConversionService;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use App\Services\SharedExpenses\SharedExpenseOccurrencePostingService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\TransactionMutationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

test('installment paid summary counts actual historical and partial payments and respects filters', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12'));
    $c = recurringManagementContext();
    $entry = createManagedRecurringEntry($c, ['entry_type' => 'installment', 'total_amount' => 250, 'installments_count' => 5, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 5]);
    $rows = $entry->occurrences()->orderBy('sequence_number')->get();
    $makePayment = fn (float $amount, string $date, string $status = 'confirmed'): Transaction => Transaction::create(['user_id' => $c['user']->id, 'account_id' => $c['account']->id, 'direction' => 'expense', 'amount' => $amount, 'currency' => 'EUR', 'transaction_date' => $date, 'kind' => 'manual', 'source_type' => 'manual', 'status' => $status]);
    foreach ([[70, '2026-03-15', 'confirmed'], [120, '2026-11-15', 'confirmed'], [33, '2026-04-15', 'draft'], [50, '2026-05-15', 'confirmed']] as $index => [$amount, $date, $status]) {
        $payment = $makePayment($amount, $date, $status);
        $rows[$index]->update(['converted_transaction_id' => $payment->id, 'status' => 'completed']);
    }
    $refunded = $rows[3]->fresh()->convertedTransaction;
    Transaction::create(['user_id' => $c['user']->id, 'account_id' => $c['account']->id, 'direction' => 'income', 'amount' => 50, 'currency' => 'EUR', 'transaction_date' => '2026-06-01', 'kind' => 'refund', 'source_type' => 'manual', 'status' => 'confirmed', 'refunded_transaction_id' => $refunded->id]);
    $partial = $makePayment(100, '2026-06-15');
    $rows[4]->paymentAllocations()->create(['transaction_id' => $partial->id, 'amount_cents' => 2000]);
    $this->actingAs($c['user'])->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()
        ->assertJsonPath('recurringEntry.summary.paid_count', 1)
        ->assertJsonPath('recurringEntry.summary.paid_amount', 90)
        ->assertJsonPath('recurringEntry.summary.paid_year', null);
    $this->actingAs($c['user'])->get(route('recurring-entries.index', ['year' => 2026, 'month' => 10]))->assertInertia(fn (Assert $page) => $page->where('monthlyCalendar.summary.installment_paid_totals.0.currency', 'EUR')->where('monthlyCalendar.summary.installment_paid_totals.0.amount', 90));
    $this->get(route('recurring-entries.index', ['year' => 2026, 'month' => 11]))->assertInertia(fn (Assert $page) => $page->where('monthlyCalendar.summary.installment_paid_totals.0.amount', 90));
    $this->get(route('recurring-entries.index', ['year' => 2026, 'month' => 10, 'entry_type' => 'recurring']))->assertInertia(fn (Assert $page) => $page->where('monthlyCalendar.summary.installment_paid_totals', []));
    $rows[0]->convertedTransaction->delete();
    $this->get(route('recurring-entries.index', ['year' => 2026, 'month' => 10]))->assertInertia(fn (Assert $page) => $page->where('monthlyCalendar.summary.installment_paid_totals.0.amount', 20));
});

test('indefinite recurring detail only counts payments received in the current year', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12'));
    $c = recurringManagementContext();
    $entry = createManagedRecurringEntry($c, ['expected_amount' => 9.99]);
    $rows = $entry->occurrences()->orderBy('sequence_number')->get();
    foreach (['2025-12-15', '2026-03-15', '2026-11-15'] as $index => $date) {
        $transaction = Transaction::create(['user_id' => $c['user']->id, 'account_id' => $c['account']->id, 'direction' => 'expense', 'amount' => 9.99, 'currency' => 'EUR', 'transaction_date' => $date, 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
        $rows[$index]->update(['converted_transaction_id' => $transaction->id, 'status' => 'completed']);
    }
    $this->actingAs($c['user'])->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()
        ->assertJsonPath('recurringEntry.summary.paid_count', 1)
        ->assertJsonPath('recurringEntry.summary.paid_amount', 9.99)
        ->assertJsonPath('recurringEntry.summary.paid_year', 2026);
});

test('recurring and installment forms receive the authenticated users active shared counterparties', function () {
    $context = recurringManagementContext();
    $otherUser = User::factory()->create();

    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id,
        'name' => 'Emanuela',
        'slug' => 'emanuela',
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);
    TrackedItem::query()->create([
        'user_id' => $context['user']->id,
        'name' => 'Inattiva',
        'slug' => 'inattiva-shared',
        'is_active' => false,
        'is_shared_expense_counterpart' => true,
    ]);
    TrackedItem::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Altra utente',
        'slug' => 'altra-utente-shared',
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);

    foreach ([
        RecurringEntryTypeEnum::RECURRING->value,
        RecurringEntryTypeEnum::INSTALLMENT->value,
    ] as $entryType) {
        $this->actingAs($context['user'])
            ->get(route('recurring-entries.index', ['entry_type' => $entryType]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('formOptions.shared_expense_counterparties', [
                    ['value' => $counterparty->uuid, 'label' => 'Emanuela'],
                ]));
    }
});

test('recurring exchange preview returns no fx preview when account currency matches the base currency', function () {
    $context = recurringManagementContext();
    $context['user']->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();
    $context['account']->forceFill([
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ])->save();

    $this->actingAs($context['user'])
        ->postJson(route('recurring-entries.exchange-preview'), [
            'account_uuid' => $context['account']->uuid,
            'start_date' => '2026-03-15',
            'amount' => 25,
        ])
        ->assertSuccessful()
        ->assertJson([
            'currency_code' => 'EUR',
            'base_currency_code' => 'EUR',
            'exchange_rate' => '1.00000000',
            'exchange_rate_date' => '2026-03-15',
            'converted_base_amount_raw' => 25.0,
            'is_multi_currency' => false,
            'should_preview' => false,
        ]);
});

test('recurring exchange preview returns stored fx data when account currency differs from base currency', function () {
    $context = recurringManagementContext();
    $context['user']->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();
    $context['account']->forceFill([
        'currency' => 'GBP',
        'currency_code' => 'GBP',
    ])->save();

    ExchangeRate::factory()->create([
        'base_currency_code' => 'GBP',
        'quote_currency_code' => 'EUR',
        'rate' => '1.16000000',
        'rate_date' => '2026-03-15',
        'source' => 'frankfurter',
    ]);

    $this->actingAs($context['user'])
        ->postJson(route('recurring-entries.exchange-preview'), [
            'account_uuid' => $context['account']->uuid,
            'start_date' => '2026-03-15',
            'amount' => 25,
        ])
        ->assertSuccessful()
        ->assertJson([
            'currency_code' => 'GBP',
            'base_currency_code' => 'EUR',
            'exchange_rate' => '1.16000000',
            'exchange_rate_date' => '2026-03-15',
            'converted_base_amount_raw' => 29.0,
            'exchange_rate_source' => 'frankfurter',
            'is_multi_currency' => true,
            'should_preview' => true,
        ]);
});

test('recurring exchange preview returns a readable error when the rate is unavailable', function () {
    $context = recurringManagementContext();
    $context['user']->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();
    $context['account']->forceFill([
        'currency' => 'GBP',
        'currency_code' => 'GBP',
    ])->save();

    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $this->actingAs($context['user'])
        ->postJson(route('recurring-entries.exchange-preview'), [
            'account_uuid' => $context['account']->uuid,
            'start_date' => '2026-03-15',
            'amount' => 25,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);
});

test('store recurring entry creates the plan and generates initial occurrences', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), [
            ...recurringManagementPayload($context, [
                'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
                'start_date' => '2026-01-15',
                'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
                'occurrences_limit' => 3,
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHas('success', __('transactions.flash.recurring_created'));

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->title)->toBe('Rent recurring entry')
        ->and($entry->occurrences)->toHaveCount(3)
        ->and($entry->occurrences->pluck('status')->map->value->all())->toBe(['pending', 'pending', 'pending']);
});

test('recurring and installment entries resolve an active personal shared counterparty by uuid', function () {
    $context = recurringManagementContext();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id,
        'name' => 'Emanuela',
        'slug' => 'shared-expense-emanuela-'.$context['user']->id,
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);

    foreach ([
        RecurringEntryTypeEnum::RECURRING->value => [
            'expected_amount' => 50,
            'total_amount' => null,
            'installments_count' => null,
            'share' => '25.00',
        ],
        RecurringEntryTypeEnum::INSTALLMENT->value => [
            'expected_amount' => null,
            'total_amount' => 100,
            'installments_count' => 2,
            'share' => '50.00',
        ],
    ] as $entryType => $amounts) {
        $share = $amounts['share'];
        unset($amounts['share']);

        $this->actingAs($context['user'])
            ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
                ...$amounts,
                'title' => "Shared {$entryType}",
                'entry_type' => $entryType,
                'is_shared_expense' => true,
                'shared_expense_counterparty_uuid' => $counterparty->uuid,
                'shared_expense_paid_by' => 'self',
                'shared_expense_my_share' => $share,
                'shared_expense_counterparty_share' => $share,
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        expect(RecurringEntry::query()
            ->where('title', "Shared {$entryType}")
            ->sole()
            ->shared_expense_counterparty_id)->toBe($counterparty->id);
    }
});

test('a shared recurring entry persists its allocation, propagates it to occurrences, and exposes it to the recurring register', function () {
    $context = recurringManagementContext();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id,
        'name' => 'Emanuela',
        'slug' => 'shared-recurring-emanuela-'.$context['user']->id,
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'title' => 'Shared March rent',
            'start_date' => '2026-03-15',
            'is_shared_expense' => true,
            'shared_expense_counterparty_uuid' => $counterparty->uuid,
            'shared_expense_paid_by' => 'counterparty',
            'shared_expense_my_share' => '15.00',
            'shared_expense_counterparty_share' => '35.00',
        ]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $entry = RecurringEntry::query()->where('title', 'Shared March rent')->sole();
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();

    expect($entry->shared_expense_counterparty_id)->toBe($counterparty->id)
        ->and($entry->shared_expense_paid_by)->toBe('counterparty')
        ->and($entry->shared_expense_my_share_cents)->toBe(1500)
        ->and($entry->shared_expense_counterparty_share_cents)->toBe(3500)
        ->and($occurrence->shared_expense_paid_by)->toBe('counterparty')
        ->and($occurrence->shared_expense_my_share_cents)->toBe(1500)
        ->and($occurrence->shared_expense_counterparty_share_cents)->toBe(3500);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 3,
            'shared_expense' => 'shared',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.shared_expense', 'shared')
            ->where('recurringEntries.0.shared_expense_counterparty.uuid', $counterparty->uuid)
            ->where('recurringEntries.0.shared_expense_counterparty.name', 'Emanuela')
            ->where('recurringEntries.0.shared_expense_paid_by', 'counterparty')
            ->where('recurringEntries.0.shared_expense_my_share', '15.00')
            ->where('recurringEntries.0.shared_expense_counterparty_share', '35.00')
            ->where('monthlyCalendar.days.0.occurrences.0.recurring_entry.shared_expense_counterparty.uuid', $counterparty->uuid)
            ->where('monthlyCalendar.days.0.occurrences.0.recurring_entry.shared_expense_counterparty.name', 'Emanuela'));
});

test('the recurring shared filter excludes normal plans without leaking another users plans', function () {
    $context = recurringManagementContext();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id,
        'name' => 'Emanuela',
        'slug' => 'shared-filter-emanuela-'.$context['user']->id,
        'is_active' => true,
        'is_shared_expense_counterpart' => true,
    ]);
    $otherContext = recurringManagementContext();

    createManagedRecurringEntry($context, [
        'title' => 'Normal March rent',
        'start_date' => '2026-03-15',
    ]);
    createManagedRecurringEntry($context, [
        'title' => 'Shared March rent',
        'start_date' => '2026-03-15',
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'self',
        'shared_expense_my_share' => '25.00',
        'shared_expense_counterparty_share' => '25.00',
    ]);
    createManagedRecurringEntry($otherContext, [
        'title' => 'Other users shared rent',
        'start_date' => '2026-03-15',
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 3,
            'shared_expense' => 'shared',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries): bool => count($entries) === 1
                && $entries[0]['title'] === 'Shared March rent')
            ->where('monthlyCalendar.summary.entries_count', 1));

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 3,
            'shared_expense' => 'not_shared',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries): bool => count($entries) === 1
                && $entries[0]['title'] === 'Normal March rent')
            ->where('monthlyCalendar.summary.entries_count', 1));
});

test('store recurring entry persists up to three advance reminders', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'reminder_days_before' => [15, 1, 7],
        ]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(RecurringEntry::query()->firstOrFail()->reminder_days_before)
        ->toBe([1, 7, 15]);
});

test('store recurring entry validates advance reminder limits', function (array $reminders, string $errorKey) {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'reminder_days_before' => $reminders,
        ]))
        ->assertSessionHasErrors($errorKey);

    expect(RecurringEntry::query()->count())->toBe(0);
})->with([
    'more than three' => [[1, 2, 3, 4], 'reminder_days_before'],
    'less than one day' => [[0], 'reminder_days_before.0'],
    'more than fifteen days' => [[16], 'reminder_days_before.0'],
    'duplicate offset' => [[3, 3], 'reminder_days_before.1'],
]);

test('store recurring entry rejects an end date earlier than the start date', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), [
            ...recurringManagementPayload($context, [
                'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
                'start_date' => '2026-03-20',
                'end_mode' => RecurringEndModeEnum::UNTIL_DATE->value,
                'end_date' => '2026-03-10',
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHasErrors([
            'end_date' => __('transactions.validation.recurring_end_date_after_start_date'),
        ]);
});

test('store recurring entry accepts a distant end date and creates its management year silently', function () {
    $context = recurringManagementContext();
    $this->travelTo(CarbonImmutable::parse('2026-08-25'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'start_date' => '2026-08-01',
            'end_mode' => RecurringEndModeEnum::UNTIL_DATE->value,
            'end_date' => '2046-01-05',
        ]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(RecurringEntry::query()->firstOrFail()->end_date?->toDateString())
        ->toBe('2046-01-05')
        ->and(UserYear::query()
            ->where('user_id', $context['user']->id)
            ->where('year', 2046)
            ->exists())->toBeTrue();
});

test('store installment entry creates the plan and generates installment occurrences with correct amounts', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'total_amount' => 1000,
            'installments_count' => 3,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences)->toHaveCount(3)
        ->and($entry->occurrences->pluck('expected_amount')->all())->toBe(['333.33', '333.33', '333.34']);
});

test('store accepts an unchecked installment recalculation confirmation flag', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'total_amount' => 25.90,
            'installments_count' => 3,
            'expected_amount' => null,
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 3,
            'confirm_installment_recalculation' => false,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('index returns recurring entries and supports filters and sorting', function () {
    $context = recurringManagementContext();
    $context['category']->update([
        'icon' => 'house',
        'color' => '#0f766e',
    ]);
    $context['account']->update(['is_default' => true]);
    $first = createManagedRecurringEntry($context, [
        'title' => 'Beta rent',
        'next_occurrence_date' => '2026-03-01',
        'auto_create_transaction' => false,
    ]);
    $second = createManagedRecurringEntry($context, [
        'title' => 'Alpha rent',
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 500,
        'installments_count' => 5,
        'next_occurrence_date' => '2026-02-01',
        'auto_create_transaction' => true,
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'auto_create_transaction' => 1,
            'year' => 2026,
            'month' => 3,
            'sort' => 'title',
            'direction_sort' => 'asc',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/recurring/Index')
            ->where('activePeriod.year', 2026)
            ->where('activePeriod.month', 3)
            ->where('dateOptions.available_years', [2026])
            ->where('dateOptions.today', now()->toDateString())
            ->where('formOptions.default_account_uuid', $context['account']->uuid)
            ->where('filters.entry_type', RecurringEntryTypeEnum::INSTALLMENT->value)
            ->where('filters.account_id', null)
            ->where('monthlyCalendar.month', 3)
            ->where('monthlyCalendar.summary.entries_count', 1)
            ->has('formOptions.accounts')
            ->has('formOptions.filter_accounts')
            ->has('formOptions.entry_types', 2)
            ->has('monthlyCalendar.days', 1)
            ->where('monthlyCalendar.days.0.date', '2026-03-15')
            ->where('monthlyCalendar.days.0.anchor', 'occurrence-day-2026-03-15')
            ->where('monthlyCalendar.days.0.occurrences_count', 1)
            ->where('monthlyCalendar.days.0.expense_total', 100)
            ->missing('recurringEntries.0.id')
            ->missing('recurringEntries.0.account.id')
            ->missing('monthlyCalendar.days.0.occurrences.0.id')
            ->where('formOptions.accounts', fn ($accounts) => count($accounts) > 0
                && is_string($accounts[0]['value'])
                && ! array_key_exists('id', $accounts[0])
                && $accounts[0]['label'] === $context['account']->name
                && $accounts[0]['bank_name'] === null
                && $accounts[0]['full_path'] === $context['account']->name)
            ->where("formOptions.categories.{$context['account']->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($item) => $item['value'] === $context['category']->uuid
                    && $item['icon'] === 'house'
                    && $item['color'] === '#0f766e'
                    && $item['full_path'] === 'Rent'
                    && $item['is_selectable'] === true))
            ->where('recurringEntries', fn ($entries) => count($entries) === 1
                && $entries[0]['uuid'] === $second->uuid
                && $entries[0]['title'] === 'Alpha rent'
                && $entries[0]['stats']['total_occurrences'] === 5)
            ->where('transactionsNavigation', fn ($navigation) => $navigation['context']['month'] === 3
                && str_contains($navigation['months'][2]['href'], '/recurring-entries'))
        );

    expect($first->uuid)->not->toBe($second->uuid);
});

test('index searches recurring entries by their displayed fields without changing the active period', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'title' => 'Amazon Prime subscription',
        'description' => 'Annual video plan',
        'start_date' => '2026-03-15',
    ]);

    foreach (['  amazon  ', 'rent', 'primary account', 'landlord', 'home', 'ricorrente'] as $search) {
        $this->actingAs($context['user'])
            ->get(route('recurring-entries.index', [
                'year' => 2026,
                'month' => 3,
                'search' => $search,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activePeriod.year', 2026)
                ->where('activePeriod.month', 3)
                ->where('filters.search', trim($search))
                ->where('recurringEntries', fn ($entries) => count($entries) === 1
                    && $entries[0]['uuid'] === $entry->uuid)
            );
    }

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 3,
            'search' => 'not found anywhere',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', [])
        );
});

test('store recurring entry automatically creates a future management year without changing the active year', function () {
    $context = recurringManagementContext();
    $this->travelTo(CarbonImmutable::parse('2026-12-15'));

    $context['user']->settings()->create(['active_year' => 2026]);

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'start_date' => '2027-01-15',
            'total_amount' => 1200,
            'installments_count' => 12,
        ]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(UserYear::query()
        ->where('user_id', $context['user']->id)
        ->where('year', 2027)
        ->where('is_closed', false)
        ->exists())->toBeTrue()
        ->and($context['user']->settings()->value('active_year'))->toBe(2026)
        ->and(RecurringEntry::query()->firstOrFail()->start_date?->toDateString())->toBe('2027-01-15');
});

test('store recurring entry still rejects a past unavailable management year', function () {
    $context = recurringManagementContext();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), [
            ...recurringManagementPayload($context, [
                'start_date' => '2025-12-31',
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHasErrors('start_date');
});

test('store recurring entry does not reopen a closed future management year', function () {
    $context = recurringManagementContext();
    $this->travelTo(CarbonImmutable::parse('2026-12-15'));

    UserYear::query()->create([
        'user_id' => $context['user']->id,
        'year' => 2027,
        'is_closed' => true,
    ]);

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'start_date' => '2027-01-15',
        ]))
        ->assertSessionHasErrors('start_date');

    expect(RecurringEntry::query()->count())->toBe(0)
        ->and(UserYear::query()->where('user_id', $context['user']->id)->where('year', 2027)->value('is_closed'))->toBeTrue();
});

test('store recurring entry does not create a future management year when validation fails', function () {
    $context = recurringManagementContext();
    $this->travelTo(CarbonImmutable::parse('2026-12-15'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'start_date' => '2027-01-15',
            'category_id' => 0,
        ]))
        ->assertSessionHasErrors('category_id');

    expect(UserYear::query()
        ->where('user_id', $context['user']->id)
        ->where('year', 2027)
        ->exists())->toBeFalse()
        ->and(RecurringEntry::query()->count())->toBe(0);
});

test('store recurring entry rejects an uncreated accounting year without persisting any plan data', function (string $locale, string $expectedMessage) {
    $context = recurringManagementContext();
    $context['user']->forceFill(['locale' => $locale])->save();

    $initialEntryCount = RecurringEntry::query()->count();
    $initialOccurrenceCount = RecurringEntryOccurrence::query()->count();
    $initialTransactionCount = Transaction::query()->count();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), [
            ...recurringManagementPayload($context, [
                'start_date' => '2025-06-06',
                'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 6],
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertInvalid([
            'start_date' => $expectedMessage,
        ])
        ->assertSessionMissing('success');

    expect(RecurringEntry::query()->count())->toBe($initialEntryCount)
        ->and(RecurringEntryOccurrence::query()->count())->toBe($initialOccurrenceCount)
        ->and(Transaction::query()->count())->toBe($initialTransactionCount);
})->with([
    'italian' => [
        'it',
        "L'anno contabile 2025 non è stato creato. Crealo prima nella sezione Anni di gestione per poter salvare.",
    ],
    'english' => [
        'en',
        'Accounting year 2025 has not been created. Create it first in the Management years section before saving.',
    ],
]);

test('index recurring date options expose available years and today', function () {
    $context = recurringManagementContext();

    UserYear::query()->where('user_id', $context['user']->id)->delete();
    UserYear::query()->create([
        'user_id' => $context['user']->id,
        'year' => 2025,
        'is_closed' => false,
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2025,
            'month' => 12,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dateOptions.available_years', [2025])
            ->where('dateOptions.min', '2025-01-01')
            ->where('dateOptions.today', now()->toDateString())
        );
});

test('index supports cancelled status filter', function () {
    $context = recurringManagementContext();
    createManagedRecurringEntry($context, [
        'title' => 'Active recurring',
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
    ]);
    $cancelled = createManagedRecurringEntry($context, [
        'title' => 'Cancelled recurring',
        'status' => RecurringEntryStatusEnum::CANCELLED->value,
        'is_active' => false,
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'status' => RecurringEntryStatusEnum::CANCELLED->value,
            'year' => 2026,
            'month' => 3,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', RecurringEntryStatusEnum::CANCELLED->value)
            ->where('recurringEntries', fn ($entries) => count($entries) === 1
                && $entries[0]['uuid'] === $cancelled->uuid
                && $entries[0]['status'] === RecurringEntryStatusEnum::CANCELLED->value)
        );
});

test('show returns recurring entry details occurrences and summary payload', function () {
    $context = recurringManagementContext();
    $context['account']->update(['is_default' => true]);
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 300,
        'installments_count' => 3,
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/recurring/Show')
            ->where('dateOptions.available_years', [2026])
            ->where('dateOptions.today', now()->toDateString())
            ->where('formOptions.default_account_uuid', $context['account']->uuid)
            ->where('recurringEntry.entry.uuid', $entry->uuid)
            ->where('recurringEntry.entry.stats.total_occurrences', 3)
            ->where('recurringEntry.summary.remaining_amount', 300)
            ->missing('recurringEntry.entry.id')
            ->missing('recurringEntry.occurrences.0.id')
            ->missing('recurringEntry.occurrences.0.converted_transaction_id')
            ->where('formOptions.accounts', fn ($accounts) => count($accounts) > 0
                && is_string($accounts[0]['value'])
                && ! array_key_exists('id', $accounts[0]))
            ->where('recurringEntry.occurrences', fn ($occurrences) => count($occurrences) === 3
                && $occurrences[0]['sequence_number'] === 1
                && array_key_exists('can_convert', $occurrences[0]))
            ->where('recurringEntry.actions.can_cancel', true)
            ->where('recurringEntry.actions.can_delete', true)
        );
});

test('show reports paid installments against the plan total', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 300,
        'installments_count' => 3,
    ]);
    $firstOccurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $firstOccurrence->uuid]))
        ->assertRedirect(recurringConversionDestination($firstOccurrence));

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.entry.installments_count', 3)
            ->where('recurringEntry.summary.total_occurrences', 3)
            ->where('recurringEntry.summary.converted_occurrences', 1)
        );
});

test('destroy removes the plan generated transactions and refunds but preserves matched transactions', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 200,
        'installments_count' => 2,
    ]);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $matchedTransaction = Transaction::query()->create([
        'user_id' => $context['user']->id,
        'account_id' => $context['account']->id,
        'category_id' => $context['category']->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 100,
        'currency' => 'EUR',
        'description' => 'Existing matched bank transaction',
        'source_type' => 'manual',
        'status' => 'confirmed',
    ]);
    $matchedOccurrence = $entry->occurrences()->orderBy('sequence_number')->skip(1)->firstOrFail();
    $matchedOccurrence->update(['matched_transaction_id' => $matchedTransaction->id]);

    $reminderDelivery = ReminderDelivery::query()->create([
        'user_id' => $context['user']->id,
        'remindable_type' => $occurrence->getMorphClass(),
        'remindable_id' => $occurrence->getKey(),
        'reminder_type' => 'recurring_advance_3',
        'due_date' => $occurrence->due_date,
        'delivery_date' => now()->toDateString(),
        'notification_kind' => 'recurring_due',
    ]);

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]))
        ->assertRedirect(recurringConversionDestination($occurrence));

    $generatedTransaction = $occurrence->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $generatedTransaction->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $refundTransaction = $generatedTransaction->fresh()->refundTransaction;

    $this->actingAs($context['user'])
        ->delete(route('recurring-entries.destroy', $entry->uuid))
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHas('success', __('transactions.flash.recurring_deleted'));

    $this->assertModelMissing($entry);
    $this->assertModelMissing($occurrence);
    $this->assertModelMissing($matchedOccurrence);
    $this->assertModelMissing($reminderDelivery);
    $this->assertModelExists($matchedTransaction);

    expect(Transaction::withTrashed()->whereKey($generatedTransaction->id)->exists())->toBeFalse()
        ->and(Transaction::withTrashed()->whereKey($refundTransaction->id)->exists())->toBeFalse();
});

test('show orders occurrences chronologically by date', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 300,
        'installments_count' => 3,
    ]);

    $occurrences = $entry->occurrences()->orderBy('sequence_number')->get();

    $occurrences[0]->update([
        'expected_date' => '2026-01-20',
        'due_date' => '2026-01-20',
    ]);
    $occurrences[1]->update([
        'expected_date' => '2026-01-05',
        'due_date' => '2026-01-05',
    ]);
    $occurrences[2]->update([
        'expected_date' => '2026-01-12',
        'due_date' => '2026-01-12',
    ]);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.occurrences.0.due_date', '2026-01-05')
            ->where('recurringEntry.occurrences.1.due_date', '2026-01-12')
            ->where('recurringEntry.occurrences.2.due_date', '2026-01-20')
        );
});

test('user sees recurring entries from accessible shared accounts but not revoked memberships', function () {
    $context = recurringManagementContext();
    $sharedOwnerContext = recurringManagementContext();
    $revokedOwnerContext = recurringManagementContext();

    $sharedEntry = createManagedRecurringEntry($sharedOwnerContext, [
        'title' => 'Shared recurring visible',
    ]);

    createManagedRecurringEntry($revokedOwnerContext, [
        'title' => 'Shared recurring hidden',
    ]);

    shareRecurringAccount($sharedOwnerContext['account'], $context['user'], AccountMembershipRoleEnum::VIEWER);
    shareRecurringAccount($revokedOwnerContext['account'], $context['user'], AccountMembershipRoleEnum::VIEWER, AccountMembershipStatusEnum::REVOKED);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->contains(fn ($entry) => $entry['uuid'] === $sharedEntry->uuid && $entry['title'] === 'Shared recurring visible'))
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->doesntContain(fn ($entry) => $entry['title'] === 'Shared recurring hidden')));
});

test('viewer can read shared recurring entries but cannot mutate them', function () {
    $context = recurringManagementContext();
    $ownerContext = recurringManagementContext();
    $entry = createManagedRecurringEntry($ownerContext, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    shareRecurringAccount($ownerContext['account'], $context['user'], AccountMembershipRoleEnum::VIEWER);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.entry.uuid', $entry->uuid)
            ->where('recurringEntry.entry.can_edit', false)
            ->where('recurringEntry.actions.can_pause', false)
            ->where('recurringEntry.actions.can_resume', false)
            ->where('recurringEntry.actions.can_cancel', false)
            ->where('recurringEntry.actions.can_delete', false)
            ->where('recurringEntry.occurrences.0.can_convert', false)
            ->where('recurringEntry.occurrences.0.can_skip', false)
            ->where('recurringEntry.occurrences.0.can_cancel', false));

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($ownerContext, [
            'title' => 'Viewer should not update',
        ]))
        ->assertSessionHasErrors('entry');

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Viewer should not create',
        ]))
        ->assertSessionHasErrors('account_id');

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'confirm_future_date' => true,
        ])
        ->assertSessionHasErrors('occurrence');

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->delete(route('recurring-entries.destroy', $entry->uuid))
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHasErrors('entry');

    $this->assertModelExists($entry);
});

test('shared recurring form options expose accessible filter accounts and operational owner plus editor datasets while excluding viewers', function () {
    $viewerContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();
    $editorContributor = recurringManagementContext();
    $viewerContributor = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $viewerContext['user'], AccountMembershipRoleEnum::VIEWER);
    shareRecurringAccount($ownerContext['account'], $editorContributor['user'], AccountMembershipRoleEnum::EDITOR);
    shareRecurringAccount($ownerContext['account'], $viewerContributor['user'], AccountMembershipRoleEnum::VIEWER);

    $editorScope = Scope::query()->create([
        'user_id' => $editorContributor['user']->id,
        'name' => 'Editor shared scope',
        'type' => 'household',
        'color' => '#112233',
        'is_active' => true,
    ]);
    $viewerScope = Scope::query()->create([
        'user_id' => $viewerContributor['user']->id,
        'name' => 'Viewer hidden scope',
        'type' => 'household',
        'color' => '#445566',
        'is_active' => true,
    ]);
    $editorCategory = Category::query()->create([
        'user_id' => $editorContributor['user']->id,
        'name' => 'Editor shared category',
        'slug' => 'editor-shared-category-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $viewerCategory = Category::query()->create([
        'user_id' => $viewerContributor['user']->id,
        'name' => 'Viewer hidden category',
        'slug' => 'viewer-hidden-category-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $editorTrackedItem = TrackedItem::query()->create([
        'user_id' => $editorContributor['user']->id,
        'name' => 'Editor shared tracked item',
        'slug' => 'editor-shared-tracked-item-'.fake()->unique()->slug(),
        'type' => 'asset',
        'is_active' => true,
    ]);
    $viewerTrackedItem = TrackedItem::query()->create([
        'user_id' => $viewerContributor['user']->id,
        'name' => 'Viewer hidden tracked item',
        'slug' => 'viewer-hidden-tracked-item-'.fake()->unique()->slug(),
        'type' => 'asset',
        'is_active' => true,
    ]);

    $this->actingAs($viewerContext['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formOptions.filter_accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['value'] === (string) $ownerContext['account']->id
                    && $account['is_shared'] === true
                    && $account['can_edit'] === false))
            ->where('formOptions.accounts', fn ($accounts) => collect($accounts)
                ->doesntContain(fn ($account) => $account['value'] === $ownerContext['account']->uuid))
        );

    $this->actingAs($editorContributor['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('formOptions.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['value'] === $ownerContext['account']->uuid
                    && $account['is_shared'] === true
                    && $account['membership_role'] === AccountMembershipRoleEnum::EDITOR->value
                    && in_array($ownerContext['user']->id, $account['category_contributor_user_ids'], true)
                    && in_array($editorContributor['user']->id, $account['category_contributor_user_ids'], true)
                    && ! in_array($viewerContributor['user']->id, $account['category_contributor_user_ids'], true)))
            ->where('formOptions.scopes', fn ($scopes) => collect($scopes)->contains('label', $ownerContext['scope']->name)
                && collect($scopes)->contains('label', $editorScope->name)
                && collect($scopes)->doesntContain('label', $viewerScope->name))
            ->where('formOptions.categories', fn ($categories) => collect($categories)->has($ownerContext['account']->uuid)
                && collect(collect($categories)->get($ownerContext['account']->uuid, []))->doesntContain(
                    fn ($category) => str_contains($category['label'], $viewerCategory->name)
                ))
            ->where('formOptions.tracked_items', fn ($trackedItems) => collect($trackedItems)->has($ownerContext['account']->uuid)
                && collect(collect($trackedItems)->get($ownerContext['account']->uuid, []))->doesntContain(
                    fn ($trackedItem) => str_contains($trackedItem['label'], $viewerTrackedItem->name)
                ))
        );
});

test('editor can create and mutate recurring entries on shared accounts with owner dataset preserved', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editorContext['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Shared editor recurring',
            'tracked_item_id' => null,
            'start_date' => '2026-02-15',
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 2,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()
        ->where('title', 'Shared editor recurring')
        ->firstOrFail();

    expect($entry->user_id)->toBe($ownerContext['user']->id)
        ->and($entry->created_by_user_id)->toBe($editorContext['user']->id)
        ->and($entry->updated_by_user_id)->toBe($editorContext['user']->id);

    $this->actingAs($editorContext['user'])
        ->patch(route('recurring-entries.pause', $entry->uuid), ['periods' => 1])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::ACTIVE)
        ->and($entry->occurrences()->where('status', 'skipped')->count())->toBe(1)
        ->and($entry->occurrences()->where('status', 'pending')->count())->toBe(1);

    $this->actingAs($editorContext['user'])
        ->patch(route('recurring-entries.resume', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $this->actingAs($editorContext['user'])
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($ownerContext, [
            'title' => 'Shared editor updated',
            'tracked_item_id' => null,
            'description' => 'Updated by shared editor',
            'start_date' => '2026-02-15',
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 2,
        ]))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->title)->toBe('Shared editor updated')
        ->and($entry->fresh()->updated_by_user_id)->toBe($editorContext['user']->id);
});

test('owner sees recurring entries created by an invitee on a shared account', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editorContext['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Invitee shared recurring visible to owner',
            'tracked_item_id' => null,
            'start_date' => '2026-02-15',
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 2,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()
        ->where('title', 'Invitee shared recurring visible to owner')
        ->firstOrFail();

    $this->actingAs($ownerContext['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->contains(fn ($candidate) => $candidate['uuid'] === $entry->uuid && $candidate['title'] === 'Invitee shared recurring visible to owner'))
        );
});

test('invitee sees recurring entries created by the owner on a shared account', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $entry = createManagedRecurringEntry($ownerContext, [
        'title' => 'Owner shared recurring visible to invitee',
        'tracked_item_id' => null,
        'start_date' => '2026-02-15',
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);

    $this->actingAs($editorContext['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->contains(fn ($candidate) => $candidate['uuid'] === $entry->uuid && $candidate['title'] === 'Owner shared recurring visible to invitee'))
        );
});

test('editor can create shared recurring entries using shared account scoped categories and tracked items while keeping scopes contributor aware', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();
    $viewerContributor = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);
    shareRecurringAccount($ownerContext['account'], $viewerContributor['user'], AccountMembershipRoleEnum::VIEWER);

    $editorScope = Scope::query()->create([
        'user_id' => $editorContext['user']->id,
        'name' => 'Editor recurring scope',
        'type' => 'household',
        'color' => '#123456',
        'is_active' => true,
    ]);
    $sharedCategory = Category::query()->create([
        'user_id' => $ownerContext['user']->id,
        'account_id' => $ownerContext['account']->id,
        'name' => 'Shared recurring category',
        'slug' => 'shared-recurring-category-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $sharedTrackedItem = TrackedItem::query()->create([
        'user_id' => $ownerContext['user']->id,
        'account_id' => $ownerContext['account']->id,
        'name' => 'Shared recurring tracked item',
        'slug' => 'shared-recurring-tracked-item-'.fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [TransactionDirectionEnum::EXPENSE->value],
            'transaction_category_uuids' => [$sharedCategory->uuid],
        ],
    ]);
    $sharedTrackedItem->compatibleCategories()->sync([$sharedCategory->id]);
    $viewerScope = Scope::query()->create([
        'user_id' => $viewerContributor['user']->id,
        'name' => 'Viewer recurring scope',
        'type' => 'household',
        'color' => '#654321',
        'is_active' => true,
    ]);

    $this->actingAs($editorContext['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Shared owner scope recurring',
            'scope_id' => $ownerContext['scope']->id,
            'category_id' => $sharedCategory->id,
            'tracked_item_id' => $sharedTrackedItem->id,
        ]))
        ->assertRedirect();

    $this->actingAs($editorContext['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Shared editor scope recurring',
            'scope_id' => $editorScope->id,
            'category_id' => $sharedCategory->id,
            'tracked_item_id' => $sharedTrackedItem->id,
        ]))
        ->assertRedirect();

    $this->actingAs($editorContext['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), recurringManagementPayload($ownerContext, [
            'title' => 'Shared viewer scope recurring',
            'scope_id' => $viewerScope->id,
            'category_id' => $sharedCategory->id,
            'tracked_item_id' => $sharedTrackedItem->id,
        ]))
        ->assertSessionHasErrors('scope_id');

    expect(RecurringEntry::query()->where('title', 'Shared owner scope recurring')->exists())->toBeTrue()
        ->and(RecurringEntry::query()->where('title', 'Shared editor scope recurring')->exists())->toBeTrue()
        ->and(RecurringEntry::query()->where('title', 'Shared viewer scope recurring')->exists())->toBeFalse();
});

test('recurring editor can create a new tracked item option on a shared account and receive an account scoped reference immediately', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editorContext['user'])
        ->postJson(route('recurring-entries.tracked-items.store'), [
            'name' => 'Shared recurring reference',
            'account_uuid' => $ownerContext['account']->uuid,
            'category_uuid' => $ownerContext['category']->uuid,
            'direction' => TransactionDirectionEnum::EXPENSE->value,
        ])
        ->assertOk()
        ->assertJsonPath('item.label', 'Shared recurring reference')
        ->assertJsonPath('item.account_uuid', $ownerContext['account']->uuid);

    $trackedItem = TrackedItem::query()
        ->where('account_id', $ownerContext['account']->id)
        ->where('name', 'Shared recurring reference')
        ->firstOrFail();

    expect((int) $trackedItem->account_id)->toBe($ownerContext['account']->id)
        ->and((int) $trackedItem->user_id)->toBe($ownerContext['account']->user_id)
        ->and($trackedItem->settings['shared_account_uuid'] ?? null)->toBe($ownerContext['account']->uuid)
        ->and($trackedItem->settings['transaction_category_uuids'] ?? [])->not->toBe([]);
});

test('recurring form tracked item creation reuses an existing personal reference without duplicates and keeps it visible in the personal account catalog', function () {
    $context = recurringManagementContext();

    TrackedItem::query()->create([
        'user_id' => $context['user']->id,
        'account_id' => null,
        'name' => 'Recurring gym',
        'slug' => 'recurring-gym',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [TransactionDirectionEnum::EXPENSE->value],
            'transaction_category_uuids' => [$context['category']->uuid],
        ],
    ])->compatibleCategories()->sync([$context['category']->id]);

    $this->actingAs($context['user'])
        ->postJson(route('recurring-entries.tracked-items.store'), [
            'name' => 'Recurring gym',
            'account_uuid' => $context['account']->uuid,
            'category_uuid' => $context['category']->uuid,
            'direction' => TransactionDirectionEnum::EXPENSE->value,
        ])
        ->assertOk()
        ->assertJsonPath('item.label', 'Recurring gym')
        ->assertJsonPath('item.account_uuid', $context['account']->uuid);

    expect(TrackedItem::query()
        ->ownedBy($context['user']->id)
        ->where('slug', 'recurring-gym')
        ->count())->toBe(1);
});

test('shared recurring show payload keeps account scoped categories and tracked items on update flows without personal fallback', function () {
    $editorContext = recurringManagementContext();
    $ownerContext = recurringManagementContext();

    shareRecurringAccount($ownerContext['account'], $editorContext['user'], AccountMembershipRoleEnum::EDITOR);

    $sharedCategory = Category::query()->create([
        'user_id' => $ownerContext['user']->id,
        'account_id' => $ownerContext['account']->id,
        'name' => 'Shared recurring category',
        'slug' => 'shared-recurring-category-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $sharedTrackedItem = TrackedItem::query()->create([
        'user_id' => $ownerContext['user']->id,
        'account_id' => $ownerContext['account']->id,
        'name' => 'Shared recurring item',
        'slug' => 'shared-recurring-item-'.fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [TransactionDirectionEnum::EXPENSE->value],
            'transaction_category_uuids' => [$sharedCategory->uuid],
        ],
    ]);
    $sharedTrackedItem->compatibleCategories()->sync([$sharedCategory->id]);
    $personalTrackedItem = TrackedItem::query()->create([
        'user_id' => $editorContext['user']->id,
        'account_id' => null,
        'name' => 'Editor personal hidden item',
        'slug' => 'editor-personal-hidden-item-'.fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
    ]);

    $entry = createManagedRecurringEntry($ownerContext, [
        'category_id' => $sharedCategory->id,
        'tracked_item_id' => $sharedTrackedItem->id,
    ]);

    $this->actingAs($editorContext['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.entry.tracked_item.uuid', $sharedTrackedItem->uuid)
            ->where('formOptions.categories', fn ($categories) => collect($categories)->has($ownerContext['account']->uuid))
            ->where('formOptions.tracked_items', fn ($trackedItems) => collect(collect($trackedItems)->get($ownerContext['account']->uuid, []))
                ->contains(fn ($trackedItem) => $trackedItem['label'] === $sharedTrackedItem->name)
                && collect(collect($trackedItems)->get($ownerContext['account']->uuid, []))->doesntContain(
                    fn ($trackedItem) => str_contains($trackedItem['label'], $personalTrackedItem->name)
                ))
        );
});

test('store recurring automatic entry with start date today creates the first transaction immediately', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'start_date' => '2026-03-23',
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'auto_create_transaction' => true,
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 3,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(1)
        ->and(Transaction::query()->where('recurring_entry_occurrence_id', $entry->occurrences()->firstOrFail()->id)->count())->toBe(1);
});

test('store installment automatic entry with start date today creates the first transaction immediately', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'start_date' => '2026-03-23',
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'total_amount' => 1200,
            'installments_count' => 3,
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(1);
});

test('store recurring automatic entry with past start date creates all matured transactions immediately', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'start_date' => '2026-01-23',
            'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 5,
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(3)
        ->and(Transaction::query()->whereNotNull('recurring_entry_occurrence_id')->count())->toBe(3);
});

test('store installment automatic entry with past start date creates all matured installments immediately', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'start_date' => '2026-01-23',
            'total_amount' => 900,
            'installments_count' => 5,
            'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    expect($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(3);
});

test('an installment plan reports the dated negative balance and rolls back every change', function (string $locale, string $expectedMessage) {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-08-25'));

    $context['user']->forceFill(['locale' => $locale])->save();
    $context['account']->forceFill([
        'settings' => ['allow_negative_balance' => false],
    ])->save();

    app(AccountOpeningBalanceService::class)->sync(
        $context['account'],
        1000,
        'positive',
        '2026-08-01',
        $context['user'],
    );

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'start_date' => '2026-06-06',
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 6],
            'total_amount' => 900,
            'installments_count' => 3,
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect(route('recurring-entries.index'))
        ->assertInvalid([
            'amount' => $expectedMessage,
        ]);

    expect(RecurringEntry::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(1)
        ->and(Transaction::query()->firstOrFail()->kind)->toBe(TransactionKindEnum::OPENING_BALANCE);
})->with([
    'italian' => [
        'it',
        'Alla data 06/06/26 il saldo del conto “Primary account” risulterebbe inferiore a 0. Modifica la data del saldo iniziale del conto oppure scegli una data del movimento compatibile.',
    ],
    'english' => [
        'en',
        'On 06/06/26, the balance of account “Primary account” would be below 0. Change the account opening balance date or choose a compatible transaction date.',
    ],
]);

test('store recurring manual entry with start date today does not create transactions automatically', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'start_date' => '2026-03-23',
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'auto_create_transaction' => false,
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 3,
        ]))
        ->assertRedirect();

    expect(Transaction::query()->count())->toBe(0);
});

test('store installment manual entry with past start date does not create transactions automatically', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'start_date' => '2026-01-23',
            'total_amount' => 900,
            'installments_count' => 5,
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'auto_create_transaction' => false,
        ]))
        ->assertRedirect();

    expect(Transaction::query()->count())->toBe(0);
});

test('initial automatic posting remains idempotent on a second lifecycle synchronization', function () {
    $context = recurringManagementContext();

    $this->travelTo(CarbonImmutable::parse('2026-03-23'));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
            'start_date' => '2026-01-23',
            'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 23],
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 5,
            'auto_create_transaction' => true,
        ]))
        ->assertRedirect();

    $entry = RecurringEntry::query()->firstOrFail();

    app(RecurringEntryLifecycleService::class)
        ->synchronize($entry->fresh(), CarbonImmutable::parse('2026-03-23'));

    expect(Transaction::query()->whereNotNull('recurring_entry_occurrence_id')->count())->toBe(3);
});

test('update allows structural changes when the plan has no converted occurrences', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-01'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'start_date' => '2026-01-15',
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);

    $newCategory = Category::query()->create([
        'user_id' => $context['user']->id,
        'name' => 'Mutuo',
        'slug' => 'mutuo-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.index'))
        ->patch(route('recurring-entries.update', $entry->uuid), [
            ...recurringManagementPayload($context, [
                'category_id' => $newCategory->id,
                'recurrence_type' => RecurringEntryRecurrenceTypeEnum::DAILY->value,
                'start_date' => '2026-02-01',
                'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
                'occurrences_limit' => 3,
            ]),
            'redirect_to' => 'index',
        ])
        ->assertRedirect(route('recurring-entries.index'))
        ->assertSessionHas('success', __('transactions.flash.recurring_updated'));

    $entry->refresh();

    expect($entry->category_id)->toBe($newCategory->id)
        ->and($entry->occurrences)->toHaveCount(3)
        ->and($entry->occurrences->pluck('expected_date')->map->toDateString()->all())->toBe(['2026-02-01', '2026-02-02', '2026-02-03']);
});

test('plan edits preserve customized and past occurrences while updating only untouched future amounts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-20'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, ['is_amount_variable' => true]);
    $past = $entry->occurrences()->where('sequence_number', 1)->firstOrFail();
    $custom = $entry->occurrences()->where('sequence_number', 2)->firstOrFail();
    app(UpdateRecurringOccurrenceAmountService::class)->update($custom, $context['user'], 77);
    $custom->refresh()->forceFill(['due_date' => '2026-02-18'])->save();
    $customAttributes = $custom->fresh()->getAttributes();
    $pastAttributes = $past->getAttributes();
    $service = app(RecurringEntryManagementService::class);

    $service->update($context['user'], $entry, ['title' => 'New title']);
    expect($custom->fresh()->getAttributes())->toBe($customAttributes)
        ->and($past->fresh()->getAttributes())->toBe($pastAttributes);

    $service->update($context['user'], $entry->fresh(), ['expected_amount' => 70]);
    expect($custom->fresh()->getAttributes())->toBe($customAttributes)
        ->and($past->fresh()->getAttributes())->toBe($pastAttributes)
        ->and($entry->occurrences()->where('sequence_number', 3)->firstOrFail()->expected_amount)->toBe('70.00');

    $service->update($context['user'], $entry->fresh(), ['start_date' => '2026-01-16', 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 16]]);
    expect($custom->fresh()->getAttributes())->toBe($customAttributes)
        ->and($past->fresh()->getAttributes())->toBe($pastAttributes);
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry->fresh(), null, 15);
    expect($entry->occurrences()->where('sequence_number', 15)->firstOrFail()->expected_amount)->toBe('70.00');
});

test('changing a recurring base amount never rewrites a converted occurrence', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-20'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context);
    $occurrence = $entry->occurrences()->where('sequence_number', 1)->firstOrFail();
    $this->actingAs($context['user'])->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), ['transaction_date' => '2026-01-15'])->assertSessionHasNoErrors();
    $original = $occurrence->fresh()->getAttributes();
    $transaction = $occurrence->fresh()->convertedTransaction;
    $originalTransaction = $transaction->getAttributes();

    app(RecurringEntryManagementService::class)->update($context['user'], $entry->fresh(), ['expected_amount' => 90, 'title' => 'Changed']);
    expect($occurrence->fresh()->getAttributes())->toBe($original)
        ->and($transaction->fresh()->getAttributes())->toBe($originalTransaction)
        ->and($entry->occurrences()->where('sequence_number', 2)->firstOrFail()->expected_amount)->toBe('90.00');
});

test('a refunded automatic occurrence remains history when its future schedule is realigned', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-28'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'start_date' => '2026-09-28',
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 28],
        'auto_create_transaction' => true,
    ]);
    $history = $entry->occurrences()->where('sequence_number', 1)->firstOrFail();
    $transaction = $history->convertedTransaction;
    expect($transaction)->not->toBeNull();
    $refund = app(TransactionRefundService::class)->refund($transaction);
    $historicalAttributes = $history->fresh()->getAttributes();
    $transactionAttributes = $transaction->fresh()->getAttributes();
    $refundAttributes = $refund->getAttributes();
    $generator = app(RecurringEntryOccurrenceGeneratorService::class);
    $generator->generate($entry, null, 8);
    $custom = $entry->occurrences()->where('sequence_number', 5)->firstOrFail();
    $custom->forceFill(['expected_amount' => 77, 'amount_confirmed_at' => now(),
        'expected_date' => '2027-11-28', 'due_date' => '2027-11-28',
    ])->save();
    $customAttributes = $custom->getAttributes();

    $this->travelTo(CarbonImmutable::parse('2026-10-10'));
    $this->actingAs($context['user'])->patch(route('recurring-entries.update', $entry->uuid),
        recurringManagementPayload($context, [
            'start_date' => '2026-11-28',
            'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 28],
            'auto_create_transaction' => true,
        ]))->assertSessionHasNoErrors();
    $entry->refresh();
    $generator->generate($entry, null, 15);
    $count = $entry->occurrences()->count();
    $generator->generate($entry, null, 15);
    app(RecurringEntryManagementService::class)->update($context['user'], $entry->fresh(), ['title' => 'Corrected schedule']);

    expect($history->fresh()->getAttributes())->toBe($historicalAttributes)
        ->and($history->fresh()->status->value)->toBe('pending')
        ->and($transaction->fresh()->getAttributes())->toBe($transactionAttributes)
        ->and($refund->fresh()->getAttributes())->toBe($refundAttributes)
        ->and($custom->fresh()->getAttributes())->toBe($customAttributes)
        ->and($entry->occurrences()->where('sequence_number', 2)->firstOrFail()->expected_date->toDateString())->toBe('2026-11-28')
        ->and($entry->occurrences()->where('sequence_number', 3)->firstOrFail()->expected_date->toDateString())->toBe('2026-12-28')
        ->and($entry->occurrences()->where('sequence_number', 3)->firstOrFail()->status->value)->toBe('pending')
        ->and($entry->occurrences()->count())->toBeGreaterThanOrEqual($count)
        ->and($entry->occurrences()->whereDate('expected_date', '2026-10-28')->where('status', 'pending')->exists())->toBeFalse()
        ->and(Transaction::query()->count())->toBe(2)
        ->and($entry->occurrences()->openObligations()->whereKey($history->id)->exists())->toBeFalse();
    $dates = $entry->occurrences()->whereIn('status', ['pending', 'generated'])->pluck('expected_date')->map->toDateString();
    expect($dates->unique()->count())->toBe($dates->count());
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn ($page) => $page
        ->where('recurringEntry.entry.stats.can_realign_refunded_schedule', true));
});

test('converted occurrence transaction link points to the matching transactions month with highlight query', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'transaction_date' => '2026-01-15',
        ])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.occurrences.0.converted_transaction.show_url', function (string $url) use ($occurrence): bool {
                $transactionUuid = $occurrence->fresh()->convertedTransaction?->uuid;

                return str_contains($url, '/transactions/2026/1')
                    && str_contains($url, 'highlight='.$transactionUuid)
                    && str_contains($url, 'source=recurring');
            })
        );
});

test('variable recurring plan updates only one pending occurrence amount', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'is_amount_variable' => true,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);
    $occurrences = $entry->occurrences()->orderBy('sequence_number')->get();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.occurrences.amount.update', [
            $entry->uuid,
            $occurrences[0]->uuid,
        ]), [
            'amount' => '83.45',
        ])
        ->assertRedirect(route('recurring-entries.show', [
            'recurringEntry' => $entry->uuid,
            'highlight' => $occurrences[0]->uuid,
        ]))
        ->assertSessionHas('success', __('transactions.flash.recurring_occurrence_amount_updated'));

    expect($entry->fresh()->expected_amount)->toBe('50.00')
        ->and($occurrences[0]->fresh()->expected_amount)->toBe('83.45')
        ->and($occurrences[0]->fresh()->amount_confirmed_at)->not->toBeNull()
        ->and($occurrences[1]->fresh()->amount_confirmed_at)->toBeNull()
        ->and($occurrences[1]->fresh()->expected_amount)->toBe('50.00')
        ->and(Transaction::query()->count())->toBe(0);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.entry.is_amount_variable', true)
            ->where('recurringEntry.occurrences.0.expected_amount', 83.45)
            ->where('recurringEntry.occurrences.0.can_update_amount', true)
            ->where('recurringEntry.occurrences.1.expected_amount', 50)
            ->where('recurringEntry.summary.remaining_amount', 133.45)
        );
});

test('dashboard confirms a due variable occurrence without changing its plan', function (string $amount) {
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'is_amount_variable' => true,
        'auto_create_transaction' => true,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    expect(Transaction::query()->count())->toBe(1);
    $dashboardUrl = route('dashboard.data', ['year' => 2026, 'month' => 1]);
    $items = $this->actingAs($context['user'])->getJson($dashboardUrl)->assertSuccessful()->json('pending_actions.items');
    expect(collect($items)->firstWhere('transaction_uuid', $occurrence->fresh()->convertedTransaction->uuid)['requires_payment_confirmation'])->toBeTrue();
    $this->from(route('dashboard'))->patch(route('recurring-entries.occurrences.amount.update', [$entry->uuid, $occurrence->uuid]), [
        'amount' => $amount,
        'return_to_dashboard' => true,
    ])->assertRedirect(route('dashboard'));
    expect($occurrence->fresh()->amount_confirmed_at)->not->toBeNull()
        ->and($occurrence->fresh()->expected_amount)->toBe($amount)
        ->and($entry->fresh()->expected_amount)->toBe('50.00');
    $items = $this->getJson($dashboardUrl)->assertSuccessful()->json('pending_actions.items');
    expect(collect($items)->firstWhere('transaction_uuid', $occurrence->fresh()->convertedTransaction->uuid)['requires_payment_confirmation'])->toBeTrue();
    app(RecurringEntryLifecycleService::class)->synchronize($entry, CarbonImmutable::today());
    expect($occurrence->fresh()->convertedTransaction->amount)->toBe($amount);
})->with(['50.00', '83.45']);

test('a pending occurrence can override its date and account without changing the plan defaults', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);
    $occurrences = $entry->occurrences()->orderBy('sequence_number')->get();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.occurrences.override.update', [
            $entry->uuid,
            $occurrences[0]->uuid,
        ]), [
            'due_date' => '2026-01-20',
            'account_uuid' => $context['secondAccount']->uuid,
        ])
        ->assertRedirect(route('recurring-entries.show', [
            'recurringEntry' => $entry->uuid,
            'highlight' => $occurrences[0]->uuid,
        ]))
        ->assertSessionHas('success', __('transactions.flash.recurring_occurrence_updated'));

    expect($entry->fresh()->account_id)->toBe($context['account']->id)
        ->and($occurrences[0]->fresh()->due_date?->toDateString())->toBe('2026-01-20')
        ->and($occurrences[0]->fresh()->account_id)->toBe($context['secondAccount']->id)
        ->and($occurrences[1]->fresh()->due_date?->toDateString())->toBe('2026-02-15')
        ->and($occurrences[1]->fresh()->account_id)->toBeNull();

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.occurrences.0.has_date_override', true)
            ->where('recurringEntry.occurrences.0.resource.uuid', $context['secondAccount']->uuid)
            ->where('recurringEntry.occurrences.0.resource.is_overridden', true)
            ->where('recurringEntry.occurrences.1.resource.uuid', $context['account']->uuid)
            ->where('recurringEntry.occurrences.1.resource.is_overridden', false)
        );
});

test('conversion uses the occurrence date and account overrides and locks them afterwards', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.occurrences.override.update', [
            $entry->uuid,
            $occurrence->uuid,
        ]), [
            'due_date' => '2026-01-20',
            'account_uuid' => $context['secondAccount']->uuid,
        ])
        ->assertRedirect();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $transaction = $occurrence->fresh()->convertedTransaction;

    expect($transaction)->not->toBeNull()
        ->and($transaction->transaction_date?->toDateString())->toBe('2026-01-20')
        ->and($transaction->account_id)->toBe($context['secondAccount']->id);

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->patch(route('recurring-entries.occurrences.override.update', [
            $entry->uuid,
            $occurrence->uuid,
        ]), [
            'due_date' => '2026-01-21',
            'account_uuid' => $context['account']->uuid,
        ])
        ->assertSessionHasErrors('occurrence');

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.occurrences.0.can_update_occurrence', false)
        );
});

test('variable recurring plan synchronizes a converted transaction amount snapshot and account balance', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'is_amount_variable' => true,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $transaction = $occurrence->fresh()->convertedTransaction;

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe('50.00')
        ->and($context['account']->fresh()->current_balance)->toBe('950.00');

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.occurrences.amount.update', [
            $entry->uuid,
            $occurrence->uuid,
        ]), [
            'amount' => '72.30',
        ])
        ->assertSessionDoesntHaveErrors();

    $transaction->refresh();

    expect($occurrence->fresh()->expected_amount)->toBe('72.30')
        ->and($transaction->amount)->toBe('72.30')
        ->and($transaction->converted_base_amount)->toBe('72.30')
        ->and($transaction->exchange_rate)->toBe('1.00000000')
        ->and($transaction->updated_by_user_id)->toBe($context['user']->id)
        ->and($context['account']->fresh()->current_balance)->toBe('927.70');
});

test('fixed installment matched and refunded occurrences cannot change their amount', function () {
    $context = recurringManagementContext();
    $fixedEntry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $fixedOccurrence = $fixedEntry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.occurrences.amount.update', [
            $fixedEntry->uuid,
            $fixedOccurrence->uuid,
        ]), ['amount' => 70])
        ->assertSessionHasErrors('amount');

    expect($fixedOccurrence->fresh()->expected_amount)->toBe('50.00');

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.store'), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'expected_amount' => null,
            'total_amount' => 200,
            'installments_count' => 2,
            'is_amount_variable' => true,
        ]))
        ->assertSessionHasErrors('is_amount_variable');

    $matchedEntry = createManagedRecurringEntry($context, [
        'is_amount_variable' => true,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $matchedOccurrence = $matchedEntry->occurrences()->firstOrFail();
    $matchedTransaction = Transaction::query()->create([
        'user_id' => $context['user']->id,
        'account_id' => $context['account']->id,
        'transaction_date' => '2026-01-15',
        'value_date' => '2026-01-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 50,
        'currency' => 'EUR',
        'source_type' => 'manual',
        'status' => 'confirmed',
    ]);
    $matchedOccurrence->update(['matched_transaction_id' => $matchedTransaction->id]);

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.occurrences.amount.update', [
            $matchedEntry->uuid,
            $matchedOccurrence->uuid,
        ]), ['amount' => 80])
        ->assertSessionHasErrors('amount');

    expect($matchedOccurrence->fresh()->expected_amount)->toBe('50.00')
        ->and($matchedTransaction->fresh()->amount)->toBe('50.00');

    $refundedEntry = createManagedRecurringEntry($context, [
        'is_amount_variable' => true,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $refundedOccurrence = $refundedEntry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$refundedEntry->uuid, $refundedOccurrence->uuid]), []);
    $convertedTransaction = $refundedOccurrence->fresh()->convertedTransaction;
    $this->actingAs($context['user'])
        ->post(route('recurring-transactions.refund', $convertedTransaction->uuid));

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.occurrences.amount.update', [
            $refundedEntry->uuid,
            $refundedOccurrence->uuid,
        ]), ['amount' => 99])
        ->assertSessionHasErrors('amount');

    expect($refundedOccurrence->fresh()->expected_amount)->toBe('50.00')
        ->and($convertedTransaction->fresh()->amount)->toBe('50.00');
});

test('variable occurrence amount validation rejects invalid precision and values', function (mixed $amount) {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'is_amount_variable' => true,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.occurrences.amount.update', [
            $entry->uuid,
            $occurrence->uuid,
        ]), ['amount' => $amount])
        ->assertSessionHasErrors('amount');

    expect($occurrence->fresh()->expected_amount)->toBe('50.00');
})->with([
    'zero' => 0,
    'negative' => -1,
    'not numeric' => 'acqua',
    'more than two decimals' => '10.123',
    'over maximum' => '1000000000000.00',
]);

test('index recurring payload exposes linked transaction path with highlight query', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'confirm_future_date' => true,
        ])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $transactionUuid = $occurrence->fresh()->convertedTransaction?->uuid;

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlyCalendar.days.0.occurrences.0.converted_transaction.show_url', function (?string $url) use ($transactionUuid): bool {
                return is_string($url)
                    && str_contains($url, '/transactions/2026/1')
                    && str_contains($url, 'highlight='.$transactionUuid)
                    && str_contains($url, 'source=recurring');
            })
        );
});

test('update blocks structural changes when the plan has converted occurrences but allows non destructive fields', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 2,
    ]);

    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($context, [
            'title' => 'Updated visible title',
            'account_id' => $context['secondAccount']->id,
        ]))
        ->assertSessionHasErrors('entry');

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($context, [
            'title' => 'Updated visible title',
            'description' => 'Updated description',
            'notes' => 'Updated notes',
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 2,
            'auto_create_transaction' => true,
            'is_amount_variable' => true,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $entry->refresh();

    expect($entry->title)->toBe('Updated visible title')
        ->and($entry->account_id)->toBe($context['account']->id)
        ->and($entry->auto_create_transaction)->toBeTrue()
        ->and($entry->is_amount_variable)->toBeTrue();
});

test('update changes only future installment base amounts after confirmation while preserving linked transactions', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-20'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 25.90,
        'installments_count' => 3,
        'expected_amount' => null,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 3,
    ]);
    $firstOccurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $firstOccurrence->uuid]), [])
        ->assertRedirect();

    $transaction = $firstOccurrence->fresh()->convertedTransaction()->firstOrFail();
    $transactionId = $transaction->id;
    $transactionDate = $transaction->transaction_date->toDateString();
    $accountId = $transaction->account_id;
    $categoryId = $transaction->category_id;
    $description = $transaction->description;

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'total_amount' => 29.90,
            'installments_count' => 3,
            'expected_amount' => null,
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 3,
            'confirm_installment_recalculation' => true,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($entry->fresh()->total_amount)->toBe('29.90')
        ->and($entry->occurrences()->orderBy('sequence_number')->pluck('expected_amount')->all())
        ->toBe(['8.63', '9.96', '9.98']);

    $transaction->refresh();

    expect($transaction->id)->toBe($transactionId)
        ->and($transaction->recurring_entry_occurrence_id)->toBe($firstOccurrence->id)
        ->and($transaction->amount)->toBe('8.63')
        ->and($transaction->transaction_date->toDateString())->toBe($transactionDate)
        ->and($transaction->account_id)->toBe($accountId)
        ->and($transaction->category_id)->toBe($categoryId)
        ->and($transaction->description)->toBe($description);
});

test('update requires confirmation before recalculating a converted installment plan', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 25.90,
        'installments_count' => 3,
        'expected_amount' => null,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 3,
    ]);
    $firstOccurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $firstOccurrence->uuid]), [])
        ->assertRedirect();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($context, [
            'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
            'total_amount' => 29.90,
            'installments_count' => 3,
            'expected_amount' => null,
            'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
            'occurrences_limit' => 3,
        ]))
        ->assertSessionHasErrors('total_amount');

    expect($entry->fresh()->total_amount)->toBe('25.90')
        ->and($entry->occurrences()->orderBy('sequence_number')->pluck('expected_amount')->all())
        ->toBe(['8.63', '8.63', '8.64']);
});

test('installment distribution updates occurrences and linked transactions while preserving the plan total', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 199.99,
        'installments_count' => 5,
        'expected_amount' => null,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 5,
    ]);
    $occurrences = $entry->occurrences()->orderBy('sequence_number')->get();

    foreach ($occurrences->take(2) as $occurrence) {
        $this->actingAs($context['user'])
            ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
            ->assertRedirect();
    }

    $transactions = $occurrences->take(2)->map(fn ($occurrence) => $occurrence->fresh()->convertedTransaction()->firstOrFail());
    $transactionIds = $transactions->pluck('id')->all();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.installment-distribution.update', $entry->uuid), [
            'installments' => $occurrences->map(fn ($occurrence, int $index): array => [
                'uuid' => $occurrence->uuid,
                'amount' => $index < 4 ? 40 : 39.99,
            ])->all(),
            'confirm' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($entry->fresh()->total_amount)->toBe('199.99')
        ->and($entry->occurrences()->orderBy('sequence_number')->pluck('expected_amount')->all())
        ->toBe(['40.00', '40.00', '40.00', '40.00', '39.99'])
        ->and($transactions->map(fn ($transaction) => $transaction->fresh()->id)->all())->toBe($transactionIds)
        ->and($transactions->map(fn ($transaction) => $transaction->fresh()->amount)->all())->toBe(['40.00', '40.00']);
});

test('installment distribution rejects a total different from the plan total', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => RecurringEntryTypeEnum::INSTALLMENT->value,
        'total_amount' => 199.99,
        'installments_count' => 5,
        'expected_amount' => null,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 5,
    ]);

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.installment-distribution.update', $entry->uuid), [
            'installments' => $entry->occurrences()->orderBy('sequence_number')->get()->map(fn ($occurrence): array => ['uuid' => $occurrence->uuid, 'amount' => 40])->all(),
            'confirm' => true,
        ])
        ->assertSessionHasErrors('installments');
});

test('installment pause resume and cancel retain the existing behavior', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => 'installment', 'total_amount' => 150, 'installments_count' => 3,
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 3,
    ]);

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.pause', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::PAUSED)
        ->and($entry->fresh()->is_active)->toBeFalse();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.resume', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::ACTIVE)
        ->and($entry->fresh()->is_active)->toBeTrue();

    $this->actingAs($context['user'])
        ->patch(route('recurring-entries.cancel', $entry->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect($entry->fresh()->status)->toBe(RecurringEntryStatusEnum::CANCELLED)
        ->and($entry->fresh()->next_occurrence_date)->toBeNull()
        ->and($entry->fresh()->occurrences->pluck('status')->map->value->all())->toBe(['cancelled', 'cancelled', 'cancelled']);
});

test('convert occurrence creates a scheduled transaction and does not duplicate on second conversion', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'transaction_date' => '2026-01-17',
        ])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(recurringConversionDestination($occurrence));

    expect(Transaction::query()->where('recurring_entry_occurrence_id', $occurrence->id)->count())->toBe(1)
        ->and($occurrence->fresh()->convertedTransaction?->kind)->toBe(TransactionKindEnum::SCHEDULED);
});

test('future occurrence conversion requires explicit confirmation', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();
    $occurrence->forceFill([
        'expected_date' => '2099-05-15',
        'due_date' => null,
    ])->save();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(recurringConversionDestination($occurrence))
        ->assertSessionHasErrors('occurrence');

    expect($occurrence->fresh()->converted_transaction_id)->toBeNull();
});

test('undo conversion permanently removes the scheduled transaction and resets the occurrence', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'confirm_future_date' => true,
        ])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $transaction = $occurrence->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->delete(route('recurring-entries.occurrences.undo-conversion', [$entry->uuid, $occurrence->uuid]))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHas('success', __('transactions.flash.recurring_conversion_undone'));

    expect(Transaction::withTrashed()->where('uuid', $transaction->uuid)->exists())->toBeFalse()
        ->and($occurrence->fresh()->converted_transaction_id)->toBeNull()
        ->and($occurrence->fresh()->status->value)->toBe('pending');
});

test('undo conversion is blocked when the scheduled transaction has already been refunded', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
            'confirm_future_date' => true,
        ])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $scheduledTransaction = $occurrence->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $scheduledTransaction->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->delete(route('recurring-entries.occurrences.undo-conversion', [$entry->uuid, $occurrence->uuid]))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHasErrors('occurrence');

    expect($occurrence->fresh()->converted_transaction_id)->not->toBeNull();
});

test('refund from recurring is available only for the latest converted occurrence', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 3,
    ]);
    $occurrences = $entry->occurrences()->orderBy('sequence_number')->get();

    foreach ($occurrences as $occurrence) {
        $this->actingAs($context['user'])
            ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
                'confirm_future_date' => true,
            ])
            ->assertRedirect(recurringConversionDestination($occurrence));
    }

    $firstTransaction = $occurrences[0]->fresh()->convertedTransaction;
    $lastTransaction = $occurrences[2]->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $firstTransaction->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHasErrors('transaction');

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $lastTransaction->uuid))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionDoesntHaveErrors();
});

test('undo conversion is blocked for occurrences linked to non scheduled transactions', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $manualTransaction = Transaction::query()->create([
        'user_id' => $context['user']->id,
        'account_id' => $context['account']->id,
        'category_id' => $context['category']->id,
        'tracked_item_id' => $context['trackedItem']->id,
        'transaction_date' => '2026-01-15',
        'value_date' => '2026-01-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 120,
        'currency' => 'EUR',
        'description' => 'Manual override',
        'source_type' => 'manual',
        'status' => 'confirmed',
        'recurring_entry_occurrence_id' => $occurrence->id,
    ]);

    $occurrence->update([
        'converted_transaction_id' => $manualTransaction->id,
        'status' => 'completed',
    ]);

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->delete(route('recurring-entries.occurrences.undo-conversion', [$entry->uuid, $occurrence->uuid]))
        ->assertRedirect(route('recurring-entries.show', $entry->uuid))
        ->assertSessionHasErrors('occurrence');

    expect(Transaction::query()->whereKey($manualTransaction->id)->exists())->toBeTrue()
        ->and($occurrence->fresh()->converted_transaction_id)->toBe($manualTransaction->id);
});

test('refund endpoint refunds eligible transactions and blocks forbidden cases', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'end_mode' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
        'occurrences_limit' => 1,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();

    $this->actingAs($context['user'])
        ->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [])
        ->assertRedirect(recurringConversionDestination($occurrence));

    $scheduledTransaction = $occurrence->fresh()->convertedTransaction;

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $scheduledTransaction->uuid), [])
        ->assertRedirect(route('recurring-entries.show', $entry->uuid));

    expect(Transaction::query()->where('refunded_transaction_id', $scheduledTransaction->id)->count())->toBe(1);

    $this->actingAs($context['user'])
        ->get(route('recurring-entries.show', $entry->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntry.occurrences.0.converted_transaction.is_refunded', true)
            ->where('recurringEntry.occurrences.0.status', 'pending')
            ->where('recurringEntry.occurrences.0.remaining_amount', 50)
            ->where('recurringEntry.occurrences.0.can_convert', true)
        );

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $scheduledTransaction->uuid), [])
        ->assertSessionHasErrors('transaction');

    $refundTransaction = Transaction::query()->where('refunded_transaction_id', $scheduledTransaction->id)->firstOrFail();

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $refundTransaction->uuid), [])
        ->assertSessionHasErrors('transaction');

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

    $this->actingAs($context['user'])
        ->from(route('recurring-entries.show', $entry->uuid))
        ->post(route('recurring-transactions.refund', $openingBalance->uuid), [])
        ->assertSessionHasErrors('transaction');
});

function recurringManagementContext(): array
{
    $user = User::factory()->create();

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'payment_account',
    ], [
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Primary account',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => '1000.00',
        'current_balance' => '1000.00',
        'is_manual' => true,
        'is_active' => true,
    ]);

    $secondAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Secondary account',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => '500.00',
        'current_balance' => '500.00',
        'is_manual' => true,
        'is_active' => true,
    ]);

    $scope = Scope::query()->create([
        'user_id' => $user->id,
        'name' => 'Family',
        'type' => 'household',
        'color' => '#000000',
        'is_active' => true,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Rent',
        'slug' => 'rent-'.fake()->unique()->slug(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $merchant = Merchant::query()->create([
        'user_id' => $user->id,
        'name' => 'Landlord',
        'normalized_name' => 'landlord',
        'is_active' => true,
    ]);

    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Home',
        'slug' => 'home-'.fake()->unique()->slug(),
        'type' => 'asset',
        'is_active' => true,
    ]);

    return compact('user', 'account', 'secondAccount', 'scope', 'category', 'merchant', 'trackedItem');
}

test('a single recurring or installment occurrence can be skipped and restored without changing its plan', function (string $entryType) {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, $entryType === 'installment' ? [
        'entry_type' => 'installment', 'total_amount' => 150, 'installments_count' => 3,
    ] : []);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $parent = $entry->getAttributes();
    $others = $entry->occurrences()->whereKeyNot($occurrence->id)->get()->map->getAttributes()->all();
    $parameters = [$entry->uuid, $occurrence->uuid];
    $this->actingAs($context['user'])->from(route('recurring-entries.index'))
        ->patch(route('recurring-entries.occurrences.skip', $parameters))->assertRedirect(route('recurring-entries.index'));
    expect($occurrence->fresh()->status->value)->toBe('skipped')
        ->and($entry->fresh()->getAttributes())->toBe($parent)
        ->and($entry->occurrences()->whereKeyNot($occurrence->id)->get()->map->getAttributes()->all())->toBe($others);
    $this->get(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertInertia(fn (Assert $page) => $page
        ->where('monthlyCalendar.days', fn ($days) => collect($days)->flatMap(fn ($day) => $day['occurrences'])->contains(fn ($item) => $item['uuid'] === $occurrence->uuid && $item['status'] === 'skipped' && ! $item['can_convert'] && $item['can_restore'])));
    $this->post(route('recurring-entries.occurrences.convert', $parameters), ['confirm_future_date' => true])->assertSessionHasErrors('occurrence');
    expect(Transaction::query()->count())->toBe(0);
    $entry->forceFill(['auto_create_transaction' => true])->save();
    app(RecurringEntryLifecycleService::class)->synchronize($entry, CarbonImmutable::parse('2026-01-15'));
    expect($occurrence->fresh()->status->value)->toBe('skipped')->and(Transaction::query()->count())->toBe(0);
    app(RecurringEntryLifecycleService::class)->synchronize($entry, CarbonImmutable::parse('2026-02-15'));
    expect($entry->occurrences()->where('sequence_number', 2)->firstOrFail()->status->value)->toBe('completed');
    $this->patch(route('recurring-entries.occurrences.restore', $parameters))->assertSessionHasNoErrors();
    expect($occurrence->fresh()->status->value)->toBe('pending');
    $this->post(route('recurring-entries.occurrences.convert', $parameters), ['confirm_future_date' => true])->assertSessionHasNoErrors();
    expect($occurrence->fresh()->status->value)->toBe('completed');
    $this->patch(route('recurring-entries.occurrences.skip', $parameters))->assertSessionHasErrors('occurrence');
    $this->patch(route('recurring-entries.occurrences.restore', $parameters))->assertSessionHasErrors('occurrence');
})->with(['recurring', 'installment']);

test('skip and restore reject another users occurrence', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context);
    $occurrence = $entry->occurrences()->firstOrFail();
    $this->actingAs(User::factory()->create());
    foreach (['skip', 'restore'] as $action) {
        $this->patch(route('recurring-entries.occurrences.'.$action, [$entry->uuid, $occurrence->uuid]))->assertNotFound();
    }
    expect($occurrence->fresh()->status->value)->toBe('pending');
});

test('temporary pause skips the next three periods and resumes without changing the plan', function (string $frequency, string $nextDate) {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, [
        'recurrence_type' => $frequency,
        'recurrence_rule' => $frequency === 'yearly' ? ['mode' => 'month_day', 'month' => 1, 'day' => 15] : ($frequency === 'weekly' ? ['weekdays' => ['thu']] : ['mode' => 'day_of_month', 'day' => 15]),
    ]);
    $before = $entry->fresh()->getAttributes();
    $dates = $entry->occurrences()->orderBy('sequence_number')->pluck('expected_date')->map->toDateString()->all();
    $this->actingAs($context['user'])->patch(route('recurring-entries.pause', $entry->uuid), ['periods' => 3])->assertSessionHasNoErrors();
    expect($entry->fresh()->getAttributes())->toBe($before)
        ->and($entry->occurrences()->orderBy('sequence_number')->limit(4)->pluck('status')->map->value->all())->toBe(['skipped', 'skipped', 'skipped', 'pending'])
        ->and($entry->occurrences()->orderBy('sequence_number')->pluck('expected_date')->map->toDateString()->take(count($dates))->all())->toBe($dates);
    $first = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $this->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $first->uuid]))->assertSessionHasErrors('occurrence');
    $entry->forceFill(['auto_create_transaction' => true])->save();
    app(RecurringEntryLifecycleService::class)->synchronize($entry, CarbonImmutable::parse($nextDate));
    expect($entry->occurrences()->orderBy('sequence_number')->limit(4)->pluck('status')->map->value->all())->toBe(['skipped', 'skipped', 'skipped', 'completed'])
        ->and(Transaction::query()->count())->toBe(1);
})->with([
    ['daily', '2026-01-18'], ['weekly', '2026-02-05'], ['monthly', '2026-04-15'], ['yearly', '2029-01-15'],
]);

test('temporary pause selects occurrences without an aggregate query incompatible with PostgreSQL row locks', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($context['user'])->patch(route('recurring-entries.pause', $entry->uuid), ['periods' => 3])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'recurring_entry_occurrences') && str_contains(strtolower($sql), 'count(*)')))->toBeEmpty();
    expect($entry->occurrences()->orderBy('sequence_number')->limit(4)->pluck('status')->map->value->all())
        ->toBe(['skipped', 'skipped', 'skipped', 'pending']);
});

test('temporary pause materializes missing future occurrences and does not exceed a finite plan', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context);
    $this->actingAs($context['user'])->patch(route('recurring-entries.pause', $entry->uuid), ['periods' => 15])->assertSessionHasNoErrors();
    expect($entry->occurrences()->where('status', 'skipped')->count())->toBe(15);
    $finite = createManagedRecurringEntry($context, ['end_mode' => 'after_occurrences', 'occurrences_limit' => 2]);
    $this->patch(route('recurring-entries.pause', $finite->uuid), ['periods' => 3])->assertSessionHasErrors('periods');
    expect($finite->occurrences()->where('status', 'skipped')->count())->toBe(0);
});

test('temporary pause validates quantity and rejects another users plan', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context);
    $this->actingAs($context['user']);
    foreach ([null, 0, -1, 1.5, 1001] as $periods) {
        $this->patch(route('recurring-entries.pause', $entry->uuid), ['periods' => $periods])->assertSessionHasErrors('periods');
    }
    $this->actingAs(User::factory()->create())->patch(route('recurring-entries.pause', $entry->uuid), ['periods' => 3])->assertNotFound();
    expect($entry->occurrences()->where('status', 'skipped')->count())->toBe(0);
});

test('scheduled automation excludes skipped occurrences and still posts following occurrences', function () {
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    app(RecurringEntryManagementService::class)->skipOccurrence($occurrence);
    $entry->forceFill(['auto_create_transaction' => true])->save();
    $this->travelTo(CarbonImmutable::parse('2026-02-15'));
    app(App\Services\Transactions\RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect($occurrence->fresh()->status->value)->toBe('skipped')
        ->and($occurrence->fresh()->converted_transaction_id)->toBeNull()
        ->and($entry->occurrences()->where('sequence_number', 2)->firstOrFail()->status->value)->toBe('completed');
});

test('a skipped shared occurrence cannot generate a ledger record', function () {
    $context = recurringManagementContext();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id, 'name' => 'Mario', 'slug' => 'mario-skip',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    $entry = createManagedRecurringEntry($context, [
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'counterparty',
        'shared_expense_my_share' => 25, 'shared_expense_counterparty_share' => 25,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();
    app(RecurringEntryManagementService::class)->skipOccurrence($occurrence);
    expect(fn () => app(SharedExpenseOccurrencePostingService::class)->post($occurrence))
        ->toThrow(ValidationException::class);
    expect($occurrence->fresh()->sharedExpenseLedgerEntry)->toBeNull()->and(Transaction::query()->count())->toBe(0);
});

function recurringManagementPayload(array $context, array $overrides = []): array
{
    return [
        'title' => 'Rent recurring entry',
        'account_id' => $context['account']->id,
        'scope_id' => $context['scope']->id,
        'category_id' => $context['category']->id,
        'tracked_item_id' => $context['trackedItem']->id,
        'merchant_id' => $context['merchant']->id,
        'description' => 'Recurring rent payment',
        'notes' => 'Recurring management notes',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 15],
        'start_date' => '2026-01-15',
        'end_date' => null,
        'end_mode' => RecurringEndModeEnum::NEVER->value,
        'occurrences_limit' => null,
        'expected_amount' => 50,
        'is_amount_variable' => false,
        'total_amount' => null,
        'installments_count' => null,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        'reminder_days_before' => [],
        ...$overrides,
    ];
}

function createManagedRecurringEntry(array $context, array $overrides = []): RecurringEntry
{
    return app(RecurringEntryManagementService::class)->store(
        $context['user'],
        recurringManagementPayload($context, $overrides)
    );
}

function shareRecurringAccount(
    Account $account,
    User $user,
    AccountMembershipRoleEnum $role,
    AccountMembershipStatusEnum $status = AccountMembershipStatusEnum::ACTIVE,
): AccountMembership {
    return AccountMembership::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'household_id' => null,
        'role' => $role->value,
        'status' => $status->value,
        'permissions' => null,
        'granted_by_user_id' => $account->user_id,
        'source' => MembershipSourceEnum::DIRECT->value,
        'joined_at' => now(),
    ]);
}

test('enabling automatic posting on a plan with recorded history immediately registers due siblings', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-15'));
    $context = recurringManagementContext();
    $entry = createManagedRecurringEntry($context, ['start_date' => '2026-01-15', 'is_amount_variable' => true, 'auto_create_transaction' => false]);
    $first = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    app(RecurringEntryPostingService::class)->post($first);
    app(RecurringEntryManagementService::class)->update($context['user'], $entry, ['auto_create_transaction' => true]);
    expect(Transaction::count())->toBe(3);
    app(RecurringEntryManagementService::class)->update($context['user'], $entry, ['notes' => 'Resynchronize']);
    expect(Transaction::count())->toBe(3)->and($first->fresh()->converted_transaction_id)->not->toBeNull();
});

test('cancelled automatic plans reactivate with overdue dates manual and future dates automatic', function (string $type) {
    $this->travelTo(CarbonImmutable::parse('2026-01-01'));
    $c = recurringManagementContext();
    $entry = createManagedRecurringEntry($c, [
        'entry_type' => $type, 'auto_create_transaction' => true,
        ...($type === 'installment' ? ['total_amount' => 300, 'installments_count' => 6, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 6] : []),
    ]);
    app(RecurringEntryManagementService::class)->cancel($entry);
    $this->actingAs($c['user'])->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (Assert $page) => $page->where('recurringEntry.actions.can_resume', true));
    $this->travelTo(CarbonImmutable::parse('2026-03-20'));
    $this->patch(route('recurring-entries.resume', $entry->uuid))->assertSessionHasNoErrors();
    app(RecurringEntryLifecycleService::class)->synchronize($entry->fresh());
    app(App\Services\Transactions\RecurringEntryLifecycleService::class)->runAutomationPipeline();
    $past = $entry->occurrences()->whereDate('due_date', '<', today())->get();
    expect($past)->toHaveCount(3)->and($past->every(fn ($row) => $row->automatic_posting_blocked_at !== null && $row->converted_transaction_id === null && $row->status->value === 'pending'))->toBeTrue()->and(Transaction::count())->toBe(0);
    $this->patch(route('recurring-entries.resume', $entry->uuid))->assertSessionHasNoErrors();
    $this->travelTo(CarbonImmutable::parse('2026-04-15'));
    app(App\Services\Transactions\RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(1)->and($entry->occurrences()->where('sequence_number', 4)->sole()->converted_transaction_id)->not->toBeNull();
    app(RecurringEntryPostingService::class)->post($past->first(), $c['user']);
    expect(Transaction::count())->toBe(2)->and($past->first()->fresh()->converted_transaction_id)->not->toBeNull();
})->with(['recurring', 'installment']);

test('reactivation materializes missed dates and preserves skipped and recorded history', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-01'));
    $c = recurringManagementContext();
    $entry = createManagedRecurringEntry($c, ['start_date' => '2026-01-02', 'recurrence_type' => 'daily', 'recurrence_rule' => null, 'auto_create_transaction' => true]);
    $first = $entry->occurrences()->where('sequence_number', 1)->sole();
    app(RecurringEntryPostingService::class)->post($first, $c['user']);
    $skipped = $entry->occurrences()->where('sequence_number', 2)->sole();
    app(RecurringEntryManagementService::class)->skipOccurrence($skipped);
    app(RecurringEntryManagementService::class)->cancel($entry);
    $this->travelTo(CarbonImmutable::parse('2026-03-01'));
    app(RecurringEntryManagementService::class)->resume($entry);
    $missing = $entry->occurrences()->whereDate('due_date', '2026-02-20')->sole();
    expect($missing->automatic_posting_blocked_at)->not->toBeNull()->and($first->fresh()->converted_transaction_id)->not->toBeNull()->and($skipped->fresh()->status->value)->toBe('skipped');
    app(RecurringEntryLifecycleService::class)->synchronize($entry->fresh());
    expect($missing->fresh()->converted_transaction_id)->toBeNull()->and(Transaction::count())->toBe(2);
    $this->actingAs(User::factory()->create())->patch(route('recurring-entries.resume', $entry->uuid))->assertNotFound();
});

test('external shared payment settles the obligation without cash and can be reversed', function (string $type) {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $context = recurringManagementContext();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id, 'name' => 'Emanuela', 'slug' => 'external-emanuela',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    $entry = createManagedRecurringEntry($context, [
        'entry_type' => $type,
        'expected_amount' => $type === 'installment' ? null : 50,
        'total_amount' => $type === 'installment' ? 100 : null,
        'installments_count' => $type === 'installment' ? 2 : null,
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'counterparty',
        'shared_expense_my_share' => $type === 'installment' ? 50 : 25,
        'shared_expense_counterparty_share' => $type === 'installment' ? 50 : 25,
    ]);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $balance = $context['account']->fresh()->current_balance;
    expect($occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists())->toBeTrue();
    $this->actingAs($context['user'])->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]))->assertSessionHasNoErrors();
    $ledger = $occurrence->fresh()->sharedExpenseLedgerEntry;
    expect($occurrence->fresh()->status->value)->toBe('completed')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and($occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists())->toBeFalse()
        ->and(Transaction::withTrashed()->count())->toBe(0)
        ->and($context['account']->fresh()->current_balance)->toBe($balance);
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('recurringEntry.occurrences.0.external_payment.payer', 'Emanuela')
        ->where('recurringEntry.occurrences.0.external_payment.settled', true)
        ->where('recurringEntry.occurrences.0.remaining_amount', 0)
        ->where('recurringEntry.summary.paid_count', 1)
        ->where('recurringEntry.summary.settled_occurrences', 1)
        ->where('recurringEntry.summary.paid_amount', 50));
    $this->get(route('shared-expenses.index', ['year' => 2026, 'month' => 1, 'entry' => $ledger->uuid]))->assertInertia(fn (Assert $page) => $page
        ->has('entries', 1)->where('entries.0.uuid', $ledger->uuid)
        ->where('entries.0.origin_url', route('recurring-entries.show', ['recurringEntry' => $entry, 'highlight' => $occurrence->uuid])));
    $dashboard = app(DashboardService::class)->build($context['user'], 2026, 1);
    expect($dashboard['recurring_summary']['pending_count'])->toBe(0)
        ->and($dashboard['recurring_summary']['overdue_count'])->toBe(0);
    $sheet = app(MonthlyTransactionSheetService::class)->build($context['user'], 2026, 1);
    expect($sheet['planned_occurrences'])->toBeEmpty()
        ->and($sheet['totals']['actual_expense_raw'])->toEqual(0);
    $this->get(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertInertia(fn (Assert $page) => $page
        ->where('monthlyCalendar.summary.pending_count', 0)
        ->where('monthlyCalendar.days.0.occurrences.0.external_payment.settled', true));
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry);
    expect($entry->occurrences()->whereKey($occurrence->id)->sole()->sharedExpenseLedgerEntry->id)->toBe($ledger->id);
    $ledger->update(['total_cents' => 2000, 'my_share_cents' => 1000, 'counterparty_share_cents' => 1000]);
    expect($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(3000)
        ->and($occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists())->toBeTrue();
    $ledger->update(['total_cents' => 5000, 'my_share_cents' => 2500, 'counterparty_share_cents' => 2500]);
    expect($occurrence->fresh()->status->value)->toBe('completed');
    app(UndoRecurringOccurrenceConversionService::class)->undo($occurrence->fresh());
    expect($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(5000)
        ->and($occurrence->fresh()->automatic_posting_blocked_at)->not->toBeNull()
        ->and($occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists())->toBeTrue()
        ->and(Transaction::withTrashed()->count())->toBe(0);
    app(App\Services\Transactions\RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->sharedExpenseLedgerEntry)->toBeNull();
})->with(['recurring', 'installment']);

test('changing a scheduled payment to counterparty retains the occurrence and restores cash', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $context = recurringManagementContext();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id, 'name' => 'Emanuela', 'slug' => 'changed-payer',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    $entry = createManagedRecurringEntry($context);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $balance = $context['account']->fresh()->current_balance;
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, $context['user']);
    $ledger = app(TransactionMutationService::class)->update($context['user'], $transaction, [
        'account_id' => $context['account']->id, 'category_id' => $context['category']->id,
        'amount' => '50.00', 'transaction_date' => '2026-01-15', 'type_key' => 'expense',
        'description' => 'Pagata da Emanuela', 'notes' => null, 'is_shared_expense' => true,
        'shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => 'counterparty',
        'shared_expense_my_share' => '25.00', 'shared_expense_counterparty_share' => '25.00',
    ]);
    expect($ledger->recurring_entry_occurrence_id)->toBe($occurrence->id)
        ->and($occurrence->fresh()->status->value)->toBe('completed')
        ->and($occurrence->fresh()->converted_transaction_id)->toBeNull()
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and(Transaction::withTrashed()->count())->toBe(0)
        ->and($context['account']->fresh()->current_balance)->toBe($balance);
    $occurrence->fresh()->update(['expected_amount' => '60.00']);
    expect($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(1000);
    $occurrence->fresh()->update(['expected_amount' => '50.00']);
    $ledger->update(['paid_by' => 'self']);
    expect($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(5000);
    $ledger->update(['paid_by' => 'counterparty']);
    expect($occurrence->fresh()->status->value)->toBe('completed');
    $ledger->update(['status' => 'cancelled']);
    expect(app(SharedExpenseSettlementService::class)->balanceCents($context['user'], $counterparty))->toBe(0);
    expect($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(5000);
    $ledger->update(['status' => 'recorded']);
    $occurrence->forceFill(['status' => 'pending'])->save();
    $migration = require database_path('migrations/2026_10_13_090848_reconcile_external_recurring_settlements.php');
    $migration->up();
    expect($occurrence->fresh()->status->value)->toBe('completed');
    $ledger->delete();
    expect($occurrence->fresh()->status->value)->toBe('pending');
});

test('a shared occurrence chooses its actual payer independently of the plan default', function (string $payer) {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $context = recurringManagementContext();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id, 'name' => 'Mario Rossi', 'slug' => 'actual-payer',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    $entry = createManagedRecurringEntry($context, [
        'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => $payer === 'self' ? 'counterparty' : 'self',
        'shared_expense_my_share' => 15, 'shared_expense_counterparty_share' => 35,
    ]);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $balance = $context['account']->fresh()->current_balance;
    $this->actingAs($context['user'])->post(route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]), [
        'shared_expense_paid_by' => $payer, 'amount' => '50.00', 'transaction_date' => '2026-01-14',
    ])->assertSessionHasNoErrors();
    expect($occurrence->fresh()->status->value)->toBe('completed')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0);
    if ($payer === 'counterparty') {
        $ledger = $occurrence->fresh()->sharedExpenseLedgerEntry;
        expect($ledger->total_cents)->toBe(5000)
            ->and($ledger->my_share_cents)->toBe(1500)
            ->and($ledger->entry_date->toDateString())->toBe('2026-01-14')
            ->and(Transaction::withTrashed()->count())->toBe(0)
            ->and($context['account']->fresh()->current_balance)->toBe($balance);
        $this->delete(route('shared-expenses.ledger.destroy', $ledger->uuid))->assertRedirect();
        expect($occurrence->fresh()->status->value)->toBe('pending')
            ->and($occurrence->fresh()->remainingPaymentCents())->toBe(5000)
            ->and($occurrence->fresh()->automatic_posting_blocked_at)->not->toBeNull()
            ->and(Transaction::withTrashed()->count())->toBe(0);
    } else {
        expect(Transaction::query()->sole()->shared_expense_paid_by)->toBe('self')
            ->and($occurrence->fresh()->sharedExpenseLedgerEntry)->toBeNull();
    }
})->with([
    'paid by the user' => ['self'],
    'paid by the reference' => ['counterparty'],
]);

test('partial counterparty payments accumulate on the linked ledger until the occurrence is settled', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $context = recurringManagementContext();
    $counterparty = TrackedItem::query()->create([
        'user_id' => $context['user']->id, 'name' => 'Mario', 'slug' => 'partial-payer',
        'is_active' => true, 'is_shared_expense_counterpart' => true,
    ]);
    $entry = createManagedRecurringEntry($context, [
        'shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => 'self',
        'shared_expense_my_share' => 25, 'shared_expense_counterparty_share' => 25,
    ]);
    $occurrence = $entry->occurrences()->firstOrFail();
    $route = route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]);
    $this->actingAs($context['user'])->post($route, ['shared_expense_paid_by' => 'counterparty', 'amount' => '10.00'])->assertSessionHasNoErrors();
    $ledger = $occurrence->fresh()->sharedExpenseLedgerEntry;
    expect($occurrence->fresh()->status->value)->toBe('pending')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(4000)
        ->and($ledger->my_share_cents)->toBe(500);
    $this->post($route, ['shared_expense_paid_by' => 'counterparty', 'amount' => '41.00'])->assertSessionHasErrors('occurrence');
    $this->post($route, ['shared_expense_paid_by' => 'counterparty', 'amount' => '40.00'])->assertSessionHasNoErrors();
    expect($occurrence->fresh()->status->value)->toBe('completed')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)
        ->and($ledger->fresh()->total_cents)->toBe(5000)
        ->and(SharedExpenseLedgerEntry::query()->count())->toBe(1)
        ->and(Transaction::withTrashed()->count())->toBe(0);
    $this->actingAs(User::factory()->create())->delete(route('shared-expenses.ledger.destroy', $ledger->uuid))->assertNotFound();
    $this->assertModelExists($ledger);
});

test('existing plans can enable and disable sharing without rewriting settled history', function (bool $withHistory) {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $c = recurringManagementContext();
    $entry = createManagedRecurringEntry($c, ['expected_amount' => 32]);
    $first = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $oldTransaction = $withHistory ? app(RecurringEntryPostingService::class)->post($first, $c['user']) : null;
    $snapshot = $first->fresh()->getAttributes();
    $counterparty = TrackedItem::create(['user_id' => $c['user']->id, 'name' => 'Mario Rossi', 'slug' => 'mario-rossi', 'type' => 'person', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $this->actingAs($c['user'])->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($c, [
        'expected_amount' => 32, 'is_shared_expense' => true, 'shared_expense_counterparty_uuid' => $counterparty->uuid,
        'shared_expense_paid_by' => 'self', 'shared_expense_my_share' => '16.00', 'shared_expense_counterparty_share' => '16.00',
    ]))->assertSessionHasNoErrors();
    expect($entry->fresh()->shared_expense_counterparty_id)->toBe($counterparty->id)
        ->and($entry->fresh()->shared_expense_my_share_cents)->toBe(1600)
        ->and($entry->occurrences()->where('sequence_number', 2)->sole()->shared_expense_paid_by)->toBe('self');
    if ($withHistory) {
        expect($first->fresh()->getAttributes())->toBe($snapshot)->and($oldTransaction->fresh()->shared_expense_counterparty_id)->toBeNull();
    }
    $this->patch(route('recurring-entries.update', $entry->uuid), recurringManagementPayload($c, [
        'expected_amount' => 32, 'is_shared_expense' => false, 'shared_expense_counterparty_uuid' => null,
        'shared_expense_paid_by' => null, 'shared_expense_my_share' => null, 'shared_expense_counterparty_share' => null,
    ]))->assertSessionHasNoErrors();
    expect($entry->fresh()->shared_expense_counterparty_id)->toBeNull()->and($entry->fresh()->shared_expense_paid_by)->toBeNull()
        ->and($entry->fresh()->shared_expense_my_share_cents)->toBeNull()
        ->and($entry->occurrences()->where('sequence_number', 2)->sole()->shared_expense_paid_by)->toBeNull();
    if ($withHistory) {
        expect($first->fresh()->getAttributes())->toBe($snapshot);
    }
})->with([false, true]);

test('refund reopens the correct residual and accepts a replacement while retaining monetary history', function (bool $mixed) {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $c = recurringManagementContext();
    $counterparty = TrackedItem::create(['user_id' => $c['user']->id, 'name' => 'Mario Rossi', 'slug' => 'mario-rossi', 'type' => 'person', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $entry = createManagedRecurringEntry($c, ['expected_amount' => 32, 'shared_expense_counterparty_id' => $counterparty->id,
        'shared_expense_paid_by' => 'self', 'shared_expense_my_share' => 16, 'shared_expense_counterparty_share' => 16]);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $route = route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]);
    $this->actingAs($c['user']);
    if ($mixed) {
        $this->post($route, ['amount' => 16, 'shared_expense_paid_by' => 'counterparty'])->assertSessionHasNoErrors();
    }
    $amount = $mixed ? 16 : 32;
    $this->post($route, ['amount' => $amount, 'shared_expense_paid_by' => 'self'])->assertSessionHasNoErrors();
    $original = $occurrence->fresh()->convertedTransaction;
    expect($original->amount)->toBe(number_format($amount, 2, '.', ''))->and($occurrence->fresh()->remainingPaymentCents())->toBe(0);
    $this->post(route('recurring-transactions.refund', $original->uuid), [])->assertSessionHasNoErrors();
    expect($occurrence->fresh()->status->value)->toBe('pending')->and($occurrence->fresh()->remainingPaymentCents())->toBe($amount * 100)
        ->and($occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists())->toBeTrue();
    $this->getJson(route('recurring-entries.show', $entry->uuid))->assertJsonPath('recurringEntry.occurrences.0.can_convert', true)
        ->assertJsonPath('recurringEntry.occurrences.0.remaining_amount', $amount);
    $this->post($route, ['amount' => $amount, 'shared_expense_paid_by' => 'self', 'transaction_date' => '2026-01-16', 'account_uuid' => $c['account']->uuid])->assertSessionHasNoErrors();
    expect(Transaction::count())->toBe(3);
    $replacement = $occurrence->fresh()->convertedTransaction;
    expect($replacement->id)->not->toBe($original->id)->and($replacement->transaction_date->toDateString())->toBe('2026-01-16')
        ->and($occurrence->fresh()->remainingPaymentCents())->toBe(0)->and($occurrence->fresh()->status->value)->toBe('completed')
        ->and($original->fresh()->refundTransaction)->not->toBeNull()->and($occurrence->fresh()->externalPaymentCents())->toBe($mixed ? 1600 : 0);
    expect(fn () => app(TransactionRefundService::class)->undo($original->fresh()->refundTransaction))->toThrow(ValidationException::class);
    $this->getJson(route('recurring-entries.show', $entry->uuid))->assertJsonPath('recurringEntry.occurrences.0.payment_history.0.uuid', $original->uuid);
    expect(app(SharedExpenseSettlementService::class)->balanceCents($c['user'], $counterparty))->toBe($mixed ? 0 : 1600);
    if ($mixed) {
        $ledger = $occurrence->fresh()->sharedExpenseLedgerEntry;
        $this->delete(route('shared-expenses.ledger.destroy', $ledger->uuid))->assertRedirect();
        expect($replacement->fresh())->not->toBeNull()->and($occurrence->fresh()->remainingPaymentCents())->toBe(1600)
            ->and($occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists())->toBeTrue();
    }
})->with([false, true]);

test('partial refunds and transaction state changes keep only active settlements in the residual', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $c = recurringManagementContext();
    $entry = createManagedRecurringEntry($c, ['expected_amount' => 32]);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $original = app(RecurringEntryPostingService::class)->post($occurrence, $c['user']);
    $refund = app(TransactionRefundService::class)->refund($original, ['amount' => 10]);
    expect($occurrence->fresh()->remainingPaymentCents())->toBe(1000)->and($occurrence->fresh()->status->value)->toBe('pending');
    $replacement = app(RecurringEntryPostingService::class)->post($occurrence->fresh(), $c['user'], ['amount' => 10, 'transaction_date' => '2026-01-14']);
    expect($occurrence->fresh()->remainingPaymentCents())->toBe(0)->and($original->fresh()->refundTransaction->id)->toBe($refund->id);
    $replacement->update(['status' => 'ignored']);
    expect($occurrence->fresh()->remainingPaymentCents())->toBe(1000)->and($occurrence->fresh()->status->value)->toBe('pending');
    $replacement->update(['status' => 'confirmed']);
    expect($occurrence->fresh()->remainingPaymentCents())->toBe(0)->and($occurrence->fresh()->status->value)->toBe('completed');
});

test('replacement payment rejects inaccessible accounts and accepts a different editable account', function () {
    $c = recurringManagementContext();
    $entry = createManagedRecurringEntry($c, ['expected_amount' => 32]);
    $occurrence = $entry->occurrences()->orderBy('sequence_number')->firstOrFail();
    $original = app(RecurringEntryPostingService::class)->post($occurrence, $c['user']);
    app(TransactionRefundService::class)->refund($original);
    $other = $c['account']->replicate(['uuid']);
    $other->name = 'Other account';
    $other->save();
    $forbidden = $c['account']->replicate(['uuid']);
    $forbidden->user_id = User::factory()->create()->id;
    $forbidden->save();
    $route = route('recurring-entries.occurrences.convert', [$entry->uuid, $occurrence->uuid]);
    $this->actingAs($c['user'])->post($route, ['account_uuid' => $forbidden->uuid])->assertSessionHasErrors('account_uuid');
    expect(Transaction::count())->toBe(2);
    $this->post($route, ['account_uuid' => $other->uuid])->assertSessionHasNoErrors();
    expect($occurrence->fresh()->convertedTransaction->account_id)->toBe($other->id)->and($occurrence->fresh()->remainingPaymentCents())->toBe(0);
});

test('unrelated updates preserve custom sharing ratios on a plan with settled history', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $c = recurringManagementContext();
    $person = TrackedItem::create(['user_id' => $c['user']->id, 'name' => 'Mario', 'slug' => 'mario', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $entry = createManagedRecurringEntry($c, ['shared_expense_counterparty_id' => $person->id, 'shared_expense_paid_by' => 'self',
        'shared_expense_my_share' => 10, 'shared_expense_counterparty_share' => 40]);
    app(RecurringEntryPostingService::class)->post($entry->occurrences()->orderBy('sequence_number')->firstOrFail());
    app(RecurringEntryManagementService::class)->update($c['user'], $entry, ['title' => 'Updated title']);
    expect($entry->fresh()->shared_expense_my_share_cents)->toBe(1000)->and($entry->fresh()->shared_expense_counterparty_share_cents)->toBe(4000);
    app(RecurringEntryManagementService::class)->update($c['user'], $entry, ['expected_amount' => 60]);
    expect($entry->fresh()->shared_expense_my_share_cents)->toBe(1200)->and($entry->fresh()->shared_expense_counterparty_share_cents)->toBe(4800);
});

function recurringConversionDestination(RecurringEntryOccurrence $occurrence): string
{
    $transaction = $occurrence->fresh()->convertedTransaction;

    return $transaction === null
        ? route('recurring-entries.show', $occurrence->recurringEntry->uuid)
        : route('transactions.show', ['year' => $transaction->transaction_date->year, 'month' => $transaction->transaction_date->month, 'highlight' => $transaction->uuid]);
}
