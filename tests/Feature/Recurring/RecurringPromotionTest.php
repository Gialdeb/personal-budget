<?php

use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use App\Services\Recurring\RecurringEntryAmountResolver;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPeriodService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\RecurringEntryValidatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Validation\ValidationException;

function updatePromotionWithConsent(User $actor, RecurringEntry $entry, array $data): RecurringEntry
{
    $normalized = app(RecurringEntryValidatorService::class)->validate($actor, [...$entry->getAttributes(), ...$data], $entry);
    $candidate = clone $entry;
    $candidate->fill($normalized);
    $impact = app(RecurringEntryOccurrenceGeneratorService::class)->promotionImpact($candidate, $entry);

    return app(RecurringEntryManagementService::class)->update($actor, $entry, [...$data, 'promotion_confirmation' => $impact['confirmation']]);
}

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));
    $this->actor = User::factory()->create();
    foreach ([2026, 2027, 2028, 2029] as $year) {
        UserYear::create(['user_id' => $this->actor->id, 'year' => $year, 'is_closed' => false]);
    }
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $category = Category::create(['user_id' => $this->actor->id, 'name' => 'Subscription', 'slug' => 'subscription', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->promotion = ['amount' => '5.00', 'normal_amount' => '20.00', 'duration' => 'payments', 'payments' => 2, 'outcome' => 'normal'];
    $this->payload = ['title' => 'Promotional subscription', 'account_id' => $this->account->id, 'category_id' => $category->id, 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 1], 'start_date' => '2026-10-01', 'end_mode' => 'never', 'expected_amount' => 20, 'is_amount_variable' => false, 'auto_generate_occurrences' => true, 'auto_create_transaction' => false, 'is_active' => true, 'reminder_days_before' => [], 'promotion' => $this->promotion];
    $this->actingAs($this->actor);
});

test('promotion counts payments from the original calendar for every recurrence frequency', function (string $frequency, int $interval, array $rule, string $boundary): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'recurrence_type' => $frequency, 'recurrence_interval' => $interval, 'recurrence_rule' => $rule]);
    expect($entry->promotion['ends_before'])->toBe($boundary);
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry, CarbonImmutable::parse($boundary));
    $rows = $entry->occurrences()->orderBy('sequence_number')->limit(3)->get();
    expect($rows->pluck('expected_amount')->all())->toBe(['5.00', '5.00', '20.00'])
        ->and($rows->last()->expected_date->toDateString())->toBe($boundary)
        ->and($entry->amountChanges()->count())->toBe(0)
        ->and($entry->price_conditions)->toBeNull();
})->with([
    'daily interval' => ['daily', 2, [], '2026-10-05'],
    'weekly interval' => ['weekly', 2, ['weekdays' => ['thu']], '2026-10-29'],
    'multiple weekdays' => ['weekly', 1, ['weekdays' => ['mon', 'thu']], '2026-10-08'],
    'monthly' => ['monthly', 1, ['mode' => 'day_of_month', 'day' => 1], '2026-12-01'],
    'quarterly' => ['quarterly', 1, ['mode' => 'day_of_month', 'day' => 1], '2027-04-01'],
    'yearly' => ['yearly', 1, ['mode' => 'month_day', 'month' => 10, 'day' => 1], '2028-10-01'],
]);

test('trial precedes first promotional payment and preview matches generated prices', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'trial_duration' => 7, 'trial_unit' => 'days', 'promotion' => [...$this->promotion, 'duration' => 'first_payment']]);
    $rows = app(RecurringEntryOccurrenceGeneratorService::class)->previewScheduleWithinRange($entry, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-12-31'));
    expect($entry->promotion['starts_on'])->toBe('2026-10-08')
        ->and($entry->promotion['ends_before'])->toBe('2026-11-08')
        ->and($rows->pluck('expected_date')->all())->toBe(['2026-10-08', '2026-11-08', '2026-12-08'])
        ->and($rows->pluck('expected_amount')->all())->toBe(['5.00', '20.00', '20.00']);
    foreach ($rows as $row) {
        expect($entry->occurrences()->where('sequence_number', $row['sequence_number'])->sole()->expected_amount)->toBe($row['expected_amount']);
    }
    expect(Transaction::count())->toBe(0)->and($this->account->fresh()->current_balance)->toBe('1000.00');
});

