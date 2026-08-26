<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class UserYearCreationPolicy
{
    public const int MINIMUM_YEAR = 1900;

    public const int MAXIMUM_YEAR = 2200;

    public const int NEXT_YEAR_CREATION_START_MONTH = 11;

    public function maximumCreatableYear(User $user, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $currentYear = min($now->year, self::MAXIMUM_YEAR);

        if (
            $currentYear >= self::MAXIMUM_YEAR
            || $now->month < self::NEXT_YEAR_CREATION_START_MONTH
            || ! $user->years()->where('year', $currentYear)->exists()
        ) {
            return $currentYear;
        }

        return $currentYear + 1;
    }

    public function allows(User $user, int $year, ?CarbonImmutable $now = null): bool
    {
        return $year >= self::MINIMUM_YEAR
            && $year <= $this->maximumCreatableYear($user, $now);
    }
}
