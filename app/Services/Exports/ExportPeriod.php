<?php

namespace App\Services\Exports;

use App\Enums\ExportPeriodPresetEnum;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ExportPeriod
{
    public function __construct(
        public readonly ExportPeriodPresetEnum $preset,
        public readonly ?CarbonImmutable $startDate,
        public readonly ?CarbonImmutable $endDate,
    ) {}

    public static function allTime(): self
    {
        return new self(ExportPeriodPresetEnum::ALL_TIME, null, null);
    }

    public static function fromPreset(
        ExportPeriodPresetEnum $preset,
        ?string $startDate = null,
        ?string $endDate = null,
        ?CarbonImmutable $referenceDate = null,
    ): self {
        $referenceDate ??= CarbonImmutable::now(config('app.timezone'));

        return match ($preset) {
            ExportPeriodPresetEnum::ALL_TIME => self::allTime(),
            ExportPeriodPresetEnum::THIS_MONTH => new self(
                $preset,
                $referenceDate->startOfMonth(),
                $referenceDate->endOfMonth(),
            ),
            ExportPeriodPresetEnum::LAST_MONTH => new self(
                $preset,
                $referenceDate->subMonthNoOverflow()->startOfMonth(),
                $referenceDate->subMonthNoOverflow()->endOfMonth(),
            ),
            ExportPeriodPresetEnum::THIS_YEAR => new self(
                $preset,
                $referenceDate->startOfYear(),
                $referenceDate->endOfYear(),
            ),
            ExportPeriodPresetEnum::CUSTOM_RANGE => self::customRange($startDate, $endDate),
        };
    }

    public static function customRange(?string $startDate, ?string $endDate): self
    {
        if ($startDate === null || $endDate === null) {
            throw new InvalidArgumentException('Custom export ranges require both start and end dates.');
        }

        return new self(
            ExportPeriodPresetEnum::CUSTOM_RANGE,
            CarbonImmutable::parse($startDate, config('app.timezone'))->startOfDay(),
            CarbonImmutable::parse($endDate, config('app.timezone'))->endOfDay(),
        );
    }

    public function isAllTime(): bool
    {
        return $this->preset === ExportPeriodPresetEnum::ALL_TIME;
    }

    public function filenameToken(CarbonImmutable $referenceDate): string
    {
        if ($this->preset === ExportPeriodPresetEnum::THIS_MONTH || $this->preset === ExportPeriodPresetEnum::LAST_MONTH) {
            return $this->startDate?->format('Y-m') ?? $referenceDate->format('Y-m');
        }

        if ($this->preset === ExportPeriodPresetEnum::THIS_YEAR) {
            return $this->startDate?->format('Y') ?? $referenceDate->format('Y');
        }

        if ($this->preset === ExportPeriodPresetEnum::CUSTOM_RANGE) {
            return sprintf(
                '%s_to_%s',
                $this->startDate?->format('Y-m-d'),
                $this->endDate?->format('Y-m-d'),
            );
        }

        return sprintf('all-time_%s', $referenceDate->format('Y-m-d'));
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset->value,
            'start_date' => $this->startDate?->toDateString(),
            'end_date' => $this->endDate?->toDateString(),
        ];
    }
}