test('date duration includes the boundary day and outcome does not create guaranteed descriptive conditions', function (string $outcome, array $amounts, bool $review): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'promotion' => [...$this->promotion, 'duration' => 'until_date', 'until_date' => '2026-11-01', 'outcome' => $outcome]]);
    expect($entry->occurrences()->orderBy('sequence_number')->limit(3)->pluck('expected_amount')->all())->toBe($amounts)
        ->and(app(RecurringEntryAmountResolver::class)->needsReview($entry, CarbonImmutable::parse('2026-11-01')))->toBeFalse()
        ->and(app(RecurringEntryAmountResolver::class)->needsReview($entry, CarbonImmutable::parse('2026-12-01')))->toBe($review)
        ->and($entry->price_conditions)->toBeNull()
        ->and($entry->amountChanges()->count())->toBe(0);
})->with([
    'normal' => ['normal', ['5.00', '5.00', '20.00'], false],
    'keep' => ['keep', ['5.00', '5.00', '5.00'], false],
    'unknown' => ['unknown', ['5.00', '5.00', '5.00'], true],
]);

test('promotion updates preserve paid completed confirmed and customized amounts and history', function (): void {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, $this->payload);
    $paid = $entry->occurrences()->where('sequence_number', 1)->sole();
    app(RecurringEntryPostingService::class)->post($paid, $this->actor);
    $transaction = $paid->fresh()->convertedTransaction;
    $transactionSnapshot = $transaction->getAttributes();
    $paidSnapshot = $paid->fresh()->getAttributes();
    $custom = $entry->occurrences()->where('sequence_number', 2)->sole();
    $custom->update(['expected_amount' => 7]);
    $confirmed = $entry->occurrences()->where('sequence_number', 3)->sole();
    $confirmed->forceFill(['amount_confirmed_at' => now()])->save();
    $completed = $entry->occurrences()->where('sequence_number', 4)->sole();
    $completed->update(['status' => 'completed']);
    $protected = collect([$custom, $confirmed, $completed])->mapWithKeys(fn ($row): array => [$row->id => $row->getAttributes()]);
    $conditions = ['lock_mode' => 'until', 'locked_until' => '2028-01-01'];
    $entry->forceFill(['price_conditions' => $conditions])->save();
    $balance = $this->account->fresh()->current_balance;
    $entry = updatePromotionWithConsent($this->actor, $entry->fresh(), [...$this->payload, 'promotion' => [...$this->promotion, 'payments' => 6, 'amount' => 4]]);
    expect($paid->fresh()->getAttributes())->toBe($paidSnapshot)
        ->and($transaction->fresh()->getAttributes())->toBe($transactionSnapshot)
        ->and($this->account->fresh()->current_balance)->toBe($balance)
        ->and($entry->occurrences()->where('sequence_number', 5)->sole()->expected_amount)->toBe('4.00')
        ->and($entry->occurrences()->where('sequence_number', 7)->sole()->expected_amount)->toBe('20.00')
        ->and($entry->price_conditions)->toBe($conditions)
        ->and($entry->amountChanges()->count())->toBe(0);
    foreach ([$custom, $confirmed, $completed] as $row) {
        expect($row->fresh()->getAttributes())->toBe($protected[$row->id]);
    }
});

test('adding removing and omitting promotions on existing plans changes only mutable future prices', function (): void {
    $service = app(RecurringEntryManagementService::class);
    $withoutPromotion = $this->payload;
    unset($withoutPromotion['promotion']);
    $entry = $service->store($this->actor, $withoutPromotion);
    $first = $entry->occurrences()->where('sequence_number', 1)->sole();
    $firstSnapshot = $first->getAttributes();
    $entry = updatePromotionWithConsent($this->actor, $entry, $this->payload);
    expect($entry->occurrences()->where('sequence_number', 2)->sole()->expected_amount)->toBe('5.00')
        ->and($first->fresh()->getAttributes())->toBe($firstSnapshot);
    $promotion = $entry->promotion;
    $entry = updatePromotionWithConsent($this->actor, $entry, [...$withoutPromotion, 'title' => 'Renamed']);
    expect($entry->promotion)->toBe($promotion)
        ->and($entry->occurrences()->where('sequence_number', 2)->sole()->expected_amount)->toBe('5.00');
    $entry = updatePromotionWithConsent($this->actor, $entry, [...$withoutPromotion, 'promotion' => null]);
    expect($entry->promotion)->toBeNull()
        ->and($entry->occurrences()->where('sequence_number', 2)->sole()->expected_amount)->toBe('20.00')
        ->and($first->fresh()->getAttributes())->toBe($firstSnapshot);
});

