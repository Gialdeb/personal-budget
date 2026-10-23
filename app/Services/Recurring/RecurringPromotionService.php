<?php

namespace App\Services\Recurring;

use App\Models\RecurringEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecurringPromotionService
{
    private const ORIGIN_FIELDS = ['start_date', 'trial_duration', 'trial_unit', 'recurrence_type', 'recurrence_interval', 'recurrence_rule'];

    public function __construct(protected RecurringEntryOccurrenceGeneratorService $generator) {}

    /**
     * @return array<string, array<mixed>>
     */
    public static function rules(): array
    {
        return [
            'promotion_confirmation' => ['sometimes', 'nullable', 'string', 'size:64'],
            'promotion' => ['sometimes', 'nullable', 'array:amount,normal_amount,duration,payments,until_date,outcome', 'min:1'],
            'promotion.amount' => ['required_with:promotion', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
            'promotion.normal_amount' => ['required_with:promotion', 'numeric', 'gte:promotion.amount', 'max:999999999999.99', 'decimal:0,2'],
            'promotion.duration' => ['required_with:promotion', Rule::in(['first_payment', 'payments', 'until_date'])],
            'promotion.payments' => ['required_if:promotion.duration,payments', 'exclude_unless:promotion.duration,payments', 'integer', 'min:1', 'max:1000'],
            'promotion.until_date' => ['required_if:promotion.duration,until_date', 'exclude_unless:promotion.duration,until_date', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'promotion.outcome' => ['required_with:promotion', Rule::in(['keep', 'normal', 'unknown'])],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    public function normalize(?array $value, RecurringEntry $schedule, ?RecurringEntry $original = null): ?array
    {
        if ($value === null) {
            return null;
        }
        if ($schedule->entry_type?->value !== 'recurring') {
            throw ValidationException::withMessages(['promotion' => __('recurring_promotion.recurring_only')]);
        }
        $config = Validator::make(['promotion' => Arr::only($value, ['amount', 'normal_amount', 'duration', 'payments', 'until_date', 'outcome'])], self::rules())->validate()['promotion'];
        $origin = $original?->promotion['origin'] ?? null;
        $originWasEditedBeforeBilling = $original !== null && $original->status?->value === 'active'
            && Arr::only($original->attributesToArray(), self::ORIGIN_FIELDS) !== Arr::only($schedule->attributesToArray(), self::ORIGIN_FIELDS)
            && ! $original->periods()->where('kind', 'reactivated')->exists()
            && ! $original->hasProtectedComponentPayments();
        if ($originWasEditedBeforeBilling) {
            $origin = Arr::only($schedule->attributesToArray(), self::ORIGIN_FIELDS);
        }
        if ($origin === null) {
            $originSchedule = $original ? clone $original : clone $schedule;
            if ($original !== null) {
                $firstPeriod = $original->periods()->where('kind', 'created')->orderBy('id')->first();
                if ($firstPeriod !== null) {
                    $originSchedule->fill(Arr::only($firstPeriod->terms, ['recurrence_type', 'recurrence_interval', 'recurrence_rule']));
                }
            }
            $origin = Arr::only($originSchedule->attributesToArray(), self::ORIGIN_FIELDS);
        }
        $originSchedule = clone $schedule;
        $originSchedule->fill($origin);
        $startsOn = $this->generator->billingDateForPayment($originSchedule, 1);
        $endsBefore = $config['duration'] === 'until_date'
            ? CarbonImmutable::parse($config['until_date'])->addDay()
            : $this->generator->billingDateForPayment($originSchedule, ($config['duration'] === 'first_payment' ? 1 : (int) $config['payments']) + 1);
        if ($endsBefore->lte($startsOn)) {
            throw ValidationException::withMessages(['promotion.until_date' => __('recurring_promotion.before_first_payment')]);
        }

        return [...$config, 'amount' => number_format((float) $config['amount'], 2, '.', ''),
            'normal_amount' => number_format((float) $config['normal_amount'], 2, '.', ''),
            'origin' => $origin, 'starts_on' => $startsOn->toDateString(), 'ends_before' => $endsBefore->toDateString()];
    }
}
