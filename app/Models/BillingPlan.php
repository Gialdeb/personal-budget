<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class BillingPlan extends Model
{
    public const CODE_FREE = 'free';

    public const CODE_SUPPORTER = 'supporter';

    protected $fillable = [
        'code',
        'name',
        'description',
        'grants_supporter_access',
        'interval_unit',
        'duration_count',
        'reminder_days_before_end',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'grants_supporter_access' => 'boolean',
            'duration_count' => 'integer',
            'reminder_days_before_end' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(BillingSubscription::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BillingTransaction::class);
    }

    public function grantsSupporterAccess(): bool
    {
        return (bool) $this->grants_supporter_access;
    }

    public function extendFrom(CarbonInterface $anchor): CarbonInterface
    {
        if ($this->interval_unit === null || $this->duration_count === null) {
            return $anchor;
        }

        return match ($this->interval_unit) {
            'day' => $anchor->addDays($this->duration_count),
            'month' => $anchor->addMonths($this->duration_count),
            'year' => $anchor->addYears($this->duration_count),
            default => throw new InvalidArgumentException("Unsupported billing interval [{$this->interval_unit}]."),
        };
    }

    public function reminderAt(CarbonInterface $endsAt): ?CarbonInterface
    {
        if ($this->reminder_days_before_end === null) {
            return null;
        }

        $candidate = $endsAt->subDays($this->reminder_days_before_end);

        return $candidate->isFuture() ? $candidate : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultDefinitions(): array
    {
        return [
            [
                'code' => self::CODE_FREE,
                'name' => 'Free',
                'description' => 'Default access without an active supporter entitlement.',
                'grants_supporter_access' => false,
                'interval_unit' => null,
                'duration_count' => null,
                'reminder_days_before_end' => null,
                'is_active' => true,
            ],
            [
                'code' => self::CODE_SUPPORTER,
                'name' => 'Supporter',
                'description' => 'A paid donation unlocks one year of supporter status.',
                'grants_supporter_access' => true,
                'interval_unit' => 'year',
                'duration_count' => 1,
                'reminder_days_before_end' => 30,
                'is_active' => true,
            ],
        ];
    }

    public static function ensureDefaults(): void
    {
        foreach (self::defaultDefinitions() as $plan) {
            self::query()->updateOrCreate(
                ['code' => $plan['code']],
                $plan,
            );
        }
    }

    public static function free(): self
    {
        self::ensureDefaults();

        /** @var self $plan */
        $plan = self::query()->where('code', self::CODE_FREE)->firstOrFail();

        return $plan;
    }

    public static function supporter(): self
    {
        self::ensureDefaults();

        /** @var self $plan */
        $plan = self::query()->where('code', self::CODE_SUPPORTER)->firstOrFail();

        return $plan;
    }
}