test('promotion is not restarted by pause resume or repeated reactivation with a different frequency', function (): void {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, [...$this->payload, 'trial_duration' => 7, 'trial_unit' => 'days']);
    $promotion = $entry->promotion;
    $service->cancel($entry);
    $this->travelTo(CarbonImmutable::parse('2027-01-01'));
    $restart = [...$this->payload, 'start_date' => '2027-01-01', 'recurrence_type' => 'weekly', 'recurrence_rule' => ['weekdays' => ['fri']]];
    $entry = app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), $restart);
    $rows = app(RecurringEntryOccurrenceGeneratorService::class)->previewScheduleWithinRange($entry, CarbonImmutable::parse('2027-01-01'), CarbonImmutable::parse('2027-02-01'));
    expect($entry->promotion)->toBe($promotion)
        ->and($rows->pluck('expected_amount')->unique()->all())->toBe(['20.00']);
    $count = $entry->occurrences()->count();
    app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry, $restart);
    expect($entry->occurrences()->count())->toBe($count);
    $entry = updatePromotionWithConsent($this->actor, $entry->fresh(), [...$restart, 'start_date' => '2026-10-01', 'promotion' => [...$this->promotion, 'outcome' => 'unknown']]);
    expect($entry->promotion['origin'])->toBe($promotion['origin'])
        ->and($entry->promotion['ends_before'])->toBe($promotion['ends_before']);
});

test('manual price changes override promotions and remain separate from automatic transitions', function (): void {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, $this->payload);
    $entry = updatePromotionWithConsent($this->actor, $entry, [...$this->payload, 'expected_amount' => 30]);
    $change = $entry->amountChanges()->sole();
    expect($change->new_amount)->toBe('30.00')
        ->and($change->previous_amount)->toBe('5.00')
        ->and($change->effective_from->toDateString())->toBe('2026-11-01')
        ->and($entry->occurrences()->where('sequence_number', 2)->sole()->expected_amount)->toBe('30.00')
        ->and($entry->occurrences()->where('sequence_number', 3)->sole()->expected_amount)->toBe('30.00');
    $snapshot = $change->getAttributes();
    updatePromotionWithConsent($this->actor, $entry, [...$this->payload, 'expected_amount' => 30, 'promotion' => [...$this->promotion, 'amount' => 3]]);
    expect($change->fresh()->getAttributes())->toBe($snapshot)->and($entry->amountChanges()->count())->toBe(1);
});

test('HTTP validation rejects malformed or forged promotion configurations', function (array $invalid, string $field): void {
    $this->post(route('recurring-entries.store'), [...$this->payload, 'promotion' => $invalid])->assertSessionHasErrors($field);
    expect(RecurringEntry::count())->toBe(0)->and(Transaction::count())->toBe(0);
})->with([
    'empty' => [[], 'promotion'],
    'zero price' => [['amount' => 0, 'normal_amount' => 20, 'duration' => 'first_payment', 'outcome' => 'normal'], 'promotion.amount'],
    'too many decimals' => [['amount' => '5.123', 'normal_amount' => 20, 'duration' => 'first_payment', 'outcome' => 'normal'], 'promotion.amount'],
    'missing count' => [['amount' => 5, 'normal_amount' => 20, 'duration' => 'payments', 'outcome' => 'normal'], 'promotion.payments'],
    'negative count' => [['amount' => 5, 'normal_amount' => 20, 'duration' => 'payments', 'payments' => -1, 'outcome' => 'normal'], 'promotion.payments'],
    'invalid outcome' => [['amount' => 5, 'normal_amount' => 20, 'duration' => 'first_payment', 'outcome' => 'guaranteed'], 'promotion.outcome'],
    'forged origin' => [['amount' => 5, 'normal_amount' => 20, 'duration' => 'first_payment', 'outcome' => 'normal', 'origin' => ['start_date' => '2030-01-01']], 'promotion'],
    'end before origin' => [['amount' => 5, 'normal_amount' => 20, 'duration' => 'until_date', 'until_date' => '2026-09-30', 'outcome' => 'normal'], 'promotion.until_date'],
]);

