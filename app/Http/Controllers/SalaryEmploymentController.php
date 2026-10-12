<?php

namespace App\Http\Controllers;

use App\Http\Requests\Salary\EndSalaryEmploymentRequest;
use App\Http\Requests\Salary\ExplainSalaryDifferenceRequest;
use App\Http\Requests\Salary\UpdateSalaryEmploymentRequest;
use App\Http\Requests\Salary\UpdateSalaryOccurrenceRequest;
use App\Models\SalaryEmployment;
use App\Models\SalaryOccurrenceDetail;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Salary\SalaryEmploymentRevisionService;
use App\Services\Salary\SalaryEmploymentService;
use App\Services\Salary\SalaryOccurrenceService;
use App\Services\Salary\SalaryStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryEmploymentController extends Controller
{
    public function show(Request $request, SalaryEmployment $salaryEmployment, SalaryStatisticsService $statistics): Response
    {
        abort_unless($salaryEmployment->user_id === $request->user()->id, 404);
        $period = $request->validate(['period' => ['nullable', 'date_format:Y-m']])['period'] ?? today()->format('Y-m');
        $day = $request->validate(['day' => ['nullable', 'date_format:Y-m-d']])['day'] ?? null;
        abort_if($day !== null && substr($day, 0, 7) !== $period, 422);
        $salaryEmployment->load(['merchant', 'schedules.recurringEntry.account', 'schedules.recurringEntry.trackedItem', 'schedules.recurringEntry.category']);
        $details = $salaryEmployment->details()->with(['components', 'schedule.recurringEntry', 'occurrence.convertedTransaction', 'occurrence.matchedTransaction', 'occurrence.paymentAllocations' => fn ($query) => $query->valid()->with('transaction')])->whereBetween('pay_period_start', [$period.'-01', CarbonImmutable::parse($period.'-01')->endOfMonth()->toDateString()])->when($day, fn ($query) => $query->whereHas('occurrence', fn ($occurrence) => $occurrence->whereDate('expected_date', $day)))->orderByDesc('pay_period_start')->orderByDesc('id')->paginate(20)->withQueryString();
        $editable = app(AccessibleAccountsQuery::class)->editableIds($request->user());
        $calendarDetails = $salaryEmployment->details()
            ->whereHas('occurrence', fn ($query) => $query->whereBetween('expected_date', [$period.'-01', CarbonImmutable::parse($period.'-01')->endOfMonth()->toDateString()])->whereNotIn('status', ['cancelled', 'skipped', 'refunded']))->with(['components', 'occurrence'])->get();
        $calendarDays = $calendarDetails->groupBy(fn ($detail) => $detail->occurrence->expected_date->toDateString())->map(fn ($group, $date): array => [
            'date' => $date, 'anchor' => 'salary-day-'.$date, 'income_total' => $calendarDetails->pluck('currency')->unique()->count() === 1 ? $group->sum(fn ($detail) => $detail->expectedTotal()) : 0, 'expense_total' => 0,
            'occurrences_count' => $group->count(), 'pending_count' => $group->filter(fn ($detail) => in_array($detail->occurrence->status->value, ['pending', 'generated'], true))->count(),
            'converted_count' => $group->filter(fn ($detail) => $detail->occurrence->converted_transaction_id !== null)->count(), 'occurrences' => [],
        ])->values();

        $details->through(function (SalaryOccurrenceDetail $detail) use ($editable): array {
            $occurrence = $detail->occurrence;
            $transaction = $occurrence->convertedTransaction ?? $occurrence->matchedTransaction;
            $allocations = $occurrence->paymentAllocations->filter(fn ($allocation) => $allocation->transaction?->status?->value === 'confirmed');

            $actual = $transaction?->status?->value === 'confirmed' ? (float) $transaction->amount : ($allocations->isNotEmpty() ? $allocations->sum('amount_cents') / 100 : null);
            $difference = $actual !== null ? round($actual - $detail->expectedTotal(), 2) : null;

            return ['id' => $detail->id, 'label' => $detail->schedule->label, 'kind' => $detail->schedule->kind,
                'pay_period_start' => $detail->pay_period_start->toDateString(), 'pay_period_end' => $detail->pay_period_end->toDateString(),
                'base_expected_amount' => (float) $detail->base_expected_amount, 'currency' => $detail->currency,
                'components' => $detail->components->sortBy('sort_order')->values()->map(fn ($component): array => ['label' => $component->label, 'amount' => (float) $component->amount, 'type' => $component->type]),
                'total' => $detail->expectedTotal(),
                'actual' => $transaction?->status?->value === 'confirmed' ? (float) $transaction->amount : ($allocations->isNotEmpty() ? $allocations->sum('amount_cents') / 100 : null),
                'difference' => $difference, 'can_explain' => $difference !== null && $difference !== 0.0 && $transaction && ! $transaction->refundTransaction()->exists() && in_array((int) $detail->schedule->recurringEntry->account_id, $editable, true),
                'expected_date' => $occurrence->expected_date->toDateString(), 'status' => $occurrence->status->value, 'due_date' => $occurrence->due_date?->toDateString() ?? $occurrence->expected_date->toDateString(),
                'amount_confirmed' => $occurrence->amount_confirmed_at !== null,
                'can_edit' => in_array((int) $detail->schedule->recurringEntry->account_id, $editable, true) && ! $transaction && $allocations->isEmpty() && in_array($occurrence->status->value, ['pending', 'generated'], true),
                'recurring_url' => route('recurring-entries.show', $detail->schedule->recurringEntry->uuid)];
        });

        return Inertia::render('transactions/salary/Show', [
            'employment' => ['uuid' => $salaryEmployment->uuid, 'name' => $salaryEmployment->name, 'employer' => $salaryEmployment->schedules->firstWhere('kind', 'ordinary')?->recurringEntry->trackedItem?->name ?? $salaryEmployment->merchant?->name,
                'start_date' => $salaryEmployment->start_date->toDateString(), 'end_date' => $salaryEmployment->end_date?->toDateString(),
                'annual_gross_salary' => $salaryEmployment->annual_gross_salary, 'annual_gross_salary_currency' => $salaryEmployment->annual_gross_salary_currency,
                'schedules' => $salaryEmployment->schedules->whereNull('valid_to')->values()->map(fn ($schedule): array => ['id' => $schedule->id, 'valid_from' => $schedule->valid_from?->toDateString(), 'next_date' => app(RecurringEntryOccurrenceGeneratorService::class)->nextDateOnOrAfter($schedule->recurringEntry, CarbonImmutable::tomorrow())->toDateString(), 'start_date' => $schedule->recurringEntry->start_date->toDateString(), 'recurrence_type' => $schedule->recurringEntry->recurrence_type->value, 'account_uuid' => $schedule->recurringEntry->account->uuid, 'category_uuid' => $schedule->recurringEntry->category?->uuid, 'reference_uuid' => $schedule->recurringEntry->trackedItem?->uuid, 'category' => $schedule->recurringEntry->category?->displayName(), 'automatic' => (bool) $schedule->recurringEntry->auto_create_transaction, 'label' => $schedule->label, 'kind' => $schedule->kind, 'amount' => (float) $schedule->recurringEntry->expected_amount,
                    'currency' => $schedule->recurringEntry->currency, 'account' => $schedule->recurringEntry->account->name, 'url' => route('recurring-entries.show', $schedule->recurringEntry->uuid)]),
                'can_edit' => $salaryEmployment->schedules->whereNull('valid_to')->every(fn ($schedule): bool => in_array((int) $schedule->recurringEntry->account_id, $editable, true)),
                'can_end' => $salaryEmployment->schedules->every(fn ($schedule): bool => in_array((int) $schedule->recurringEntry->account_id, $editable, true))],
            'calendarDays' => $calendarDays, 'selectedDay' => $day,
            'revisionOptions' => app(RecurringEntryController::class)->formOptionsPayload($request),
            'yearSummary' => $statistics->annual($salaryEmployment, (int) substr($period, 0, 4)),
            'details' => $details, 'comparison' => $statistics->comparison($salaryEmployment, $period),
        ]);
    }

    public function updateOccurrence(UpdateSalaryOccurrenceRequest $request, SalaryEmployment $salaryEmployment, SalaryOccurrenceDetail $detail, SalaryOccurrenceService $service): RedirectResponse
    {
        abort_unless($salaryEmployment->user_id === $request->user()->id && $detail->salary_employment_id === $salaryEmployment->id, 404);
        abort_unless(app(AccessibleAccountsQuery::class)->canEditAccountId($request->user(), (int) $detail->schedule->recurringEntry->account_id), 403);
        $service->update($detail, $request->user(), $request->validated());

        return back()->with('success', __('salary.composition_saved'));
    }

    public function update(UpdateSalaryEmploymentRequest $request, SalaryEmployment $salaryEmployment, SalaryEmploymentRevisionService $service): RedirectResponse
    {
        abort_unless($salaryEmployment->user_id === $request->user()->id, 404);
        $service->update($request->user(), $salaryEmployment, $request->validated());

        return back()->with('success', __('salary.revision_saved'));
    }

    public function explain(ExplainSalaryDifferenceRequest $request, SalaryEmployment $salaryEmployment, SalaryOccurrenceDetail $detail, SalaryOccurrenceService $service): RedirectResponse
    {
        abort_unless($salaryEmployment->user_id === $request->user()->id && $detail->salary_employment_id === $salaryEmployment->id, 404);
        abort_unless(app(AccessibleAccountsQuery::class)->canEditAccountId($request->user(), (int) $detail->schedule->recurringEntry->account_id), 403);
        $service->explain($detail, $request->user(), $request->validated('label'), $request->validated('type'));

        return back()->with('success', __('salary.composition_saved'));
    }

    public function end(EndSalaryEmploymentRequest $request, SalaryEmployment $salaryEmployment, SalaryEmploymentService $service): RedirectResponse
    {
        abort_unless($salaryEmployment->user_id === $request->user()->id, 404);
        foreach ($salaryEmployment->schedules()->with('recurringEntry')->get() as $schedule) {
            abort_unless(app(AccessibleAccountsQuery::class)->canEditAccountId($request->user(), (int) $schedule->recurringEntry->account_id), 403);
        }
        $service->end($salaryEmployment, $request->validated('end_date'));

        return back()->with('success', __('salary.employment_ended'));
    }
}
