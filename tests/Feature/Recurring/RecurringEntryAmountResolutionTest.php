<?php

use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryAmountChange;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\RecurringEntryAmountResolver;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));
    $this->actor = User::factory()->create();
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $category = Category::create(['user_id' => $this->actor->id, 'name' => 'Subscription', 'slug' => 'subscription', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->payload = ['title' => 'Amount resolution', 'account_id' => $this->account->id, 'category_id' => $category->id, 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 1], 'start_date' => '2026-10-01', 'end_mode' => 'never', 'expected_amount' => 10, 'is_amount_variable' => false, 'auto_generate_occurrences' => true, 'auto_create_transaction' => false, 'is_active' => true];
});

test('amount resolution keeps ordinary pricing separate from manual history and descriptive conditions', function (): void {
    $entry = new RecurringEntry(['expected_amount' => '29.99']);
    $entry->price_conditions = ['new_amount' => 1, 'effective_from' => '2020-01-01', 'change_scope' => 'personal'];
    $changes = collect([
        new RecurringEntryAmountChange(['previous_amount' => 10, 'new_amount' => 20, 'effective_from' => '2026-11-01']),
        new RecurringEntryAmountChange(['previous_amount' => 20, 'new_amount' => 15, 'effective_from' => '2026-12-01']),
        new RecurringEntryAmountChange(['previous_amount' => 15, 'new_amount' => 17, 'effective_from' => '2026-12-01']),
    ]);
    $resolver = app(RecurringEntryAmountResolver::class);
    $snapshot = $entry->getAttributes();
    foreach (['2026-10-31' => '10.00', '2026-11-01' => '20.00', '2026-11-30' => '20.00', '2026-12-01' => '17.00'] as $date => $expected) {
        expect($resolver->resolve($entry, CarbonImmutable::parse($date), $changes))->toBe($expected)
            ->and($resolver->resolve($entry, CarbonImmutable::parse($date)))->toBe('29.99');
    }
    expect($resolver->resolve($entry, CarbonImmutable::parse('2026-10-01'), collect()))->toBe('29.99')
        ->and($entry->getAttributes())->toBe($snapshot);
});

test('planned preview matches generation after a trial and does not write economic data', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'trial_duration' => 7, 'trial_unit' => 'days']);
    $entrySnapshot = $entry->getAttributes();
    $occurrences = $entry->occurrences()->get()->map->getAttributes()->all();
    $historyCount = $entry->amountChanges()->count();
    DB::enableQueryLog();
    $preview = app(RecurringEntryOccurrenceGeneratorService::class)->previewScheduleWithinRange($entry, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-12-31'));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($preview->pluck('expected_date')->all())->toBe(['2026-10-08', '2026-11-08', '2026-12-08'])
        ->and($preview->pluck('expected_amount')->all())->toBe(['10.00', '10.00', '10.00']);
    foreach ($preview as $row) {
        $persisted = $entry->occurrences()->where('sequence_number', $row['sequence_number'])->firstOrFail();
        expect($persisted->expected_date->toDateString())->toBe($row['expected_date'])
            ->and($persisted->expected_amount)->toBe($row['expected_amount']);
    }
    foreach ($queries as $query) {
        expect(strtolower(ltrim($query['query'])))->toStartWith('select');
    }
    expect($entry->fresh()->getAttributes())->toBe($entrySnapshot)
        ->and($entry->occurrences()->get()->map->getAttributes()->all())->toBe($occurrences)
        ->and($entry->amountChanges()->count())->toBe($historyCount)
        ->and(Transaction::count())->toBe(0)
        ->and($this->account->fresh()->current_balance)->toBe('1000.00');
});

test('common resolution preserves completed confirmed and customized occurrences during synchronization', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $completed = $entry->occurrences()->where('sequence_number', 2)->firstOrFail();
    $completed->update(['status' => 'completed']);
    $custom = $entry->occurrences()->where('sequence_number', 3)->firstOrFail();
    $custom->update(['expected_amount' => 7]);
    $confirmed = $entry->occurrences()->where('sequence_number', 4)->firstOrFail();
    $confirmed->forceFill(['amount_confirmed_at' => now()])->save();
    $protected = collect([$completed, $custom, $confirmed])->mapWithKeys(fn ($row): array => [$row->id => $row->getAttributes()]);
    app(RecurringEntryManagementService::class)->update($this->actor, $entry, [...$this->payload, 'expected_amount' => 15]);
    foreach ([$completed, $custom, $confirmed] as $row) {
        expect($row->fresh()->getAttributes())->toBe($protected[$row->id]);
    }
    $mutable = $entry->occurrences()->where('sequence_number', 5)->firstOrFail();
    expect($mutable->expected_amount)->toBe('15.00')
        ->and($entry->occurrences()->where('sequence_number', 1)->firstOrFail()->expected_amount)->toBe('10.00')
        ->and(Transaction::count())->toBe(0);
    $preview = app(RecurringEntryOccurrenceGeneratorService::class)->previewScheduleWithinRange($entry->fresh(), CarbonImmutable::parse('2027-02-01'), CarbonImmutable::parse('2027-02-28'));
    expect($preview->sole()['expected_amount'])->toBe($mutable->expected_amount);
});

test('reactivation and its preview resolve manual history using the original billing origin', function (): void {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->actor, [...$this->payload, 'trial_duration' => 7, 'trial_unit' => 'days']);
    $initialBillingDate = $entry->firstBillingDate()->toDateString();
    $service->cancel($entry);
    $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00'));
    $resolver = new class extends RecurringEntryAmountResolver
    {
        public array $origins = [];

        public function resolve(RecurringEntry $entry, CarbonImmutable $date, ?Collection $manualChanges = null): string|float|null
        {
            $this->origins[] = $entry->firstBillingDate()->toDateString();

            return parent::resolve($entry, $date, $manualChanges);
        }
    };
    app()->instance(RecurringEntryAmountResolver::class, $resolver);
    $entry->amountChanges()->create(['previous_amount' => 20, 'new_amount' => 25, 'effective_from' => '2027-01-01', 'currency' => 'EUR']);
    $entry = app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), [...$this->payload, 'start_date' => '2026-12-01', 'expected_amount' => 20]);
    $generator = app(RecurringEntryOccurrenceGeneratorService::class);
    $preview = $generator->previewScheduleWithinRange($entry->fresh(), CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2027-02-28'));
    expect($preview->pluck('expected_amount')->all())->toBe(['20.00', '25.00', '25.00']);
    $generator->generate($entry->fresh(), CarbonImmutable::parse('2027-02-28'));
    foreach ($preview as $row) {
        expect($entry->occurrences()->where('sequence_number', $row['sequence_number'])->sole()->expected_amount)->toBe($row['expected_amount']);
    }
    $before = $entry->fresh();
    $after = clone $before;
    $after->expected_amount = 30;
    $generator->synchronizeFutureOccurrences($after, $before);
    expect(array_unique($resolver->origins))->toBe([$initialBillingDate])
        ->and($entry->fresh()->firstBillingDate()->toDateString())->toBe($initialBillingDate)
        ->and(Transaction::count())->toBe(0);
});