test('domain validation rejects promotions on installments and invalid data without HTTP', function (): void {
    $service = app(RecurringEntryManagementService::class);
    expect(fn () => $service->store($this->actor, [...$this->payload, 'entry_type' => 'installment', 'total_amount' => 100, 'installments_count' => 2]))->toThrow(ValidationException::class)
        ->and(fn () => $service->store($this->actor, [...$this->payload, 'promotion' => [...$this->promotion, 'normal_amount' => 1]]))->toThrow(ValidationException::class)
        ->and(RecurringEntry::count())->toBe(0);
});

test('HTTP creation and modification persist server anchors and expose configuration without changing descriptive conditions', function (): void {
    $this->post(route('recurring-entries.store'), $this->payload)->assertSessionHasNoErrors()->assertRedirect();
    $entry = RecurringEntry::sole();
    $anchor = $entry->promotion['origin'];
    $data = [...$this->payload, 'promotion' => [...$this->promotion, 'outcome' => 'keep']];
    $preview = $this->postJson(route('recurring-entries.promotion-preview'), [...$data, 'recurring_entry_uuid' => $entry->uuid])->assertOk();
    $this->patch(route('recurring-entries.update', $entry->uuid), [...$data, 'promotion_confirmation' => $preview->json('confirmation')])->assertSessionHasNoErrors()->assertRedirect();
    expect($entry->fresh()->promotion['origin'])->toBe($anchor)
        ->and($entry->fresh()->promotion['outcome'])->toBe('keep')
        ->and($entry->occurrences()->where('sequence_number', 4)->sole()->expected_amount)->toBe('5.00')
        ->and($entry->price_conditions)->toBeNull();
    $this->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()
        ->assertJsonPath('recurringEntry.promotion.amount', '5.00')
        ->assertJsonPath('recurringEntry.promotion.outcome', 'keep');
});

test('editing the trial before any payment still places the promotion after the trial', function (): void {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, [...$this->payload, 'trial_duration' => 7, 'trial_unit' => 'days', 'promotion' => [...$this->promotion, 'duration' => 'first_payment']]);
    $entry = updatePromotionWithConsent($this->actor, $entry, [...$this->payload, 'trial_duration' => 2, 'trial_unit' => 'months', 'promotion' => [...$this->promotion, 'duration' => 'first_payment']]);
    expect($entry->promotion['starts_on'])->toBe('2026-12-01')
        ->and($entry->promotion['ends_before'])->toBe('2027-01-01');
    $rows = app(RecurringEntryOccurrenceGeneratorService::class)->previewScheduleWithinRange($entry, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2027-01-31'));
    expect($rows->pluck('expected_amount')->all())->toBe(['5.00', '20.00']);
});

test('pause and legacy resume do not renew a first-payment promotion', function (): void {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, [...$this->payload, 'promotion' => [...$this->promotion, 'duration' => 'first_payment']]);
    $promotion = $entry->promotion;
    $service->pause($entry);
    $this->travelTo(CarbonImmutable::parse('2026-12-01'));
    $entry = $service->resume($entry->fresh());
    expect($entry->promotion)->toBe($promotion)
        ->and($entry->occurrences()->where('sequence_number', 3)->sole()->expected_amount)->toBe('20.00');
});

test('reactivation within the original promotional window does not allocate a fresh payment count', function (): void {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, [...$this->payload, 'promotion' => [...$this->promotion, 'payments' => 3]]);
    $promotion = $entry->promotion;
    $service->cancel($entry);
    $this->travelTo(CarbonImmutable::parse('2026-11-01'));
    $entry = app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), [...$this->payload, 'start_date' => '2026-11-01']);
    $generator = app(RecurringEntryOccurrenceGeneratorService::class);
    $rows = $generator->previewScheduleWithinRange($entry, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2027-01-31'));
    expect($entry->promotion)->toBe($promotion)
        ->and($rows->pluck('expected_amount')->all())->toBe(['5.00', '5.00', '20.00']);
    foreach ($rows as $row) {
        expect($entry->occurrences()->where('sequence_number', $row['sequence_number'])->sole()->expected_amount)->toBe($row['expected_amount']);
    }
});

test('partial payments and their allocations survive promotional modifications unchanged', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-01'));
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, $this->payload);
    $occurrence = $entry->occurrences()->where('sequence_number', 2)->sole();
    $transaction = Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'direction' => 'expense', 'amount' => 2, 'currency' => 'EUR', 'transaction_date' => '2026-10-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    $allocation = app(AllocateOccurrencePaymentService::class)->allocate($occurrence, $transaction, $this->actor, '2.00');
    $snapshots = [$occurrence->fresh()->getAttributes(), $transaction->fresh()->getAttributes(), $allocation->fresh()->getAttributes()];
    updatePromotionWithConsent($this->actor, $entry, [...$this->payload, 'promotion' => [...$this->promotion, 'amount' => 3, 'payments' => 4]]);
    expect([$occurrence->fresh()->getAttributes(), $transaction->fresh()->getAttributes(), $allocation->fresh()->getAttributes()])->toBe($snapshots);
});

test('posting uses resolved promotional and normal prices without creating descriptive or manual changes', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'promotion' => [...$this->promotion, 'duration' => 'first_payment', 'normal_amount' => 25]]);
    $first = $entry->occurrences()->where('sequence_number', 1)->sole();
    $payment = app(RecurringEntryPostingService::class)->post($first, $this->actor);
    $snapshot = $payment->getAttributes();
    $this->travelTo(CarbonImmutable::parse('2026-11-01'));
    $second = $entry->occurrences()->where('sequence_number', 2)->sole();
    $renewal = app(RecurringEntryPostingService::class)->post($second, $this->actor);
    expect($payment->fresh()->getAttributes())->toBe($snapshot)
        ->and($payment->amount)->toBe('5.00')
        ->and($renewal->amount)->toBe('25.00')
        ->and($entry->amountChanges()->count())->toBe(0)
        ->and($entry->fresh()->price_conditions)->toBeNull();
});

test('promotional origin follows the first real calendar slot rather than a nominal start before that slot', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'start_date' => '2026-10-15', 'promotion' => [...$this->promotion, 'duration' => 'first_payment']]);
    expect($entry->promotion['starts_on'])->toBe('2026-11-01')
        ->and($entry->promotion['ends_before'])->toBe('2026-12-01')
        ->and($entry->occurrences()->orderBy('sequence_number')->limit(2)->pluck('expected_amount')->all())->toBe(['5.00', '20.00']);
});

test('promotional previews are read only and manual history resolves an unknown renewal without a guarantee', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'promotion' => [...$this->promotion, 'outcome' => 'unknown', 'duration' => 'first_payment']]);
    $entry->amountChanges()->create(['previous_amount' => 5, 'new_amount' => 12, 'currency' => 'EUR', 'effective_from' => '2026-12-01']);
    $before = [$entry->fresh()->getAttributes(), $entry->occurrences()->get()->map->getAttributes()->all(), $entry->amountChanges()->get()->map->getAttributes()->all()];
    $rows = app(RecurringEntryOccurrenceGeneratorService::class)->previewScheduleWithinRange($entry->fresh(), CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2027-01-31'));
    expect($rows->pluck('expected_amount')->all())->toBe(['5.00', '12.00', '12.00'])
        ->and(app(RecurringEntryAmountResolver::class)->needsReview($entry, CarbonImmutable::parse('2026-11-01'), $entry->amountChanges()->get()))->toBeTrue()
        ->and(app(RecurringEntryAmountResolver::class)->needsReview($entry, CarbonImmutable::parse('2026-12-01'), $entry->amountChanges()->get()))->toBeFalse()
        ->and([$entry->fresh()->getAttributes(), $entry->occurrences()->get()->map->getAttributes()->all(), $entry->amountChanges()->get()->map->getAttributes()->all()])->toBe($before);
});

test('shared promotional schedules reuse existing allocations and retain customized shares on reactivation', function (): void {
    $counterparty = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Partner', 'slug' => 'promotion-partner', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $payload = [...$this->payload, 'is_shared_expense' => true, 'shared_expense_counterparty_id' => $counterparty->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share' => '12.00', 'shared_expense_counterparty_share' => '8.00', 'promotion' => [...$this->promotion, 'payments' => 3]];
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, $payload);
    $untouched = $entry->occurrences()->where('sequence_number', 2)->sole();
    expect($untouched->expected_amount)->toBe('5.00')
        ->and($untouched->shared_expense_my_share_cents)->toBe(300)
        ->and($untouched->shared_expense_counterparty_share_cents)->toBe(200);
    $custom = $entry->occurrences()->where('sequence_number', 3)->sole();
    $custom->forceFill(['shared_expense_my_share_cents' => 200, 'shared_expense_counterparty_share_cents' => 300])->save();
    $customSnapshot = $custom->getAttributes();
    $service->cancel($entry);
    expect($untouched->fresh()->status->value)->toBe('cancelled')
        ->and($custom->fresh()->getAttributes())->toBe($customSnapshot);
    $this->travelTo(CarbonImmutable::parse('2026-11-01'));
    $entry = app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), [...$payload, 'start_date' => '2026-11-01']);
    expect($custom->fresh()->getAttributes())->toBe($customSnapshot);
    $new = $entry->occurrences()->whereDate('expected_date', '2026-11-01')->where('status', 'pending')->sole();
    expect($new->expected_amount)->toBe('5.00')
        ->and($new->shared_expense_my_share_cents)->toBe(300)
        ->and($new->shared_expense_counterparty_share_cents)->toBe(200);
});

test('promotion preview is read only and requires explicit matching confirmation for the selected editable rows', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $protected = $entry->occurrences()->where('sequence_number', 2)->sole();
    $protected->forceFill(['amount_confirmed_at' => now()])->save();
    $data = [...$this->payload, 'promotion' => [...$this->promotion, 'payments' => 6, 'amount' => 3]];
    $before = [$entry->fresh()->getAttributes(), $entry->occurrences()->get()->map->getAttributes()->all(), Transaction::count(), $entry->amountChanges()->count()];
    $preview = $this->postJson(route('recurring-entries.promotion-preview'), [...$data, 'recurring_entry_uuid' => $entry->uuid])->assertOk();
    expect($preview->json('changes'))->not->toBeEmpty()
        ->and(collect($preview->json('changes'))->pluck('uuid')->all())->not->toContain($protected->uuid)
        ->and([$entry->fresh()->getAttributes(), $entry->occurrences()->get()->map->getAttributes()->all(), Transaction::count(), $entry->amountChanges()->count()])->toBe($before);
    $this->patch(route('recurring-entries.update', $entry->uuid), $data)->assertSessionHasErrors('promotion_confirmation');
    $this->patch(route('recurring-entries.update', $entry->uuid), [...$data, 'promotion_confirmation' => str_repeat('0', 64)])->assertSessionHasErrors('promotion_confirmation');
    expect([$entry->fresh()->getAttributes(), $entry->occurrences()->get()->map->getAttributes()->all(), Transaction::count(), $entry->amountChanges()->count()])->toBe($before);
    $this->patch(route('recurring-entries.update', $entry->uuid), [...$data, 'promotion_confirmation' => $preview->json('confirmation')])->assertSessionHasNoErrors();
    foreach ($preview->json('changes') as $row) {
        expect((float) $entry->occurrences()->where('uuid', $row['uuid'])->sole()->expected_amount)->toBe((float) $row['amount']);
    }
    expect($protected->fresh()->getAttributes())->toBe($before[1][1]);
});

test('confirmation becomes invalid when a selected occurrence or the proposed promotion changes', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $data = [...$this->payload, 'promotion' => [...$this->promotion, 'payments' => 6, 'amount' => 3]];
    $token = $this->postJson(route('recurring-entries.promotion-preview'), [...$data, 'recurring_entry_uuid' => $entry->uuid])->assertOk()->json('confirmation');
    $this->patch(route('recurring-entries.update', $entry->uuid), [...$data, 'promotion' => [...$data['promotion'], 'amount' => 2], 'promotion_confirmation' => $token])->assertSessionHasErrors('promotion_confirmation');
    $entry->occurrences()->where('sequence_number', 3)->sole()->forceFill(['amount_confirmed_at' => now()])->save();
    $this->patch(route('recurring-entries.update', $entry->uuid), [...$data, 'promotion_confirmation' => $token])->assertSessionHasErrors('promotion_confirmation');
    expect($entry->fresh()->promotion['amount'])->toBe('5.00')->and(Transaction::count())->toBe(0);
});

test('promotion previews deny other users and validate creation without persisting a plan', function (): void {
    $this->postJson(route('recurring-entries.promotion-preview'), $this->payload)->assertOk()->assertJsonPath('promotion.amount', '5.00');
    expect(RecurringEntry::count())->toBe(0)->and(Transaction::count())->toBe(0);
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $other = User::factory()->create();
    $this->actingAs($other)->postJson(route('recurring-entries.promotion-preview'), [...$this->payload, 'recurring_entry_uuid' => $entry->uuid])->assertNotFound();
});

test('promotion form options include the saved configuration for expenses and income', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Riferimento esistente', 'slug' => 'reference-picker-existing', 'is_active' => true]);
    $reference->compatibleCategories()->attach($this->payload['category_id']);
    $entry->update(['tracked_item_id' => $reference->id]);
    $expense = $this->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()->json();
    $category = Category::create(['user_id' => $this->actor->id, 'name' => 'Income', 'slug' => 'income-promotion', 'direction_type' => 'income', 'group_type' => 'income', 'is_active' => true, 'is_selectable' => true]);
    $incoming = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'category_id' => $category->id, 'direction' => 'income']);
    $reference->compatibleCategories()->attach($category->id);
    $incoming->update(['tracked_item_id' => $reference->id]);
    $income = $this->getJson(route('recurring-entries.show', $incoming->uuid))->assertOk()->json();
    expect($expense['recurringEntry']['entry']['promotion']['amount'])->toBe('5.00')
        ->and($income['recurringEntry']['entry']['promotion']['amount'])->toBe('5.00');
    if (getenv('PROMOTION_BROWSER_FIXTURE')) {
        file_put_contents(getenv('PROMOTION_BROWSER_FIXTURE'), json_encode(['expense' => $expense, 'income' => $income], JSON_THROW_ON_ERROR));
    }
});

test('promotion preview leaves provisional reference creation and website changes for the main submission', function (): void {
    $context = ['account_uuid' => $this->account->uuid, 'category_uuid' => Category::findOrFail($this->payload['category_id'])->uuid, 'type_key' => 'expense'];
    $token = $this->postJson(route('transactions.tracked-items.store'), [...$context, 'draft' => true, 'name' => 'Provisional provider', 'website_url' => 'https://example.com'])
        ->assertOk()->json('item.value');
    $before = TrackedItem::count();
    $data = [...$this->payload, ...$context, 'tracked_item_uuid' => $token];
    $this->postJson(route('recurring-entries.promotion-preview'), $data)->assertOk();
    expect(TrackedItem::count())->toBe($before)->and(RecurringEntry::count())->toBe(0);
    $this->post(route('recurring-entries.store'), $data)->assertSessionHasNoErrors();
    $reference = TrackedItem::where('name', 'Provisional provider')->sole();
    expect($reference->website_url)->toBe('https://example.com/')
        ->and(RecurringEntry::sole()->tracked_item_id)->toBe($reference->id);
    $this->postJson(route('recurring-entries.promotion-preview'), $data)->assertOk();
    expect(TrackedItem::count())->toBe($before + 1);
    $this->post(route('recurring-entries.store'), [...$data, 'title' => 'Second plan'])->assertSessionHasNoErrors();
    expect(TrackedItem::count())->toBe($before + 1);
    $changedToken = $this->postJson(route('transactions.tracked-items.store'), [...$context, 'draft' => true, 'tracked_item_uuid' => $reference->uuid, 'website_url' => 'https://example.org'])
        ->assertOk()->json('item.value');
    $changedData = [...$data, 'tracked_item_uuid' => $changedToken];
    $this->postJson(route('recurring-entries.promotion-preview'), $changedData)->assertOk();
    expect($reference->fresh()->website_url)->toBe('https://example.com/');
    $entry = RecurringEntry::where('title', $this->payload['title'])->sole();
    $this->patch(route('recurring-entries.update', $entry->uuid), $changedData)->assertSessionHasNoErrors();
    expect($reference->fresh()->website_url)->toBe('https://example.org/')
        ->and(TrackedItem::count())->toBe($before + 1);
});
